<?php

namespace Autoblog\Sources\Drivers;

use Autoblog\Utils\Logger;

/**
 * Trait DuckDuckGoDriver
 *
 * Mengambil hasil pencarian melalui halaman HTML DuckDuckGo (tanpa API key).
 *
 * Strategi request:
 * 1. WordPress HTTP API (wp_remote_get) — paling kompatibel di environment WP.
 * 2. Fallback ke Guzzle jika WP HTTP API tidak tersedia.
 *
 * Di-use oleh SearchSource. Mengasumsikan method passes_filters() dan
 * fetch_full_content() tersedia dari class yang menggunakannya.
 *
 * @package Autoblog\Sources\Drivers
 */
trait DuckDuckGoDriver {

    /**
     * Ambil data dari DuckDuckGo HTML (gratis, tanpa API key).
     *
     * @return array
     */
    private function fetch_duckduckgo_free() {
        $url = 'https://html.duckduckgo.com/html/?q=' . urlencode( $this->query );
        $ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

        Logger::log( "DuckDuckGo: fetch dimulai untuk query [{$this->query}]", 'debug' );

        // ── Ambil HTML via WordPress HTTP API (lebih reliable di lingkungan WP) ──
        $html = $this->ddg_fetch_html( $url, $ua );

        if ( $html === false ) {
            Logger::log( 'DuckDuckGo: gagal mengambil HTML dari semua method.', 'error' );
            return [];
        }

        Logger::log( 'DuckDuckGo: HTML diterima, ukuran=' . strlen( $html ) . ' bytes.', 'debug' );

        return $this->ddg_parse_html( $html );
    }

    // ================================================================
    // FETCH: WordPress HTTP API → Guzzle fallback
    // ================================================================

    /**
     * Ambil HTML dari URL menggunakan WP HTTP API atau Guzzle sebagai fallback.
     *
     * @param string $url
     * @param string $ua
     * @return string|false
     */
    private function ddg_fetch_html( $url, $ua ) {

        // ── Method 1: WordPress HTTP API ──
        if ( function_exists( 'wp_remote_get' ) ) {
            $response = wp_remote_get( $url, [
                'timeout'    => 20,
                'user-agent' => $ua,
                'headers'    => [
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Referer'         => 'https://duckduckgo.com/',
                ],
                'sslverify'  => false,
                'decompress' => true,
                // Paksa IPv4 — cURL kadang prefer ::1 (localhost) di environment Local by Flywheel
                'curl'       => [ CURLOPT_IPRESOLVE => CURLOPT_IPRESOLVE_V4 ],
            ]);

            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                $body = wp_remote_retrieve_body( $response );

                Logger::log( "DuckDuckGo: WP HTTP API response code={$code}, body_len=" . strlen( $body ), 'debug' );

                if ( $code === 200 && ! empty( $body ) ) {
                    return $body;
                }

                if ( $code !== 200 ) {
                    Logger::log( "DuckDuckGo: WP HTTP API status {$code}.", 'warning' );
                }
            } else {
                Logger::log( 'DuckDuckGo: WP HTTP API error: ' . $response->get_error_message(), 'warning' );
            }
        }

        // ── Method 2: Guzzle Fallback ──
        if ( ! class_exists( 'GuzzleHttp\Client' ) ) {
            Logger::log( 'DuckDuckGo: Guzzle tidak tersedia.', 'error' );
            return false;
        }

        try {
            $client   = new \GuzzleHttp\Client( [
                'timeout'     => 20,
                'http_errors' => false,
                'verify'      => false,
                // Paksa IPv4 — hindari ::1 dari DNS Local by Flywheel
                'curl'        => [ CURLOPT_IPRESOLVE => CURLOPT_IPRESOLVE_V4 ],
            ]);
            $response = $client->get( $url, [
                'headers' => [
                    'User-Agent'      => $ua,
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Referer'         => 'https://duckduckgo.com/',
                ],
            ]);

            $code = $response->getStatusCode();
            $body = (string) $response->getBody();

            Logger::log( "DuckDuckGo: Guzzle response code={$code}, body_len=" . strlen( $body ), 'debug' );

            if ( $code === 200 && ! empty( $body ) ) {
                return $body;
            }

            Logger::log( "DuckDuckGo: Guzzle status {$code}.", 'warning' );

        } catch ( \Throwable $e ) {
            Logger::log( 'DuckDuckGo: Guzzle exception: ' . get_class( $e ) . ' — ' . $e->getMessage(), 'error' );
        }

        return false;
    }

    // ================================================================
    // PARSE: HTML → items array
    // ================================================================

    /**
     * Parse HTML DuckDuckGo menjadi array item hasil pencarian.
     *
     * @param string $html
     * @return array
     */
    private function ddg_parse_html( $html ) {
        $dom = new \DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        $xpath = new \DOMXPath( $dom );

        // Coba selector utama
        $nodes = $xpath->query( "//div[contains(@class, 'web-result')]" );

        // Fallback: DuckDuckGo kadang ganti ke format berbeda
        if ( ! $nodes || $nodes->length === 0 ) {
            $nodes = $xpath->query( "//div[contains(@class, 'result') and not(contains(@class, 'result--more'))]" );
            Logger::log( 'DuckDuckGo: selector utama gagal, coba fallback selector.', 'debug' );
        }

        if ( ! $nodes || $nodes->length === 0 ) {
            Logger::log( 'DuckDuckGo: tidak ada node hasil ditemukan di HTML.', 'warning' );

            // Debug: log beberapa class <div> yang ada
            $all_divs = $xpath->query( '//div[@class]' );
            $sample   = [];
            if ( $all_divs ) {
                foreach ( $all_divs as $i => $div ) {
                    if ( $i >= 10 ) { break; }
                    $sample[] = $div->getAttribute('class');
                }
            }
            Logger::log( 'DuckDuckGo HTML classes sample: ' . implode( ' | ', $sample ), 'debug' );

            return [];
        }

        Logger::log( 'DuckDuckGo: ditemukan ' . $nodes->length . ' node hasil.', 'debug' );

        $items = [];
        $count = 0;

        foreach ( $nodes as $node ) {
            if ( $count >= 3 ) {
                break;
            }

            // Cari link judul — coba beberapa variasi selector
            $title_node = $xpath->query( ".//a[contains(@class, 'result__a')]", $node );
            if ( ! $title_node || $title_node->length === 0 ) {
                $title_node = $xpath->query( ".//a[contains(@class, 'result-link')]", $node );
            }
            if ( ! $title_node || $title_node->length === 0 ) {
                $title_node = $xpath->query( ".//h2/a", $node );
            }

            if ( ! $title_node || $title_node->length === 0 ) {
                continue;
            }

            $snippet_node = $xpath->query( ".//a[contains(@class, 'result__snippet')]", $node );
            if ( ! $snippet_node || $snippet_node->length === 0 ) {
                $snippet_node = $xpath->query( ".//div[contains(@class, 'result__snippet')]", $node );
            }

            $title = trim( $title_node->item(0)->textContent );
            $link  = $title_node->item(0)->getAttribute('href');

            // Ekstrak URL asli dari parameter uddg
            if ( preg_match( '/uddg=(https?[^&]+)/', $link, $matches ) ) {
                $link = urldecode( $matches[1] );
            }

            $snippet = '';
            if ( $snippet_node && $snippet_node->length > 0 ) {
                $snippet = trim( $snippet_node->item(0)->textContent );
            }

            $full_content = ! empty( $link ) ? $this->fetch_full_content( $link ) : '';
            if ( empty( $full_content ) ) {
                $full_content = $snippet;
            }

            if ( empty( $title ) && empty( $full_content ) ) {
                continue;
            }

            if ( $this->passes_filters( $title . ' ' . $full_content ) ) {
                $items[] = [
                    'title'       => $title,
                    'link'        => $link,
                    'description' => $snippet,
                    'content'     => $full_content,
                    'source_type' => 'duckduckgo_free',
                    'source_url'  => $this->query,
                ];
                $count++;
            }
        }

        Logger::log( 'DuckDuckGo: berhasil parsing ' . count( $items ) . ' item.', 'info' );
        return $items;
    }
}
