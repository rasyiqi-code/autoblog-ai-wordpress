<?php
/**
 * Unit Test untuk Autoblog\Intelligence\AngleInjector.
 *
 * AngleInjector bertanggung jawab menghasilkan angle/perspektif unik
 * untuk konten menggunakan AI. Class ini:
 * 1. Constructor: membuat instance AIClient
 * 2. add_human_perception(): membersihkan teks, membangun prompt, panggil AI
 * 3. clean_text(): private — hapus HTML/script/style, decode entities
 *
 * Tests menggunakan mock AIClient yang di-inject via reflection untuk
 * menghindari panggilan API nyata.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      intelligence
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\AngleInjector;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;

class AngleInjectorTest extends TestCase {

    /** @var AngleInjector */
    private $injector;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        $this->injector = new AngleInjector();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    /**
     * Test bahwa constructor mengeksekusi tanpa exception.
     */
    public function test_constructor_creates_instance() {
        $this->assertInstanceOf( AngleInjector::class, $this->injector );
    }

    // ====================================================================
    // ADD_HUMAN_PERCEPTION — SUCCESS PATH
    // ====================================================================

    /**
     * Test bahwa add_human_perception mengembalikan angle dari AI client.
     */
    public function test_add_human_perception_returns_angle() {
        $this->setUpAiOptions();
        $mockAi = $this->createMockAiClient( 'Unique angle: focus on human impact' );
        $this->injectAiClient( $mockAi );

        $result = $this->injector->add_human_perception( 'Test content about AI technology.' );

        $this->assertIsString( $result );
        $this->assertSame( 'Unique angle: focus on human impact', $result );
    }

    /**
     * Test bahwa add_human_perception menyertakan konteks dalam prompt.
     */
    public function test_add_human_perception_includes_context_in_prompt() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $this->injector->add_human_perception( 'Content.', 'Background info about AI trends in 2026.' );

        $this->assertStringContainsString( 'Background info about AI trends in 2026', $capturedPrompt,
            'Context harus disertakan dalam prompt'
        );
        $this->assertStringContainsString( 'CONTEXT / KNOWLEDGE BASE', $capturedPrompt,
            'Prompt harus memiliki label CONTEXT / KNOWLEDGE BASE'
        );
    }

    /**
     * Test bahwa add_human_perception berfungsi tanpa konteks.
     */
    public function test_add_human_perception_works_without_context() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $this->injector->add_human_perception( 'Just content.' );

        $this->assertStringNotContainsString( 'CONTEXT / KNOWLEDGE BASE', $capturedPrompt,
            'Prompt TIDAK boleh mengandung bagian konteks jika tidak ada context parameter'
        );
    }

    // ====================================================================
    // ADD_HUMAN_PERCEPTION — FAILURE PATH
    // ====================================================================

    /**
     * Test bahwa add_human_perception mengembalikan false jika AI gagal.
     */
    public function test_add_human_perception_returns_false_on_ai_failure() {
        $this->setUpAiOptions();
        $mockAi = $this->createMockAiClient( false );
        $this->injectAiClient( $mockAi );

        $result = $this->injector->add_human_perception( 'Test content.' );

        $this->assertFalse( $result, 'Jika AI client return false, harus return false' );
    }

    /**
     * Test bahwa add_human_perception mengembalikan false jika AI return null.
     */
    public function test_add_human_perception_returns_false_on_ai_null() {
        $this->setUpAiOptions();
        $mockAi = $this->createMockAiClient( null );
        $this->injectAiClient( $mockAi );

        $result = $this->injector->add_human_perception( 'Test content.' );

        $this->assertFalse( $result, 'Jika AI client return null, harus return false' );
    }

    /**
     * Test bahwa add_human_perception mengembalikan false jika AI return empty string.
     */
    public function test_add_human_perception_returns_false_on_empty_string() {
        $this->setUpAiOptions();
        $mockAi = $this->createMockAiClient( '' );
        $this->injectAiClient( $mockAi );

        $result = $this->injector->add_human_perception( 'Test content.' );

        $this->assertFalse( $result, 'Jika AI client return empty string, harus return false' );
    }

    // ====================================================================
    // ADD_HUMAN_PERCEPTION — EMPTY & EDGE CONTENT
    // ====================================================================

    /**
     * Test bahwa add_human_perception tetap memproses content kosong
     * (tidak crash) dan mengembalikan hasil dari AI.
     */
    public function test_add_human_perception_empty_content_still_calls_ai() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $this->injector->add_human_perception( '' );

        $this->assertStringContainsString( 'Content:', $capturedPrompt,
            'Prompt harus tetap memiliki label Content: meski content kosong'
        );
    }

    /**
     * Test bahwa add_human_perception menangani whitespace-only content.
     */
    public function test_add_human_perception_whitespace_content() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $this->injector->add_human_perception( "   \n  \n  " );

        $this->assertStringContainsString( 'Content:', $capturedPrompt );
    }

    /**
     * Test bahwa add_human_perception menangani karakter spesial UTF-8.
     */
    public function test_add_human_perception_handles_utf8() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $this->injector->add_human_perception( 'Konten dengan aksara Jawa: ꦲꦏ꧀ꦱꦫ.' );

        $this->assertStringContainsString( 'aksara', $capturedPrompt );
        $this->assertStringContainsString( 'Konten dengan aksara', $capturedPrompt );
    }

    // ====================================================================
    // ADD_HUMAN_PERCEPTION — CONTENT CLEANING VIA PROMPT CAPTURE
    // ====================================================================

    /**
     * Test bahwa HTML tags dihapus dari prompt (via clean_text).
     */
    public function test_add_human_perception_cleans_html_tags() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $html = '<h1>Title</h1><p>Paragraph with <b>bold</b> text.</p>';
        $this->injector->add_human_perception( $html );

        $this->assertStringNotContainsString( '<h1>', $capturedPrompt );
        $this->assertStringNotContainsString( '<p>', $capturedPrompt );
        $this->assertStringNotContainsString( '<b>', $capturedPrompt );
        $this->assertStringNotContainsString( '</b>', $capturedPrompt );
        $this->assertStringContainsString( 'TitleParagraph with bold text.', $capturedPrompt,
            'Teks bersih harus tetap ada di prompt (tanpa spasi karena strip_tags tidak menambah spasi)'
        );
    }

    /**
     * Test bahwa tag <script> dan kontennya dihapus.
     */
    public function test_add_human_perception_removes_script_tags_and_content() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $html = '<p>Good content.</p><script>alert("evil");</script><p>More good content.</p>';
        $this->injector->add_human_perception( $html );

        $this->assertStringNotContainsString( '<script>', $capturedPrompt );
        $this->assertStringNotContainsString( 'alert', $capturedPrompt, 'Konten script harus dihapus' );
        $this->assertStringContainsString( 'Good content', $capturedPrompt );
        $this->assertStringContainsString( 'More good content', $capturedPrompt );
    }

    /**
     * Test bahwa tag <style> dan kontennya dihapus.
     */
    public function test_add_human_perception_removes_style_tags_and_content() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $html = '<p>Visible text.</p><style>.css{color:red}</style><p>After style.</p>';
        $this->injector->add_human_perception( $html );

        $this->assertStringNotContainsString( '<style>', $capturedPrompt );
        $this->assertStringNotContainsString( '.css{color:red}', $capturedPrompt, 'Konten style harus dihapus' );
        $this->assertStringContainsString( 'Visible text', $capturedPrompt );
        $this->assertStringContainsString( 'After style', $capturedPrompt );
    }

    /**
     * Test bahwa HTML entities didecode di prompt.
     */
    public function test_add_human_perception_decodes_html_entities() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $html = '<p>Price: &euro;100 &amp; &lt;discount&gt;</p>';
        $this->injector->add_human_perception( $html );

        $this->assertStringContainsString( '€100', $capturedPrompt, '&euro; harus jadi €' );
        $this->assertStringContainsString( '&', $capturedPrompt, '&amp; harus jadi &' );
        $this->assertStringContainsString( '<discount>', $capturedPrompt, '&lt;discount&gt; harus jadi <discount>' );
    }

    /**
     * Test bahwa whitespace berlebih dinormalisasi di prompt.
     */
    public function test_add_human_perception_normalizes_whitespace() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $messy = "Line1\n\n\n  Line2    Line3\n\tLine4";
        $this->injector->add_human_perception( $messy );

        $this->assertStringContainsString( 'Line1 Line2 Line3 Line4', $capturedPrompt,
            'Whitespace harus dinormalisasi'
        );
    }

    // ====================================================================
    // ADD_HUMAN_PERCEPTION — TRUNCATION
    // ====================================================================

    /**
     * Test bahwa content yang sangat panjang di-truncate ke 4000 chars.
     */
    public function test_add_human_perception_truncates_long_content() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        // 5000 chars of clean text
        $longContent = str_repeat( 'A ', 3000 ); // ~6000 chars
        $this->injector->add_human_perception( $longContent );

        // Extract the part after "Content:" label
        $contentPos = strpos( $capturedPrompt, 'Content: ' );
        $afterLabel  = substr( $capturedPrompt, $contentPos + 9 );
        $truncated   = trim( $afterLabel );

        $this->assertLessThanOrEqual( 4500, strlen( $truncated ),
            'Content setelah di-truncate harus <= 4000 chars (plus label margin)'
        );
    }

    /**
     * Test bahwa context yang panjang di-truncate ke 3000 chars.
     */
    public function test_add_human_perception_truncates_long_context() {
        $this->setUpAiOptions();
        $capturedPrompt = '';
        $mockAi = $this->createMockAiClientWithCapture( $capturedPrompt );
        $this->injectAiClient( $mockAi );

        $longContext = str_repeat( 'Long context info. ', 300 );
        $this->injector->add_human_perception( 'Short content.', $longContext );

        // Extract context part
        $contextStart = strpos( $capturedPrompt, 'CONTEXT / KNOWLEDGE BASE' );
        $contentStart = strpos( $capturedPrompt, 'Content:' );
        $contextSection = substr( $capturedPrompt, $contextStart, $contentStart - $contextStart );

        $this->assertLessThanOrEqual( 3400, strlen( $contextSection ),
            'Context section harus <= 3000 chars (plus label + instruction margin)'
        );
    }

    // ====================================================================
    // CLEAN_TEXT — DIRECT TEST VIA REFLECTION
    // ====================================================================

    /**
     * Test clean_text langsung via reflection.
     */
    public function test_clean_text_removes_html() {
        $result = $this->invokeCleanText( '<h1>Title</h1><p>Paragraph</p>' );
        $this->assertSame( 'TitleParagraph', $result,
            'strip_tags tidak menambah spasi antar tag'
        );
    }

    public function test_clean_text_removes_script_and_style() {
        $input  = 'Before<script>alert("x")</script>After<style>.css{}</style>End';
        $result = $this->invokeCleanText( $input );
        $this->assertSame( 'BeforeAfterEnd', $result,
            'strip_tags tidak menambah spasi antar tag'
        );
    }

    public function test_clean_text_decodes_html_entities() {
        $result = $this->invokeCleanText( ' &amp; &lt; &gt; &quot; &euro; ' );
        $this->assertStringContainsString( '&', $result, '&amp; harus jadi &' );
        $this->assertStringContainsString( '<', $result, '&lt; harus jadi <' );
        $this->assertStringContainsString( '>', $result, '&gt; harus jadi >' );
        $this->assertStringContainsString( '"', $result, '&quot; harus jadi "' );
        $this->assertStringContainsString( '€', $result, '&euro; harus jadi €' );
    }

    public function test_clean_text_decodes_nbsp() {
        $result = $this->invokeCleanText( 'Hello&nbsp;World' );
        // &nbsp; didecode menjadi non-breaking space (U+00A0), bukan spasi biasa
        $nbsp = "\u{00A0}";
        $this->assertStringContainsString( "Hello{$nbsp}World", $result,
            '&nbsp; harus jadi non-breaking space (U+00A0)'
        );
    }

    public function test_clean_text_normalizes_multiple_whitespace() {
        $result = $this->invokeCleanText( "A   B\n\n\nC\t\tD" );
        $this->assertSame( 'A B C D', $result );
    }

    public function test_clean_text_trims_whitespace() {
        $result = $this->invokeCleanText( "   Hello World   \n" );
        $this->assertSame( 'Hello World', $result );
    }

    public function test_clean_text_empty_returns_empty() {
        $result = $this->invokeCleanText( '' );
        $this->assertSame( '', $result );
    }

    public function test_clean_text_whitespace_only_returns_empty_after_trim() {
        $result = $this->invokeCleanText( "   \n  \n  " );
        $this->assertSame( '', $result );
    }

    public function test_clean_text_preserves_normal_text() {
        $text   = 'This is normal text without any tags.';
        $result = $this->invokeCleanText( $text );
        $this->assertSame( $text, $result );
    }

    // ====================================================================
    // HELPER METHODS
    // ====================================================================

    /**
     * Setup default AI options untuk test add_human_perception.
     */
    private function setUpAiOptions(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_ai_provider'] = 'openai';
        $_autoblog_mock_options['autoblog_ai_model']    = 'gpt-4o';
        OptionCache::flush();
    }

    /**
     * Buat mock AIClient dengan return value tertentu.
     *
     * @param mixed $returnValue Nilai yang dikembalikan oleh generate_text().
     * @return AIClient
     */
    private function createMockAiClient( $returnValue ) {
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'generate_text' )
               ->willReturn( $returnValue );
        return $mockAi;
    }

    /**
     * Buat mock AIClient yang menangkap prompt ke variabel reference.
     *
     * @param string &$capturedPrompt Variabel untuk menangkap prompt.
     * @return AIClient
     */
    private function createMockAiClientWithCapture( &$capturedPrompt ) {
        $mockAi = $this->createMock( AIClient::class );
        $mockAi->method( 'generate_text' )
               ->willReturnCallback( function( $prompt ) use ( &$capturedPrompt ) {
                   $capturedPrompt = $prompt;
                   return 'Mock angle from AI.';
               } );
        return $mockAi;
    }

    /**
     * Inject mock AIClient ke AngleInjector via reflection.
     *
     * @param AIClient $mockAi
     */
    private function injectAiClient( $mockAi ): void {
        $reflection = new \ReflectionClass( AngleInjector::class );
        $prop       = $reflection->getProperty( 'ai_client' );
        $prop->setAccessible( true );
        $prop->setValue( $this->injector, $mockAi );
    }

    /**
     * Panggil clean_text() private method via reflection.
     *
     * @param string $text
     * @return string
     */
    private function invokeCleanText( string $text ): string {
        $reflection = new \ReflectionClass( AngleInjector::class );
        $method     = $reflection->getMethod( 'clean_text' );
        $method->setAccessible( true );
        return $method->invokeArgs( $this->injector, [ $text ] );
    }
}
