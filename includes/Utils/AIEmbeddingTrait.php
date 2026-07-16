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
    // PUBLIC: Single & Batch embedding dispatcher
    // ================================================================

    /**
     * Buat embedding vector untuk satu teks.
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

        return $this->dispatch_embedding( $text, $provider );
    }

    /**
     * Buat embedding untuk BANYAK teks sekaligus (batch).
     *
     * Mengirim seluruh chunk dalam 1 request API jika provider mendukung
     * (OpenAI), atau sequential dengan rate limiting optimal.
     *
     * @param array  $texts    Array of strings to embed.
     * @param string $provider Provider embedding.
     * @return array Array of [ 'text' => string, 'vector' => array|false, 'index' => int ]
     */
    public function create_embeddings_batch( array $texts, $provider = 'openai' ) {
        if ( empty( $provider ) ) {
            $provider = 'openai';
        }

        if ( empty( $texts ) ) {
            return [];
        }

        Logger::log( 'Batch embedding: ' . count( $texts ) . ' chunks via ' . $provider, 'info' );

        switch ( $provider ) {
            case 'openai':
                return $this->openai_embeddings_batch( $texts );
            case 'gemini_001':
            case 'gemini':
                return $this->google_embeddings_batch( $texts );
            case 'hf':
                return $this->sequential_embeddings( $texts, 'hf' );
            default:
                return $this->sequential_embeddings( $texts, $provider );
        }
    }

    /**
     * Dispatcher untuk single embedding (digunakan oleh search).
     */
    private function dispatch_embedding( $text, $provider ) {
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
    // BATCH EMBEDDING
    // ================================================================

    /**
     * Batch embedding untuk OpenAI — kirim seluruh teks dalam 1 request.
     *
     * @param array $texts
     * @return array [ [ 'text' => string, 'vector' => array|false, 'index' => int ] ]
     */
    private function openai_embeddings_batch( array $texts ) {
        $keys_pool = $this->get_keys_pool( 'openai' );

        if ( empty( $keys_pool ) ) {
            Logger::log( 'OpenAI API Key is missing for batch embeddings.', 'error' );
            return $this->build_batch_result( $texts, null );
        }

        // Sanitasi semua teks
        $sanitized = [];
        foreach ( $texts as $t ) {
            $sanitized[] = $this->sanitize_utf8( $t );
        }

        foreach ( $keys_pool as $api_key ) {
            try {
                $response = $this->request_with_backoff( 'POST', 'https://api.openai.com/v1/embeddings', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'input' => $sanitized,  // Array of strings — 1 API call
                        'model' => 'text-embedding-3-small',
                    ],
                ]);

                $body = json_decode( (string) $response->getBody(), true );

                if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
                    // Map response by index
                    $vectors = [];
                    foreach ( $body['data'] as $item ) {
                        $idx = $item['index'];
                        $vectors[ $idx ] = $item['embedding'] ?? false;
                    }

                    $results = [];
                    foreach ( $sanitized as $i => $t ) {
                        $results[] = [
                            'text'   => $t,
                            'vector' => isset( $vectors[ $i ] ) ? $vectors[ $i ] : false,
                            'index'  => $i,
                        ];
                    }

                    Logger::log( 'OpenAI batch embedding sukses: ' . count( $results ) . ' chunks.', 'info' );
                    return $results;
                }
            } catch ( \Exception $e ) {
                Logger::log( 'OpenAI batch embedding error: ' . $e->getMessage() . '. Coba key berikutnya...', 'warning' );
            }
        }

        Logger::log( 'OpenAI batch embedding GAGAL total untuk ' . count( $texts ) . ' chunks.', 'error' );
        return $this->build_batch_result( $texts, null );
    }

    /**
     * Batch embedding untuk Gemini — sequential dengan rate limit optimal.
     *
     * Gemini tidak mendukung batch input, tapi kita optimasi delay:
     * - 1 chunk: tanpa delay
     * - 2+ chunks: delay 1 detik antar request (vs 4 detik sebelumnya)
     *
     * @param array $texts
     * @return array
     */
    private function google_embeddings_batch( array $texts ) {
        $keys_pool = $this->get_keys_pool( 'gemini' );

        if ( empty( $keys_pool ) ) {
            Logger::log( 'Gemini API Key is missing for embeddings.', 'error' );
            return $this->build_batch_result( $texts, null );
        }

        $results = [];
        $count = count( $texts );

        foreach ( $texts as $i => $text ) {
            $sanitized = $this->sanitize_utf8( $text );
            if ( empty( $sanitized ) ) {
                $results[] = [ 'text' => $text, 'vector' => false, 'index' => $i ];
                continue;
            }

            // Delay hanya jika bukan chunk pertama dan ada lebih dari 1 chunk
            if ( $i > 0 && $count > 1 ) {
                Logger::log( 'Gemini batch: jeda 1 detik antar chunk (' . ( $i + 1 ) . '/' . $count . ')...', 'debug' );
                sleep( 1 );
            }

            $vector = $this->google_embedding( $sanitized, 'models/gemini-embedding-001' );
            $results[] = [
                'text'   => $sanitized,
                'vector' => $vector ?: false,
                'index'  => $i,
            ];

            // Jika satu chunk gagal, lanjutkan ke chunk berikutnya
        }

        Logger::log( 'Gemini batch embedding selesai: ' . count( $results ) . ' chunks.', 'info' );
        return $results;
    }

    /**
     * Fallback sequential untuk provider yang tidak mendukung batch.
     *
     * @param array  $texts
     * @param string $provider
     * @return array
     */
    private function sequential_embeddings( array $texts, $provider ) {
        $results = [];
        $count = count( $texts );

        foreach ( $texts as $i => $text ) {
            $sanitized = $this->sanitize_utf8( $text );
            if ( empty( $sanitized ) ) {
                $results[] = [ 'text' => $text, 'vector' => false, 'index' => $i ];
                continue;
            }

            // Micro delay antar chunk untuk mencegah rate limit
            if ( $i > 0 ) {
                usleep( 100000 ); // 0.1s
            }

            $vector = $this->dispatch_embedding( $sanitized, $provider );
            $results[] = [
                'text'   => $sanitized,
                'vector' => $vector ?: false,
                'index'  => $i,
            ];
        }

        Logger::log( 'Sequential batch embedding selesai: ' . count( $results ) . ' chunks via ' . $provider, 'info' );
        return $results;
    }

    /**
     * Helper: bangun array batch result dengan vector seragam.
     */
    private function build_batch_result( array $texts, $default_vector = null ) {
        $results = [];
        foreach ( $texts as $i => $t ) {
            $results[] = [
                'text'   => $t,
                'vector' => $default_vector,
                'index'  => $i,
            ];
        }
        return $results;
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
