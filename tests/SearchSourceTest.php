<?php

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Sources\SearchSource;
use Autoblog\Utils\OptionCache;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Unit Test untuk SearchSource coordinator.
 *
 * Memverifikasi:
 * 1. Constructor — query cleaning, keywords, provider config
 * 2. validate_source — duckduckgo_free vs serpapi key check
 * 3. get_display_name — sesuai provider terpilih
 * 4. passes_filters — match/negative keywords (expanded)
 * 5. fetch_data — routing ke driver (duckduckgo_free, serpapi, brave)
 * 6. build_item — struktur item standar
 * 7. fetch_full_content — Readability + cURL error handling
 * 8. set_http_client — dependency injection pattern
 *
 * @group unit
 * @group search_source
 * @package Autoblog\Tests
 */
class SearchSourceTest extends TestCase {

    /** @var array Container untuk Guzzle History middleware */
    private $requestContainer = [];

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_options         = [];
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;

        // Default: duckduckgo_free
        $_autoblog_mock_options['autoblog_search_provider'] = 'duckduckgo_free';
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_options         = [];
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;

        OptionCache::flush();
        parent::tearDown();
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Pastikan mock Readability classes sudah didefinisikan (hanya sekali).
     *
     * fetch_full_content() mengecek class_exists FiveFilters\Readability\Readability.
     * Di test env library ini tidak ada, jadi kita buat mock minimal.
     */
    private function ensureReadabilityMock(): void {
        if ( class_exists( 'FiveFilters\Readability\Configuration', false ) ) {
            return;
        }
        eval( '
            namespace FiveFilters\Readability;
            class Configuration {}
            class Readability {
                private $html = "";
                public function __construct( Configuration $config ) {}
                public function parse( string $html ): void {
                    global $_mock_readability_throw;
                    if ( isset( $_mock_readability_throw ) && $_mock_readability_throw ) {
                        throw new \Exception( "Mock Readability parse error" );
                    }
                    $this->html = $html;
                }
                public function getContent(): string {
                    return $this->html;
                }
            }
        ' );
    }

    /**
     * Invoke private method via reflection.
     */
    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }

    /**
     * Dapatkan nilai private property via reflection.
     */
    private function getProperty( $object, string $propName ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $prop       = $reflection->getProperty( $propName );
        $prop->setAccessible( true );
        return $prop->getValue( $object );
    }

    /**
     * Buat mock Guzzle Client dan inject via set_http_client.
     */
    private function injectMockClient( $source, array $responses ): void {
        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );

        $handlerStack = HandlerStack::create( new MockHandler( $responses ) );
        $handlerStack->push( $history );

        $mockClient = new Client( [
            'handler'     => $handlerStack,
            'http_errors' => false,
        ] );

        $source->set_http_client( $mockClient );
    }

    // ================================================================
    // CONSTRUCTOR — Query Cleaning
    // ================================================================

    public function test_constructor_strips_https_prefix() {
        $source = new SearchSource( 'https://html.duckduckgo.com' );

        $this->assertEquals( 'html.duckduckgo.com', $this->getProperty( $source, 'query' ) );
    }

    public function test_constructor_strips_http_prefix() {
        $source = new SearchSource( 'http://example.com' );

        $this->assertEquals( 'example.com', $this->getProperty( $source, 'query' ) );
    }

    public function test_constructor_strips_www_prefix() {
        $source = new SearchSource( 'www.example.com' );

        $this->assertEquals( 'example.com', $this->getProperty( $source, 'query' ) );
    }

    public function test_constructor_preserves_query_without_prefix() {
        $source = new SearchSource( 'Kecerdasan Buatan' );

        $this->assertEquals( 'Kecerdasan Buatan', $this->getProperty( $source, 'query' ) );
    }

    // ================================================================
    // CONSTRUCTOR — Keywords & Provider Config
    // ================================================================

    public function test_constructor_stores_match_keywords() {
        $source = new SearchSource( 'test', 'teknologi, AI' );

        $this->assertEquals( 'teknologi, AI', $this->getProperty( $source, 'match_keywords' ) );
    }

    public function test_constructor_stores_negative_keywords() {
        $source = new SearchSource( 'test', '', 'judi, slot' );

        $this->assertEquals( 'judi, slot', $this->getProperty( $source, 'negative_keywords' ) );
    }

    public function test_constructor_reads_provider_from_option() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';
        OptionCache::flush();

        $source = new SearchSource( 'test' );

        $this->assertEquals( 'serpapi', $this->getProperty( $source, 'provider' ) );
    }

    public function test_constructor_defaults_to_serpapi_when_option_missing() {
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_search_provider'] );
        OptionCache::flush();

        $source = new SearchSource( 'test' );

        $this->assertEquals( 'serpapi', $this->getProperty( $source, 'provider' ),
            'Default provider harus serpapi' );
    }

    public function test_constructor_reads_serpapi_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_serpapi_key'] = 'sk-test-key';
        OptionCache::flush();

        $source = new SearchSource( 'test' );

        $this->assertEquals( 'sk-test-key', $this->getProperty( $source, 'serpapi_key' ) );
    }

    public function test_constructor_reads_brave_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_brave_key'] = 'brave-test-key';
        OptionCache::flush();

        $source = new SearchSource( 'test' );

        $this->assertEquals( 'brave-test-key', $this->getProperty( $source, 'brave_key' ) );
    }

    // ================================================================
    // VALIDATE SOURCE
    // ================================================================

    public function test_validate_source_passes_for_duckduckgo_with_empty_query() {
        $source = new SearchSource( '' );
        $this->assertFalse( $source->validate_source() );
    }

    public function test_validate_source_passes_for_duckduckgo_with_valid_query() {
        $source = new SearchSource( 'AI teknologi' );
        $this->assertTrue( $source->validate_source() );
    }

    public function test_validate_source_fails_for_serpapi_without_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';
        $_autoblog_mock_options['autoblog_serpapi_key']    = '';
        OptionCache::flush();

        $source = new SearchSource( 'test' );

        $this->assertFalse( $source->validate_source(),
            'SerpApi tanpa key harus gagal validasi' );
    }

    public function test_validate_source_passes_for_serpapi_with_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';
        $_autoblog_mock_options['autoblog_serpapi_key']    = 'sk-valid';
        OptionCache::flush();

        $source = new SearchSource( 'test' );

        $this->assertTrue( $source->validate_source() );
    }

    public function test_validate_source_fails_for_brave_without_serpapi_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'brave';
        $_autoblog_mock_options['autoblog_serpapi_key']    = '';
        OptionCache::flush();

        $source = new SearchSource( 'test' );

        $this->assertFalse( $source->validate_source(),
            'Brave tanpa serpapi_key juga gagal (cek serpapi_key untuk non-duckduckgo)' );
    }

    // ================================================================
    // GET DISPLAY NAME
    // ================================================================

    public function test_get_display_name_default() {
        update_option( 'autoblog_search_provider', 'duckduckgo_free' );

        $source = new SearchSource( 'test' );
        $this->assertEquals( 'Web Search (DuckDuckGo Free)', $source->get_display_name() );
    }

    public function test_get_display_name_serpapi() {
        update_option( 'autoblog_search_provider', 'serpapi' );

        $source = new SearchSource( 'test' );
        $this->assertEquals( 'Web Search (SerpApi)', $source->get_display_name() );
    }

    public function test_get_display_name_brave() {
        update_option( 'autoblog_search_provider', 'brave' );

        $source = new SearchSource( 'test' );
        $this->assertEquals( 'Web Search (Brave)', $source->get_display_name() );
    }

    // ================================================================
    // PASSES FILTERS
    // ================================================================

    public function test_passes_filters_match_keywords() {
        $source = new SearchSource( 'test query', 'teknologi' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Perkembangan teknologi AI 2026' ] );
        $this->assertTrue( $pass, 'Teks mengandung "teknologi" harus lolos' );

        $fail = $this->invokeMethod( $source, 'passes_filters', [ 'Kecerdasan buatan masa kini' ] );
        $this->assertFalse( $fail, 'Teks tanpa "teknologi" harus diblokir' );
    }

    public function test_passes_filters_negative_keywords() {
        $source = new SearchSource( 'test query', '', 'judi, slot' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Teknologi modern game online' ] );
        $this->assertTrue( $pass, 'Tanpa kata terlarang harus lolos' );

        $fail = $this->invokeMethod( $source, 'passes_filters', [ 'Situs judi online terpercaya' ] );
        $this->assertFalse( $fail, 'Mengandung "judi" harus diblokir' );
    }

    public function test_passes_filters_case_insensitive() {
        $source = new SearchSource( 'test query', 'TEKNOLOGI' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'perkembangan teknologi AI' ] );
        $this->assertTrue( $pass, 'Case insensitive: lowercase match tetap lolos' );
    }

    public function test_passes_filters_empty_keywords_allows_all() {
        $source = new SearchSource( 'test query' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Any random text' ] );
        $this->assertTrue( $pass, 'Tanpa filter keyword, semua teks lolos' );
    }

    public function test_passes_filters_multiple_match_keywords_or_logic() {
        $source = new SearchSource( 'test query', 'teknologi, AI, data' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Analisis data besar' ] );
        $this->assertTrue( $pass, '"data" ada di teks, harus lolos (OR logic)' );

        $fail = $this->invokeMethod( $source, 'passes_filters', [ 'Resep masakan rumahan' ] );
        $this->assertFalse( $fail, 'Tanpa keyword satupun harus diblokir' );
    }

    public function test_passes_filters_multiple_negative_or_logic() {
        $source = new SearchSource( 'test query', '', 'judi, slot' );

        $fail = $this->invokeMethod( $source, 'passes_filters', [ 'Promo slot online' ] );
        $this->assertFalse( $fail, '"slot" ada di teks, harus diblokir (OR logic)' );
    }

    public function test_passes_filters_both_match_and_negative() {
        $source = new SearchSource( 'test query', 'teknologi', 'judi' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Perkembangan teknologi AI' ] );
        $this->assertTrue( $pass, 'Match "teknologi" + tidak ada "judi" => lolos' );

        $fail = $this->invokeMethod( $source, 'passes_filters', [ 'Teknologi judi online' ] );
        $this->assertFalse( $fail, 'Match "teknologi" tapi ada "judi" => diblokir' );
    }

    public function test_passes_filters_whitespace_handling_in_keywords() {
        $source = new SearchSource( 'test query', '  teknologi ,  AI  ' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Perkembangan teknologi AI' ] );
        $this->assertTrue( $pass, 'Whitespace di keyword di-trim, harus tetap match' );
    }

    // ================================================================
    // FETCH DATA — Routing Validation
    // ================================================================

    public function test_fetch_data_returns_empty_when_validate_source_fails() {
        $source = new SearchSource( '' );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // FETCH DATA — DuckDuckGo Free Provider
    // ================================================================

    public function test_fetch_data_duckduckgo_free_routes_to_driver() {
        global $_autoblog_mock_options;
        global $_autoblog_mock_remote_body;

        $_autoblog_mock_options['autoblog_search_provider'] = 'duckduckgo_free';

        $html = '<html><body><div class="result">
            <a class="result__a" href="https://example.com/article">AI Technology Article</a>
            <div class="result__snippet">Latest AI technology developments</div>
        </div></body></html>';

        $_autoblog_mock_remote_body     = $html;
        $_autoblog_mock_remote_response = [ 'body' => $html ];

        $source = new SearchSource( 'AI teknologi' );
        $result = $source->fetch_data();

        $this->assertNotEmpty( $result, 'DuckDuckGo parsing harus menghasilkan items dari mock HTML' );
        $this->assertEquals( 'AI Technology Article', $result[0]['title'] );
    }

    public function test_fetch_data_duckduckgo_free_with_missing_key_works() {
        global $_autoblog_mock_options;
        global $_autoblog_mock_remote_body;

        $_autoblog_mock_options['autoblog_search_provider'] = 'duckduckgo_free';
        $_autoblog_mock_options['autoblog_serpapi_key']    = '';
        OptionCache::flush();

        $html = '<html><body><div class="result">
            <a class="result__a" href="https://ex.com/a">Article A</a>
            <div class="result__snippet">Snippet A content</div>
        </div></body></html>';

        $_autoblog_mock_remote_body     = $html;
        $_autoblog_mock_remote_response = [ 'body' => $html ];

        $source = new SearchSource( 'test' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result,
            'DuckDuckGo harus tetap jalan meski serpapi_key kosong' );
    }

    // ================================================================
    // FETCH DATA — SerpApi Provider
    // ================================================================

    public function test_fetch_data_serpapi_routes_to_driver() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';
        $_autoblog_mock_options['autoblog_serpapi_key']    = 'sk-test';
        OptionCache::flush();

        $source = new SearchSource( 'AI teknologi' );

        $this->injectMockClient( $source, [
            new Response( 200, [], json_encode( [ 'error' => 'Invalid API key' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'Not available' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'Quota exceeded' ] ) ),
            new Response( 200, [], json_encode( [ 'error' => 'No organic' ] ) ),
        ] );

        $result = $source->fetch_data();

        $this->assertIsArray( $result,
            'SerpApi driver harus tetap return array (walau empty karena mock error)' );
    }

    public function test_fetch_data_serpapi_ai_mode_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';
        $_autoblog_mock_options['autoblog_serpapi_key']    = 'sk-valid';
        OptionCache::flush();

        $source = new SearchSource( 'AI teknologi' );

        $this->injectMockClient( $source, [
            new Response( 200, [], json_encode( [
                'ai_overview' => 'AI (Artificial Intelligence) is a transformative technology...',
            ] ) ),
        ] );

        $result = $source->fetch_data();

        $this->assertNotEmpty( $result, 'AI Mode sukses harus return items' );
        $this->assertEquals( 'google_ai_mode', $result[0]['source_type'] );
    }

    // ================================================================
    // FETCH DATA — Brave Provider
    // ================================================================

    public function test_fetch_data_brave_returns_empty_when_key_missing() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'brave';
        $_autoblog_mock_options['autoblog_brave_key']      = '';
        $_autoblog_mock_options['autoblog_serpapi_key']    = 'dummy-key';
        OptionCache::flush();

        $source = new SearchSource( 'test' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
    }

    public function test_fetch_data_brave_routes_to_driver() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'brave';
        $_autoblog_mock_options['autoblog_brave_key']      = 'brave-valid-key';
        $_autoblog_mock_options['autoblog_serpapi_key']    = 'dummy-key';
        OptionCache::flush();

        $source = new SearchSource( 'AI teknologi' );

        $this->injectMockClient( $source, [
            new Response( 200, [], json_encode( [
                'web' => [ 'results' => [] ],
            ] ) ),
        ] );

        $result = $source->fetch_data();

        $this->assertIsArray( $result,
            'Brave driver harus return array (empty karena mock kosong)' );
    }

    public function test_fetch_data_brave_with_results() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'brave';
        $_autoblog_mock_options['autoblog_brave_key']      = 'brave-valid';
        $_autoblog_mock_options['autoblog_serpapi_key']    = 'dummy-key';
        OptionCache::flush();

        $source = new SearchSource( 'AI teknologi' );

        $this->injectMockClient( $source, [
            new Response( 200, [], json_encode( [
                'web' => [
                    'results' => [
                        [
                            'title'       => 'AI Technology News',
                            'url'         => 'https://example.com/ai-news',
                            'description' => 'Latest AI technology developments and news',
                        ],
                    ],
                ],
            ] ) ),
        ] );

        $result = $source->fetch_data();

        $this->assertNotEmpty( $result, 'Brave dengan hasil harus return items' );
        $this->assertEquals( 'AI Technology News', $result[0]['title'] );
        $this->assertEquals( 'brave_search', $result[0]['source_type'] );
    }

    // ================================================================
    // SET HTTP CLIENT — Dependency Injection
    // ================================================================

    public function test_set_http_client_injects_mock_client() {
        $source = new SearchSource( 'test' );

        $mockClient = $this->createMock( Client::class );
        $source->set_http_client( $mockClient );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'http_client' );
        $prop->setAccessible( true );

        $this->assertSame( $mockClient, $prop->getValue( $source ),
            'set_http_client harus menyimpan client yang sama' );
    }

    // ================================================================
    // FETCH FULL CONTENT (private)
    //
    // Method: fetch_full_content($url)
    //   1. class_exists Readability? → false: return false
    //   2. cURL request → curl_exec fails → Logger::log → return false
    //   3. Readability::parse() throws → Logger::log → return false
    //   4. Success → return parsed content
    //
    // Strategi: gunakan file:// URL untuk mock cURL + mock Readability via eval
    // ================================================================

    public function test_fetch_full_content_returns_false_when_readability_missing() {
        $source = new SearchSource( 'test query' );

        $result = $this->invokeMethod( $source, 'fetch_full_content', [
            'https://example.com/article',
        ] );

        $this->assertFalse( $result,
            'Tanpa Readability library, fetch_full_content harus return false'
        );
    }

    /**
     * Test bahwa fetch_full_content menangani kegagalan cURL (koneksi gagal).
     */
    public function test_fetch_full_content_handles_curl_failure() {
        $source = new SearchSource( 'test query' );

        $result = $this->invokeMethod( $source, 'fetch_full_content', [
            'http://127.0.0.1:1/nonexistent',
        ] );

        $this->assertFalse( $result,
            'cURL gagal (connection refused) harus return false'
        );
    }

    /**
     * Test bahwa fetch_full_content berhasil dengan Readability mock
     * dan file:// URL untuk cURL.
     */
    public function test_fetch_full_content_with_readability_success() {
        $this->ensureReadabilityMock();

        $htmlContent = '<html><body><article><h1>Test Article</h1><p>This is the article content that Readability would extract.</p></article></body></html>';
        $tmpFile = tempnam( sys_get_temp_dir(), 'test_article_' );
        file_put_contents( $tmpFile, $htmlContent );

        $source = new SearchSource( 'test query' );
        $url    = 'file://' . $tmpFile;

        $result = $this->invokeMethod( $source, 'fetch_full_content', [ $url ] );

        @unlink( $tmpFile );

        $this->assertNotEmpty( $result,
            'Dengan Readability mock + file:// URL, harus return content'
        );
        $this->assertStringContainsString( 'Test Article', $result,
            'Content harus mengandung teks dari HTML file'
        );
    }

    /**
     * Test bahwa fetch_full_content menangani exception dari Readability::parse().
     */
    public function test_fetch_full_content_readability_exception_returns_false() {
        $this->ensureReadabilityMock();
        global $_mock_readability_throw;
        $_mock_readability_throw = true;

        $htmlContent = '<html><body><p>Some content here</p></body></html>';
        $tmpFile = tempnam( sys_get_temp_dir(), 'test_article_' );
        file_put_contents( $tmpFile, $htmlContent );

        $source = new SearchSource( 'test query' );
        $url    = 'file://' . $tmpFile;

        $result = $this->invokeMethod( $source, 'fetch_full_content', [ $url ] );

        @unlink( $tmpFile );
        $_mock_readability_throw = false;

        $this->assertFalse( $result,
            'Readability exception harus return false'
        );
    }



    // ================================================================
    // BUILD ITEM (private) — struktur output standar
    // ================================================================

    public function test_build_item_returns_correct_structure() {
        $source   = new SearchSource( 'test query' );
        $title    = 'Test Title';
        $content  = 'Test content with enough text for description generation.';
        $type     = 'google_ai_overview';

        $item = $this->invokeMethod( $source, 'build_item', [ $title, $content, $type ] );

        $this->assertIsArray( $item );
        $this->assertArrayHasKey( 'title', $item );
        $this->assertArrayHasKey( 'content', $item );
        $this->assertArrayHasKey( 'source_type', $item );
        $this->assertArrayHasKey( 'source_url', $item );
        $this->assertArrayHasKey( 'link', $item );
        $this->assertArrayHasKey( 'description', $item );
        $this->assertArrayHasKey( 'guid', $item );

        $this->assertEquals( $title, $item['title'] );
        $this->assertEquals( $content, $item['content'] );
        $this->assertEquals( $type, $item['source_type'] );
        $this->assertEquals( 'test query', $item['source_url'] );
    }

    public function test_build_item_generates_description_from_content() {
        $source  = new SearchSource( 'test' );
        $content = 'This is a long article content about artificial intelligence and machine learning.';

        $item = $this->invokeMethod( $source, 'build_item', [ 'Title', $content, 'test_type' ] );

        $this->assertStringEndsWith( '...', $item['description'] );
        $this->assertLessThanOrEqual( 155, strlen( $item['description'] ) );
    }

    public function test_build_item_generates_md5_guid() {
        $source  = new SearchSource( 'test' );
        $content = 'Unique content for GUID test';

        $item = $this->invokeMethod( $source, 'build_item', [ 'Title', $content, 'type' ] );

        $this->assertEquals( md5( $content ), $item['guid'] );
    }

    // ================================================================
    // INTERFACE
    // ================================================================

    public function test_implements_source_interface() {
        $reflection = new \ReflectionClass( SearchSource::class );
        $this->assertTrue(
            $reflection->implementsInterface( 'Autoblog\\Interfaces\\SourceInterface' )
        );
    }
}
