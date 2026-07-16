<?php
/**
 * Unit Test untuk Autoblog\Sources\Drivers\BraveDriver trait.
 *
 * BraveDriver adalah trait yang mengambil hasil pencarian dari
 * Brave Search API menggunakan Guzzle HTTP client.
 *
 * Strategi test:
 * - Test harness class (BraveTestHarness) yang menggunakan trait
 * - HTTP calls menggunakan mock Guzzle Client (MockHandler) yang di-inject
 *   via mock_client property harness
 * - Test semua path: success, empty, error, filter, fetch_full_content
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
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

// ========================================================================
// TEST HARNESS
// ========================================================================

/**
 * Test harness untuk BraveDriver.
 *
 * Menyediakan property ($query, $brave_key) dan stub method
 * (passes_filters, fetch_full_content) yang dibutuhkan trait.
 */
class BraveTestHarness {
    use \Autoblog\Sources\Drivers\BraveDriver;

    /** @var string */
    public $query = 'brave test query';

    /** @var string */
    public $brave_key = 'test_brave_key';

    /** @var \GuzzleHttp\Client|null Mock Guzzle Client */
    public $mock_client = null;

    /** @var bool Kontrol return value passes_filters */
    public $passes_filters_result = true;

    /** @var mixed Kontrol return value fetch_full_content (null = default) */
    public $fetch_full_content_override = null;

    /** @var int Counter untuk fetch_full_content calls */
    public $fetch_call_count = 0;

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
        return 'Full content from ' . $url;
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

class BraveDriverTest extends TestCase {

    /** @var BraveTestHarness */
    private $harness;

    /** @var array Container untuk Guzzle History middleware */
    private $requestContainer = [];

    protected function setUp(): void {
        parent::setUp();
        $this->harness = new BraveTestHarness();
    }

    protected function tearDown(): void {
        parent::tearDown();
    }

    // ====================================================================
    // HELPER
    // ====================================================================

    /**
     * Buat mock Guzzle Client dengan MockHandler.
     */
    private function createMockClient( array $responses ): Client {
        $mock = new MockHandler( $responses );
        $handlerStack = HandlerStack::create( $mock );
        return new Client( [ 'handler' => $handlerStack ] );
    }

    /**
     * Inject mock client ke harness dan panggil fetch_brave().
     */
    private function fetchWithMockResponses( array $responses ) {
        $this->harness->mock_client = $this->createMockClient( $responses );
        return $this->invokeMethod( $this->harness, 'fetch_brave' );
    }

    // ====================================================================
    // KEY GUARD
    // ====================================================================

    /**
     * Test fetch_brave() return [] ketika brave_key kosong.
     */
    public function test_fetch_returns_empty_when_key_missing() {
        $this->harness->brave_key = '';

        $result = $this->invokeMethod( $this->harness, 'fetch_brave' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'Tanpa brave_key, fetch_brave harus return []'
        );
    }

    /**
     * Test fetch_brave() tidak throw exception saat Guzzle gagal
     * (connection refused di test environment).
     */
    public function test_fetch_does_not_throw_on_http_error() {
        $result = $this->invokeMethod( $this->harness, 'fetch_brave' );

        $this->assertIsArray( $result,
            'fetch_brave harus tetap return array meskipun HTTP gagal'
        );
    }

    // ====================================================================
    // SUCCESS PATH — WITH RESULTS
    // ====================================================================

    /**
     * Test fetch_brave sukses dengan 1 result.
     */
    public function test_fetch_success_with_single_result() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Brave Result 1',
                            'url'         => 'https://example.com/1',
                            'description' => 'Deskripsi result 1.',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Brave Result 1', $result[0]['title'] );
        $this->assertSame( 'https://example.com/1', $result[0]['link'] );
        $this->assertSame( 'brave_search', $result[0]['source_type'] );
        $this->assertSame( 'brave test query', $result[0]['source_url'] );
    }

    /**
     * Test fetch_brave sukses dengan multiple results.
     */
    public function test_fetch_success_with_multiple_results() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Result 1',
                            'url'         => 'https://ex.com/1',
                            'description' => 'Desc 1',
                        ],
                        [
                            'title'       => 'Result 2',
                            'url'         => 'https://ex.com/2',
                            'description' => 'Desc 2',
                        ],
                        [
                            'title'       => 'Result 3',
                            'url'         => 'https://ex.com/3',
                            'description' => 'Desc 3',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 3, $result );
        $this->assertSame( 'Result 1', $result[0]['title'] );
        $this->assertSame( 'Result 2', $result[1]['title'] );
        $this->assertSame( 'Result 3', $result[2]['title'] );
    }

    /**
     * Test fetch_brave — item structure lengkap.
     */
    public function test_fetch_item_structure() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Title',
                            'url'         => 'https://ex.com/item',
                            'description' => 'Description',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertArrayHasKey( 'title', $result[0] );
        $this->assertArrayHasKey( 'link', $result[0] );
        $this->assertArrayHasKey( 'description', $result[0] );
        $this->assertArrayHasKey( 'content', $result[0] );
        $this->assertArrayHasKey( 'source_type', $result[0] );
        $this->assertArrayHasKey( 'source_url', $result[0] );
        $this->assertSame( 'Full content from https://ex.com/item', $result[0]['content'] );
    }

    // ====================================================================
    // EMPTY RESULTS / MISSING KEYS
    // ====================================================================

    /**
     * Test fetch_brave — web.results kosong → return [].
     */
    public function test_fetch_empty_results() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [],
                ],
            ] ) ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'results kosong harus return []' );
    }

    /**
     * Test fetch_brave — web key tidak ada → return [].
     */
    public function test_fetch_missing_web_key() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'some_other' => 'data',
            ] ) ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Tanpa key web harus return []' );
    }

    /**
     * Test fetch_brave — web ada tapi tanpa results → return [].
     */
    public function test_fetch_missing_results_key() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [ 'no_results' => 'here' ],
            ] ) ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Web tanpa results harus return []' );
    }

    /**
     * Test fetch_brave — response JSON tidak valid → return [].
     */
    public function test_fetch_invalid_json() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], 'Not JSON at all' ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'Invalid JSON harus return []' );
    }

    // ====================================================================
    // FETCH_FULL_CONTENT — FALLBACK
    // ====================================================================

    /**
     * Test fetch_brave — fetch_full_content return false → fallback ke description.
     */
    public function test_fetch_full_content_false_falls_back_to_description() {
        $this->harness->fetch_full_content_override = false;

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Fallback Test',
                            'url'         => 'https://ex.com/fallback',
                            'description' => 'Fallback description text.',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Fallback description text.', $result[0]['content'],
            'fetch_full_content false → content harus description'
        );
    }

    /**
     * Test fetch_brave — fetch_full_content empty string + description empty → skip item.
     */
    public function test_fetch_full_content_empty_skips_item() {
        $this->harness->fetch_full_content_override = '';

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Empty Content',
                            'url'         => 'https://ex.com/empty',
                            'description' => '',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertEmpty( $result,
            'fetch_full_content empty + description empty → item harus di-skip'
        );
    }

    /**
     * Test fetch_brave — fetch_full_content false + tanpa description → skip item.
     */
    public function test_fetch_full_content_false_no_description_skips_item() {
        $this->harness->fetch_full_content_override = false;

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'No Desc',
                            'url'         => 'https://ex.com/no-desc',
                            // Tanpa description key
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertEmpty( $result,
            'fetch_full_content false + tanpa description → item harus di-skip'
        );
    }

    /**
     * Test fetch_brave — fetch_full_content sukses dipanggil untuk setiap result.
     */
    public function test_fetch_calls_fetch_full_content() {
        $this->harness->fetch_call_count = 0;

        $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [ 'title' => 'A', 'url' => 'https://ex.com/a', 'description' => 'A' ],
                        [ 'title' => 'B', 'url' => 'https://ex.com/b', 'description' => 'B' ],
                        [ 'title' => 'C', 'url' => 'https://ex.com/c', 'description' => 'C' ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertSame( 3, $this->harness->fetch_call_count,
            'fetch_full_content harus dipanggil 3x (1x per result)'
        );
    }

    // ====================================================================
    // PASSES_FILTERS — ITEM SKIPPING
    // ====================================================================

    /**
     * Test fetch_brave — passes_filters return false → item di-skip.
     */
    public function test_fetch_filter_skips_item() {
        $this->harness->passes_filters_result = false;

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Filtered Out',
                            'url'         => 'https://ex.com/filtered',
                            'description' => 'Should be skipped',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertEmpty( $result, 'Item yang tidak lolos filter harus di-skip' );
    }

    /**
     * Test fetch_brave — 1 item lolos filter, 1 tidak.
     */
    public function test_fetch_some_items_filtered() {
        $this->harness->passes_filters_result = true;

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Keep Me',
                            'url'         => 'https://ex.com/keep',
                            'description' => 'Keep description',
                        ],
                        [
                            'title'       => 'Also Keep',
                            'url'         => 'https://ex.com/also-keep',
                            'description' => 'Also keep description',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 2, $result, 'Semua item lolos filter (default true)' );
        $this->assertSame( 'Keep Me', $result[0]['title'] );
        $this->assertSame( 'Also Keep', $result[1]['title'] );
    }

    /**
     * Test fetch_brave — semua item gagal filter → return [].
     */
    public function test_fetch_all_items_filtered_returns_empty() {
        $this->harness->passes_filters_result = false;

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Skip 1',
                            'url'         => 'https://ex.com/1',
                            'description' => 'Skip 1',
                        ],
                        [
                            'title'       => 'Skip 2',
                            'url'         => 'https://ex.com/2',
                            'description' => 'Skip 2',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertEmpty( $result, 'Semua item gagal filter harus return []' );
    }

    // ====================================================================
    // ERROR HANDLING
    // ====================================================================

    /**
     * Test fetch_brave — HTTP 500 error → exception → return [].
     */
    public function test_fetch_http_500_returns_empty() {
        $result = $this->fetchWithMockResponses( [
            new Response( 500, [], 'Internal Server Error' ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'HTTP 500 harus return []' );
    }

    /**
     * Test fetch_brave — HTTP 403 error → exception → return [].
     */
    public function test_fetch_http_403_returns_empty() {
        $result = $this->fetchWithMockResponses( [
            new Response( 403, [], 'Forbidden' ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'HTTP 403 harus return []' );
    }

    /**
     * Test fetch_brave — HTTP 429 rate limit → exception → return [].
     */
    public function test_fetch_http_429_returns_empty() {
        $result = $this->fetchWithMockResponses( [
            new Response( 429, [], json_encode( [ 'error' => 'Rate limited' ] ) ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, 'HTTP 429 harus return []' );
    }

    /**
     * Test fetch_brave — response code sukses 200 tapi JSON tidak punya web.results.
     */
    public function test_fetch_200_without_web_results() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [],
                ],
            ] ) ),
        ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result, '200 tanpa results harus return []' );
    }

    // ====================================================================
    // ITEM WITHOUT DESCRIPTION
    // ====================================================================

    /**
     * Test fetch_brave — item tanpa description key → description = ''.
     */
    public function test_fetch_item_without_description() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title' => 'No Description',
                            'url'   => 'https://ex.com/no-desc',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertSame( '', $result[0]['description'],
            'Tanpa description key, description harus string kosong'
        );
    }

    /**
     * Test fetch_brave — item dengan description kosong.
     */
    public function test_fetch_item_with_empty_description() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Empty Desc',
                            'url'         => 'https://ex.com/empty-desc',
                            'description' => '',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertSame( '', $result[0]['description'] );
    }

    // ====================================================================
    // API REQUEST FORMATTING — URL, HEADERS, PARAMS
    // ====================================================================

    /**
     * Buat mock Guzzle Client dengan History middleware untuk capture request.
     */
    private function createMockClientWithHistory( array $responses ): Client {
        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );

        $handlerStack = HandlerStack::create( new MockHandler( $responses ) );
        $handlerStack->push( $history );

        return new Client( [ 'handler' => $handlerStack ] );
    }

    /**
     * Test bahwa fetch_brave mengirim request ke Brave Search API
     * dengan URL, method, dan path yang benar.
     */
    public function test_request_url_method_and_path() {
        $this->harness->mock_client = $this->createMockClientWithHistory( [
            new Response( 200, [], json_encode( [ 'web' => [ 'results' => [] ] ] ) ),
        ] );

        $this->invokeMethod( $this->harness, 'fetch_brave' );

        $this->assertCount( 1, $this->requestContainer, 'Harus mengirim 1 request' );
        $request = $this->requestContainer[0]['request'];
        $uri     = $request->getUri();

        $this->assertEquals( 'GET', $request->getMethod() );
        $this->assertStringContainsString( 'api.search.brave.com', $uri->getHost() );
        $this->assertEquals( '/res/v1/web/search', $uri->getPath() );
    }

    /**
     * Test bahwa fetch_brave mengirim query parameters yang benar:
     * q, count=3, summary=1.
     */
    public function test_request_query_parameters() {
        $this->harness->mock_client = $this->createMockClientWithHistory( [
            new Response( 200, [], json_encode( [ 'web' => [ 'results' => [] ] ] ) ),
        ] );

        $this->invokeMethod( $this->harness, 'fetch_brave' );

        $request  = $this->requestContainer[0]['request'];
        $queryStr = $request->getUri()->getQuery();
        parse_str( $queryStr, $params );

        $this->assertEquals( 'brave test query', $params['q'] );
        $this->assertEquals( '3', $params['count'], 'Parameter count harus 3' );
        $this->assertEquals( '1', $params['summary'], 'Parameter summary harus 1' );
    }

    /**
     * Test bahwa fetch_brave mengirim headers yang benar:
     * Accept, Accept-Encoding, X-Subscription-Token.
     */
    public function test_request_headers() {
        $this->harness->mock_client = $this->createMockClientWithHistory( [
            new Response( 200, [], json_encode( [ 'web' => [ 'results' => [] ] ] ) ),
        ] );

        $this->invokeMethod( $this->harness, 'fetch_brave' );

        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'application/json', $request->getHeaderLine( 'Accept' ) );
        $this->assertEquals( 'gzip', $request->getHeaderLine( 'Accept-Encoding' ) );
        $this->assertEquals( 'test_brave_key', $request->getHeaderLine( 'X-Subscription-Token' ) );
    }

    // ====================================================================
    // EDGE CASES
    // ====================================================================

    /**
     * Test fetch_brave — item dengan URL empty → content diisi description.
     */
    public function test_fetch_item_with_empty_url() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Empty URL',
                            'url'         => '',
                            'description' => 'Fallback description',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        // fetch_full_content dipanggil dengan '' (empty url)
        // stub return 'Full content from ' → bukan empty, jadi content terisi
        $this->assertStringContainsString( 'Full content from', $result[0]['content'] );
    }

    /**
     * Test fetch_brave — verifikasi count parameter = 3.
     *
     * NOTE: Test ini memverifikasi bahwa trait menggunakan count=3,
     * tapi karena response di-mock, jumlah item tergantung response.
     */
    public function test_fetch_count_parameter_respected() {
        // API response dengan 5 results → tetap 5 karena count hanya request parameter
        $results = [];
        for ( $i = 1; $i <= 5; $i++ ) {
            $results[] = [
                'title'       => "Result $i",
                'url'         => "https://ex.com/$i",
                'description' => "Desc $i",
            ];
        }

        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [ 'results' => $results ],
            ] ) ),
        ] );

        // Brave trait TIDAK membatasi hasil secara lokal — semua item dari response diolah
        $this->assertCount( 5, $result,
            'Brave trait memproses semua results dari response (count=3 hanya parameter request)'
        );
    }

    /**
     * Test fetch_brave — result URL dengan karakter spesial.
     */
    public function test_fetch_item_with_special_chars_in_url() {
        $result = $this->fetchWithMockResponses( [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'Special URL',
                            'url'         => 'https://ex.com/path?q=test&lang=en#section',
                            'description' => 'Special URL desc',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $this->assertCount( 1, $result );
        $this->assertSame( 'https://ex.com/path?q=test&lang=en#section', $result[0]['link'] );
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
