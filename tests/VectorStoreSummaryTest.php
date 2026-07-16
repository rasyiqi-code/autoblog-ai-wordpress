<?php
/**
 * Unit Test untuk VectorStore::get_brief_summary().
 *
 * Pure function test — tidak perlu mock AIClient, tidak perlu HTTP, tidak perlu file.
 * Cukup instantiate VectorStore dan inject data langsung ke memory via reflection,
 * lalu verifikasi format output dari get_brief_summary().
 *
 * get_brief_summary() mengembalikan string dengan format:
 *   Sumber: source1, source2
 *   Total chunks: N
 *   Contoh isi:
 *   - snippet 100 chars...
 *   - snippet 100 chars...
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\VectorStore;
use Autoblog\Utils\OptionCache;

class VectorStoreSummaryTest extends TestCase {

    /** @var VectorStore */
    private $store;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_embedding_provider'] = 'openai';

        $this->store = new VectorStore();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_embedding_provider'] );
        OptionCache::flush();

        // Bersihkan file store jika ada
        $reflection = new \ReflectionClass( $this->store );
        $path_prop  = $reflection->getProperty( 'store_path' );
        $path_prop->setAccessible( true );
        $store_path = $path_prop->getValue( $this->store );
        if ( file_exists( $store_path ) ) {
            @unlink( $store_path );
        }

        parent::tearDown();
    }

    // ====================================================================
    // EMPTY MEMORY
    // ====================================================================

    public function test_returns_empty_string_for_empty_memory() {
        $this->injectMemory( [] );
        $this->assertSame( '', $this->store->get_brief_summary() );
    }

    // ====================================================================
    // FORMAT STRUCTURE
    // ====================================================================

    public function test_summary_contains_expected_sections() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Ini adalah teks contoh untuk test ringkasan knowledge base.',
                'vector' => [ 0.1, 0.2 ],
                'source' => 'dokumen.pdf',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertStringContainsString( 'Sumber:', $summary );
        $this->assertStringContainsString( 'Total chunks:', $summary );
        $this->assertStringContainsString( 'Contoh isi:', $summary );
    }

    public function test_summary_format_newlines() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Teks contoh.',
                'vector' => [ 0.1 ],
                'source' => 'file.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // Format harus: Sumber: ...\nTotal chunks: ...\nContoh isi:\n- ...
        $lines = explode( "\n", $summary );
        $this->assertGreaterThanOrEqual( 3, count( $lines ) );
        $this->assertStringStartsWith( 'Sumber:', $lines[0] );
        $this->assertStringStartsWith( 'Total chunks:', $lines[1] );
        $this->assertEquals( 'Contoh isi:', $lines[2] );
    }

    // ====================================================================
    // SOURCE NAME HANDLING
    // ====================================================================

    public function test_summary_contains_source_filename() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Konten dari dokumen PDF.',
                'vector' => [ 0.1, 0.2 ],
                'source' => 'dokumen.pdf',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertStringContainsString( 'dokumen.pdf', $summary );
    }

    public function test_summary_uses_basename_for_path_sources() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Konten dari path panjang.',
                'vector' => [ 0.1 ],
                'source' => '/var/www/html/wp-content/uploads/2025/artikel-panjang.pdf',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // basename() harus mengambil 'artikel-panjang.pdf'
        $this->assertStringContainsString( 'artikel-panjang.pdf', $summary );
        $this->assertStringNotContainsString( '/var/www/html', $summary );
    }

    public function test_summary_uses_basename_for_url_sources() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Konten dari URL.',
                'vector' => [ 0.1 ],
                'source' => 'https://example.com/artikel/teknologi/ai-machine-learning.html',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // basename() dari URL path → 'ai-machine-learning.html'
        $this->assertStringContainsString( 'ai-machine-learning.html', $summary );
        $this->assertStringNotContainsString( 'https://', $summary );
    }

    public function test_summary_handles_array_source_with_name_key() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Konten dengan source array name.',
                'vector' => [ 0.1 ],
                'source' => [ 'name' => 'Custom Name', 'url' => 'https://example.com' ],
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertStringContainsString( 'Custom Name', $summary );
    }

    public function test_summary_handles_array_source_with_source_key() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Konten dengan source array source key.',
                'vector' => [ 0.1 ],
                'source' => [ 'source' => 'NestedSource', 'type' => 'pdf' ],
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertStringContainsString( 'NestedSource', $summary );
    }

    public function test_summary_handles_array_source_without_name_or_source() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Konten dengan array tanpa name.',
                'vector' => [ 0.1 ],
                'source' => [ 'id' => 123, 'type' => 'scraped' ],
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // Tanpa 'name' atau 'source' key → 'unknown'
        $this->assertStringContainsString( 'unknown', $summary );
    }

    public function test_summary_defaults_to_unknown_when_source_missing() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Item tanpa field source.',
                'vector' => [ 0.1 ],
                // 'source' key tidak ada
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertStringContainsString( 'unknown', $summary );
    }

    public function test_summary_handles_numeric_source() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Item dengan source numerik.',
                'vector' => [ 0.1 ],
                'source' => 42,
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // (string) 42 → '42'
        $this->assertStringContainsString( '42', $summary );
    }

    public function test_summary_multiple_sources_are_comma_separated() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Teks dari PDF.',
                'vector' => [ 0.1 ],
                'source' => 'dokumen.pdf',
            ],
            [
                'id'     => 'vec_2',
                'text'   => 'Teks dari DOCX.',
                'vector' => [ 0.2 ],
                'source' => 'artikel.docx',
            ],
            [
                'id'     => 'vec_3',
                'text'   => 'Teks dari TXT.',
                'vector' => [ 0.3 ],
                'source' => 'catatan.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // Sumber harus mengandung ketiga nama file
        $this->assertStringContainsString( 'dokumen.pdf', $summary );
        $this->assertStringContainsString( 'artikel.docx', $summary );
        $this->assertStringContainsString( 'catatan.txt', $summary );
    }

    // ====================================================================
    // TOTAL CHUNKS COUNT
    // ====================================================================

    public function test_summary_shows_correct_total_chunks() {
        $chunks = [];
        for ( $i = 1; $i <= 7; $i++ ) {
            $chunks[] = [
                'id'     => "vec_{$i}",
                'text'   => "Teks chunk ke-{$i}.",
                'vector' => [ $i * 0.1 ],
                'source' => 'file.txt',
            ];
        }
        $this->injectMemory( $chunks );

        $summary = $this->store->get_brief_summary();

        $this->assertStringContainsString( 'Total chunks: 7', $summary );
    }

    // ====================================================================
    // SNIPPET BEHAVIOR
    // ====================================================================

    public function test_summary_limits_snippets_to_five() {
        $chunks = [];
        for ( $i = 1; $i <= 20; $i++ ) {
            $chunks[] = [
                'id'     => "vec_{$i}",
                'text'   => "Ini adalah teks chunk nomor {$i} yang digunakan untuk test ringkasan knowledge base.",
                'vector' => [ $i * 0.1 ],
                'source' => 'file.txt',
            ];
        }
        $this->injectMemory( $chunks );

        $summary = $this->store->get_brief_summary();

        // Hitung jumlah baris snippet (dimulai dengan '- ')
        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        $this->assertCount( 5, $snippets, 'Max 5 snippets should be shown' );
    }

    public function test_summary_shows_all_snippets_when_less_than_five() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Hanya ada 2 chunk.',
                'vector' => [ 0.1 ],
                'source' => 'a.txt',
            ],
            [
                'id'     => 'vec_2',
                'text'   => 'Ini chunk kedua.',
                'vector' => [ 0.2 ],
                'source' => 'b.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        $this->assertCount( 2, $snippets, 'Should show all 2 snippets when < 5' );
    }

    public function test_snippet_max_length_is_100_chars() {
        $this->injectMemory( [
            [
                'id'     => 'vec_long',
                'text'   => 'Ini adalah teks yang sangat panjang sekali dan melampaui batas seratus karakter sehingga harus dipotong dengan mb_substr dan tidak boleh melebihi seratus karakter.',
                'vector' => [ 0.1 ],
                'source' => 'long.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        foreach ( $snippets as $snippet ) {
            // Snippet starts with '- ' (2 chars) + content max 100 chars = 102 total
            $this->assertLessThanOrEqual( 102, strlen( $snippet ), 'Snippet + prefix max 102 chars' );
            // Content (after '- ') must be ≤ 100 chars
            $content = substr( $snippet, 2 );
            $this->assertLessThanOrEqual( 100, strlen( $content ), 'Snippet content max 100 chars' );
        }
    }

    public function test_snippet_content_does_not_exceed_100_chars() {
        // Teks tepat 200 karakter
        $long_text = str_repeat( 'a', 200 );
        $this->injectMemory( [
            [
                'id'     => 'vec_long',
                'text'   => $long_text,
                'vector' => [ 0.1 ],
                'source' => 'long.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // Ambil baris snippet
        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        $this->assertNotEmpty( $snippets );
        // Konten snippet harus ≤ 100 chars (200 chars asli dipotong)
        foreach ( $snippets as $snippet ) {
            $content = substr( $snippet, 2 );
            $this->assertLessThanOrEqual( 100, strlen( $content ) );
        }
    }

    public function test_skips_items_without_text() {
        $this->injectMemory( [
            [
                'id'     => 'vec_no_text',
                // 'text' key tidak ada → harus di-skip
                'vector' => [ 0.1 ],
                'source' => 'empty.txt',
            ],
            [
                'id'     => 'vec_with_text',
                'text'   => 'Hanya ini yang punya teks.',
                'vector' => [ 0.2 ],
                'source' => 'valid.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        // Hanya 1 snippet karena item tanpa 'text' di-skip
        $this->assertCount( 1, $snippets );

        // Sumber 'empty.txt' tetap muncul di baris Sumber
        $this->assertStringContainsString( 'empty.txt', $summary );
        $this->assertStringContainsString( 'valid.txt', $summary );
    }

    public function test_skips_items_with_empty_text() {
        $this->injectMemory( [
            [
                'id'     => 'vec_empty',
                'text'   => '', // empty → harus di-skip
                'vector' => [ 0.1 ],
                'source' => 'empty.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        $this->assertEmpty( $snippets, 'No snippets for empty text' );

        // Tapi sumber tetap muncul
        $this->assertStringContainsString( 'empty.txt', $summary );
    }

    public function test_snippet_trim_removes_whitespace() {
        $this->injectMemory( [
            [
                'id'     => 'vec_ws',
                'text'   => '   Teks dengan spasi berlebih di awal dan akhir.   ',
                'vector' => [ 0.1 ],
                'source' => 'whitespace.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        $this->assertNotEmpty( $snippets );
        // Snippet harus di-trim: tidak boleh mulai dengan spasi
        foreach ( $snippets as $snippet ) {
            $content = substr( $snippet, 2 );
            $this->assertEquals( $content, trim( $content ), 'Snippet content should be trimmed' );
        }
    }

    /**
     * Test bahwa snippets menggunakan mb_substr, bukan substr.
     * Karakter multi-byte (misal emoji, aksen) tidak boleh corrupt.
     */
    public function test_snippet_handles_multi_byte_characters() {
        $this->injectMemory( [
            [
                'id'     => 'vec_mb',
                'text'   => 'Teks dengan karakter khusus: à la recherche du temps perdu café naïve fiancé. ' .
                           'Juga emoji: 🔥 🚀 💡. Ini adalah teks panjang yang melebihi 100 karakter untuk ' .
                           'memastikan mb_substr bekerja dengan benar pada karakter multi-byte.',
                'vector' => [ 0.1 ],
                'source' => 'unicode.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $lines    = explode( "\n", $summary );
        $snippets = array_filter( $lines, function ( $line ) {
            return strpos( $line, '- ' ) === 0;
        } );

        $this->assertNotEmpty( $snippets );
        // Snippet harus valid UTF-8, tidak ada karakter corrupt
        foreach ( $snippets as $snippet ) {
            $content = substr( $snippet, 2 );
            $this->assertTrue(
                mb_check_encoding( $content, 'UTF-8' ),
                'Snippet content must be valid UTF-8'
            );
            // Tidak boleh ada replacement character (corrupt multi-byte)
            $this->assertStringNotContainsString( "\xEF\xBF\xBD", $snippet, 'No corrupted UTF-8 chars' );
        }
    }

    // ====================================================================
    // EDGE CASES
    // ====================================================================

    public function test_summary_with_single_item() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Satu-satunya teks.',
                'vector' => [ 0.1 ],
                'source' => 'single.pdf',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertStringContainsString( 'Total chunks: 1', $summary );
        $this->assertStringContainsString( 'single.pdf', $summary );
        $this->assertStringContainsString( 'Satu-satunya', $summary );
    }

    public function test_summary_deduplicates_identical_sources() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Chunk pertama dari dokumen.',
                'vector' => [ 0.1 ],
                'source' => 'sama.pdf',
            ],
            [
                'id'     => 'vec_2',
                'text'   => 'Chunk kedua dari dokumen yang sama.',
                'vector' => [ 0.2 ],
                'source' => 'sama.pdf',
            ],
            [
                'id'     => 'vec_3',
                'text'   => 'Chunk ketiga dari dokumen yang sama.',
                'vector' => [ 0.3 ],
                'source' => 'sama.pdf',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        // 'sama.pdf' hanya muncul sekali di baris Sumber (setelah hash map unique)
        $lines = explode( "\n", $summary );
        $this->assertEquals( 'Sumber: sama.pdf', $lines[0] );
    }

    public function test_summary_return_type_is_string() {
        $this->injectMemory( [
            [
                'id'     => 'vec_1',
                'text'   => 'Test type.',
                'vector' => [ 0.1 ],
                'source' => 'test.txt',
            ],
        ] );

        $summary = $this->store->get_brief_summary();

        $this->assertIsString( $summary );
        $this->assertNotEmpty( $summary );
    }

    // ====================================================================
    // HELPER METHODS
    // ====================================================================

    /**
     * Inject data langsung ke memory VectorStore via Reflection.
     *
     * @param array $chunks Array of chunk data.
     */
    private function injectMemory( array $chunks ) {
        $reflection = new \ReflectionClass( $this->store );
        $prop       = $reflection->getProperty( 'memory' );
        $prop->setAccessible( true );
        $prop->setValue( $this->store, $chunks );
    }
}
