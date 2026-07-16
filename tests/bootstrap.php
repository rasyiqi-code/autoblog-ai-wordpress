<?php
/**
 * PHPUnit Bootstrap — WordPress Test Suite + Fallback Mocks
 *
 * Strategi:
 * 1. Jika WP_TESTS_DIR environment variable diset, load WordPress test suite
 *    (biasanya via bin/install-wp-tests.sh)
 * 2. Jika tidak, load mock functions sebagai fallback
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

defined( 'WP_TESTS_PLUGIN_DIR' ) or define( 'WP_TESTS_PLUGIN_DIR', dirname( __DIR__ ) );

// Load Composer autoloader
require_once WP_TESTS_PLUGIN_DIR . '/vendor/autoload.php';

// ── Mode 1: WordPress Test Suite ──
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( $wp_tests_dir && file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
    require_once $wp_tests_dir . '/includes/functions.php';

    /**
     * Plugin manual load.
     */
    function _manually_load_plugin() {
        require WP_TESTS_PLUGIN_DIR . '/autoblog.php';
    }
    tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

    require_once $wp_tests_dir . '/includes/bootstrap.php';

    echo "WordPress Test Suite: LOADED\n";
    return;
}

// ── Mode 2: Mock Fallback ──
echo "WordPress Test Suite: NOT FOUND (using mocks)\n";
require_once __DIR__ . '/mocks.php';



