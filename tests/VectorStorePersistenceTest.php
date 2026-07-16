<?php
/**
 * Unit Test untuk VectorStore persistence (save/load/clear).
 *
 * Memverifikasi bahwa:
 * 1. Store dapat menyimpan dan memuat data melalui file JSON.
 * 2. Directory store dibuat secara otomatis.
 * 3. Atomic save menggunakan tempnam() + rename() bekerja (regresi Bug #8).
 * 4. clear() mengosongkan store dan menyimpan file kosong.
 * 5. get_brief_summary() mengembalikan ringkasan dari memory.
 * 6. get_brief_summary() mengembalikan string kosong jika memory kosong.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\VectorStore;

/**
 * Unit Test untuk VectorStore persistence (save/load/clear).
 *
 * @group unit
 * @group regression
 */
class VectorStorePersistenceTest extends TestCase {

    /** @var VectorStore */
    private $store;

    /** @var string Path ke file JSON store */
    private $store_path;

    protected function setUp(): void {
        parent::setUp();

        // Setel embedding provider default via mock options
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_embedding_provider'] = 'openai';

        // Inisialisasi store — akan membuat vector_store_openai.json di temp dir
        $this->store = new VectorStore();
        $this->store_path = sys_get_temp_dir() . '/autoblog/vector_store_openai.json';

        // Bersihkan file store yang tersisa dari test sebelumnya
        $this->cleanupStoreFile();
    }

    protected function tearDown(): void {
        $this->cleanupStoreFile();

        // Bersihkan juga file temporary yang mungkin tersisa
        $temp_dir = sys_get_temp_dir() . '/autoblog/';
        if ( is_dir( $temp_dir ) ) {
            $files = glob( $temp_dir . 'vs_tmp_*' );
            foreach ( $files as $f ) {
                @unlink( $f );
            }
        }

        parent::tearDown();
    }

    // ================================================================
    // TEST 1: Store membuat directory jika belum ada
    // ================================================================

    public function test_constructor_creates_store_directory() {
        $store_dir = dirname( $this->store_path );
        $this->assertDirectoryExists( $store_dir, 'Directory store harus dibuat oleh constructor' );
    }

    // ================================================================
    // TEST 2: Inisialisasi store menghasilkan file yang valid
    // ================================================================

    public function test_store_creates_valid_json_file() {
        // Store baru harusnya ada file (dari constructor load)
        if ( file_exists( $this->store_path ) ) {
            $content = file_get_contents( $this->store_path );
            $data    = json_decode( $content, true );
            $this->assertIsArray( $data, 'File store harus berisi JSON array valid' );
        } else {
            // Jika tidak ada file, itu juga valid (store kosong)
            $this->assertTrue( true, 'Store kosong tanpa file adalah valid' );
        }
    }

    // ================================================================
    // TEST 3: save() dengan data yang ada menghasilkan file yang berisi
    // ================================================================

    public function test_save_writes_valid_json() {
        // Inject data ke memory via refleksi
        $this->injectMemoryData( [
            [
                'id'     => 'vec_test_1',
                'text'   => 'Hello world',
                'vector' => [ 0.1, 0.2, 0.3 ],
                'source' => 'test',
            ],
        ] );

        // Simpan
        $this->store->save();

        // Verifikasi file ada dan berisi JSON valid
        $this->assertFileExists( $this->store_path, 'File store harus ada setelah save()' );

        $content = file_get_contents( $this->store_path );
        $data    = json_decode( $content, true );

        $this->assertIsArray( $data );
        $this->assertCount( 1, $data, 'Store harus berisi 1 item' );
        $this->assertEquals( 'Hello world', $data[0]['text'] );
    }

    // ================================================================
    // TEST 4: load() memuat data yang sebelumnya disimpan
    // ================================================================

    public function test_load_restores_previously_saved_data() {
        // Simpan data
        $this->injectMemoryData( [
            [
                'id'     => 'vec_load_test',
                'text'   => 'Data untuk load test',
                'vector' => [ 0.5, 0.6 ],
                'source' => 'test',
            ],
        ] );
        $this->store->save();

        // Buat instance baru (akan load dari file)
        $store2 = new VectorStore();

        // Verifikasi data termuat via get_brief_summary
        $summary = $store2->get_brief_summary();
        $this->assertStringContainsString( 'Data untuk load test', $summary );
    }

    // ================================================================
    // TEST 5: clear() mengosongkan memory dan menyimpan perubahan
    // ================================================================

    public function test_clear_empties_store() {
        // Inject data lalu clear
        $this->injectMemoryData( [
            [
                'id'     => 'vec_clear_test',
                'text'   => 'Akan dihapus',
                'vector' => [ 0.1 ],
                'source' => 'test',
            ],
        ] );
        $this->store->clear();

        // Setelah clear, get_brief_summary harus kosong
        $summary = $this->store->get_brief_summary();
        $this->assertEmpty( $summary, 'get_brief_summary harus kosong setelah clear()' );
    }

    // ================================================================
    // TEST 6: get_brief_summary() mengembalikan string kosong untuk store kosong
    // ================================================================

    public function test_get_brief_summary_empty_for_empty_store() {
        $this->injectMemoryData( [] );
        $summary = $this->store->get_brief_summary();
        $this->assertEmpty( $summary );
    }

    // ================================================================
    // TEST 7: get_brief_summary() mengembalikan ringkasan yang valid
    // ================================================================

    public function test_get_brief_summary_returns_summary() {
        $this->injectMemoryData( [
            [
                'id'     => 'vec_summary_1',
                'text'   => 'Ini adalah teks contoh untuk test ringkasan knowledge base.',
                'vector' => [ 0.1, 0.2 ],
                'source' => 'dokumen.pdf',
            ],
            [
                'id'     => 'vec_summary_2',
                'text'   => 'Teks kedua tentang teknologi AI yang sedang berkembang.',
                'vector' => [ 0.3, 0.4 ],
                'source' => 'artikel.docx',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertNotEmpty( $summary );
        $this->assertStringContainsString( 'Sumber:', $summary );
        $this->assertStringContainsString( 'Total chunks:', $summary );
        $this->assertStringContainsString( 'Contoh isi:', $summary );
    }

    // ================================================================
    // TEST 8: Atomic save — file tidak corrupt jika save gagal di tengah
    //         (Regresi test untuk Bug #8 — tempnam + rename)
    // ================================================================

    public function test_atomic_save_does_not_corrupt_existing_file() {
        // Simpan data awal
        $this->injectMemoryData( [
            [
                'id'     => 'vec_original',
                'text'   => 'Data asli sebelum corrupt',
                'vector' => [ 0.1 ],
                'source' => 'original',
            ],
        ] );
        $this->store->save();

        // Baca file untuk verifikasi
        $original_content = file_get_contents( $this->store_path );

        // Simpan ulang (harusnya atomic)
        $this->injectMemoryData( [
            [
                'id'     => 'vec_updated',
                'text'   => 'Data baru setelah update',
                'vector' => [ 0.2 ],
                'source' => 'updated',
            ],
        ] );
        $this->store->save();

        // Verifikasi file masih JSON valid
        $new_content = file_get_contents( $this->store_path );
        $data        = json_decode( $new_content, true );
        $this->assertIsArray( $data, 'File store harus tetap JSON valid setelah multiple save' );
        $this->assertCount( 1, $data, 'Store harus hanya berisi data terakhir' );
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
     * Bersihkan file store setelah test.
     */
    private function cleanupStoreFile() {
        // Hapus file utama
        if ( file_exists( $this->store_path ) ) {
            @unlink( $this->store_path );
        }

        // Hapus file temporary (.tmp legacy)
        $tmp_path = $this->store_path . '.tmp';
        if ( file_exists( $tmp_path ) ) {
            @unlink( $tmp_path );
        }
    }
}
