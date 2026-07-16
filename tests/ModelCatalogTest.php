<?php
/**
 * Unit Test untuk ModelCatalog.
 *
 * Memverifikasi 4 method static:
 *
 * 1. get_dynamic_models()  — model list dari API, cache hit/miss, error handling
 * 2. get_merged_models()   — backward-compat alias
 * 3. get_dynamic_providers() — provider list dari API, cache hit/miss, error handling
 * 4. get_active_model()    — fallback chain: custom → global → provider → catalog → ''
 *
 * Strategi:
 * - Mock HTTP via $_autoblog_mock_remote_body / $_autoblog_mock_remote_response
 * - Mock transient via $_autoblog_mock_transients
 * - Mock options via $_autoblog_mock_options
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      utils
 * @group      model_catalog
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Utils\ModelCatalog;
use Autoblog\Utils\OptionCache;

class ModelCatalogTest extends TestCase {

    /** @var array Sample API response untuk testing */
    private $sampleApiData = [
        'openai' => [
            'name'   => 'OpenAI',
            'api'    => 'https://api.openai.com',
            'env'    => [ 'OPENAI_API_KEY' => 'sk-...' ],
            'models' => [
                'gpt-4o'    => [ 'name' => 'GPT-4o' ],
                'gpt-4o-mini' => [ 'name' => 'GPT-4o Mini' ],
                'gpt-4-turbo' => [ 'name' => 'GPT-4 Turbo' ],
            ],
        ],
        'google' => [
            'name'   => 'Google Gemini',
            'api'    => 'https://generativelanguage.googleapis.com',
            'env'    => [ 'GEMINI_API_KEY' => 'AIza...' ],
            'models' => [
                'gemini-1.5-pro' => [ 'name' => 'Gemini 1.5 Pro' ],
                'gemini-2.0-flash' => [ 'name' => 'Gemini 2.0 Flash' ],
            ],
        ],
        'custom_provider_no_models' => [
            'name' => 'No Models Provider',
            'api'  => 'https://custom.api.com',
        ],
    ];

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        global $_autoblog_mock_transients;
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;
        global $_autoblog_mock_is_wp_error;

        $_autoblog_mock_options         = [];
        $_autoblog_mock_transients      = [];
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;
        $_autoblog_mock_is_wp_error     = null;
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        global $_autoblog_mock_transients;
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;
        global $_autoblog_mock_is_wp_error;

        $_autoblog_mock_options         = [];
        $_autoblog_mock_transients      = [];
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;
        $_autoblog_mock_is_wp_error     = null;

        OptionCache::flush();
        parent::tearDown();
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Setel mock HTTP response untuk ModelCatalog API calls.
     */
    private function mockHttpResponse( array $data ): void {
        $body = json_encode( $data );
        $GLOBALS['_autoblog_mock_remote_body']     = $body;
        $GLOBALS['_autoblog_mock_remote_response'] = [ 'body' => $body ];
        $GLOBALS['_autoblog_mock_is_wp_error']     = false;
    }

    /**
     * Setel mock WP_Error untuk HTTP calls.
     */
    private function mockHttpError(): void {
        $GLOBALS['_autoblog_mock_remote_body']     = '';
        $GLOBALS['_autoblog_mock_remote_response'] = new \WP_Error( 'http_error', 'Connection failed' );
        $GLOBALS['_autoblog_mock_is_wp_error']     = true;
    }

    // ================================================================
    // GET_DYNAMIC_MODELS — Cache Hit
    // ================================================================

    public function test_get_dynamic_models_returns_cached_data() {
        $cachedData = [ 'openai' => [ 'gpt-4o' => 'GPT-4o (Cached)' ] ];
        $GLOBALS['_autoblog_mock_transients']['autoblog_models_dev_cache_v2'] = $cachedData;

        $result = ModelCatalog::get_dynamic_models();

        $this->assertEquals( $cachedData, $result );
    }

    // ================================================================
    // GET_DYNAMIC_MODELS — Cache Miss / HTTP Errors
    // ================================================================

    public function test_get_dynamic_models_returns_empty_on_wp_error() {
        $this->mockHttpError();

        $result = ModelCatalog::get_dynamic_models();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_get_dynamic_models_returns_empty_on_invalid_json() {
        $GLOBALS['_autoblog_mock_remote_body']     = 'not-json';
        $GLOBALS['_autoblog_mock_remote_response'] = [ 'body' => 'not-json' ];
        $GLOBALS['_autoblog_mock_is_wp_error']     = false;

        $result = ModelCatalog::get_dynamic_models();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // GET_DYNAMIC_MODELS — Valid API Response
    // ================================================================

    public function test_get_dynamic_models_parses_valid_response() {
        $this->mockHttpResponse( $this->sampleApiData );

        $result = ModelCatalog::get_dynamic_models();

        $this->assertArrayHasKey( 'openai', $result );
        $this->assertArrayHasKey( 'google', $result );
        $this->assertArrayNotHasKey( 'custom_provider_no_models', $result,
            'Provider tanpa model harus di-skip' );
    }

    public function test_get_dynamic_models_extracts_model_name() {
        $this->mockHttpResponse( $this->sampleApiData );

        $result = ModelCatalog::get_dynamic_models();

        // Model dengan 'name' field → gunakan name
        $this->assertEquals( 'GPT-4o', $result['openai']['gpt-4o'] );
        $this->assertEquals( 'Gemini 1.5 Pro', $result['google']['gemini-1.5-pro'] );
    }

    public function test_get_dynamic_models_uses_id_when_name_missing() {
        $apiData = [
            'test_provider' => [
                'models' => [
                    'model-without-name' => [ 'other_field' => 'value' ],
                ],
            ],
        ];
        $this->mockHttpResponse( $apiData );

        $result = ModelCatalog::get_dynamic_models();

        // Tanpa 'name' field → gunakan model_id sebagai name
        $this->assertEquals( 'model-without-name', $result['test_provider']['model-without-name'] );
    }

    public function test_get_dynamic_models_skips_providers_without_models_key() {
        $apiData = [
            'valid_provider' => [
                'models' => [ 'model-a' => [ 'name' => 'Model A' ] ],
            ],
            'no_models_key' => [
                'name' => 'No Models Key',
            ],
        ];
        $this->mockHttpResponse( $apiData );

        $result = ModelCatalog::get_dynamic_models();

        $this->assertArrayHasKey( 'valid_provider', $result );
        $this->assertArrayNotHasKey( 'no_models_key', $result,
            'Provider tanpa key "models" harus di-skip' );
    }

    public function test_get_dynamic_models_returns_empty_for_empty_data() {
        $this->mockHttpResponse( [] );

        $result = ModelCatalog::get_dynamic_models();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_get_dynamic_models_stores_cache_after_fetch() {
        $this->mockHttpResponse( $this->sampleApiData );

        $result = ModelCatalog::get_dynamic_models();

        // Verifikasi cache disimpan (set_transient dipanggil)
        // Dalam test env, set_transient adalah mock yang tidak menyimpan,
        // tapi kita bisa verifikasi result bukan dari cache
        $this->assertNotEmpty( $result );

        // Panggil lagi — harus dari cache jika transient mock return data
        $GLOBALS['_autoblog_mock_transients']['autoblog_models_dev_cache_v2'] = $result;

        $cachedResult = ModelCatalog::get_dynamic_models();
        $this->assertEquals( $result, $cachedResult,
            'Hasil kedua harus sama (dari cache)' );
    }

    // ================================================================
    // GET_MERGED_MODELS — backward compatibility
    // ================================================================

    public function test_get_merged_models_returns_same_as_get_dynamic_models() {
        $this->mockHttpResponse( $this->sampleApiData );

        $merged  = ModelCatalog::get_merged_models();
        $dynamic = ModelCatalog::get_dynamic_models();

        $this->assertEquals( $dynamic, $merged,
            'get_merged_models harus return data yang sama dengan get_dynamic_models' );
    }

    public function test_get_merged_models_uses_cache() {
        $cachedData = [ 'openai' => [ 'gpt-4o' => 'GPT-4o (Cached)' ] ];
        $GLOBALS['_autoblog_mock_transients']['autoblog_models_dev_cache_v2'] = $cachedData;

        // Tidak set HTTP mock — jika cache miss, akan return []
        $result = ModelCatalog::get_merged_models();

        $this->assertEquals( $cachedData, $result,
            'get_merged_models harus membaca dari cache yang sama' );
    }

    // ================================================================
    // GET_DYNAMIC_PROVIDERS — Cache Hit
    // ================================================================

    public function test_get_dynamic_providers_returns_cached_data() {
        $cachedData = [ 'openai' => [ 'name' => 'OpenAI (Cached)', 'api' => '', 'env' => [] ] ];
        $GLOBALS['_autoblog_mock_transients']['autoblog_providers_cache_v2'] = $cachedData;

        $result = ModelCatalog::get_dynamic_providers();

        $this->assertEquals( $cachedData, $result );
    }

    // ================================================================
    // GET_DYNAMIC_PROVIDERS — Cache Miss / HTTP Errors
    // ================================================================

    public function test_get_dynamic_providers_returns_empty_on_wp_error() {
        $this->mockHttpError();

        $result = ModelCatalog::get_dynamic_providers();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_get_dynamic_providers_returns_empty_on_invalid_json() {
        $GLOBALS['_autoblog_mock_remote_body']     = 'not-json';
        $GLOBALS['_autoblog_mock_remote_response'] = [ 'body' => 'not-json' ];
        $GLOBALS['_autoblog_mock_is_wp_error']     = false;

        $result = ModelCatalog::get_dynamic_providers();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // GET_DYNAMIC_PROVIDERS — Valid API Response
    // ================================================================

    public function test_get_dynamic_providers_parses_valid_response() {
        $this->mockHttpResponse( $this->sampleApiData );

        $result = ModelCatalog::get_dynamic_providers();

        $this->assertArrayHasKey( 'openai', $result );
        $this->assertArrayHasKey( 'google', $result );
        $this->assertArrayHasKey( 'custom_provider_no_models', $result,
            'Semua provider harus ada (tidak hanya yang punya models)' );
    }

    public function test_get_dynamic_providers_extracts_name_api_env() {
        $this->mockHttpResponse( $this->sampleApiData );

        $result = ModelCatalog::get_dynamic_providers();

        $openai = $result['openai'];
        $this->assertEquals( 'OpenAI', $openai['name'] );
        $this->assertEquals( 'https://api.openai.com', $openai['api'] );
        $this->assertEquals( [ 'OPENAI_API_KEY' => 'sk-...' ], $openai['env'] );
    }

    public function test_get_dynamic_providers_fills_defaults_for_missing_fields() {
        $apiData = [
            'minimal_provider' => [
                // Tidak ada name, api, env
            ],
        ];
        $this->mockHttpResponse( $apiData );

        $result = ModelCatalog::get_dynamic_providers();

        $this->assertEquals( 'minimal_provider', $result['minimal_provider']['name'],
            'Tanpa name field, gunakan provider_id sebagai name' );
        $this->assertEquals( '', $result['minimal_provider']['api'],
            'Tanpa api field, default empty string' );
        $this->assertEquals( [], $result['minimal_provider']['env'],
            'Tanpa env field, default empty array' );
    }

    // ================================================================
    // GET_ACTIVE_MODEL — Custom Model Priority
    // ================================================================

    public function test_get_active_model_returns_custom_model_when_set() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_models'] = [
            'openai' => 'gpt-4o-mini',
        ];

        $model = ModelCatalog::get_active_model( 'openai' );
        $this->assertEquals( 'gpt-4o-mini', $model );
    }

    public function test_get_active_model_falls_back_to_global_model() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_ai_model'] = 'gemini-2.0-pro';

        $model = ModelCatalog::get_active_model( 'gemini' );
        $this->assertEquals( 'gemini-2.0-pro', $model );
    }

    public function test_get_active_model_falls_back_to_provider_model() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_groq_model'] = 'llama-3.3-70b';

        $model = ModelCatalog::get_active_model( 'groq' );
        $this->assertEquals( 'llama-3.3-70b', $model );
    }

    public function test_get_active_model_prioritizes_custom_over_global() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_models'] = [
            'openai' => 'gpt-4o',
        ];
        $_autoblog_mock_options['autoblog_ai_model'] = 'gpt-3.5-turbo';

        $model = ModelCatalog::get_active_model( 'openai' );
        $this->assertEquals( 'gpt-4o', $model,
            'Custom model harus lebih prioritas dari global' );
    }

    // ================================================================
    // GET_ACTIVE_MODEL — Provider Key Normalization
    // ================================================================

    public function test_get_active_model_handles_gemini_provider_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_ai_model'] = 'gemini-2.5-pro';

        $model = ModelCatalog::get_active_model( 'gemini' );
        $this->assertEquals( 'gemini-2.5-pro', $model );
    }

    public function test_get_active_model_handles_google_provider_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_google_model'] = 'gemini-2.0-flash';

        $model = ModelCatalog::get_active_model( 'google' );
        $this->assertEquals( 'gemini-2.0-flash', $model );
    }

    public function test_get_active_model_handles_hf_provider_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_hf_model'] = 'mistralai/Mistral-7B';

        $model = ModelCatalog::get_active_model( 'hf' );
        $this->assertEquals( 'mistralai/Mistral-7B', $model );
    }

    // ================================================================
    // GET_ACTIVE_MODEL — Catalog Fallback
    // ================================================================

    public function test_get_active_model_falls_back_to_catalog_first_model() {
        // Tidak ada option custom/global/provider
        // Tapi catalog tersedia via transient
        $catalogData = [
            'openai' => [
                'gpt-4o'  => 'GPT-4o',
                'gpt-4-turbo' => 'GPT-4 Turbo',
            ],
        ];
        $GLOBALS['_autoblog_mock_transients']['autoblog_models_dev_cache_v2'] = $catalogData;

        $model = ModelCatalog::get_active_model( 'openai' );

        $this->assertEquals( 'gpt-4o', $model,
            'Fallback ke model pertama dari catalog' );
    }

    public function test_get_active_model_catalog_fallback_with_gemini_normalization() {
        // Provider 'gemini' → cari di key 'google' di catalog
        $catalogData = [
            'google' => [
                'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            ],
        ];
        $GLOBALS['_autoblog_mock_transients']['autoblog_models_dev_cache_v2'] = $catalogData;

        $model = ModelCatalog::get_active_model( 'gemini' );

        $this->assertEquals( 'gemini-1.5-pro', $model,
            'Gemini provider harus mencari di key "google" di catalog' );
    }

    public function test_get_active_model_catalog_fallback_with_hf_normalization() {
        // Provider 'hf' → cari di key 'huggingface' di catalog
        $catalogData = [
            'huggingface' => [
                'mistralai/Mistral-7B' => 'Mistral 7B',
            ],
        ];
        $GLOBALS['_autoblog_mock_transients']['autoblog_models_dev_cache_v2'] = $catalogData;

        $model = ModelCatalog::get_active_model( 'hf' );

        $this->assertEquals( 'mistralai/Mistral-7B', $model,
            'HF provider harus mencari di key "huggingface" di catalog' );
    }

    public function test_get_active_model_returns_empty_string_when_all_fallbacks_exhausted() {
        // Tanpa option, tanpa catalog, tanpa HTTP mock
        $model = ModelCatalog::get_active_model( 'nonexistent_provider' );

        $this->assertEquals( '', $model,
            'Semua fallback habis harus return empty string' );
    }

    // ================================================================
    // GET_ACTIVE_MODEL — Priority Chain Integration
    // ================================================================

    public function test_get_active_model_full_priority_chain() {
        global $_autoblog_mock_options;

        // Semua level ada — harus pilih yang paling prioritas
        $_autoblog_mock_options['autoblog_custom_api_models'] = [
            'openai' => 'gpt-4o-priority',
        ];
        $_autoblog_mock_options['autoblog_ai_model']        = 'gpt-4o-global';
        $_autoblog_mock_options['autoblog_openai_model']     = 'gpt-4o-provider';

        $model = ModelCatalog::get_active_model( 'openai' );
        $this->assertEquals( 'gpt-4o-priority', $model,
            'Custom model harus menang di priority chain' );
    }

    public function test_get_active_model_skip_custom_when_not_set_for_provider() {
        global $_autoblog_mock_options;

        // Custom models ada tapi untuk provider lain
        $_autoblog_mock_options['autoblog_custom_api_models'] = [
            'other_provider' => 'some-model',
        ];
        $_autoblog_mock_options['autoblog_ai_model'] = 'fallback-global';

        $model = ModelCatalog::get_active_model( 'openai' );
        $this->assertEquals( 'fallback-global', $model,
            'Custom model untuk provider lain harus di-skip' );
    }
}
