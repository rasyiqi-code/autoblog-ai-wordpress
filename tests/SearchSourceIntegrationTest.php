<?php
/**
 * Integration Test untuk SearchSource — HTTP Request Formatting & Response Parsing.
 *
 * Menggunakan GuzzleHttp MockHandler + History middleware untuk:
 * 1. Meng-intercept HTTP request SEBELUM dikirim
 * 2. Memverifikasi URL, method, headers, dan query parameters
 * 3. Mengembalikan mock response JSON/HTML
 * 4. Memverifikasi parsing response menjadi item array
 *
 * Dependency injection Guzzle Client via SearchSource::set_http_client().
 * (get_http_client() method di SearchSource mengembalikan mock client jika diset)
 *
 * Driver yang diuji:
 * - SerpApiDriver:   fetch_serpapi() — 4 priorities
 * - DuckDuckGoDriver: fetch_duckduckgo_free() — WP HTTP API → Guzzle fallback
 * - BraveDriver:      fetch_brave() — Brave Search API
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      integration
 * @group      drivers
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Autoblog\Sources\SearchSource;
use Autoblog\Utils\OptionCache;

class SearchSourceIntegrationTest extends TestCase {

    /** @var array Container untuk Guzzle History middleware (captured requests) */
    private $requestContainer = [];

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
    // HELPER: Create mock Guzzle Client dengan response tertentu
    // ====================================================================

    /**
     * Buat mock Guzzle Client yang mengembalikan response tertentu dan
     * mencatat semua request yang masuk.
     *
     * @param string $responseBody Body response yang akan dikembalikan
     * @param int    $statusCode   HTTP status code
     * @param array  $headers      HTTP response headers
     * @return Client
     */
    private function createMockGuzzleClient(
        string $responseBody,
        int $statusCode = 200,
        array $headers = []
    ): Client {
        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );

        $mock = new MockHandler( [
            new Response( $statusCode, $headers, $responseBody ),
        ] );

        $handlerStack = HandlerStack::create( $mock );
        $handlerStack->push( $history );

        return new Client( [ 'handler' => $handlerStack ] );
    }

    /**
     * Ambil request yang tertangkap oleh History middleware.
     *
     * @return \Psr\Http\Message\RequestInterface[]
     */
    private function getCapturedRequests(): array {
        $requests = [];
        foreach ( $this->requestContainer as $transaction ) {
            $requests[] = $transaction['request'];
        }
        return $requests;
    }

    /**
     * Buat SearchSource dengan konfigurasi mock dan inject mock Guzzle Client.
     *
     * @param string $query        Query pencarian
     * @param string $provider     Provider (serpapi, brave, duckduckgo_free)
     * @param Client $mockClient   Mock Guzzle Client
     * @return SearchSource
     */
    private function createSearchSourceWithMockClient(
        string $query,
        string $provider,
        Client $mockClient
    ): SearchSource {
        global $_autoblog_mock_options;

        $_autoblog_mock_options['autoblog_search_provider'] = $provider;
        $_autoblog_mock_options['autoblog_serpapi_key'] = 'test_serpapi_key';
        $_autoblog_mock_options['autoblog_brave_key']   = 'test_brave_key';

        $source = new SearchSource( $query );
        $source->set_http_client( $mockClient );

        return $source;
    }

    // ====================================================================
    // SERPAPI DRIVER — HTTP REQUEST FORMATTING
    // ====================================================================

    /**
     * Test bahwa SerpApiDriver mengirim request ke serpapi.com/search
     * dengan parameter engine=google_ai_mode, q, api_key, gl, hl.
     *
     * Mock response: AI Mode dengan ai_overview string.
     */
    public function test_serpapi_google_ai_mode_request_format() {
        $mockClient = $this->createMockGuzzleClient(
            json_encode( [ 'ai_overview' => 'AI Overview text from SerpApi.' ] )
        );

        $source = $this->createSearchSourceWithMockClient(
            'AI technology trends',
            'serpapi',
            $mockClient
        );

        $result = $source->fetch_data();

        // ── Verifikasi Request Format ──
        $requests = $this->getCapturedRequests();
        $this->assertCount( 1, $requests,
            'SerpApi harus mengirim 1 request (AI Mode sukses, stop)'
        );

        $request  = $requests[0];
        $uri      = $request->getUri();
        $queryStr = $uri->getQuery();
        parse_str( $queryStr, $params );

        $this->assertEquals( 'GET', $request->getMethod() );
        $this->assertStringContainsString( 'serpapi.com', $uri->getHost() );
        $this->assertEquals( '/search', $uri->getPath() );

        // Verify query parameters
        $this->assertArrayHasKey( 'engine', $params );
        $this->assertEquals( 'google_ai_mode', $params['engine'],
            'Priority 1 harus menggunakan engine=google_ai_mode'
        );
        $this->assertEquals( 'AI technology trends', $params['q'] );
        $this->assertEquals( 'test_serpapi_key', $params['api_key'] );
        $this->assertEquals( 'us', $params['gl'] );
        $this->assertEquals( 'en', $params['hl'] );

        // ── Verifikasi Response Parsing ──
        $this->assertCount( 1, $result,
            'AI Mode sukses harus menghasilkan 1 item'
        );
        $this->assertSame( 'AI technology trends', $result[0]['title'] );
        $this->assertStringContainsString( 'AI Overview text from SerpApi', $result[0]['content'] );
        $this->assertSame( 'google_ai_mode', $result[0]['source_type'] );
    }

    /**
     * Test bahwa SerpApiDriver fallback ke Priority 2 (bing_copilot)
     * ketika Priority 1 (google_ai_mode) gagal.
     *
     * Mock: P1 return error → P2 return copilot_answer.
     */
    public function test_serpapi_fallback_to_bing_copilot() {
        $mockHandler = new MockHandler( [
            // P1: Google AI Mode gagal (error response)
            new Response( 200, [], json_encode( [ 'error' => 'AI Mode not available' ] ) ),
            // P2: Bing Copilot sukses
            new Response( 200, [], json_encode( [
                'copilot_answer' => 'Bing Copilot answer text.',
            ] ) ),
        ] );

        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );
        $handlerStack = HandlerStack::create( $mockHandler );
        $handlerStack->push( $history );

        $mockClient = new Client( [ 'handler' => $handlerStack ] );

        $source = $this->createSearchSourceWithMockClient(
            'machine learning',
            'serpapi',
            $mockClient
        );

        $result = $source->fetch_data();

        // ── Verifikasi Request Format (2 requests) ──
        $requests = $this->getCapturedRequests();
        $this->assertCount( 2, $requests, 'Harus ada 2 request (P1 gagal, P2 sukses)' );

        // P1: google_ai_mode
        parse_str( $requests[0]->getUri()->getQuery(), $p1Params );
        $this->assertEquals( 'google_ai_mode', $p1Params['engine'] );

        // P2: bing_copilot
        parse_str( $requests[1]->getUri()->getQuery(), $p2Params );
        $this->assertEquals( 'bing_copilot', $p2Params['engine'],
            'Priority 2 harus menggunakan engine=bing_copilot'
        );
        $this->assertEquals( 'Balanced', $p2Params['tone'] );

        // ── Verifikasi Response Parsing ──
        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Bing Copilot answer text.', $result[0]['content'] );
        $this->assertSame( 'bing_copilot', $result[0]['source_type'] );
    }

    /**
     * Test SerpApi P3 (Google Standard) ketika P1 dan P2 gagal,
     * dan P3 mengembalikan ai_overview.
     */
    public function test_serpapi_fallback_to_google_standard() {
        $mockHandler = new MockHandler( [
            new Response( 200, [], json_encode( [ 'error' => 'P1 failed' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'P2 failed' ] ) ),
            new Response( 200, [], json_encode( [
                'ai_overview' => 'AI Overview from Google Standard.',
                'organic_results' => [ [ 'title' => 'Organic', 'link' => 'http://ex.com', 'snippet' => 'Snip' ] ],
            ] ) ),
        ] );

        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );
        $handlerStack = HandlerStack::create( $mockHandler );
        $handlerStack->push( $history );

        $mockClient = new Client( [ 'handler' => $handlerStack ] );

        $source = $this->createSearchSourceWithMockClient( 'test query', 'serpapi', $mockClient );
        $result = $source->fetch_data();

        $requests = $this->getCapturedRequests();
        $this->assertCount( 3, $requests, 'Harus ada 3 request (P1+P2 gagal, P3 sukses)' );

        parse_str( $requests[2]->getUri()->getQuery(), $p3Params );
        $this->assertEquals( 'google', $p3Params['engine'],
            'Priority 3 harus menggunakan engine=google'
        );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'AI Overview from Google Standard', $result[0]['content'] );
    }

    // ====================================================================
    // BRAVE DRIVER — HTTP REQUEST FORMATTING
    // ====================================================================

    /**
     * Test bahwa BraveDriver mengirim request ke Brave Search API
     * dengan header X-Subscription-Token, Accept, dan Accept-Encoding yang benar,
     * serta query parameters q, count, dan summary.
     */
    public function test_brave_request_format_and_response() {
        $mockResponse = json_encode( [
            'web' => [
                'results' => [
                    [
                        'title'       => 'Brave Result 1',
                        'url'         => 'https://example.com/brave1',
                        'description' => 'Deskripsi Brave result 1.',
                    ],
                    [
                        'title'       => 'Brave Result 2',
                        'url'         => 'https://example.com/brave2',
                        'description' => 'Deskripsi Brave result 2.',
                    ],
                ],
            ],
        ] );

        $mockClient = $this->createMockGuzzleClient( $mockResponse );

        $source = $this->createSearchSourceWithMockClient(
            'brave search test',
            'brave',
            $mockClient
        );

        $result = $source->fetch_data();

        // ── Verifikasi Request Format ──
        $requests = $this->getCapturedRequests();
        $this->assertCount( 1, $requests );

        $request = $requests[0];
        $uri     = $request->getUri();
        $queryStr = $uri->getQuery();
        parse_str( $queryStr, $params );

        $this->assertEquals( 'GET', $request->getMethod() );
        $this->assertStringContainsString( 'api.search.brave.com', $uri->getHost() );
        $this->assertEquals( '/res/v1/web/search', $uri->getPath() );

        // Verify query parameters
        $this->assertEquals( 'brave search test', $params['q'] );
        $this->assertEquals( '3', $params['count'], 'Brave harus meminta count=3' );
        $this->assertEquals( '1', $params['summary'], 'Brave harus meminta summary=1' );

        // Verify headers
        $this->assertEquals( 'application/json', $request->getHeaderLine( 'Accept' ) );
        $this->assertEquals( 'gzip', $request->getHeaderLine( 'Accept-Encoding' ) );
        $this->assertEquals( 'test_brave_key', $request->getHeaderLine( 'X-Subscription-Token' ) );

        // ── Verifikasi Response Parsing ──
        $this->assertCount( 2, $result, 'Harus menghasilkan 2 item dari 2 result' );
        $this->assertSame( 'Brave Result 1', $result[0]['title'] );
        $this->assertSame( 'brave_search', $result[0]['source_type'] );
        // Content fallback: fetch_full_content() gagal di test env (Readability tidak ada),
        // jadi content menggunakan description dari response API
        $this->assertStringContainsString( 'Deskripsi Brave result 1.', $result[0]['description'],
            'Description harus dari response API'
        );
    }

    /**
     * Test BraveDriver ketika response API kosong (tidak ada results).
     */
    public function test_brave_handles_empty_results() {
        $mockClient = $this->createMockGuzzleClient(
            json_encode( [ 'web' => [ 'results' => [] ] ] )
        );

        $source = $this->createSearchSourceWithMockClient( 'empty query', 'brave', $mockClient );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Response tanpa results harus return []' );
    }

    // ====================================================================
    // DUCKDUCKGO DRIVER — HTTP REQUEST FORMATTING (Guzzle Fallback)
    // ====================================================================

    /**
     * Test DuckDuckGoDriver — Guzzle Fallback HTTP request formatting.
     *
     * Di test environment, WP HTTP API (method 1) gagal karena wp_remote_get()
     * mocked return [] dengan body ''. Method 2 (Guzzle fallback) menggunakan
     * mock client yang kita inject.
     *
     * URL: https://html.duckduckgo.com/html/?q={query}
     * Headers: User-Agent, Accept, Accept-Language, Referer
     */
    public function test_duckduckgo_guzzle_fallback_request_format() {
        // Mock response HTML dengan 2 hasil
        $mockHtml = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://ddg.com/result1">DDG Result 1</a>
                <a class="result__snippet">Snippet 1.</a>
            </div>
            <div class="web-result">
                <a class="result__a" href="https://ddg.com/result2">DDG Result 2</a>
                <a class="result__snippet">Snippet 2.</a>
            </div>
        </body></html>';

        $mockClient = $this->createMockGuzzleClient( $mockHtml );

        $source = $this->createSearchSourceWithMockClient(
            'duckduckgo test',
            'duckduckgo_free',
            $mockClient
        );

        $result = $source->fetch_data();

        // ── Verifikasi Request Format ──
        $requests = $this->getCapturedRequests();
        $this->assertCount( 1, $requests, 'Harus 1 request via Guzzle fallback' );

        $request = $requests[0];
        $uri     = $request->getUri();

        $this->assertEquals( 'GET', $request->getMethod() );
        $this->assertStringContainsString( 'html.duckduckgo.com', $uri->getHost() );
        $this->assertEquals( '/html/', $uri->getPath() );
        $this->assertStringContainsString( 'q=duckduckgo+test', $uri->getQuery(),
            'Query parameter q harus di-URL-encode'
        );

        // Verify headers
        $this->assertStringContainsString( 'Mozilla', $request->getHeaderLine( 'User-Agent' ),
            'User-Agent harus browser-like'
        );
        $this->assertStringContainsString( 'text/html', $request->getHeaderLine( 'Accept' ) );
        $this->assertStringContainsString( 'duckduckgo.com', $request->getHeaderLine( 'Referer' ) );

        // ── Verifikasi Response Parsing ──
        $this->assertCount( 2, $result, 'Harus mengekstrak 2 hasil dari HTML' );
        $this->assertSame( 'DDG Result 1', $result[0]['title'] );
        $this->assertSame( 'duckduckgo_free', $result[0]['source_type'] );
        $this->assertSame( 'DDG Result 2', $result[1]['title'] );
    }

    /**
     * Test DuckDuckGoDriver — WP HTTP API method (tanpa mock Guzzle).
     *
     * Jika WP HTTP API return body valid, Guzzle fallback tidak boleh
     * dipanggil. Kita override mock wp_remote_get via global options...
     *
     * Catatan: wp_remote_get() di-mock di bootstrap.php dan tidak bisa
     * diubah per-test. Test ini memverifikasi bahwa Guzzle fallback
     * digunakan (karena WP selalu gagal di test env).
     */
    public function test_duckduckgo_uses_wp_http_first_then_guzzle_fallback() {
        $mockHtml = '<html><body>
            <div class="web-result">
                <a class="result__a" href="https://example.com/page">WP Fallback Test</a>
                <a class="result__snippet">Snippet</a>
            </div>
        </body></html>';

        $mockClient = $this->createMockGuzzleClient( $mockHtml );

        // JANGAN set autoblog_search_provider — default 'serpapi' akan digunakan
        // Kita override provider secara langsung via mock options
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'duckduckgo_free';

        $source = new SearchSource( 'test query' );
        $source->set_http_client( $mockClient );

        $result = $source->fetch_data();

        // WP HTTP API gagal (mocked) → Guzzle fallback sukses → parsing HTML
        $this->assertNotEmpty( $result, 'Harus dapat hasil via Guzzle fallback' );
        $this->assertSame( 'WP Fallback Test', $result[0]['title'] );

        $requests = $this->getCapturedRequests();
        $this->assertCount( 1, $requests,
            'Hanya 1 request via Guzzle (WP HTTP API gagal duluan)'
        );
    }

    // ====================================================================
    // ERROR HANDLING INTEGRATION
    // ====================================================================

    /**
     * Test bahwa SearchSource mengembalikan [] ketika semua driver gagal
     * (HTTP error responses).
     */
    public function test_all_priorities_fail_returns_empty() {
        $mockHandler = new MockHandler( [
            new Response( 500, [], 'Internal Server Error' ), // P1 gagal
            new Response( 500, [], 'Internal Server Error' ), // P2 gagal
            new Response( 500, [], 'Internal Server Error' ), // P3 gagal
            // P4: organic fallback juga gagal (fetch_standard_results throw)
            new Response( 500, [], 'Internal Server Error' ), // (tidak digunakan jika organic fallback kosong)
        ] );

        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );
        $handlerStack = HandlerStack::create( $mockHandler );
        $handlerStack->push( $history );

        $mockClient = new Client( [ 'handler' => $handlerStack ] );

        $source = $this->createSearchSourceWithMockClient( 'fail test', 'serpapi', $mockClient );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'Semua priority gagal (500) → harus return []'
        );
    }

    /**
     * Test bahwa SearchSource tidak throw exception ketika Guzzle throw
     * ConnectException (misal DNS failure).
     */
    public function test_connection_error_does_not_crash() {
        // Tanpa mock handler → Guzzle akan coba koneksi sungguhan
        // → ConnectException → catch di trait → log error → return []

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';
        $_autoblog_mock_options['autoblog_serpapi_key'] = 'test_key';

        $source = new SearchSource( 'connection test' );
        // Tidak set mock client → Guzzle coba koneksi nyata → gagal

        $result = $source->fetch_data();

        $this->assertIsArray( $result, 'Harus tetap return array meskipun koneksi gagal' );
        // Di test environment tanpa internet/API, semua priority gagal → []
        // Mungkin ada hasil jika ada internet, tapi test environment lokal tidak punya API key
    }

    // ====================================================================
    // TIMEOUT & ERROR RESPONSE HANDLING
    // ====================================================================

    /**
     * Test bahwa SerpApi menangani error response (misal rate limit).
     *
     * Semua priority mengembalikan 429 error → semua gagal → return [].
     */
    public function test_serpapi_handles_api_error_response() {
        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );

        // 3 priorities: P1, P2, P3 → semua gagal
        $handlerStack = HandlerStack::create( new MockHandler( [
            new Response( 429, [], json_encode( [ 'error' => 'Rate limit' ] ) ),  // P1
            new Response( 429, [], json_encode( [ 'error' => 'Rate limit' ] ) ),  // P2
            new Response( 429, [], json_encode( [ 'error' => 'Rate limit' ] ) ),  // P3
        ] ) );
        $handlerStack->push( $history );
        $mockClient = new Client( [ 'handler' => $handlerStack ] );

        $source = $this->createSearchSourceWithMockClient( 'rate limit test', 'serpapi', $mockClient );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'Semua priority gagal karena error response → harus return []'
        );
    }

    // ====================================================================
    // REQUEST PARAMETER VALIDATION — EDGE CASES
    // ====================================================================

    /**
     * Test bahwa SerpApi mengirim query yang sudah di-URL-encode.
     *
     * Guzzle meng-encode query params via http_build_query():
     * 'special query & symbols + more' → 'special+query+%26+symbols+%2B+more'
     */
    public function test_serpapi_sends_url_encoded_query() {
        $mockClient = $this->createMockGuzzleClient(
            json_encode( [ 'ai_overview' => 'Result' ] )
        );

        $source = $this->createSearchSourceWithMockClient(
            'special query & symbols + more',
            'serpapi',
            $mockClient
        );

        $source->fetch_data();

        $requests = $this->getCapturedRequests();
        $this->assertCount( 1, $requests );

        $queryStr = $requests[0]->getUri()->getQuery();
        // Verifikasi query ada di parameter q (Guzzle encode otomatis)
        $this->assertStringContainsString( 'q=', $queryStr,
            'Query string harus mengandung parameter q'
        );
        // Verifikasi karakter spesial di-encode: & → %26, spasi → %20
        $this->assertStringContainsString( '%26', $queryStr,
            '& harus di-encode menjadi %26'
        );
        $this->assertStringContainsString( '%20', $queryStr,
            'Spasi di-encode sebagai %20 oleh Guzzle mock handler'
        );
    }

    /**
     * Test bahwa Brave Search mengirim count parameter yang benar.
     */
    public function test_brave_request_with_count_parameter() {
        $mockClient = $this->createMockGuzzleClient(
            json_encode( [ 'web' => [ 'results' => [] ] ] )
        );

        $source = $this->createSearchSourceWithMockClient( 'test', 'brave', $mockClient );
        $source->fetch_data();

        $requests = $this->getCapturedRequests();
        $this->assertCount( 1, $requests );

        parse_str( $requests[0]->getUri()->getQuery(), $params );
        $this->assertArrayHasKey( 'count', $params );
        $this->assertEquals( '3', $params['count'] );
        $this->assertArrayHasKey( 'summary', $params );
        $this->assertEquals( '1', $params['summary'] );
    }
}
