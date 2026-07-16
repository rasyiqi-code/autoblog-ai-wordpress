<?php
/**
 * Integration Test untuk VectorStore::search().
 *
 * Menguji pipeline search secara end-to-end dengan mock AIClient:
 * 1. Inject data langsung ke memory via reflection.
 * 2. Mock AIClient::create_embedding() untuk return vector yang diketahui.
 * 3. Verifikasi cosine similarity, threshold filtering, limit, dan edge cases.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      regression
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\VectorStore;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;

class VectorStoreSearchIntegrationTest extends TestCase {

    /** @var VectorStore */
    private $store;

    /** @var string Path ke file JSON store */
    private $store_path;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_embedding_provider'] = 'openai';

        // Bersihkan file store dari test sebelumnya SEBELUM constructor dipanggil
        $store_dir = sys_get_temp_dir() . '/autoblog';
        $old_files = glob( $store_dir . '/vector_store_*.json' );
        foreach ( $old_files as $f ) {
            @unlink( $f );
        }

        $this->store = new VectorStore();

        // Store path dari reflection
        $reflection = new \ReflectionClass( $this->store );
        $prop       = $reflection->getProperty( 'store_path' );
        $prop->setAccessible( true );
        $this->store_path = $prop->getValue( $this->store );
    }

    protected function tearDown(): void {
        $this->cleanupStoreFile();
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_embedding_provider'] );
        OptionCache::flush();
        parent::tearDown();
    }

    // ================================================================
    // TEST 1: Empty store → empty array
    // ================================================================

    public function test_returns_empty_array_for_empty_store() {
        $this->injectMemoryData( [] );
        $result = $this->store->search( 'any query' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // TEST 2: Search returns most relevant chunk
    // ================================================================

    public function test_returns_most_relevant_chunk() {
        // Inject 2 chunks dengan vectors yang diketahui
        $this->injectMemoryData( [
            [
                'id'     => 'vec_ai',
                'text'   => 'Artificial intelligence dan machine learning sedang berkembang pesat di industri teknologi.',
                'vector' => [ 0.1, 0.2, 0.3 ],
                'source' => 'artikel_ai.pdf',
            ],
            [
                'id'     => 'vec_cooking',
                'text'   => 'Resep masakan nusantara menggunakan bumbu tradisional yang kaya rasa.',
                'vector' => [ 0.5, -0.3, 0.0 ], // Orthogonal ke query: 0.15*0.5 + 0.25*(-0.3) + 0.35*0 = 0
                'source' => 'resep.docx',
            ],
        ] );

        // Mock AIClient — return vector mirip vec_ai
        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.15, 0.25, 0.35 ] ); // Cosine similarity ~0.99 dengan vec_ai

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'teknologi AI', 3 );

        $this->assertNotEmpty( $results );
        $this->assertCount( 1, $results, 'Hanya vec_ai yang melewati threshold > 0.4' );
        $this->assertStringContainsString( 'Artificial intelligence', $results[0]['text'] );
        $this->assertArrayHasKey( 'score', $results[0] );
        $this->assertGreaterThan( 0.5, $results[0]['score'] );
        // Vector tidak boleh dikembalikan (sudah di-unset)
        $this->assertArrayNotHasKey( 'vector', $results[0] );
    }

    // ================================================================
    // TEST 3: Search respects limit parameter
    // ================================================================

    public function test_respects_limit_parameter() {
        // 3 chunks dengan vectors serupa
        $this->injectMemoryData( [
            [
                'id'     => 'vec_1',
                'text'   => 'Chunk pertama tentang teknologi informasi.',
                'vector' => [ 0.1, 0.2 ],
                'source' => 'src1',
            ],
            [
                'id'     => 'vec_2',
                'text'   => 'Chunk kedua tentang perkembangan digital.',
                'vector' => [ 0.15, 0.25 ],
                'source' => 'src2',
            ],
            [
                'id'     => 'vec_3',
                'text'   => 'Chunk ketiga tentang inovasi teknologi masa depan.',
                'vector' => [ 0.12, 0.22 ],
                'source' => 'src3',
            ],
        ] );

        // Mock AIClient — return vector mirip semua chunk
        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.11, 0.21 ] );

        $this->mockAiClient( $mockAiClient );

        // Minta hanya 2 hasil
        $results = $this->store->search( 'teknologi', 2 );
        $this->assertCount( 2, $results );

        // Minta 10 hasil — tapi hanya ada 3
        $results = $this->store->search( 'teknologi', 10 );
        $this->assertCount( 3, $results );
    }

    // ================================================================
    // TEST 4: Returns empty when query embedding fails
    // ================================================================

    public function test_returns_empty_when_no_query_embedding() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_exists',
                'text'   => 'Data exists meskipun query gagal di-embed.',
                'vector' => [ 0.1, 0.2 ],
                'source' => 'test',
            ],
        ] );

        // Mock AIClient — return false (embedding gagal)
        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( false );

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'gagal embed' );
        $this->assertIsArray( $results );
        $this->assertEmpty( $results );
    }

    // ================================================================
    // TEST 5: Filters results below threshold (0.4)
    // ================================================================

    public function test_filters_below_threshold() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_similar',
                'text'   => 'Sangat relevan dengan query pencarian.',
                'vector' => [ 0.1, 0.2 ], // Similar ke query
                'source' => 'relevant',
            ],
            [
                'id'     => 'vec_dissimilar',
                'text'   => 'Topik yang sangat berbeda dan tidak berhubungan sama sekali.',
                // Orthogonal ke query [0.15, 0.25]: 0.15*0.5 + 0.25*(-0.3) = 0
                'vector' => [ 0.5, -0.3 ],
                'source' => 'irrelevant',
            ],
        ] );

        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.15, 0.25 ] ); // Mirip vec_similar

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'query relevan', 5 );

        // Hanya vec_similar yang melewati threshold
        $this->assertCount( 1, $results );
        $this->assertEquals( 'relevant', $results[0]['source'] );
        $this->assertGreaterThan( 0.4, $results[0]['score'] );
    }

    // ================================================================
    // TEST 6: Cosine similarity — identical vectors = 1.0
    // ================================================================

    public function test_cosine_similarity_identical_vectors() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_identical',
                'text'   => 'Teks yang sama persis dengan query.',
                'vector' => [ 0.5, 0.5, 0.5 ],
                'source' => 'test',
            ],
        ] );

        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.5, 0.5, 0.5 ] );

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'query identical', 1 );
        $this->assertNotEmpty( $results );
        // Cosine similarity of identical vectors should be ~1.0
        $this->assertEqualsWithDelta( 1.0, $results[0]['score'], 0.001 );
    }

    // ================================================================
    // TEST 7: Cosine similarity — opposite vectors = -1.0
    // ================================================================

    public function test_cosine_similarity_opposite_vectors() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_opposite',
                'text'   => 'Teks yang berlawanan dengan query.',
                'vector' => [ -0.5, -0.5 ],
                'source' => 'test',
            ],
        ] );

        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.5, 0.5 ] );

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'query opposite', 1 );
        // Opposite vectors → score -1.0 → below threshold → not returned
        $this->assertEmpty( $results );
    }

    // ================================================================
    // TEST 8: Cosine similarity — zero vector = 0
    // ================================================================

    public function test_cosine_similarity_zero_vector() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_zero',
                'text'   => 'Zero vector test.',
                'vector' => [ 0.0, 0.0 ],
                'source' => 'test',
            ],
        ] );

        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.5, 0.5 ] );

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'query with zero', 1 );
        // Zero vector → cosine_similarity returns 0 → below threshold
        $this->assertEmpty( $results );
    }

    // ================================================================
    // TEST 9: Skips items without vector field
    // ================================================================

    public function test_skips_items_without_vector() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_with_vector',
                'text'   => 'Item yang punya vector dan relevan.',
                'vector' => [ 0.1, 0.2 ],
                'source' => 'good',
            ],
            [
                'id'     => 'vec_no_vector',
                'text'   => 'Item tanpa vector (embedding gagal).',
                // no 'vector' key!
                'source' => 'bad',
            ],
        ] );

        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.15, 0.25 ] );

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'query', 5 );
        $this->assertCount( 1, $results );
        $this->assertEquals( 'good', $results[0]['source'] );
    }

    // ================================================================
    // TEST 10: Different dimension vectors return score 0
    // ================================================================

    public function test_different_dimension_vectors() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_3d',
                'text'   => 'Vector dengan 3 dimensi.',
                'vector' => [ 0.1, 0.2, 0.3 ],
                'source' => '3d',
            ],
        ] );

        // Query vector hanya 2 dimensi — berbeda dimensi
        $mockAiClient = $this->createMock( AIClient::class );
        $mockAiClient->method( 'create_embedding' )
            ->willReturn( [ 0.5, 0.5 ] );

        $this->mockAiClient( $mockAiClient );

        $results = $this->store->search( 'query', 1 );
        // Bug #4 fix: dimensi berbeda → score 0 → below threshold → empty
        $this->assertEmpty( $results );
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Inject data langsung ke memory VectorStore via Reflection.
     */
    private function injectMemoryData( array $chunks ) {
        $reflection = new \ReflectionClass( $this->store );
        $prop       = $reflection->getProperty( 'memory' );
        $prop->setAccessible( true );
        $prop->setValue( $this->store, $chunks );
    }

    /**
     * Inject mock AIClient ke VectorStore via Reflection.
     */
    private function mockAiClient( $mock ) {
        $reflection = new \ReflectionClass( $this->store );
        $prop       = $reflection->getProperty( 'ai_client' );
        $prop->setAccessible( true );
        $prop->setValue( $this->store, $mock );
    }

    /**
     * Bersihkan file store.
     */
    private function cleanupStoreFile() {
        if ( ! empty( $this->store_path ) && file_exists( $this->store_path ) ) {
            @unlink( $this->store_path );
        }
    }
}
