<?php
/**
 * Unit Test untuk FileSource.
 *
 * Memverifikasi:
 * 1. Constructor & interface compliance
 * 2. validate_source() untuk file exist / nonexist
 * 3. fetch_data() untuk semua format (.txt, .md, .csv, .xlsx, .pdf, .docx)
 * 4. Error handling (file rusak, file tidak ada, ekstensi tidak support)
 * 5. Safety limit di parse_spreadsheet (2500 baris)
 * 6. Private methods: parse_text, parse_spreadsheet, parse_pdf
 * 7. get_display_name()
 *
 * Menggunakan file temp nyata di filesystem agar file_get_contents,
 * file_exists, dan library parser bisa bekerja secara real.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Sources\FileSource;

/**
 * Unit Test untuk FileSource.
 *
 * @group unit
 * @group regression
 * @group file_source
 */
class FileSourceTest extends TestCase {

    /**
     * Daftar file temp yang perlu dibersihkan di tearDown.
     *
     * @var string[]
     */
    private $tempFiles = array();

    protected function tearDown(): void {
        foreach ( $this->tempFiles as $file ) {
            if ( file_exists( $file ) ) {
                @unlink( $file );
            }
        }
        $this->tempFiles = array();
        parent::tearDown();
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Buat file temp dengan konten tertentu.
     *
     * @param string $content Isi file.
     * @param string $ext     Ekstensi (tanpa dot).
     * @return string Absolute path ke file temp.
     */
    private function createTempFile( string $content, string $ext ): string {
        $path = sys_get_temp_dir() . '/fs_test_' . uniqid( '', true ) . '.' . $ext;
        file_put_contents( $path, $content );
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Invoke private/protected method via reflection.
     *
     * @param object $object     Instance object.
     * @param string $methodName Nama method.
     * @param array  $parameters Parameter method.
     * @return mixed Hasil return dari method.
     */
    private function invokeMethod( $object, string $methodName, array $parameters = array() ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }

    // ================================================================
    // CONSTRUCTOR & INTERFACE
    // ================================================================

    public function test_constructor_stores_file_path() {
        $path   = '/tmp/test-file.pdf';
        $source = new FileSource( $path );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'file_path' );
        $prop->setAccessible( true );

        $this->assertEquals( $path, $prop->getValue( $source ) );
    }

    public function test_constructor_requires_argument() {
        $this->expectException( \ArgumentCountError::class );
        new FileSource();
    }

    public function test_implements_source_interface() {
        $reflection = new \ReflectionClass( FileSource::class );
        $this->assertTrue( $reflection->implementsInterface( 'Autoblog\\Interfaces\\SourceInterface' ) );
    }

    // ================================================================
    // VALIDATE SOURCE
    // ================================================================

    public function test_validate_source_returns_false_for_nonexistent_file() {
        $source = new FileSource( '/tmp/nonexistent_file_' . uniqid() . '.txt' );
        $this->assertFalse( $source->validate_source() );
    }

    public function test_validate_source_returns_true_for_existing_file() {
        $path   = $this->createTempFile( 'hello world', 'txt' );
        $source = new FileSource( $path );
        $this->assertTrue( $source->validate_source() );
    }

    // ================================================================
    // FETCH DATA — MISSING FILE
    // ================================================================

    public function test_fetch_data_returns_empty_array_when_file_missing() {
        $source = new FileSource( '/tmp/missing_file_' . uniqid() . '.pdf' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // FETCH DATA — TEXT (.txt)
    // ================================================================

    public function test_fetch_data_parses_txt_file() {
        $content = 'Halo, ini adalah file teks sederhana.';
        $path    = $this->createTempFile( $content, 'txt' );
        $source  = new FileSource( $path );
        $result  = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertEquals( $content, $result[0]['content'] );
        $this->assertEquals( 'file', $result[0]['source_type'] );
        $this->assertEquals( $path, $result[0]['source_url'] );
    }

    public function test_fetch_data_parses_txt_with_multibyte_utf8() {
        $content = 'Halló heimur — こんにちは世界 — 你好世界 — Привет мир';
        $path    = $this->createTempFile( $content, 'txt' );
        $source  = new FileSource( $path );
        $result  = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertEquals( $content, $result[0]['content'] );
    }

    public function test_fetch_data_parses_txt_with_newlines() {
        $content = "Baris pertama\nBaris kedua\n\nBaris keempat";
        $path    = $this->createTempFile( $content, 'txt' );
        $source  = new FileSource( $path );
        $result  = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'Baris pertama', $result[0]['content'] );
        $this->assertStringContainsString( 'Baris keempat', $result[0]['content'] );
    }

    public function test_fetch_data_parses_empty_txt_file() {
        $path   = $this->createTempFile( '', 'txt' );
        $source = new FileSource( $path );
        $result = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertEmpty( $result[0]['content'] );
    }

    // ================================================================
    // FETCH DATA — MARKDOWN (.md)
    // ================================================================

    public function test_fetch_data_parses_md_file() {
        $content = "# Heading\n\nThis is a **bold** paragraph.\n\n- List item 1\n- List item 2";
        $path    = $this->createTempFile( $content, 'md' );
        $source  = new FileSource( $path );
        $result  = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( '**bold**', $result[0]['content'] );
        $this->assertStringContainsString( '# Heading', $result[0]['content'] );
    }

    // ================================================================
    // FETCH DATA — CSV via PhpSpreadsheet
    // ================================================================

    public function test_fetch_data_parses_csv_file() {
        $content = "Nama,Umur,Kota\nBudi,25,Jakarta\nAni,30,Bandung";
        $path    = $this->createTempFile( $content, 'csv' );
        $source  = new FileSource( $path );
        $result  = $source->fetch_data();

        // Row 0 = header "Nama Umur Kota", Row 1 = "Budi 25 Jakarta", Row 2 = "Ani 30 Bandung"
        $this->assertCount( 3, $result );
        $this->assertStringContainsString( 'Nama', $result[0]['content'] );
        $this->assertStringContainsString( 'Budi', $result[1]['content'] );
        $this->assertStringContainsString( 'Ani', $result[2]['content'] );
        $this->assertEquals( 'file', $result[0]['source_type'] );
        $this->assertEquals( $path, $result[0]['source_url'] );
    }

    public function test_fetch_data_parses_csv_with_single_column() {
        $content = "Apel\nJeruk\nPisang";
        $path    = $this->createTempFile( $content, 'csv' );
        $source  = new FileSource( $path );
        $result  = $source->fetch_data();

        $this->assertCount( 3, $result );
        $this->assertEquals( 'Apel', $result[0]['content'] );
        $this->assertEquals( 'Pisang', $result[2]['content'] );
    }

    public function test_fetch_data_parses_csv_skips_empty_rows() {
        $content = "data1\n\ndata3";
        $path    = $this->createTempFile( $content, 'csv' );
        $source  = new FileSource( $path );
        $result  = $source->fetch_data();

        // Baris 2 terdiri dari sel kosong -> array_filter = [] -> empty() = true -> di-skip
        $this->assertCount( 2, $result );
    }

    // ================================================================
    // FETCH DATA — UNSUPPORTED EXTENSION
    // ================================================================

    public function test_fetch_data_unsupported_extension_returns_empty_array() {
        $path   = $this->createTempFile( 'some data', 'exe' );
        $source = new FileSource( $path );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_unsupported_extension_json_returns_empty() {
        $path   = $this->createTempFile( '{"key": "value"}', 'json' );
        $source = new FileSource( $path );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // FETCH DATA — EXCEPTION HANDLING (file rusak / tidak valid)
    // ================================================================

    public function test_fetch_data_catches_exception_on_invalid_pdf() {
        // File .pdf dengan isi garbage — Smalot\\PdfParser throws exception
        $path   = $this->createTempFile( 'NOT_A_VALID_PDF_BINARY', 'pdf' );
        $source = new FileSource( $path );
        $result = $source->fetch_data();

        // Tidak crash — exception di-catch, return array kosong
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_catches_exception_on_invalid_docx() {
        // File .docx dengan isi garbage — PhpWord throws exception (bukan ZIP valid)
        $path   = $this->createTempFile( 'NOT_A_VALID_DOCX_BINARY', 'docx' );
        $source = new FileSource( $path );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_handles_invalid_xlsx_gracefully() {
        // File .xlsx dengan isi garbage — PhpSpreadsheet mungkin throw atau parse minimal
        // Yang penting: fetch_data tidak crash dan return array (walau mungkin berisi data mentah)
        $path   = $this->createTempFile( 'NOT_A_VALID_XLSX_BINARY', 'xlsx' );
        $source = new FileSource( $path );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        // Tidak assert empty karena behavior reader bisa beda antar versi
    }

    // ================================================================
    // PRIVATE METHOD: parse_text()
    // ================================================================

    public function test_parse_text_returns_correct_format() {
        $content = 'Private method test content.';
        $path    = $this->createTempFile( $content, 'txt' );
        $source  = new FileSource( $path );

        $result = $this->invokeMethod( $source, 'parse_text' );

        $this->assertCount( 1, $result );
        $this->assertEquals( $content, $result[0]['content'] );
        $this->assertEquals( 'file', $result[0]['source_type'] );
        $this->assertEquals( $path, $result[0]['source_url'] );
    }

    public function test_parse_text_handles_large_content() {
        $content = str_repeat( 'A', 100000 ); // ~100KB
        $path    = $this->createTempFile( $content, 'txt' );
        $source  = new FileSource( $path );

        $result = $this->invokeMethod( $source, 'parse_text' );

        $this->assertCount( 1, $result );
        $this->assertEquals( 100000, strlen( $result[0]['content'] ) );
    }

    // ================================================================
    // PRIVATE METHOD: parse_spreadsheet() — row safety limit
    // ================================================================

    public function test_parse_spreadsheet_enforces_row_limit() {
        // Buat CSV dengan >2500 baris untuk memicu safety limit.
        // Catatan: kondisi `if ( count( $items ) > 2500 )` dicek SETELAH add item,
        // sehingga item ke-2501 ikut masuk sebelum break — total 2501 items.
        $lines = array();
        for ( $i = 0; $i < 2600; $i++ ) {
            $lines[] = "data_{$i},value_{$i}";
        }
        $content = implode( "\n", $lines );
        $path    = $this->createTempFile( $content, 'csv' );
        $source  = new FileSource( $path );

        $result = $this->invokeMethod( $source, 'parse_spreadsheet' );

        $this->assertNotEmpty( $result );
        $this->assertLessThan( 2600, count( $result ), 'Safety limit harus memotong row count < 2600' );
        $this->assertStringContainsString( 'data_2499', $result[2499]['content'] );
    }

    public function test_parse_spreadsheet_under_limit_returns_all_rows() {
        $lines = array();
        for ( $i = 1; $i <= 10; $i++ ) {
            $lines[] = "row_{$i}";
        }
        $content = implode( "\n", $lines );
        $path    = $this->createTempFile( $content, 'csv' );
        $source  = new FileSource( $path );

        $result = $this->invokeMethod( $source, 'parse_spreadsheet' );

        $this->assertCount( 10, $result );
        $this->assertEquals( 'row_10', $result[9]['content'] );
    }

    // ================================================================
    // PRIVATE METHOD: parse_pdf() — exception propagation
    // ================================================================

    public function test_parse_pdf_throws_exception_for_invalid_file() {
        // Catatan: behavior ini tergantung versi Smalot\\PdfParser.
        // Versi saat ini melempar exception untuk file non-PDF.
        $path   = $this->createTempFile( 'BINARY_GARBAGE_NOT_PDF', 'pdf' );
        $source = new FileSource( $path );

        $this->expectException( \Exception::class );
        $this->invokeMethod( $source, 'parse_pdf' );
    }

    // ================================================================
    // PRIVATE METHOD: parse_docx() — exception propagation
    // ================================================================

    public function test_parse_docx_throws_exception_for_invalid_file() {
        // Catatan: behavior ini tergantung versi PhpOffice\\PhpWord.
        // Versi saat ini melempar exception untuk file non-DOCX.
        $path   = $this->createTempFile( 'BINARY_GARBAGE_NOT_DOCX', 'docx' );
        $source = new FileSource( $path );

        $this->expectException( \Exception::class );
        $this->invokeMethod( $source, 'parse_docx' );
    }

    // ================================================================
    // FETCH DATA — EDGE CASES
    // ================================================================

    public function test_fetch_data_with_different_extensions_same_content() {
        // TXT dan MD harus menghasilkan output yang sama untuk konten identik
        $content = 'Identical content';

        $txt_path = $this->createTempFile( $content, 'txt' );
        $md_path  = $this->createTempFile( $content, 'md' );

        $txt_source = new FileSource( $txt_path );
        $md_source  = new FileSource( $md_path );

        $txt_result = $txt_source->fetch_data();
        $md_result  = $md_source->fetch_data();

        $this->assertEquals( $txt_result[0]['content'], $md_result[0]['content'] );
    }

    public function test_fetch_data_returns_empty_when_source_invalid() {
        // File tidak ada — validate_source return false — fetch_data langsung return empty
        $source = new FileSource( '/tmp/definitely_not_exist_' . uniqid() . '.txt' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // GET DISPLAY NAME
    // ================================================================

    public function test_get_display_name() {
        $source = new FileSource( '/tmp/dummy.pdf' );
        $this->assertEquals( 'File Upload', $source->get_display_name() );
    }

    // ================================================================
    // REGRESSION: path handling
    // ================================================================

    public function test_constructor_with_relative_path() {
        $source = new FileSource( 'relative/path/file.txt' );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'file_path' );
        $prop->setAccessible( true );

        $this->assertEquals( 'relative/path/file.txt', $prop->getValue( $source ) );
    }

    public function test_constructor_with_path_with_spaces() {
        $source = new FileSource( '/tmp/my uploads/file name.txt' );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'file_path' );
        $prop->setAccessible( true );

        $this->assertEquals( '/tmp/my uploads/file name.txt', $prop->getValue( $source ) );
    }
}
