<?php
/**
 * Unit Test untuk Autoblog\Utils\AICompletionTrait.
 *
 * AICompletionTrait berisi 8 method completion per-provider AI:
 * - get_keys_pool()     — parsing multi-key input
 * - openai_completion()     — OpenAI GPT
 * - anthropic_completion()  — Anthropic Claude
 * - google_completion()     — Google Gemini
 * - groq_completion()       — Groq
 * - huggingface_completion() — HuggingFace Inference API
 * - openrouter_completion() — OpenRouter
 * - custom_provider_completion() — OpenAI-compatible custom endpoint
 *
 * Semua completion method menggunakan $this->request_with_backoff() kecuali
 * huggingface_completion yang langsung $this->client->post().
 *
 * Strategi test:
 * - Harness class menggunakan trait + menyediakan mock Guzzle Client
 * - Inject mock HTTP responses via Guzzle MockHandler + Middleware::history
 * - Completion methods public → panggil langsung di harness
 * - get_keys_pool() private → via reflection
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

class AICompletionTestHarness {
    use \Autoblog\Utils\AICompletionTrait;

    /** @var Client|null Mock Guzzle Client */
    public $client = null;

    /** @var string API Keys (dibutuhkan oleh trait) */
    public $openai_key     = '';
    public $anthropic_key  = '';
    public $gemini_key     = '';
    public $groq_key       = '';
    public $hf_key         = '';
    public $openrouter_key = '';

    /**
     * Delegasi request_with_backoff ke Guzzle client langsung.
     * Method ini dipanggil oleh completion method di trait.
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

class AICompletionTest extends TestCase {

    /** @var array Container untuk Guzzle History middleware */
    private $requestContainer = [];

    /** @var AICompletionTestHarness */
    private $harness;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        $this->harness = new AICompletionTestHarness();
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

    /**
     * Buat mock Guzzle Client dan set ke harness.
     *
     * @param array $responses Array of GuzzleHttp\Psr7\Response
     */
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
    // GET_KEYS_POOL (private) — parsing multi-key
    // ====================================================================

    /**
     * Test get_keys_pool memparsing multi-key dengan newline.
     */
    public function test_get_keys_pool_parses_newline_separated_keys() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => "key_one\nkey_two\nkey_three",
        ];
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->harness, 'get_keys_pool', [ 'openai' ] );

        $this->assertCount( 3, $pool );
        $this->assertEquals( 'key_one', $pool[0] );
        $this->assertEquals( 'key_two', $pool[1] );
        $this->assertEquals( 'key_three', $pool[2] );
    }

    /**
     * Test get_keys_pool memparsing multi-key dengan koma.
     */
    public function test_get_keys_pool_parses_comma_separated_keys() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'key_a, key_b, key_c',
        ];
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->harness, 'get_keys_pool', [ 'openai' ] );
        $this->assertCount( 3, $pool );
        $this->assertEquals( 'key_a', $pool[0] );
        $this->assertEquals( 'key_b', $pool[1] );
    }

    /**
     * Test get_keys_pool menangani campuran newline dan koma.
     */
    public function test_get_keys_pool_handles_mixed_separators() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => "key_one\nkey_two, key_three\n\nkey_four",
        ];
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->harness, 'get_keys_pool', [ 'openai' ] );
        $this->assertCount( 4, $pool );
    }

    /**
     * Test get_keys_pool normalisasi provider gemini → google.
     */
    public function test_get_keys_pool_normalizes_gemini_provider() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'gemini_key_123',
        ];
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->harness, 'get_keys_pool', [ 'gemini' ] );
        $this->assertCount( 1, $pool );
        $this->assertEquals( 'gemini_key_123', $pool[0] );
    }

    /**
     * Test get_keys_pool normalisasi provider hf → huggingface.
     */
    public function test_get_keys_pool_normalizes_hf_provider() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf_key_456',
        ];
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->harness, 'get_keys_pool', [ 'hf' ] );
        $this->assertCount( 1, $pool );
        $this->assertEquals( 'hf_key_456', $pool[0] );
    }

    /**
     * Test get_keys_pool fallback ke option legacy (autoblog_openai_key).
     */
    public function test_get_keys_pool_falls_back_to_legacy_option() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_openai_key'] = 'sk-legacy-key';
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->harness, 'get_keys_pool', [ 'openai' ] );
        $this->assertCount( 1, $pool );
        $this->assertEquals( 'sk-legacy-key', $pool[0] );
    }

    /**
     * Test get_keys_pool returns empty array when no keys.
     */
    public function test_get_keys_pool_returns_empty_when_no_keys() {
        $pool = $this->invokeMethod( $this->harness, 'get_keys_pool', [ 'non_existent' ] );
        $this->assertEmpty( $pool );
    }

    // ====================================================================
    // OPENAI COMPLETION
    // ====================================================================

    /**
     * Test openai_completion return false ketika key kosong.
     */
    public function test_openai_returns_false_when_key_missing() {
        $this->harness->openai_key = '';

        $result = $this->harness->openai_completion( 'Hello', 'gpt-4o' );
        $this->assertFalse( $result );
    }

    /**
     * Test openai_completion sukses: memparsing response body.
     */
    public function test_openai_completion_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'choices' => [ [ 'message' => [ 'content' => 'Hello from OpenAI!' ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->openai_completion( 'Say hello', 'gpt-4o' );
        $this->assertEquals( 'Hello from OpenAI!', $result );
    }

    /**
     * Test openai_completion: multiple keys, key pertama gagal → rotasi ke key kedua.
     */
    public function test_openai_completion_key_rotation() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => "sk-fail\nsk-success",
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 401, [], 'Unauthorized' ),  // Key pertama gagal
            new Response( 200, [], json_encode( [     // Key kedua sukses
                'choices' => [ [ 'message' => [ 'content' => 'Success with second key' ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->openai_completion( 'test', 'gpt-4o' );
        $this->assertEquals( 'Success with second key', $result,
            'Harus fallback ke key kedua setelah key pertama gagal'
        );
        $this->assertCount( 2, $this->requestContainer,
            'Harus ada 2 request (key pertama gagal, key kedua sukses)'
        );
    }

    /**
     * Test openai_completion: semua key gagal → false.
     */
    public function test_openai_completion_all_keys_fail() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => "sk-fail1\nsk-fail2",
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 401, [], 'Unauthorized' ),
            new Response( 403, [], 'Forbidden' ),
        ] );

        $result = $this->harness->openai_completion( 'test', 'gpt-4o' );
        $this->assertFalse( $result, 'Semua key gagal harus return false' );
    }

    /**
     * Test openai_completion: request format (method, URL, headers, JSON body).
     */
    public function test_openai_completion_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test-format',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'choices' => [ [ 'message' => [ 'content' => 'OK' ] ] ] ] ) ),
        ] );

        $this->harness->openai_completion( 'Test prompt', 'gpt-4o-mini', 0.5, 'Be helpful.' );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertEquals( 'api.openai.com', $request->getUri()->getHost() );
        $this->assertEquals( '/v1/chat/completions', $request->getUri()->getPath() );
        $this->assertEquals( 'Bearer sk-test-format', $request->getHeaderLine( 'Authorization' ) );
        $this->assertStringContainsString( 'application/json', $request->getHeaderLine( 'Content-Type' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'gpt-4o-mini', $body['model'] );
        $this->assertEquals( 0.5, $body['temperature'] );
        $this->assertEquals( 'Be helpful.', $body['messages'][0]['content'] );
        $this->assertEquals( 'Test prompt', $body['messages'][1]['content'] );
    }

    // ====================================================================
    // ANTHROPIC COMPLETION
    // ====================================================================

    /**
     * Test anthropic_completion return false ketika key kosong.
     */
    public function test_anthropic_returns_false_when_key_missing() {
        $this->harness->anthropic_key = '';
        $result = $this->harness->anthropic_completion( 'Hello', 'claude-3-5-sonnet-20240620' );
        $this->assertFalse( $result );
    }

    /**
     * Test anthropic_completion sukses.
     */
    public function test_anthropic_completion_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'anthropic' => 'sk-ant-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'content' => [ [ 'text' => 'Hello from Claude!' ] ],
            ] ) ),
        ] );

        $result = $this->harness->anthropic_completion( 'Say hello', 'claude-3-5-sonnet-20240620' );
        $this->assertEquals( 'Hello from Claude!', $result );
    }

    /**
     * Test anthropic_completion request format (x-api-key, anthropic-version, system top-level).
     */
    public function test_anthropic_completion_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'anthropic' => 'sk-ant-format',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'content' => [ [ 'text' => 'OK' ] ] ] ) ),
        ] );

        $this->harness->anthropic_completion( 'Hi', 'claude-3-haiku', 0.3, 'You are kind.' );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertEquals( 'api.anthropic.com', $request->getUri()->getHost() );
        $this->assertEquals( 'sk-ant-format', $request->getHeaderLine( 'x-api-key' ) );
        $this->assertEquals( '2023-06-01', $request->getHeaderLine( 'anthropic-version' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'claude-3-haiku', $body['model'] );
        $this->assertEquals( 2048, $body['max_tokens'] );
        $this->assertEquals( 0.3, $body['temperature'] );
        $this->assertEquals( 'You are kind.', $body['system'] );
        $this->assertEquals( 'Hi', $body['messages'][0]['content'] );
    }

    /**
     * Test anthropic_completion tanpa system prompt — tidak ada field system di body.
     */
    public function test_anthropic_completion_without_system_prompt() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'anthropic' => 'sk-ant-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'content' => [ [ 'text' => 'OK' ] ] ] ) ),
        ] );

        $this->harness->anthropic_completion( 'Hello', 'claude-3-haiku' );

        $body = json_decode( (string) $this->requestContainer[0]['request']->getBody(), true );
        $this->assertArrayNotHasKey( 'system', $body,
            'Tanpa system prompt, field system tidak boleh ada'
        );
    }

    // ====================================================================
    // GOOGLE GEMINI COMPLETION
    // ====================================================================

    /**
     * Test google_completion return false ketika key kosong.
     */
    public function test_gemini_returns_false_when_key_missing() {
        $this->harness->gemini_key = '';
        $result = $this->harness->google_completion( 'Hello', 'gemini-2.0-flash' );
        $this->assertFalse( $result );
    }

    /**
     * Test google_completion sukses.
     */
    public function test_gemini_completion_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'Hello from Gemini!' ] ] ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->google_completion( 'Hi', 'gemini-2.0-flash' );
        $this->assertEquals( 'Hello from Gemini!', $result );
    }

    /**
     * Test google_completion menggunakan model fallback dari ModelCatalog
     * jika model='auto'. Bug #3 Fix: ganti dari hardcoded gemini-3.1-pro.
     */
    public function test_gemini_completion_auto_model_fallback() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'OK' ] ] ] ] ],
            ] ) ),
        ] );

        $this->harness->google_completion( 'test', 'auto' );

        // Saat model='auto', harus pake fallback 'gemini-2.0-flash-lite'
        $url = (string) $this->requestContainer[0]['request']->getUri();
        $this->assertStringContainsString( 'gemini-2.0-flash-lite', $url,
            'Model auto harus fallback ke gemini-2.0-flash-lite'
        );
    }

    /**
     * Test google_completion request format (API key in URL query param).
     */
    public function test_gemini_completion_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-format-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'OK' ] ] ] ] ],
            ] ) ),
        ] );

        $this->harness->google_completion( 'Hello', 'gemini-2.0-flash', 0.8, 'Speak English.' );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertStringContainsString( 'generativelanguage.googleapis.com', (string) $request->getUri() );
        $this->assertStringContainsString( 'v1beta', (string) $request->getUri() );
        $this->assertStringContainsString( 'gemini-2.0-flash', (string) $request->getUri() );
        $this->assertStringContainsString( 'AIza-format-test', (string) $request->getUri() );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 0.8, $body['generationConfig']['temperature'] );
        $this->assertEquals( 'Speak English.', $body['system_instruction']['parts'][0]['text'] );
        $this->assertEquals( 'Hello', $body['contents'][0]['parts'][0]['text'] );
    }

    /**
     * Test google_completion dengan Search Grounding diaktifkan.
     */
    public function test_gemini_completion_with_grounding() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'google' => 'AIza-test',
        ];
        $_autoblog_mock_options['autoblog_gemini_grounding'] = '1';
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'OK' ] ] ] ] ],
            ] ) ),
        ] );

        $this->harness->google_completion( 'test', 'gemini-2.0-flash' );

        $body = json_decode( (string) $this->requestContainer[0]['request']->getBody(), true );
        $this->assertArrayHasKey( 'tools', $body,
            'Search Grounding aktif → harus ada field tools'
        );
        $this->assertArrayHasKey( 'google_search', $body['tools'][0],
            'tools harus berisi google_search'
        );
    }

    // ====================================================================
    // GROQ COMPLETION
    // ====================================================================

    /**
     * Test groq_completion return false ketika key kosong.
     */
    public function test_groq_returns_false_when_key_missing() {
        $this->harness->groq_key = '';
        $result = $this->harness->groq_completion( 'Hello', 'llama-3.3-70b-versatile' );
        $this->assertFalse( $result );
    }

    /**
     * Test groq_completion sukses.
     */
    public function test_groq_completion_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'groq' => 'gsk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'choices' => [ [ 'message' => [ 'content' => 'Hello from Groq!' ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->groq_completion( 'Hi', 'llama-3.3-70b-versatile' );
        $this->assertEquals( 'Hello from Groq!', $result );
    }

    /**
     * Test groq_completion dengan model auto → fallback ke llama-3.3-70b-versatile.
     */
    public function test_groq_completion_auto_model() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'groq' => 'gsk-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'choices' => [ [ 'message' => [ 'content' => 'OK' ] ] ] ] ) ),
        ] );

        $this->harness->groq_completion( 'test', 'auto' );

        $body = json_decode( (string) $this->requestContainer[0]['request']->getBody(), true );
        $this->assertEquals( 'llama-3.3-70b-versatile', $body['model'],
            'Model auto harus fallback ke llama-3.3-70b-versatile'
        );
    }

    /**
     * Test groq_completion request format.
     */
    public function test_groq_completion_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'groq' => 'gsk-format',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'choices' => [ [ 'message' => [ 'content' => 'OK' ] ] ] ] ) ),
        ] );

        $this->harness->groq_completion( 'Groq prompt', 'mixtral-8x7b-32768', 0.9 );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertEquals( 'api.groq.com', $request->getUri()->getHost() );
        $this->assertEquals( 'Bearer gsk-format', $request->getHeaderLine( 'Authorization' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'mixtral-8x7b-32768', $body['model'] );
        $this->assertEquals( 0.9, $body['temperature'] );
        $this->assertEquals( 'Groq prompt', $body['messages'][0]['content'] );
    }

    // ====================================================================
    // HUGGING FACE COMPLETION (via $this->client->post(), bukan request_with_backoff)
    // ====================================================================

    /**
     * Test huggingface_completion return false ketika key kosong.
     */
    public function test_huggingface_returns_false_when_key_missing() {
        $this->harness->hf_key = '';
        $result = $this->harness->huggingface_completion( 'Hello', 'mistralai/Mistral-7B-v0.1' );
        $this->assertFalse( $result );
    }

    /**
     * Test huggingface_completion sukses.
     */
    public function test_huggingface_completion_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf_test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                [ 'generated_text' => 'Generated text from HF' ],
            ] ) ),
        ] );

        $result = $this->harness->huggingface_completion( 'Complete this', 'gpt2' );
        $this->assertEquals( 'Generated text from HF', $result );
    }

    /**
     * Test huggingface_completion request format (URL, headers, JSON body).
     */
    public function test_huggingface_completion_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf_format',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ [ 'generated_text' => 'OK' ] ] ) ),
        ] );

        $this->harness->huggingface_completion( 'HF prompt', 'meta-llama/Llama-2-7b', 0.6 );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertStringContainsString( 'api-inference.huggingface.co', (string) $request->getUri() );
        $this->assertStringContainsString( 'meta-llama/Llama-2-7b', (string) $request->getUri() );
        $this->assertEquals( 'Bearer hf_format', $request->getHeaderLine( 'Authorization' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'HF prompt', $body['inputs'] );
        $this->assertEquals( 0.6, $body['parameters']['temperature'] );
    }

    /**
     * Test huggingface_completion — response tanpa generated_text → false.
     */
    public function test_huggingface_completion_missing_generated_text() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'huggingface' => 'hf_test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ [ 'error' => 'Model busy' ] ] ) ),
        ] );

        $result = $this->harness->huggingface_completion( 'test', 'gpt2' );
        $this->assertFalse( $result, 'Response tanpa generated_text harus return false' );
    }

    // ====================================================================
    // OPENROUTER COMPLETION
    // ====================================================================

    /**
     * Test openrouter_completion return false ketika key kosong.
     */
    public function test_openrouter_returns_false_when_key_missing() {
        $this->harness->openrouter_key = '';
        $result = $this->harness->openrouter_completion( 'Hello', 'openrouter/test' );
        $this->assertFalse( $result );
    }

    /**
     * Test openrouter_completion sukses.
     */
    public function test_openrouter_completion_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openrouter' => 'or-test',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'choices' => [ [ 'message' => [ 'content' => 'Hello from OpenRouter!' ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->openrouter_completion( 'Hi', 'openrouter/test' );
        $this->assertEquals( 'Hello from OpenRouter!', $result );
    }

    /**
     * Test openrouter_completion request format (HTTP-Referer header).
     */
    public function test_openrouter_completion_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openrouter' => 'or-format',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'choices' => [ [ 'message' => [ 'content' => 'OK' ] ] ] ] ) ),
        ] );

        $this->harness->openrouter_completion( 'OR prompt', 'openrouter/test', 0.5 );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertEquals( 'openrouter.ai', $request->getUri()->getHost() );
        $this->assertEquals( 'Bearer or-format', $request->getHeaderLine( 'Authorization' ) );
        $this->assertStringContainsString( 'example.com', $request->getHeaderLine( 'HTTP-Referer' ),
            'OpenRouter harus mengirim HTTP-Referer header'
        );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'openrouter/test', $body['model'] );
        $this->assertEquals( 0.5, $body['temperature'] );
        $this->assertEquals( 'OR prompt', $body['messages'][0]['content'] );
    }

    // ====================================================================
    // CUSTOM PROVIDER COMPLETION
    // ====================================================================

    /**
     * Test custom_provider_completion return false ketika endpoint kosong.
     */
    public function test_custom_provider_returns_false_when_endpoint_missing() {
        // Tidak ada custom endpoints, tidak ada ModelCatalog provider data
        $result = $this->harness->custom_provider_completion( 'Hello', 'test-model', 'custom_provider' );
        $this->assertFalse( $result, 'Tanpa endpoint harus return false' );
    }

    /**
     * Test custom_provider_completion return false ketika key kosong.
     */
    public function test_custom_provider_returns_false_when_key_missing() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_endpoints'] = [
            'my_provider' => 'https://my-api.example.com',
        ];
        OptionCache::flush();

        $result = $this->harness->custom_provider_completion( 'Hello', 'test-model', 'my_provider' );
        $this->assertFalse( $result, 'Tanpa API key harus return false' );
    }

    /**
     * Test custom_provider_completion dengan custom endpoint dari OptionCache.
     */
    public function test_custom_provider_completion_with_custom_endpoint() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_endpoints'] = [
            'my_provider' => 'https://my-api.example.com',
        ];
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'my_provider' => 'custom-key-123',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [
                'choices' => [ [ 'message' => [ 'content' => 'Hello from custom!' ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->custom_provider_completion(
            'Hi', 'my-model', 'my_provider', 0.5, 'Act as an expert.'
        );

        $this->assertEquals( 'Hello from custom!', $result );

        // Verify request format
        $request = $this->requestContainer[0]['request'];
        $this->assertStringContainsString( 'my-api.example.com/chat/completions', (string) $request->getUri() );
        $this->assertEquals( 'Bearer custom-key-123', $request->getHeaderLine( 'Authorization' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertEquals( 'my-model', $body['model'] );
        $this->assertEquals( 0.5, $body['temperature'] );
        $this->assertEquals( 'Act as an expert.', $body['messages'][0]['content'] );
        $this->assertEquals( 'Hi', $body['messages'][1]['content'] );
    }

    /**
     * Test custom_provider_completion: key rotation.
     */
    public function test_custom_provider_completion_key_rotation() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_endpoints'] = [
            'my_provider' => 'https://my-api.example.com',
        ];
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'my_provider' => "bad-key\ngood-key",
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 401, [], 'Unauthorized' ),
            new Response( 200, [], json_encode( [
                'choices' => [ [ 'message' => [ 'content' => 'Rotated!' ] ] ],
            ] ) ),
        ] );

        $result = $this->harness->custom_provider_completion( 'Hi', 'my-model', 'my_provider' );
        $this->assertEquals( 'Rotated!', $result,
            'Key rotation harus fallback ke key kedua'
        );
        $this->assertCount( 2, $this->requestContainer,
            'Harus ada 2 request untuk 2 keys'
        );
    }

    /**
     * Test custom_provider_completion tanpa system prompt.
     */
    public function test_custom_provider_completion_without_system_prompt() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_endpoints'] = [
            'my_provider' => 'https://my-api.example.com',
        ];
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'my_provider' => 'key',
        ];
        OptionCache::flush();

        $this->setMockClient( [
            new Response( 200, [], json_encode( [ 'choices' => [ [ 'message' => [ 'content' => 'OK' ] ] ] ] ) ),
        ] );

        $this->harness->custom_provider_completion( 'Hi', 'my-model', 'my_provider' );

        $body = json_decode( (string) $this->requestContainer[0]['request']->getBody(), true );
        $this->assertCount( 1, $body['messages'],
            'Tanpa system prompt, harus hanya ada 1 message (user)'
        );
        $this->assertEquals( 'user', $body['messages'][0]['role'] );
    }
}
