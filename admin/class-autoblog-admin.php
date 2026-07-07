<?php

namespace Autoblog\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin
 * @author     Rasyiqi
 */
class Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version           The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Autoblog_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Autoblog_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/autoblog-admin.css', array(), $this->version, 'all' );
		
		// Load khusus halaman taxonomy tools
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'posts_page_autoblog-taxonomy-tools' ) {
			wp_enqueue_style( $this->plugin_name . '-taxonomy', plugin_dir_url( __FILE__ ) . 'css/autoblog-admin-taxonomy.css', array(), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Hanya load di halaman plugin ini
		$screen = get_current_screen();
		$allowed_screens = array(
			'toplevel_page_' . $this->plugin_name,
			'posts_page_autoblog-taxonomy-tools'
		);

		if ( ! $screen || ! in_array( $screen->id, $allowed_screens ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/autoblog-admin.js',
			array( 'jquery' ),
			$this->version,
			true // Load di footer
		);

		// Pass AJAX URL dan nonce ke JavaScript
		wp_localize_script( $this->plugin_name, 'autoblog_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'autoblog_ajax_nonce' ),
		));

		// Anti-conflict: WordPress kadang memuat script inline-edit-post secara paksa di edit.php?page=...
		// yang memicu error inlineEditPost is not defined.
		if ( $screen->id === 'posts_page_autoblog-taxonomy-tools' ) {
			wp_dequeue_script( 'inline-edit-post' );
			
			// Sebagai jaring pengaman terakhir, kita inject dummy object via inline script
			wp_add_inline_script( $this->plugin_name, 'var inlineEditPost = { init: function(){} };', 'before' );
		}
	}


	/**
	 * AJAX Handler: Jalankan pipeline tanpa reload halaman.
	 *
	 * Dipanggil via wp_ajax_autoblog_run_pipeline dari JavaScript.
	 * Menggunakan nonce untuk keamanan.
	 *
	 * @since    1.1.0
	 */
	public function ajax_run_pipeline() {
		$this->handle_ajax_pipeline_call( 'run_pipeline' );
	}

	/**
	 * AJAX Handler: AI-powered Taxonomy Prediction based on post title.
	 */
	public function ajax_ai_predict_taxonomy() {
		check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Akses ditolak.' ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : array();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => 'Tidak ada post yang dipilih.' ) );
		}

		// Prevent execution timeout and memory issues during bulk AI operations
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '512M' );

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Utils/AIClient.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Utils/Logger.php';
		
		$ai_client = new \Autoblog\Utils\AIClient();
		
		// Get existing categories
		$categories = get_categories( array( 'hide_empty' => false ) );
		$cat_list = array();
		foreach ( $categories as $cat ) {
			$cat_list[] = $cat->name;
		}
		$cat_context = implode( ', ', $cat_list );

		$provider = get_option( 'autoblog_ai_provider', 'openai' );
		$model_option_name = 'autoblog_' . $provider . '_model';
		$model = get_option( $model_option_name, 'gpt-4o' );

		$count = 0;
		$failed = 0;
		foreach ( $post_ids as $post_id ) {
			try {
				$post = get_post( $post_id );
				if ( ! $post ) {
					$failed++;
					continue;
				}

				$system_prompt = "You are a WordPress SEO Specialist. Your task is to categorize and tag a blog post based ONLY on its title.\n";
				$system_prompt .= "Select 1 most relevant category from the provided list, and provide 3 to 5 relevant tags.\n";
				$system_prompt .= "Return ONLY a valid JSON object in this format:\n";
				$system_prompt .= "{\"category\": \"Category Name\", \"tags\": [\"tag1\", \"tag2\"]}";

				$user_prompt = "Post Title: \"{$post->post_title}\"\n\nAvailable Categories: [{$cat_context}]\n\nJSON Output:";

				$response_text = $ai_client->generate_text( $user_prompt, $model, $provider, 0.3, $system_prompt );

				if ( $response_text ) {
					// Strip markdown code blocks jika AI membungkus output JSON
					$response_text = preg_replace( '/^```(?:json)?\s*$/m', '', $response_text );
					$response_text = trim( $response_text );
					
					$json_data = json_decode( $response_text, true );

					if ( $json_data && isset( $json_data['category'] ) ) {
						// 1. Kategori
						$predicted_cat = trim( $json_data['category'], " \n\r\t\"'[]" );
						$term = get_term_by( 'name', $predicted_cat, 'category' );
						
						if ( ! $term ) {
							$term = get_term_by( 'slug', sanitize_title( $predicted_cat ), 'category' );
						}

						$success = false;
						if ( $term && ! is_wp_error( $term ) ) {
							wp_set_post_categories( $post_id, array( $term->term_id ) );
							$success = true;
							\Autoblog\Utils\Logger::log( "AI Predicted category '{$term->name}' for post ID {$post_id}", 'info' );
						}

						// 2. Tags
						if ( isset( $json_data['tags'] ) && is_array( $json_data['tags'] ) ) {
							// Append = false (timpa tag lama dengan tag baru hasil prediksi)
							wp_set_post_tags( $post_id, $json_data['tags'], false );
							$success = true;
							\Autoblog\Utils\Logger::log( "AI Predicted tags [" . implode(', ', $json_data['tags']) . "] for post ID {$post_id}", 'info' );
						}

						if ( $success ) {
							$count++;
						} else {
							$failed++;
						}
					} else {
						$failed++;
						\Autoblog\Utils\Logger::log( "Error processing post ID {$post_id}: Invalid JSON Response -> " . $response_text, 'error' );
					}
				} else {
					$failed++;
				}
			} catch ( \Throwable $e ) {
				$failed++;
				\Autoblog\Utils\Logger::log( "Error processing post ID {$post_id}: " . $e->getMessage(), 'error' );
			}
		}

		if ( $count > 0 ) {
			$msg = "AI berhasil memprediksi dan menetapkan kategori untuk {$count} pos.";
			if ( $failed > 0 ) {
				$msg .= " ({$failed} gagal).";
			}
			wp_send_json_success( array(
				'message' => $msg,
			));
		} else {
			wp_send_json_error( array(
				'message' => "Gagal memprediksi. {$failed} pos dilewati. Cek log.",
			));
		}
	}

	/**
	 * AJAX Handler: Ingestion Phase.
	 */
	public function ajax_run_collector() {
		$this->handle_ajax_pipeline_call( 'run_ingestion_phase' );
	}

	/**
	 * AJAX Handler: Ideation Phase.
	 */
	public function ajax_run_ideator() {
		$this->handle_ajax_pipeline_call( 'run_ideation_phase' );
	}

	/**
	 * AJAX Handler: Production Phase.
	 */
	public function ajax_run_writer() {
		$this->handle_ajax_pipeline_call( 'run_production_phase' );
	}

	/**
	 * AJAX Handler: Test Gemini Grounding natively.
	 */
	public function ajax_test_gemini_grounding() {
		check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Akses ditolak.' ) );
		}

		$prompt = isset( $_POST['prompt'] ) ? sanitize_text_field( $_POST['prompt'] ) : '';
		$model  = isset( $_POST['model'] ) ? sanitize_text_field( $_POST['model'] ) : 'gemini-3.1-pro';

		if ( empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => 'Prompt tidak boleh kosong.' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Utils/AIClient.php';
		$client = new \Autoblog\Utils\AIClient();
		
		// Paksa nyalakan grounding untuk test ini tanpa peduli setting global
		add_filter( 'option_autoblog_gemini_grounding', function() { return '1'; } );

		$result = $client->generate_text( $prompt, $model, 'gemini' );

		if ( $result ) {
			wp_send_json_success( array( 'answer' => $result ) );
		} else {
			wp_send_json_error( array( 'message' => 'Gagal mendapatkan respon dari Gemini. Cek Logs untuk detail error.' ) );
		}
	}

	/**
	 * Helper to handle AJAX pipeline calls consistently.
	 */
	private function handle_ajax_pipeline_call( $method ) {
		check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Akses ditolak.' ) );
		}

		// Prevent execution timeout and memory issues during long AI operations
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '512M' );
		// Bug #15 Fix: Dihapus display_errors dan error_reporting untuk keamanan production
		// Informasi error PHP sudah dicatat ke error_log, tidak perlu dikirim ke response AJAX

		try {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Core/Runner.php';
			// Bug #8 Fix: Sanitasi overrides dari POST untuk mencegah injeksi parameter
			$raw_overrides = isset( $_POST['overrides'] ) && is_array( $_POST['overrides'] ) ? $_POST['overrides'] : array();
			$overrides = array_map( 'sanitize_text_field', $raw_overrides );

			// Petakan method ke hook internal
			$hook_name = '';
			if ( $method === 'run_pipeline' ) {
				$hook_name = 'autoblog_run_pipeline';
			} elseif ( $method === 'run_ingestion_phase' ) {
				$hook_name = 'autoblog_run_collector';
			} elseif ( $method === 'run_ideation_phase' ) {
				$hook_name = 'autoblog_run_ideator';
			} elseif ( $method === 'run_production_phase' ) {
				$hook_name = 'autoblog_run_writer';
			}

			if ( $hook_name ) {
				// Jadwalkan di background (asinkron) melalui WP-Cron agar Nginx tidak timeout (503)
				wp_schedule_single_event( time(), $hook_name, array( $overrides ) );
				spawn_cron(); // Pancing (trigger) cron untuk mulai bekerja sekarang juga

				\Autoblog\Utils\Logger::log( "AJAX: Task '{$method}' dialihkan ke background processing via hook '{$hook_name}'.", 'info' );

				wp_send_json_success( array(
					'message' => 'Proses dialihkan ke background. Silakan pantau Log di bawah.',
				));
			} else {
				wp_send_json_error( array( 'message' => 'Method ' . $method . ' tidak siap untuk mode background.' ) );
			}
		} catch ( \Throwable $e ) {
			\Autoblog\Utils\Logger::log( 'AJAX Pipeline Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
			wp_send_json_error( array(
				'message' => 'Fatal Error: ' . $e->getMessage() . ' (See debug.log for details)',
			));
		}
	}

	/**
	 * AJAX Handler: Test API connection for static utility keys and custom dynamic LLMs.
	 */
	public function ajax_test_api_connection() {
		check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Akses ditolak.' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';

		if ( empty( $provider ) || empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Provider atau API Key tidak boleh kosong.' ) );
		}

		$result = $this->validate_api_key( $provider, $api_key );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => 'Sukses terhubung!' ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Memvalidasi API key dengan melakukan request HTTP minimal ke endpoint provider.
	 *
	 * @param string $provider
	 * @param string $api_key
	 * @return array
	 */
	private function validate_api_key( $provider, $api_key ) {
		$client = new \GuzzleHttp\Client( array( 'http_errors' => false, 'timeout' => 8 ) );

		// 1. SerpApi
		if ( $provider === 'serpapi' ) {
			$url = "https://serpapi.com/search.json?q=test&api_key=" . $api_key;
			try {
				$response = $client->get( $url );
				$body = json_decode( (string) $response->getBody(), true );
				if ( isset( $body['error'] ) ) {
					return array( 'success' => false, 'message' => 'SerpApi Error: ' . $body['error'] );
				}
				if ( $response->getStatusCode() === 200 ) {
					return array( 'success' => true, 'message' => 'OK' );
				}
				return array( 'success' => false, 'message' => 'HTTP Status ' . $response->getStatusCode() );
			} catch ( \Exception $e ) {
				return array( 'success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage() );
			}
		}

		// 2. Pexels
		if ( $provider === 'pexels' ) {
			$url = "https://api.pexels.com/v1/search?query=nature&per_page=1";
			try {
				$response = $client->get( $url, array(
					'headers' => array( 'Authorization' => $api_key )
				));
				$body = json_decode( (string) $response->getBody(), true );
				if ( isset( $body['error'] ) ) {
					return array( 'success' => false, 'message' => 'Pexels Error: ' . $body['error'] );
				}
				if ( $response->getStatusCode() === 200 ) {
					return array( 'success' => true, 'message' => 'OK' );
				}
				return array( 'success' => false, 'message' => 'HTTP Status ' . $response->getStatusCode() );
			} catch ( \Exception $e ) {
				return array( 'success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage() );
			}
		}

		// 3. LLM Providers (OpenAI-compatible / models.dev dinamis)
		$providers = self::get_dynamic_providers();
		$p_data = isset( $providers[$provider] ) ? $providers[$provider] : null;
		
		$api_endpoint = '';
		if ( $p_data && ! empty( $p_data['api'] ) ) {
			$api_endpoint = $p_data['api'];
		} else {
			$fallbacks = self::get_fallback_providers();
			if ( isset( $fallbacks[$provider]['api'] ) ) {
				$api_endpoint = $fallbacks[$provider]['api'];
			}
		}

		if ( empty( $api_endpoint ) ) {
			return array( 'success' => false, 'message' => 'Provider API Endpoint tidak ditemukan.' );
		}

		$models = self::get_merged_models();
		$dev_key = $provider;
		if ( $provider === 'gemini' || $provider === 'google' ) {
			$dev_key = 'google';
		} elseif ( $provider === 'huggingface' || $provider === 'hf' ) {
			$dev_key = 'huggingface';
		}

		$provider_models = isset( $models[$dev_key] ) ? $models[$dev_key] : array();
		$test_model = ! empty( $provider_models ) ? array_key_first( $provider_models ) : 'gpt-4o';
		if ( $provider === 'google' || $provider === 'gemini' ) {
			$test_model = 'gemini-2.5-flash';
		}

		try {
			// A. Google Gemini khusus
			if ( $provider === 'google' || $provider === 'gemini' ) {
				$url = "https://generativelanguage.googleapis.com/v1beta/models/{$test_model}:generateContent?key=" . $api_key;
				$response = $client->post( $url, array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'json'    => array(
						'contents' => array(
							array( 'parts' => array( array( 'text' => 'Hello' ) ) )
						),
						'generationConfig' => array( 'maxOutputTokens' => 1 )
					)
				));
				$body = json_decode( (string) $response->getBody(), true );
				if ( isset( $body['error']['message'] ) ) {
					return array( 'success' => false, 'message' => 'Gemini Error: ' . $body['error']['message'] );
				}
				if ( $response->getStatusCode() === 200 ) {
					return array( 'success' => true, 'message' => 'OK' );
				}
				return array( 'success' => false, 'message' => 'HTTP Status ' . $response->getStatusCode() );
			}

			// B. Anthropic khusus
			if ( $provider === 'anthropic' ) {
				$url = "https://api.anthropic.com/v1/messages";
				$response = $client->post( $url, array(
					'headers' => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json'
					),
					'json' => array(
						'model'      => 'claude-3-5-haiku-20241022',
						'max_tokens' => 1,
						'messages'   => array( array( 'role' => 'user', 'content' => 'Hello' ) )
					)
				));
				$body = json_decode( (string) $response->getBody(), true );
				if ( isset( $body['error']['message'] ) ) {
					return array( 'success' => false, 'message' => 'Anthropic Error: ' . $body['error']['message'] );
				}
				if ( $response->getStatusCode() === 200 ) {
					return array( 'success' => true, 'message' => 'OK' );
				}
				return array( 'success' => false, 'message' => 'HTTP Status ' . $response->getStatusCode() );
			}

			// C. Generic OpenAI-compatible
			$url = rtrim( $api_endpoint, '/' ) . '/chat/completions';
			
			$payload = array(
				'model'      => $test_model,
				'max_tokens' => 1,
				'messages'   => array(
					array( 'role' => 'user', 'content' => 'Hello' )
				)
			);

			$response = $client->post( $url, array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json'
				),
				'json' => $payload
			));

			$body = json_decode( (string) $response->getBody(), true );

			if ( isset( $body['error']['message'] ) ) {
				return array( 'success' => false, 'message' => 'API Error: ' . $body['error']['message'] );
			}

			if ( $response->getStatusCode() === 200 ) {
				return array( 'success' => true, 'message' => 'OK' );
			}

			return array( 'success' => false, 'message' => 'HTTP Status ' . $response->getStatusCode() . ': ' . substr( (string)$response->getBody(), 0, 150 ) );

		} catch ( \Exception $e ) {
			return array( 'success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX Handler: Ambil log terbaru tanpa reload halaman.
	 *
	 * @since    1.1.0
	 */
	public function ajax_get_logs() {
		check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Akses ditolak.' ) );
		}

		$upload_dir = wp_upload_dir();
		$log_file   = $upload_dir['basedir'] . '/autoblog-logs/debug.log';
		$log_content = 'No logs found.';

		if ( file_exists( $log_file ) ) {
			$max_bytes = 15360; // Ambil ~15KB terakhir agar tidak memory exhaustion
			$size = filesize( $log_file );
			if ( $size > $max_bytes ) {
				$fp = fopen( $log_file, 'r' );
				fseek( $fp, -$max_bytes, SEEK_END );
				$log_content = fread( $fp, $max_bytes );
				fclose( $fp );
				// Potong baris pertama yang mungkin terpotong setengah
				$log_content = substr( $log_content, strpos( $log_content, "\n" ) + 1 );
			} else {
				$log_content = file_get_contents( $log_file );
			}
		}

		// Helper rendering untuk asinkron status agen di dashboard
		$ingestion = get_option( 'autoblog_last_ingestion_data', array( 'status' => 'idle' ) );
		$ideation  = get_option( 'autoblog_last_ideation_data', array( 'status' => 'idle' ) );
		$production = get_option( 'autoblog_last_production_data', array( 'status' => 'idle' ) );

		// Render list sources terakhir secara aman
		$sources_html = '';
		if ( ! empty( $ingestion['sources'] ) ) {
			$sources_html .= '<ul style="font-size: 11px; color: #64748b; background: #ffffff; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; margin: 10px 0 0;">';
			foreach ( array_slice($ingestion['sources'], -3) as $src ) {
				$sources_html .= '<li style="margin-bottom: 4px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 4px;">' . esc_html( $src ) . '</li>';
			}
			$sources_html .= '</ul>';
		}

		// Status badge helper function locally
		$get_badge = function( $status ) {
			$color = '#999';
			$label = strtoupper( $status );
			switch ( $status ) {
				case 'running':
					$color = '#2271b1';
					$label = '🔄 RUNNING';
					break;
				case 'completed':
					$color = '#46b450';
					$label = '✅ COMPLETED';
					break;
				case 'failed':
					$color = '#d63638';
					$label = '❌ FAILED';
					break;
				case 'skipped':
					$color = '#dba617';
					$label = '⚠️ SKIPPED';
					break;
				default:
					$label = '💤 IDLE';
			}
			return "<span style='background: {$color}; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;'>{$label}</span>";
		};

		wp_send_json_success( array(
			'html' => esc_textarea( $log_content ),
			'statuses' => array(
				'collector' => array(
					'badge'     => $get_badge( $ingestion['status'] ),
					'last_sync' => isset($ingestion['timestamp']) ? esc_html($ingestion['timestamp']) : '-',
					'ingested'  => isset($ingestion['count']) ? intval($ingestion['count']) : 0,
					'sources'   => $sources_html,
				),
				'ideator' => array(
					'badge'           => $get_badge( $ideation['status'] ),
					'last_brainstorm' => isset($ideation['timestamp']) ? esc_html($ideation['timestamp']) : '-',
					'topic'           => isset( $ideation['title'] ) ? '"' . esc_html( $ideation['title'] ) . '"' : 'Waiting for next brainstorm session...',
				),
				'writer' => array(
					'badge'          => $get_badge( $production['status'] ),
					'last_published' => isset($production['timestamp']) ? esc_html($production['timestamp']) : '-',
					'topic'          => isset($production['topic']) ? esc_html( $production['topic'] ) : '-',
					'topic_attr'     => isset($production['topic']) ? esc_attr( $production['topic'] ) : '',
					'result'         => ( isset( $production['post_id'] ) && $production['post_id'] > 0 )
						? '<a href="' . get_edit_post_link( $production['post_id'] ) . '" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">View Post ID: ' . $production['post_id'] . ' ↗</a>'
						: esc_html( strtoupper($production['status']) )
				)
			)
		));
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		add_menu_page(
			'Autoblog AI Settings', 
			'Autoblog AI', 
			'manage_options', 
			$this->plugin_name, 
			array( $this, 'display_plugin_setup_page' ), 
			'dashicons-superhero', 
			110
		);

		add_submenu_page(
			'edit.php',
			'Auto-Set Taxonomy',
			'Auto-Set Taxonomy',
			'manage_options',
			'autoblog-taxonomy-tools',
			array( $this, 'display_taxonomy_tools_page' )
		);

	}

	/**
	 * Render the taxonomy tools page.
	 */
	public function display_taxonomy_tools_page() {
		include_once 'partials/autoblog-admin-taxonomy-tools.php';
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_setup_page() {
		include_once 'partials/autoblog-admin-display.php';
	}


	/**
	 * Handle operasi mutasi Data Sources (upload, hapus) SEBELUM HTML output.
	 *
	 * Method ini HARUS di-hook ke admin_init karena wp_safe_redirect()
	 * membutuhkan header HTTP yang belum terkirim. Jika dipanggil saat
	 * render halaman, akan error "headers already sent".
	 *
	 * @since    1.1.0
	 */
	public function handle_data_source_actions() {
		// Hanya proses di halaman plugin ini
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $this->plugin_name ) {
			return;
		}

		$clean_url = admin_url( 'admin.php?page=autoblog&tab=data_sources' );

		// --- Handle: Upload file Knowledge Base ---
		if ( isset( $_POST['autoblog_upload_file'] ) && ! empty( $_FILES['autoblog_file'] ) ) {
			check_admin_referer( 'autoblog_datasource_verify' );

			$uploaded_file = $_FILES['autoblog_file'];

            // 1. Validasi Ukuran File (Maksimal 10 MB agar RAM aman saat parsing/chunking)
            $max_size = 10 * 1024 * 1024;
            if ( $uploaded_file['size'] > $max_size ) {
                set_transient( 'autoblog_admin_notice_error', 'Upload gagal: Ukuran file melebihi batas maksimal 10MB.', 30 );
                wp_safe_redirect( $clean_url );
                exit;
            }

            // 2. Validasi Ekstensi dan Mime Type Strict (Security Gate)
            $allowed_mimes = array(
                'pdf'  => 'application/pdf',
                'csv'  => 'text/csv',
                'txt'  => 'text/plain',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

            $file_info = wp_check_filetype( basename( $uploaded_file['name'] ), $allowed_mimes );
            
            if ( empty( $file_info['ext'] ) || empty( $file_info['type'] ) ) {
                set_transient( 'autoblog_admin_notice_error', 'Upload gagal: Tipe/Format file tidak didukung atau disusupi.', 30 );
                wp_safe_redirect( $clean_url );
                exit;
            }

			$upload_overrides = array( 
                'test_form' => false,
                'mimes'     => $allowed_mimes // Paksa wp_handle_upload hanya menerima daftar ini
            );
            
			$movefile = wp_handle_upload( $uploaded_file, $upload_overrides );

			if ( $movefile && ! isset( $movefile['error'] ) ) {
				$knowledge_base = get_option( 'autoblog_knowledge', array() );
				if ( ! is_array( $knowledge_base ) ) {
					$knowledge_base = array();
				}

				$new_item = array(
					'id'   => uniqid('doc_'),
					'name' => basename($movefile['file']),
					'path' => $movefile['file'],
					'url'  => $movefile['url'],
					'date' => current_time('mysql') // Bug #14 Fix: Gunakan timezone WordPress
				);

				$knowledge_base[] = $new_item;
				update_option( 'autoblog_knowledge', $knowledge_base );
				set_transient( 'autoblog_admin_notice', 'File berhasil ditambahkan ke Knowledge Base!', 30 );
			} else {
				set_transient( 'autoblog_admin_notice_error', 'Upload gagal: ' . $movefile['error'], 30 );
			}

			wp_safe_redirect( $clean_url );
			exit;
		}

		// --- Handle: Hapus file Knowledge Base ---
		// Bug #7 Fix: Tambahkan nonce check untuk mencegah CSRF
		if ( isset( $_GET['delete_kb'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'autoblog_delete_kb' ) ) {
				wp_die( 'Security check gagal.' );
			}
			$idx = intval( $_GET['delete_kb'] );
			$kb = get_option( 'autoblog_knowledge', array() );
			if ( isset( $kb[ $idx ] ) ) {
				$file_path = isset( $kb[ $idx ]['path'] ) ? $kb[ $idx ]['path'] : '';
				if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
					@unlink( $file_path );
				}
				unset( $kb[ $idx ] );
				update_option( 'autoblog_knowledge', array_values( $kb ) );
				set_transient( 'autoblog_admin_notice', 'File dihapus dari Knowledge Base.', 30 );
			}

			wp_safe_redirect( $clean_url );
			exit;
		}

		// --- Handle: Tambah Content Trigger ---
		if ( isset( $_POST['autoblog_add_source'] ) ) {
			check_admin_referer( 'autoblog_datasource_verify' );

			$sources = get_option( 'autoblog_sources', array() );
			if ( ! is_array( $sources ) ) {
				$sources = array();
			}

			$urls     = array_map( 'trim', explode( ',', $_POST['source_url'] ) );
			$type     = sanitize_text_field( $_POST['source_type'] );
			$selector = sanitize_text_field( $_POST['source_selector'] );
			$match    = sanitize_text_field( $_POST['match_keywords'] );
			$negative = sanitize_text_field( $_POST['negative_keywords'] );

			$count = 0;
			foreach ( $urls as $url ) {
				if ( empty( $url ) ) continue;

				$clean_source_url = ( $type === 'web_search' )
					? sanitize_text_field( $url )
					: esc_url_raw( $url );

				$sources[] = array(
					'type'              => $type,
					'url'               => $clean_source_url,
					'match_keywords'    => $match,
					'negative_keywords' => $negative,
					'selector'          => $selector,
				);
				$count++;
			}

			update_option( 'autoblog_sources', $sources );
			set_transient( 'autoblog_admin_notice', $count . ' source berhasil ditambahkan.', 30 );

			wp_safe_redirect( $clean_url );
			exit;
		}

		// --- Handle: Hapus Content Trigger ---
		// Bug #7 Fix: Tambahkan nonce check untuk mencegah CSRF
		if ( isset( $_GET['autoblog_delete_source'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'autoblog_delete_source' ) ) {
				wp_die( 'Security check gagal.' );
			}
			$index   = intval( $_GET['autoblog_delete_source'] );
			$sources = get_option( 'autoblog_sources', array() );
			if ( ! is_array( $sources ) ) {
				$sources = array();
			}

			if ( isset( $sources[ $index ] ) ) {
				unset( $sources[ $index ] );
				update_option( 'autoblog_sources', array_values( $sources ) );
				set_transient( 'autoblog_admin_notice', 'Source berhasil dihapus.', 30 );
			}

			wp_safe_redirect( $clean_url );
			exit;
		}
	}

	/**
	 * Register the settings for this plugin.
	 *
	 * PENTING: Setiap tab HARUS punya option group sendiri.
	 * WordPress options.php akan mengupdate SEMUA setting di group yang sama,
	 * setting yang tidak ada di form POST akan diset null.
	 * Pisahkan group agar submit di satu tab tidak menghapus setting di tab lain.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {

		// ── Tab: API Keys (group: autoblog_keys) ──
		register_setting( 'autoblog_keys', 'autoblog_openai_key' );
		register_setting( 'autoblog_keys', 'autoblog_anthropic_key' );
		register_setting( 'autoblog_keys', 'autoblog_gemini_key' );
		register_setting( 'autoblog_keys', 'autoblog_groq_key' );
		register_setting( 'autoblog_keys', 'autoblog_hf_key' );
		register_setting( 'autoblog_keys', 'autoblog_openrouter_key' );
		register_setting( 'autoblog_keys', 'autoblog_serpapi_key' );
		register_setting( 'autoblog_keys', 'autoblog_pexels_key' );
		register_setting( 'autoblog_keys', 'autoblog_custom_api_keys' );

		// ── Tab: AI Engine (group: autoblog_ai) ──
		register_setting( 'autoblog_ai', 'autoblog_ai_provider' );
		register_setting( 'autoblog_ai', 'autoblog_openai_model' );
		register_setting( 'autoblog_ai', 'autoblog_anthropic_model' );
		register_setting( 'autoblog_ai', 'autoblog_gemini_model' );
		register_setting( 'autoblog_ai', 'autoblog_groq_model' );
		register_setting( 'autoblog_ai', 'autoblog_openrouter_model' );
		register_setting( 'autoblog_ai', 'autoblog_hf_model' );
		register_setting( 'autoblog_ai', 'autoblog_embedding_provider' );
		register_setting( 'autoblog_ai', 'autoblog_gemini_grounding' );
		register_setting( 'autoblog_ai', 'autoblog_thumbnail_source' );
		register_setting( 'autoblog_ai', 'autoblog_enable_dalle' );
		register_setting( 'autoblog_ai', 'autoblog_enable_stock_pexels' );
		register_setting( 'autoblog_ai', 'autoblog_enable_stock_openverse' );
		register_setting( 'autoblog_ai', 'autoblog_enable_fallback' );
		register_setting( 'autoblog_ai', 'autoblog_ai_model' );

		// ── Tab: Data Sources (group: autoblog_ds) ──
		register_setting( 'autoblog_ds', 'autoblog_data_source_mode' );
		register_setting( 'autoblog_ds', 'autoblog_search_provider' );

		// ── Tab: Writing Style — Personality (group: autoblog_style) ──
		register_setting( 'autoblog_style', 'autoblog_enable_personality' );
		register_setting( 'autoblog_style', 'autoblog_personality_samples' );
		register_setting( 'autoblog_style', 'autoblog_author_strategy' );
		register_setting( 'autoblog_style', 'autoblog_author_fixed_id' );

		// ── Tab: Advanced Intelligence (group: autoblog_adv) ──
		register_setting( 'autoblog_adv', 'autoblog_enable_dynamic_search' );
		register_setting( 'autoblog_adv', 'autoblog_enable_deep_research' );
		register_setting( 'autoblog_adv', 'autoblog_enable_interlinking' );
		register_setting( 'autoblog_adv', 'autoblog_enable_living_content' );
		register_setting( 'autoblog_adv', 'autoblog_enable_multimodal' );

		// ── Tab: Tools & Logs — Cron (group: autoblog_ops) ──
		register_setting( 'autoblog_ops', 'autoblog_cron_schedule' );
		register_setting( 'autoblog_ops', 'autoblog_refresh_schedule' );
		register_setting( 'autoblog_ops', 'autoblog_post_status' );
	}

	/**
	 * Mengambil catalog model terupdate dari models.dev dengan caching transient.
	 *
	 * @return array
	 */
	public static function get_dynamic_models() {
		$cache = get_transient( 'autoblog_models_dev_cache' );
		if ( false !== $cache ) {
			return $cache;
		}

		$response = wp_remote_get( 'https://models.dev/api.json', array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		// Filter & transform data hanya untuk provider yang kita dukung agar hemat memori cache
		$supported = array(
			'openai'      => 'openai',
			'anthropic'   => 'anthropic',
			'google'      => 'google', // mapped to gemini
			'groq'        => 'groq',
			'openrouter'  => 'openrouter',
		);

		$filtered = array();
		foreach ( $supported as $key => $models_dev_key ) {
			if ( isset( $data[$models_dev_key]['models'] ) && is_array( $data[$models_dev_key]['models'] ) ) {
				$filtered[$key] = array();
				foreach ( $data[$models_dev_key]['models'] as $m_id => $m_data ) {
					// Kita hanya butuh name dan id untuk select dropdown
					$filtered[$key][$m_id] = isset( $m_data['name'] ) ? $m_data['name'] : $m_id;
				}
			}
		}

		set_transient( 'autoblog_models_dev_cache', $filtered, DAY_IN_SECONDS );
		return $filtered;
	}

	/**
	 * Fallback list static model jika API models.dev down.
	 *
	 * @return array
	 */
	public static function get_fallback_models() {
		return array(
			'openai' => array(
				'gpt-4o'        => 'GPT-4o (Most Capable)',
				'gpt-4-turbo'   => 'GPT-4 Turbo',
				'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Fast/Cheap)',
			),
			'anthropic' => array(
				'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet',
				'claude-3-opus-20240229'     => 'Claude 3 Opus',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
			),
			'google' => array(
				'auto'                 => 'Auto (Best for request)',
				'gemini-3.1-pro'       => 'Gemini 3.1 Pro',
				'gemini-3.0-pro'       => 'Gemini 3 Pro',
				'gemini-3.0-flash'     => 'Gemini 3 Flash',
				'gemini-2.5-pro'       => 'Gemini 2.5 Pro',
				'gemini-2.5-flash'     => 'Gemini 2.5 Flash',
				'gemini-2.5-flash-lite'=> 'Gemini 2.5 Flash Lite',
				'gemini-2.0-flash'     => 'Gemini 2 Flash',
				'gemini-2.0-flash-lite'=> 'Gemini 2 Flash Lite',
			),
			'groq' => array(
				'auto'                     => 'Auto (Best for request)',
				'llama-3.3-70b-versatile'  => 'Llama 3.3 70B',
				'mixtral-8x7b-32768'       => 'Mixtral 8x7B',
				'gemma-7b-it'              => 'Gemma 7B (Google)',
			),
			'openrouter' => array(
				'openrouter/auto'                        => 'Auto (Best for request)',
				'qwen/qwen3-vl-30b-a3b-thinking'         => 'Qwen: Qwen3 VL 30B A3B Thinking ($0/1M)',
				'qwen/qwen3-vl-235b-a22b-thinking'        => 'Qwen: Qwen3 VL 235B A22B Thinking ($0/1M)',
				'qwen/qwen3-next-80b-a3b-instruct:free'  => 'Qwen: Qwen3 Next 80B A3B Instruct (Free)',
				'meta-llama/llama-3.3-70b-instruct:free' => 'Meta: Llama 3.3 70B Instruct (Free)',
				'meta-llama/llama-3.2-3b-instruct:free'  => 'Meta: Llama 3.2 3B Instruct (Free)',
			),
		);
	}

	/**
	 * Menggabungkan model dynamic dan fallback static secara aman.
	 *
	 * @return array
	 */
	public static function get_merged_models() {
		$dynamic = self::get_dynamic_models();
		$fallback = self::get_fallback_models();
		
		$merged = array();
		$providers = array( 'openai', 'anthropic', 'google', 'groq', 'openrouter' );
		
		foreach ( $providers as $p ) {
			$merged[$p] = isset( $fallback[$p] ) ? $fallback[$p] : array();
			if ( isset( $dynamic[$p] ) && is_array( $dynamic[$p] ) ) {
				foreach ( $dynamic[$p] as $m_id => $m_name ) {
					$merged[$p][$m_id] = $m_name;
				}
			}
		}
		
		return $merged;
	}

	/**
	 * Mengambil semua provider yang tersedia dari models.dev dengan caching transient.
	 *
	 * @return array
	 */
	public static function get_dynamic_providers() {
		$cache = get_transient( 'autoblog_providers_cache' );
		if ( false !== $cache ) {
			return $cache;
		}

		$response = wp_remote_get( 'https://models.dev/api.json', array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return self::get_fallback_providers();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return self::get_fallback_providers();
		}

		$providers = array();
		foreach ( $data as $p_id => $p_data ) {
			$providers[$p_id] = array(
				'name' => isset( $p_data['name'] ) ? $p_data['name'] : $p_id,
				'api'  => isset( $p_data['api'] ) ? $p_data['api'] : '',
				'env'  => isset( $p_data['env'] ) ? $p_data['env'] : array(),
			);
		}

		// Masukkan huggingface/hf jika tidak terdeteksi lengkap
		if ( ! isset( $providers['huggingface'] ) ) {
			$providers['huggingface'] = array( 'name' => 'Hugging Face', 'api' => 'https://api-inference.huggingface.co' );
		}

		set_transient( 'autoblog_providers_cache', $providers, DAY_IN_SECONDS );
		return $providers;
	}

	/**
	 * Fallback static providers jika models.dev down.
	 *
	 * @return array
	 */
	public static function get_fallback_providers() {
		return array(
			'openai'     => array( 'name' => 'OpenAI', 'api' => 'https://api.openai.com/v1' ),
			'anthropic'  => array( 'name' => 'Anthropic', 'api' => 'https://api.anthropic.com/v1' ),
			'google'     => array( 'name' => 'Google Gemini (google)', 'api' => 'https://generativelanguage.googleapis.com' ),
			'groq'       => array( 'name' => 'Groq', 'api' => 'https://api.groq.com/openai/v1' ),
			'openrouter' => array( 'name' => 'OpenRouter', 'api' => 'https://openrouter.ai/api/v1' ),
			'huggingface'=> array( 'name' => 'Hugging Face', 'api' => 'https://api-inference.huggingface.co' ),
		);
	}

}
