<?php

namespace Autoblog\Admin;

/**
 * AdminAjax
 *
 * Berisi semua AJAX handler untuk halaman admin plugin:
 * - Pipeline runner (full, collector, ideator, writer)
 * - AI Taxonomy Prediction (bulk)
 * - Test Gemini Grounding
 * - Test API Connection (provider keys)
 * - Get Logs (polling real-time)
 *
 * Di-load dan di-hook oleh Autoblog.php.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin
 */
class AdminAjax {

    // ================================================================
    // PIPELINE RUNNERS
    // ================================================================

    /**
     * AJAX: Jalankan full pipeline.
     */
    public function ajax_run_pipeline() {
        $this->handle_ajax_pipeline_call( 'run_pipeline' );
    }

    /**
     * AJAX: Jalankan Collector (Ingestion Phase) saja.
     */
    public function ajax_run_collector() {
        $this->handle_ajax_pipeline_call( 'run_ingestion_phase' );
    }

    /**
     * AJAX: Jalankan Ideator (Ideation Phase) saja.
     */
    public function ajax_run_ideator() {
        $this->handle_ajax_pipeline_call( 'run_ideation_phase' );
    }

    /**
     * AJAX: Jalankan Writer (Production Phase) saja.
     */
    public function ajax_run_writer() {
        $this->handle_ajax_pipeline_call( 'run_production_phase' );
    }

    /**
     * Helper terpusat untuk menjalankan pipeline action via WP-Cron background.
     *
     * @param string $method
     */
    private function handle_ajax_pipeline_call( $method ) {
        check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Akses ditolak.' ] );
        }

        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        try {
            require_once plugin_dir_path( __FILE__ ) . '../includes/Core/Runner.php';

            // Sanitasi overrides dari POST
            $raw_overrides = isset( $_POST['overrides'] ) && is_array( $_POST['overrides'] ) ? $_POST['overrides'] : [];
            $overrides     = array_map( 'sanitize_text_field', $raw_overrides );

            $hook_map = [
                'run_pipeline'         => 'autoblog_run_pipeline',
                'run_ingestion_phase'  => 'autoblog_run_collector',
                'run_ideation_phase'   => 'autoblog_run_ideator',
                'run_production_phase' => 'autoblog_run_writer',
            ];

            $hook_name = isset( $hook_map[ $method ] ) ? $hook_map[ $method ] : '';

            if ( $hook_name ) {
                // Delegasi ke background via WP-Cron agar Nginx tidak timeout (503)
                wp_schedule_single_event( time(), $hook_name, [ $overrides ] );
                spawn_cron();

                \Autoblog\Utils\Logger::log( "AJAX: Task '{$method}' dialihkan ke background via '{$hook_name}'.", 'info' );

                wp_send_json_success( [ 'message' => 'Proses dialihkan ke background. Silakan pantau Log di bawah.' ] );
            } else {
                wp_send_json_error( [ 'message' => "Method {$method} tidak dikenali." ] );
            }
        } catch ( \Throwable $e ) {
            \Autoblog\Utils\Logger::log( 'AJAX Pipeline Fatal: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
            wp_send_json_error( [ 'message' => 'Fatal Error: ' . $e->getMessage() ] );
        }
    }

    // ================================================================
    // AI TAXONOMY PREDICTION (Bulk)
    // ================================================================

    /**
     * AJAX: Prediksi kategori & tag menggunakan AI berdasarkan judul post.
     */
    public function ajax_ai_predict_taxonomy() {
        check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Akses ditolak.' ] );
        }

        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : [];
        if ( empty( $post_ids ) ) {
            wp_send_json_error( [ 'message' => 'Tidak ada post yang dipilih.' ] );
        }

        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '512M' );

        $ai_client  = new \Autoblog\Utils\AIClient();
        $categories = get_categories( [ 'hide_empty' => false ] );
        $cat_context = implode( ', ', wp_list_pluck( $categories, 'name' ) );

        $provider = get_option( 'autoblog_ai_provider', 'openai' );
        $model    = get_option( 'autoblog_' . $provider . '_model', 'gpt-4o' );

        $count  = 0;
        $failed = 0;

        foreach ( $post_ids as $post_id ) {
            try {
                $post = get_post( $post_id );
                if ( ! $post ) { $failed++; continue; }

                $system_prompt  = "You are a WordPress SEO Specialist. Categorize a blog post based ONLY on its title.\n";
                $system_prompt .= "Select 1 most relevant category from the provided list, and provide 3 to 5 relevant tags.\n";
                $system_prompt .= "Return ONLY valid JSON: {\"category\": \"Category Name\", \"tags\": [\"tag1\", \"tag2\"]}";

                $user_prompt = "Post Title: \"{$post->post_title}\"\n\nAvailable Categories: [{$cat_context}]\n\nJSON Output:";

                $response_text = $ai_client->generate_text( $user_prompt, $model, $provider, 0.3, $system_prompt );

                if ( ! $response_text ) { $failed++; continue; }

                // Bersihkan wrapper ```json ... ```
                $response_text = trim( preg_replace( '/^```(?:json)?\s*$/m', '', $response_text ) );
                $json_data     = json_decode( $response_text, true );

                if ( ! $json_data || ! isset( $json_data['category'] ) ) {
                    $failed++;
                    \Autoblog\Utils\Logger::log( "AI Taxonomy: invalid JSON untuk post ID {$post_id} → {$response_text}", 'error' );
                    continue;
                }

                $success = false;

                // 1. Tetapkan kategori
                $predicted_cat = trim( $json_data['category'], " \n\r\t\"'[]" );
                $term          = get_term_by( 'name', $predicted_cat, 'category' );
                if ( ! $term ) {
                    $term = get_term_by( 'slug', sanitize_title( $predicted_cat ), 'category' );
                }
                if ( $term && ! is_wp_error( $term ) ) {
                    wp_set_post_categories( $post_id, [ $term->term_id ] );
                    \Autoblog\Utils\Logger::log( "AI Taxonomy: set kategori '{$term->name}' untuk post ID {$post_id}", 'info' );
                    $success = true;
                }

                // 2. Tetapkan tags
                if ( isset( $json_data['tags'] ) && is_array( $json_data['tags'] ) ) {
                    wp_set_post_tags( $post_id, $json_data['tags'], false );
                    \Autoblog\Utils\Logger::log( 'AI Taxonomy: set tags [' . implode( ', ', $json_data['tags'] ) . "] untuk post ID {$post_id}", 'info' );
                    $success = true;
                }

                $success ? $count++ : $failed++;

            } catch ( \Throwable $e ) {
                $failed++;
                \Autoblog\Utils\Logger::log( "AI Taxonomy error post ID {$post_id}: " . $e->getMessage(), 'error' );
            }
        }

        if ( $count > 0 ) {
            $msg = "AI berhasil menetapkan kategori untuk {$count} pos.";
            if ( $failed > 0 ) { $msg .= " ({$failed} gagal)."; }
            wp_send_json_success( [ 'message' => $msg ] );
        } else {
            wp_send_json_error( [ 'message' => "Gagal memprediksi. {$failed} pos dilewati. Cek log." ] );
        }
    }

    // ================================================================
    // TEST GEMINI GROUNDING
    // ================================================================

    /**
     * AJAX: Test Gemini Grounding dengan prompt user.
     */
    public function ajax_test_gemini_grounding() {
        check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Akses ditolak.' ] );
        }

        $prompt = isset( $_POST['prompt'] ) ? sanitize_text_field( $_POST['prompt'] ) : '';
        $model  = isset( $_POST['model'] )  ? sanitize_text_field( $_POST['model'] )  : 'gemini-3.1-pro';

        if ( empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'Prompt tidak boleh kosong.' ] );
        }

        $client = new \Autoblog\Utils\AIClient();

        // Paksa grounding aktif untuk test ini saja
        add_filter( 'option_autoblog_gemini_grounding', function () { return '1'; } );

        $result = $client->generate_text( $prompt, $model, 'gemini' );

        if ( $result ) {
            wp_send_json_success( [ 'answer' => $result ] );
        } else {
            wp_send_json_error( [ 'message' => 'Gagal mendapatkan respon dari Gemini. Cek Logs untuk detail.' ] );
        }
    }

    // ================================================================
    // TEST API CONNECTION
    // ================================================================

    /**
     * AJAX: Test koneksi API key provider.
     */
    public function ajax_test_api_connection() {
        check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Akses ditolak.' ] );
        }

        $provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
        $api_key  = isset( $_POST['api_key'] )  ? sanitize_text_field( $_POST['api_key'] )  : '';

        if ( empty( $provider ) || empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'Provider atau API Key tidak boleh kosong.' ] );
        }

        $result = $this->validate_api_key( $provider, $api_key );

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => 'Sukses terhubung!' ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    /**
     * Validasi API key dengan request minimal ke endpoint provider.
     *
     * @param string $provider
     * @param string $api_key
     * @return array [ success => bool, message => string ]
     */
    private function validate_api_key( $provider, $api_key ) {
        $client = new \GuzzleHttp\Client( [ 'http_errors' => false, 'timeout' => 8 ] );

        // SerpApi
        if ( $provider === 'serpapi' ) {
            try {
                $response = $client->get( 'https://serpapi.com/search.json?q=test&api_key=' . $api_key );
                $body     = json_decode( (string) $response->getBody(), true );
                if ( isset( $body['error'] ) ) { return [ 'success' => false, 'message' => 'SerpApi Error: ' . $body['error'] ]; }
                if ( $response->getStatusCode() === 200 ) { return [ 'success' => true, 'message' => 'OK' ]; }
                return [ 'success' => false, 'message' => 'HTTP ' . $response->getStatusCode() ];
            } catch ( \Exception $e ) {
                return [ 'success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage() ];
            }
        }

        // Pexels
        if ( $provider === 'pexels' ) {
            try {
                $response = $client->get( 'https://api.pexels.com/v1/search?query=nature&per_page=1', [ 'headers' => [ 'Authorization' => $api_key ] ] );
                $body     = json_decode( (string) $response->getBody(), true );
                if ( isset( $body['error'] ) ) { return [ 'success' => false, 'message' => 'Pexels Error: ' . $body['error'] ]; }
                if ( $response->getStatusCode() === 200 ) { return [ 'success' => true, 'message' => 'OK' ]; }
                return [ 'success' => false, 'message' => 'HTTP ' . $response->getStatusCode() ];
            } catch ( \Exception $e ) {
                return [ 'success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage() ];
            }
        }

        // Cari endpoint dari models.dev catalog
        $providers    = AdminSettings::get_dynamic_providers();
        $p_data       = isset( $providers[ $provider ] ) ? $providers[ $provider ] : null;
        $api_endpoint = ( $p_data && ! empty( $p_data['api'] ) ) ? $p_data['api'] : '';

        if ( empty( $api_endpoint ) ) {
            return [ 'success' => false, 'message' => 'Provider API Endpoint tidak ditemukan.' ];
        }

        // Tentukan model test
        $models          = AdminSettings::get_merged_models();
        $dev_key         = ( $provider === 'gemini' || $provider === 'google' ) ? 'google'
                         : ( ( $provider === 'huggingface' || $provider === 'hf' ) ? 'huggingface' : $provider );
        $provider_models = isset( $models[ $dev_key ] ) ? $models[ $dev_key ] : [];
        $test_model      = ! empty( $provider_models ) ? array_key_first( $provider_models ) : 'gpt-4o';
        if ( $provider === 'google' || $provider === 'gemini' ) {
            $test_model = 'gemini-2.5-flash';
        }

        try {
            // Google Gemini
            if ( $provider === 'google' || $provider === 'gemini' ) {
                $url      = "https://generativelanguage.googleapis.com/v1beta/models/{$test_model}:generateContent?key={$api_key}";
                $response = $client->post( $url, [
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'json'    => [ 'contents' => [ [ 'parts' => [ [ 'text' => 'Hello' ] ] ] ], 'generationConfig' => [ 'maxOutputTokens' => 1 ] ],
                ]);
                $body = json_decode( (string) $response->getBody(), true );
                if ( isset( $body['error']['message'] ) ) { return [ 'success' => false, 'message' => 'Gemini Error: ' . $body['error']['message'] ]; }
                return $response->getStatusCode() === 200 ? [ 'success' => true, 'message' => 'OK' ] : [ 'success' => false, 'message' => 'HTTP ' . $response->getStatusCode() ];
            }

            // Anthropic
            if ( $provider === 'anthropic' ) {
                $response = $client->post( 'https://api.anthropic.com/v1/messages', [
                    'headers' => [ 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json' ],
                    'json'    => [ 'model' => 'claude-3-5-haiku-20241022', 'max_tokens' => 1, 'messages' => [ [ 'role' => 'user', 'content' => 'Hello' ] ] ],
                ]);
                $body = json_decode( (string) $response->getBody(), true );
                if ( isset( $body['error']['message'] ) ) { return [ 'success' => false, 'message' => 'Anthropic Error: ' . $body['error']['message'] ]; }
                return $response->getStatusCode() === 200 ? [ 'success' => true, 'message' => 'OK' ] : [ 'success' => false, 'message' => 'HTTP ' . $response->getStatusCode() ];
            }

            // Generic OpenAI-compatible
            $url      = rtrim( $api_endpoint, '/' ) . '/chat/completions';
            $response = $client->post( $url, [
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ],
                'json'    => [ 'model' => $test_model, 'max_tokens' => 1, 'messages' => [ [ 'role' => 'user', 'content' => 'Hello' ] ] ],
            ]);
            $body = json_decode( (string) $response->getBody(), true );
            if ( isset( $body['error']['message'] ) ) { return [ 'success' => false, 'message' => 'API Error: ' . $body['error']['message'] ]; }
            return $response->getStatusCode() === 200 ? [ 'success' => true, 'message' => 'OK' ] : [ 'success' => false, 'message' => 'HTTP ' . $response->getStatusCode() ];

        } catch ( \Exception $e ) {
            return [ 'success' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage() ];
        }
    }

    // ================================================================
    // GET LOGS (Real-time polling)
    // ================================================================

    /**
     * AJAX: Ambil log terbaru dan status agent untuk dashboard pipeline.
     */
    public function ajax_get_logs() {
        check_ajax_referer( 'autoblog_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Akses ditolak.' ] );
        }

        $log_file    = wp_upload_dir()['basedir'] . '/autoblog-logs/debug.log';
        $log_content = 'No logs found.';

        if ( file_exists( $log_file ) ) {
            $max_bytes = 15360; // ~15KB terakhir
            $size      = filesize( $log_file );
            if ( $size > $max_bytes ) {
                $fp          = fopen( $log_file, 'r' );
                fseek( $fp, -$max_bytes, SEEK_END );
                $log_content = fread( $fp, $max_bytes );
                fclose( $fp );
                // Potong baris pertama yang mungkin terpotong setengah
                $log_content = substr( $log_content, strpos( $log_content, "\n" ) + 1 );
            } else {
                $log_content = file_get_contents( $log_file );
            }
        }

        $ingestion  = get_option( 'autoblog_last_ingestion_data',  [ 'status' => 'idle' ] );
        $ideation   = get_option( 'autoblog_last_ideation_data',   [ 'status' => 'idle' ] );
        $production = get_option( 'autoblog_last_production_data', [ 'status' => 'idle' ] );

        // Render sources list terakhir
        $sources_html = '';
        if ( ! empty( $ingestion['sources'] ) ) {
            $sources_html .= '<ul style="font-size: 11px; color: #64748b; background: #ffffff; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; margin: 10px 0 0;">';
            foreach ( array_slice( $ingestion['sources'], -3 ) as $src ) {
                $sources_html .= '<li style="margin-bottom: 4px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 4px;">' . esc_html( $src ) . '</li>';
            }
            $sources_html .= '</ul>';
        }

        $get_badge = function ( $status ) {
            $map = [
                'running'   => [ '#2271b1', '🔄 RUNNING' ],
                'completed' => [ '#46b450', '✅ COMPLETED' ],
                'failed'    => [ '#d63638', '❌ FAILED' ],
                'skipped'   => [ '#dba617', '⚠️ SKIPPED' ],
            ];
            [ $color, $label ] = isset( $map[ $status ] ) ? $map[ $status ] : [ '#999', '💤 IDLE' ];
            return "<span style='background: {$color}; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;'>{$label}</span>";
        };

        wp_send_json_success( [
            'html'     => esc_textarea( $log_content ),
            'statuses' => [
                'collector' => [
                    'status'    => $ingestion['status'],
                    'badge'     => $get_badge( $ingestion['status'] ),
                    'last_sync' => isset( $ingestion['timestamp'] ) ? esc_html( $ingestion['timestamp'] ) : '-',
                    'ingested'  => isset( $ingestion['count'] )     ? intval( $ingestion['count'] )       : 0,
                    'sources'   => $sources_html,
                ],
                'ideator' => [
                    'status'          => $ideation['status'],
                    'badge'           => $get_badge( $ideation['status'] ),
                    'last_brainstorm' => isset( $ideation['timestamp'] ) ? esc_html( $ideation['timestamp'] ) : '-',
                    'topic'           => isset( $ideation['title'] ) ? '"' . esc_html( $ideation['title'] ) . '"' : 'Waiting for next brainstorm session...',
                    'topic_plain'     => isset( $ideation['title'] ) ? esc_html( $ideation['title'] ) : 'No topic selected',
                ],
                'writer' => [
                    'status'         => $production['status'],
                    'badge'          => $get_badge( $production['status'] ),
                    'last_published' => isset( $production['timestamp'] ) ? esc_html( $production['timestamp'] ) : '-',
                    'topic'          => isset( $production['topic'] ) ? esc_html( $production['topic'] ) : '-',
                    'topic_attr'     => isset( $production['topic'] ) ? esc_attr( $production['topic'] ) : '',
                    'post_id'        => isset( $production['post_id'] ) && $production['post_id'] > 0 ? intval( $production['post_id'] ) : '-',
                    'result'         => ( isset( $production['post_id'] ) && $production['post_id'] > 0 )
                        ? '<a href="' . get_edit_post_link( $production['post_id'] ) . '" target="_blank" style="color: #2271b1; text-decoration: none; font-weight: 600;">View Post ID: ' . $production['post_id'] . ' ↗</a>'
                        : esc_html( strtoupper( $production['status'] ) ),
                ],
            ],
        ]);
    }
}
