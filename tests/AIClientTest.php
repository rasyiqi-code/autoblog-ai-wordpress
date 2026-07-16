<?php
/**
 * Unit Test untuk Autoblog\Utils\AIClient.
 *
 * AIClient adalah klien AI terpusat yang menggunakan AICompletionTrait
 * dan AIEmbeddingTrait. Test ini fokus pada method-method SPESIFIK AIClient:
 *
 * 1. __construct()           — loading API keys dari options, create Guzzle Client
 * 2. request_with_backoff()  — Exponential backoff untuk 429/5xx/4xx
 * 3. get_fallback_model()     — Intra-provider & cross-provider fallback
 * 4. generate_text()         — Provider routing & auto-fallback circuit breaker
 *
 * Completion method per-provider (openai_completion, anthropic_completion, dll)
 * sudah di-test di AICompletionTest.php — tidak di-duplikasi di sini.
 *
 * Strategi:
 * - Instansiasi AIClient nyata dengan mock options
 * - Inject mock Guzzle Client via reflection (private $client)
 * - Mock model catalog via $_autoblog_mock_transients
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      utils
 * @group      ai_client
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;

/**
 * Unit Test untuk AIClient.
 *
 * @group unit
 * @group utils
 * @group ai_client
 */
class AIClientTest extends TestCase {

    /** @var array Container untuk Guzzle History middleware */
    private $requestContainer = [];

    /** @var AIClient Instance AIClient dengan mock Guzzle client */
    private $aiClient;

    /** @var array Mock model catalog untuk get_fallback_model */
    private $mockModels = [
        'openai' => [
            'gpt-4o'              => 'GPT-4o',
            'gpt-4-turbo'         => 'GPT-4 Turbo',
            'gpt-3.5-turbo'       => 'GPT-3.5 Turbo',
        ],
        'google' => [
            'gemini-1.5-pro'      => 'Gemini 1.5 Pro',
            'gemini-1.5-flash'    => 'Gemini 1.5 Flash',
            'gemini-2.0-flash'    => 'Gemini 2.0 Flash',
        ],
        'anthropic' => [
            'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet',
            'claude-3-opus-20240229'     => 'Claude 3 Opus',
        ],
        'groq' => [
            'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
            'mixtral-8x7b-32768'      => 'Mixtral 8x7B',
        ],
    ];

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        global $_autoblog_mock_transients;
        $_autoblog_mock_options     = array();
        $_autoblog_mock_transients  = array();

        // Mock model catalog untuk get_fallback_model
        $_autoblog_mock_transients['autoblog_models_dev_cache_v2'] = $this->mockModels;

        // Mock API keys untuk constructor AIClient
        $_autoblog_mock_options['autoblog_openai_key']     = 'sk-openai-test';
        $_autoblog_mock_options['autoblog_anthropic_key']  = 'sk-anthropic-test';
        $_autoblog_mock_options['autoblog_gemini_key']     = 'AIza-gemini-test';
        $_autoblog_mock_options['autoblog_groq_key']       = 'gsk-groq-test';
        $_autoblog_mock_options['autoblog_hf_key']         = 'hf_test';
        $_autoblog_mock_options['autoblog_openrouter_key'] = 'or-test';
        $_autoblog_mock_options['autoblog_enable_fallback'] = '1';

        // Construct AIClient — akan membaca dari mock options
        $this->aiClient = new AIClient();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        global $_autoblog_mock_transients;
        $_autoblog_mock_options    = array();
        $_autoblog_mock_transients = array();
        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // HELPERS
    // ====================================================================

    /**
     * Buat mock Guzzle Client dan inject ke AIClient via reflection.
     *
     * @param array $responses Array of GuzzleHttp\Psr7\Response
     */
    private function setMockClient( array $responses ): void {
        $this->requestContainer = array();
        $history                = Middleware::history( $this->requestContainer );

        $handlerStack = HandlerStack::create( new MockHandler( $responses ) );
        $handlerStack->push( $history );

        $mockClient = new Client( array(
            'handler'     => $handlerStack,
            'http_errors' => false,
        ) );

        $reflection  = new \ReflectionClass( AIClient::class );
        $clientProp  = $reflection->getProperty( 'client' );
        $clientProp->setAccessible( true );
        $clientProp->setValue( $this->aiClient, $mockClient );
    }

    /**
     * Panggil private/protected method via reflection.
     */
    private function invokeMethod( string $methodName, array $parameters = array() ) {
        $reflection = new \ReflectionClass( AIClient::class );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $this->aiClient, $parameters );
    }

    /**
     * Dapatkan nilai private property via reflection.
     */
    private function getProperty( string $propName ) {
        $reflection = new \ReflectionClass( AIClient::class );
        $prop       = $reflection->getProperty( $propName );
        $prop->setAccessible( true );
        return $prop->getValue( $this->aiClient );
    }

    // ================================================================
    // CONSTRUCTOR
    // ================================================================

    public function test_constructor_loads_openai_key() {
        $this->assertEquals( 'sk-openai-test', $this->getProperty( 'openai_key' ) );
    }

    public function test_constructor_loads_anthropic_key() {
        $this->assertEquals( 'sk-anthropic-test', $this->getProperty( 'anthropic_key' ) );
    }

    public function test_constructor_loads_gemini_key() {
        $this->assertEquals( 'AIza-gemini-test', $this->getProperty( 'gemini_key' ) );
    }

    public function test_constructor_loads_groq_key() {
        $this->assertEquals( 'gsk-groq-test', $this->getProperty( 'groq_key' ) );
    }

    public function test_constructor_loads_hf_key() {
        $this->assertEquals( 'hf_test', $this->getProperty( 'hf_key' ) );
    }

    public function test_constructor_loads_openrouter_key() {
        $this->assertEquals( 'or-test', $this->getProperty( 'openrouter_key' ) );
    }

    public function test_constructor_creates_guzzle_client() {
        $client = $this->getProperty( 'client' );
        $this->assertInstanceOf( Client::class, $client );
    }

    public function test_constructor_handles_missing_keys_gracefully() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = array(); // Hapus semua keys

        // Reset cache agar OptionCache.get() membaca dari mock options yang baru
        OptionCache::flush();

        $client = new AIClient();

        $reflection = new \ReflectionClass( AIClient::class );

        $openaiProp = $reflection->getProperty( 'openai_key' );
        $openaiProp->setAccessible( true );
        $this->assertEmpty( $openaiProp->getValue( $client ), 'Tanpa keys, openai_key harus empty' );
    }

    // ================================================================
    // REQUEST WITH BACKOFF (private)
    // ================================================================

    public function test_request_with_backoff_success_returns_response() {
        $this->setMockClient( array(
            new Response( 200, array(), '{"success": true}' ),
        ) );

        $response = $this->invokeMethod( 'request_with_backoff', array(
            'GET', 'https://api.example.com/test', array(),
        ) );

        $this->assertEquals( 200, $response->getStatusCode() );
        $body = json_decode( (string) $response->getBody(), true );
        $this->assertTrue( $body['success'] );
    }

    public function test_request_with_backoff_429_retries_once_then_succeeds() {
        $this->setMockClient( array(
            new Response( 429, array(), 'Too Many Requests' ),
            new Response( 200, array(), '{"success": true}' ),
        ) );

        $response = $this->invokeMethod( 'request_with_backoff', array(
            'GET', 'https://api.example.com/test', array(),
        ) );

        // Harus sukses setelah retry
        $this->assertEquals( 200, $response->getStatusCode() );

        // Pastikan 2 request dibuat (1 gagal + 1 sukses)
        $this->assertCount( 2, $this->requestContainer );
    }

    public function test_request_with_backoff_429_exhausted_throws() {
        $this->setMockClient( array(
            new Response( 429, array(), 'Too Many' ),
            new Response( 429, array(), 'Too Many' ),
            new Response( 429, array(), 'Too Many' ),
        ) );

        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( '429 Too Many Requests' );

        $this->invokeMethod( 'request_with_backoff', array(
            'GET', 'https://api.example.com/test', array(), 2, // max_retries=2
        ) );
    }

    public function test_request_with_backoff_500_retries_once_then_succeeds() {
        $this->setMockClient( array(
            new Response( 500, array(), 'Server Error' ),
            new Response( 200, array(), '{"ok": true}' ),
        ) );

        $response = $this->invokeMethod( 'request_with_backoff', array(
            'GET', 'https://api.example.com/test', array(),
        ) );

        $this->assertEquals( 200, $response->getStatusCode() );
        $this->assertCount( 2, $this->requestContainer );
    }

    public function test_request_with_backoff_400_throws_immediately() {
        $this->setMockClient( array(
            new Response( 400, array(), 'Bad Request' ),
        ) );

        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( 'HTTP Error 400' );

        $this->invokeMethod( 'request_with_backoff', array(
            'GET', 'https://api.example.com/test', array(),
        ) );
    }

    // ================================================================
    // GET FALLBACK MODEL — Intra-Provider
    // ================================================================

    public function test_get_fallback_model_intra_provider_next_model() {
        // exclude_model = 'gpt-4o', provider = 'openai'
        // Pool: ['gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo']
        // Next: 'gpt-4-turbo'
        $result = $this->aiClient->get_fallback_model( 'gpt-4o', 'openai' );

        $this->assertEquals( 'gpt-4-turbo', $result,
            'Intra-provider: harus return model berikutnya di pool openai'
        );
    }

    public function test_get_fallback_model_intra_provider_last_model_returns_false() {
        global $_autoblog_mock_options;
        // Disable cross-provider agar intra-provider saja yang dievaluasi
        $_autoblog_mock_options['autoblog_enable_fallback'] = '0';
        OptionCache::flush();

        // exclude_model = 'gpt-3.5-turbo' (last in pool), provider = 'openai'
        // No more models in pool → intra-provider selesai → cross-provider disabled → false
        $result = $this->aiClient->get_fallback_model( 'gpt-3.5-turbo', 'openai' );

        $this->assertFalse( $result,
            'Intra-provider: model terakhir di pool harus return false'
        );
    }

    public function test_get_fallback_model_intra_provider_auto_model() {
        // exclude_model = 'auto' → treat sebagai model pertama di pool
        // Next: model[1] = 'gpt-4-turbo'
        $result = $this->aiClient->get_fallback_model( 'auto', 'openai' );

        $this->assertEquals( 'gpt-4-turbo', $result,
            'Intra-provider: "auto" harus skip ke model kedua di pool'
        );
    }

    public function test_get_fallback_model_intra_provider_gemini_normalizes_key() {
        // Provider 'gemini' → normalized ke 'google'
        // exclude_model = 'gemini-1.5-pro', pool google: ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-2.0-flash']
        // Next: 'gemini-1.5-flash'
        $result = $this->aiClient->get_fallback_model( 'gemini-1.5-pro', 'gemini' );

        $this->assertEquals( 'gemini-1.5-flash', $result,
            'Intra-provider: gemini harus normalized ke google, next model = gemini-1.5-flash'
        );
    }

    // ================================================================
    // GET FALLBACK MODEL — Provider Detection
    // ================================================================

    public function test_get_fallback_model_detects_provider_from_model_name() {
        // Model name 'gpt-4o' → provider = 'openai'
        // exclude_model = 'gpt-4o' → next = 'gpt-4-turbo'
        $result = $this->aiClient->get_fallback_model( 'gpt-4o' );

        $this->assertEquals( 'gpt-4-turbo', $result,
            'Provider harus terdeteksi dari nama model gpt-4o → openai'
        );
    }

    public function test_get_fallback_model_detects_claude_provider() {
        // Model name 'claude-3-5-sonnet-20240620' → provider = 'anthropic'
        // Pool anthropic: ['claude-3-5-sonnet-20240620', 'claude-3-opus-20240229']
        // Next: 'claude-3-opus-20240229'
        $result = $this->aiClient->get_fallback_model( 'claude-3-5-sonnet-20240620' );

        $this->assertEquals( 'claude-3-opus-20240229', $result,
            'Provider harus terdeteksi dari nama model claude → anthropic'
        );
    }

    public function test_get_fallback_model_detects_gemini_provider() {
        $result = $this->aiClient->get_fallback_model( 'gemini-1.5-pro' );

        $this->assertEquals( 'gemini-1.5-flash', $result,
            'Provider harus terdeteksi dari nama model gemini → google'
        );
    }

    public function test_get_fallback_model_detects_groq_provider_from_llama() {
        // Pool groq: ['llama-3.3-70b-versatile', 'mixtral-8x7b-32768']
        // exclude = 'llama-3.3-70b-versatile' → next = 'mixtral-8x7b-32768'
        $result = $this->aiClient->get_fallback_model( 'llama-3.3-70b-versatile' );

        $this->assertEquals( 'mixtral-8x7b-32768', $result,
            'Provider harus terdeteksi dari nama model llama → groq'
        );
    }

    // ================================================================
    // GET FALLBACK MODEL — Cross-Provider (Smart Fallback)
    // ================================================================

    public function test_get_fallback_model_cross_provider_gemini_to_openai() {
        // Gemini habis → cross-provider cek keys: openai, groq, anthropic
        // Prioritas: openai → groq → anthropic
        $result = $this->aiClient->get_fallback_model( 'gemini-2.0-flash', 'gemini' );

        // openai_key = 'sk-openai-test' → target = 'openai'
        // custom models: not set → fallback ke model pertama di catalog openai = 'gpt-4o'
        $this->assertEquals( 'gpt-4o', $result,
            'Cross-provider: gemini → openai dengan model gpt-4o'
        );
    }

    public function test_get_fallback_model_cross_provider_openai_to_gemini() {
        // exclude model TERAKHIR di pool openai ('gpt-3.5-turbo') agar intra-provider habis
        // dan cross-provider aktif (openai → gemini)
        $result = $this->aiClient->get_fallback_model( 'gpt-3.5-turbo', 'openai' );

        $this->assertEquals( 'gemini-1.5-pro', $result,
            'Cross-provider: openai → gemini dengan model gemini-1.5-pro'
        );
    }

    public function test_get_fallback_model_cross_provider_disabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_fallback'] = '0';
        OptionCache::flush();

        // Intra-provider habis untuk openai → cross-provider disabled → false
        $result = $this->aiClient->get_fallback_model( 'gpt-3.5-turbo', 'openai' );

        $this->assertFalse( $result,
            'Cross-provider disabled: harus return false'
        );
    }

    public function test_get_fallback_model_cross_provider_no_target_key() {
        global $_autoblog_mock_options;
        // Hanya OpenAI yang punya key, gemini hapus
        $_autoblog_mock_options['autoblog_gemini_key'] = '';
        OptionCache::flush();

        // Buat new AIClient dengan keys yang diupdate
        $updatedClient = new AIClient();

        $reflection = new \ReflectionClass( AIClient::class );
        $clientProp = $reflection->getProperty( 'client' );
        $clientProp->setAccessible( true );
        $clientProp->setValue( $updatedClient, $this->getProperty( 'client' ) );

        // Gemini last model → harus cross ke openai
        $result = $updatedClient->get_fallback_model( 'gemini-2.0-flash', 'gemini' );

        $this->assertEquals( 'gpt-4o', $result,
            'Cross-provider: gemini → openai dengan model gpt-4o'
        );
    }

    // ================================================================
    // GENERATE TEXT — Provider Routing
    // ================================================================

    public function test_generate_text_routes_to_openai_for_gpt_model() {
        $this->setMockClient( array(
            new Response( 200, array(), json_encode( array(
                'choices' => array( array( 'message' => array( 'content' => 'Hello from OpenAI!' ) ) ),
            ) ) ),
        ) );

        $result = $this->aiClient->generate_text( 'Say hello', 'gpt-4o' );

        $this->assertEquals( 'Hello from OpenAI!', $result );

        // Verify request host
        $url = (string) $this->requestContainer[0]['request']->getUri();
        $this->assertStringContainsString( 'api.openai.com', $url );
    }

    public function test_generate_text_routes_to_anthropic_for_claude_model() {
        $this->setMockClient( array(
            new Response( 200, array(), json_encode( array(
                'content' => array( array( 'text' => 'Hello from Claude!' ) ),
            ) ) ),
        ) );

        $result = $this->aiClient->generate_text( 'Hi', 'claude-3-5-sonnet-20240620' );

        $this->assertEquals( 'Hello from Claude!', $result );
        $this->assertStringContainsString( 'api.anthropic.com', (string) $this->requestContainer[0]['request']->getUri() );
    }

    public function test_generate_text_routes_to_gemini_for_gemini_model() {
        $this->setMockClient( array(
            new Response( 200, array(), json_encode( array(
                'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Hello from Gemini!' ) ) ) ) ),
            ) ) ),
        ) );

        $result = $this->aiClient->generate_text( 'Hi', 'gemini-2.0-flash' );

        $this->assertEquals( 'Hello from Gemini!', $result );
        $this->assertStringContainsString( 'generativelanguage.googleapis.com', (string) $this->requestContainer[0]['request']->getUri() );
    }

    public function test_generate_text_routes_to_groq_for_llama_model() {
        $this->setMockClient( array(
            new Response( 200, array(), json_encode( array(
                'choices' => array( array( 'message' => array( 'content' => 'Hello from Groq!' ) ) ),
            ) ) ),
        ) );

        $result = $this->aiClient->generate_text( 'Hi', 'llama-3.3-70b-versatile' );

        $this->assertEquals( 'Hello from Groq!', $result );
        $this->assertStringContainsString( 'api.groq.com', (string) $this->requestContainer[0]['request']->getUri() );
    }

    // ================================================================
    // GENERATE TEXT — Provider dari parameter explicit
    // ================================================================

    public function test_generate_text_uses_explicit_provider_parameter() {
        $this->setMockClient( array(
            new Response( 200, array(), json_encode( array(
                'choices' => array( array( 'message' => array( 'content' => 'Custom provider!' ) ) ),
            ) ) ),
        ) );

        // Model tidak dikenal, tapi provider explicit = 'openai'
        $result = $this->aiClient->generate_text( 'Test', 'unknown-model', 'openai' );

        $this->assertEquals( 'Custom provider!', $result );
        $this->assertStringContainsString( 'api.openai.com', (string) $this->requestContainer[0]['request']->getUri() );
    }

    // ================================================================
    // GENERATE TEXT — Auto-Fallback
    // ================================================================

    public function test_generate_text_auto_fallback_on_failure() {
        // OpenAI 401 → key rotation gagal → false → get_fallback_model → 'gpt-4-turbo'
        // Recursive call with 'gpt-4-turbo' → success
        $this->setMockClient( array(
            new Response( 401, array(), 'Unauthorized' ),
            new Response( 200, array(), json_encode( array(
                'choices' => array( array( 'message' => array( 'content' => 'Fallback success!' ) ) ),
            ) ) ),
        ) );

        $result = $this->aiClient->generate_text( 'Test prompt for fallback', 'gpt-4o' );

        $this->assertEquals( 'Fallback success!', $result,
            'Auto-fallback harus mengembalikan hasil dari model fallback'
        );

        // 2 HTTP requests: 1 gagal (gpt-4o) + 1 sukses (gpt-4-turbo via fallback)
        $this->assertCount( 2, $this->requestContainer );
    }

    public function test_generate_text_auto_fallback_gemini_to_openai() {
        // Gemini 401 → key rotation gagal → intra-provider habis → cross ke openai
        $this->setMockClient( array(
            new Response( 401, array(), 'Unauthorized' ),
            new Response( 200, array(), json_encode( array(
                'choices' => array( array( 'message' => array( 'content' => 'OpenAI fallback!' ) ) ),
            ) ) ),
        ) );

        $result = $this->aiClient->generate_text( 'Gemini fallback test', 'gemini-2.0-flash' );

        $this->assertEquals( 'OpenAI fallback!', $result,
            'Cross-provider fallback: gemini gagal → openai sukses'
        );
    }

    // ================================================================
    // GENERATE TEXT — Temperature & System Prompt
    // ================================================================

    public function test_generate_text_passes_temperature_and_system_prompt() {
        $this->setMockClient( array(
            new Response( 200, array(), json_encode( array(
                'choices' => array( array( 'message' => array( 'content' => 'OK' ) ) ),
            ) ) ),
        ) );

        $this->aiClient->generate_text( 'Test', 'gpt-4o', 'openai', 0.3, 'Be concise.' );

        $body = json_decode( (string) $this->requestContainer[0]['request']->getBody(), true );
        $this->assertEquals( 0.3, $body['temperature'], 'Temperature harus 0.3' );
        $this->assertEquals( 'Be concise.', $body['messages'][0]['content'], 'System prompt harus dikirim' );
    }

    // ================================================================
    // GENERATE TEXT — Edge Cases
    // ================================================================

    public function test_generate_text_returns_false_when_no_keys() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = array(); // Hapus semua keys
        OptionCache::flush();

        $client = new AIClient();

        // Inject mock client (meski tidak akan dipakai karena key kosong)
        $reflection = new \ReflectionClass( AIClient::class );
        $prop       = $reflection->getProperty( 'client' );
        $prop->setAccessible( true );
        $prop->setValue( $client, $this->getProperty( 'client' ) );

        $result = $client->generate_text( 'Test', 'gpt-4o' );

        $this->assertFalse( $result, 'Tanpa API keys, generate_text harus return false' );
    }

    public function test_generate_text_handles_unknown_model_gracefully() {
        // Model 'nonexistent-model' tidak dikenal → provider kosong → default case
        // custom_provider_completion dipanggil → tidak ada endpoint → false
        $result = $this->aiClient->generate_text( 'Test', 'nonexistent-model' );

        $this->assertFalse( $result,
            'Model tidak dikenal harus return false (bukan throw)'
        );
    }

    // ================================================================
    // GENERATE TEXT — sanitize_utf8 dipanggil
    // ================================================================

    public function test_generate_text_sanitizes_input() {
        $this->setMockClient( array(
            new Response( 200, array(), json_encode( array(
                'choices' => array( array( 'message' => array( 'content' => 'OK' ) ) ),
            ) ) ),
        ) );

        // Null byte dalam prompt — harus di-sanitasi sebelum dikirim
        $this->aiClient->generate_text( "Clean\x00Text", 'gpt-4o' );

        $body = json_decode( (string) $this->requestContainer[0]['request']->getBody(), true );
        $this->assertStringNotContainsString( "\x00", $body['messages'][1]['content'],
            'Null bytes harus di-strip dari prompt sebelum dikirim'
        );
        $this->assertStringContainsString( 'CleanText', $body['messages'][1]['content'] );
    }
}
