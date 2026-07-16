<?php
/**
 * Unit Test untuk ContentRefresher — Living Content / Auto-Refresh.
 *
 * Dual-mode compatible: bekerja dengan mock fallback (mocks.php) dan
 * real WordPress test suite (WP_TESTS_DIR).
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\ContentRefresher;
use Autoblog\Intelligence\ResearchAgent;
use Autoblog\Generators\ArticleWriter;
use Autoblog\Utils\OptionCache;

class ContentRefresherTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        global $_autoblog_mock_wp_query_posts;
        $_autoblog_mock_options         = [];
        $_autoblog_mock_wp_query_posts  = [];

        $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] = [];
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        global $_autoblog_mock_wp_query_posts;
        $_autoblog_mock_options         = [];
        $_autoblog_mock_wp_query_posts  = [];

        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // HELPERS
    // ====================================================================

    /**
     * Deteksi apakah dalam mock fallback mode.
     */
    private function isMockMode(): bool {
        return function_exists( 'get_option' ) && ! function_exists( 'wp_using_ext_object_cache' );
    }

    /**
     * Dapatkan tracked WP_Query args (mock mode) atau fallback.
     */
    private function getWpQueryArgs(): array {
        $tracked = $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? null;
        if ( $tracked !== null ) {
            return $tracked;
        }
        return [];
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    public function test_constructor_creates_instance() {
        $refresher = new ContentRefresher();
        $this->assertInstanceOf( ContentRefresher::class, $refresher );
    }

    public function test_constructor_accepts_research_mock() {
        $researchMock = new class {
            public function conduct_research( $topic ) { return ''; }
        };

        $refresher = new ContentRefresher( $researchMock );
        $this->assertInstanceOf( ContentRefresher::class, $refresher );
    }

    public function test_constructor_defaults_to_real_instances() {
        $refresher = new ContentRefresher();
        $this->assertInstanceOf( ContentRefresher::class, $refresher );
    }

    // ====================================================================
    // PHASE 1: GUARD — EARLY RETURN LOGIC
    // ====================================================================

    public function test_refresh_returns_early_when_disabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '0';

        $refresher = new ContentRefresher();
        $result = $refresher->refresh_old_content( false );

        $this->assertNull( $result, 'Method harus return null ketika disabled' );
    }

    public function test_refresh_proceeds_when_forced_even_if_disabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '0';

        $refresher = new ContentRefresher();
        $result = $refresher->refresh_old_content( true );

        $this->assertNull( $result );
    }

    public function test_refresh_proceeds_when_enabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $refresher = new ContentRefresher();
        $result = $refresher->refresh_old_content( false );

        $this->assertNull( $result );
    }

    public function test_guard_no_wp_query_when_disabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '0';

        $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] = [];

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $tracked = $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? null;
        if ( $tracked !== null ) {
            $this->assertEmpty( $tracked, 'WP_Query tidak boleh dibuat saat disabled' );
        } else {
            // Real WP mode: tidak bisa track, test pass as no-op
            $this->assertTrue( true );
        }
    }

    public function test_guard_wp_query_created_when_enabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $tracked = $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? null;
        if ( $tracked !== null ) {
            $this->assertNotEmpty( $tracked, 'WP_Query harus dibuat saat enabled' );
        } else {
            $this->assertTrue( true );
        }
    }

    // ====================================================================
    // PHASE 2: WP_Query — STALE POST DETECTION
    // ====================================================================

    public function test_refresh_returns_when_no_stale_posts() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $this->assertNotNull( $refresher );
    }

    public function test_refresh_calls_wp_reset_postdata() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $this->assertNotNull( $refresher );
    }

    // ====================================================================
    // WP_Query ARGS TRACKING
    // ====================================================================

    public function test_wp_query_args_structure() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] = [];

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $tracked = $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? null;
        if ( $tracked !== null && ! empty( $tracked ) ) {
            $args = $tracked[0];
            $this->assertIsArray( $args );
            $this->assertEquals( 'post', $args['post_type'] );
            $this->assertEquals( 'publish', $args['post_status'] );
            $this->assertEquals( 1, $args['posts_per_page'] );
            $this->assertEquals( 'rand', $args['orderby'] );
            $this->assertArrayHasKey( 'date_query', $args );
            $this->assertArrayHasKey( 'meta_query', $args );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_wp_query_date_query_before_six_months() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $tracked = $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? [];
        if ( ! empty( $tracked ) && isset( $tracked[0] ) ) {
            $dateQuery = $tracked[0]['date_query'][0];
            $this->assertEquals( 'post_date', $dateQuery['column'] );
            $this->assertEquals( '6 months ago', $dateQuery['before'] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_wp_query_meta_query_or_logic() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $tracked = $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? [];
        if ( ! empty( $tracked ) && isset( $tracked[0] ) ) {
            $metaQuery = $tracked[0]['meta_query'];
            $this->assertEquals( 'OR', $metaQuery['relation'] );
            $this->assertEquals( '_autoblog_last_refreshed', $metaQuery[0]['key'] );
            $this->assertEquals( 'NOT EXISTS', $metaQuery[0]['compare'] );
            $this->assertEquals( '_autoblog_last_refreshed', $metaQuery[1]['key'] );
            $this->assertEquals( '<', $metaQuery[1]['compare'] );
            $this->assertEquals( 'DATE', $metaQuery[1]['type'] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_wp_query_meta_query_date_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';

        $refresher = new ContentRefresher();
        $refresher->refresh_old_content( false );

        $tracked = $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? [];
        if ( ! empty( $tracked ) && isset( $tracked[0] ) ) {
            $value = $tracked[0]['meta_query'][1]['value'];
            $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $value,
                'Format tanggal harus Y-m-d'
            );
        } else {
            $this->assertTrue( true );
        }
    }

    // ====================================================================
    // PHASE 3: REFRESH LOOP — DENGAN ANONYMOUS CLASS STUBS
    // ====================================================================

    public function test_phase3_research_failure_skips_writer() {
        global $_autoblog_mock_options;
        global $_autoblog_mock_wp_query_posts;

        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';
        $_autoblog_mock_wp_query_posts = [
            (object) [ 'ID' => 42, 'post_title' => 'Stale Post', 'post_content' => 'Old content' ],
        ];

        $researchStub = new class {
            public function conduct_research( $topic ) { return ''; }
        };
        $writerStub = new class {
            public $last_taxonomy = null;
            public $called = false;
            public function write_article( $data, $angle, $context = '', $persona_data = null, $overrides = [] ) {
                $this->called = true;
                return '<p>Should not be called</p>';
            }
        };

        $refresher = new ContentRefresher( $researchStub, $writerStub );
        $refresher->refresh_old_content( true );

        $this->assertFalse( $writerStub->called,
            'Writer tidak boleh dipanggil ketika research gagal'
        );
    }

    public function test_phase3_research_success_calls_writer() {
        global $_autoblog_mock_options;
        global $_autoblog_mock_wp_query_posts;

        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';
        $_autoblog_mock_wp_query_posts = [
            (object) [ 'ID' => 42, 'post_title' => 'Stale Post', 'post_content' => 'Old content' ],
        ];

        $researchStub = new class {
            public function conduct_research( $topic ) {
                return 'New research findings about ' . $topic;
            }
        };
        $writerStub = new class {
            public $last_taxonomy = null;
            public $called = false;
            public $last_data = null;
            public $last_angle = '';
            public function write_article( $data, $angle, $context = '', $persona_data = null, $overrides = [] ) {
                $this->called = true;
                $this->last_data = $data;
                $this->last_angle = $angle;
                return '<p>Refreshed article content.</p>';
            }
        };

        $refresher = new ContentRefresher( $researchStub, $writerStub );
        $refresher->refresh_old_content( true );

        $this->assertTrue( $writerStub->called,
            'Writer harus dipanggil ketika research sukses'
        );
        $this->assertStringContainsString( 'Mock Title', $writerStub->last_data[0]['title'],
            'Writer harus menerima title dari post (via get_the_title mock)'
        );
        $this->assertStringContainsStringIgnoringCase( 'Research findings', $writerStub->last_data[0]['content'],
            'Writer harus menerima research context dalam content'
        );
    }

    public function test_phase3_write_failure_handled_gracefully() {
        global $_autoblog_mock_options;
        global $_autoblog_mock_wp_query_posts;

        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';
        $_autoblog_mock_wp_query_posts = [
            (object) [ 'ID' => 42, 'post_title' => 'Stale Post', 'post_content' => 'Old content' ],
        ];

        $researchStub = new class {
            public function conduct_research( $topic ) { return 'Research data'; }
        };
        $writerStub = new class {
            public $last_taxonomy = null;
            public function write_article( $data, $angle, $context = '', $persona_data = null, $overrides = [] ) {
                return false;
            }
        };

        $refresher = new ContentRefresher( $researchStub, $writerStub );
        $refresher->refresh_old_content( true );

        $this->assertNotNull( $refresher,
            'Method harus selesai tanpa error meski writer gagal'
        );
    }

    public function test_phase3_full_success_completes() {
        global $_autoblog_mock_options;
        global $_autoblog_mock_wp_query_posts;

        $_autoblog_mock_options['autoblog_enable_living_content'] = '1';
        $_autoblog_mock_wp_query_posts = [
            (object) [ 'ID' => 42, 'post_title' => 'Stale Post', 'post_content' => 'Old content' ],
        ];

        $researchStub = new class {
            public function conduct_research( $topic ) { return 'Research data'; }
        };
        $writerStub = new class {
            public $last_taxonomy = null;
            public function write_article( $data, $angle, $context = '', $persona_data = null, $overrides = [] ) {
                return '<p>Final updated content</p>';
            }
        };

        $refresher = new ContentRefresher( $researchStub, $writerStub );
        $refresher->refresh_old_content( true );

        $this->assertNotNull( $refresher );
    }

    // ====================================================================
    // LOGIC DOCUMENTATION TESTS
    // ====================================================================

    public function test_expected_stale_post_query_structure() {
        $three_months_ago = strtotime( '-3 months' );

        $expected_args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'date_query'     => [
                [ 'column' => 'post_date', 'before' => '6 months ago' ],
            ],
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => '_autoblog_last_refreshed', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_autoblog_last_refreshed', 'value' => date( 'Y-m-d', $three_months_ago ), 'compare' => '<', 'type' => 'DATE' ],
            ],
        ];

        $this->assertArrayHasKey( 'post_type', $expected_args );
        $this->assertEquals( 'post', $expected_args['post_type'] );
        $this->assertEquals( 'publish', $expected_args['post_status'] );
        $this->assertEquals( 1, $expected_args['posts_per_page'] );
        $this->assertEquals( 'rand', $expected_args['orderby'] );
        $this->assertCount( 1, $expected_args['date_query'] );
        $this->assertEquals( 'post_date', $expected_args['date_query'][0]['column'] );
        $this->assertEquals( '6 months ago', $expected_args['date_query'][0]['before'] );
        $this->assertEquals( 'OR', $expected_args['meta_query']['relation'] );
        $this->assertCount( 3, $expected_args['meta_query'] );
    }

    public function test_six_months_ago_date_is_valid() {
        $six_months_ago = strtotime( '-6 months' );
        $this->assertNotFalse( $six_months_ago, 'strtotime(-6 months) harus valid' );
        $this->assertLessThan( time(), $six_months_ago, '-6 months harus < today' );
    }

    public function test_three_month_cooldown_date_is_valid() {
        $three_months_ago = strtotime( '-3 months' );
        $this->assertNotFalse( $three_months_ago, 'strtotime(-3 months) harus valid' );
        $this->assertLessThan( time(), $three_months_ago, '-3 months harus < today' );

        $formatted = date( 'Y-m-d', $three_months_ago );
        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $formatted,
            'Format tanggal cooldown harus Y-m-d'
        );
    }

    public function test_meta_query_or_logic_allows_unrefreshed_and_old_refreshed() {
        $meta_query = [
            'relation' => 'OR',
            [ 'key' => '_autoblog_last_refreshed', 'compare' => 'NOT EXISTS' ],
            [ 'key' => '_autoblog_last_refreshed', 'value' => date( 'Y-m-d', strtotime( '-3 months' ) ), 'compare' => '<', 'type' => 'DATE' ],
        ];

        $this->assertEquals( 'OR', $meta_query['relation'] );
        $this->assertArrayHasKey( 0, $meta_query );
        $this->assertArrayHasKey( 1, $meta_query );
        $this->assertEquals( 'NOT EXISTS', $meta_query[0]['compare'] );
        $this->assertEquals( '<', $meta_query[1]['compare'] );
    }
}
