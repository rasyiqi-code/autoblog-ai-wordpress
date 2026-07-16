<?php
/**
 * WP Mock Functions — Fallback ketika WordPress tidak tersedia.
 *
 * File ini berisi mock functions untuk WordPress functions yang dibutuhkan
 * oleh plugin tests. Hanya digunakan jika WordPress test suite tidak aktif.
 *
 * Tests yang perlu mock external API (AI, search engine) harus meng-include
 * file ini secara eksplisit atau via setUp() masing-masing.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

// ── Call tracking helper ──
// Inisialisasi global call tracker (jika belum)
$GLOBALS['_wp_mock_calls'] = isset( $GLOBALS['_wp_mock_calls'] ) ? $GLOBALS['_wp_mock_calls'] : [];
$GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [ 'id' => 'toplevel_page_autoblog' ];

// ── Hooks ──
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'do_action' ) ) {
    function do_action( $tag, ...$arg ) {}
}
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'remove_filter' ) ) {
    function remove_filter( $tag, $function_to_remove, $priority = 10 ) { return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) { return $value; }
}

// ── i18n ──
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( '_e' ) ) {
    function _e( $text, $domain = 'default' ) { echo $text; }
}
if ( ! function_exists( 'date_i18n' ) ) {
    function date_i18n( $format, $timestamp = null ) {
        return date( $format, $timestamp ?? time() );
    }
}

// ── Escaping ──
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return $text; }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return $text; }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url, $protocols = null ) { return $url; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $content ) { return $content; }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return trim( $str ); }
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( $filename ) {
        return preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );
    }
}
if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $title ) {
        return strtolower( str_replace( ' ', '-', trim( $title ) ) );
    }
}

// ── Options / Transients ──
$GLOBALS['_autoblog_mock_options']    = [];
$GLOBALS['_autoblog_mock_transients'] = [];

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        global $_autoblog_mock_options;
        return isset( $_autoblog_mock_options[ $option ] ) ? $_autoblog_mock_options[ $option ] : $default;
    }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        global $_autoblog_mock_options;
        $_autoblog_mock_options[ $option ] = $value;
        return true;
    }
}
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options[ $option ] );
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        global $_autoblog_mock_transients;
        return isset( $_autoblog_mock_transients[ $key ] ) ? $_autoblog_mock_transients[ $key ] : false;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration = 0 ) {
        global $_autoblog_mock_transients;
        $_autoblog_mock_transients[ $key ] = $value;
        return true;
    }
}

// ── Time ──
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) { return date( 'Y-m-d H:i:s' ); }
}

// ── URL / Path ──
if ( ! function_exists( 'get_site_url' ) ) {
    function get_site_url() { return 'http://example.com'; }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '', $scheme = 'admin' ) { return 'http://example.com/wp-admin/' . $path; }
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/autoblog/'; }
}
if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( $file ) { return basename( $file ); }
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
        return [
            'path'    => sys_get_temp_dir(),
            'url'     => 'http://example.com/uploads',
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://example.com/uploads',
            'error'   => false,
        ];
    }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
    function wp_mkdir_p( $target ) {
        if ( ! is_dir( $target ) ) { return @mkdir( $target, 0777, true ); }
        return true;
    }
}

// ── Users ──
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() { return 1; }
}
if ( ! function_exists( 'get_users' ) ) {
    function get_users( $args = [] ) {
        return [
            (object) [ 'ID' => 1, 'display_name' => 'Admin' ],
            (object) [ 'ID' => 2, 'display_name' => 'Author' ],
        ];
    }
}
if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( $user_id, $key = '', $single = false ) {
        $meta = [ 1 => [ '_autoblog_persona_name' => '', '_autoblog_personality_samples' => '' ] ];
        if ( empty( $key ) ) { return isset( $meta[ $user_id ] ) ? $meta[ $user_id ] : []; }
        $value = isset( $meta[ $user_id ][ $key ] ) ? $meta[ $user_id ][ $key ] : '';
        return $single ? $value : [ $value ];
    }
}
if ( ! function_exists( 'update_user_meta' ) ) {
    function update_user_meta( $user_id, $meta_key, $meta_value ) { return true; }
}

// ── Posts / WP_Query ──
$GLOBALS['_autoblog_mock_wp_query_posts'] = [];

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $errors = [];
        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( $code ) $this->errors[ $code ] = [ $message ];
        }
        public function get_error_message() { return 'Mock WP_Error'; }
        public function get_error_code() { return 'mock_error'; }
    }
}
if ( ! class_exists( 'WP_Post' ) ) {
    class WP_Post {
        public $ID = 1;
        public $post_title = 'Mock Title';
        public $post_content = 'Mock content.';
        public $post_status = 'publish';
    }
}
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = [];
        public $post = null;
        private $current_post = 0;
        public function __construct( $args = [] ) {
            $GLOBALS['_wp_mock_calls']['WP_Query::__construct'][] = $args;
            global $_autoblog_mock_wp_query_posts;
            if ( isset( $_autoblog_mock_wp_query_posts ) ) {
                $this->posts = $_autoblog_mock_wp_query_posts;
            }
            $this->current_post = 0;
        }
        public function have_posts() { return isset( $this->posts[ $this->current_post ] ); }
        public function the_post() {
            if ( isset( $this->posts[ $this->current_post ] ) ) {
                $this->post = $this->posts[ $this->current_post ];
            }
            $this->current_post++;
        }
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}
if ( ! function_exists( 'get_the_title' ) ) {
    function get_the_title( $post = 0 ) { return 'Mock Title'; }
}
if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post = 0 ) { return 'http://example.com/mock-post'; }
}
if ( ! function_exists( 'get_the_content' ) ) {
    function get_the_content( $more_link_text = null, $strip_teaser = false ) { return 'Mock content.'; }
}
if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() { return 1; }
}
if ( ! function_exists( 'have_posts' ) ) {
    function have_posts() {
        global $wp_query;
        return $wp_query && $wp_query->have_posts();
    }
}
if ( ! function_exists( 'the_post' ) ) {
    function the_post() {
        global $wp_query;
        if ( $wp_query ) $wp_query->the_post();
    }
}
if ( ! function_exists( 'wp_reset_postdata' ) ) {
    function wp_reset_postdata() {}
}
if ( ! function_exists( 'get_categories' ) ) {
    function get_categories( $args = [] ) { return []; }
}
if ( ! function_exists( 'wp_list_pluck' ) ) {
    function wp_list_pluck( $list, $field ) { return []; }
}
if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $post_id, $meta_key, $meta_value ) { return true; }
}
if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key = '', $single = false ) { return ''; }
}
if ( ! function_exists( 'wp_update_post' ) ) {
    function wp_update_post( $post_data = [] ) { return 1; }
}
if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $post_data, $wp_error = false ) { return 1; }
}
if ( ! function_exists( 'set_post_thumbnail' ) ) {
    function set_post_thumbnail( $post, $thumbnail_id ) { return true; }
}
if ( ! function_exists( 'wp_set_post_categories' ) ) {
    function wp_set_post_categories( $post_id, $categories ) {}
}
if ( ! function_exists( 'wp_set_post_tags' ) ) {
    function wp_set_post_tags( $post_id, $tags, $append = false ) {}
}
if ( ! function_exists( 'get_term_by' ) ) {
    function get_term_by( $field, $value, $taxonomy = 'category' ) { return false; }
}
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
    function wp_get_attachment_url( $attachment_id ) { return 'http://example.com/image.jpg'; }
}
if ( ! function_exists( 'media_sideload_image' ) ) {
    function media_sideload_image( $file, $post_id = 0, $desc = null, $return = 'html' ) {
        $GLOBALS['_wp_mock_calls']['media_sideload_image'][] = func_get_args();
        // Support controllable return untuk testing
        if ( isset( $GLOBALS['_autoblog_mock_media_sideload_return'] ) ) {
            return $GLOBALS['_autoblog_mock_media_sideload_return'];
        }
        return 1;
    }
}

// ── HTTP / Remote ──
$GLOBALS['_autoblog_mock_remote_response'] = null;
$GLOBALS['_autoblog_mock_remote_body']     = '';

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = [] ) {
        global $_autoblog_mock_remote_response;
        return isset( $_autoblog_mock_remote_response ) ? $_autoblog_mock_remote_response : [];
    }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        global $_autoblog_mock_remote_body;
        return isset( $_autoblog_mock_remote_body ) ? $_autoblog_mock_remote_body : '';
    }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) { return 200; }
}
if ( ! function_exists( 'wp_remote_retrieve_response_message' ) ) {
    function wp_remote_retrieve_response_message( $response ) { return 'OK'; }
}
if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
    function wp_remote_retrieve_headers( $response ) { return []; }
}

// ── Cron ──
if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook ) { return false; }
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $time, $schedule, $hook ) {}
}
if ( ! function_exists( 'wp_unschedule_event' ) ) {
    function wp_unschedule_event( $time, $hook ) {}
}
if ( ! function_exists( 'spawn_cron' ) ) {
    function spawn_cron() {}
}
if ( ! function_exists( 'wp_cache_delete' ) ) {
    function wp_cache_delete( $key, $group = '' ) { return true; }
}

// ── Content ──
if ( ! function_exists( 'wpautop' ) ) {
    function wpautop( $text, $br = true ) { return '<p>' . $text . '</p>'; }
}
if ( ! function_exists( 'remove_all_filters' ) ) {
    function remove_all_filters( $tag, $priority = false ) {}
}

// ── Admin ──
if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action = -1 ) { return true; }
}
if ( ! function_exists( 'check_admin_referer' ) ) {
    function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { return true; }
}
if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '', $title = '', $args = [] ) {}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) { return md5( $action . time() ); }
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
    function wp_safe_redirect( $location, $status = 302 ) { return true; }
}
if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
    function wp_add_dashboard_widget( $widget_id, $widget_name, $callback ) {
        $GLOBALS['_wp_mock_calls']['wp_add_dashboard_widget'][] = func_get_args();
    }
}
if ( ! function_exists( 'wp_handle_upload' ) ) {
    function wp_handle_upload( $file, $overrides = [] ) {
        return [
            'file'  => '/tmp/uploads/test.pdf',
            'url'   => 'http://example.com/uploads/test.pdf',
            'type'  => 'application/pdf',
        ];
    }
}
if ( ! function_exists( 'wp_check_filetype' ) ) {
    function wp_check_filetype( $filename, $mimes = [] ) {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( isset( $mimes[ $ext ] ) ) {
            return [ 'ext' => $ext, 'type' => $mimes[ $ext ] ];
        }
        return [ 'ext' => '', 'type' => '' ];
    }
}
if ( ! function_exists( 'get_current_screen' ) ) {
    function get_current_screen() {
        // Support override via $GLOBALS untuk testing
        if ( isset( $GLOBALS['_wp_mock_calls']['get_current_screen_return'] ) ) {
            return $GLOBALS['_wp_mock_calls']['get_current_screen_return'];
        }
        return (object) [ 'id' => 'toplevel_page_autoblog' ];
    }
}

// ── Admin Menu ──
// ── Admin Menu ──
if ( ! function_exists( 'add_menu_page' ) ) {
    function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null ) {
        $GLOBALS['_wp_mock_calls']['add_menu_page'][] = func_get_args();
        return $menu_slug;
    }
}
if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
        $GLOBALS['_wp_mock_calls']['add_submenu_page'][] = func_get_args();
        return $menu_slug;
    }
}

// ── Assets ──
if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
        $GLOBALS['_wp_mock_calls']['wp_enqueue_style'][] = func_get_args();
    }
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {
        $GLOBALS['_wp_mock_calls']['wp_enqueue_script'][] = func_get_args();
    }
}
if ( ! function_exists( 'wp_style_is' ) ) {
    function wp_style_is( $handle, $list = 'enqueued' ) {
        $calls = $GLOBALS['_wp_mock_calls']['wp_enqueue_style'] ?? [];
        $handles = array_map( function( $args ) { return $args[0]; }, $calls );
        return in_array( $handle, $handles, true );
    }
}
if ( ! function_exists( 'wp_script_is' ) ) {
    function wp_script_is( $handle, $list = 'enqueued' ) {
        $calls = $GLOBALS['_wp_mock_calls']['wp_enqueue_script'] ?? [];
        $handles = array_map( function( $args ) { return $args[0]; }, $calls );
        return in_array( $handle, $handles, true );
    }
}
if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $l10n ) {
        $GLOBALS['_wp_mock_calls']['wp_localize_script'][] = func_get_args();
    }
}
if ( ! function_exists( 'wp_add_inline_script' ) ) {
    function wp_add_inline_script( $handle, $data, $position = 'after' ) {
        $GLOBALS['_wp_mock_calls']['wp_add_inline_script'][] = func_get_args();
    }
}
if ( ! function_exists( 'wp_dequeue_script' ) ) {
    function wp_dequeue_script( $handle ) {
        $GLOBALS['_wp_mock_calls']['wp_dequeue_script'][] = func_get_args();
    }
}

// ── Plugin lifecycle ──
if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ) {}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( $file, $callback ) {}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = '' ) {}
}

// ── Constants ──
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', sys_get_temp_dir() . '/wp-abspath/' );
    $wp_admin_dir = ABSPATH . 'wp-admin/includes/';
    if ( ! is_dir( $wp_admin_dir ) ) {
        @mkdir( $wp_admin_dir, 0777, true );
        foreach ( [ 'media.php', 'file.php', 'image.php' ] as $sf ) {
            if ( ! file_exists( $wp_admin_dir . $sf ) ) {
                file_put_contents( $wp_admin_dir . $sf, '<?php' );
            }
        }
    }
}

// ── PHP Helpers ──
if ( ! function_exists( '__return_true' ) ) {
    function __return_true() { return true; }
}
if ( ! function_exists( 'mb_scrub' ) ) {
    function mb_scrub( $string, $encoding = null ) {
        if ( $encoding === null ) { $encoding = 'UTF-8'; }
        return mb_convert_encoding( $string, $encoding, $encoding );
    }
}
