<?php

namespace Autoblog\Utils;

/**
 * Trait AIEmbeddingTrait
 *
 * Berisi semua method untuk membuat embedding vector:
 * OpenAI, Google Gemini, dan HuggingFace.
 *
 * Di-use oleh AIClient. Mengasumsikan property $client, $openai_key,
 * $gemini_key, $hf_key sudah tersedia di class yang menggunakannya.
 *
 * @package Autoblog\Utils
 */
trait AIEmbeddingTrait {

    // ================================================================
    // PUBLIC: Dispatcher embedding
    // ================================================================

    /**
     * Buat embedding vector untuk teks.
     *
     * @param string $text     Teks yang akan di-embed.
     * @param string $provider Provider embedding (openai, gemini_001, hf).
     * @return array|false
     */
    public function create_embedding( $text, $provider = 'openai' ) {
        if ( empty( $provider ) ) {
            $provider = 'openai';
        }

        // Sanitasi UTF-8 sebelum dikirim ke API
        $text = $this->sanitize_utf8( $text );

        if ( empty( $text ) ) {
            Logger::log( 'Embedding skipped: text kosong setelah sanitasi UTF-8.', 'warning' );
            return false;
        }

        switch ( $provider ) {
            case 'openai':
                return $this->openai_embedding( $text );
            case 'gemini_001':
            case 'gemini':
                return $this->google_embedding( $text, 'models/gemini-embedding-001' );
            case 'hf':
                return $this->huggingface_embedding( $text );
            default:
                return $this->openai_embedding( $text );
        }
    }

    // ================================================================
    // PRIVATE: Implementasi per provider
    // ================================================================

    /**
     * OpenAI Embeddings (text-embedding-3-small).
     *
     * @param string $text
     * @return array|false
     */
    private function openai_embedding( $text ) {
        $keys_pool = $this->get_keys_pool( 'openai' );

        if ( empty( $keys_pool ) ) {
            Logger::log( 'OpenAI API Key is missing for embeddings.', 'error' );
            return false;
        }

        foreach ( $keys_pool as $index => $api_key ) {
            $key_num = $index + 1;
            Logger::log( "Mencoba request embedding [openai] menggunakan API Key ke-{$key_num} dari pool...", 'debug' );

            try {
                $response = $this->request_with_backoff( 'POST', 'https://api.openai.com/v1/embeddings', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'input' => $text,
                        'model' => 'text-embedding-3-small',
                    ],
                ]);

                $body = json_decode( (string) $response->getBody(), true );

                if ( isset( $body['data'][0]['embedding'] ) ) {
                    return $body['data'][0]['embedding'];
                }
            } catch ( \Exception $e ) {
                Logger::log( "API Key ke-{$key_num} untuk embedding [openai] gagal: " . $e->getMessage() . ". Mencoba key berikutnya...", 'warning' );
            }
        }

        return false;
    }

    /**
     * Google Gemini Embeddings (gemini-embedding-001).
     *
     * @param string $text
     * @param string $model
     * @return array|false
     */
    private function google_embedding( $text, $model = 'models/gemini-embedding-001' ) {
        $keys_pool = $this->get_keys_pool( 'gemini' );

        if ( empty( $keys_pool ) ) {
            Logger::log( 'Gemini API Key is missing for embeddings.', 'error' );
            return false;
        }

        foreach ( $keys_pool as $index => $api_key ) {
            $key_num = $index + 1;
            Logger::log( "Mencoba request embedding [gemini] menggunakan API Key ke-{$key_num} dari pool...", 'debug' );

            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/{$model}:embedContent?key={$api_key}";

                $response = $this->request_with_backoff( 'POST', $url, [
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'json'    => [
                        'content' => [ 'parts' => [ [ 'text' => $text ] ] ],
                    ],
                ]);

                $body = json_decode( (string) $response->getBody(), true );

                if ( isset( $body['embedding']['values'] ) ) {
                    return $body['embedding']['values'];
                }
            } catch ( \Exception $e ) {
                Logger::log( "API Key ke-{$key_num} untuk embedding [gemini] gagal: " . $e->getMessage() . ". Mencoba key berikutnya...", 'warning' );
            }
        }

        return false;
    }

    /**
     * HuggingFace Embeddings (sentence-transformers/all-MiniLM-L6-v2).
     *
     * @param string $text
     * @return array|false
     */
    private function huggingface_embedding( $text ) {
        $keys_pool = $this->get_keys_pool( 'huggingface' );

        if ( empty( $keys_pool ) ) {
            Logger::log( 'Hugging Face API Key is missing for embeddings.', 'error' );
            return false;
        }

        foreach ( $keys_pool as $index => $api_key ) {
            $key_num = $index + 1;
            Logger::log( "Mencoba request embedding [huggingface] menggunakan API Key ke-{$key_num} dari pool...", 'debug' );

            try {
                $model = 'sentence-transformers/all-MiniLM-L6-v2';
                $url   = "https://api-inference.huggingface.co/pipeline/feature-extraction/{$model}";

                $response = $this->client->post( $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'inputs'  => $text,
                        'options' => [ 'wait_for_model' => true ],
                    ],
                ]);

                $body = json_decode( (string) $response->getBody(), true );

                // HF feature-extraction mengembalikan array langsung
                if ( is_array( $body ) && count( $body ) > 0 && is_numeric( $body[0] ) ) {
                    return $body;
                }
            } catch ( \Exception $e ) {
                Logger::log( "API Key ke-{$key_num} untuk embedding [huggingface] gagal: " . $e->getMessage() . ". Mencoba key berikutnya...", 'warning' );
            }
        }

        return false;
    }

    // ================================================================
    // UTILITY: Sanitasi UTF-8
    // ================================================================

    /**
     * Sanitasi teks agar valid UTF-8.
     *
     * PDF extraction sering menghasilkan byte sequence yang bukan valid UTF-8,
     * yang menyebabkan json_encode() gagal ("Malformed UTF-8 characters").
     *
     * @param string $text
     * @return string
     */
    private function sanitize_utf8( $text ) {
        if ( empty( $text ) ) {
            return '';
        }

        // Konversi ke UTF-8, karakter invalid di-drop
        $clean = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );

        // Strip NULL bytes dan control characters (kecuali newline/tab)
        $clean = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $clean );

        return $clean;
    }
}
