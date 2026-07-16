<?php
/**
 * Unit Test untuk Autoblog\Utils\AIEmbeddingTrait.
 *
 * AIEmbeddingTrait berisi 11 method embedding per-provider:
 * - sanitize_utf8()          — utility: validasi UTF-8
 * - create_embedding()           — public dispatcher single embedding
 * - create_embeddings_batch()    — public dispatcher batch embedding
 * - dispatch_embedding()         — routing ke per-provider
 * - openai_embedding()           — OpenAI text-embedding-3-small
 * - google_embedding()           — Google Gemini embedding-001
 * - huggingface_embedding()      — HuggingFace feature-extraction
 * - openai_embeddings_batch()    — Batch: kirim semua teks dalam 1 request
 * - google_embeddings_batch()    — Batch: sequential dengan delay
 * - sequential_embeddings()      — Fallback sequential
 * - build_batch_result()         — Helper batch result
 *
 * Harness menggunakan both AICompletionTrait (untuk get_keys_pool)
 * dan AIEmbeddingTrait. Menyediakan mock Guzzle Client + request_with_backoff().
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      utils
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Autoblog\Utils\OptionCache;

// ========================================================================
// TEST HARNESS
// ========================================================================

class AIEmbeddingTestHarness {
    use \Autoblog\Utils\AICompletionTrait;
    use \Autoblog\Utils\AIEmbeddingTrait;

    /** @var Client|null Mock Guzzle Client */
    public $client = null;

    /** @var string API Keys (dibutuhkan oleh trait) */
    public $openai_key     = '';
    public $gemini_key     = '';
    public $hf_key         = '';

    /**
     * Delegasi request_with_backoff ke Guzzle client langsung.
     */
    private function request_with_backoff( $method, $url, $options = [], $max_retries = 2 ) {
        if ( $this->client === null ) {
            throw new \RuntimeException( 'Mock Guzzle Client belum diset di harness' );
        }
        return $this->client->request( $method, $url, $options );
    }
}

// ========================================================================
// TEST CLASS
// ========================================================================

class AIEmbeddingTest extends TestCase {

    /** @var array Container untuk Guzzle History middleware */
    private $requestContainer = [];

    /** @var AIEmbeddingTestHarness */
    private $harness;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        $this->harness = new AIEmbeddingTestHarness();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // HELPER: Create mock Guzzle Client with responses
    // ====================================================================

    private function setMockClient( array $responses ): void {
        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );

        $handlerStack = HandlerStack::create( new MockHandler( $responses ) );
        $handlerStack->push( $history );

        $this->harness->client = new Client( [ 'handler' => $handlerStack ] );
    }

    /**
     * Panggil private method via reflection.
     */
    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }

    // ====================================================================
    // SANITIZE_UTF8 (private utility)
    // ====================================================================

    public function test_sanitize_utf8_preserves_normal_text() {
        $text   = 'Hello World! Normal text 123.';
        $result = $this->invokeMethod( $this->harness, 'sanitize_utf8', [ $text ] );
        $this->assertSame( $text, $result );
    }

    public function test_sanitize_utf8_strips_null_bytes() {
        $input  = "Hello\x00World\x00\x00Test";
        $result = $this->invokeMethod( $this->harness, 'sanitize_utf8', [ $input ] );
        $this->assertSame( 'HelloWorldTest', $result, 'NULL bytes harus di-strip' );
    }

    public function test_sanitize_utf8_strips_control_chars() {
        // 0x01 (SOH), 0x02 (STX), 0x1F (US) — harus dihapus, newline/tab tetap
        $input  = "Line1\x01\x02Line2\x1FEnd";
        $result = $this->invokeMethod( $this->harness, 'sanitize_utf8', [ $input ] );
        $this->assertSame( 'Line1Line2End', $result, 'Control chars harus di-strip' );
    }

    public function test_sanitize_utf8_preserves_newline_and_tab() {
        $input  = "Line1\n\tLine2";
        $result = $this->invokeMethod( $this->harness, 'sanitize_utf8', [ $input ] );
        $this->assertSame( "Line1\n\tLine2", $result, 'Newline dan tab harus tetap' );
    }

    public function test_sanitize_utf8_handles_empty_text() {
        $result = $this->invokeMethod( $this->harness, 'sanitize_utf8', [ '' ] );
        $this->assertSame( '', $result );
    }

    public function test_sanitize_utf8_handles_utf8_multibyte() {
        $text   = 'Hëllö Wörld — café résumé français 日本語';
        $result = $this->invokeMethod( $this->harness, 'sanitize_utf8', [ $text ] );
        $this->assertSame( $text, $result, 'Multibyte UTF-8 harus tetap' );
    }

    // ====================================================================
    // CREATE_EMBEDDING (public dispatcher)
    // ====================================================================

    public function test_create_embedding_returns_false_on_empty_text() {
        $result = $this->harness->create_embedding( '' );
        $this->assertFalse( $result, 'Text kosong harus return false' );
    }

    public function test_create_embedding_defaults_to_openai() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [ [ 'embedding' => [ 0.1, 0.2, 0.3 ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->create_embedding( 'Test text' );

        $this->assertIsArray( $result );
        $this->assertEquals( [ 0.1, 0.2, 0.3 ], $result );

        // Pastikan request ke OpenAI
        $url = (string) $this->requestContainer[0]['request']->getUri();
        $this->assertStringContainsString( 'api.openai.com', $url );
    }

    public function test_create_embedding_sanitizes_text() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [ [ 'embedding' => [ 0.5 ] ] ],
            ] ) ),
        ] );

        // Text with null byte — should be sanitized before sending
        $this->harness->create_embedding( "Clean\x00Text" );

        $body = json_decode( (string) $this->requestContainer[0]['request']->getBody(), true );
        $this->assertEquals( 'CleanText', $body['input'],
            'Text harus di-sanitasi (null byte dihapus) sebelum dikirim'
        );
    }

    // ====================================================================
    // OPENAI_EMBEDDING (private)
    // ====================================================================

    public function test_openai_embedding_returns_false_when_key_missing() {
        $result = $this->invokeMethod( $this->harness, 'openai_embedding', [ 'test' ] );
        $this->assertFalse( $result );
    }

    public function test_openai_embedding_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [ [ 'embedding' => [ 0.1, 0.2, 0.3, 0.4 ] ] ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'openai_embedding', [ 'Hello world' ] );
        $this->assertEquals( [ 0.1, 0.2, 0.3, 0.4 ], $result );
    }

    public function test_openai_embedding_key_rotation() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => "sk-fail\nsk-success",
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 401, [], 'Unauthorized' ),
            new Response( 200, [], json_encode( [
                'data' => [ [ 'embedding' => [ 0.9 ] ] ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'openai_embedding', [ 'test' ] );
        $this->assertEquals( [ 0.9 ], $result, 'Key rotation harus fallback ke key kedua' );
        $this->assertCount( 2, $this->requestContainer );
    }

    public function test_openai_embedding_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-format-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [ [ 'embedding' => [ 0.5 ] ] ],
            ] ) ),
        ] );

        $this->invokeMethod( $this->harness, 'openai_embedding', [ 'Embed me' ] );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertEquals( 'api.openai.com', $request->getUri()->getHost() );
        $this->assertEquals( '/v1/embeddings', $request->getUri()->getPath() );
        $this->assertEquals( 'Bearer sk-format-test', $request->getHeaderLine( 'Authorization' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'Embed me', $body['input'] );
        $this->assertEquals( 'text-embedding-3-small', $body['model'] );
    }

    // ====================================================================
    // GOOGLE_EMBEDDING (private)
    // ====================================================================

    public function test_google_embedding_returns_false_when_key_missing() {
        $result = $this->invokeMethod( $this->harness, 'google_embedding', [ 'test' ] );
        $this->assertFalse( $result );
    }

    public function test_google_embedding_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'embedding' => [ 'values' => [ 0.01, 0.02, 0.03 ] ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'google_embedding', [ 'Gemini text', 'models/gemini-embedding-001' ] );
        $this->assertEquals( [ 0.01, 0.02, 0.03 ], $result );
    }

    public function test_google_embedding_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-format',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'embedding' => [ 'values' => [ 0.5 ] ],
            ] ) ),
        ] );

        $this->invokeMethod( $this->harness, 'google_embedding', [ 'Hello', 'models/gemini-embedding-001' ] );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $url = (string) $request->getUri();
        $this->assertStringContainsString( 'generativelanguage.googleapis.com', $url );
        $this->assertStringContainsString( 'v1beta', $url );
        $this->assertStringContainsString( 'gemini-embedding-001', $url );
        $this->assertStringContainsString( 'AIza-format', $url );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'Hello', $body['content']['parts'][0]['text'] );
    }

    // ====================================================================
    // HUGGINGFACE_EMBEDDING (via $this->client->post())
    // ====================================================================

    public function test_huggingface_embedding_returns_false_when_key_missing() {
        $result = $this->invokeMethod( $this->harness, 'huggingface_embedding', [ 'test' ] );
        $this->assertFalse( $result );
    }

    public function test_huggingface_embedding_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf_test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 0.1, 0.2, 0.3, 0.4 ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'huggingface_embedding', [ 'HF test' ] );
        $this->assertEquals( [ 0.1, 0.2, 0.3, 0.4 ], $result );
    }

    public function test_huggingface_embedding_validates_array_response() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf_test',
        ];
        OptionCache::flush();

        // HF returns array of arrays (batched), not flat array — must check is_numeric($body[0])
        $this->setMockClient( [
            new Response( 200, [], json_encode( [ [ 0.1, 0.2 ], [ 0.3, 0.4 ] ] ) ),
        ] );

        // First element is an array, not numeric → should be rejected
        $result = $this->invokeMethod( $this->harness, 'huggingface_embedding', [ 'test' ] );
        $this->assertFalse( $result, 'Nested array response harus direject' );
    }

    public function test_huggingface_embedding_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf_format',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 0.5 ] ) ),
        ] );

        $this->invokeMethod( $this->harness, 'huggingface_embedding', [ 'HF input' ] );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertStringContainsString( 'api-inference.huggingface.co', (string) $request->getUri() );
        $this->assertStringContainsString( 'all-MiniLM-L6-v2', (string) $request->getUri() );
        $this->assertEquals( 'Bearer hf_format', $request->getHeaderLine( 'Authorization' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'HF input', $body['inputs'] );
        $this->assertTrue( $body['options']['wait_for_model'] );
    }

    // ====================================================================
    // DISPATCH_EMBEDDING (private router)
    // ====================================================================

    public function test_dispatch_embedding_routes_to_openai() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [ [ 'embedding' => [ 0.1 ] ] ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'dispatch_embedding', [ 'text', 'openai' ] );
        $this->assertIsArray( $result );
        $this->assertStringContainsString( 'api.openai.com', (string) $this->requestContainer[0]['request']->getUri() );
    }

    public function test_dispatch_embedding_routes_to_gemini() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'embedding' => [ 'values' => [ 0.1 ] ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'dispatch_embedding', [ 'text', 'gemini' ] );
        $this->assertIsArray( $result );
        $this->assertStringContainsString( 'generativelanguage.googleapis.com', (string) $this->requestContainer[0]['request']->getUri() );
    }

    public function test_dispatch_embedding_routes_to_gemini_001() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'embedding' => [ 'values' => [ 0.1 ] ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'dispatch_embedding', [ 'text', 'gemini_001' ] );
        $this->assertIsArray( $result );
    }

    public function test_dispatch_embedding_routes_to_hf() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 0.1 ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'dispatch_embedding', [ 'text', 'hf' ] );
        $this->assertIsArray( $result );
    }

    public function test_dispatch_embedding_unknown_provider_defaults_to_openai() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [ [ 'embedding' => [ 0.1 ] ] ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'dispatch_embedding', [ 'text', 'unknown_provider' ] );
        $this->assertIsArray( $result, 'Unknown provider harus fallback ke openai' );
        $this->assertStringContainsString( 'api.openai.com', (string) $this->requestContainer[0]['request']->getUri() );
    }

    // ====================================================================
    // CREATE_EMBEDDINGS_BATCH (public dispatcher)
    // ====================================================================

    public function test_create_embeddings_batch_empty_returns_empty() {
        $result = $this->harness->create_embeddings_batch( [] );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_create_embeddings_batch_defaults_to_openai() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [
                    [ 'index' => 0, 'embedding' => [ 0.1, 0.2 ] ],
                    [ 'index' => 1, 'embedding' => [ 0.3, 0.4 ] ],
                ],
            ] ) ),
        ] );

        $result = $this->harness->create_embeddings_batch( [ 'Text A', 'Text B' ] );

        $this->assertCount( 2, $result );
        $this->assertEquals( [ 0.1, 0.2 ], $result[0]['vector'] );
        $this->assertEquals( [ 0.3, 0.4 ], $result[1]['vector'] );
        $this->assertEquals( 0, $result[0]['index'] );
        $this->assertEquals( 'Text A', $result[0]['text'] );
    }

    public function test_create_embeddings_batch_empty_provider_uses_openai() {
        $texts  = [ 'Hello' ];
        $result = $this->harness->create_embeddings_batch( $texts, '' );

        $this->assertCount( 1, $result );
        $this->assertArrayHasKey( 'text', $result[0] );
        $this->assertArrayHasKey( 'vector', $result[0] );
        $this->assertArrayHasKey( 'index', $result[0] );
    }

    // ====================================================================
    // OPENAI_EMBEDDINGS_BATCH (private)
    // ====================================================================

    public function test_openai_embeddings_batch_returns_result_when_no_keys() {
        $result = $this->invokeMethod( $this->harness, 'openai_embeddings_batch', [ [ 'A', 'B', 'C' ] ] );
        $this->assertCount( 3, $result );
        $this->assertNull( $result[0]['vector'], 'Tanpa key vector harus null' );
    }

    public function test_openai_embeddings_batch_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [
                    [ 'index' => 0, 'embedding' => [ 0.01, 0.02 ] ],
                    [ 'index' => 1, 'embedding' => [ 0.03, 0.04 ] ],
                    [ 'index' => 2, 'embedding' => [ 0.05, 0.06 ] ],
                ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'openai_embeddings_batch', [ [ 'A', 'B', 'C' ] ] );

        $this->assertCount( 3, $result );
        $this->assertEquals( [ 0.01, 0.02 ], $result[0]['vector'] );
        $this->assertEquals( [ 0.03, 0.04 ], $result[1]['vector'] );
        $this->assertEquals( [ 0.05, 0.06 ], $result[2]['vector'] );
        $this->assertEquals( 0, $result[0]['index'] );
        $this->assertEquals( 'A', $result[0]['text'] );
    }

    public function test_openai_embeddings_batch_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-batch',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'data' => [] ] ) ),
        ] );

        $this->invokeMethod( $this->harness, 'openai_embeddings_batch', [ [ 'Text1', 'Text2', 'Text3' ] ] );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertEquals( 'api.openai.com', $request->getUri()->getHost() );
        $this->assertEquals( '/v1/embeddings', $request->getUri()->getPath() );
        $this->assertEquals( 'Bearer sk-batch', $request->getHeaderLine( 'Authorization' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertIsArray( $body['input'] );
        $this->assertCount( 3, $body['input'] );
        $this->assertEquals( 'Text1', $body['input'][0] );
        $this->assertEquals( 'Text2', $body['input'][1] );
        $this->assertEquals( 'Text3', $body['input'][2] );
        $this->assertEquals( 'text-embedding-3-small', $body['model'] );
    }

    // ====================================================================
    // GOOGLE_EMBEDDINGS_BATCH (private)
    // ====================================================================

    public function test_google_embeddings_batch_returns_result_when_no_keys() {
        $result = $this->invokeMethod( $this->harness, 'google_embeddings_batch', [ [ 'X', 'Y' ] ] );
        $this->assertCount( 2, $result );
        $this->assertNull( $result[0]['vector'], 'Tanpa key vector harus null' );
        $this->assertSame( 'X', $result[0]['text'] );
    }

    public function test_google_embeddings_batch_sequential() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-batch',
        ];
        OptionCache::flush();

        // Sequential: 1 request per chunk
        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'embedding' => [ 'values' => [ 0.1 ] ] ] ) ),
            new Response( 200, [], json_encode( [ 'embedding' => [ 'values' => [ 0.2 ] ] ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'google_embeddings_batch', [ [ 'G1', 'G2' ] ] );

        $this->assertCount( 2, $result );
        $this->assertEquals( [ 0.1 ], $result[0]['vector'] );
        $this->assertEquals( [ 0.2 ], $result[1]['vector'] );
        $this->assertCount( 2, $this->requestContainer, 'Gemini batch: 2 chunks = 2 requests' );
    }

    // ====================================================================
    // SEQUENTIAL_EMBEDDINGS (private fallback)
    // ====================================================================

    public function test_sequential_embeddings_returns_correct_structure() {
        $result = $this->invokeMethod( $this->harness, 'sequential_embeddings', [ [ 'S1', 'S2' ], 'openai' ] );

        $this->assertCount( 2, $result );
        $this->assertArrayHasKey( 'text', $result[0] );
        $this->assertArrayHasKey( 'vector', $result[0] );
        $this->assertArrayHasKey( 'index', $result[0] );
    }

    public function test_sequential_embeddings_handles_empty_text_after_sanitize() {
        $result = $this->invokeMethod( $this->harness, 'sequential_embeddings', [ [ "\x00\x00", 'Valid' ], 'openai' ] );

        $this->assertCount( 2, $result );
        $this->assertFalse( $result[0]['vector'], 'Empty after sanitize → vector = false' );
        $this->assertSame( "\x00\x00", $result[0]['text'],
            'Original text harus tetap (bukan yang sudah di-sanitasi)'
        );
    }

    // ====================================================================
    // BUILD_BATCH_RESULT (private helper)
    // ====================================================================

    public function test_build_batch_result_returns_correct_structure() {
        $result = $this->invokeMethod( $this->harness, 'build_batch_result', [ [ 'A', 'B', 'C' ] ] );

        $this->assertCount( 3, $result );
        foreach ( $result as $i => $item ) {
            $this->assertSame( [ 'A', 'B', 'C' ][ $i ], $item['text'] );
            $this->assertNull( $item['vector'] );
            $this->assertSame( $i, $item['index'] );
        }
    }

    public function test_build_batch_result_with_custom_vector() {
        $default = [ 0.0, 0.0 ];
        $result = $this->invokeMethod( $this->harness, 'build_batch_result', [ [ 'X' ], $default ] );

        $this->assertCount( 1, $result );
        $this->assertSame( $default, $result[0]['vector'],
            'Custom default vector harus digunakan'
        );
    }

    // ====================================================================
    // DISPATCH_EMBEDDING via create_embedding (integration)
    // ====================================================================

    public function test_create_embedding_uses_gemini_provider() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'embedding' => [ 'values' => [ 0.01, 0.02 ] ],
            ] ) ),
        ] );

        $result = $this->harness->create_embedding( 'Gemini embed test', 'gemini' );
        $this->assertEquals( [ 0.01, 0.02 ], $result );
    }

    public function test_create_embedding_uses_hf_provider() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 0.5, 0.6, 0.7 ] ) ),
        ] );

        $result = $this->harness->create_embedding( 'HF embed test', 'hf' );
        $this->assertEquals( [ 0.5, 0.6, 0.7 ], $result );
    }

    public function test_create_embedding_returns_false_on_http_error() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 500, [], 'Server Error' ),
        ] );

        $result = $this->harness->create_embedding( 'Error test', 'openai' );
        $this->assertFalse( $result, 'HTTP error harus return false' );
    }

    // ====================================================================
    // BATCH ROUTING VIA create_embeddings_batch
    // ====================================================================

    public function test_create_embeddings_batch_gemini_provider() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-batch',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'embedding' => [ 'values' => [ 0.1 ] ] ] ) ),
        ] );

        $result = $this->harness->create_embeddings_batch( [ 'G1' ], 'gemini' );

        $this->assertCount( 1, $result );
        $this->assertEquals( [ 0.1 ], $result[0]['vector'] );
        $this->assertStringContainsString( 'generativelanguage.googleapis.com',
            (string) $this->requestContainer[0]['request']->getUri() );
    }

    public function test_create_embeddings_batch_hf_provider() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf-batch',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 0.3 ] ) ),
        ] );

        $result = $this->harness->create_embeddings_batch( [ 'H1' ], 'hf' );

        $this->assertCount( 1, $result );
        $this->assertEquals( [ 0.3 ], $result[0]['vector'] );
        $this->assertStringContainsString( 'huggingface.co',
            (string) $this->requestContainer[0]['request']->getUri() );
    }

    public function test_create_embeddings_batch_unknown_provider_fallback() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-fallback',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'data' => [ [ 'index' => 0, 'embedding' => [ 0.7 ] ] ] ] ) ),
        ] );

        // Unknown provider → sequential_embeddings → dispatch_embedding → openai (default)
        $result = $this->harness->create_embeddings_batch( [ 'F1' ], 'some_random_provider' );

        $this->assertCount( 1, $result );
        $this->assertEquals( [ 0.7 ], $result[0]['vector'],
            'Unknown provider harus fallback ke openai embedding'
        );
    }

    // ====================================================================
    // OPENAI_EMBEDDING — error responses
    // ====================================================================

    public function test_openai_embedding_missing_embedding_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'data' => [ [ 'not_embedding' => 'x' ] ] ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'openai_embedding', [ 'test' ] );
        $this->assertFalse( $result, 'Response tanpa embedding key harus return false' );
    }

    public function test_google_embedding_missing_values_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'embedding' => [ 'no_values' => [] ] ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'google_embedding', [ 'test' ] );
        $this->assertFalse( $result, 'Response tanpa values key harus return false' );
    }

    // ====================================================================
    // OPENAI_EMBEDDINGS_BATCH — out-of-order indices
    // ====================================================================

    public function test_openai_embeddings_batch_out_of_order_indices() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        // API kadang mengembalikan index tidak berurutan
        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'data' => [
                    [ 'index' => 2, 'embedding' => [ 0.2 ] ],
                    [ 'index' => 0, 'embedding' => [ 0.0 ] ],
                    [ 'index' => 1, 'embedding' => [ 0.1 ] ],
                ],
            ] ) ),
        ] );

        $result = $this->invokeMethod( $this->harness, 'openai_embeddings_batch', [ [ 'A', 'B', 'C' ] ] );

        $this->assertEquals( [ 0.0 ], $result[0]['vector'], 'Index 0 harus [0.0]' );
        $this->assertEquals( [ 0.1 ], $result[1]['vector'], 'Index 1 harus [0.1]' );
        $this->assertEquals( [ 0.2 ], $result[2]['vector'], 'Index 2 harus [0.2]' );
    }
}
