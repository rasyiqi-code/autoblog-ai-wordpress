<?php
/**
 * Unit Test untuk Autoblog\Sources\Drivers\DuckDuckGoDriver trait.
 *
 * DuckDuckGoDriver adalah trait yang mengambil hasil pencarian dari
 * halaman HTML DuckDuckGo (gratis, tanpa API key).
 *
 * Strategi test:
 * - Test harness class (DuckDuckGoTestHarness) yang menggunakan trait
 * - ddg_fetch_html menggunakan WP HTTP API (mocked di bootstrap) +
 *   Guzzle fallback (mock client di-inject via mock_client property)
 * - ddg_parse_html diuji dengan mock HTML via reflection
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      drivers
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

// ========================================================================
// TEST HARNESS
// ========================================================================

/**
 * Test harness untuk DuckDuckGoDriver.
 *
 * Menyediakan property ($query) dan stub method (passes_filters,
 * fetch_full_content) yang dibutuhkan trait.
 */
class DuckDuckGoTestHarness {
    use \Autoblog\Sources\Drivers\DuckDuckGoDriver;

    /** @var string */
    public $query = 'test query';

    /** @var int Counter untuk fetch_full_content calls */
    public $fetch_call_count = 0;

    /** @var \GuzzleHttp\Client|null Mock Guzzle Client */
    public $mock_client = null;

    /** @var bool Kontrol return value passes_filters */
    public $passes_filters_result = true;

    /** @var string|null Kontrol return value fetch_full_content (null = default) */
    public $fetch_full_content_override = null;

    /**
     * Stub: filter — bisa di-override via $passes_filters_result.
     */
    private function passes_filters( $text ) {
        return $this->passes_filters_result;
    }

    /**
     * Stub: return content dari URL atau override.
     */
    private function fetch_full_content( $url ) {
        $this->fetch_call_count++;
        if ( $this->fetch_full_content_override !== null ) {
            return $this->fetch_full_content_override;
        }
        return 'Scraped content from ' . $url;
    }

    /**
     * Stub: get_http_client() — jika mock_client diset, return itu.
     *
     * @param array $config
     * @return \GuzzleHttp\Client
     */
    private function get_http_client( $config = [] ) {
        if ( $this->mock_client !== null ) {
            return $this->mock_client;
        }
        return new \GuzzleHttp\Client( $config );
    }
}

// ========================================================================
// TEST CLASS
// ========================================================================

class DuckDuckGoDriverTest extends TestCase {

    /** @var DuckDuckGoTestHarness */
    private $harness;

    protected function setUp(): void {
        parent::setUp();
        $this->harness = new DuckDuckGoTestHarness();

        // Reset globals
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;
        global $_autoblog_mock_is_wp_error;
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;
        $_autoblog_mock_is_wp_error     = null;
    }

    protected function tearDown(): void {
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;
        global $_autoblog_mock_is_wp_error;
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;
        $_autoblog_mock_is_wp_error     = null;

        parent::tearDown();
    }

    // ====================================================================
    // HELPER: Buat mock Guzzle Client
    // ====================================================================

    /**
     * Buat mock Guzzle Client dengan MockHandler.
     */
    private function createMockClient( array $responses ): Client {
        $mock = new MockHandler( $responses );
        $handlerStack = HandlerStack::create( $mock );
        return new Client( [ 'handler' => $handlerStack ] );
    }

    // ====================================================================
    // FETCH_DUCKDUCKGO_FREE — HTTP / ERROR HANDLING
    // ====================================================================

    /**
     * Test bahwa fetch_duckduckgo_free() return [] ketika fetch HTML gagal
     * (kedua metode: WP HTTP API dan Guzzle).
     */
    public function test_fetch_returns_empty_when_html_fetch_fails() {
        $result = $this->invokeMethod( $this->harness, 'fetch_duckduckgo_free' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'fetch_duckduckgo_free harus return [] ketika HTTP fetch gagal'
        );
    }

    /**
     * Test fetch_duckduckgo_free — WP HTTP API sukses → parsing HTML → items.
     */
    public function test_fetch_with_wp_http_success() {
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;

        $html = '<html><body><div class="web-result">
            <a class="result__a" href="https://ex.com/a">Article A</a>
            <a class="result__snippet">Snippet A</a>
        </div></body></html>';

        $_autoblog_mock_remote_body     = $html;
        $_autoblog_mock_remote_response = [ 'body' => $html ];

        $result = $this->invokeMethod( $this->harness, 'fetch_duckduckgo_free' );

        $this->assertNotEmpty( $result, 'WP HTTP sukses harus menghasilkan items' );
        $this->assertSame( 'Article A', $result[0]['title'] );
        $this->assertSame( 'duckduckgo_free', $result[0]['source_type'] );
    }

    /**
     * Test fetch_duckduckgo_free — WP HTTP API error → Guzzle fallback sukses.
     */
    public function test_fetch_with_wp_error_then_guzzle_success() {
        global $_autoblog_mock_is_wp_error;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_is_wp_error     = true;
        $_autoblog_mock_remote_response = new \WP_Error( 'http_error', 'Connection failed' );

        $html = '<html><body><div class="web-result">
            <a class="result__a" href="https://ex.com/b">Article B</a>
            <a class="result__snippet">Snippet B</a>
        </div></body></html>';

        $this->harness->mock_client = $this->createMockClient( [
            new Response( 200, [], $html ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'fetch_duckduckgo_free' );

        $this->assertNotEmpty( $result, 'WP Error → Guzzle sukses harus menghasilkan items' );
        $this->assertSame( 'Article B', $result[0]['title'] );
    }

    /**
     * Test fetch_duckduckgo_free — semua metode gagal → return [].
     */
    public function test_fetch_all_methods_fail_returns_empty() {
        // WP HTTP API: empty response (default)
        // Guzzle: no mock client → connection error
        $result = $this->invokeMethod( $this->harness, 'fetch_duckduckgo_free' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Semua metode gagal harus return []' );
    }

    // ====================================================================
    // DDG_FETCH_HTML
    // ====================================================================

    /**
     * Test ddg_fetch_html — WP HTTP API sukses return body.
     */
    public function test_ddg_fetch_html_wp_http_success() {
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_remote_body     = '<html>Success</html>';
        $_autoblog_mock_remote_response = [ 'body' => '<html>Success</html>' ];

        $result = $this->invokeMethod(
            $this->harness,
            'ddg_fetch_html',
            [ 'https://html.duckduckgo.com/html/?q=test', 'Mozilla/5.0 Test' ]
        );

        $this->assertSame( '<html>Success</html>', $result );
    }

    /**
     * Test ddg_fetch_html — WP HTTP API return WP_Error → Guzzle fallback sukses.
     */
    public function test_ddg_fetch_html_wp_error_then_guzzle_success() {
        global $_autoblog_mock_is_wp_error;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_is_wp_error     = true;
        $_autoblog_mock_remote_response = new \WP_Error( 'http_error', 'Connection failed' );

        $this->harness->mock_client = $this->createMockClient( [
            new Response( 200, [], '<html>Guzzle Success</html>' ),
        ] );

        $result = $this->invokeMethod(
            $this->harness,
            'ddg_fetch_html',
            [ 'https://html.duckduckgo.com/html/?q=test', 'Mozilla/5.0 Test' ]
        );

        $this->assertSame( '<html>Guzzle Success</html>', $result );
    }

    /**
     * Test ddg_fetch_html — WP HTTP API empty body → Guzzle fallback sukses.
     */
    public function test_ddg_fetch_html_wp_empty_body_then_guzzle_success() {
        // Default globals: wp_remote_get returns [], body = ''
        // WP HTTP API: code=200, body='' → !empty(body) false → fall through

        $this->harness->mock_client = $this->createMockClient( [
            new Response( 200, [], '<html>Guzzle Body</html>' ),
        ] );

        $result = $this->invokeMethod(
            $this->harness,
            'ddg_fetch_html',
            [ 'https://html.duckduckgo.com/html/?q=test', 'Mozilla/5.0 Test' ]
        );

        $this->assertSame( '<html>Guzzle Body</html>', $result,
            'WP body kosong → Guzzle sukses harus return body Guzzle'
        );
    }

    /**
     * Test ddg_fetch_html — WP HTTP API empty body → Guzzle gagal → return false.
     */
    public function test_ddg_fetch_html_wp_empty_then_guzzle_fails() {
        // Default globals: both methods fail
        $result = $this->invokeMethod(
            $this->harness,
            'ddg_fetch_html',
            [ 'https://html.duckduckgo.com/html/?q=test', 'Mozilla/5.0 Test' ]
        );

        $this->assertFalse( $result,
            'ddg_fetch_html harus return false ketika kedua metode gagal'
        );
    }

    /**
     * Test ddg_fetch_html — Guzzle throws exception → return false.
     */
    public function test_ddg_fetch_html_guzzle_exception_returns_false() {
        // WP HTTP API empty (default) → Guzzle fallback → connection refused (no mock)
        // Tanpa mock_client, Guzzle coba koneksi nyata → exception
        $result = $this->invokeMethod(
            $this->harness,
            'ddg_fetch_html',
            [ 'https://html.duckduckgo.com/html/?q=test', 'Mozilla/5.0 Test' ]
        );

        $this->assertFalse( $result,
            'Guzzle exception harus return false'
        );
    }



    // ====================================================================
    // DDG_PARSE_HTML — PRIMARY SELECTOR (web-result)
    // ====================================================================

    /**
     * Test ddg_parse_html dengan HTML kosong.
     */
    public function test_parse_html_empty() {
        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ '<html></html>' ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'HTML tanpa result node harus return []' );
    }

    /**
     * Test ddg_parse_html dengan HTML tanpa node hasil.
     */
    public function test_parse_html_no_results() {
        $html = '<html><body><div class="other">No results here</div></body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'HTML tanpa result node harus return []' );
    }

    /**
     * Test ddg_parse_html dengan format web-result (selector utama).
     */
    public function test_parse_html_web_result_format() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://example.com/artikel1">Judul Artikel 1</a>
                <a class="result__snippet">Deskripsi singkat artikel 1.</a>
            </div>
            <div class="web-result">
                <a class="result__a" href="https://example.com/artikel2">Judul Artikel 2</a>
                <a class="result__snippet">Deskripsi artikel 2.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 2, $result, 'Harus mengekstrak 2 hasil' );
        $this->assertSame( 'Judul Artikel 1', $result[0]['title'] );
        $this->assertSame( 'https://example.com/artikel1', $result[0]['link'] );
        $this->assertSame( 'Judul Artikel 2', $result[1]['title'] );
    }

    /**
     * Test ddg_parse_html — primary selector web-result dengan banyak item.
     */
    public function test_parse_html_web_result_multiple_items() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/1">First</a>
                <a class="result__snippet">First snippet</a>
            </div>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/2">Second</a>
                <a class="result__snippet">Second snippet</a>
            </div>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/3">Third</a>
                <a class="result__snippet">Third snippet</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 3, $result );
        $this->assertSame( 'First', $result[0]['title'] );
        $this->assertSame( 'Second', $result[1]['title'] );
        $this->assertSame( 'Third', $result[2]['title'] );
    }

    // ====================================================================
    // DDG_PARSE_HTML — FALLBACK SELECTOR (result, exclude result--more)
    // ====================================================================

    /**
     * Test ddg_parse_html dengan format result (fallback selector).
     */
    public function test_parse_html_result_fallback_format() {
        $html = '<html><body>
            <div class="result">
                <a class="result__a" href="https://blog.com/post">Blog Post Title</a>
                <a class="result__snippet">Blog snippet here.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Blog Post Title', $result[0]['title'] );
        $this->assertSame( 'https://blog.com/post', $result[0]['link'] );
    }

    /**
     * Test ddg_parse_html — result--more class harus di-exclude dari fallback.
     *
     * Gunakan HTML tanpa web-result agar fallback selector terpakai.
     * XPath: contains(@class, 'result') and not(contains(@class, 'result--more'))
     */
    public function test_parse_html_excludes_result_more() {
        $html = '<html><body>
            <div class="result result--more">
                <a class="result__a" href="https://ex.com/more">More Link</a>
                <a class="result__snippet">More snippet</a>
            </div>
            <div class="result">
                <a class="result__a" href="https://ex.com/real">Real Result</a>
                <a class="result__snippet">Real snippet</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result, 'result--more harus di-exclude, hanya 1 hasil' );
        $this->assertSame( 'Real Result', $result[0]['title'] );
    }

    /**
     * Test ddg_parse_html — mixed web-result and result (primary menang).
     */
    public function test_parse_html_primary_over_fallback() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/primary">From Primary</a>
                <a class="result__snippet">Primary snippet</a>
            </div>
            <div class="result">
                <a class="result__a" href="https://ex.com/fallback">From Fallback</a>
                <a class="result__snippet">Fallback snippet</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        // Primary selector works, only web-result items are parsed
        $this->assertCount( 1, $result, 'Primary selector lebih diutamakan dari fallback' );
        $this->assertSame( 'From Primary', $result[0]['title'] );
    }

    // ====================================================================
    // DDG_PARSE_HTML — TITLE SELECTOR CHAIN
    // ====================================================================

    /**
     * Test ddg_parse_html — title via result-link selector (2nd in chain).
     */
    public function test_parse_html_title_via_result_link() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result-link" href="https://ex.com/link">Linked Article</a>
                <a class="result__snippet">Article snippet</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Linked Article', $result[0]['title'] );
    }

    /**
     * Test ddg_parse_html — title via h2/a selector (3rd in chain).
     */
    public function test_parse_html_title_via_h2_anchor() {
        $html = '<html><body>
            <div class="web-result">
                <h2><a href="https://site.com/article">Judul dalam H2</a></h2>
                <a class="result__snippet">Snippet dari H2 result.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Judul dalam H2', $result[0]['title'] );
    }

    /**
     * Test ddg_parse_html — semua title selectors gagal → item dilewati.
     */
    public function test_parse_html_skips_item_without_title() {
        $html = '<html><body>
            <div class="web-result">
                <span>No anchor here</span>
                <a class="result__snippet">Snippet without title.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertEmpty( $result, 'Item tanpa title node harus dilewati' );
    }

    /**
     * Test ddg_parse_html — item dengan semua 3 title selectors,
     * memverifikasi prioritas (result__a > result-link > h2/a).
     */
    public function test_parse_html_title_selector_priority() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/a">From Result__A</a>
                <a class="result-link" href="https://ex.com/link">From Result Link</a>
                <h2><a href="https://ex.com/h2">From H2</a></h2>
                <a class="result__snippet">Multiple selectors test</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'From Result__A', $result[0]['title'],
            'result__a selector harus diprioritaskan'
        );
    }

    // ====================================================================
    // DDG_PARSE_HTML — SNIPPET SELECTORS
    // ====================================================================

    /**
     * Test ddg_parse_html — snippet dari a.result__snippet.
     */
    public function test_parse_html_snippet_from_anchor() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://example.com/page">Title</a>
                <a class="result__snippet">Snippet from anchor element.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Snippet from anchor element.', $result[0]['description'] );
    }

    /**
     * Test ddg_parse_html — snippet dari div.result__snippet (fallback).
     */
    public function test_parse_html_snippet_from_div() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://example.com/page">Title</a>
                <div class="result__snippet">Snippet from div element.</div>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Snippet from div element.', $result[0]['description'] );
    }

    /**
     * Test ddg_parse_html — tanpa snippet node → description kosong.
     */
    public function test_parse_html_without_snippet() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/no-snippet">No Snippet</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( '', $result[0]['description'], 'Tanpa snippet, description harus kosong' );
    }

    // ====================================================================
    // DDG_PARSE_HTML — UDDG URL EXTRACTION
    // ====================================================================

    /**
     * Test ddg_parse_html mengekstrak URL asli dari parameter uddg.
     */
    public function test_parse_html_extracts_uddg_url() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Fexample.com%2Fpage%3Ffoo%3Dbar">Judul dengan UDDG</a>
                <a class="result__snippet">Snippet.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'https://example.com/page?foo=bar', $result[0]['link'],
            'URL uddg harus di-decode menjadi URL asli'
        );
    }

    /**
     * Test ddg_parse_html — URL tanpa uddg digunakan langsung.
     */
    public function test_parse_html_url_without_uddg() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://langsung.com/artikel">Langsung</a>
                <a class="result__snippet">Snippet</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'https://langsung.com/artikel', $result[0]['link'],
            'URL tanpa uddg harus digunakan langsung'
        );
    }

    /**
     * Test ddg_parse_html — uddg dengan path kompleks.
     */
    public function test_parse_html_uddg_with_complex_path() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Fblog.example.com%2F2026%2F07%2Farticle%3Futm_source%3Dddg%26ref%3Dhome">Complex UDDG</a>
                <a class="result__snippet">Complex snippet</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        // URL harus di-decode: https://blog.example.com/2026/07/article?utm_source=ddg&ref=home
        $this->assertStringContainsString( 'blog.example.com', $result[0]['link'] );
        $this->assertStringContainsString( 'utm_source=ddg', $result[0]['link'] );
        $this->assertStringContainsString( 'ref=home', $result[0]['link'] );
    }

    // ====================================================================
    // DDG_PARSE_HTML — LIMIT & EDGE CASES
    // ====================================================================

    /**
     * Test ddg_parse_html membatasi maksimal 3 hasil.
     */
    public function test_parse_html_limits_to_three_results() {
        $items_html = '';
        for ( $i = 1; $i <= 5; $i++ ) {
            $items_html .= '<div class="web-result">
                <a class="result__a" href="https://example.com/' . $i . '">Judul ' . $i . '</a>
                <a class="result__snippet">Snippet ' . $i . '.</a>
            </div>';
        }
        $html = '<html><body>' . $items_html . '</body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 3, $result,
            'ddg_parse_html harus membatasi maksimal 3 hasil'
        );
        $this->assertSame( 'Judul 1', $result[0]['title'] );
        $this->assertSame( 'Judul 2', $result[1]['title'] );
        $this->assertSame( 'Judul 3', $result[2]['title'] );
    }

    /**
     * Test ddg_parse_html — passes_filters return false → item dilewati.
     */
    public function test_parse_html_skips_item_when_filter_fails() {
        $this->harness->passes_filters_result = false;

        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/1">Filtered Out</a>
                <a class="result__snippet">Should be skipped</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertEmpty( $result, 'Item yang tidak lolos filter harus dilewati' );
    }

    /**
     * Test ddg_parse_html — item dengan title dan content kosong → dilewati.
     *
     * empty($title) && empty($full_content) → continue
     */
    public function test_parse_html_skips_empty_title_and_content() {
        $this->harness->fetch_full_content_override = '';

        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/empty"></a>
                <a class="result__snippet"></a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertEmpty( $result, 'Item dengan title dan content kosong harus dilewati' );
    }

    /**
     * Test ddg_parse_html — item dengan title kosong tapi content ada → TIDAK dilewati.
     *
     * empty($title) && empty($full_content) → false karena content tidak empty
     */
    public function test_parse_html_keeps_item_with_empty_title_but_nonempty_content() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/content-only">  </a>
                <a class="result__snippet">Only content here</a>
            </div>
        </body></html>';

        // trim() of "  " = "" → title empty
        // fetch_full_content return 'Scraped content from ...' → content not empty
        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertNotEmpty( $result, 'Item dengan title kosong tapi content ada harus tetap masuk' );
    }

    /**
     * Test ddg_parse_html — link kosong dan fetch_full_content tidak dipanggil.
     */
    public function test_parse_html_empty_link_uses_snippet() {
        $this->harness->fetch_call_count = 0;

        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="">Empty Link</a>
                <a class="result__snippet">Fallback snippet content</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 0, $this->harness->fetch_call_count,
            'fetch_full_content tidak boleh dipanggil untuk link kosong'
        );
        $this->assertSame( 'Fallback snippet content', $result[0]['content'],
            'Content harus fallback ke snippet ketika link kosong'
        );
    }

    /**
     * Test ddg_parse_html — fetch_full_content return false → falls back to snippet.
     */
    public function test_parse_html_fetch_full_content_false_uses_snippet() {
        $this->harness->fetch_full_content_override = false;

        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/fail">Fetch Fail</a>
                <a class="result__snippet">Snippet fallback content</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Snippet fallback content', $result[0]['content'],
            'fetch_full_content false → content harus snippet'
        );
    }

    /**
     * Test ddg_parse_html — fetch_full_content return empty string → falls back to snippet.
     */
    public function test_parse_html_fetch_full_content_empty_uses_snippet() {
        $this->harness->fetch_full_content_override = '';

        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ex.com/empty-content">Empty Content</a>
                <a class="result__snippet">Snippet after empty</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Snippet after empty', $result[0]['content'],
            'fetch_full_content empty → content harus snippet'
        );
    }

    /**
     * Test ddg_parse_html — memverifikasi struktur item output.
     */
    public function test_parse_html_output_structure() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://example.com/test">Test</a>
                <a class="result__snippet">Snippet.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertArrayHasKey( 'title', $result[0] );
        $this->assertArrayHasKey( 'link', $result[0] );
        $this->assertArrayHasKey( 'description', $result[0] );
        $this->assertArrayHasKey( 'content', $result[0] );
        $this->assertArrayHasKey( 'source_type', $result[0] );
        $this->assertArrayHasKey( 'source_url', $result[0] );
        $this->assertSame( 'duckduckgo_free', $result[0]['source_type'] );
        $this->assertSame( 'test query', $result[0]['source_url'] );
    }

    /**
     * Test bahwa fetch_full_content() dipanggil untuk setiap result.
     */
    public function test_parse_html_calls_fetch_full_content() {
        $this->harness->fetch_call_count = 0;

        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://example.com/a">A</a>
                <a class="result__snippet">Snippet A.</a>
            </div>
            <div class="web-result">
                <a class="result__a" href="https://example.com/b">B</a>
                <a class="result__snippet">Snippet B.</a>
            </div>
            <div class="web-result">
                <a class="result__a" href="https://example.com/c">C</a>
                <a class="result__snippet">Snippet C.</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 3, $result );
        $this->assertSame( 3, $this->harness->fetch_call_count,
            'fetch_full_content harus dipanggil 3x (1x per result)'
        );
    }

    /**
     * Test ddg_parse_html — item tanpa link dan tanpa snippet → content empty.
     */
    public function test_parse_html_no_link_no_snippet() {
        $html = '<html><body>
            <div class="web-result">
                <a class="result__a" href="">No Link</a>
            </div>
        </body></html>';

        $result = $this->invokeMethod( $this->harness, 'ddg_parse_html', [ $html ] );

        $this->assertCount( 1, $result );
        $this->assertSame( '', $result[0]['content'], 'Tanpa link dan snippet, content harus kosong' );
        $this->assertSame( '', $result[0]['description'] );
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
