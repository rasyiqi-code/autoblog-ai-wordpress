<?php
/**
 * Unit Test untuk Runner::get_configured_sources().
 *
 * Memverifikasi bahwa:
 * 1. Method mengembalikan array kosong jika opsi tidak di-set.
 * 2. Method memfilter elemen non-array dari daftar sumber.
 * 3. Method mengembalikan sumber yang valid dengan benar.
 *
 * Mencegah regresi yang terkait dengan pipeline ingestion
 * dan pengelolaan daftar sumber konten.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\Runner;
use Autoblog\Utils\OptionCache;

/**
 * Unit Test untuk Runner::get_configured_sources().
 *
 * @group unit
 * @group regression
 */
class RunnerSourcesTest extends TestCase {

    /** @var Runner */
    private $runner;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_sources'] );
        $this->runner = new Runner();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_sources'] );
        OptionCache::flush();
        parent::tearDown();
    }

    // ================================================================
    // TEST 1: Mengembalikan array kosong jika opsi tidak di-set
    // ================================================================

    public function test_returns_empty_array_when_no_sources_configured() {
        $result = $this->invokeMethod( $this->runner, 'get_configured_sources' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // TEST 2: Mengembalikan array sources yang valid
    // ================================================================

    public function test_returns_configured_sources() {
        $sources = [
            [ 'type' => 'rss',         'url' => 'https://example.com/feed' ],
            [ 'type' => 'web_search',   'url' => 'AI technology' ],
        ];

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = $sources;

        $result = $this->invokeMethod( $this->runner, 'get_configured_sources' );

        $this->assertIsArray( $result );
        $this->assertCount( 2, $result );
        $this->assertEquals( 'rss', $result[0]['type'] );
        $this->assertEquals( 'web_search', $result[1]['type'] );
    }

    // ================================================================
    // TEST 3: Memfilter elemen non-array (misal string/null)
    // ================================================================

    public function test_filters_out_non_array_elements() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [ 'type' => 'rss', 'url' => 'https://example.com/feed' ],
            'this is a string, not an array',  // Harus difilter
            null,                               // Harus difilter
            123,                                // Harus difilter
            [ 'type' => 'web', 'url' => 'https://other.com' ],
        ];

        $result = $this->invokeMethod( $this->runner, 'get_configured_sources' );

        $this->assertIsArray( $result );
        $this->assertCount( 2, $result, 'Hanya 2 sumber valid yang harus dipertahankan' );
        $this->assertEquals( 'rss', $result[0]['type'] );
        $this->assertEquals( 'web', $result[1]['type'] );
    }

    // ================================================================
    // TEST 4: Kembalikan array kosong jika opsi adalah string (bukan array)
    // ================================================================

    public function test_returns_empty_array_when_option_is_string() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = 'not_an_array';

        $result = $this->invokeMethod( $this->runner, 'get_configured_sources' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // TEST 5: Kembalikan array kosong jika opsi adalah null
    // ================================================================

    public function test_returns_empty_array_when_option_is_null() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = null;

        $result = $this->invokeMethod( $this->runner, 'get_configured_sources' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // TEST 6: Sumber dengan key 'type' yang tidak dikenal tetap dipertahankan
    // ================================================================

    public function test_unknown_source_type_is_preserved() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [ 'type' => 'custom_type', 'url' => 'https://example.com' ],
        ];

        $result = $this->invokeMethod( $this->runner, 'get_configured_sources' );

        $this->assertCount( 1, $result );
        $this->assertEquals( 'custom_type', $result[0]['type'] );
    }

    // ================================================================
    // HELPER: Invoke private method via Reflection
    // ================================================================

    private function invokeMethod( &$object, $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }
}
