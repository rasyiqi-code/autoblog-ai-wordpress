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

		// License Check
		$license_status = get_option( 'agencyos_license_autoblog-ai_status' );
		if ( $license_status !== 'active' ) {
			wp_send_json_error( array( 'message' => 'License Required: Peringatan! Fitur AI hanya tersedia untuk pengguna lisensi aktif.' ) );
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

		// License Check
		$license_status = get_option( 'agencyos_license_autoblog-ai_status' );
		if ( $license_status !== 'active' ) {
			wp_send_json_error( array( 'message' => 'License Required: Fitur ini terkunci. Silakan aktivasi lisensi Autoblog AI Anda.' ) );
		}

		// Prevent execution timeout and memory issues during long AI operations
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '512M' );
		@ini_set( 'display_errors', 1 );
		error_reporting( E_ALL );

		try {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Core/Runner.php';
			$runner = new \Autoblog\Core\Runner();
			
			$overrides = isset( $_POST['overrides'] ) ? $_POST['overrides'] : array();

			if ( method_exists( $runner, $method ) ) {
				$runner->$method( $overrides );
				wp_send_json_success( array(
					'message' => 'Proses ' . $method . ' selesai!',
				));
			} else {
				wp_send_json_error( array( 'message' => 'Method ' . $method . ' tidak ditemukan.' ) );
			}
		} catch ( \Throwable $e ) {
			\Autoblog\Utils\Logger::log( 'AJAX Pipeline Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
			wp_send_json_error( array(
				'message' => 'Fatal Error: ' . $e->getMessage() . ' (See debug.log for details)',
			));
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
		$log_content = file_exists( $log_file ) ? file_get_contents( $log_file ) : 'No logs found.';

		wp_send_json_success( array(
			'html' => esc_textarea( $log_content ),
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
		// License Check
		$license_status = get_option( 'agencyos_license_autoblog-ai_status' );
		if ( $license_status !== 'active' ) {
			$this->display_licensing_required_notice();
			return;
		}
		include_once 'partials/autoblog-admin-taxonomy-tools.php';
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_setup_page() {
		// License Check
		$license_status = get_option( 'agencyos_license_autoblog-ai_status' );
		if ( $license_status !== 'active' ) {
			$this->display_licensing_required_notice();
			return;
		}
		include_once 'partials/autoblog-admin-display.php';
	}

	/**
	 * Helper to display a licensing required screen.
	 */
	private function display_licensing_required_notice() {
		?>
		<div class="wrap">
			<h1>Autoblog AI: License Activation Required</h1>
			<div class="notice notice-error" style="padding: 20px; border-left-width: 5px;">
				<h2 style="margin-top: 0; color: #dc3232;">🔒 Fitur Terkunci</h2>
				<p style="font-size: 16px;">Semua fitur canggih Autoblog AI (Ingestion, Ideation, Production, & Taxonomy Tools) saat ini terkunci.</p>
				<p style="font-size: 16px;">Silakan masukkan License Key yang valid untuk mengaktifkan seluruh kekuatan AI di blog Anda.</p>
				<p style="margin-top: 20px;">
					<a href="<?php echo admin_url( 'admin.php?page=autoblog-ai-license' ); ?>" class="button button-primary button-large">Aktivasi Lisensi Sekarang</a>
				</p>
			</div>
		</div>
		<?php
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
					'date' => date('Y-m-d H:i:s')
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
		if ( isset( $_GET['delete_kb'] ) ) {
			$idx = intval( $_GET['delete_kb'] );
			$kb = get_option( 'autoblog_knowledge', array() );
			if ( isset( $kb[ $idx ] ) ) {
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
		if ( isset( $_GET['autoblog_delete_source'] ) ) {
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

		// ── Tab: AI Engine (group: autoblog_ai) ──
		register_setting( 'autoblog_ai', 'autoblog_ai_provider' );
		register_setting( 'autoblog_ai', 'autoblog_openai_model' );
		register_setting( 'autoblog_ai', 'autoblog_anthropic_model' );
		register_setting( 'autoblog_ai', 'autoblog_gemini_model' );
		register_setting( 'autoblog_ai', 'autoblog_groq_model' );
		register_setting( 'autoblog_ai', 'autoblog_openrouter_model' );
		register_setting( 'autoblog_ai', 'autoblog_hf_model' );
		register_setting( 'autoblog_ai', 'autoblog_embedding_provider' );
		register_setting( 'autoblog_ai', 'autoblog_search_provider' );
		register_setting( 'autoblog_ai', 'autoblog_gemini_grounding' );
		register_setting( 'autoblog_ai', 'autoblog_thumbnail_source' );
		register_setting( 'autoblog_ai', 'autoblog_enable_dalle' );
		register_setting( 'autoblog_ai', 'autoblog_enable_stock_pexels' );
		register_setting( 'autoblog_ai', 'autoblog_enable_stock_openverse' );
		register_setting( 'autoblog_ai', 'autoblog_enable_fallback' );

		// ── Tab: Data Sources (group: autoblog_ds) ──
		register_setting( 'autoblog_ds', 'autoblog_data_source_mode' );

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

	public function display_license_notice() {
		$license_status = get_option( 'agencyos_license_autoblog-ai_status' );
		if ( $license_status === 'active' ) {
			return;
		}

		// Don't show notice twice on the license page itself
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'autoblog_page_autoblog-ai-license' ) {
			return;
		}

		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong>Autoblog AI:</strong> Lisensi Anda belum aktif. Semua fitur otomatisasi AI saat ini <strong>terkunci</strong>. 
				<a href="<?php echo admin_url( 'admin.php?page=autoblog-ai-license' ); ?>">Klik di sini untuk aktivasi lisensi</a>.
			</p>
		</div>
		<?php
	}

}
