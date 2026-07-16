<?php
/**
 * Unit Test untuk VectorStore::add_document() dan VectorStore::chunk_text().
 *
 * add_document(): public method yang menghubungkan chunk_text → create_embeddings_batch → save.
 * chunk_text():   private method untuk memecah teks panjang menjadi segmen-segmen kecil.
 *
 * Test ini menggunakan mock AIClient untuk create_embeddings_batch agar tidak perlu
 * koneksi API sungguhan. Vector di-inject via reflection untuk verifikasi memory state.
 *
 * Coverage:
 * - chunk_text: sentence boundary, max_length, whitespace, empty, single/long text
 * - add_document: empty text, single chunk, multiple chunks, batch partial failure,
 *   source tracking, provider passthrough, memory structure, file persistence
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

class VectorStoreDocumentTest extends TestCase {

    /** @var VectorStore */
    private $store;

    /** @var string Path ke file JSON store */
    private $store_path;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_embedding_provider'] = 'openai';

        // Bersihkan file store dari test sebelumnya SEBELUM constructor
        $store_dir = sys_get_temp_dir() . '/autoblog';
        if ( ! is_dir( $store_dir ) ) {
            mkdir( $store_dir, 0755, true );
        }
        $old_files = glob( $store_dir . '/vector_store_*.json' );
        foreach ( $old_files as $f ) {
            @unlink( $f );
        }

        $this->store = new VectorStore();

        // Baca store_path via reflection untuk cleanup nanti
        $reflection       = new \ReflectionClass( $this->store );
        $path_prop        = $reflection->getProperty( 'store_path' );
        $path_prop->setAccessible( true );
        $this->store_path = $path_prop->getValue( $this->store );
    }

    protected function tearDown(): void {
        $this->cleanupStoreFile();
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_embedding_provider'] );
        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // chunk_text() — PURE FUNCTION TESTS
    // ====================================================================

    public function test_chunk_text_returns_array_for_normal_text() {
        $chunks = $this->invokeChunkText( 'Ini adalah teks normal. Ini kalimat kedua.' );
        $this->assertIsArray( $chunks );
        $this->assertNotEmpty( $chunks );
    }

    public function test_chunk_text_splits_at_max_length() {
        // Buat 15 kalimat pendek, masing-masing ~13-15 chars
        $sentences = [];
        for ( $i = 1; $i <= 15; $i++ ) {
            $sentences[] = "Kalimat ke-{$i}.";
        }
        $text   = implode( ' ', $sentences ); // ~200+ chars total
        $chunks = $this->invokeChunkText( $text, 30 );

        // Harus terpecah menjadi beberapa chunk (15 kalimat @ ~14 chars, max 30 → ~7-8 chunk)
        $this->assertGreaterThan( 1, count( $chunks ), 'Teks panjang harus terpecah menjadi > 1 chunk' );

        // Setiap chunk tidak boleh melebihi max_length + trailing space
        foreach ( $chunks as $chunk ) {
            $this->assertLessThanOrEqual( 32, strlen( $chunk ), "Chunk terlalu panjang: '{$chunk}'" );
        }
    }

    public function test_chunk_text_preserves_sentence_boundaries() {
        $text   = 'Kalimat pertama adalah tentang AI. Kalimat kedua tentang machine learning. Kalimat ketiga tentang deep learning.';
        $chunks = $this->invokeChunkText( $text, 200 );

        // Dengan max_length 200, semua kalimat masuk dalam 1 chunk karena total < 200
        $this->assertCount( 1, $chunks );
        $this->assertStringContainsString( 'AI', $chunks[0] );
        $this->assertStringContainsString( 'machine learning', $chunks[0] );
        $this->assertStringContainsString( 'deep learning', $chunks[0] );
    }

    public function test_chunk_text_returns_empty_for_empty_text() {
        $chunks = $this->invokeChunkText( '' );
        $this->assertIsArray( $chunks );
        $this->assertEmpty( $chunks );
    }

    public function test_chunk_text_handles_whitespace() {
        $text   = "  Teks dengan   spasi berlebih.\n\nBaris baru.\tTab.  ";
        $chunks = $this->invokeChunkText( $text, 200 );

        $this->assertNotEmpty( $chunks );
        // Whitespace berlebih harus dibersihkan — teks tidak boleh mulai dengan spasi
        foreach ( $chunks as $chunk ) {
            $this->assertEquals( $chunk, trim( $chunk ), 'Chunk harus di-trim' );
            // Tidak boleh ada spasi ganda
            $this->assertDoesNotMatchRegularExpression( '/\s{2,}/', $chunk, 'Chunk tidak boleh mengandung spasi ganda' );
        }
    }

    public function test_chunk_text_short_text_returns_single_chunk() {
        $text   = 'Teks pendek.';
        $chunks = $this->invokeChunkText( $text, 800 );
        $this->assertCount( 1, $chunks );
        $this->assertEquals( 'Teks pendek.', $chunks[0] );
    }

    public function test_chunk_text_splits_into_multiple_chunks() {
        // Buat 5 kalimat pendek yang total > 100 chars
        $sentences = [];
        for ( $i = 1; $i <= 5; $i++ ) {
            $sentences[] = "Ini adalah kalimat percobaan yang ke-{$i} untuk test chunking.";
        }
        $text = implode( ' ', $sentences ); // pakai spasi, bukan titik — regex split (?<=[.?!])\s+

        // Ah, chunk_text menggunakan preg_split('/(?<=[.?!])\\s+/', ...)
        // Jadi kalimat harus diakhiri dengan . lalu spasi
        $text_with_periods = implode( '. ', $sentences ) . '.';

        $chunks = $this->invokeChunkText( $text_with_periods, 100 );

        // 5 kalimat @ ~50 chars + titik = ~255 chars total, max_length 100 → minimal 2 chunk
        $this->assertGreaterThanOrEqual( 2, count( $chunks ), '5 kalimat dengan max 100 harus jadi >= 2 chunk' );

        // Semua chunk harus non-kosong
        foreach ( $chunks as $chunk ) {
            $this->assertNotEmpty( trim( $chunk ) );
        }
    }

    public function test_chunk_text_without_sentence_boundary_returns_one_chunk() {
        // chunk_text menggunakan sentence boundary untuk splitting.
        // Tanpa tanda baca kalimat, seluruh teks dianggap 1 sentence → 1 chunk.
        $long   = str_repeat( 'kata ', 500 ); // ~2000 chars
        $chunks = $this->invokeChunkText( $long, 200 );

        // Tanpa sentence boundary, hanya 1 chunk yang dihasilkan
        $this->assertCount( 1, $chunks );
        $this->assertGreaterThan( 200, strlen( $chunks[0] ) );
    }

    // ====================================================================
    // add_document() — INTEGRATION WITH MOCK AI CLIENT
    // ====================================================================

    public function test_add_document_returns_zero_for_empty_text() {
        $result = $this->store->add_document( '', 'test.txt' );
        $this->assertSame( 0, $result );
    }

    public function test_add_document_adds_single_chunk_to_memory() {
        // Mock AIClient → create_embeddings_batch returns 1 vector
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Ini adalah teks dokumen.', 'vector' => [ 0.1, 0.2, 0.3 ], 'index' => 0 ],
            ] );
        $this->mockAiClient( $mockAi );

        $result = $this->store->add_document( 'Ini adalah teks dokumen.', 'test.txt' );

        $this->assertSame( 1, $result );

        // Verifikasi memory
        $memory = $this->getMemory();
        $this->assertCount( 1, $memory );
        $this->assertEquals( 'Ini adalah teks dokumen.', $memory[0]['text'] );
        $this->assertEquals( [ 0.1, 0.2, 0.3 ], $memory[0]['vector'] );
        $this->assertEquals( 'test.txt', $memory[0]['source'] );
    }

    public function test_add_document_multiple_chunks_stores_all() {
        // Gunakan teks sangat panjang untuk memastikan chunk_text menghasilkan > 1 chunk
        $long_text = '';
        for ( $i = 1; $i <= 30; $i++ ) {
            $long_text .= "Ini adalah kalimat ke-{$i} yang berisi kata-kata untuk test chunking dokumen panjang. ";
        }

        // Dengan max_length default 800, 30 kalimat @ ~80 chars = ~2400 chars → ~3 chunk
        // Tapi karena chunk_text akumulasi sampai max_length, estimasi:
        // 80 chars * 10 kalimat = 800 → chunk 1: kalimat 1-10, chunk 2: kalimat 11-20, chunk 3: kalimat 21-30

        // Mock AIClient — return vector sesuai jumlah chunk aktual
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturnCallback( function ( $texts, $provider ) {
                $results = [];
                foreach ( $texts as $i => $text ) {
                    $results[] = [
                        'text'   => $text,
                        'vector' => [ 0.1 * ( $i + 1 ), 0.2 * ( $i + 1 ), 0.3 * ( $i + 1 ) ],
                        'index'  => $i,
                    ];
                }
                return $results;
            } );
        $this->mockAiClient( $mockAi );

        $result = $this->store->add_document( $long_text, 'long.txt' );

        // Semua chunk harus tersimpan
        $this->assertGreaterThan( 1, $result, 'Teks panjang harus menghasilkan > 1 chunk' );

        $memory = $this->getMemory();
        $this->assertCount( $result, $memory );

        // Semua item harus punya struktur yang benar
        foreach ( $memory as $item ) {
            $this->assertArrayHasKey( 'id', $item );
            $this->assertArrayHasKey( 'text', $item );
            $this->assertArrayHasKey( 'vector', $item );
            $this->assertArrayHasKey( 'source', $item );
            $this->assertArrayHasKey( 'provider', $item );
            $this->assertNotEmpty( $item['id'] );
            $this->assertEquals( 'long.txt', $item['source'] );
            $this->assertEquals( 'openai', $item['provider'] );
        }
    }

    public function test_add_document_handles_partial_batch_failure() {
        // Mock AIClient — return 3 items, 1 dengan vector = false (gagal)
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Berhasil satu.', 'vector' => [ 0.1, 0.2 ], 'index' => 0 ],
                [ 'text' => 'Gagal embed.',  'vector' => false,         'index' => 1 ],
                [ 'text' => 'Berhasil dua.', 'vector' => [ 0.3, 0.4 ], 'index' => 2 ],
            ] );
        $this->mockAiClient( $mockAi );

        // Teks 3 kalimat — chunk_text menghasilkan 1 chunk, tapi mock return 3 item
        // Test ini fokus ke logika add_document: skip item dengan vector = false
        // Kita inject teks pendek agar hanya 1 chunk, lalu lihat berapa yang disimpan
        $text   = 'Kalimat pertama. Kalimat kedua. Kalimat ketiga.';
        $result = $this->store->add_document( $text, 'partial.txt' );

        // Mock return 3 items, 2 valid → success_count = 2
        $this->assertSame( 2, $result );

        $memory = $this->getMemory();
        $this->assertCount( 2, $memory );
        $this->assertEquals( 'Berhasil satu.', $memory[0]['text'] );
        $this->assertEquals( 'Berhasil dua.', $memory[1]['text'] );
    }

    public function test_add_document_handles_all_batch_failure() {
        // Mock AIClient — semua vector = false
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Gagal 1.', 'vector' => false, 'index' => 0 ],
                [ 'text' => 'Gagal 2.', 'vector' => false, 'index' => 1 ],
            ] );
        $this->mockAiClient( $mockAi );

        $text   = 'Teks pendek.';
        $result = $this->store->add_document( $text, 'fail.txt' );

        // Semua gagal → success_count = 0
        $this->assertSame( 0, $result );

        $memory = $this->getMemory();
        $this->assertEmpty( $memory );
    }

    public function test_add_document_saves_to_file() {
        // Mock AIClient
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Data untuk file.', 'vector' => [ 0.5, 0.5 ], 'index' => 0 ],
            ] );
        $this->mockAiClient( $mockAi );

        $this->store->add_document( 'Data untuk file.', 'file_test.txt' );

        // Verifikasi file tersimpan dengan benar
        $this->assertFileExists( $this->store_path );
        $content = file_get_contents( $this->store_path );
        $data    = json_decode( $content, true );

        $this->assertIsArray( $data );
        $this->assertCount( 1, $data );
        $this->assertEquals( 'file_test.txt', $data[0]['source'] );
        $this->assertEquals( [ 0.5, 0.5 ], $data[0]['vector'] );
    }

    public function test_add_document_returns_count_matching_stored_chunks() {
        // Mock AIClient — 4 chunks, semua valid
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Satu.',  'vector' => [ 0.1 ], 'index' => 0 ],
                [ 'text' => 'Dua.',   'vector' => [ 0.2 ], 'index' => 1 ],
                [ 'text' => 'Tiga.',  'vector' => [ 0.3 ], 'index' => 2 ],
                [ 'text' => 'Empat.','vector' => [ 0.4 ], 'index' => 3 ],
            ] );
        $this->mockAiClient( $mockAi );

        $text   = 'Kalimat satu. Kalimat dua. Kalimat tiga. Kalimat empat.';
        $result = $this->store->add_document( $text, 'count_test.txt' );

        $memory = $this->getMemory();
        $this->assertSame( count( $memory ), $result, 'Return value harus sama dengan jumlah item di memory' );
    }

    public function test_add_document_source_is_passed_correctly() {
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Source test.', 'vector' => [ 0.1, 0.2 ], 'index' => 0 ],
            ] );
        $this->mockAiClient( $mockAi );

        $this->store->add_document( 'Source test.', 'https://example.com/artikel.html' );

        $memory = $this->getMemory();
        $this->assertEquals( 'https://example.com/artikel.html', $memory[0]['source'] );
    }

    public function test_add_document_default_source_is_unknown() {
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Default source test.', 'vector' => [ 0.1 ], 'index' => 0 ],
            ] );
        $this->mockAiClient( $mockAi );

        $this->store->add_document( 'Default source test.' ); // tanpa arg source

        $memory = $this->getMemory();
        $this->assertEquals( 'unknown', $memory[0]['source'] );
    }

    public function test_add_document_passes_provider_to_batch() {
        // Verifikasi bahwa provider dari OptionCache diteruskan ke create_embeddings_batch
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->expects( $this->once() )
            ->method( 'create_embeddings_batch' )
            ->with(
                $this->anything(),
                $this->equalTo( 'openai' ) // Harusnya pakai provider dari mock options
            )
            ->willReturn( [] );
        $this->mockAiClient( $mockAi );

        $this->store->add_document( 'Provider test.', 'prov.txt' );
    }

    public function test_add_document_increments_memory_on_multiple_calls() {
        // Panggil add_document 3 kali, masing-masing 1 chunk
        for ( $i = 1; $i <= 3; $i++ ) {
            $mockAi = $this->createMock( AIClient::class );
            $mockAi->method( 'create_embeddings_batch' )
                ->willReturn( [
                    [ 'text' => "Dokumen ke-{$i}", 'vector' => [ $i * 0.1 ], 'index' => 0 ],
                ] );
            $this->mockAiClient( $mockAi );
            $this->store->add_document( "Dokumen ke-{$i}", "doc{$i}.txt" );
        }

        $memory = $this->getMemory();
        $this->assertCount( 3, $memory );
        $this->assertEquals( 'Dokumen ke-1', $memory[0]['text'] );
        $this->assertEquals( 'Dokumen ke-2', $memory[1]['text'] );
        $this->assertEquals( 'Dokumen ke-3', $memory[2]['text'] );
    }

    public function test_add_document_handles_null_vectors_from_batch() {
        // create_embeddings_batch bisa return vector = null (graceful degradation)
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'create_embeddings_batch' )
            ->willReturn( [
                [ 'text' => 'Null vector test.', 'vector' => null, 'index' => 0 ],
            ] );
        $this->mockAiClient( $mockAi );

        $result = $this->store->add_document( 'Null vector test.', 'null.txt' );

        // null !== false, jadi isset($result['vector']) && $result['vector'] !== false
        // null !== false → TRUE, isset(null) → FALSE → skip
        // Karena isset(null) false, item tidak ditambahkan
        $this->assertSame( 0, $result );

        $memory = $this->getMemory();
        $this->assertEmpty( $memory );
    }

    // ====================================================================
    // HELPER METHODS
    // ====================================================================

    /**
     * Panggil private method chunk_text via reflection.
     *
     * @param string $text      Teks yang akan di-chunk.
     * @param int    $max_length Maksimum panjang per chunk.
     * @return array
     */
    private function invokeChunkText( string $text, int $max_length = 800 ): array {
        $reflection = new \ReflectionClass( $this->store );
        $method     = $reflection->getMethod( 'chunk_text' );
        $method->setAccessible( true );
        return $method->invokeArgs( $this->store, [ $text, $max_length ] );
    }

    /**
     * Inject mock AIClient ke VectorStore via reflection.
     *
     * @param AIClient $mock Mock AIClient instance.
     */
    private function mockAiClient( $mock ) {
        $reflection = new \ReflectionClass( $this->store );
        $prop       = $reflection->getProperty( 'ai_client' );
        $prop->setAccessible( true );
        $prop->setValue( $this->store, $mock );
    }

    /**
     * Ambil memory dari VectorStore via reflection.
     *
     * @return array
     */
    private function getMemory(): array {
        $reflection = new \ReflectionClass( $this->store );
        $prop       = $reflection->getProperty( 'memory' );
        $prop->setAccessible( true );
        return $prop->getValue( $this->store );
    }

    /**
     * Bersihkan file store setelah test.
     */
    private function cleanupStoreFile() {
        if ( ! empty( $this->store_path ) && file_exists( $this->store_path ) ) {
            @unlink( $this->store_path );
        }

        // Hapus juga file temporary yang mungkin tersisa
        $store_dir = sys_get_temp_dir() . '/autoblog';
        if ( is_dir( $store_dir ) ) {
            $tmp_files = glob( $store_dir . '/vs_tmp_*' );
            foreach ( $tmp_files as $f ) {
                @unlink( $f );
            }
        }
    }
}
