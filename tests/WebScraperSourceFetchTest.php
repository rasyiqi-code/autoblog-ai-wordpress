<?php
/**
 * Unit Test untuk WebScraperSource::fetch_data() dan passes_filters().
 *
 * fetch_data() menggunakan Guzzle HTTP Client di internal — test ini menggunakan
 * PHP built-in server (php -S) untuk serve HTML controlled sebagai mock HTTP response.
 * Ini memungkinkan pengujian:
 *   - CSS selector → XPath conversion (#id, .class, tag)
 *   - DOMDocument + DOMXPath extraction
 *   - Error handling (koneksi gagal, URL invalid)
 *   - Readability fallback (selector tidak match)
 *   - Keyword filtering dalam fetch_data flow
 *
 * passes_filters() diuji via reflection sebagai pure function test tanpa HTTP.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      regression
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Sources\WebScraperSource;

class WebScraperSourceFetchTest extends TestCase {

    /** @var int Port untuk PHP built-in server */
    private static $serverPort = 18789;

    /** @var resource Process handle PHP built-in server */
    private static $serverProcess;

    /** @var string Path ke file HTML test */
    private static $htmlPath;

    /** @var string Base URL server lokal */
    private static $baseUrl;

    // ====================================================================
    // SETUP / TEARDOWN — PHP Built-in Server
    // ====================================================================

    public static function setUpBeforeClass(): void {
        self::$htmlPath = sys_get_temp_dir() . '/ws_test_page.html';
        self::$baseUrl  = 'http://127.0.0.1:' . self::$serverPort;

        // Buat halaman HTML test yang kaya untuk menguji selector dan parsing
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Test Artikel AI Indonesia</title>
</head>
<body>
    <nav id="navigation">
        <a href="/" class="nav-link">Home</a>
        <a href="/about" class="nav-link">Tentang</a>
    </nav>

    <article id="main-content">
        <h1 class="article-title">Perkembangan AI di Indonesia Tahun 2025</h1>

        <p class="content-paragraph">Kecerdasan buatan (AI) sedang berkembang pesat di Indonesia.
        Banyak perusahaan teknologi yang mulai mengadopsi AI dalam berbagai sektor seperti
        kesehatan, keuangan, dan pendidikan. Hal ini didorong oleh ketersediaan data yang
        melimpah dan infrastruktur digital yang semakin baik.</p>

        <p class="content-paragraph">Machine learning dan deep learning menjadi dua cabang AI
        yang paling banyak digunakan. Startup-startup teknologi di Indonesia berlomba-lomba
        mengembangkan solusi berbasis AI untuk menjawab tantangan lokal. Pemerintah juga
        mendukung pengembangan AI melalui berbagai kebijakan strategis nasional.</p>

        <p class="content-paragraph">Promo spesial untuk produk kecantikan berbasis AI.
        Dengan teknologi AI, Anda bisa mendapatkan rekomendasi produk yang sesuai dengan
        jenis kulit Anda. Promo berlaku hingga akhir bulan ini dengan diskon hingga 50 persen.</p>

        <p class="content-paragraph">Masa depan AI di Indonesia sangat cerah. Dengan populasi
        yang besar dan penetrasi internet yang tinggi, Indonesia memiliki potensi besar untuk
        menjadi pemimpin AI di Asia Tenggara. Kolaborasi antara akademisi, industri, dan
        pemerintah akan menjadi kunci keberhasilan transformasi digital.</p>
    </article>

    <aside id="sidebar">
        <ul>
            <li>Berita teknologi terkini</li>
            <li>Review produk AI</li>
            <li>Promo dan diskon spesial</li>
        </ul>
    </aside>

    <footer id="footer">
        <p class="footer-text">Hak Cipta &copy; 2025 — Test Page untuk Unit Test</p>
    </footer>
</body>
</html>
HTML;

        file_put_contents( self::$htmlPath, $html );

        // Start PHP built-in server menggunakan PHP_BINARY agar pakai PHP runner yang sama
        $docRoot = sys_get_temp_dir();
        $cmd     = sprintf(
            '%s -S 127.0.0.1:%d -t %s > /dev/null 2>&1 & echo $!',
            PHP_BINARY,
            self::$serverPort,
            escapeshellarg( $docRoot )
        );

        $output = [];
        exec( $cmd, $output, $exitCode );
        self::$serverProcess = isset( $output[0] ) ? (int) $output[0] : null;

        // Tunggu server siap (max 2 detik)
        $maxWait = 20; // 20 * 100ms = 2 detik
        for ( $i = 0; $i < $maxWait; $i++ ) {
            usleep( 100000 ); // 100ms
            // Gunakan fsockopen — lebih reliable daripada get_headers() yang bisa di-disable
            $fp = @fsockopen( '127.0.0.1', self::$serverPort, $errno, $errstr, 1 );
            if ( $fp !== false ) {
                fclose( $fp );
                break;
            }
        }
    }

    public static function tearDownAfterClass(): void {
        // Matikan PHP built-in server
        if ( self::$serverProcess ) {
            // Kirim SIGTERM
            exec( 'kill ' . self::$serverProcess . ' 2>/dev/null' );
            usleep( 100000 );
            // Paksa kill jika masih hidup
            exec( 'kill -9 ' . self::$serverProcess . ' 2>/dev/null' );
        }

        // Hapus file HTML temporer
        if ( file_exists( self::$htmlPath ) ) {
            @unlink( self::$htmlPath );
        }

        // Bersihkan juga proses server yang mungkin orphan
        exec( 'lsof -ti:18789 2>/dev/null | xargs kill -9 2>/dev/null' );
    }

    // ====================================================================
    // fetch_data() — HTML PARSING WITH MOCK HTTP (PHP Built-in Server)
    // ====================================================================

    /**
     * Test fetch_data() dengan CSS ID selector (#main-content).
     *
     * Memverifikasi bahwa:
     * - HTTP request ke server lokal berhasil (status 200)
     * - Selector #id → XPath //*[@id='$id'] berfungsi
     * - Konten diekstrak dengan benar
     * - Struktur data item lengkap (content, text_content, source_type, source_url, title)
     */
    public function test_fetch_data_with_id_selector_extracts_content() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            '#main-content'
        );

        $result = $source->fetch_data();

        $this->assertNotEmpty( $result, 'Should extract content with #main-content selector' );

        $item = $result[0];
        $this->assertArrayHasKey( 'content', $item );
        $this->assertArrayHasKey( 'text_content', $item );
        $this->assertArrayHasKey( 'source_type', $item );
        $this->assertArrayHasKey( 'source_url', $item );
        $this->assertArrayHasKey( 'title', $item );

        $this->assertEquals( 'web', $item['source_type'] );
        $this->assertEquals( self::$baseUrl . '/ws_test_page.html', $item['source_url'] );

        // text_content harus mengandung teks dari dalam #main-content
        $this->assertStringContainsStringIgnoringCase( 'ai', $item['text_content'] );
        $this->assertStringContainsString( 'Indonesia', $item['text_content'] );
        $this->assertStringContainsStringIgnoringCase( 'machine learning', $item['text_content'] );
    }

    /**
     * Test fetch_data() dengan CSS class selector (.content-paragraph).
     *
     * Memverifikasi bahwa selector .class → XPath contains(@class) berfungsi
     * dan semua node yang cocok dikembalikan.
     */
    public function test_fetch_data_with_class_selector_extracts_all_matches() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            '.content-paragraph'
        );

        $result = $source->fetch_data();

        // Ada 4 <p class="content-paragraph"> di halaman
        $this->assertCount( 4, $result, 'Should find 4 paragraphs with .content-paragraph' );

        // Setiap item harus punya text_content, source_type, source_url
        foreach ( $result as $item ) {
            $this->assertArrayHasKey( 'text_content', $item );
            $this->assertEquals( 'web', $item['source_type'] );
            $this->assertEquals( self::$baseUrl . '/ws_test_page.html', $item['source_url'] );
        }
    }

    /**
     * Test fetch_data() dengan tag selector (h1).
     *
     * Memverifikasi bahwa plain tag name → XPath //h1 berfungsi.
     */
    public function test_fetch_data_with_tag_selector_extracts_title() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            'h1'
        );

        $result = $source->fetch_data();

        $this->assertNotEmpty( $result );
        $this->assertCount( 1, $result, 'Should find 1 h1 element' );
        $this->assertStringContainsString( 'Perkembangan AI', $result[0]['text_content'] );
    }

    /**
     * Test fetch_data() — selector tidak match → fallback ke Readability.
     *
     * Saat selector #nonexistent tidak menemukan node, fetch_data() akan:
     * 1. Coba XPath query → 0 results
     * 2. Fallback ke fetch_with_readability()
     * 3. Readability berusaha mengekstrak konten utama
     *
     * Ekstraksi Readability tergantung pada heuristics library (text density,
     * minimum content length). Jika tidak berhasil, hasil adalah array kosong.
     * Test memverifikasi bahwa path kode berjalan tanpa exception dan struktur
     * data benar jika konten berhasil diekstrak.
     */
    public function test_fetch_data_falls_back_to_readability_when_selector_yields_no_results() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            '#nonexistent-element'
        );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );

        // Jika Readability berhasil ekstrak konten
        if ( ! empty( $result ) ) {
            $item = $result[0];
            $this->assertArrayHasKey( 'title', $item );
            $this->assertArrayHasKey( 'content', $item );
            $this->assertEquals( 'web_auto', $item['source_type'] );
            $this->assertEquals( self::$baseUrl . '/ws_test_page.html', $item['source_url'] );
        }
        // Jika Readability gagal (heuristics tidak terpenuhi), array kosong juga valid
    }

    /**
     * Test fetch_data() dengan selector kosong → langsung Readability.
     *
     * Jika selector = '', fetch_data() langsung memanggil fetch_with_readability()
     * tanpa melalui path CSS selector. Test memverifikasi bahwa path kode berjalan
     * dan struktur data benar jika konten berhasil diekstrak.
     */
    public function test_fetch_data_with_empty_selector_uses_readability_directly() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html'
            // selector = '' (default) → readability path
        );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );

        if ( ! empty( $result ) ) {
            $this->assertEquals( 'web_auto', $result[0]['source_type'] );
            $this->assertArrayHasKey( 'content', $result[0] );
            $this->assertArrayHasKey( 'title', $result[0] );
        }
    }

    /**
     * Test fetch_data() dengan empty selector + match keyword — filter berfungsi.
     *
     * Readability mengekstrak konten → passes_filters() dipanggil dengan
     * title + content → match 'AI' → true → konten dikembalikan.
     *
     * Jika Readability tidak berhasil ekstrak (heuristics), array kosong valid.
     */
    public function test_fetch_data_readability_with_match_keyword_filters_content() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            '',          // selector kosong → readability
            'AI',        // match keywords
            ''           // no negative keywords
        );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );

        // Jika Readability berhasil ekstrak dan konten mengandung 'AI'
        if ( ! empty( $result ) ) {
            $this->assertEquals( 'web_auto', $result[0]['source_type'] );
        }
    }

    /**
     * Test fetch_data() dengan CSS selector + match keyword — hanya node relevan.
     *
     * .content-paragraph ada 4 paragraf. Dengan match_keywords 'Kecerdasan buatan':
     * - Paragraf 1: contains 'Kecerdasan buatan' → PASS
     * - Paragraf 2: no 'Kecerdasan buatan' → FAIL (filtered out)
     * - Paragraf 3: no 'Kecerdasan buatan' → FAIL (filtered out)
     * - Paragraf 4: no 'Kecerdasan buatan' → FAIL (filtered out)
     */
    public function test_fetch_data_with_class_selector_and_match_keywords() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            '.content-paragraph',
            'Kecerdasan buatan', // match keywords — hanya paragraf 1 yang mengandung ini
            ''                   // no negative keywords
        );

        $result = $source->fetch_data();

        // Hanya paragraf 1 yang mengandung 'Kecerdasan buatan'
        $this->assertCount( 1, $result, 'Only the paragraph containing "Kecerdasan buatan" should pass' );

        $this->assertStringContainsString( 'Kecerdasan buatan', $result[0]['text_content'] );
        $this->assertStringContainsString( 'AI', $result[0]['text_content'] );
    }

    /**
     * Test fetch_data() dengan CSS selector + negative keyword — node difilter.
     *
     * .content-paragraph dengan negative_keywords 'promo':
     * - Paragraf 1: no 'promo' (AI) → PASS
     * - Paragraf 2: no 'promo' (machine learning) → PASS
     * - Paragraf 3: contains 'Promo' (case-insensitive match 'promo') → FAIL
     * - Paragraf 4: no 'promo' (masa depan AI) → PASS
     */
    public function test_fetch_data_with_class_selector_and_negative_keywords() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            '.content-paragraph',
            '',            // no match keywords (all pass)
            'promo'        // negative keywords — exclude yang mengandung 'promo'
        );

        $result = $source->fetch_data();

        // Harusnya 3 paragraf (semua kecuali yang mengandung 'promo')
        $this->assertCount( 3, $result, 'Should exclude the promo paragraph' );

        // Tidak ada item yang mengandung 'promo' (case-insensitive)
        foreach ( $result as $item ) {
            $text = strtolower( $item['text_content'] );
            $this->assertStringNotContainsString( 'promo', $text, 'Promo paragraphs should be filtered out' );
        }
    }

    /**
     * Test fetch_data() dengan selector nomatch + match keywords — fallback readability.
     *
     * Saat selector tidak match, fallback ke readability, lalu readability
     * mengekstrak konten. Match keyword 'kucing' tidak ada di halaman → empty.
     */
    public function test_fetch_data_fallback_readability_with_unmatched_keyword_returns_empty() {
        $source = new WebScraperSource(
            self::$baseUrl . '/ws_test_page.html',
            '#nonexistent',
            'kucing',      // match keyword yang tidak ada di halaman
            ''
        );

        $result = $source->fetch_data();

        // Selector tidak match → fallback readability → konten tidak mengandung 'kucing' → empty
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ====================================================================
    // fetch_data() — HTTP ERROR HANDLING
    // ====================================================================

    /**
     * Test fetch_data() — koneksi gagal (connection refused).
     *
     * URL http://127.0.0.1:1/ — port 1 tidak ada service yang listen.
     * Guzzle Client throw ConnectException → catch(\Exception) → empty array.
     *
     * @group integration
     */
    public function test_fetch_data_returns_empty_on_connection_error() {
        $source = new WebScraperSource(
            'http://127.0.0.1:1/test-page',
            '#main-content'
        );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    /**
     * Test fetch_data() readability fallback — koneksi gagal.
     *
     * @group integration
     */
    public function test_fetch_data_readability_returns_empty_on_connection_error() {
        $source = new WebScraperSource(
            'http://127.0.0.1:1/artikel'
            // selector = '' (default) → readability path
        );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    /**
     * Test fetch_data() mengembalikan array kosong jika validate_source() gagal.
     */
    public function test_fetch_data_returns_empty_for_invalid_url() {
        $source = new WebScraperSource( 'not-a-url' );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    /**
     * Test fetch_data() mengembalikan array kosong untuk URL kosong.
     */
    public function test_fetch_data_returns_empty_for_empty_url() {
        $source = new WebScraperSource( '' );

        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ====================================================================
    // passes_filters() — PURE FUNCTION TESTS (via reflection)
    // ====================================================================

    public function test_passes_filters_match_keyword_found_returns_true() {
        $source = new WebScraperSource( 'https://example.com', '', 'AI', '' );
        $result = $this->invokePassesFilters( $source, 'Berita tentang AI dan machine learning' );
        $this->assertTrue( $result );
    }

    public function test_passes_filters_match_keyword_not_found_returns_false() {
        $source = new WebScraperSource( 'https://example.com', '', 'AI', '' );
        $result = $this->invokePassesFilters( $source, 'Berita tentang bisnis dan keuangan' );
        $this->assertFalse( $result );
    }

    public function test_passes_filters_match_keyword_case_insensitive() {
        $source = new WebScraperSource( 'https://example.com', '', 'teknologi', '' );
        $result = $this->invokePassesFilters( $source, 'Perkembangan Teknologi AI' );
        $this->assertTrue( $result );
    }

    public function test_passes_filters_multiple_match_keywords_any_one_suffices() {
        $source = new WebScraperSource( 'https://example.com', '', 'AI, machine learning, deep learning', '' );
        $result = $this->invokePassesFilters( $source, 'Penerapan machine learning di industri' );
        $this->assertTrue( $result );
    }

    public function test_passes_filters_negative_keyword_found_returns_false() {
        $source = new WebScraperSource( 'https://example.com', '', '', 'spam' );
        $result = $this->invokePassesFilters( $source, 'Ini adalah konten spam yang tidak diinginkan' );
        $this->assertFalse( $result );
    }

    public function test_passes_filters_negative_keyword_not_found_returns_true() {
        $source = new WebScraperSource( 'https://example.com', '', '', 'spam' );
        $result = $this->invokePassesFilters( $source, 'Ini adalah konten berkualitas tentang AI' );
        $this->assertTrue( $result );
    }

    public function test_passes_filters_negative_keyword_case_insensitive() {
        $source = new WebScraperSource( 'https://example.com', '', '', 'PROMO' );
        $result = $this->invokePassesFilters( $source, 'Dapatkan promo spesial hari ini' );
        $this->assertFalse( $result );
    }

    public function test_passes_filters_multiple_negative_keywords_any_one_triggers_rejection() {
        $source = new WebScraperSource( 'https://example.com', '', '', 'spam, promo, iklan' );
        $result = $this->invokePassesFilters( $source, 'Halaman ini berisi iklan dan konten biasa' );
        $this->assertFalse( $result );
    }

    public function test_passes_filters_match_and_negative_both_required() {
        $source = new WebScraperSource( 'https://example.com', '', 'AI', 'spam' );

        // Match 'AI' found, negative 'spam' not found → true
        $result = $this->invokePassesFilters( $source, 'Perkembangan AI di Indonesia' );
        $this->assertTrue( $result );

        // Match 'AI' found, negative 'spam' also found → false
        $result = $this->invokePassesFilters( $source, 'AI dan spam dalam pemasaran digital' );
        $this->assertFalse( $result );

        // Match 'AI' not found → false (regardless of negative)
        $result = $this->invokePassesFilters( $source, 'Berita tentang bisnis' );
        $this->assertFalse( $result );
    }

    public function test_passes_filters_no_keywords_always_true() {
        $source = new WebScraperSource( 'https://example.com' );
        $result = $this->invokePassesFilters( $source, 'Konten apa pun harus lolos tanpa filter' );
        $this->assertTrue( $result );

        $result = $this->invokePassesFilters( $source, '' );
        $this->assertTrue( $result );
    }

    public function test_passes_filters_empty_text_with_keywords_returns_false() {
        $source = new WebScraperSource( 'https://example.com', '', 'AI', '' );
        $result = $this->invokePassesFilters( $source, '' );
        $this->assertFalse( $result );
    }

    public function test_passes_filters_handles_empty_keyword_in_comma_list() {
        $source = new WebScraperSource( 'https://example.com', '', 'AI, , teknologi', '' );
        // Keyword '' (empty) harus di-skip saat array_map('trim', explode(',', ...))
        $result = $this->invokePassesFilters( $source, 'Perkembangan teknologi informasi' );
        $this->assertTrue( $result );
    }

    // ====================================================================
    // HELPER METHODS
    // ====================================================================

    /**
     * Panggil private method passes_filters via reflection.
     */
    private function invokePassesFilters( WebScraperSource $source, string $text ): bool {
        $reflection = new \ReflectionClass( WebScraperSource::class );
        $method     = $reflection->getMethod( 'passes_filters' );
        $method->setAccessible( true );
        return $method->invokeArgs( $source, [ $text ] );
    }
}
