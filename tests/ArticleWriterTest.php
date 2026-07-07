<?php

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Generators\ArticleWriter;

/**
 * Unit Test untuk ArticleWriter dan Trait Helper-nya.
 *
 * Memverifikasi pembersihan teks boilerplate, transformasi format Markdown ke HTML,
 * serta ekstraksi data taksonomi JSON dari teks output AI.
 *
 * @package Autoblog\Tests
 */
class ArticleWriterTest extends TestCase {

    /** @var ArticleWriter */
    private $writer;

    protected function setUp(): void {
        parent::setUp();
        $this->writer = new ArticleWriter();
    }

    // ================================================================
    // TEST 1: Pembersihan Teks (ContentCleaner::clean_text)
    // ================================================================

    public function test_clean_text_removes_scripts_and_styles() {
        $input    = 'Hello <style>body {color:red;}</style>World <script>alert(1);</script>!';
        $expected = 'Hello World !';
        $output   = $this->invokeMethod( $this->writer, 'clean_text', [ $input ] );

        $this->assertEquals( $expected, $output );
    }

    public function test_clean_text_removes_social_sharing_boilerplates() {
        $input    = 'Baca artikel ini. Share this page on Twitter or Facebook! follow us on instagram for details.';
        $expected = 'Baca artikel ini. or ! for details.';
        $output   = $this->invokeMethod( $this->writer, 'clean_text', [ $input ] );

        $this->assertEquals( $expected, $output );
    }

    public function test_clean_text_removes_copyrights_and_read_more() {
        $input    = 'Konten utama disini. Copyright © 2026 Autoblog AI. Read more here for details.';
        $expected = 'Konten utama disini. .';
        $output   = $this->invokeMethod( $this->writer, 'clean_text', [ $input ] );

        $this->assertEquals( $expected, $output );
    }

    // ================================================================
    // TEST 2: Deteksi Format (ContentCleaner::is_html)
    // ================================================================

    public function test_is_html_detects_html_vs_markdown() {
        $html_content = '<h2>Judul</h2><p>Ini paragraf pertama.</p><p>Ini paragraf kedua.</p>';
        $md_content   = "## Judul\n\nIni paragraf pertama.\n\nIni paragraf kedua.";

        $this->assertTrue( $this->invokeMethod( $this->writer, 'is_html', [ $html_content ] ) );
        $this->assertFalse( $this->invokeMethod( $this->writer, 'is_html', [ $md_content ] ) );
    }

    // ================================================================
    // TEST 3: Transformasi Markdown ke HTML (ContentTransformer::markdown_to_html)
    // ================================================================

    public function test_markdown_to_html_conversion() {
        $markdown = "## Subjudul\nIni adalah **teks tebal** dan *teks miring*.\n\n- Item 1\n- Item 2";
        $output   = $this->invokeMethod( $this->writer, 'markdown_to_html', [ $markdown ] );

        $this->assertStringContainsString( '<h2>Subjudul</h2>', $output );
        $this->assertStringContainsString( '<strong>teks tebal</strong>', $output );
        $this->assertStringContainsString( '<em>teks miring</em>', $output );
        $this->assertStringContainsString( '<ul>', $output );
        $this->assertStringContainsString( '<li>Item 1</li>', $output );
    }

    // ================================================================
    // TEST 4: Ekstraksi Taksonomi JSON (ContentCleaner::extract_taxonomy_json)
    // ================================================================

    public function test_extract_taxonomy_json_removes_block_from_content() {
        $content = "Konten artikel utama di sini.\n\n```json\n{ \"taxonomy\": { \"category\": \"Teknologi\", \"tags\": [\"AI\", \"Wordpress\"] } }\n```\nSisa konten.";

        $taxonomy = $this->invokeMethod( $this->writer, 'extract_taxonomy_json', [ &$content ] );

        // 1. JSON harus terurai dengan benar
        $this->assertIsArray( $taxonomy );
        $this->assertEquals( 'Teknologi', $taxonomy['category'] );
        $this->assertEquals( [ 'AI', 'Wordpress' ], $taxonomy['tags'] );

        // 2. Blok JSON di dalam konten harus dihapus (by reference)
        $this->assertStringNotContainsString( 'taxonomy', $content );
        $this->assertStringNotContainsString( '```json', $content );
        $this->assertStringContainsString( 'Konten artikel utama di sini.', $content );
        $this->assertStringContainsString( 'Sisa konten.', $content );
    }

    // ================================================================
    // HELPER: Invoke private method via Reflection
    // ================================================================

    private function invokeMethod( &$object, $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }
}
