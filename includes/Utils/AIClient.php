<?php

namespace Autoblog\Utils;

use GuzzleHttp\Client;
use Autoblog\Utils\Logger;

/**
 * Handles communication with AI services (OpenAI, Anthropic).
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Utils
 * @author     Rasyiqi
 */
class AIClient {

	private $openai_key;
	private $anthropic_key;
    private $gemini_key;
    private $groq_key;
    private $hf_key;
    private $openrouter_key;

	private $client;

	public function __construct() {
		$this->openai_key    = get_option( 'autoblog_openai_key' );
		$this->anthropic_key = get_option( 'autoblog_anthropic_key' );
        $this->gemini_key    = get_option( 'autoblog_gemini_key' );
        $this->groq_key      = get_option( 'autoblog_groq_key' );
        $this->hf_key        = get_option( 'autoblog_hf_key' );
        $this->openrouter_key = get_option( 'autoblog_openrouter_key' );

		$this->client        = new Client();
	}

    /**
     * Generate text using OpenAI GPT.
     *
     * @param string $prompt The prompt to send.
     * @param string $model  The model to use (default: gpt-4o).
     * @return string|false Generated text or false on failure.
     */
	public function openai_completion( $prompt, $model = 'gpt-4o', $temperature = 0.7 ) {

		if ( empty( $this->openai_key ) ) {
			Logger::log( 'OpenAI API Key is missing.', 'error' );
			return false;
		}

		try {
			$response = $this->client->post( 'https://api.openai.com/v1/chat/completions', [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->openai_key,
					'Content-Type'  => 'application/json',
				],
				'json'    => [
					'model'    => $model,
                    'temperature' => (float) $temperature,
					'messages' => [
						[ 'role' => 'system', 'content' => 'You are a helpful assistant.' ],
						[ 'role' => 'user', 'content' => $prompt ],
					],
				],
			] );

			$body = json_decode( (string) $response->getBody(), true );
			
			if ( isset( $body['choices'][0]['message']['content'] ) ) {
				return $body['choices'][0]['message']['content'];
			}

		} catch ( \Exception $e ) {
			Logger::log( 'OpenAI API Error: ' . $e->getMessage(), 'error' );
		}

		return false;
	}

    /**
     * Generate text using Anthropic Claude.
     * 
     * @param string $prompt The prompt to send.
     * @param string $model The model to use (default: claude-3-5-sonnet-20240620).
     * @return string|false Generated text or false on failure.
     */
    public function anthropic_completion( $prompt, $model = 'claude-3-5-sonnet-20240620', $temperature = 0.7 ) {
        
        if ( empty( $this->anthropic_key ) ) {
            Logger::log( 'Anthropic API Key is missing.', 'error' );
            return false;
        }

        try {
            $response = $this->client->post( 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $this->anthropic_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 2048,
                    'temperature' => (float) $temperature,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]
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

    /**
     * Get a fallback model based on available API keys.
     * Priorities: OpenAI -> Anthropic -> Gemini -> Groq -> OpenRouter -> HF.
     *
     * @param string $exclude_model The model that just failed.
     * @return string|false A fallback model identifier or false.
     */
    public function get_fallback_model( $exclude_model = '' ) {
        
        // Check if Smart Fallback is enabled
        $enable_fallback = get_option( 'autoblog_enable_fallback', '0' );
        if ( ! $enable_fallback ) {
            Logger::log( 'Smart Fallback is DISABLED in settings.', 'warning' );
            return false;
        }

        // Define default models for each provider
        $defaults = [
            'openai'     => 'gpt-4o',
            'anthropic'  => 'claude-3-5-sonnet-20240620',
            'gemini'     => 'gemini-2.5-flash', 
            'groq'       => 'llama3-70b-8192', // Fast & Good Fallback
            'openrouter' => 'openrouter/auto',
        ];

        // LOGIC: Retrieve keys first to know what's available
        $has_openai = ! empty( $this->openai_key );
        $has_anthropic = ! empty( $this->anthropic_key );
        $has_gemini = ! empty( $this->gemini_key );
        $has_groq = ! empty( $this->groq_key );
        $has_openrouter = ! empty( $this->openrouter_key );
        
        Logger::log( "Checking fallback. Keys available: OpenAI=" . ($has_openai?1:0) . ", Anthropic=" . ($has_anthropic?1:0) . ", Groq=" . ($has_groq?1:0), 'info' );

        // 1. If Gemini failed, try Groq first (Fast/Free/Cheap)
        if ( strpos( $exclude_model, 'gemini' ) !== false ) {
             if ( $has_groq ) return $defaults['groq'];
             if ( $has_openai ) return $defaults['openai'];
             if ( $has_anthropic ) return $defaults['anthropic'];
        }

        // 2. If OpenAI failed, try Anthropic -> Groq
        if ( strpos( $exclude_model, 'gpt' ) !== false ) {
             if ( $has_anthropic ) return $defaults['anthropic'];
             if ( $has_groq ) return $defaults['groq'];
             if ( $has_gemini ) return $defaults['gemini'];
        }

        // 3. If Anthropic failed, try OpenAI -> Groq
        if ( strpos( $exclude_model, 'claude' ) !== false ) {
             if ( $has_openai ) return $defaults['openai'];
             if ( $has_groq ) return $defaults['groq'];
        }

        // 4. If Groq failed, try Gemini -> OpenAI
        if ( strpos( $exclude_model, 'llama' ) !== false || strpos( $exclude_model, 'mixtral' ) !== false ) {
             if ( $has_gemini ) return $defaults['gemini'];
             if ( $has_openai ) return $defaults['openai'];
        }

        // Generic Fallback Chain (if model name didn't match above)
        if ( $has_groq && strpos( $exclude_model, 'llama' ) === false ) return $defaults['groq'];
        if ( $has_gemini && strpos( $exclude_model, 'gemini' ) === false ) return $defaults['gemini'];
        if ( $has_openai && strpos( $exclude_model, 'gpt' ) === false ) return $defaults['openai'];

        return false;
    }

    /**
     * Unified text generation method.
     * Matches model name to provider.
     * 
     * @param string $prompt The prompt.
     * @param string $model  The model identifier (optional if provider is set).
     * @param string $provider The provider to use (optional).
     * @return string|false
     */
    public function generate_text( $prompt, $model = '', $provider = '', $temperature = 0.7 ) {
        
        // Sanitasi UTF-8: prompt bisa mengandung teks KB dari PDF yang rusak
        $prompt = $this->sanitize_utf8( $prompt );

        // If provider is not passed, try to detect from model name (backward compatibility)
        if ( empty( $provider ) ) {
            if ( strpos( $model, 'gpt' ) === 0 ) $provider = 'openai';
            elseif ( strpos( $model, 'claude' ) === 0 ) $provider = 'anthropic';
            elseif ( strpos( $model, 'gemini' ) === 0 ) $provider = 'gemini';
            elseif ( strpos( $model, 'llama' ) === 0 || strpos( $model, 'mixtral' ) === 0 ) $provider = 'groq';
            elseif ( strpos( $model, 'openrouter' ) === 0 ) $provider = 'openrouter';
        }

        switch ( $provider ) {
            case 'openai':
                return $this->openai_completion( $prompt, $model, $temperature );
            case 'anthropic':
                return $this->anthropic_completion( $prompt, $model, $temperature );
            case 'gemini':
                return $this->google_completion( $prompt, $model, $temperature );
            case 'groq':
                return $this->groq_completion( $prompt, $model, $temperature );
            case 'openrouter':
                 return $this->openrouter_completion( $prompt, str_replace( 'openrouter/', '', $model ), $temperature );
            case 'hf':
                 return $this->huggingface_completion( $prompt, $model, $temperature );
            default:
                 // Fallback to legacy detection if loop above failed
                 return false;
        }
    }

    /**
     * Google Gemini Generation.
     */
    public function google_completion( $prompt, $model, $temperature = 0.7 ) {
        if ( empty( $this->gemini_key ) ) {
            Logger::log( 'Google Gemini API Key is missing.', 'error' );
            return false;
        }

        try {
            // Google API: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->gemini_key}";
            
            $postData = [
                'contents' => [
                    [ 'parts' => [ [ 'text' => $prompt ] ] ]
                ],
                'generationConfig' => [
                    'temperature' => (float) $temperature,
                ]
            ];

            $response = $this->client->post( $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'json' => $postData
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
                return $body['candidates'][0]['content']['parts'][0]['text'];
            }

        } catch ( \Exception $e ) {
            Logger::log( 'Gemini API Error: ' . $e->getMessage(), 'error' );
        }
        return false;
    }

    /**
     * Groq Generation.
     */
    public function groq_completion( $prompt, $model, $temperature = 0.7 ) {
        if ( empty( $this->groq_key ) ) {
            Logger::log( 'Groq API Key is missing.', 'error' );
            return false;
        }

        try {
            $response = $this->client->post( 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groq_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => (float) $temperature,
                    'messages' => [
                        [ 'role' => 'user', 'content' => $prompt ]
                    ]
                ]
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

    /**
     * Hugging Face Inference API.
     */
    public function huggingface_completion( $prompt, $model, $temperature = 0.7 ) {
        if ( empty( $this->hf_key ) ) {
            Logger::log( 'Hugging Face API Key is missing.', 'error' );
            return false;
        }

        try {
            // For HF, model is usually the repo ID. If just 'hf', we might need a default.
            // But here we assume $model passed is a HF repo ID if it fell through to here.
            // If $model is weird, this URL might fail.
            $url = "https://api-inference.huggingface.co/models/{$model}";
            
            $response = $this->client->post( $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->hf_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [ 
                    'inputs' => $prompt,
                    'parameters' => [ 'temperature' => (float) $temperature ]
                ]
            ]);

            $body = json_decode( (string) $response->getBody(), true );
            
            // HF return format varies
            if ( isset( $body[0]['generated_text'] ) ) {
                return $body[0]['generated_text'];
            }
        } catch ( \Exception $e ) {
            Logger::log( 'HF API Error: ' . $e->getMessage(), 'error' );
        }
        return false;
    }

    /**
     * OpenRouter Generation.
     */
    public function openrouter_completion( $prompt, $model, $temperature = 0.7 ) {
        if ( empty( $this->openrouter_key ) ) {
            Logger::log( 'OpenRouter API Key is missing.', 'error' );
            return false;
        }

        try {
            $response = $this->client->post( 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openrouter_key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => get_site_url(),
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => (float) $temperature,
                    'messages' => [
                        [ 'role' => 'user', 'content' => $prompt ]
                    ]
                ]
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

    /**
     * Generate embedding for text.
     * 
     * @param string $text The text to embed.
     * @param string $provider The provider to use (openai, gemini, hf).
     * @return array|false The embedding vector or false on failure.
     */
    public function create_embedding( $text, $provider = 'openai' ) {
        
        // Normalize provider
        if ( empty( $provider ) ) $provider = 'openai';

        // Sanitasi UTF-8: bersihkan karakter invalid dari PDF/dokumen lain
        // yang bisa menyebabkan json_encode gagal
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

    /**
     * OpenAI Embeddings (text-embedding-3-small)
     */
    private function openai_embedding( $text ) {
        // ... (Keep existing OpenAI logic) ...
        if ( empty( $this->openai_key ) ) {
            Logger::log( 'OpenAI API Key is missing for embeddings.', 'error' );
            return false;
        }

        try {
            $response = $this->client->post( 'https://api.openai.com/v1/embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openai_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'input' => $text,
                    'model' => 'text-embedding-3-small',
                ],
            ] );

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body['data'][0]['embedding'] ) ) {
                return $body['data'][0]['embedding'];
            }

        } catch ( \Exception $e ) {
            Logger::log( 'OpenAI Embedding Error: ' . $e->getMessage(), 'error' );
        }

        return false;
    }


    /**
     * Google Gemini Embeddings (embedding-001)
     */
    private function google_embedding( $text, $model = 'models/gemini-embedding-001' ) {
        if ( empty( $this->gemini_key ) ) {
             Logger::log( 'Gemini API Key is missing for embeddings.', 'error' );
             return false;
        }

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/{$model}:embedContent?key={$this->gemini_key}";
            
            $response = $this->client->post( $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'json' => [
                    'content' => [
                        'parts' => [ [ 'text' => $text ] ]
                    ]
                ]
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            if ( isset( $body['embedding']['values'] ) ) {
                return $body['embedding']['values'];
            }
        } catch ( \Exception $e ) {
            Logger::log( 'Gemini Embedding Error: ' . $e->getMessage(), 'error' );
        }
        return false;
    }

    /**
     * Hugging Face Embeddings (sentence-transformers/all-MiniLM-L6-v2)
     */
    private function huggingface_embedding( $text ) {
        if ( empty( $this->hf_key ) ) {
             Logger::log( 'Hugging Face API Key is missing for embeddings.', 'error' );
             return false;
        }

        try {
            $model = 'sentence-transformers/all-MiniLM-L6-v2';
            $url = "https://api-inference.huggingface.co/pipeline/feature-extraction/{$model}";

            $response = $this->client->post( $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->hf_key,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [ 
                    'inputs' => $text,
                    'options' => [ 'wait_for_model' => true ]
                ]
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            // HF returns the array directly for feature extraction
            if ( is_array( $body ) && count($body) > 0 && is_numeric($body[0]) ) {
                return $body;
            }
        } catch ( \Exception $e ) {
            Logger::log( 'HF Embedding Error: ' . $e->getMessage(), 'error' );
        }
        return false;
    }

    /**
     * Sanitasi teks agar valid UTF-8.
     *
     * PDF extraction sering menghasilkan byte sequence yang bukan valid UTF-8,
     * yang menyebabkan json_encode() gagal ("Malformed UTF-8 characters").
     * Method ini membersihkan karakter yang tidak valid.
     *
     * @param string $text Teks yang mungkin mengandung karakter UTF-8 rusak.
     * @return string Teks yang sudah dibersihkan (hanya valid UTF-8).
     */
    private function sanitize_utf8( $text ) {
        if ( empty( $text ) ) {
            return '';
        }

        // Cara paling reliable: gunakan mb_convert_encoding
        // dari encoding apapun ke UTF-8, karakter invalid akan di-drop
        $clean = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );

        // Fallback: jika masih ada masalah, gunakan regex untuk strip non-UTF8
        // Hapus NULL bytes dan control characters (kecuali newline/tab)
        $clean = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $clean );

        return $clean;
    }

}
