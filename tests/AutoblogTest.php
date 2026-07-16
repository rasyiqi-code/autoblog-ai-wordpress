<?php
/**
 * Unit Test untuk Autoblog\\Core\\Autoblog — Entry Point Plugin Class.
 *
 * Autoblog adalah kelas utama plugin yang:
 * 1. Constructor: set version & plugin_name, load dependencies, set locale,
 *    define admin hooks, define public hooks.
 * 2. load_dependencies(): require Loader.php + i18n.php, create Loader.
 * 3. set_locale(): create i18n, register plugins_loaded action.
 * 4. define_admin_hooks(): require admin/*.php, register ~20+ actions/filters
 *    untuk menu, assets, settings, pipeline, scheduler, content refresher.
 * 5. define_public_hooks(): (kosong — commented out).
 * 6. run(): delegasikan ke Loader::run().
 * 7. Getters: get_plugin_name(), get_loader(), get_version().
 *
 * Karena constructor memanggil require_once dengan path relatif terhadap
 * __FILE__ asli (Autoblog.php di disk), semua file exists dan require
 * berhasil. Objek-objek yang dibuat di constructor (Loader, i18n, Admin,
 * AdminSettings, AdminAjax, Runner, UpdateScheduler, ContentRefresher)
 * semuanya memiliki constructor sederhana tanpa parameter wajib atau
 * constructor default, sehingga instantiasi berjalan lancar.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      entrypoint
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\Autoblog;
use Autoblog\Core\Loader;
use Autoblog\Utils\OptionCache;

class AutoblogTest extends TestCase {

    /** @var Autoblog */
    private $autoblog;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    /**
     * Test bahwa constructor mengeksekusi tanpa exception dan
     * mengembalikan instance Autoblog yang valid.
     *
     * Constructor memanggil:
     * - load_dependencies() → require 2 file + new Loader
     * - set_locale() → new i18n + $loader->add_action
     * - define_admin_hooks() → require 5+ file + new 6+ object
     * - define_public_hooks() → no-op
     */
    public function test_constructor_creates_instance() {
        $autoblog = new Autoblog();
        $this->assertInstanceOf( Autoblog::class, $autoblog );
    }

    /**
     * Test bahwa plugin_name diset ke 'autoblog'.
     */
    public function test_constructor_sets_plugin_name() {
        $autoblog = new Autoblog();
        $this->assertSame( 'autoblog', $autoblog->get_plugin_name() );
    }

    /**
     * Test bahwa version fallback ke '1.0.0' jika AUTOBLOG_VERSION
     * tidak didefinisikan (sesuai environment test).
     */
    public function test_constructor_sets_version_default_when_constant_undefined() {
        // AUTOBLOG_VERSION tidak didefinisikan di test env
        $this->assertFalse( defined( 'AUTOBLOG_VERSION' ), 'AUTOBLOG_VERSION seharusnya tidak terdefinisi' );

        $autoblog = new Autoblog();
        $this->assertSame( '1.0.0', $autoblog->get_version(),
            'Tanpa AUTOBLOG_VERSION, version harus fallback ke 1.0.0'
        );
    }

    /**
     * Test bahwa version menggunakan AUTOBLOG_VERSION jika didefinisikan.
     *
     * Dijalankan di process terpisah agar define() tidak mempengaruhi
     * test lain dalam class yang sama.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_constructor_uses_autoblog_version_constant_when_defined() {
        define( 'AUTOBLOG_VERSION', '9.9.9-test' );

        $autoblog = new Autoblog();
        $this->assertSame( '9.9.9-test', $autoblog->get_version() );
    }

    // ====================================================================
    // GETTERS
    // ====================================================================

    /**
     * Test getter get_version() mengembalikan string.
     */
    public function test_get_version_returns_string() {
        $autoblog = new Autoblog();
        $this->assertIsString( $autoblog->get_version() );
    }

    /**
     * Test getter get_plugin_name() mengembalikan string.
     */
    public function test_get_plugin_name_returns_string() {
        $autoblog = new Autoblog();
        $this->assertIsString( $autoblog->get_plugin_name() );
    }

    /**
     * Test bahwa get_loader() mengembalikan instance Loader.
     *
     * Loader dibuat di load_dependencies() dan disimpan di
     * $this->loader. get_loader() mengembalikan referensi yang
     * sama.
     */
    public function test_get_loader_returns_loader_instance() {
        $autoblog = new Autoblog();
        $loader = $autoblog->get_loader();

        $this->assertInstanceOf( Loader::class, $loader );
    }

    /**
     * Test bahwa get_loader() mengembalikan objek yang sama setiap
     * kali dipanggil (singleton dalam satu instance).
     */
    public function test_get_loader_returns_same_instance() {
        $autoblog = new Autoblog();
        $loader1 = $autoblog->get_loader();
        $loader2 = $autoblog->get_loader();

        $this->assertSame( $loader1, $loader2 );
    }

    // ====================================================================
    // RUN() METHOD
    // ====================================================================

    /**
     * Test bahwa run() mengeksekusi tanpa exception.
     *
     * run() memanggil $this->loader->run(), yang mengiterasi
     * $actions dan $filters dan memanggil add_action/add_filter
     * untuk setiap hook. Di test environment, fungsi-fungsi WP
     * ini sudah di-mock di bootstrap.php menjadi no-op.
     */
    public function test_run_method_completes_without_error() {
        $autoblog = new Autoblog();

        // Tidak ada exception yang dilempar
        $autoblog->run();

        $this->assertTrue( true, 'run() harus selesai tanpa error' );
    }

    /**
     * Test bahwa run() dapat dipanggil multiple kali tanpa error.
     *
     * Loader::run() mengiterasi array internal — panggilan kedua
     * akan memproses ulang (hooks yang sama dipanggil lagi ke
     * add_action/add_filter yang no-op).
     */
    public function test_run_method_called_multiple_times() {
        $autoblog = new Autoblog();
        $autoblog->run();
        $autoblog->run();

        $this->assertTrue( true, 'run() harus bisa dipanggil multiple kali' );
    }

    // ====================================================================
    // LOADER — HOOK REGISTRATION VERIFICATION
    // ====================================================================

    /**
     * Test bahwa loader memiliki actions setelah konstruksi.
     *
     * define_admin_hooks() dan set_locale() mendaftarkan banyak
     * actions ke loader. Kita verifikasi melalui reflection bahwa
     * $loader->actions tidak kosong.
     */
    public function test_loader_has_actions_after_construction() {
        $autoblog = new Autoblog();
        $loader   = $autoblog->get_loader();

        $actions = $this->getPrivateProperty( $loader, 'actions' );

        $this->assertIsArray( $actions );
        $this->assertNotEmpty( $actions, 'Loader harus memiliki actions setelah konstruksi' );
    }

    /**
     * Test bahwa loader memiliki actions termasuk 'admin_menu'.
     *
     * define_admin_hooks() mendaftarkan:
     *   $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
     *
     * Kita verifikasi 'admin_menu' ada di daftar actions.
     */
    public function test_loader_includes_admin_menu_action() {
        $autoblog = new Autoblog();
        $loader   = $autoblog->get_loader();

        $actions = $this->getPrivateProperty( $loader, 'actions' );
        $hooks   = array_column( $actions, 'hook' );

        $this->assertContains( 'admin_menu', $hooks,
            'Loader harus memiliki action admin_menu dari define_admin_hooks()'
        );
    }

    /**
     * Test bahwa loader memiliki actions termasuk 'plugins_loaded'.
     *
     * set_locale() mendaftarkan:
     *   $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
     */
    public function test_loader_includes_plugins_loaded_action() {
        $autoblog = new Autoblog();
        $loader   = $autoblog->get_loader();

        $actions = $this->getPrivateProperty( $loader, 'actions' );
        $hooks   = array_column( $actions, 'hook' );

        $this->assertContains( 'plugins_loaded', $hooks,
            'Loader harus memiliki action plugins_loaded dari set_locale()'
        );

        // Verify callback is load_plugin_textdomain (i18n init)
        $plugins_loaded_actions = array_values( array_filter( $actions, function( $a ) {
            return $a['hook'] === 'plugins_loaded';
        } ) );

        $this->assertCount( 1, $plugins_loaded_actions,
            'Harus ada tepat 1 action plugins_loaded'
        );
        $this->assertSame( 'load_plugin_textdomain', $plugins_loaded_actions[0]['callback'],
            'Callback plugins_loaded harus i18n::load_plugin_textdomain()'
        );
    }

    /**
     * Test bahwa loader memiliki actions terkait AJAX pipeline runner.
     *
     * define_admin_hooks() mendaftarkan:
     *   wp_ajax_autoblog_run_pipeline
     *   wp_ajax_autoblog_run_collector
     *   wp_ajax_autoblog_run_ideator
     *   wp_ajax_autoblog_run_writer
     */
    public function test_loader_includes_ajax_pipeline_actions() {
        $autoblog = new Autoblog();
        $loader   = $autoblog->get_loader();

        $actions = $this->getPrivateProperty( $loader, 'actions' );
        $hooks   = array_column( $actions, 'hook' );

        $this->assertContains( 'wp_ajax_autoblog_run_pipeline', $hooks );
        $this->assertContains( 'wp_ajax_autoblog_run_collector', $hooks );
        $this->assertContains( 'wp_ajax_autoblog_run_ideator', $hooks );
        $this->assertContains( 'wp_ajax_autoblog_run_writer', $hooks );
    }

    /**
     * Test bahwa loader memiliki action untuk cron scheduler.
     *
     * define_admin_hooks() mendaftarkan:
     *   autoblog_run_pipeline (via Runner)
     *   autoblog_daily_refresh (via ContentRefresher)
     */
    public function test_loader_includes_cron_actions() {
        $autoblog = new Autoblog();
        $loader   = $autoblog->get_loader();

        $actions = $this->getPrivateProperty( $loader, 'actions' );
        $hooks   = array_column( $actions, 'hook' );

        $this->assertContains( 'autoblog_run_pipeline', $hooks,
            'Loader harus memiliki action autoblog_run_pipeline'
        );
        $this->assertContains( 'autoblog_daily_refresh', $hooks,
            'Loader harus memiliki action autoblog_daily_refresh'
        );
    }

    /**
     * Test bahwa loader memiliki filter 'cron_schedules'.
     *
     * define_admin_hooks() mendaftarkan:
     *   $this->loader->add_filter( 'cron_schedules', $scheduler, 'add_cron_intervals' );
     */
    public function test_loader_includes_cron_schedules_filter() {
        $autoblog = new Autoblog();
        $loader   = $autoblog->get_loader();

        $filters = $this->getPrivateProperty( $loader, 'filters' );
        $hooks   = array_column( $filters, 'hook' );

        $this->assertContains( 'cron_schedules', $hooks,
            'Loader harus memiliki filter cron_schedules'
        );
    }

    /**
     * Test bahwa loader memiliki action 'admin_enqueue_scripts'.
     *
     * define_admin_hooks() mendaftarkan:
     *   $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
     *   $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
     */
    public function test_loader_includes_admin_enqueue_actions() {
        $autoblog = new Autoblog();
        $loader   = $autoblog->get_loader();

        $actions = $this->getPrivateProperty( $loader, 'actions' );

        // Filter actions dengan hook = 'admin_enqueue_scripts'
        $enqueue_actions = array_values( array_filter( $actions, function( $a ) {
            return $a['hook'] === 'admin_enqueue_scripts';
        } ) );

        $this->assertCount( 2, $enqueue_actions,
            'Harus ada 2 action admin_enqueue_scripts (styles + scripts)'
        );

        $callbacks = array_column( $enqueue_actions, 'callback' );
        $this->assertContains( 'enqueue_styles', $callbacks );
        $this->assertContains( 'enqueue_scripts', $callbacks );
    }

    // ====================================================================
    // HELPER
    // ====================================================================

    /**
     * Ambil private/protected property dari objek via reflection.
     *
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    private function getPrivateProperty( $object, string $propertyName ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $property   = $reflection->getProperty( $propertyName );
        $property->setAccessible( true );
        return $property->getValue( $object );
    }
}
