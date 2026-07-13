<?php

namespace Autoblog\Utils;

use GuzzleHttp\Client;
use Autoblog\Utils\Logger;

/**
 * AIClient
 *
 * Klien terpusat untuk semua komunikasi dengan layanan AI.
 *
 * Class ini tipis: hanya menyimpan HTTP client, API keys, dan logika inti
 * (request_with_backoff, get_fallback_model, generate_text).
 *
 * Detail completion per-provider ada di AICompletionTrait.
 * Detail embedding ada di AIEmbeddingTrait.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Utils
 */
class AIClient {

    use AICompletionTrait;
    use AIEmbeddingTrait;

    // ================================================================
    // STATE: API keys & HTTP client
    // ================================================================

    private $openai_key;
    private $anthropic_key;
    private $gemini_key;
    private $groq_key;
    private $hf_key;
    private $openrouter_key;

    private $client;

    public function __construct() {
        $openai_pool = $this->get_keys_pool( 'openai' );
        $this->openai_key = ! empty( $openai_pool ) ? $openai_pool[0] : '';

        $anthropic_pool = $this->get_keys_pool( 'anthropic' );
        $this->anthropic_key = ! empty( $anthropic_pool ) ? $anthropic_pool[0] : '';

        $gemini_pool = $this->get_keys_pool( 'gemini' );
        $this->gemini_key = ! empty( $gemini_pool ) ? $gemini_pool[0] : '';

        $groq_pool = $this->get_keys_pool( 'groq' );
        $this->groq_key = ! empty( $groq_pool ) ? $groq_pool[0] : '';

        $hf_pool = $this->get_keys_pool( 'hf' );
        $this->hf_key = ! empty( $hf_pool ) ? $hf_pool[0] : '';

        $openrouter_pool = $this->get_keys_pool( 'openrouter' );
        $this->openrouter_key = ! empty( $openrouter_pool ) ? $openrouter_pool[0] : '';

        // HTTP errors di-handle secara manual agar retry logic berjalan
        $this->client = new Client( [ 'http_errors' => false ] );
    }

    // ================================================================
    // HTTP HELPER: Retry otomatis untuk error 429 dan 5xx
    // ================================================================

    /**
     * Kirim HTTP request dengan Exponential Backoff untuk error 429/5xx.
     *
     * @param string $method
     * @param string $url
     * @param array  $options
     * @param int    $max_retries
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    private function request_with_backoff( $method, $url, $options = [], $max_retries = 2 ) {
        $attempt = 0;

        while ( $attempt <= $max_retries ) {
            try {
                $response    = $this->client->request( $method, $url, $options );
                $status_code = $response->getStatusCode();

                if ( $status_code === 429 ) {
                    $attempt++;
                    if ( $attempt > $max_retries ) {
                        throw new \Exception( 'Error 429 Too Many Requests (Max retries reached).' );
                    }
                    $sleep_time = rand( 3, 5 );
                    Logger::log( "Rate limit 429 hit. Retrying in {$sleep_time}s (Attempt {$attempt}/{$max_retries})...", 'warning' );
                    sleep( (int) $sleep_time );
                    continue;
                }

                if ( $status_code >= 500 ) {
                    $attempt++;
                    if ( $attempt > $max_retries ) {
                        throw new \Exception( 'Max retries reached for 500x error.' );
                    }
                    Logger::log( "Server error {$status_code} at {$url}. Retrying in 2s...", 'warning' );
                    sleep( 2 );
                    continue;
                }

                if ( $status_code >= 400 ) {
                    throw new \Exception( "HTTP Error {$status_code}: " . $response->getBody() );
                }

                return $response;

            } catch ( \GuzzleHttp\Exception\RequestException $e ) {
                throw $e;
            }
        }
    }

    // ================================================================
    // FALLBACK: Pilih model pengganti jika model utama gagal
    // ================================================================

    /**
     * Dapatkan model pengganti berdasarkan key yang tersedia.
     *
     * Prioritas intra-provider dulu (pool model), lalu lintas-provider jika
     * Smart Fallback diaktifkan di pengaturan.
     *
     * @param string $exclude_model Model yang baru gagal.
     * @param string $provider      Provider dari model yang gagal.
     * @return string|false
     */
    public function get_fallback_model( $exclude_model = '', $provider = '' ) {
        $defaults = [
            'openai'     => 'gpt-4o',
            'anthropic'  => 'claude-3-5-sonnet-20240620',
            'gemini'     => 'gemini-3.1-pro',
            'groq'       => 'llama-3.3-70b-versatile',
            'openrouter' => 'openrouter/auto',
        ];

        // Pool model per provider untuk fallback intra-provider
        $pools = [
            'gemini' => [
                'gemini-3.1-pro', 'gemini-3.0-pro', 'gemini-3.0-flash',
                'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite',
                'gemini-2.0-flash', 'gemini-2.0-flash-exp',
            ],
            'groq' => [
                'llama-3.3-70b-versatile', 'llama3-70b-8192', 'mixtral-8x7b-32768',
            ],
            'openai' => [
                'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo',
            ],
        ];

        $has_openai     = ! empty( $this->openai_key );
        $has_anthropic  = ! empty( $this->anthropic_key );
        $has_gemini     = ! empty( $this->gemini_key );
        $has_groq       = ! empty( $this->groq_key );

        // Deteksi provider dari nama model jika tidak di-pass
        $provider_of_failed = $provider;
        if ( empty( $provider_of_failed ) ) {
            if ( strpos( $exclude_model, 'gemini' ) !== false )                                                         { $provider_of_failed = 'gemini'; }
            elseif ( strpos( $exclude_model, 'gpt' ) !== false )                                                        { $provider_of_failed = 'openai'; }
            elseif ( strpos( $exclude_model, 'claude' ) !== false )                                                     { $provider_of_failed = 'anthropic'; }
            elseif ( strpos( $exclude_model, 'llama' ) !== false || strpos( $exclude_model, 'mixtral' ) !== false )     { $provider_of_failed = 'groq'; }
        }

        // 1. INTRA-PROVIDER: coba model cadangan di provider yang sama
        if ( ! empty( $provider_of_failed ) && isset( $pools[ $provider_of_failed ] ) ) {
            $pool = $pools[ $provider_of_failed ];

            if ( $exclude_model === 'auto' || empty( $exclude_model ) ) {
                $next = isset( $pool[1] ) ? $pool[1] : $pool[0];
                Logger::log( "Intra-Provider Fallback ({$provider_of_failed}): 'auto' failed, jumping to: {$next}", 'debug' );
                return $next;
            }

            $found = false;
            foreach ( $pool as $m ) {
                if ( $found ) {
                    Logger::log( "Intra-Provider Fallback ({$provider_of_failed}): next model = {$m}", 'debug' );
                    return $m;
                }
                if ( $m === $exclude_model ) { $found = true; }
            }
            Logger::log( "Intra-Provider Fallback ({$provider_of_failed}): pool habis.", 'info' );
        }

        // 2. CROSS-PROVIDER: pindah ke provider lain (hanya jika Smart Fallback aktif)
        if ( ! get_option( 'autoblog_enable_fallback', '0' ) ) {
            Logger::log( 'Smart Provider Switching (Cross-Provider) DISABLED.', 'info' );
            return false;
        }

        Logger::log( "Cross-provider fallback check. Keys: OpenAI=" . ($has_openai?1:0) . ", Anthropic=" . ($has_anthropic?1:0) . ", Groq=" . ($has_groq?1:0), 'info' );

        if ( $provider_of_failed === 'gemini' ) {
            if ( $has_groq )      { return $defaults['groq']; }
            if ( $has_openai )    { return $defaults['openai']; }
            if ( $has_anthropic ) { return $defaults['anthropic']; }
        }
        if ( $provider_of_failed === 'openai' ) {
            if ( $has_anthropic ) { return $defaults['anthropic']; }
            if ( $has_groq )      { return $defaults['groq']; }
            if ( $has_gemini )    { return $defaults['gemini']; }
        }
        if ( $provider_of_failed === 'anthropic' ) {
            if ( $has_openai ) { return $defaults['openai']; }
            if ( $has_groq )   { return $defaults['groq']; }
        }
        if ( $provider_of_failed === 'groq' ) {
            if ( $has_gemini )  { return $defaults['gemini']; }
            if ( $has_openai )  { return $defaults['openai']; }
        }

        return false;
    }

    // ================================================================
    // CORE: Unified text generation + auto-fallback
    // ================================================================

    /**
     * Hasilkan teks dengan model & provider yang dipilih.
     *
     * Jika model gagal, secara otomatis mencoba fallback (maks 3 lompatan).
     *
     * @param string $prompt
     * @param string $model
     * @param string $provider
     * @param float  $temperature
     * @param string $system_prompt
     * @return string|false
     */
    public function generate_text( $prompt, $model = '', $provider = '', $temperature = 0.7, $system_prompt = '' ) {
        Logger::log( "AIClient: generate_text model=[{$model}] provider=[{$provider}]", 'debug' );

        $prompt        = $this->sanitize_utf8( $prompt );
        $system_prompt = $this->sanitize_utf8( $system_prompt );

        // Deteksi provider dari nama model jika tidak di-pass
        if ( empty( $provider ) ) {
            if ( strpos( $model, 'gpt' ) === 0 )                                              { $provider = 'openai'; }
            elseif ( strpos( $model, 'claude' ) === 0 )                                       { $provider = 'anthropic'; }
            elseif ( strpos( $model, 'gemini' ) === 0 )                                       { $provider = 'gemini'; }
            elseif ( strpos( $model, 'llama' ) === 0 || strpos( $model, 'mixtral' ) === 0 )  { $provider = 'groq'; }
            elseif ( strpos( $model, 'openrouter' ) === 0 )                                   { $provider = 'openrouter'; }
        }

        $result = false;
        switch ( $provider ) {
            case 'openai':
                $result = $this->openai_completion( $prompt, $model, $temperature, $system_prompt );
                break;
            case 'anthropic':
                $result = $this->anthropic_completion( $prompt, $model, $temperature, $system_prompt );
                break;
            case 'gemini':
                $result = $this->google_completion( $prompt, $model, $temperature, $system_prompt );
                break;
            case 'groq':
                $result = $this->groq_completion( $prompt, $model, $temperature, $system_prompt );
                break;
            case 'openrouter':
                $result = $this->openrouter_completion( $prompt, str_replace( 'openrouter/', '', $model ), $temperature );
                break;
            case 'hf':
                $result = $this->huggingface_completion( $prompt, $model, $temperature );
                break;
            default:
                $result = $this->custom_provider_completion( $prompt, $model, $provider, $temperature, $system_prompt );
                break;
        }

        // Auto-fallback dengan circuit breaker (maks 3 lompatan)
        if ( $result === false ) {
            static $fallback_depth = [];
            $call_id = md5( $prompt . $temperature );

            if ( ! isset( $fallback_depth[$call_id] ) ) {
                $fallback_depth[$call_id] = 0;
            }

            if ( $fallback_depth[$call_id] < 3 ) {
                $fallback_model = $this->get_fallback_model( $model, $provider );
                if ( $fallback_model ) {
                    $fallback_depth[$call_id]++;
                    Logger::log( "Model [{$model}] gagal. Auto-redirect → [{$fallback_model}] (Lompatan ke-{$fallback_depth[$call_id]})...", 'warning' );

                    $new_result = $this->generate_text( $prompt, $fallback_model, '', $temperature, $system_prompt );

                    if ( $new_result !== false ) {
                        unset( $fallback_depth[$call_id] );
                    }
                    return $new_result;
                }
            } else {
                Logger::log( 'CRITICAL: Maksimal rentetan Fallback tercapai (3x). Hentikan generate_text.', 'error' );
            }

            unset( $fallback_depth[$call_id] );
        }

        return $result;
    }
}
