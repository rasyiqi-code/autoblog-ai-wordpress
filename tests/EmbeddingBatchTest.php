<?php
/**
 * Unit Test untuk AIEmbeddingTrait::create_embeddings_batch().
 *
 * Memverifikasi bahwa:
 * 1. Batch embedding mengembalikan array kosong untuk input kosong.
 * 2. Batch embedding mengembalikan struktur yang benar ('text', 'vector', 'index').
 * 3. Tanpa API key, semua vector = null (graceful degradation via build_batch_result).
 * 4. Urutan teks dipertahankan.
 * 5. Provider default ke 'openai' jika tidak diberikan.
 * 6. Provider yang tidak dikenal menggunakan sequential fallback.
 * 7. Teks kosong setelah sanitasi menghasilkan vector = false.
 * 8. Gemimi batch juga mengembalikan struktur yang benar.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      regression
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;

/**
 * Unit Test untuk AIEmbeddingTrait::create_embeddings_batch().
 *
 * @group unit
 * @group regression
 */
class EmbeddingBatchTest extends TestCase {

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

    // ================================================================
    // TEST 1: Input kosong → array kosong
    // ================================================================

    public function test_returns_empty_array_for_empty_texts() {
        $result = $this->client->create_embeddings_batch( [] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // TEST 2: Tanpa API key → graceful degradation (vectors = null)
    // ================================================================

    public function test_returns_null_vectors_when_no_api_keys() {
        $texts  = [ 'Hello world', 'Embedding batch test', 'Third chunk' ];
        $result = $this->client->create_embeddings_batch( $texts, 'openai' );

        $this->assertCount( 3, $result );

        foreach ( $result as $i => $item ) {
            $this->assertArrayHasKey( 'text', $item, "Item {$i} harus punya key 'text'" );
            $this->assertArrayHasKey( 'vector', $item, "Item {$i} harus punya key 'vector'" );
            $this->assertArrayHasKey( 'index', $item, "Item {$i} harus punya key 'index'" );
            $this->assertNull( $item['vector'], "Item {$i} harus punya vector = null (no API key)" );
        }
    }

    // ================================================================
    // TEST 3: Urutan teks dipertahankan setelah batch
    // ================================================================

    public function test_preserves_text_order() {
        $texts  = [ 'First chunk', 'Second chunk', 'Third chunk' ];
        $result = $this->client->create_embeddings_batch( $texts, 'openai' );

        $this->assertCount( 3, $result );
        $this->assertEquals( 'First chunk', $result[0]['text'], 'Index 0 harus teks pertama' );
        $this->assertEquals( 'Second chunk', $result[1]['text'], 'Index 1 harus teks kedua' );
        $this->assertEquals( 'Third chunk', $result[2]['text'], 'Index 2 harus teks ketiga' );
        $this->assertEquals( 0, $result[0]['index'] );
        $this->assertEquals( 1, $result[1]['index'] );
        $this->assertEquals( 2, $result[2]['index'] );
    }

    // ================================================================
    // TEST 4: Provider default ke 'openai' jika kosong
    // ================================================================

    public function test_defaults_provider_to_openai_when_empty() {
        $texts  = [ 'Default provider test' ];
        $result = $this->client->create_embeddings_batch( $texts, '' );

        $this->assertCount( 1, $result );
        // Saat tanpa API key, openai batch mengembalikan null vectors
        $this->assertNull( $result[0]['vector'] );
        $this->assertEquals( 'Default provider test', $result[0]['text'] );
    }

    // ================================================================
    // TEST 5: Provider tidak dikenal → sequential fallback
    // ================================================================

    public function test_unknown_provider_uses_sequential_fallback() {
        $texts  = [ 'Sequential fallback test' ];
        $result = $this->client->create_embeddings_batch( $texts, 'unknown_provider' );

        $this->assertCount( 1, $result );
        $this->assertArrayHasKey( 'text', $result[0] );
        $this->assertArrayHasKey( 'vector', $result[0] );
        $this->assertArrayHasKey( 'index', $result[0] );
        // Sequential fallback dengan no keys → dispatch_embedding returns false
        // Lalu $vector ?: false → false, bukan null
        $this->assertFalse( $result[0]['vector'], 'Sequential fallback harus false, bukan null' );
    }

    // ================================================================
    // TEST 6: Single chunk tetap berfungsi
    // ================================================================

    public function test_single_chunk_returns_correct_structure() {
        $texts  = [ 'Just one chunk' ];
        $result = $this->client->create_embeddings_batch( $texts, 'openai' );

        $this->assertCount( 1, $result );
        $this->assertEquals( 'Just one chunk', $result[0]['text'] );
        $this->assertEquals( 0, $result[0]['index'] );
    }

    // ================================================================
    // TEST 7: Banyak chunk (10+) tetap mengembalikan semua hasil
    // ================================================================

    public function test_many_chunks_returns_all_results() {
        $texts = [];
        for ( $i = 0; $i < 15; $i++ ) {
            $texts[] = "Chunk number {$i}";
        }

        $result = $this->client->create_embeddings_batch( $texts, 'openai' );

        $this->assertCount( 15, $result );
        $this->assertEquals( 'Chunk number 0', $result[0]['text'] );
        $this->assertEquals( 'Chunk number 14', $result[14]['text'] );
        $this->assertEquals( 14, $result[14]['index'] );
    }

    // ================================================================
    // TEST 8: Gemimi batch — struktur output benar
    // ================================================================

    public function test_gemini_batch_returns_correct_structure() {
        $texts  = [ 'Gemini test one', 'Gemini test two' ];
        $result = $this->client->create_embeddings_batch( $texts, 'gemini' );

        $this->assertCount( 2, $result );
        foreach ( $result as $i => $item ) {
            $this->assertArrayHasKey( 'text', $item, "Gemini item {$i}: harus punya text" );
            $this->assertArrayHasKey( 'vector', $item, "Gemini item {$i}: harus punya vector" );
            $this->assertArrayHasKey( 'index', $item, "Gemini item {$i}: harus punya index" );
            // Gemini tanpa API key → build_batch_result($texts, null) → null
            $this->assertNull( $item['vector'], "Gemini item {$i}: vector harus null (no API key)" );
        }
    }

    // ================================================================
    // TEST 9: HuggingFace batch — struktur output benar
    // ================================================================

    public function test_hf_batch_returns_correct_structure() {
        $texts  = [ 'HF test' ];
        $result = $this->client->create_embeddings_batch( $texts, 'hf' );

        $this->assertCount( 1, $result );
        $this->assertArrayHasKey( 'text', $result[0] );
        $this->assertArrayHasKey( 'vector', $result[0] );
        $this->assertArrayHasKey( 'index', $result[0] );
    }

    // ================================================================
    // TEST 10: Provider 'gemini_001' diperlakukan sama seperti 'gemini'
    // ================================================================

    public function test_gemini_001_provider_uses_gemini_path() {
        $texts  = [ 'Gemini 001 test' ];
        $result = $this->client->create_embeddings_batch( $texts, 'gemini_001' );

        $this->assertCount( 1, $result );
        // gemini_001 juga lewat google_embeddings_batch → build_batch_result($texts, null) → null
        $this->assertNull( $result[0]['vector'], 'gemini_001 juga harus null (pakai google_embeddings_batch)' );
    }

    // ================================================================
    // TEST 11: build_batch_result — item structur (diuji via openai batch gagal)
    // ================================================================

    public function test_result_items_have_expected_keys() {
        $texts  = [ 'Alpha', 'Beta', 'Gamma' ];
        $result = $this->client->create_embeddings_batch( $texts, 'openai' );

        $expected_keys = [ 'text', 'vector', 'index' ];
        foreach ( $result as $i => $item ) {
            $item_keys = array_keys( $item );
            sort( $item_keys );
            sort( $expected_keys );
            $this->assertEquals( $expected_keys, $item_keys, "Item {$i} harus memiliki key yang tepat" );
        }
    }
}
