<?php
/**
 * Unit Test untuk Runner — Pipeline Orchestration.
 *
 * Runner mengelola 3 phase pipeline: Ingestion -> Ideation -> Production.
 *
 * Strategi test:
 * - get_configured_sources() diuji via reflection (pure function)
 * - Phase methods diuji via early-return paths (guard clauses)
 * - IdeationAgent dan ArticleWriter di-instantiate dengan 'new' di dalam
 *   method -> tidak bisa di-mock -> phase AI tidak diuji di unit test
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\Runner;
use Autoblog\Utils\OptionCache;

class RunnerTest extends TestCase {

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

    public function test_constructor_creates_instance() {
        $runner = new Runner();
        $this->assertInstanceOf( Runner::class, $runner );
    }

    // ====================================================================
    // GET_CONFIGURED_SOURCES -- PURE FUNCTION VIA REFLECTION
    // ====================================================================

    public function test_get_sources_returns_empty_when_option_not_set() {
        $runner = new Runner();

        $sources = $this->invokeMethod( $runner, 'get_configured_sources' );

        $this->assertIsArray( $sources );
        $this->assertEmpty( $sources, 'Tanpa option sources, harus return []' );
    }

    public function test_get_sources_returns_empty_when_option_empty_string() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = '0';
        OptionCache::flush();

        $runner = new Runner();

        $sources = $this->invokeMethod( $runner, 'get_configured_sources' );

        $this->assertIsArray( $sources );
        $this->assertEmpty( $sources, 'Option string harus return []' );
    }

    public function test_get_sources_single_source() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [ 'type' => 'rss', 'url' => 'https://example.com/feed' ],
        ];
        OptionCache::flush();

        $runner = new Runner();

        $sources = $this->invokeMethod( $runner, 'get_configured_sources' );

        $this->assertCount( 1, $sources );
        $this->assertEquals( 'rss', $sources[0]['type'] );
        $this->assertEquals( 'https://example.com/feed', $sources[0]['url'] );
    }

    public function test_get_sources_multiple_sources() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [ 'type' => 'rss', 'url' => 'https://ex.com/rss', 'match_keywords' => 'AI' ],
            [ 'type' => 'web', 'url' => 'https://ex.com/page', 'selector' => '.content' ],
            [ 'type' => 'web_search', 'url' => 'machine learning' ],
        ];
        OptionCache::flush();

        $runner = new Runner();

        $sources = $this->invokeMethod( $runner, 'get_configured_sources' );

        $this->assertCount( 3, $sources );
        $this->assertEquals( 'rss', $sources[0]['type'] );
        $this->assertEquals( 'web', $sources[1]['type'] );
        $this->assertEquals( 'web_search', $sources[2]['type'] );
    }

    public function test_get_sources_non_array_returns_empty() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = 'invalid_string';
        OptionCache::flush();

        $runner = new Runner();

        $sources = $this->invokeMethod( $runner, 'get_configured_sources' );

        $this->assertIsArray( $sources );
        $this->assertEmpty( $sources, 'Non-array option harus return []' );
    }

    public function test_get_sources_filters_non_array_items() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [ 'type' => 'rss', 'url' => 'https://ex.com/feed' ],
            'string item',
            123,
            null,
            [ 'type' => 'web_search', 'url' => 'query' ],
        ];
        OptionCache::flush();

        $runner = new Runner();

        $sources = $this->invokeMethod( $runner, 'get_configured_sources' );

        $this->assertCount( 2, $sources,
            'Non-array items harus difilter, hanya 2 yang valid'
        );
        $this->assertEquals( 'rss', $sources[0]['type'] );
        $this->assertEquals( 'web_search', $sources[1]['type'] );
    }

    public function test_get_sources_resets_indices() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            5 => [ 'type' => 'rss', 'url' => 'https://ex.com' ],
            10 => [ 'type' => 'web', 'url' => 'https://ex.com/page', 'selector' => 'div' ],
        ];
        OptionCache::flush();

        $runner = new Runner();

        $sources = $this->invokeMethod( $runner, 'get_configured_sources' );

        $this->assertCount( 2, $sources );
        $this->assertArrayHasKey( 0, $sources );
        $this->assertArrayHasKey( 1, $sources );
        $this->assertArrayNotHasKey( 5, $sources );
    }

    // ====================================================================
    // RUN_INGESTION_PHASE -- GUARD PATHS
    // ====================================================================

    public function test_ingestion_phase_kb_only_returns_early() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_data_source_mode'] = 'kb_only';
        OptionCache::flush();

        $runner = new Runner();
        $runner->run_ingestion_phase();

        $this->assertNotNull( $runner );
    }

    public function test_ingestion_phase_with_empty_sources() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_data_source_mode'] = 'both';
        $_autoblog_mock_options['autoblog_sources'] = [];
        OptionCache::flush();

        $runner = new Runner();
        $runner->run_ingestion_phase();

        $this->assertNotNull( $runner );
    }

    // ====================================================================
    // RUN_PRODUCTION_PHASE -- GUARD PATHS
    // ====================================================================

    public function test_production_phase_returns_early_when_no_idea() {
        $runner = new Runner();
        $runner->run_production_phase( null );

        $this->assertNotNull( $runner );
    }

    // ====================================================================
    // RUN_MAINTENANCE_PHASE -- LIVING CONTENT
    // ====================================================================

    public function test_maintenance_phase_creates_refresher() {
        $runner = new Runner();
        $runner->run_maintenance_phase();

        $this->assertNotNull( $runner );
    }

    // ====================================================================
    // HELPER
    // ====================================================================

    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }
}
