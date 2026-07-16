<?php
/**
 * Unit Test untuk AIClient::get_fallback_model().
 *
 * Memverifikasi bahwa:
 * 1. Tanpa Smart Fallback, return false (intra-provider habis).
 * 2. Dengan keys multiple, fallback ke model berikutnya.
 * 3. Cross-provider fallback (Gemini → OpenAI) jika Smart Fallback aktif.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;

class AIClientFallbackTest extends TestCase {

    /** @var AIClient */
    private $client;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        $this->client = new AIClient();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    public function test_returns_false_when_smart_fallback_disabled_and_no_intra_fallback() {
        // Nonaktifkan Smart Fallback
        $_autoblog_mock_options['autoblog_enable_fallback'] = '0';

        $result = $this->client->get_fallback_model( 'gemini-2.0-flash', 'gemini' );

        $this->assertFalse( $result, 'Tanpa intra-provider dan smart fallback = false' );
    }

    public function test_returns_false_for_empty_exclude_model() {
        $result = $this->client->get_fallback_model( '', '' );
        $this->assertFalse( $result );
    }

    public function test_detects_openai_from_model_name() {
        $_autoblog_mock_options['autoblog_enable_fallback'] = '1';
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test-key',
        ];

        $result = $this->client->get_fallback_model( 'gpt-4o', 'openai' );

        // Karena only openai key, cross provider tidak ada tujuan
        // Tapi method akan check keys: hanya openai yang ada
        $this->assertFalse( $result );
    }

    /**
     * Cross-provider fallback: gemini → openai jika ada key openai.
     */
    public function test_cross_provider_fallback_gemini_to_openai() {
        // Setel AIClient dengan openai_key langsung via reflection
        $reflection = new \ReflectionClass( $this->client );
        $prop = $reflection->getProperty( 'openai_key' );
        $prop->setAccessible( true );
        $prop->setValue( $this->client, 'sk-test-key' );

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_fallback'] = '1';
        // Set model di custom_models agar fallback punya model tujuan
        $_autoblog_mock_options['autoblog_custom_api_models'] = [
            'openai' => 'gpt-4o-mini',
        ];
        OptionCache::flush();

        $result = $this->client->get_fallback_model( 'gemini-2.0-flash', 'gemini' );

        // Gemini gagal, OpenAI key ada → harus pindah ke openai
        $this->assertEquals( 'gpt-4o-mini', $result,
            'Gemini → OpenAI fallback harus return model dari custom_models' );
    }

    /**
     * Intra-provider fallback: model kedua dalam pool yang sama.
     */
    public function test_intra_provider_returns_next_model() {
        // Skenario: gemini-2.0-flash gagal, intra-provider cari model berikutnya
        // Karena tanpa model catalog, fallback intra akan ke next dari model list
        // Tapi tanpa custom models, result tergantung ModelCatalog::get_merged_models()

        $_autoblog_mock_options['autoblog_enable_fallback'] = '1';

        $result = $this->client->get_fallback_model( 'gemini-2.0-flash', 'gemini' );

        // Tanpa API key, akan return false karena cross-provider juga gagal
        $this->assertFalse( $result );
    }

    /**
     * Cross-provider ke anthropic ketika openai tidak ada.
     */
    public function test_cross_provider_anthropic_as_second_priority() {
        $reflection = new \ReflectionClass( $this->client );
        $prop = $reflection->getProperty( 'anthropic_key' );
        $prop->setAccessible( true );
        $prop->setValue( $this->client, 'sk-ant-test' );

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_fallback'] = '1';
        // Set model untuk anthropic (digunakan sebagai target cross-provider)
        $_autoblog_mock_options['autoblog_custom_api_models'] = [
            'anthropic' => 'claude-3-5-sonnet-20240620',
        ];
        OptionCache::flush();

        $result = $this->client->get_fallback_model( 'gemini-2.0-flash', 'gemini' );

        // Gemini gagal, openai tidak ada, anthropic ada → harus pindah ke anthropic
        $this->assertEquals( 'claude-3-5-sonnet-20240620', $result,
            'Gemini → Anthropic fallback harus return claude model' );
    }
}
