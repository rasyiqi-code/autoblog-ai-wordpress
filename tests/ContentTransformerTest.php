<?php
/**
 * Unit Test untuk Autoblog\\Generators\\Helpers\\ContentTransformer trait.
 *
 * ContentTransformer adalah trait yang berisi helper untuk mentransformasi
 * konten artikel: Markdown→HTML, chart injection, media embed, median injection.
 *
 * Semua method adalah private pure function — diuji via test harness + reflection.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      generators
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;

// ========================================================================
// TEST HARNESS
// ========================================================================

class ContentTransformerTestHarness {
    use \Autoblog\Generators\Helpers\ContentTransformer;
}

// ========================================================================
// TEST CLASS
// ========================================================================

class ContentTransformerTest extends TestCase {

    /** @var ContentTransformerTestHarness */
    private $harness;

    protected function setUp(): void {
        parent::setUp();
        $this->harness = new ContentTransformerTestHarness();
    }

    // ====================================================================
    // MARKDOWN_TO_HTML — EMPTY & EDGE CASES
    // ====================================================================

    public function test_markdown_to_html_empty() {
        $result = $this->invoke( 'markdown_to_html', [ '' ] );
        $this->assertSame( '', $result );
    }

    public function test_markdown_to_html_whitespace_only() {
        $result = $this->invoke( 'markdown_to_html', [ "   \n  \n  " ] );
        $this->assertSame( '', $result, 'Whitespace-only harus return empty string' );
    }

    // ====================================================================
    // MARKDOWN_TO_HTML — HEADINGS
    // ====================================================================

    public function test_markdown_to_html_h1() {
        $result = $this->invoke( 'markdown_to_html', [ '# Heading Level 1' ] );
        $this->assertStringContainsString( '<h1>Heading Level 1</h1>', $result );
    }

    public function test_markdown_to_html_h2() {
        $result = $this->invoke( 'markdown_to_html', [ '## Heading Level 2' ] );
        $this->assertStringContainsString( '<h2>Heading Level 2</h2>', $result );
    }

    public function test_markdown_to_html_h6() {
        $result = $this->invoke( 'markdown_to_html', [ '###### Heading Level 6' ] );
        $this->assertStringContainsString( '<h6>Heading Level 6</h6>', $result );
    }

    public function test_markdown_to_html_multiple_headings() {
        $md = "# Title\n\n## Section 1\n\n### Subsection";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '<h1>Title</h1>', $result );
        $this->assertStringContainsString( '<h2>Section 1</h2>', $result );
        $this->assertStringContainsString( '<h3>Subsection</h3>', $result );
    }

    // ====================================================================
    // MARKDOWN_TO_HTML — LISTS
    // ====================================================================

    public function test_markdown_to_html_unordered_list() {
        $md = "- Item 1\n- Item 2\n- Item 3";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '<ul>', $result );
        $this->assertStringContainsString( '<li>Item 1</li>', $result );
        $this->assertStringContainsString( '<li>Item 2</li>', $result );
        $this->assertStringContainsString( '<li>Item 3</li>', $result );
        $this->assertStringContainsString( '</ul>', $result );
    }

    public function test_markdown_to_html_unordered_list_asterisk() {
        $md = "* Item A\n* Item B";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '<ul>', $result );
        $this->assertStringContainsString( '<li>Item A</li>', $result );
        $this->assertStringContainsString( '<li>Item B</li>', $result );
    }

    public function test_markdown_to_html_ordered_list() {
        $md = "1. First\n2. Second\n3. Third";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '<ol>', $result );
        $this->assertStringContainsString( '<li>First</li>', $result );
        $this->assertStringContainsString( '<li>Second</li>', $result );
        $this->assertStringContainsString( '<li>Third</li>', $result );
        $this->assertStringContainsString( '</ol>', $result );
    }

    public function test_markdown_to_html_ordered_list_respects_numbers() {
        // Ordered list dengan angka berapapun tetap jadi ordered list
        $md = "1. Item satu\n10. Item sepuluh\n100. Item seratus";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '<ol>', $result );
        $this->assertStringContainsString( '<li>Item satu</li>', $result );
        $this->assertStringContainsString( '<li>Item sepuluh</li>', $result );
        $this->assertStringContainsString( '<li>Item seratus</li>', $result );
    }

    // ====================================================================
    // MARKDOWN_TO_HTML — BLOCKQUOTES
    // ====================================================================

    public function test_markdown_to_html_blockquote() {
        $result = $this->invoke( 'markdown_to_html', [ '> This is a blockquote' ] );
        $this->assertStringContainsString( '<blockquote>', $result );
        $this->assertStringContainsString( '<p>This is a blockquote</p>', $result );
        $this->assertStringContainsString( '</blockquote>', $result );
    }

    // ====================================================================
    // MARKDOWN_TO_HTML — PARAGRAPHS
    // ====================================================================

    public function test_markdown_to_html_paragraph() {
        $result = $this->invoke( 'markdown_to_html', [ 'Ini adalah paragraf biasa.' ] );
        $this->assertStringContainsString( '<p>Ini adalah paragraf biasa.</p>', $result );
    }

    public function test_markdown_to_html_multiple_paragraphs() {
        $md = "Paragraf pertama.\n\nParagraf kedua.";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '<p>Paragraf pertama.</p>', $result );
        $this->assertStringContainsString( '<p>Paragraf kedua.</p>', $result );
    }

    // ====================================================================
    // MARKDOWN_TO_HTML — MIXED CONTENT
    // ====================================================================

    public function test_markdown_to_html_mixed_content() {
        $md = "# Judul\n\nParagraf pembuka.\n\n- List item 1\n- List item 2\n\n> Kutipan.\n\nParagraf penutup.";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '<h1>Judul</h1>', $result );
        $this->assertStringContainsString( '<p>Paragraf pembuka.</p>', $result );
        $this->assertStringContainsString( '<li>List item 1</li>', $result );
        $this->assertStringContainsString( '<blockquote>', $result );
        $this->assertStringContainsString( '<p>Paragraf penutup.</p>', $result );
    }

    // ====================================================================
    // MARKDOWN_TO_HTML — LIST CLOSING
    // ====================================================================

    public function test_markdown_to_html_list_closes_before_heading() {
        $md = "- Item 1\n- Item 2\n# Heading setelah list";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        // List harus ditutup sebelum heading
        $this->assertStringContainsString( '</ul>', $result );
        $this->assertStringContainsString( '<h1>Heading setelah list</h1>', $result );
    }

    public function test_markdown_to_html_list_closes_on_empty_line() {
        $md = "- Item 1\n- Item 2\n\nParagraf setelah list.";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '</ul>', $result );
    }

    public function test_markdown_to_html_nested_heading_inside_list() {
        // Heading di tengah list → list ditutup, heading diproses
        $md = "- Item 1\n## Heading\n- Item 2";
        $result = $this->invoke( 'markdown_to_html', [ $md ] );
        $this->assertStringContainsString( '</ul>', $result );
        $this->assertStringContainsString( '<h2>Heading</h2>', $result );
        // Item 2 mulai list baru karena setelah heading baris kosong
        // Tapi karena tidak ada empty line, Item 2 masuk paragraf
    }

    // ====================================================================
    // CONVERT_INLINE_MARKDOWN
    // ====================================================================

    public function test_convert_inline_bold_asterisk() {
        $result = $this->invoke( 'convert_inline_markdown', [ 'Ini **bold** text.' ] );
        $this->assertStringContainsString( '<strong>bold</strong>', $result );
    }

    public function test_convert_inline_bold_underscore() {
        $result = $this->invoke( 'convert_inline_markdown', [ 'Ini __bold__ text.' ] );
        $this->assertStringContainsString( '<strong>bold</strong>', $result );
    }

    public function test_convert_inline_italic_asterisk() {
        $result = $this->invoke( 'convert_inline_markdown', [ 'Ini *italic* text.' ] );
        $this->assertStringContainsString( '<em>italic</em>', $result );
    }

    public function test_convert_inline_italic_underscore() {
        $result = $this->invoke( 'convert_inline_markdown', [ 'Ini _italic_ text.' ] );
        $this->assertStringContainsString( '<em>italic</em>', $result );
    }

    public function test_convert_inline_mixed_bold_italic() {
        $result = $this->invoke( 'convert_inline_markdown', [ '**Bold** dan *italic* bersama.' ] );
        $this->assertStringContainsString( '<strong>Bold</strong>', $result );
        $this->assertStringContainsString( '<em>italic</em>', $result );
    }

    public function test_convert_inline_no_markdown() {
        $result = $this->invoke( 'convert_inline_markdown', [ 'Teks biasa tanpa markdown.' ] );
        $this->assertSame( 'Teks biasa tanpa markdown.', $result );
    }

    public function test_convert_inline_empty() {
        $result = $this->invoke( 'convert_inline_markdown', [ '' ] );
        $this->assertSame( '', $result );
    }

    // ====================================================================
    // NORMALIZE_JSON_QUOTES
    // ====================================================================

    public function test_normalize_json_quotes_converts_smart_double_quotes() {
        $input  = "\u{201C}Hello\u{201D}";
        $result = $this->invoke( 'normalize_json_quotes', [ $input ] );
        $this->assertSame( '"Hello"', $result );
    }

    public function test_normalize_json_quotes_converts_smart_single_quotes() {
        $input  = "\u{2018}World\u{2019}";
        $result = $this->invoke( 'normalize_json_quotes', [ $input ] );
        $this->assertSame( '"World"', $result );
    }

    public function test_normalize_json_quotes_regular_text_unchanged() {
        $input  = 'Normal text with "regular" quotes.';
        $result = $this->invoke( 'normalize_json_quotes', [ $input ] );
        $this->assertSame( $input, $result );
    }

    public function test_normalize_json_quotes_mixed() {
        $input  = "\u{201C}Smart\u{201D} and \"regular\" and \u{2018}single\u{2019}";
        $result = $this->invoke( 'normalize_json_quotes', [ $input ] );
        $this->assertSame( '"Smart" and "regular" and "single"', $result );
    }

    // ====================================================================
    // INJECT_AT_MEDIAN
    // ====================================================================

    public function test_inject_at_median_short_content_appends() {
        $content = '<p>Short content.</p>';
        $element = '<div class="chart">Chart</div>';

        $result = $this->invoke( 'inject_at_median', [ $content, $element ] );
        // Short content (< 2 paragraphs) → append di akhir
        $this->assertStringContainsString( $element, $result,
            'Short content → element harus ada di result'
        );
    }

    public function test_inject_at_median_appends_short_content() {
        $content = '<p>Single paragraph.</p>';
        $element = 'SUFFIX';
        $result  = $this->invoke( 'inject_at_median', [ $content, $element ] );

        // Short content (< 2 paragraph closures) → di-append
        $this->assertStringContainsString( $element, $result,
            'Element harus ditemukan di result untuk short content'
        );
        $this->assertStringContainsString( 'Single paragraph', $result,
            'Konten asli harus tetap ada'
        );
    }

    public function test_inject_at_median_preserves_content() {
        $content = '<p>Only paragraph.</p>';
        $element = '<!-- injected -->';

        $result = $this->invoke( 'inject_at_median', [ $content, $element ] );
        $this->assertStringContainsString( 'Only paragraph.', $result,
            'Content asli harus tetap ada di result'
        );
    }

    // ====================================================================
    // PROCESS_CHART_JSON — (dengan ChartGenerator tidak tersedia)
    // ====================================================================

    /**
     * Test bahwa process_chart_json tetap mengembalikan konten asli jika
     * tidak ada JSON chart di dalamnya.
     */
    public function test_process_chart_json_no_chart() {
        $content = '<p>Konten tanpa chart.</p>';
        $result = $this->invoke( 'process_chart_json', [ $content ] );
        $this->assertSame( $content, $result,
            'Konten tanpa chart JSON harus tetap sama'
        );
    }

    /**
     * Test bahwa process_chart_json memproses konten tanpa error
     * meskipun ada JSON tapi bukan chart.
     */
    public function test_process_chart_json_non_chart_json() {
        $content = '<p>Data: {"name": "test", "value": 123}</p>';
        $result = $this->invoke( 'process_chart_json', [ $content ] );
        $this->assertStringContainsString( 'test', $result,
            'Non-chart JSON tidak boleh diubah'
        );
    }

    /**
     * Test bahwa process_chart_json tidak crash ketika ChartGenerator
     * tidak tersedia (fallback graceful).
     *
     * Karena process_chart_json mencoba require ChartGenerator.php
     * jika class_exists false, dan pemanggilan plugin_dir_path
     * mungkin tidak bekerja di test harness, method akan skip.
     */
    public function test_process_chart_json_graceful_when_chart_generator_unavailable() {
        $content = '{"chart": {"labels": ["A","B"], "data": [1,2], "type": "bar", "title": "Test"}}';
        $result = $this->invoke( 'process_chart_json', [ $content ] );
        // Harus tidak throw exception dan mengembalikan string
        $this->assertIsString( $result );
    }

    // ====================================================================
    // PROCESS_MEDIA_EMBEDS
    // ====================================================================

    /**
     * Test bahwa process_media_embeds tidak mengubah konten tanpa media JSON.
     */
    public function test_process_media_embeds_no_media() {
        $content = '<p>Konten biasa.</p>';
        $result = $this->invoke( 'process_media_embeds', [ $content ] );
        $this->assertSame( $content, $result, 'Konten tanpa media JSON harus tetap sama' );
    }

    /**
     * Test bahwa process_media_embeds tidak crash dengan non-media JSON.
     */
    public function test_process_media_embeds_non_media_json() {
        $content = '{"name": "bukan media"}';
        $result = $this->invoke( 'process_media_embeds', [ $content ] );
        $this->assertIsString( $result );
    }

    // ====================================================================
    // HELPER
    // ====================================================================

    private function invoke( string $method, array $params = [] ) {
        $reflection = new \ReflectionClass( ContentTransformerTestHarness::class );
        $m = $reflection->getMethod( $method );
        $m->setAccessible( true );
        return $m->invokeArgs( $this->harness, $params );
    }
}
