<?php

namespace Autoblog\Sources;

use Autoblog\Interfaces\SourceInterface;
use Autoblog\Utils\Logger;
use Autoblog\Utils\OptionCache;
use Autoblog\Sources\Drivers\DuckDuckGoDriver;
use Autoblog\Sources\Drivers\SerpApiDriver;
use Autoblog\Sources\Drivers\BraveDriver;

/**
 * SearchSource
 *
 * Koordinator pencarian web. Mendelegasikan logika fetch ke driver trait
 * berdasarkan provider yang dikonfigurasi (duckduckgo_free, serpapi, brave).
 *
 * Detail implementasi per-provider ada di:
 * - DuckDuckGoDriver: fetch_duckduckgo_free()
 * - SerpApiDriver:    fetch_serpapi(), process_organic_results(), dll.
 * - BraveDriver:      fetch_brave()
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Sources
 */
class SearchSource implements SourceInterface {

    use DuckDuckGoDriver;
    use SerpApiDriver;
    use BraveDriver;

    // ================================================================
    // STATE
    // ================================================================

    /** @var string Query pencarian */
    private $query;

    /** @var string Keywords wajib ada (comma-separated) */
    private $match_keywords;

    /** @var string Keywords yang harus absen (comma-separated) */
    private $negative_keywords;

    /** @var string Provider aktif (duckduckgo_free | serpapi | brave) */
    private $provider;

    /** @var string SerpApi key */
    private $serpapi_key;

    /** @var string Brave Search API key */
    private $brave_key;

    /** @var \GuzzleHttp\Client|null Guzzle Client untuk testing (dependency injection) */
    private $http_client = null;

    // ================================================================
    // HTTP CLIENT GETTER/SETTER (untuk testing)
    // ================================================================

    /**
     * Set Guzzle Client kustom (misal mock client untuk integration test).
     *
     * @param \GuzzleHttp\Client $client
     */
    public function set_http_client( \GuzzleHttp\Client $client ) {
        $this->http_client = $client;
    }

    /**
     * Dapatkan Guzzle Client. Jika sudah diset via set_http_client(),
     * gunakan itu. Jika tidak, buat instance baru dengan konfigurasi $config.
     *
     * @param array $config
     * @return \GuzzleHttp\Client
     */
    private function get_http_client( $config = [] ) {
        if ( $this->http_client !== null ) {
            return $this->http_client;
        }
        return new \GuzzleHttp\Client( $config );
    }

    // ================================================================
    // CONSTRUCTOR
    // ================================================================

    /**
     * @param string $query
     * @param string $match_keywords
     * @param string $negative_keywords
     */
    public function __construct( $query, $match_keywords = '', $negative_keywords = '' ) {
        // Bersihkan prefix URL yang tidak sengaja masuk dari kolom URL
        $this->query = preg_replace( '#^https?://#', '', $query );
        $this->query = preg_replace( '#^www\.#', '', $this->query );

        $this->match_keywords    = $match_keywords;
        $this->negative_keywords = $negative_keywords;

        $this->provider    = OptionCache::get( 'autoblog_search_provider', 'serpapi' );
        $this->serpapi_key = OptionCache::get( 'autoblog_serpapi_key' );
        $this->brave_key   = OptionCache::get( 'autoblog_brave_key' );
    }

    // ================================================================
    // INTERFACE: Fetch Data
    // ================================================================

    /**
     * Ambil data dari provider yang aktif.
     *
     * @return array
     */
    public function fetch_data() {
        if ( ! $this->validate_source() ) {
            return [];
        }

        switch ( $this->provider ) {
            case 'duckduckgo_free':
                return $this->fetch_duckduckgo_free();
            case 'brave':
                return $this->fetch_brave();
            default:
                // Serpapi (default) — tangkap exception agar pipeline tidak crash
                try {
                    return $this->fetch_serpapi();
                } catch ( \Exception $e ) {
                    Logger::log( 'SerpApi failed: ' . $e->getMessage(), 'error' );
                    return [];
                }
        }
    }

    // ================================================================
    // INTERFACE: Validation & Display
    // ================================================================

    public function validate_source() {
        if ( $this->provider === 'brave' && empty( $this->brave_key ) ) {
            Logger::log( 'Brave Search Key is missing. Search will fail.', 'error' );
            return false;
        }

        if ( $this->provider !== 'duckduckgo_free' && $this->provider !== 'brave' && empty( $this->serpapi_key ) ) {
            Logger::log( 'SerpApi Key is missing. Search will fail.', 'error' );
            return false;
        }

        if ( empty( $this->query ) ) {
            Logger::log( 'Search query is empty.', 'warning' );
            return false;
        }

        return true;
    }

    public function get_display_name() {
        $names = [
            'duckduckgo_free' => 'Web Search (DuckDuckGo Free)',
            'brave'           => 'Web Search (Brave)',
        ];
        return isset( $names[ $this->provider ] ) ? $names[ $this->provider ] : 'Web Search (SerpApi)';
    }

    // ================================================================
    // SHARED HELPERS (diakses oleh driver traits)
    // ================================================================

    /**
     * Bangun struktur item standar untuk hasil AI/answer.
     *
     * @param string $title
     * @param string $content
     * @param string $type
     * @return array
     */
    private function build_item( $title, $content, $type ) {
        return [
            'title'       => $title,
            'content'     => $content,
            'source_type' => $type,
            'source_url'  => $this->query,
            'link'        => '',
            'description' => substr( strip_tags( $content ), 0, 150 ) . '...',
            'guid'        => md5( $content ),
        ];
    }

    /**
     * Cache hasil HTTP request untuk menghindari N+1 fetch ke URL yang sama.
     * Static cache per request — tidak persist antar request.
     *
     * @var array [ url => html_content ]
     */
    private static $http_response_cache = [];

    /**
     * Ambil konten penuh artikel menggunakan Readability (via cURL).
     *
     * N+1 Protection: hasil HTTP di-cache per URL dalam 1 siklus request
     * untuk menghindari fetch ulang URL yang sama dari source berbeda.
     *
     * @param string $url
     * @return string|false
     */
    private function fetch_full_content( $url ) {
        if ( ! class_exists( 'FiveFilters\Readability\Readability' ) ) {
            return false;
        }

        // N+1 fix: cek cache dulu sebelum HTTP request
        $cache_key = md5( $url );
        if ( isset( self::$http_response_cache[ $cache_key ] ) ) {
            Logger::log( "fetch_full_content: cache HIT untuk {$url}", 'debug' );
            $cached = self::$http_response_cache[ $cache_key ];
            if ( $cached === false ) {
                return false;
            }
            // Re-parse Readability untuk konten baru (cache hanya HTML mentah)
            try {
                $readability = new \FiveFilters\Readability\Readability( new \FiveFilters\Readability\Configuration() );
                $readability->parse( $cached );
                return method_exists( $readability, 'getContent' ) ? $readability->getContent() : $cached;
            } catch ( \Exception $e ) {
                return false;
            }
        }

        $ch = curl_init();
        curl_setopt( $ch, \CURLOPT_URL, $url );
        curl_setopt( $ch, \CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, \CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, \CURLOPT_MAXREDIRS, 5 );
        curl_setopt( $ch, \CURLOPT_TIMEOUT, 20 );
        curl_setopt( $ch, \CURLOPT_CONNECTTIMEOUT, 10 );
        curl_setopt( $ch, \CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, \CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, \CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4 );

        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0',
        ];
        curl_setopt( $ch, CURLOPT_USERAGENT, $user_agents[ array_rand( $user_agents ) ] );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Referer: https://www.google.com/',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: cross-site',
            'Sec-Fetch-User: ?1',
        ]);

        $cookie_file = sys_get_temp_dir() . '/autoblog_cookie_' . uniqid() . '.txt';
        curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie_file );
        curl_setopt( $ch, CURLOPT_COOKIEFILE, $cookie_file );

        $html  = curl_exec( $ch );
        $error = curl_error( $ch );
        curl_close( $ch );

        if ( file_exists( $cookie_file ) ) {
            @unlink( $cookie_file );
        }

        // Cache hasil HTTP (termasuk failure) untuk hindari N+1
        self::$http_response_cache[ $cache_key ] = $html ?: false;

        if ( ! $html || $error ) {
            Logger::log( "cURL failed for {$url}: {$error}", 'warning' );
            return false;
        }

        try {
            $readability = new \FiveFilters\Readability\Readability( new \FiveFilters\Readability\Configuration() );
            $readability->parse( $html );
            return method_exists( $readability, 'getContent' ) ? $readability->getContent() : $html;
        } catch ( \Exception $e ) {
            Logger::log( "Readability failed for {$url}: " . $e->getMessage(), 'warning' );
            return false;
        }
    }

    /**
     * Cek apakah teks lolos filter keyword.
     *
     * @param string $text
     * @return bool
     */
    private function passes_filters( $text ) {
        $text = strtolower( $text );

        // Wajib mengandung salah satu match keyword
        if ( ! empty( $this->match_keywords ) ) {
            $keywords = array_filter( array_map( 'trim', explode( ',', $this->match_keywords ) ) );
            $found    = false;
            foreach ( $keywords as $keyword ) {
                if ( strpos( $text, strtolower( $keyword ) ) !== false ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                return false;
            }
        }

        // Tidak boleh mengandung satu pun negative keyword
        if ( ! empty( $this->negative_keywords ) ) {
            $negatives = array_filter( array_map( 'trim', explode( ',', $this->negative_keywords ) ) );
            foreach ( $negatives as $negative ) {
                if ( strpos( $text, strtolower( $negative ) ) !== false ) {
                    return false;
                }
            }
        }

        return true;
    }
}
