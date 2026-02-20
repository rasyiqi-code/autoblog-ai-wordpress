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

		$this->client        = new Client( ['http_errors' => false] ); // We handle HTTP errors ourselves for retries
	}

    /**
     * Helper: Execute HTTP request with Exponential Backoff for 429 errors.
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @param int $max_retries
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function request_with_backoff( $method, $url, $options = [], $max_retries = 2 ) {
        $attempt = 0;
        
        while ( $attempt <= $max_retries ) {
            try {
                $response = $this->client->request( $method, $url, $options );
                $status_code = $response->getStatusCode();

                if ( $status_code === 429 ) {
                    $attempt++;
                    if ( $attempt > $max_retries ) {
                        throw new \Exception( "Error 429 Too Many Requests (Max retries reached)." );
                    }

                    // Short, sane delay instead of exponential lock to save PHP from Timeout
                    $sleep_time = rand( 3, 5 );
                    Logger::log( "Rate limit 429 hit. Retrying in {$sleep_time} seconds (Attempt {$attempt}/{$max_retries}) sebelum meneruskan ke Fallback AI...", 'warning' );
                    sleep( (int) $sleep_time );
                    continue; 
                }

                // If no 429 but maybe a server 500
                if ( $status_code >= 500 ) {
                    $attempt++;
                    if ( $attempt > $max_retries ) {
                        throw new \Exception( "Max retries reached for 500x error." );
                    }
                    Logger::log( "Server error {$status_code} at {$url}. Retrying in 2 seconds...", 'warning' );
                    sleep( 2 );
                    continue;
                }

                // Success or normal client error (400, 401, etc)
                if ( $status_code >= 400 ) {
                   throw new \Exception( "HTTP Error {$status_code}: " . $response->getBody() );
                }

                return $response;

            } catch ( \GuzzleHttp\Exception\RequestException $e ) {
                if ( $e->hasResponse() && $e->getResponse()->getStatusCode() === 429 ) {
                     // Handled below if HTTP errors are NOT false, but config above sets them to false.
                }
                throw $e; 
            }
        }
    }

    /**
     * Generate text using OpenAI GPT.
     *
     * @param string $prompt The prompt to send.
     * @param string $model  The model to use (default: gpt-4o).
     * @return string|false Generated text or false on failure.
     */
	public function openai_completion( $prompt, $model = 'gpt-4o', $temperature = 0.7, $system_prompt = '' ) {

		if ( empty( $this->openai_key ) ) {
			Logger::log( 'OpenAI API Key is missing.', 'error' );
			return false;
		}

        $system_content = ! empty( $system_prompt ) ? $system_prompt : 'You are a helpful assistant.';

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
						[ 'role' => 'system', 'content' => $system_content ],
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
    public function anthropic_completion( $prompt, $model = 'claude-3-5-sonnet-20240620', $temperature = 0.7, $system_prompt = '' ) {
        
        if ( empty( $this->anthropic_key ) ) {
            Logger::log( 'Anthropic API Key is missing.', 'error' );
            return false;
        }

        $messages = [['role' => 'user', 'content' => $prompt]];
        if (!empty($system_prompt)) {
            array_unshift($messages, ['role' => 'system', 'content' => $system_prompt]);
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
                    'messages' => $messages
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
     * @param string $provider      The provider of the failed model.
     * @return string|false A fallback model identifier or false.
     */
    public function get_fallback_model( $exclude_model = '', $provider = '' ) {
        
        // Note: Smart Fallback (Cross-Provider) check is now moved specifically 
        // to the cross-provider section to allow intra-provider pooling to always work.

        $defaults = [
            'openai'     => 'gpt-4o',
            'anthropic'  => 'claude-3-5-sonnet-20240620',
            'gemini'     => 'gemini-3.1-pro', 
            'groq'       => 'llama-3.3-70b-versatile', 
            'openrouter' => 'openrouter/auto',
        ];

        // Define model pools for intra-provider fallback
        $pools = [
            'gemini' => [
                'gemini-3.1-pro',
                'gemini-3.0-pro',
                'gemini-3.0-flash',
                'gemini-2.5-pro',
                'gemini-2.5-flash',
                'gemini-2.5-flash-lite',
                'gemini-2.0-flash',
                'gemini-2.0-flash-exp',
            ],
            'groq' => [
                'llama-3.3-70b-versatile',
                'llama3-70b-8192',
                'mixtral-8x7b-32768',
            ],
            'openai' => [
                'gpt-4o',
                'gpt-4-turbo',
                'gpt-3.5-turbo',
            ]
        ];

        // LOGIC: Retrieve keys first to know what's available
        $has_openai = ! empty( $this->openai_key );
        $has_anthropic = ! empty( $this->anthropic_key );
        $has_gemini = ! empty( $this->gemini_key );
        $has_groq = ! empty( $this->groq_key );
        $has_openrouter = ! empty( $this->openrouter_key );
        
        // 1. INTRA-PROVIDER POOLING: Cek apakah model yang gagal punya cadangan di provider yang sama
        $provider_of_failed = $provider;
        if ( empty( $provider_of_failed ) ) {
            if ( strpos( $exclude_model, 'gemini' ) !== false ) $provider_of_failed = 'gemini';
            elseif ( strpos( $exclude_model, 'gpt' ) !== false ) $provider_of_failed = 'openai';
            elseif ( strpos( $exclude_model, 'claude' ) !== false ) $provider_of_failed = 'anthropic';
            elseif ( strpos( $exclude_model, 'llama' ) !== false || strpos( $exclude_model, 'mixtral' ) !== false || strpos( $exclude_model, 'gemma' ) !== false ) $provider_of_failed = 'groq';
        }

        if ( ! empty( $provider_of_failed ) && isset( $pools[ $provider_of_failed ] ) ) {
            $pool = $pools[ $provider_of_failed ];
            
            // Logika cerdas untuk 'auto':
            // Jika mode 'auto' gagal, jangan coba model pertama di pool lagi 
            // (krena 'auto' biasanya sudah memanggil model tercanggih/pertama di pool).
            if ( $exclude_model === 'auto' || empty( $exclude_model ) ) {
                $next_after_auto = ( isset( $pool[1] ) ) ? $pool[1] : $pool[0];
                Logger::log( "Intra-Provider Fallback ({$provider_of_failed}): 'auto' failed, jumping to: " . $next_after_auto, 'debug' );
                return $next_after_auto;
            }

            $found_current = false;
            foreach ( $pool as $m ) {
                if ( $found_current ) {
                    Logger::log( "Intra-Provider Fallback ({$provider_of_failed}): Found next model: {$m}", 'debug' );
                    return $m;
                }
                if ( $m === $exclude_model ) {
                    $found_current = true;
                }
            }
            Logger::log( "Intra-Provider Fallback ({$provider_of_failed}): Exhausted all models in pool.", 'info' );
        }

        // 2. CROSS-PROVIDER FALLBACK: Pindah ke provider lain jika pool atau provider utama gagal
        // ONLY if Smart Fallback is enabled in settings.
        $enable_fallback = get_option( 'autoblog_enable_fallback', '0' );
        if ( ! $enable_fallback ) {
            Logger::log( 'Smart Provider Switching (Cross-Provider) is DISABLED in settings.', 'info' );
            return false;
        }

        Logger::log( "Checking cross-provider fallback. Keys available: OpenAI=" . ($has_openai?1:0) . ", Anthropic=" . ($has_anthropic?1:0) . ", Groq=" . ($has_groq?1:0), 'info' );

        // If Gemini failed (and exhausted pool), try Groq -> OpenAI
        if ( $provider_of_failed === 'gemini' || strpos( $exclude_model, 'gemini' ) !== false ) {
             if ( $has_groq ) return $defaults['groq'];
             if ( $has_openai ) return $defaults['openai'];
             if ( $has_anthropic ) return $defaults['anthropic'];
        }

        // If OpenAI failed, try Anthropic -> Groq -> Gemini
        if ( $provider_of_failed === 'openai' || strpos( $exclude_model, 'gpt' ) !== false || strpos( $exclude_model, 'openai' ) !== false ) {
             if ( $has_anthropic ) return $defaults['anthropic'];
             if ( $has_groq ) return $defaults['groq'];
             if ( $has_gemini ) return $defaults['gemini'];
        }

        // If Anthropic failed, try OpenAI -> Groq
        if ( $provider_of_failed === 'anthropic' || strpos( $exclude_model, 'claude' ) !== false ) {
             if ( $has_openai ) return $defaults['openai'];
             if ( $has_groq ) return $defaults['groq'];
        }

        // If Groq failed, try Gemini -> OpenAI
        if ( $provider_of_failed === 'groq' || strpos( $exclude_model, 'llama' ) !== false || strpos( $exclude_model, 'mixtral' ) !== false || strpos( $exclude_model, 'gemma' ) !== false ) {
             if ( $has_gemini ) return $defaults['gemini'];
             if ( $has_openai ) return $defaults['openai'];
        }

        return false;
    }

    /**
     * Unified text generation method.
     * Matches model name to provider.
     * 
     * @param string $prompt The user prompt.
     * @param string $model  The model identifier (optional if provider is set).
     * @param string $provider The provider to use (optional).
     * @param float  $temperature Creativity level.
     * @param string $system_prompt The global instructions for the AI.
     * @return string|false
     */
    public function generate_text( $prompt, $model = '', $provider = '', $temperature = 0.7, $system_prompt = '' ) {
        Logger::log( "AIClient: Generating text with model [{$model}] via [{$provider}]", 'debug' );
        
        $prompt = $this->sanitize_utf8( $prompt );
        $system_prompt = $this->sanitize_utf8( $system_prompt );

        // If provider is not passed, try to detect from model name (backward compatibility)
        if ( empty( $provider ) ) {
            if ( strpos( $model, 'gpt' ) === 0 ) $provider = 'openai';
            elseif ( strpos( $model, 'claude' ) === 0 ) $provider = 'anthropic';
            elseif ( strpos( $model, 'gemini' ) === 0 ) $provider = 'gemini';
            elseif ( strpos( $model, 'llama' ) === 0 || strpos( $model, 'mixtral' ) === 0 ) $provider = 'groq';
            elseif ( strpos( $model, 'openrouter' ) === 0 ) $provider = 'openrouter';
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
        }

        // --- GLOBAL AUTO-FALLBACK DENGAN CIRCUIT BREAKER ---
        // Jika model utama gagal, coba model pengganti secara otomatis (Max 3x lompatan)
        if ( $result === false ) {
            // Kita pinjam argumen temperature untuk parameter internal fallback jika tak terlihat,
            // Namun karena kita butuh merubah signature tanpa melanggar caller lain, 
            // kita lacak kedalaman dari stack debug secara cerdas, atau kita gunakan parameter statis lokal.
            static $fallback_depth = [];
            $call_id = md5($prompt . $temperature); // Unique Identifier untuk request ini

            if (!isset($fallback_depth[$call_id])) {
                $fallback_depth[$call_id] = 0;
            }

            if ( $fallback_depth[$call_id] < 10 ) {
                $fallback_model = $this->get_fallback_model( $model, $provider );
                if ( $fallback_model ) {
                    $fallback_depth[$call_id]++;
                    Logger::log( "Model [{$model}] gagal. Auto-redirect -> [{$fallback_model}] (Lompatan ke-{$fallback_depth[$call_id]})...", 'warning' );
                    
                    $new_result = $this->generate_text( $prompt, $fallback_model, '', $temperature, $system_prompt );
                    
                    // Bersihkan memori static jika berhasil
                    if ($new_result !== false) {
                        unset($fallback_depth[$call_id]);
                    }
                    return $new_result;
                }
            } else {
                Logger::log( "CRITICAL: Maksimal rentetan Fallback tercapai (3x). Menghentikan generate_text untuk mencegah system hang/infinite loop.", 'error' );
            }

            // Bersihkan jika error mutlak
            unset($fallback_depth[$call_id]);
        }

        return $result;
    }

    /**
     * Google Gemini Generation.
     */
    public function google_completion( $prompt, $model, $temperature = 0.7, $system_prompt = '' ) {
        if ( empty( $this->gemini_key ) ) {
            Logger::log( 'Google Gemini API Key is missing.', 'error' );
            return false;
        }

        // Handle 'auto' model request explicitly
        if ( $model === 'auto' || empty($model) ) {
            $model = 'gemini-3.1-pro'; // Default smartest model based on user list
        }

        // Grounding with Google Search is a v1beta feature.
        $version = 'v1beta';
        try {
            // Google API: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}
            $url = "https://generativelanguage.googleapis.com/{$version}/models/{$model}:generateContent?key={$this->gemini_key}";
            
            $postData = [
                'contents' => [
                    [ 'parts' => [ [ 'text' => $prompt ] ] ]
                ],
                'generationConfig' => [
                    'temperature' => (float) $temperature,
                ]
            ];

            // Add System Instruction if available (v1beta support)
            if ( ! empty( $system_prompt ) ) {
                $postData['system_instruction'] = [
                    'parts' => [ [ 'text' => $system_prompt ] ]
                ];
            }

            // Add Search Grounding if enabled
            if ( get_option( 'autoblog_gemini_grounding', '0' ) === '1' ) {
                $postData['tools'] = [
                    [
                        'google_search' => (object)[]
                    ]
                ];
                Logger::log( "Gemini ({$version}): Google Search Grounding (google_search) ENABLED with default settings.", 'debug' );
            }

            // DEBUG: Log full payload for deep debug
            Logger::log( "Gemini Request Payload ({$version}): " . json_encode($postData), 'debug' );

            $response = $this->request_with_backoff( 'POST', $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'json' => $postData
            ]);

            $body = json_decode( (string) $response->getBody(), true );

            // DEBUG: Log full response for deep debug
            Logger::log( "Gemini Response Body ({$version}): " . json_encode($body), 'debug' );

            if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
                return $body['candidates'][0]['content']['parts'][0]['text'];
            }

        } catch ( \Exception $e ) {
            $err_msg = $e->getMessage();
            
            Logger::log( "Gemini API Error ({$version}): " . $err_msg, 'error' );
            
            // Log full response if available
            if ( method_exists($e, 'getResponse') && $e->getResponse() ) {
                Logger::log( "Gemini API Error Body ({$version}): " . (string) $e->getResponse()->getBody(), 'error' );
            }
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

        // Handle 'auto' model request explicitly
        if ( $model === 'auto' ) {
            $model = 'llama-3.3-70b-versatile'; // Default smartest/stable model for 'auto'
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
            
            $response = $this->request_with_backoff( 'POST', $url, [
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
