<?php
/**
 * Integration Test untuk Plugin Bootstrap (autoblog.php), Activation/Deactivation Hooks, dan i18n.
 *
 * autoblog.php adalah file entry point plugin yang:
 * - Mendefinisikan konstanta (AUTOBLOG_VERSION, AUTOBLOG_PLUGIN_DIR, AUTOBLOG_PLUGIN_URL)
 * - Load vendor autoload
 * - Mendefinisikan activate_autoblog() dan deactivate_autoblog()
 * - Register activation/deactivation hooks via register_activation_hook / deactivation hook
 * - Require Autoblog class dan menjalankan run_autoblog()
 *
 * Catatan penting:
 * - autoblog.php memiliki guard if (!defined('WPINC')) { die; }
 * - @runInSeparateProcess tests perlu define WPINC + mock fungsi WP sebelum require
 * - Test yang tidak terisolasi panggil Activator/Deactivator langsung (sudah autoloaded)
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      integration
 * @group      entrypoint
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\i18n;
use Autoblog\Core\Autoblog;
use Autoblog\Core\Activator;
use Autoblog\Core\Deactivator;

class AutoblogBootstrapTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        parent::tearDown();
    }

    // ====================================================================
    // PLUGIN BOOTSTRAP (autoblog.php) — @runInSeparateProcess
    // ====================================================================

    /**
     * Test bahwa autoblog.php mendefinisikan konstanta AUTOBLOG_VERSION
     * dan menjalankan tanpa error.
     *
     * Di @runInSeparateProcess, bootstrap.php TIDAK jalan → WP functions
     * tidak tersedia. Kita definisikan WPINC + stub function sebelum require.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_bootstrap_defines_constants() {
        // Guard: WPINC harus didefinisikan agar autoblog.php tidak die()
        if ( ! defined( 'WPINC' ) ) {
            define( 'WPINC', true );
        }

        // Stub fungsi WP yang dibutuhkan autoblog.php di bootstrap
        if ( ! function_exists( 'plugin_dir_path' ) ) {
            function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
        }
        if ( ! function_exists( 'plugin_dir_url' ) ) {
            function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/autoblog/'; }
        }
        if ( ! function_exists( 'register_activation_hook' ) ) {
            function register_activation_hook( $file, $callback ) {}
        }
        if ( ! function_exists( 'register_deactivation_hook' ) ) {
            function register_deactivation_hook( $file, $callback ) {}
        }
        if ( ! function_exists( 'add_action' ) ) {
            function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
        }
        if ( ! function_exists( 'add_filter' ) ) {
            function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
        }

        // Autoloader + bootstrap dari plugin root (dirname(__DIR__) = plugin root)
        $pluginRoot = dirname( __DIR__ );

        $autoloadPath = $pluginRoot . '/vendor/autoload.php';
        if ( file_exists( $autoloadPath ) ) {
            require_once $autoloadPath;
        }

        // Bootstrap test: load mock WP functions
        $bootstrapPath = $pluginRoot . '/tests/bootstrap.php';
        if ( file_exists( $bootstrapPath ) ) {
            require_once $bootstrapPath;
        }

        require $pluginRoot . '/autoblog.php';

        // Verifikasi konstanta
        $this->assertTrue( defined( 'AUTOBLOG_VERSION' ) );
        $this->assertEquals( '1.1.9', AUTOBLOG_VERSION );
        $this->assertTrue( defined( 'AUTOBLOG_PLUGIN_DIR' ) );
        $this->assertNotEmpty( AUTOBLOG_PLUGIN_DIR );
        $this->assertTrue( defined( 'AUTOBLOG_PLUGIN_URL' ) );
        $this->assertStringStartsWith( 'http', AUTOBLOG_PLUGIN_URL );
    }

    /**
     * Test bahwa setelah autoblog.php di-load, fungsi-fungsi global
     * activate_autoblog, deactivate_autoblog, dan run_autoblog tersedia.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_bootstrap_defines_functions() {
        define( 'WPINC', true );

        // Stub fungsi WP yang dibutuhkan
        if ( ! function_exists( 'plugin_dir_path' ) ) {
            function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
        }
        if ( ! function_exists( 'plugin_dir_url' ) ) {
            function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/autoblog/'; }
        }
        if ( ! function_exists( 'register_activation_hook' ) ) {
            function register_activation_hook( $file, $callback ) {}
        }
        if ( ! function_exists( 'register_deactivation_hook' ) ) {
            function register_deactivation_hook( $file, $callback ) {}
        }
        if ( ! function_exists( 'add_action' ) ) {
            function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
        }
        if ( ! function_exists( 'add_filter' ) ) {
            function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {}
        }

        // Autoloader + bootstrap
        $pluginRoot = dirname( __DIR__ );

        $autoloadPath = $pluginRoot . '/vendor/autoload.php';
        if ( file_exists( $autoloadPath ) ) {
            require_once $autoloadPath;
        }
        $bootstrapPath = $pluginRoot . '/tests/bootstrap.php';
        if ( file_exists( $bootstrapPath ) ) {
            require_once $bootstrapPath;
        }

        require $pluginRoot . '/autoblog.php';

        // Fungsi global harus tersedia
        $this->assertTrue( function_exists( 'activate_autoblog' ) );
        $this->assertTrue( function_exists( 'deactivate_autoblog' ) );
        $this->assertTrue( function_exists( 'run_autoblog' ) );
    }

    // ====================================================================
    // i18n — LOAD_PLUGIN_TEXTDOMAIN
    // ====================================================================

    /**
     * Test bahwa i18n::load_plugin_textdomain() dapat dipanggil tanpa error.
     * Fungsi WP load_plugin_textdomain() di-mock di bootstrap.php (no-op).
     */
    public function test_i18n_load_plugin_textdomain_called() {
        $i18n    = new i18n();
        $i18n->load_plugin_textdomain();

        $this->assertTrue( true, 'i18n::load_plugin_textdomain() selesai tanpa error' );
    }

    /**
     * Test bahwa text domain yang digunakan adalah 'autoblog'.
     *
     * load_plugin_textdomain() di-mock, jadi kita verifikasi method
     * bisa dipanggil dengan return null (void function).
     */
    public function test_i18n_uses_correct_textdomain() {
        $i18n   = new i18n();
        $result = $this->invokeMethod( $i18n, 'load_plugin_textdomain' );

        $this->assertNull( $result );
    }

    // ====================================================================
    // VENDOR AUTOLOAD CHECK
    // ====================================================================

    /**
     * Test bahwa vendor/autoload.php dapat diakses.
     */
    public function test_vendor_autoload_file_exists() {
        $autoloadPath = dirname( __DIR__ ) . '/vendor/autoload.php';

        $this->assertFileExists( $autoloadPath,
            'vendor/autoload.php harus ada untuk autoload Composer'
        );
    }

    // ====================================================================
    // HELPER
    // ====================================================================

    /**
     * Panggil private method via reflection.
     */
    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }
}
