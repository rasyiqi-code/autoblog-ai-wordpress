<?php
/**
 * Unit Test untuk Autoblog\\Sources\\Drivers\\SerpApiDriver trait.
 *
 * SerpApiDriver adalah trait yang di-use oleh SearchSource. Ia memiliki
 * 4 priority fetch: Google AI Mode → Bing Copilot → Google Standard → Organic Fallback.
 *
 * Strategi test:
 * - Test harness class (SerpApiTestHarness) yang menggunakan trait dan
 *   menyediakan property/method yang dibutuhkan trait.
 * - HTTP calls menggunakan mock Guzzle Client (MockHandler) yang di-inject
 *   via mock_client property harness.
 * - Pure function helpers (parse_text_blocks, extract_ai_overview_content,
 *   process_organic_results) diuji via reflection.
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
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

// ========================================================================
// TEST HARNESS: Kelas ringan yang menggunakan SerpApiDriver trait
// ========================================================================

/**
 * Test harness untuk SerpApiDriver.
 *
 * Menyediakan property ($query, $serpapi_key) dan stub method
 * (passes_filters, fetch_full_content, build_item) yang dibutuhkan trait.
 */
class SerpApiTestHarness {
    use \Autoblog\Sources\Drivers\SerpApiDriver;

    /** @var string */
    public $query = 'test query';

    /** @var string */
    public $serpapi_key = 'test_key';

    /** @var \GuzzleHttp\Client|null Mock Guzzle Client untuk integration test */
    public $mock_client = null;

    /**
     * Stub: semua filter lolos.
     */
    private function passes_filters( $text ) {
        return true;
    }

    /**
     * Stub: selalu return content placeholder.
     */
    private function fetch_full_content( $url ) {
        return 'Full article content from ' . $url;
    }

    /**
     * Stub: build item standar.
     */
    private function build_item( $title, $content, $type ) {
        return [
            'title'       => $title,
            'content'     => $content,
            'source_type' => $type,
            'source_url'  => $this->query,
        ];
    }

    /**
     * Stub: get_http_client() — jika mock_client diset, return itu.
     * Jika tidak, buat Client baru.
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

class SerpApiDriverTest extends TestCase {

    /** @var SerpApiTestHarness */
    private $harness;

    protected function setUp(): void {
        parent::setUp();
        $this->harness = new SerpApiTestHarness();
    }

    // ====================================================================
    // HELPER: Buat mock Guzzle Client dengan array responses
    // ====================================================================

    /**
     * Buat mock Guzzle Client dengan MockHandler.
     *
     * @param Response[] $responses Array of Guzzle Response objects
     * @return Client
     */
    private function createMockClient( array $responses ): Client {
        $mock = new MockHandler( $responses );
        $handlerStack = HandlerStack::create( $mock );
        return new Client( [ 'handler' => $handlerStack ] );
    }

    /**
     * Inject mock client ke harness dan panggil fetch_serpapi().
     *
     * @param Response[] $responses Mock responses
     * @return mixed Hasil dari fetch_serpapi()
     */
    private function fetchWithMockResponses( array $responses ) {
        $this->harness->mock_client = $this->createMockClient( $responses );
        return $this->invokeMethod( $this->harness, 'fetch_serpapi' );
    }

    // ====================================================================
    // FETCH_SERPAPI — GUARD / EARLY RETURN
    // ====================================================================

    /**
     * Test bahwa fetch_serpapi() return [] ketika serpapi_key kosong.
     */
    public function test_fetch_returns_empty_when_key_missing() {
        $this->harness->serpapi_key = '';

        $result = $this->invokeMethod( $this->harness, 'fetch_serpapi' );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'Tanpa serpapi_key, fetch_serpapi harus return []'
        );
    }

    /**
     * Test bahwa fetch_serpapi() tetap return array (tidak throw exception)
     * ketika semua HTTP call gagal (connection refused).
     */
    public function test_fetch_handles_http_exception_gracefully() {
        // Tanpa mock client → Guzzle coba koneksi nyata → gagal → return []
        $result = $this->invokeMethod( $this->harness, 'fetch_serpapi' );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'Semua HTTP gagal (no real API) → harus return []'
        );
    }

    // ====================================================================
    // PRIORITY 1: Google AI Mode
    // ====================================================================

    /**
     * P1 sukses — ai_overview sebagai string langsung.
     */
    public function test_p1_ai_mode_string_overview() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'ai_overview' => 'AI Mode string overview content.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result, 'P1 sukses harus menghasilkan 1 item' );
        $this->assertStringContainsString( 'AI Mode string overview content.', $result[0]['content'] );
        $this->assertSame( 'google_ai_mode', $result[0]['source_type'] );
    }

    /**
     * P1 sukses — ai_overview sebagai object dengan text_blocks.
     */
    public function test_p1_ai_mode_object_overview() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'ai_overview' => [
                    'text_blocks' => [
                        [ 'type' => 'paragraph', 'snippet' => 'Object overview paragraph.' ],
                        [ 'type' => 'heading', 'snippet' => 'Object Overview Heading' ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Object overview paragraph.', $result[0]['content'] );
        $this->assertStringContainsString( '### Object Overview Heading', $result[0]['content'] );
        $this->assertSame( 'google_ai_mode', $result[0]['source_type'] );
    }

    /**
     * P1 sukses — via text_blocks (tanpa ai_overview).
     */
    public function test_p1_ai_mode_text_blocks_fallback() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'text_blocks' => [
                    [ 'type' => 'list', 'list' => [
                        [ 'snippet' => 'Item dari text_blocks' ],
                    ] ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result, 'P1 text_blocks fallback harus menghasilkan 1 item' );
        $this->assertStringContainsString( '- Item dari text_blocks', $result[0]['content'] );
        $this->assertSame( 'google_ai_mode', $result[0]['source_type'] );
    }

    /**
     * P1 gagal (ai_overview = object kosong) → jatuh ke P2.
     * Mock 2 responses: P1 empty, P2 sukses (copilot_answer).
     */
    public function test_p1_empty_overview_falls_to_p2() {
        $result = $this->fetchWithMockResponses( [
            // P1: ai_overview object kosong → tidak return
            new Response( 200, [], json_encode( [
                'ai_overview' => [],
            ] ) ),
            // P2: Bing Copilot sukses
            new Response( 200, [], json_encode( [
                'copilot_answer' => 'Bing answer setelah P1 kosong.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Bing answer setelah P1 kosong.', $result[0]['content'] );
        $this->assertSame( 'bing_copilot', $result[0]['source_type'] );
    }

    /**
     * P1 gagal (error response) → jatuh ke P2.
     */
    public function test_p1_error_response_falls_to_p2() {
        $result = $this->fetchWithMockResponses( [
            // P1: error
            new Response( 200, [], json_encode( [ 'error' => 'AI Mode not available' ] ) ),
            // P2: Bing Copilot sukses
            new Response( 200, [], json_encode( [
                'copilot_answer' => 'Bing answer setelah P1 error.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Bing answer setelah P1 error.', $result[0]['content'] );
        $this->assertSame( 'bing_copilot', $result[0]['source_type'] );
    }

    /**
     * P1 gagal (HTTP 500) → exception → jatuh ke P2.
     */
    public function test_p1_http_error_falls_to_p2() {
        $result = $this->fetchWithMockResponses( [
            // P1: 500 Internal Server Error → exception
            new Response( 500, [], 'Internal Server Error' ),
            // P2: Bing Copilot sukses
            new Response( 200, [], json_encode( [
                'copilot_answer' => 'Bing answer setelah P1 HTTP 500.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result, 'P1 HTTP 500 harus fallback ke P2 dan berhasil' );
        $this->assertStringContainsString( 'Bing answer setelah P1 HTTP 500.', $result[0]['content'] );
        $this->assertSame( 'bing_copilot', $result[0]['source_type'] );
    }

    // ====================================================================
    // PRIORITY 2: Bing Copilot
    // ====================================================================

    /**
     * P2 sukses — copilot_answer sebagai string.
     */
    public function test_p2_bing_copilot_string_answer() {
        $result = $this->fetchWithMockResponses( [
            // P1: error
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            // P2: copilot_answer string
            new Response( 200, [], json_encode( [
                'copilot_answer' => 'Bing Copilot string answer.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Bing Copilot string answer.', $result[0]['content'] );
        $this->assertSame( 'bing_copilot', $result[0]['source_type'] );
    }

    /**
     * P2 sukses — copilot_answer sebagai object dengan text_blocks.
     */
    public function test_p2_bing_copilot_object_answer() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'copilot_answer' => [
                    'text_blocks' => [
                        [ 'type' => 'paragraph', 'snippet' => 'Bing copilot object paragraph.' ],
                        [ 'type' => 'heading', 'snippet' => 'Bing Heading' ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Bing copilot object paragraph.', $result[0]['content'] );
        $this->assertStringContainsString( '### Bing Heading', $result[0]['content'] );
    }

    /**
     * P2 sukses — via text_blocks + header (tanpa copilot_answer).
     */
    public function test_p2_bing_copilot_text_blocks_and_header() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'header' => 'Bing Header Title',
                'text_blocks' => [
                    [ 'type' => 'paragraph', 'snippet' => 'Bing text block content.' ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Bing Header Title', $result[0]['content'] );
        $this->assertStringContainsString( 'Bing text block content.', $result[0]['content'] );
    }

    /**
     * P2 sukses — hanya text_blocks (tanpa header dan copilot_answer).
     */
    public function test_p2_bing_copilot_text_blocks_only() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'text_blocks' => [
                    [ 'type' => 'paragraph', 'snippet' => 'Only text blocks content.' ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Only text blocks content.', $result[0]['content'] );
    }

    /**
     * P2 response kosong (tidak ada copilot_answer/text_blocks/header) → jatuh ke P3.
     */
    public function test_p2_empty_response_falls_to_p3() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            // P2: no copilot_answer, no text_blocks, no header
            new Response( 200, [], json_encode( [ 'some_other_field' => 'value' ] ) ),
            // P3: Google Standard AI Overview sukses
            new Response( 200, [], json_encode( [
                'ai_overview' => 'P3 AI overview after P2 empty.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result, 'P2 empty harus fallback ke P3 dan menghasilkan item' );
        $this->assertStringContainsString( 'P3 AI overview after P2 empty.', $result[0]['content'] );
        $this->assertSame( 'google_ai_overview', $result[0]['source_type'] );
    }

    /**
     * P2 error response → jatuh ke P3.
     */
    public function test_p2_error_response_falls_to_p3() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'Bing Copilot not available' ] ) ),
            new Response( 200, [], json_encode( [
                'ai_overview' => 'P3 content after P2 error.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'P3 content after P2 error.', $result[0]['content'] );
        $this->assertSame( 'google_ai_overview', $result[0]['source_type'] );
    }

    /**
     * P2 HTTP 500 → exception → jatuh ke P3.
     */
    public function test_p2_http_error_falls_to_p3() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 500, [], 'P2 Internal Error' ),
            new Response( 200, [], json_encode( [
                'ai_overview' => 'P3 after P2 HTTP 500.',
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'P3 after P2 HTTP 500.', $result[0]['content'] );
    }

    // ====================================================================
    // PRIORITY 3: Google Standard + AI Overview
    // ====================================================================

    /**
     * P3 sukses — ai_overview string.
     */
    public function test_p3_google_standard_ai_overview_string() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'ai_overview' => 'P3 AI Overview string.',
                'organic_results' => [
                    [ 'title' => 'Organic 1', 'link' => 'http://ex.com/1', 'snippet' => 'Snip 1' ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result, 'P3 sukses harus menghasilkan 1 item' );
        $this->assertStringContainsString( 'P3 AI Overview string.', $result[0]['content'] );
        $this->assertSame( 'google_ai_overview', $result[0]['source_type'] );
    }

    /**
     * P3 sukses — ai_overview object dengan text_blocks.
     */
    public function test_p3_google_standard_ai_overview_object() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'ai_overview' => [
                    'text_blocks' => [
                        [ 'type' => 'paragraph', 'snippet' => 'P3 AI Overview object paragraph.' ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'P3 AI Overview object paragraph.', $result[0]['content'] );
    }

    /**
     * P3 error response → organic_results ter-cache untuk P4 → P4 dari cache.
     */
    public function test_p3_error_with_organic_cache_falls_to_p4_from_cache() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            // P3: error + organic_results (ter-cache)
            new Response( 200, [], json_encode( [
                'error' => 'P3 failed',
                'organic_results' => [
                    [ 'title' => 'Cached Organic', 'link' => 'http://ex.com/cached', 'snippet' => 'Cached snippet' ],
                ],
            ] ) ),
            // P4 menggunakan cached hasil → tidak perlu response ke-4
        ] );

        $this->assertNotEmpty( $result, 'Harus menghasilkan item dari P4 cached organic' );
        $this->assertSame( 'Cached Organic', $result[0]['title'] );
        $this->assertSame( 'google_standard_fallback', $result[0]['source_type'] );
    }

    /**
     * P3 tidak ada ai_overview (tapi ada organic_results) → jatuh ke P4 dari cache.
     */
    public function test_p3_no_ai_overview_falls_to_p4_cached_organic() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            // P3: no ai_overview, tapi ada organic_results
            new Response( 200, [], json_encode( [
                'organic_results' => [
                    [ 'title' => 'No AI Overview Result', 'link' => 'http://ex.com/no-ai', 'snippet' => 'Snippet' ],
                ],
            ] ) ),
        ] );

        $this->assertNotEmpty( $result );
        $this->assertSame( 'No AI Overview Result', $result[0]['title'] );
        $this->assertSame( 'google_standard_fallback', $result[0]['source_type'] );
    }

    // ====================================================================
    // PRIORITY 4: Organic Fallback
    // ====================================================================

    /**
     * P4 dari cached organic_results (P3 buffer).
     * P1-P3 semua fail, P3 punya organic_results → P4 menggunakan cache.
     * Hanya perlu 3 responses (P1, P2, P3).
     */
    public function test_p4_organic_fallback_from_cache() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'error' => 'P3 also failed',
                'organic_results' => [
                    [ 'title' => 'Organic Cache 1', 'link' => 'http://ex.com/cache1', 'snippet' => 'Snip 1' ],
                    [ 'title' => 'Organic Cache 2', 'link' => 'http://ex.com/cache2', 'snippet' => 'Snip 2' ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 2, $result, 'P4 dari cache harus menghasilkan 2 item' );
        $this->assertSame( 'Organic Cache 1', $result[0]['title'] );
        $this->assertSame( 'Organic Cache 2', $result[1]['title'] );
        $this->assertSame( 'google_standard_fallback', $result[0]['source_type'] );
    }

    /**
     * P4 — organic fallback membatasi maksimal 3 item.
     */
    public function test_p4_organic_fallback_limits_to_three() {
        $organicItems = [];
        for ( $i = 1; $i <= 5; $i++ ) {
            $organicItems[] = [
                'title'   => "Organic $i",
                'link'    => "http://ex.com/$i",
                'snippet' => "Snippet $i",
            ];
        }

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'error' => 'P3 failed',
                'organic_results' => $organicItems,
            ] ) ),
        ] );

        $this->assertCount( 3, $result, 'P4 organic fallback harus membatasi maksimal 3 item' );
    }

    /**
     * P4 — fetch_standard_results() dipanggil ketika P3 tidak punya organic_results cache.
     * Butuh 4 responses: P1, P2, P3(polos), P4(fetch_standard_results).
     */
    public function test_p4_organic_fallback_fetches_standard_results() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            // P3: error, tanpa organic_results → cache kosong
            new Response( 200, [], json_encode( [ 'error' => 'P3 failed' ] ) ),
            // P4: fetch_standard_results (engine=google) → sukses
            new Response( 200, [], json_encode( [
                'organic_results' => [
                    [ 'title' => 'Fresh Organic', 'link' => 'http://ex.com/fresh', 'snippet' => 'Fresh snippet' ],
                ],
            ] ) ),
        ] );

        $this->assertNotEmpty( $result, 'P4 fetch_standard_results harus menghasilkan item' );
        $this->assertSame( 'Fresh Organic', $result[0]['title'] );
    }

    /**
     * P4 — fetch_standard_results return organic_results kosong → return [].
     */
    public function test_p4_organic_fallback_standard_results_empty() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            // P3: error, tanpa organic_results
            new Response( 200, [], json_encode( [ 'error' => 'P3 failed' ] ) ),
            // P4: fetch_standard_results → organic_results kosong
            new Response( 200, [], json_encode( [ 'organic_results' => [] ] ) ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'organic_results kosong harus return []' );
    }

    /**
     * P4 — fetch_standard_results gagal (HTTP error) → return [].
     */
    public function test_p4_organic_fallback_standard_results_fails() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            // P3: error, tanpa organic_results
            new Response( 200, [], json_encode( [ 'error' => 'P3 failed' ] ) ),
            // P4: fetch_standard_results → 500 error
            new Response( 500, [], 'Server Error' ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'P4 fetch_standard_results gagal harus return []' );
    }

    // ====================================================================
    // ALL PRIORITIES FAIL
    // ====================================================================

    /**
     * Semua priority gagal → return [].
     */
    public function test_all_priorities_fail_returns_empty() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            // P3: error, tanpa organic_results
            new Response( 200, [], json_encode( [ 'error' => 'P3 failed' ] ) ),
            // P4: fetch_standard_results → error
            new Response( 200, [], json_encode( [ 'error' => 'P4 also failed' ] ) ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Semua priority gagal harus return []' );
    }

    // ====================================================================
    // PARSE_TEXT_BLOCKS — PURE FUNCTION TESTS
    // ====================================================================

    /**
     * Test parse_text_blocks dengan heading block.
     */
    public function test_parse_text_blocks_heading() {
        $blocks = [
            [ 'type' => 'heading', 'snippet' => 'Pengantar AI' ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringContainsString( '### Pengantar AI', $result );
    }

    /**
     * Test parse_text_blocks dengan list block.
     */
    public function test_parse_text_blocks_list() {
        $blocks = [
            [
                'type' => 'list',
                'list' => [
                    [ 'snippet' => 'Item pertama' ],
                    [ 'title' => 'Item kedua' ],
                    [ 'snippet' => 'Item ketiga' ],
                ],
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringContainsString( '- Item pertama', $result );
        $this->assertStringContainsString( '- Item kedua', $result );
        $this->assertStringContainsString( '- Item ketiga', $result );
    }

    /**
     * Test parse_text_blocks dengan paragraph block (snippet).
     */
    public function test_parse_text_blocks_paragraph_snippet() {
        $blocks = [
            [ 'type' => 'paragraph', 'snippet' => 'Ini adalah paragraf.' ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringContainsString( 'Ini adalah paragraf.', $result );
    }

    /**
     * Test parse_text_blocks dengan paragraph block (text field).
     */
    public function test_parse_text_blocks_paragraph_text() {
        $blocks = [
            [ 'type' => 'paragraph', 'text' => 'Teks dari field text.' ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringContainsString( 'Teks dari field text.', $result );
    }

    /**
     * Test parse_text_blocks dengan block tanpa snippet dan text.
     */
    public function test_parse_text_blocks_empty_block() {
        $blocks = [
            [ 'type' => 'paragraph' ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertEmpty( $result,
            'Block tanpa snippet/text harus menghasilkan string kosong'
        );
    }

    /**
     * Test parse_text_blocks dengan multiple blocks campuran.
     */
    public function test_parse_text_blocks_mixed_blocks() {
        $blocks = [
            [ 'type' => 'heading', 'snippet' => 'Judul' ],
            [ 'type' => 'paragraph', 'snippet' => 'Paragraf.' ],
            [
                'type' => 'list',
                'list' => [
                    [ 'snippet' => 'Item 1' ],
                ],
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringContainsString( '### Judul', $result );
        $this->assertStringContainsString( 'Paragraf.', $result );
        $this->assertStringContainsString( '- Item 1', $result );
    }

    /**
     * Test parse_text_blocks dengan list item tanpa snippet dan title.
     */
    public function test_parse_text_blocks_list_item_empty() {
        $blocks = [
            [
                'type' => 'list',
                'list' => [
                    [ 'foo' => 'bar' ],
                ],
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringNotContainsString( '- ', $result );
    }

    /**
     * Test parse_text_blocks dengan type tidak dikenal (default ke paragraph).
     */
    public function test_parse_text_blocks_unknown_type() {
        $blocks = [
            [ 'type' => 'custom_block', 'snippet' => 'Custom content' ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringContainsString( 'Custom content', $result,
            'Type tidak dikenal harus tetap memproses snippet'
        );
    }

    /**
     * Test parse_text_blocks dengan array blocks kosong.
     */
    public function test_parse_text_blocks_empty_array() {
        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ [] ] );
        $this->assertEmpty( $result, 'Array blocks kosong harus return string kosong' );
    }

    /**
     * Test parse_text_blocks — list item dengan snippet lebih diprioritaskan dari title.
     */
    public function test_parse_text_blocks_list_item_snippet_over_title() {
        $blocks = [
            [
                'type' => 'list',
                'list' => [
                    [ 'snippet' => 'Snippet text', 'title' => 'Title text' ],
                ],
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'parse_text_blocks', [ $blocks ] );
        $this->assertStringContainsString( '- Snippet text', $result,
            'snippet harus diprioritaskan dari title'
        );
        $this->assertStringNotContainsString( '- Title text', $result );
    }

    // ====================================================================
    // EXTRACT_AI_OVERVIEW_CONTENT — PURE FUNCTION TESTS
    // ====================================================================

    /**
     * Test extract_ai_overview_content dengan string langsung.
     */
    public function test_extract_ai_overview_string() {
        $result = $this->invokeMethod( $this->harness, 'extract_ai_overview_content', [ 'AI Overview text' ] );
        $this->assertSame( 'AI Overview text', $result );
    }

    /**
     * Test extract_ai_overview_content dengan objek berisi text_blocks.
     */
    public function test_extract_ai_overview_object_with_blocks() {
        $overview = [
            'text_blocks' => [
                [ 'type' => 'paragraph', 'snippet' => 'Block content.' ],
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'extract_ai_overview_content', [ $overview ] );
        $this->assertStringContainsString( 'Block content.', $result );
    }

    /**
     * Test extract_ai_overview_content dengan objek tanpa text_blocks.
     */
    public function test_extract_ai_overview_empty_object() {
        $result = $this->invokeMethod( $this->harness, 'extract_ai_overview_content', [ [ 'foo' => 'bar' ] ] );
        $this->assertSame( '', $result,
            'Objek tanpa text_blocks harus return string kosong'
        );
    }

    /**
     * Test extract_ai_overview_content dengan string kosong.
     */
    public function test_extract_ai_overview_empty_string() {
        $result = $this->invokeMethod( $this->harness, 'extract_ai_overview_content', [ '' ] );
        $this->assertSame( '', $result,
            'String kosong harus return string kosong'
        );
    }

    /**
     * Test extract_ai_overview_content dengan null.
     */
    public function test_extract_ai_overview_null() {
        $result = $this->invokeMethod( $this->harness, 'extract_ai_overview_content', [ null ] );
        $this->assertSame( '', $result,
            'null harus return string kosong'
        );
    }

    // ====================================================================
    // PROCESS_ORGANIC_RESULTS — PURE FUNCTION TESTS
    // ====================================================================

    /**
     * Test process_organic_results dengan results kosong.
     */
    public function test_process_organic_results_empty() {
        $result = $this->invokeMethod( $this->harness, 'process_organic_results', [ [] ] );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Results kosong harus return []' );
    }

    /**
     * Test process_organic_results dengan 1 result valid.
     */
    public function test_process_organic_results_single() {
        $results = [
            [
                'title'   => 'Judul Artikel',
                'link'    => 'https://example.com/artikel',
                'snippet' => 'Deskripsi singkat artikel.',
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'process_organic_results', [ $results ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Judul Artikel', $result[0]['title'] );
        $this->assertSame( 'https://example.com/artikel', $result[0]['link'] );
        $this->assertSame( 'Full article content from https://example.com/artikel', $result[0]['content'] );
    }

    /**
     * Test process_organic_results — item tanpa snippet (fallback ke empty string).
     */
    public function test_process_organic_results_without_snippet() {
        $results = [
            [
                'title'   => 'No Snippet Article',
                'link'    => 'https://ex.com/no-snippet',
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'process_organic_results', [ $results ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'No Snippet Article', $result[0]['title'] );
        $this->assertSame( '', $result[0]['description'],
            'Tanpa snippet, description harus string kosong'
        );
    }

    /**
     * Test process_organic_results — verify bahwa item memiliki struktur yang diharapkan.
     */
    public function test_process_organic_results_item_structure() {
        $results = [
            [
                'title'   => 'Test',
                'link'    => 'https://test.com',
                'snippet' => 'Snippet',
            ],
        ];

        $result = $this->invokeMethod( $this->harness, 'process_organic_results', [ $results ] );

        $this->assertArrayHasKey( 'title', $result[0] );
        $this->assertArrayHasKey( 'link', $result[0] );
        $this->assertArrayHasKey( 'description', $result[0] );
        $this->assertArrayHasKey( 'content', $result[0] );
        $this->assertArrayHasKey( 'source_type', $result[0] );
        $this->assertArrayHasKey( 'source_url', $result[0] );
    }

    // ====================================================================
    // FETCH_STANDARD_RESULTS — VIA REFLECTION
    // ====================================================================

    /**
     * Test fetch_standard_results dengan organic_results valid.
     */
    public function test_fetch_standard_results_with_results() {
        $mockClient = $this->createMockClient( [
            new Response( 200, [], json_encode( [
                'organic_results' => [
                    [ 'title' => 'Std Result 1', 'link' => 'http://ex.com/std1', 'snippet' => 'Std 1' ],
                ],
            ] ) ),
        ] );

        $result = $this->invokeMethod(
            $this->harness,
            'fetch_standard_results',
            [ $mockClient, 'https://serpapi.com/search', [ 'q' => 'test' ] ]
        );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Std Result 1', $result[0]['title'] );
    }

    /**
     * Test fetch_standard_results dengan organic_results kosong → return [].
     */
    public function test_fetch_standard_results_empty() {
        $mockClient = $this->createMockClient( [
            new Response( 200, [], json_encode( [ 'organic_results' => [] ] ) ),
        ] );

        $result = $this->invokeMethod(
            $this->harness,
            'fetch_standard_results',
            [ $mockClient, 'https://serpapi.com/search', [ 'q' => 'test' ] ]
        );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'organic_results kosong harus return []' );
    }

    /**
     * Test fetch_standard_results dengan response tanpa organic_results key → return [].
     */
    public function test_fetch_standard_results_missing_key() {
        $mockClient = $this->createMockClient( [
            new Response( 200, [], json_encode( [ 'some_other' => 'data' ] ) ),
        ] );

        $result = $this->invokeMethod(
            $this->harness,
            'fetch_standard_results',
            [ $mockClient, 'https://serpapi.com/search', [ 'q' => 'test' ] ]
        );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Tanpa key organic_results harus return []' );
    }

    // ====================================================================
    // HELPER: Invoke private trait method via reflection
    // ====================================================================

    /**
     * Panggil private method (termasuk trait method) via reflection.
     *
     * @param object $object
     * @param string $methodName
     * @param array  $parameters
     * @return mixed
     */
    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }
}
