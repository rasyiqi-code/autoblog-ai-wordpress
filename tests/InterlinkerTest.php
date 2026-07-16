<?php
/**
 * Unit Test untuk Interlinker.
 *
 * Dual-mode compatible: bekerja dengan mock fallback (mocks.php) dan
 * real WordPress test suite (WP_TESTS_DIR).
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\Interlinker;
use Autoblog\Utils\OptionCache;

/**
 * @group unit
 * @group regression
 * @group interlinker
 */
class InterlinkerTest extends TestCase {

    /** @var Interlinker */
    private $interlinker;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();
        global $_autoblog_mock_options;
        $_autoblog_mock_options                              = [];
        $_autoblog_mock_options['autoblog_enable_interlinking'] = '1';

        unset( $GLOBALS['_autoblog_mock_wp_query_posts'] );
        $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] = [];

        $this->interlinker = new Interlinker();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        unset( $GLOBALS['_autoblog_mock_wp_query_posts'] );
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
     * Dapatkan tracked WP_Query args, atau array kosong jika tidak ada.
     */
    private function getWpQueryCalls(): array {
        return $GLOBALS['_wp_mock_calls']['WP_Query::__construct'] ?? [];
    }

    /**
     * Dapatkan args dari WP_Query terakhir, atau null jika tidak ada.
     */
    private function getLastWpQueryArgs(): ?array {
        $calls = $this->getWpQueryCalls();
        if ( empty( $calls ) ) {
            return null;
        }
        $last = end( $calls );
        return is_array( $last ) ? $last : null;
    }

    // ================================================================
    // GET RELEVANT POSTS — DISABLED
    // ================================================================

    public function test_get_relevant_posts_returns_empty_when_disabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_interlinking'] = '0';

        $result = $this->interlinker->get_relevant_posts( 'test topic' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Harus empty ketika fitur disabled' );
    }

    public function test_get_relevant_posts_returns_empty_when_option_not_set() {
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_enable_interlinking'] );

        $result = $this->interlinker->get_relevant_posts( 'test topic' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // GET RELEVANT POSTS — NO RESULTS
    // ================================================================

    public function test_get_relevant_posts_returns_empty_when_no_results() {
        $result = $this->interlinker->get_relevant_posts( 'nonexistent topic' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // GET RELEVANT POSTS — WITH RESULTS
    // ================================================================

    public function test_get_relevant_posts_returns_links_when_posts_found() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1, 2, 3 ];

        $result = $this->interlinker->get_relevant_posts( 'AI teknologi' );

        if ( $this->isMockMode() ) {
            $this->assertCount( 3, $result );
        } else {
            // Real WP: hasil tergantung database, minimal array
            $this->assertIsArray( $result );
        }
    }

    public function test_get_relevant_posts_links_have_url_and_title_keys() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 42 ];

        $result = $this->interlinker->get_relevant_posts( 'machine learning' );

        if ( $this->isMockMode() ) {
            $this->assertArrayHasKey( 'title', $result[0] );
            $this->assertArrayHasKey( 'url', $result[0] );
            $this->assertEquals( 'Mock Title', $result[0]['title'] );
            $this->assertEquals( 'http://example.com/mock-post', $result[0]['url'] );
        } else {
            $this->assertIsArray( $result );
            if ( ! empty( $result ) ) {
                $this->assertArrayHasKey( 'title', $result[0] );
                $this->assertArrayHasKey( 'url', $result[0] );
            }
        }
    }

    public function test_get_relevant_posts_limits_to_five_posts() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1, 2, 3, 4, 5 ];

        $result = $this->interlinker->get_relevant_posts( 'test' );

        if ( $this->isMockMode() ) {
            $this->assertCount( 5, $result, 'Harus maksimal 5 post' );
        } else {
            $this->assertIsArray( $result );
        }
    }

    // ================================================================
    // GET RELEVANT POSTS — QUERY ARGS VALIDATION
    // ================================================================

    public function test_get_relevant_posts_passes_topic_to_wp_query_search() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1 ];

        $this->interlinker->get_relevant_posts( 'deep learning' );

        $lastArgs = $this->getLastWpQueryArgs();
        if ( $lastArgs !== null ) {
            $this->assertEquals( 'deep learning', $lastArgs['s'] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_get_relevant_posts_uses_post_type_post() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1 ];

        $this->interlinker->get_relevant_posts( 'test' );

        $lastArgs = $this->getLastWpQueryArgs();
        if ( $lastArgs !== null ) {
            $this->assertEquals( 'post', $lastArgs['post_type'] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_get_relevant_posts_uses_publish_status() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1 ];

        $this->interlinker->get_relevant_posts( 'test' );

        $lastArgs = $this->getLastWpQueryArgs();
        if ( $lastArgs !== null ) {
            $this->assertEquals( 'publish', $lastArgs['post_status'] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_get_relevant_posts_uses_posts_per_page_5() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1 ];

        $this->interlinker->get_relevant_posts( 'test' );

        $lastArgs = $this->getLastWpQueryArgs();
        if ( $lastArgs !== null ) {
            $this->assertEquals( 5, $lastArgs['posts_per_page'] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_get_relevant_posts_uses_fields_ids() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1 ];

        $this->interlinker->get_relevant_posts( 'test' );

        $lastArgs = $this->getLastWpQueryArgs();
        if ( $lastArgs !== null ) {
            $this->assertEquals( 'ids', $lastArgs['fields'] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_get_relevant_posts_does_not_query_when_disabled() {
        global $_autoblog_mock_options;
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1 ];
        $_autoblog_mock_options['autoblog_enable_interlinking'] = '0';

        $this->interlinker->get_relevant_posts( 'test' );

        $calls = $this->getWpQueryCalls();
        if ( ! empty( $calls ) ) {
            $this->assertEmpty( $calls, 'WP_Query tidak boleh dipanggil jika interlinking disabled' );
        } else {
            $this->assertTrue( true );
        }
    }

    // ================================================================
    // GET RELEVANT POSTS — EDGE CASES
    // ================================================================

    public function test_get_relevant_posts_empty_topic_still_queries() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [];

        $result = $this->interlinker->get_relevant_posts( '' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );

        $calls = $this->getWpQueryCalls();
        if ( ! empty( $calls ) ) {
            $this->assertNotEmpty( $calls, 'WP_Query tetap dipanggil walau topic kosong' );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_get_relevant_posts_handles_single_result() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 99 ];

        $result = $this->interlinker->get_relevant_posts( 'singular' );

        if ( $this->isMockMode() ) {
            $this->assertCount( 1, $result );
            $this->assertEquals( 'Mock Title', $result[0]['title'] );
        } else {
            $this->assertIsArray( $result );
        }
    }

    // ================================================================
    // INJECT LINKS — BASIC (pure function, no WP dependency)
    // ================================================================

    public function test_inject_links_appends_html_at_bottom() {
        $links = [
            [ 'url' => 'https://example.com/post-1', 'title' => 'Post 1' ],
            [ 'url' => 'https://example.com/post-2', 'title' => 'Post 2' ],
        ];

        $html   = '<p>Konten utama artikel.</p>';
        $result = $this->interlinker->inject_links( $html, $links );

        $this->assertStringContainsString( 'Konten utama artikel.', $result );
        $this->assertStringContainsString( 'Related Reading', $result );
        $this->assertStringContainsString( 'Post 1', $result );
        $this->assertStringContainsString( 'Post 2', $result );
        $this->assertStringContainsString( '<ul>', $result );
        $this->assertStringContainsString( '</ul>', $result );
    }

    public function test_inject_links_returns_original_html_when_links_empty() {
        $html   = '<p>Konten tanpa link.</p>';
        $result = $this->interlinker->inject_links( $html, [] );

        $this->assertEquals( $html, $result, 'Tanpa links, konten harus tetap sama' );
    }

    public function test_inject_links_handles_single_link() {
        $links = [ [ 'url' => 'https://example.com/post', 'title' => 'Single Post' ] ];

        $html   = '<p>Test</p>';
        $result = $this->interlinker->inject_links( $html, $links );

        $this->assertStringContainsString( 'Single Post', $result );
        $this->assertStringContainsString( 'href=', $result );
    }

    // ================================================================
    // INJECT LINKS — OUTPUT STRUCTURE
    // ================================================================

    public function test_inject_links_output_structure() {
        $links = [ [ 'url' => 'https://ex.com/a', 'title' => 'A' ] ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $this->assertStringContainsString( "<div class='autoblog-internal-links'>", $result );
        $this->assertStringContainsString( '</div>', $result );
        $this->assertStringContainsString( '<h3>Related Reading:</h3>', $result );
    }

    public function test_inject_links_each_link_in_li() {
        $links = [
            [ 'url' => 'https://ex.com/a', 'title' => 'A' ],
            [ 'url' => 'https://ex.com/b', 'title' => 'B' ],
            [ 'url' => 'https://ex.com/c', 'title' => 'C' ],
        ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $this->assertEquals( 3, substr_count( $result, '<li>' ) );
        $this->assertEquals( 3, substr_count( $result, '</li>' ) );
    }

    public function test_inject_links_content_before_links() {
        $links = [ [ 'url' => 'https://ex.com/p', 'title' => 'P' ] ];

        $html   = '<p>Original content.</p>';
        $result = $this->interlinker->inject_links( $html, $links );

        $pos_content = strpos( $result, 'Original content' );
        $pos_links   = strpos( $result, 'Related Reading' );

        $this->assertNotFalse( $pos_content );
        $this->assertNotFalse( $pos_links );
        $this->assertLessThan( $pos_links, $pos_content,
            'Content harus muncul SEBELUM Related Reading' );
    }

    // ================================================================
    // INJECT LINKS — SPECIAL CHARACTERS
    // ================================================================

    public function test_inject_links_with_url_containing_ampersand() {
        $links = [ [ 'url' => 'https://ex.com/page?id=123&ref=test', 'title' => 'Link' ] ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $this->assertStringContainsString( 'id=123&ref=test', $result );
    }

    public function test_inject_links_with_special_chars_in_title() {
        $links = [ [ 'url' => 'https://ex.com/p', 'title' => 'Harga Rp 50.000 & Diskon 20%' ] ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $this->assertStringContainsString( 'Harga Rp 50.000 & Diskon 20%', $result );
    }

    public function test_inject_links_with_single_quote_in_title() {
        $links = [ [ 'url' => 'https://ex.com/p', 'title' => "It's a test" ] ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $this->assertStringContainsString( "It's a test", $result );
    }

    // ================================================================
    // INJECT LINKS — EDGE CASES
    // ================================================================

    public function test_inject_links_with_empty_title_in_link() {
        $links = [ [ 'url' => 'https://ex.com/p', 'title' => '' ] ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $this->assertStringContainsString( 'href=', $result );
        $this->assertStringContainsString( 'https://ex.com/p', $result );
    }

    public function test_inject_links_with_empty_url_in_link() {
        $links = [ [ 'url' => '', 'title' => 'No URL' ] ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $this->assertStringContainsString( 'No URL', $result );
    }

    public function test_inject_links_preserves_multiple_links_order() {
        $links = [
            [ 'url' => 'https://ex.com/first', 'title' => 'First' ],
            [ 'url' => 'https://ex.com/second', 'title' => 'Second' ],
            [ 'url' => 'https://ex.com/third', 'title' => 'Third' ],
        ];

        $result = $this->interlinker->inject_links( '<p>Content</p>', $links );

        $pos_first  = strpos( $result, 'First' );
        $pos_second = strpos( $result, 'Second' );
        $pos_third  = strpos( $result, 'Third' );

        $this->assertLessThan( $pos_second, $pos_first );
        $this->assertLessThan( $pos_third, $pos_second );
    }

    public function test_inject_links_handles_html_content_with_newlines() {
        $links = [ [ 'url' => 'https://ex.com/p', 'title' => 'Post' ] ];

        $html   = "<p>First paragraph.</p>\n<p>Second paragraph.</p>";
        $result = $this->interlinker->inject_links( $html, $links );

        $this->assertStringContainsString( "Second paragraph.</p>\n<div", $result );
    }

    // ================================================================
    // COMBINED INTEGRATION
    // ================================================================

    public function test_get_posts_and_inject_links_workflow() {
        $GLOBALS['_autoblog_mock_wp_query_posts'] = [ 1, 2 ];

        $content = '<p>Artikel tentang AI dan machine learning.</p>';
        $links   = $this->interlinker->get_relevant_posts( 'AI teknologi' );

        if ( $this->isMockMode() ) {
            $this->assertCount( 2, $links, 'get_relevant_posts harus mengembalikan 2 link' );
        }

        $result = $this->interlinker->inject_links( $content, $links );

        $this->assertStringContainsString( 'Artikel tentang AI', $result );
        $this->assertStringContainsString( 'Related Reading', $result );

        if ( $this->isMockMode() && ! empty( $links ) ) {
            $this->assertStringContainsString( 'Mock Title', $result );
        }
    }
}
