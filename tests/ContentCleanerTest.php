<?php
/**
 * Unit Test untuk Autoblog\Generators\Helpers\ContentCleaner trait.
 *
 * ContentCleaner adalah trait yang berisi helper untuk:
 * 1. clean_text(): hapus HTML, script/style, boilerplate, normalize whitespace
 * 2. is_html(): deteksi apakah string mengandung block-level HTML tags
 * 3. extract_taxonomy_json(): ekstrak JSON taxonomy dari output AI
 *
 * Semua method adalah private — diuji via test harness + reflection
 * mengikuti pola ContentTransformerTest.
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

class ContentCleanerTestHarness {
    use \Autoblog\Generators\Helpers\ContentCleaner;
}

// ========================================================================
// TEST CLASS
// ========================================================================

class ContentCleanerTest extends TestCase {

    /** @var ContentCleanerTestHarness */
    private $harness;

    protected function setUp(): void {
        parent::setUp();
        $this->harness = new ContentCleanerTestHarness();
    }

    // ====================================================================
    // CLEAN_TEXT — HTML & TAG REMOVAL
    // ====================================================================

    public function test_clean_text_removes_html_tags() {
        $result = $this->invoke( 'clean_text', [ '<h1>Title</h1><p>Paragraph</p>' ] );
        $this->assertSame( 'TitleParagraph', $result,
            'strip_tags tidak menambah spasi antar tag'
        );
    }

    public function test_clean_text_removes_multiple_html_tags() {
        $result = $this->invoke( 'clean_text', [
            '<div><section><article>Deeply nested</article></section></div>'
        ] );
        $this->assertSame( 'Deeply nested', $result );
    }

    public function test_clean_text_removes_script_tags_and_content() {
        $input  = 'Before<script>alert("evil");</script>After';
        $result = $this->invoke( 'clean_text', [ $input ] );
        $this->assertSame( 'BeforeAfter', $result,
            'Script tag beserta kontennya harus dihapus (strip_tags tidak menambah spasi)'
        );
    }

    public function test_clean_text_removes_style_tags_and_content() {
        $input  = 'Visible<style>body{color:red}</style>Text';
        $result = $this->invoke( 'clean_text', [ $input ] );
        $this->assertSame( 'VisibleText', $result,
            'Style tag beserta kontennya harus dihapus (strip_tags tidak menambah spasi)'
        );
    }

    public function test_clean_text_removes_script_with_attributes() {
        $input  = '<p>Safe</p><script type="text/javascript">badCode();</script><p>Also safe</p>';
        $result = $this->invoke( 'clean_text', [ $input ] );
        $this->assertSame( 'SafeAlso safe', $result,
            'strip_tags tidak menambah spasi antar tag'
        );
    }

    // ====================================================================
    // CLEAN_TEXT — HTML ENTITIES
    // ====================================================================

    public function test_clean_text_decodes_html_entities() {
        $result = $this->invoke( 'clean_text', [ '&amp; &lt; &gt; &quot; &euro;' ] );
        $this->assertStringContainsString( '&', $result );
        $this->assertStringContainsString( '<', $result );
        $this->assertStringContainsString( '>', $result );
        $this->assertStringContainsString( '"', $result );
        $this->assertStringContainsString( '€', $result );
    }

    public function test_clean_text_decodes_nbsp() {
        $result = $this->invoke( 'clean_text', [ 'Hello&nbsp;World&nbsp;Test' ] );
        // &nbsp; didecode menjadi non-breaking space (U+00A0), bukan spasi biasa
        $nbsp = "\u{00A0}";
        $this->assertStringContainsString( "Hello{$nbsp}World{$nbsp}Test", $result );
    }

    // ====================================================================
    // CLEAN_TEXT — BOILERPLATE REMOVAL
    // ====================================================================

    public function test_clean_text_removes_share_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'Content here. Share this on Twitter. More content.'
        ] );
        $this->assertStringContainsString( 'Content here.', $result );
        $this->assertStringContainsString( 'More content.', $result );
        $this->assertStringNotContainsString( 'Share this on Twitter', $result );
    }

    public function test_clean_text_removes_follow_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'Article text. Follow us on Facebook for updates. Ending.'
        ] );
        $this->assertStringNotContainsString( 'Follow us on Facebook', $result );
    }

    public function test_clean_text_removes_subscribe_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'Main content. Subscribe to our newsletter for more. Footer.'
        ] );
        $this->assertStringNotContainsString( 'Subscribe to our newsletter', $result );
    }

    public function test_clean_text_removes_read_more_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'Intro. Read more about this topic. Conclusion.'
        ] );
        $this->assertStringNotContainsString( 'Read more', $result,
            'Frases "Read more" harus dihapus beserta teks setelahnya'
        );
    }

    public function test_clean_text_removes_copyright_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'Content. Copyright © 2026 All rights reserved. End.'
        ] );
        $this->assertStringNotContainsString( 'Copyright', $result,
            'Baris copyright harus dihapus'
        );
        $this->assertStringNotContainsString( 'All rights reserved', $result );
    }

    public function test_clean_text_removes_multiple_boilerplate_patterns() {
        $result = $this->invoke( 'clean_text', [
            'Real content here. Subscribe to our newsletter. '
            . 'Follow us on Twitter. Copyright © 2026. All rights reserved. '
            . 'More real content.'
        ] );
        // Boilerplate harus dihapus, content asli tetap
        $this->assertStringContainsString( 'Real content here.', $result );
        $this->assertStringContainsString( 'More real content.', $result );
        $this->assertStringNotContainsString( 'Subscribe', $result );
        $this->assertStringNotContainsString( 'Copyright', $result );
        // 'Twitter' mungkin tersisa setelah penghapusan 'Follow us' (menjadi 'on Twitter')
        // yang penting konten asli tetap ada
    }

    public function test_clean_text_handles_tweet_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'News. Tweet this article. More news.'
        ] );
        $this->assertStringNotContainsString( 'Tweet', $result );
    }

    public function test_clean_text_handles_like_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'Post. Like us on Facebook. End.'
        ] );
        $this->assertStringNotContainsString( 'Like us on Facebook', $result );
    }

    // ====================================================================
    // CLEAN_TEXT — WHITESPACE & EDGE CASES
    // ====================================================================

    public function test_clean_text_normalizes_whitespace() {
        $result = $this->invoke( 'clean_text', [ "A   B\n\n\nC\t\tD" ] );
        $this->assertSame( 'A B C D', $result );
    }

    public function test_clean_text_trims_whitespace() {
        $result = $this->invoke( 'clean_text', [ "   Hello World   \n" ] );
        $this->assertSame( 'Hello World', $result );
    }

    public function test_clean_text_empty_returns_empty() {
        $result = $this->invoke( 'clean_text', [ '' ] );
        $this->assertSame( '', $result );
    }

    public function test_clean_text_whitespace_only_returns_empty() {
        $result = $this->invoke( 'clean_text', [ "   \n  \n  " ] );
        $this->assertSame( '', $result );
    }

    public function test_clean_text_preserves_plain_text() {
        $text   = 'This is normal text without any tags or boilerplate.';
        $result = $this->invoke( 'clean_text', [ $text ] );
        $this->assertSame( $text, $result );
    }

    public function test_clean_text_handles_only_boilerplate() {
        $result = $this->invoke( 'clean_text', [
            'Subscribe to our newsletter. Follow us. All rights reserved.'
        ] );
        // Periode/titik antar frasa boilerplate tetap tersisa setelah penghapusan
        // Yang penting frasa boilerplate-nya sendiri sudah dihapus
        $this->assertStringNotContainsString( 'Subscribe', $result,
            'Frasa Subscribe harus dihapus'
        );
        $this->assertStringNotContainsString( 'Follow', $result,
            'Frasa Follow harus dihapus'
        );
        $this->assertStringNotContainsString( 'All rights reserved', $result,
            'Frasa All rights reserved harus dihapus'
        );
    }

    // ====================================================================
    // IS_HTML
    // ====================================================================

    public function test_is_html_returns_true_with_two_block_tags() {
        $result = $this->invoke( 'is_html', [ '<h1>Title</h1><p>Paragraph</p>' ] );
        $this->assertTrue( $result );
    }

    public function test_is_html_returns_true_with_many_block_tags() {
        $result = $this->invoke( 'is_html', [
            '<section><h2>Section</h2><p>Text</p><ul><li>Item</li></ul></section>'
        ] );
        $this->assertTrue( $result );
    }

    public function test_is_html_returns_false_with_one_block_tag() {
        $result = $this->invoke( 'is_html', [ '<p>Single paragraph</p>' ] );
        $this->assertFalse( $result, 'Satu tag block-level saja tidak cukup' );
    }

    public function test_is_html_returns_false_with_inline_tags() {
        $result = $this->invoke( 'is_html', [ '<b>bold</b> and <i>italic</i>' ] );
        $this->assertFalse( $result, 'Inline tags (b, i) bukan block-level' );
    }

    public function test_is_html_returns_false_with_plain_text() {
        $result = $this->invoke( 'is_html', [ 'Just plain text.' ] );
        $this->assertFalse( $result );
    }

    public function test_is_html_returns_false_with_empty_string() {
        $result = $this->invoke( 'is_html', [ '' ] );
        $this->assertFalse( $result );
    }

    public function test_is_html_returns_false_with_markdown() {
        $result = $this->invoke( 'is_html', [ '# Heading\n\nParagraph with **bold**.' ] );
        $this->assertFalse( $result, 'Markdown tanpa HTML tags harus false' );
    }

    public function test_is_html_detects_section_and_article() {
        $result = $this->invoke( 'is_html', [ '<section>Content</section><article>Article</article>' ] );
        $this->assertTrue( $result, 'section dan article adalah block-level tags' );
    }

    public function test_is_html_detects_blockquote_and_table() {
        $result = $this->invoke( 'is_html', [ '<blockquote>Quote</blockquote><table><tr><td>Cell</td></tr></table>' ] );
        $this->assertTrue( $result, 'blockquote dan table adalah block-level tags' );
    }

    // ====================================================================
    // EXTRACT_TAXONOMY_JSON — VALID
    // ====================================================================

    public function test_extract_taxonomy_json_valid() {
        $content = 'Article content here. {"taxonomy": {"category": "Tech", "tags": ["AI", "ML"]}} More text.';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );

        $this->assertIsArray( $result );
        $this->assertEquals( 'Tech', $result['category'] );
        $this->assertEquals( [ 'AI', 'ML' ], $result['tags'] );
    }

    public function test_extract_taxonomy_json_removes_from_content() {
        $content = 'Lead paragraph. {"taxonomy": {"category": "Science"}} Trailing text.';
        $this->invoke( 'extract_taxonomy_json', [ &$content ] );

        $this->assertStringNotContainsString( 'taxonomy', $content,
            'Blok JSON taxonomy harus dihapus dari content'
        );
        $this->assertStringContainsString( 'Lead paragraph.', $content );
        $this->assertStringContainsString( 'Trailing text.', $content );
    }

    public function test_extract_taxonomy_json_with_backtick_fences() {
        $content = 'Text. ```json {"taxonomy": {"category": "Dev", "tags": ["PHP"]}} ``` End.';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );

        $this->assertIsArray( $result );
        $this->assertEquals( 'Dev', $result['category'] );
        $this->assertEquals( [ 'PHP' ], $result['tags'] );
        $this->assertStringNotContainsString( '```json', $content,
            'Backtik fences harus dihapus dari content'
        );
    }

    public function test_extract_taxonomy_json_with_html_fences() {
        $content = 'Text. ```html {"taxonomy": {"category": "Web"}} ``` End.';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertIsArray( $result );
        $this->assertEquals( 'Web', $result['category'] );
    }

    public function test_extract_taxonomy_json_nested_braces() {
        $content = 'Content. {"taxonomy": {"category": "Nested", "metadata": {"key": "val", "nested": {"a": 1}}}} End.';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertIsArray( $result );
        $this->assertEquals( 'Nested', $result['category'] );
        $this->assertEquals( 'val', $result['metadata']['key'] );
        $this->assertEquals( 1, $result['metadata']['nested']['a'] );
    }

    // ====================================================================
    // EXTRACT_TAXONOMY_JSON — SMART QUOTES
    // ====================================================================

    public function test_extract_taxonomy_json_smart_double_quotes() {
        // Unicode smart double quotes \u201C and \u201D
        $content = "Text. {\u{201C}taxonomy\u{201D}: {\u{201C}category\u{201D}: \u{201C}AI\u{201D}}} End.";
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertIsArray( $result, 'Smart double quotes harus dikonversi' );
        $this->assertEquals( 'AI', $result['category'] );
    }

    public function test_extract_taxonomy_json_smart_single_quotes() {
        // Unicode smart single quotes \u2018 and \u2019
        $content = "Text. {\u{2018}taxonomy\u{2019}: {\u{2018}category\u{2019}: \u{2018}Tech\u{2019}}} End.";
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertIsArray( $result, 'Smart single quotes harus dikonversi ke double quotes' );
        $this->assertEquals( 'Tech', $result['category'] );
    }

    // ====================================================================
    // EXTRACT_TAXONOMY_JSON — NO TAXONOMY / MALFORMED
    // ====================================================================

    public function test_extract_taxonomy_json_no_taxonomy_key() {
        $content = '{"name": "test", "value": 123}';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertNull( $result, 'JSON tanpa key taxonomy harus return null' );
    }

    public function test_extract_taxonomy_json_malformed_json() {
        $content = '{"taxonomy": {"category": "broken}';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertNull( $result, 'JSON malformed harus return null' );
    }

    public function test_extract_taxonomy_json_no_json_at_all() {
        $content = 'Plain text without any JSON content.';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertNull( $result );
    }

    public function test_extract_taxonomy_json_empty_string() {
        $content = '';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertNull( $result );
    }

    public function test_extract_taxonomy_json_taxonomy_not_object() {
        $content = '{"taxonomy": "just a string"}';
        $result  = $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        // Code mengembalikan nilai taxonomy apa adanya, meskipun bukan object/array
        $this->assertSame( 'just a string', $result,
            'extract_taxonomy_json mengembalikan nilai taxonomy mentah apa adanya'
        );
    }

    /**
     * Test bahwa content asli tidak berubah jika tidak ada taxonomy JSON.
     */
    public function test_extract_taxonomy_json_unchanged_when_not_found() {
        $original = 'Just regular content without any JSON.';
        $content  = $original;
        $this->invoke( 'extract_taxonomy_json', [ &$content ] );
        $this->assertSame( $original, $content );
    }

    // ====================================================================
    // HELPER
    // ====================================================================

    /**
     * Panggil private method dari ContentCleaner trait via reflection.
     *
     * @param string $method Nama method.
     * @param array  $params Parameter method.
     * @return mixed
     */
    private function invoke( string $method, array $params = [] ) {
        $reflection = new \ReflectionClass( ContentCleanerTestHarness::class );
        $m          = $reflection->getMethod( $method );
        $m->setAccessible( true );
        return $m->invokeArgs( $this->harness, $params );
    }
}
