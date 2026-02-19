<?php
/**
 * PHPUnit Bootstrap
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Mock WordPress functions if not exists
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $tag, ...$arg ) {}
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) { return $value; }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return $text; }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return $text; }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) { return $text; }
}

if ( ! function_exists( '_e' ) ) {
    function _e( $text, $domain = 'default' ) { echo $text; }
}

/* Mock other WP functions needed by PostManager */
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() { return 1; }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) { return $default; }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) { return true; }
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
        return array(
            'path'    => sys_get_temp_dir(),
            'url'     => 'http://example.com/uploads',
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://example.com/uploads',
            'error'   => false,
        );
    }
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
    function wp_mkdir_p( $target ) { return true; }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) { return date( 'Y-m-d H:i:s' ); }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public function __construct( $code = '', $message = '', $data = '' ) {}
        public function get_error_message() { return ''; }
    }
}
