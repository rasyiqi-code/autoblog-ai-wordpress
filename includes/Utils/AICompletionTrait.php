<?php

namespace Autoblog\Utils;

/**
 * Trait AICompletionTrait
 *
 * Berisi semua method completion per provider AI:
 * OpenAI, Anthropic, Google Gemini, Groq, HuggingFace, OpenRouter, dan Custom Provider.
 *
 * Di-use oleh AIClient. Mengasumsikan property $client, $openai_key, dll sudah
 * tersedia di class yang menggunakannya.
 *
 * @package Autoblog\Utils
 */
trait AICompletionTrait {

    /**
     * Dapatkan pool API Key yang diinput user untuk provider tertentu (multi-key support).
     *
     * @param string $provider
     * @return array
     */
    private function get_keys_pool( $provider ) {
        // Normalisasi key name untuk backward compatibility
        $check_id = $provider;
        if ( $provider === 'gemini' ) {
            $check_id = 'google';
        } elseif ( $provider === 'hf' ) {
            $check_id = 'huggingface';
        }

        // Ambil dari custom keys
        $custom_keys = get_option( 'autoblog_custom_api_keys', [] );
        $raw_keys    = isset( $custom_keys[$check_id] ) ? $custom_keys[$check_id] : '';
        
        // Fallback ke option standard
        if ( empty( $raw_keys ) ) {
            $raw_keys = get_option( "autoblog_{$provider}_key" );
        }

        if ( empty( $raw_keys ) ) {
            return [];
        }

        // Pecah berdasarkan baris baru atau koma, bersihkan whitespace
        $keys = array_filter( array_map( 'trim', preg_split( '/[\n,]+/', $raw_keys ) ) );
        return array_values( $keys );
    }

    // ================================================================
    // CUSTOM PROVIDER (OpenAI-compatible endpoint dari models.dev)
    // ================================================================

    /**
     * Completion menggunakan provider dinamis (OpenAI-compatible endpoint).
     *
     * @param string $prompt
     * @param string $model
     * @param string $provider
     * @param float  $temperature
     * @param string $system_prompt
     * @return string|false
     */
    public function custom_provider_completion( $prompt, $model, $provider, $temperature = 0.7, $system_prompt = '' ) {
        // Ambil custom endpoint jika didefinisikan oleh user
        $custom_endpoints = get_option( 'autoblog_custom_api_endpoints', array() );
        $api_endpoint     = isset( $custom_endpoints[$provider] ) ? trim( $custom_endpoints[$provider] ) : '';

        if ( empty( $api_endpoint ) ) {
            $providers    = \Autoblog\Utils\ModelCatalog::get_dynamic_providers();
            $p_data       = isset( $providers[$provider] ) ? $providers[$provider] : null;
            $api_endpoint = ( $p_data && ! empty( $p_data['api'] ) ) ? $p_data['api'] : '';
        }

        if ( empty( $api_endpoint ) ) {
            Logger::log( "Endpoint API untuk provider dinamis [{$provider}] tidak ditemukan. Silakan isi Base URL di tab AI Settings.", 'error' );
            return false;
        }

        // Dapatkan pool key untuk provider ini
        $keys_pool = $this->get_keys_pool( $provider );

        if ( empty( $keys_pool ) ) {
            Logger::log( "API Key untuk provider dinamis [{$provider}] belum diisi atau kosong di tab AI Settings.", 'error' );
            return false;
        }

        $url = rtrim( $api_endpoint, '/' ) . '/chat/completions';

        $json_payload = [
            'model'       => $model,
            'temperature' => (float) $temperature,
            'messages'    => [],
        ];

        if ( ! empty( $system_prompt ) ) {
            $json_payload['messages'][] = [ 'role' => 'system', 'content' => $system_prompt ];
        }
        $json_payload['messages'][] = [ 'role' => 'user', 'content' => $prompt ];

        // ── Rotasi Kunci Otomatis (Intra-Provider Rotation) ──
        foreach ( $keys_pool as $index => $api_key ) {
            $key_num = $index + 1;
            Logger::log( "Mencoba request [{$provider}] menggunakan API Key ke-{$key_num} dari pool...", 'debug' );

            try {
                $response = $this->request_with_backoff( 'POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $json_payload,
                ]);

                $body = json_decode( (string) $response->getBody(), true );

                if ( isset( $body['choices'][0]['message']['content'] ) ) {
                    return $body['choices'][0]['message']['content'];
                }
            } catch ( \Exception $e ) {
                Logger::log( "API Key ke-{$key_num} untuk [{$provider}] gagal: " . $e->getMessage() . ". Mencoba key berikutnya...", 'warning' );
            }
        }

        Logger::log( "Semua API Key dalam pool untuk [{$provider}] telah dicoba dan GAGAL.", 'error' );
        return false;
    }

    // ================================================================
    // OPENAI
    // ================================================================

    /**
     * Generate text menggunakan OpenAI GPT.
     *
     * @param string $prompt
     * @param string $model
     * @param float  $temperature
     * @param string $system_prompt
     * @return string|false
     */
    public function openai_completion( $prompt, $model = 'gpt-4o', $temperature = 0.7, $system_prompt = '' ) {
        if ( empty( $this->openai_key ) ) {
            Logger::log( 'OpenAI API Key is missing.', 'error' );
            return false;
        }

        $system_content = ! empty( $system_prompt ) ? $system_prompt : 'You are a helpful assistant.';

        try {
            $response = $this->request_with_backoff( 'POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openai_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => $model,
                    'temperature' => (float) $temperature,
                    'messages'    => [
                        [ 'role' => 'system', 'content' => $system_content ],
                        [ 'role' => 'user',   'content' => $prompt ],
                    ],
                ],
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                return $body['choices'][0]['message']['content'];
            }
        } catch ( \Exception $e ) {
            Logger::log( 'OpenAI API Error: ' . $e->getMessage(), 'error' );
        }

        return false;
    }

    // ================================================================
    // ANTHROPIC
    // ================================================================

    /**
     * Generate text menggunakan Anthropic Claude.
     *
     * @param string $prompt
     * @param string $model
     * @param float  $temperature
     * @param string $system_prompt
     * @return string|false
     */
    public function anthropic_completion( $prompt, $model = 'claude-3-5-sonnet-20240620', $temperature = 0.7, $system_prompt = '' ) {
        if ( empty( $this->anthropic_key ) ) {
            Logger::log( 'Anthropic API Key is missing.', 'error' );
            return false;
        }

        // Anthropic API: system harus dikirim sebagai top-level parameter, bukan di messages
        $messages = [[ 'role' => 'user', 'content' => $prompt ]];

        $request_body = [
            'model'       => $model,
            'max_tokens'  => 2048,
            'temperature' => (float) $temperature,
            'messages'    => $messages,
        ];

        if ( ! empty( $system_prompt ) ) {
            $request_body['system'] = $system_prompt;
        }

        try {
            $response = $this->request_with_backoff( 'POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => $this->anthropic_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ],
                'json' => $request_body,
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body['content'][0]['text'] ) ) {
                return $body['content'][0]['text'];
            }
        } catch ( \Exception $e ) {
            Logger::log( 'Anthropic API Error: ' . $e->getMessage(), 'error' );
        }

        return false;
    }

    // ================================================================
    // GOOGLE GEMINI
    // ================================================================

    /**
     * Generate text menggunakan Google Gemini.
     *
     * @param string $prompt
     * @param string $model
     * @param float  $temperature
     * @param string $system_prompt
     * @return string|false
     */
    public function google_completion( $prompt, $model, $temperature = 0.7, $system_prompt = '' ) {
        if ( empty( $this->gemini_key ) ) {
            Logger::log( 'Google Gemini API Key is missing.', 'error' );
            return false;
        }

        if ( $model === 'auto' || empty( $model ) ) {
            $model = 'gemini-3.1-pro';
        }

        $version = 'v1beta';

        try {
            $url = "https://generativelanguage.googleapis.com/{$version}/models/{$model}:generateContent?key={$this->gemini_key}";

            $postData = [
                'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
                'generationConfig' => [ 'temperature' => (float) $temperature ],
            ];

            if ( ! empty( $system_prompt ) ) {
                $postData['system_instruction'] = [ 'parts' => [ [ 'text' => $system_prompt ] ] ];
            }

            // Tambahkan Search Grounding jika diaktifkan
            if ( get_option( 'autoblog_gemini_grounding', '0' ) === '1' ) {
                $postData['tools'] = [ [ 'google_search' => (object)[] ] ];
                Logger::log( "Gemini ({$version}): Google Search Grounding ENABLED.", 'debug' );
            }

            Logger::log( "Gemini Request Payload ({$version}): " . json_encode( $postData ), 'debug' );

            $response = $this->request_with_backoff( 'POST', $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'json'    => $postData,
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            Logger::log( "Gemini Response Body ({$version}): " . json_encode( $body ), 'debug' );

            if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
                return $body['candidates'][0]['content']['parts'][0]['text'];
            }
        } catch ( \Exception $e ) {
            Logger::log( "Gemini API Error ({$version}): " . $e->getMessage(), 'error' );

            if ( method_exists( $e, 'getResponse' ) && $e->getResponse() ) {
                Logger::log( "Gemini API Error Body ({$version}): " . (string) $e->getResponse()->getBody(), 'error' );
            }
        }

        return false;
    }

    // ================================================================
    // GROQ
    // ================================================================

    /**
     * Generate text menggunakan Groq.
     *
     * @param string $prompt
     * @param string $model
     * @param float  $temperature
     * @return string|false
     */
    public function groq_completion( $prompt, $model, $temperature = 0.7 ) {
        if ( empty( $this->groq_key ) ) {
            Logger::log( 'Groq API Key is missing.', 'error' );
            return false;
        }

        if ( $model === 'auto' ) {
            $model = 'llama-3.3-70b-versatile';
        }

        try {
            $response = $this->request_with_backoff( 'POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groq_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => $model,
                    'temperature' => (float) $temperature,
                    'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
                ],
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                return $body['choices'][0]['message']['content'];
            }
        } catch ( \Exception $e ) {
            Logger::log( 'Groq API Error: ' . $e->getMessage(), 'error' );
        }

        return false;
    }

    // ================================================================
    // HUGGING FACE
    // ================================================================

    /**
     * Generate text menggunakan Hugging Face Inference API.
     *
     * @param string $prompt
     * @param string $model  HF repo ID (misal: mistralai/Mistral-7B-v0.1)
     * @param float  $temperature
     * @return string|false
     */
    public function huggingface_completion( $prompt, $model, $temperature = 0.7 ) {
        if ( empty( $this->hf_key ) ) {
            Logger::log( 'Hugging Face API Key is missing.', 'error' );
            return false;
        }

        try {
            $url = "https://api-inference.huggingface.co/models/{$model}";

            $response = $this->client->post( $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->hf_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'inputs'     => $prompt,
                    'parameters' => [ 'temperature' => (float) $temperature ],
                ],
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body[0]['generated_text'] ) ) {
                return $body[0]['generated_text'];
            }
        } catch ( \Exception $e ) {
            Logger::log( 'HF API Error: ' . $e->getMessage(), 'error' );
        }

        return false;
    }

    // ================================================================
    // OPENROUTER
    // ================================================================

    /**
     * Generate text menggunakan OpenRouter.
     *
     * @param string $prompt
     * @param string $model
     * @param float  $temperature
     * @return string|false
     */
    public function openrouter_completion( $prompt, $model, $temperature = 0.7 ) {
        if ( empty( $this->openrouter_key ) ) {
            Logger::log( 'OpenRouter API Key is missing.', 'error' );
            return false;
        }

        try {
            $response = $this->request_with_backoff( 'POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openrouter_key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => get_site_url(),
                ],
                'json' => [
                    'model'       => $model,
                    'temperature' => (float) $temperature,
                    'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
                ],
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                return $body['choices'][0]['message']['content'];
            }
        } catch ( \Exception $e ) {
            Logger::log( 'OpenRouter API Error: ' . $e->getMessage(), 'error' );
        }

        return false;
    }
}
