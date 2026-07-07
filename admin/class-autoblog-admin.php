<?php

namespace Autoblog\Admin;

use Autoblog\Utils\ModelCatalog;

/**
 * Admin
 *
 * Kelas tipis yang menangani layer presentasi admin:
 * - Enqueue CSS & JS
 * - Registrasi menu
 * - Render halaman (display)
 * - Handle aksi Data Sources (upload, hapus file, tambah/hapus source)
 *
 * AJAX handlers → AdminAjax
 * Settings registration & model catalog → AdminSettings
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin
 */
class Admin {

    /** @var string Plugin name/slug */
    private $plugin_name;

    /** @var string Plugin version */
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    // ================================================================
    // ASSETS: CSS & JS
    // ================================================================

    /**
     * Enqueue CSS admin plugin.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'css/autoblog-admin.css',
            [],
            $this->version,
            'all'
        );

        // Load CSS khusus halaman taxonomy tools
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'posts_page_autoblog-taxonomy-tools' ) {
            wp_enqueue_style(
                $this->plugin_name . '-taxonomy',
                plugin_dir_url( __FILE__ ) . 'css/autoblog-admin-taxonomy.css',
                [],
                $this->version,
                'all'
            );
        }
    }

    /**
     * Enqueue JS admin plugin (4 file modular).
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        $allowed_screens = [
            'toplevel_page_' . $this->plugin_name,
            'posts_page_autoblog-taxonomy-tools',
        ];

        if ( ! $screen || ! in_array( $screen->id, $allowed_screens ) ) {
            return;
        }

        $base_url = plugin_dir_url( __FILE__ ) . 'js/';

        // Siapkan data untuk JS via localize
        $custom_keys = get_option( 'autoblog_custom_api_keys', [] );
        $keys_filled = [
            'openai'     => ! empty( $custom_keys['openai'] )      ? true : ! empty( get_option( 'autoblog_openai_key' ) ),
            'gemini_001' => ! empty( $custom_keys['google'] )      ? true : ! empty( get_option( 'autoblog_gemini_key' ) ),
            'hf'         => ! empty( $custom_keys['huggingface'] ) ? true :
                            ( ! empty( $custom_keys['hf'] )         ? true : ! empty( get_option( 'autoblog_hf_key' ) ) ),
        ];

        $localize_data = [
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'autoblog_ajax_nonce' ),
            'keys_filled'       => $keys_filled,
            'catalog_models'    => ModelCatalog::get_merged_models(),
            'selected_model'    => get_option( 'autoblog_ai_model', 'gemini-1.5-flash' ),
            'dynamic_providers' => ModelCatalog::get_dynamic_providers(),
            'custom_keys'       => $custom_keys,
            'custom_endpoints'  => get_option( 'autoblog_custom_api_endpoints', [] ),
        ];

        // 1. Pipeline runner + log polling + agent flow diagram
        wp_enqueue_script( $this->plugin_name . '-pipeline',     $base_url . 'autoblog-pipeline.js',     [ 'jquery' ], $this->version, true );
        wp_localize_script( $this->plugin_name . '-pipeline', 'autoblog_ajax', $localize_data );

        // 2. AI Engine: dropdown provider/model + Gemini Grounding test
        wp_enqueue_script( $this->plugin_name . '-ai-engine',    $base_url . 'autoblog-ai-engine.js',    [ 'jquery' ], $this->version, true );

        // 3. Custom API Keys CRUD + test connection
        wp_enqueue_script( $this->plugin_name . '-api-keys',     $base_url . 'autoblog-api-keys.js',     [ 'jquery' ], $this->version, true );

        // 4. Data Sources toggle (RSS/Web/Search)
        wp_enqueue_script( $this->plugin_name . '-data-sources', $base_url . 'autoblog-data-sources.js', [ 'jquery' ], $this->version, true );

        // Anti-conflict: cegah error inlineEditPost di halaman taxonomy tools
        if ( $screen->id === 'posts_page_autoblog-taxonomy-tools' ) {
            wp_dequeue_script( 'inline-edit-post' );
            wp_add_inline_script( $this->plugin_name . '-pipeline', 'var inlineEditPost = { init: function(){} };', 'before' );
        }
    }

    // ================================================================
    // MENU & DISPLAY
    // ================================================================

    /**
     * Registrasi menu plugin di WordPress Dashboard.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'Autoblog AI Settings',
            'Autoblog AI',
            'manage_options',
            $this->plugin_name,
            [ $this, 'display_plugin_setup_page' ],
            'dashicons-superhero',
            110
        );

        add_submenu_page(
            'edit.php',
            'Auto-Set Taxonomy',
            'Auto-Set Taxonomy',
            'manage_options',
            'autoblog-taxonomy-tools',
            [ $this, 'display_taxonomy_tools_page' ]
        );
    }

    /**
     * Daftarkan widget kustom di Dashboard utama WordPress.
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'autoblog_crediblemark_promo_widget',
            '🚀 Custom Web & App Development - CredibleMark',
            [ $this, 'render_crediblemark_promo_widget' ]
        );
    }

    /**
     * Render isi widget promosi CredibleMark.
     */
    public function render_crediblemark_promo_widget() {
        ?>
        <div style="padding: 5px 0;">
            <p style="font-size: 14px; font-weight: 700; color: #1d2327; margin-top: 0;">Butuh Website Premium atau Sistem Automasi Bisnis?</p>
            <p><strong>CredibleMark</strong> adalah mitra teknologi terpercaya Anda untuk membangun solusi digital berkualitas tinggi tanpa biaya lisensi bulanan (100% milik Anda).</p>
            
            <ul style="list-style-type: disc; padding-left: 20px; margin: 12px 0; color: #50575e;">
                <li><strong>WordPress Kustom:</strong> Sangat cepat, aman, dan UI/UX bespoke yang disesuaikan khusus untuk bisnis Anda.</li>
                <li><strong>Sistem Operasional Khusus:</strong> Pembangunan CRM, Pelacakan Inventaris, Portal Klien, dan Integrasi Database kustom.</li>
                <li><strong>Automasi Proses Bisnis:</strong> Kurangi dokumen manual dan otomatiskan alur kerja harian untuk meningkatkan penjualan.</li>
            </ul>

            <p style="margin-top: 15px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <a href="https://crediblemark.com" target="_blank" class="button button-primary" style="font-weight: 600;">Kunjungi Website Kami</a>
                <a href="https://wa.me/6285183131249?text=Halo%20CredibleMark,%20saya%20tertarik%20tanya%20jasa%20pembuatan%20website" target="_blank" class="button button-secondary" style="font-weight: 600; color: #22c55e; border-color: #22c55e;">
                    💬 Chat WhatsApp (0851-8313-1249)
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Render halaman Taxonomy Tools.
     */
    public function display_taxonomy_tools_page() {
        include_once 'partials/autoblog-admin-taxonomy-tools.php';
    }

    /**
     * Render halaman Settings utama.
     */
    public function display_plugin_setup_page() {
        include_once 'partials/autoblog-admin-display.php';
    }

    // ================================================================
    // DATA SOURCE ACTIONS (harus di admin_init, sebelum header dikirim)
    // ================================================================

    /**
     * Handle operasi mutasi Data Sources (upload, hapus KB, tambah/hapus source).
     *
     * Di-hook ke admin_init agar wp_safe_redirect() berfungsi
     * sebelum output HTML dimulai.
     */
    public function handle_data_source_actions() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $this->plugin_name ) {
            return;
        }

        $clean_url = admin_url( 'admin.php?page=autoblog&tab=data_sources' );

        // --- Upload file Knowledge Base ---
        if ( isset( $_POST['autoblog_upload_file'] ) && ! empty( $_FILES['autoblog_file'] ) ) {
            check_admin_referer( 'autoblog_datasource_verify' );

            $uploaded_file = $_FILES['autoblog_file'];
            $max_size      = 10 * 1024 * 1024; // 10 MB

            if ( $uploaded_file['size'] > $max_size ) {
                set_transient( 'autoblog_admin_notice_error', 'Upload gagal: Ukuran file melebihi batas 10MB.', 30 );
                wp_safe_redirect( $clean_url ); exit;
            }

            $allowed_mimes = [
                'pdf'  => 'application/pdf',
                'csv'  => 'text/csv',
                'txt'  => 'text/plain',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];

            $file_info = wp_check_filetype( basename( $uploaded_file['name'] ), $allowed_mimes );
            if ( empty( $file_info['ext'] ) || empty( $file_info['type'] ) ) {
                set_transient( 'autoblog_admin_notice_error', 'Upload gagal: Tipe/Format file tidak didukung.', 30 );
                wp_safe_redirect( $clean_url ); exit;
            }

            $movefile = wp_handle_upload( $uploaded_file, [ 'test_form' => false, 'mimes' => $allowed_mimes ] );

            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $knowledge_base   = get_option( 'autoblog_knowledge', [] );
                $knowledge_base[] = [
                    'id'   => uniqid( 'doc_' ),
                    'name' => basename( $movefile['file'] ),
                    'path' => $movefile['file'],
                    'url'  => $movefile['url'],
                    'date' => current_time( 'mysql' ),
                ];
                update_option( 'autoblog_knowledge', $knowledge_base );
                set_transient( 'autoblog_admin_notice', 'File berhasil ditambahkan ke Knowledge Base!', 30 );
            } else {
                set_transient( 'autoblog_admin_notice_error', 'Upload gagal: ' . $movefile['error'], 30 );
            }

            wp_safe_redirect( $clean_url ); exit;
        }

        // --- Hapus file Knowledge Base ---
        if ( isset( $_GET['delete_kb'] ) ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'autoblog_delete_kb' ) ) {
                wp_die( 'Security check gagal.' );
            }
            $idx = intval( $_GET['delete_kb'] );
            $kb  = get_option( 'autoblog_knowledge', [] );
            if ( isset( $kb[ $idx ] ) ) {
                $path = isset( $kb[ $idx ]['path'] ) ? $kb[ $idx ]['path'] : '';
                if ( $path && file_exists( $path ) ) { @unlink( $path ); }
                unset( $kb[ $idx ] );
                update_option( 'autoblog_knowledge', array_values( $kb ) );
                set_transient( 'autoblog_admin_notice', 'File dihapus dari Knowledge Base.', 30 );
            }
            wp_safe_redirect( $clean_url ); exit;
        }

        // --- Tambah Content Source ---
        if ( isset( $_POST['autoblog_add_source'] ) ) {
            check_admin_referer( 'autoblog_datasource_verify' );

            $sources = get_option( 'autoblog_sources', [] );
            $urls    = array_map( 'trim', explode( ',', $_POST['source_url'] ) );
            $type    = sanitize_text_field( $_POST['source_type'] );

            $count = 0;
            foreach ( $urls as $url ) {
                if ( empty( $url ) ) { continue; }
                $sources[] = [
                    'type'              => $type,
                    'url'               => ( $type === 'web_search' ) ? sanitize_text_field( $url ) : esc_url_raw( $url ),
                    'match_keywords'    => sanitize_text_field( $_POST['match_keywords'] ),
                    'negative_keywords' => sanitize_text_field( $_POST['negative_keywords'] ),
                    'selector'          => sanitize_text_field( $_POST['source_selector'] ),
                ];
                $count++;
            }
            update_option( 'autoblog_sources', $sources );
            set_transient( 'autoblog_admin_notice', $count . ' source berhasil ditambahkan.', 30 );
            wp_safe_redirect( $clean_url ); exit;
        }

        // --- Hapus Content Source ---
        if ( isset( $_GET['autoblog_delete_source'] ) ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'autoblog_delete_source' ) ) {
                wp_die( 'Security check gagal.' );
            }
            $index   = intval( $_GET['autoblog_delete_source'] );
            $sources = get_option( 'autoblog_sources', [] );
            if ( isset( $sources[ $index ] ) ) {
                unset( $sources[ $index ] );
                update_option( 'autoblog_sources', array_values( $sources ) );
                set_transient( 'autoblog_admin_notice', 'Source berhasil dihapus.', 30 );
            }
            wp_safe_redirect( $clean_url ); exit;
        }
    }

    // ================================================================
    // STATIC PROXIES: Backward compatibility → AdminSettings
    // ================================================================

    /**
     * @deprecated Gunakan AdminSettings::get_dynamic_models()
     */
    public static function get_dynamic_models() {
        return ModelCatalog::get_dynamic_models();
    }

    /** @deprecated Gunakan ModelCatalog::get_merged_models() */
    public static function get_merged_models() {
        return ModelCatalog::get_merged_models();
    }

    /** @deprecated Gunakan ModelCatalog::get_dynamic_providers() */
    public static function get_dynamic_providers() {
        return ModelCatalog::get_dynamic_providers();
    }
}
