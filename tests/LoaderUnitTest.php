<?php
/**
 * Unit Test untuk Autoblog\\Core\\Loader — Hook Registration & Execution.
 *
 * Loader adalah kelas yang mengelola registrasi WordPress hooks:
 * - add_action(): tambah action ke collection
 * - add_filter(): tambah filter ke collection
 * - run(): iterasi collection dan panggil add_action / add_filter WP functions
 * - add(): internal — tambah hook ke array dengan format standar
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      entrypoint
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\Loader;

class LoaderUnitTest extends TestCase {

    /** @var Loader */
    private $loader;

    protected function setUp(): void {
        parent::setUp();
        $this->loader = new Loader();
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    public function test_constructor_initializes_empty_collections() {
        $actions = $this->getPrivateProperty( 'actions' );
        $filters = $this->getPrivateProperty( 'filters' );

        $this->assertIsArray( $actions );
        $this->assertIsArray( $filters );
        $this->assertEmpty( $actions, 'actions harus kosong di awal' );
        $this->assertEmpty( $filters, 'filters harus kosong di awal' );
    }

    // ====================================================================
    // ADD_ACTION
    // ====================================================================

    public function test_add_action_stores_hook() {
        $component = new \stdClass();
        $this->loader->add_action( 'init', $component, 'my_callback' );

        $actions = $this->getPrivateProperty( 'actions' );

        $this->assertCount( 1, $actions );
        $this->assertSame( 'init', $actions[0]['hook'] );
        $this->assertSame( $component, $actions[0]['component'] );
        $this->assertSame( 'my_callback', $actions[0]['callback'] );
    }

    public function test_add_action_default_priority() {
        $this->loader->add_action( 'wp_loaded', new \stdClass(), 'cb' );

        $actions = $this->getPrivateProperty( 'actions' );
        $this->assertEquals( 10, $actions[0]['priority'] );
        $this->assertEquals( 1, $actions[0]['accepted_args'] );
    }

    public function test_add_action_custom_priority() {
        $this->loader->add_action( 'shutdown', new \stdClass(), 'cb', 99, 3 );

        $actions = $this->getPrivateProperty( 'actions' );
        $this->assertEquals( 99, $actions[0]['priority'] );
        $this->assertEquals( 3, $actions[0]['accepted_args'] );
    }

    public function test_add_action_multiple_actions() {
        $this->loader->add_action( 'init', new \stdClass(), 'cb1' );
        $this->loader->add_action( 'admin_init', new \stdClass(), 'cb2' );
        $this->loader->add_action( 'wp_loaded', new \stdClass(), 'cb3' );

        $actions = $this->getPrivateProperty( 'actions' );
        $this->assertCount( 3, $actions );
        $this->assertSame( 'init', $actions[0]['hook'] );
        $this->assertSame( 'admin_init', $actions[1]['hook'] );
        $this->assertSame( 'wp_loaded', $actions[2]['hook'] );
    }

    // ====================================================================
    // ADD_FILTER
    // ====================================================================

    public function test_add_filter_stores_hook() {
        $this->loader->add_filter( 'the_content', new \stdClass(), 'filter_cb' );

        $filters = $this->getPrivateProperty( 'filters' );

        $this->assertCount( 1, $filters );
        $this->assertSame( 'the_content', $filters[0]['hook'] );
        $this->assertSame( 'filter_cb', $filters[0]['callback'] );
    }

    public function test_add_filter_default_priority() {
        $this->loader->add_filter( 'the_title', new \stdClass(), 'cb' );

        $filters = $this->getPrivateProperty( 'filters' );
        $this->assertEquals( 10, $filters[0]['priority'] );
    }

    public function test_add_filter_multiple() {
        $this->loader->add_filter( 'the_content', new \stdClass(), 'cb1' );
        $this->loader->add_filter( 'the_excerpt', new \stdClass(), 'cb2' );

        $filters = $this->getPrivateProperty( 'filters' );
        $this->assertCount( 2, $filters );
    }

    // ====================================================================
    // ADD (PRIVATE — VIA REFLECTION)
    // ====================================================================

    public function test_add_creates_correct_structure() {
        $component  = new \stdClass();
        $hookInfo   = $this->invokeAdd( [], 'test_hook', $component, 'test_cb', 5, 2 );

        $this->assertCount( 1, $hookInfo );

        $entry = $hookInfo[0];
        $this->assertArrayHasKey( 'hook', $entry );
        $this->assertArrayHasKey( 'component', $entry );
        $this->assertArrayHasKey( 'callback', $entry );
        $this->assertArrayHasKey( 'priority', $entry );
        $this->assertArrayHasKey( 'accepted_args', $entry );

        $this->assertSame( 'test_hook', $entry['hook'] );
        $this->assertSame( $component, $entry['component'] );
        $this->assertSame( 'test_cb', $entry['callback'] );
        $this->assertSame( 5, $entry['priority'] );
        $this->assertSame( 2, $entry['accepted_args'] );
    }

    public function test_add_appends_to_existing_array() {
        $existing = [ [ 'hook' => 'existing_hook' ] ];
        $result   = $this->invokeAdd( $existing, 'new_hook', new \stdClass(), 'cb', 10, 1 );

        $this->assertCount( 2, $result );
        $this->assertSame( 'existing_hook', $result[0]['hook'] );
        $this->assertSame( 'new_hook', $result[1]['hook'] );
    }

    // ====================================================================
    // RUN
    // ====================================================================

    /**
     * Test bahwa run() memanggil add_action() untuk setiap action
     * yang terdaftar, dan add_filter() untuk setiap filter yang terdaftar.
     *
     * add_action dan add_filter di-mock di bootstrap.php menjadi no-op.
     * Test memverifikasi method tidak throw exception.
     */
    public function test_run_calls_add_action_and_add_filter() {
        $component = new \stdClass();

        $this->loader->add_action( 'init', $component, 'on_init' );
        $this->loader->add_action( 'wp_loaded', $component, 'on_loaded' );
        $this->loader->add_filter( 'the_content', $component, 'filter_content' );

        // run() → iterasi actions & filters → panggil add_filter/add_action mock
        $this->loader->run();

        $this->assertTrue( true, 'run() harus selesai tanpa error' );
    }

    public function test_run_with_no_hooks() {
        // Loader kosong — tidak ada actions/filters
        $this->loader->run();

        $actions = $this->getPrivateProperty( 'actions' );
        $filters = $this->getPrivateProperty( 'filters' );

        $this->assertEmpty( $actions );
        $this->assertEmpty( $filters );
    }

    /**
     * Test bahwa run() dapat dipanggil multiple kali tanpa error.
     */
    public function test_run_called_multiple_times() {
        $this->loader->add_action( 'init', new \stdClass(), 'cb' );
        $this->loader->run();
        $this->loader->run();

        $this->assertTrue( true, 'run() harus bisa dipanggil multiple kali' );
    }

    // ====================================================================
    // ACTIONS VS FILTERS — SEPARATION
    // ====================================================================

    public function test_actions_and_filters_are_separate_collections() {
        $component = new \stdClass();

        $this->loader->add_action( 'init', $component, 'action_cb' );
        $this->loader->add_filter( 'the_content', $component, 'filter_cb' );

        $actions = $this->getPrivateProperty( 'actions' );
        $filters = $this->getPrivateProperty( 'filters' );

        $this->assertCount( 1, $actions );
        $this->assertCount( 1, $filters );
        $this->assertSame( 'action_cb', $actions[0]['callback'] );
        $this->assertSame( 'filter_cb', $filters[0]['callback'] );
    }

    // ====================================================================
    // HELPER
    // ====================================================================

    /**
     * Ambil private property dari Loader.
     */
    private function getPrivateProperty( string $propertyName ) {
        $reflection = new \ReflectionClass( Loader::class );
        $prop       = $reflection->getProperty( $propertyName );
        $prop->setAccessible( true );
        return $prop->getValue( $this->loader );
    }

    /**
     * Panggil private method add() via reflection.
     */
    private function invokeAdd( array $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $reflection = new \ReflectionClass( Loader::class );
        $method     = $reflection->getMethod( 'add' );
        $method->setAccessible( true );
        return $method->invokeArgs( $this->loader, [ $hooks, $hook, $component, $callback, $priority, $accepted_args ] );
    }
}
