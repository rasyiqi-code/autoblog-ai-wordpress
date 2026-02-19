<?php
/**
 * Unit Test untuk VectorStore::get_recent_topics()
 *
 * Memverifikasi bahwa method get_recent_topics() bekerja dengan benar
 * untuk fitur Data Source Mode = kb_only, dimana topik diambil dari
 * chunk terakhir di vector store.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

require_once dirname(__FILE__) . '/bootstrap.php';

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\VectorStore;

class VectorStoreTopicsTest extends TestCase {

    /**
     * Instance VectorStore untuk testing.
     * @var VectorStore
     */
    private $store;

    /**
     * Path temporer untuk file vector store selama test.
     * @var string
     */
    private $temp_store_path;

    protected function setUp(): void {
        parent::setUp();

        // Buat VectorStore instance (akan menggunakan temp dir dari mock wp_upload_dir)
        $this->store = new VectorStore();
        $this->temp_store_path = sys_get_temp_dir() . '/autoblog/vector_store.json';

        // Bersihkan store sebelum setiap test
        $this->store->clear();
    }

    protected function tearDown(): void {
        // Bersihkan file setelah test
        if ( file_exists( $this->temp_store_path ) ) {
            unlink( $this->temp_store_path );
        }
        parent::tearDown();
    }

    /**
     * Test: get_recent_topics() mengembalikan array kosong jika memory kosong.
     */
    public function test_get_recent_topics_returns_empty_when_no_data() {
        $topics = $this->store->get_recent_topics( 3 );
        $this->assertIsArray( $topics );
        $this->assertEmpty( $topics );
    }

    /**
     * Test: get_recent_topics() mengembalikan topik dengan struktur yang benar.
     *
     * Memverifikasi bahwa setiap topik memiliki key: title, text, source.
     */
    public function test_get_recent_topics_returns_correct_structure() {
        // Simulasikan data di memory via refleksi (tanpa memanggil API embedding)
        $this->injectMemoryChunks([
            ['id' => 'vec_1', 'text' => 'Ini adalah contoh teks pertama yang cukup panjang untuk membuat judul yang layak.', 'source' => 'file1.pdf', 'vector' => [0.1, 0.2]],
            ['id' => 'vec_2', 'text' => 'Teks kedua tentang teknologi AI dan machine learning yang sedang berkembang pesat.', 'source' => 'file2.docx', 'vector' => [0.3, 0.4]],
        ]);

        $topics = $this->store->get_recent_topics( 2 );

        $this->assertCount( 2, $topics );

        // Verifikasi struktur setiap topik
        foreach ( $topics as $topic ) {
            $this->assertArrayHasKey( 'title', $topic );
            $this->assertArrayHasKey( 'text', $topic );
            $this->assertArrayHasKey( 'source', $topic );
            $this->assertNotEmpty( $topic['title'] );
            $this->assertNotEmpty( $topic['text'] );
        }
    }

    /**
     * Test: get_recent_topics() mengembalikan topik terbaru terlebih dahulu.
     *
     * Urutan harus: chunk terakhir = topik pertama.
     */
    public function test_get_recent_topics_returns_newest_first() {
        $this->injectMemoryChunks([
            ['id' => 'vec_old', 'text' => 'Dokumen lama yang sudah lama ditambahkan ke knowledge base.', 'source' => 'old.pdf', 'vector' => [0.1]],
            ['id' => 'vec_mid', 'text' => 'Dokumen pertengahan yang ditambahkan setelah dokumen lama.', 'source' => 'mid.pdf', 'vector' => [0.2]],
            ['id' => 'vec_new', 'text' => 'Dokumen terbaru yang baru saja ditambahkan ke knowledge base.', 'source' => 'new.pdf', 'vector' => [0.3]],
        ]);

        $topics = $this->store->get_recent_topics( 2 );

        $this->assertCount( 2, $topics );
        // Yang terbaru harus di posisi pertama
        $this->assertEquals( 'new.pdf', $topics[0]['source'] );
        $this->assertEquals( 'mid.pdf', $topics[1]['source'] );
    }

    /**
     * Test: get_recent_topics() menghormati parameter limit.
     */
    public function test_get_recent_topics_respects_limit() {
        $this->injectMemoryChunks([
            ['id' => 'vec_1', 'text' => 'Chunk satu.', 'source' => 'src1', 'vector' => [0.1]],
            ['id' => 'vec_2', 'text' => 'Chunk dua.', 'source' => 'src2', 'vector' => [0.2]],
            ['id' => 'vec_3', 'text' => 'Chunk tiga.', 'source' => 'src3', 'vector' => [0.3]],
            ['id' => 'vec_4', 'text' => 'Chunk empat.', 'source' => 'src4', 'vector' => [0.4]],
            ['id' => 'vec_5', 'text' => 'Chunk lima.', 'source' => 'src5', 'vector' => [0.5]],
        ]);

        // Minta hanya 2, meski ada 5 chunks
        $topics = $this->store->get_recent_topics( 2 );
        $this->assertCount( 2, $topics );

        // Minta 10, tapi hanya ada 5
        $topics = $this->store->get_recent_topics( 10 );
        $this->assertCount( 5, $topics );
    }

    /**
     * Test: Judul di-truncate dengan benar pada batas kata.
     */
    public function test_get_recent_topics_truncates_title_correctly() {
        // Teks sangat panjang (> 80 karakter)
        $long_text = 'Ini adalah teks yang sangat panjang sekali dan melampaui batas delapan puluh karakter sehingga harus dipotong dengan elipsis.';

        $this->injectMemoryChunks([
            ['id' => 'vec_long', 'text' => $long_text, 'source' => 'long.txt', 'vector' => [0.1]],
        ]);

        $topics = $this->store->get_recent_topics( 1 );

        $this->assertCount( 1, $topics );
        // Judul tidak boleh lebih dari ~80 karakter + ellipsis
        $this->assertLessThanOrEqual( 84, mb_strlen( $topics[0]['title'] ) );
        // Teks asli harus tetap utuh
        $this->assertEquals( $long_text, $topics[0]['text'] );
    }

    /**
     * Test: Default source jika chunk tidak punya field source.
     */
    public function test_get_recent_topics_default_source() {
        $this->injectMemoryChunks([
            ['id' => 'vec_nosrc', 'text' => 'Chunk tanpa informasi source yang jelas.', 'vector' => [0.1]],
        ]);

        $topics = $this->store->get_recent_topics( 1 );

        $this->assertCount( 1, $topics );
        $this->assertEquals( 'knowledge_base', $topics[0]['source'] );
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Inject data langsung ke memory VectorStore via Reflection.
     *
     * Menghindari kebutuhan memanggil API embedding untuk test.
     *
     * @param array $chunks Array of chunk data.
     */
    private function injectMemoryChunks( array $chunks ) {
        $reflection = new \ReflectionClass( $this->store );
        $memory_prop = $reflection->getProperty( 'memory' );
        $memory_prop->setAccessible( true );
        $memory_prop->setValue( $this->store, $chunks );
    }
}
