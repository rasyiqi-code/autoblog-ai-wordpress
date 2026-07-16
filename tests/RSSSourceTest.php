<?php
/**
 * Unit Test untuk RSSSource.
 *
 * Memverifikasi:
 * 1. Constructor & interface compliance
 * 2. validate_source() untuk URL valid / tidak valid / kosong
 * 3. get_display_name()
 * 4. passes_filters() — match keywords, negative keywords, case-insensitive,
 *    whitespace handling, keyword di description saja, partial word match
 * 5. fetch_data() — RSS XML parsing via SimpleXML, item extraction,
 *    content:encoded namespace, empty response, WP_Error, invalid XML
 * 6. fetch_full_content() — Readability tidak terinstall, path error
 *
 * Menggunakan global HTTP mock override di bootstrap.php untuk mensimulasikan
 * wp_remote_get / wp_remote_retrieve_body / is_wp_error tanpa koneksi nyata.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Sources\RSSSource;

/**
 * Unit Test untuk RSSSource.
 *
 * @group unit
 * @group regression
 * @group rss_source
 */
class RSSSourceTest extends TestCase {

    // ================================================================
    // SETUP / TEARDOWN
    // ================================================================

    protected function setUp(): void {
        parent::setUp();
        // Hapus global HTTP mock sebelum setiap test
        unset( $GLOBALS['_autoblog_mock_remote_response'] );
        unset( $GLOBALS['_autoblog_mock_remote_body'] );
        unset( $GLOBALS['_autoblog_mock_is_wp_error'] );
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Invoke private/protected method via reflection.
     */
    private function invokeMethod( $object, string $methodName, array $parameters = array() ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }

    /**
     * Setel mock HTTP untuk RSS feed.
     *
     * @param string $xml_body RSS XML content.
     */
    private function mockHttpResponse( string $xml_body ): void {
        $GLOBALS['_autoblog_mock_remote_response'] = array( 'body' => $xml_body );
        $GLOBALS['_autoblog_mock_remote_body']     = $xml_body;
        $GLOBALS['_autoblog_mock_is_wp_error']     = false;
    }

    /**
     * Dapatkan RSS XML untuk testing (2 item dengan konten pendek).
     */
    private function getRssXml(): string {
        return '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Test Feed</title>
    <link>https://example.com</link>
    <description>Test RSS feed</description>
    <item>
      <title>Perkembangan Teknologi AI 2026</title>
      <link>https://example.com/ai-2026</link>
      <description>Perkembangan terbaru teknologi AI dan machine learning</description>
      <content:encoded><![CDATA[<p>Artikel pendek tentang AI.</p>]]></content:encoded>
      <pubDate>Mon, 01 Jan 2026 00:00:00 GMT</pubDate>
      <guid>https://example.com/ai-2026</guid>
    </item>
    <item>
      <title>Resep Masakan Rumahan</title>
      <link>https://example.com/resep-masakan</link>
      <description>Resep mudah untuk masakan rumahan sehari-hari</description>
      <pubDate>Tue, 02 Jan 2026 00:00:00 GMT</pubDate>
      <guid>https://example.com/resep-masakan</guid>
    </item>
  </channel>
</rss>';
    }

    // ================================================================
    // CONSTRUCTOR
    // ================================================================

    public function test_constructor_stores_url() {
        $source = new RSSSource( 'https://example.com/feed.xml' );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'url' );
        $prop->setAccessible( true );

        $this->assertEquals( 'https://example.com/feed.xml', $prop->getValue( $source ) );
    }

    public function test_constructor_stores_match_keywords() {
        $source = new RSSSource( 'https://example.com/feed', 'teknologi, AI' );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'match_keywords' );
        $prop->setAccessible( true );

        $this->assertEquals( 'teknologi, AI', $prop->getValue( $source ) );
    }

    public function test_constructor_stores_negative_keywords() {
        $source = new RSSSource( 'https://example.com/feed', '', 'judi, slot' );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'negative_keywords' );
        $prop->setAccessible( true );

        $this->assertEquals( 'judi, slot', $prop->getValue( $source ) );
    }

    public function test_constructor_default_keywords_are_empty_string() {
        $source = new RSSSource( 'https://example.com/feed' );

        $reflection = new \ReflectionClass( $source );
        $mk_prop    = $reflection->getProperty( 'match_keywords' );
        $nk_prop    = $reflection->getProperty( 'negative_keywords' );
        $mk_prop->setAccessible( true );
        $nk_prop->setAccessible( true );

        $this->assertEquals( '', $mk_prop->getValue( $source ) );
        $this->assertEquals( '', $nk_prop->getValue( $source ) );
    }

    // ================================================================
    // VALIDATE SOURCE
    // ================================================================

    public function test_validate_source_accepts_valid_url() {
        $source = new RSSSource( 'https://example.com/feed.xml' );
        $this->assertTrue( $source->validate_source() );
    }

    public function test_validate_source_rejects_invalid_url() {
        $source = new RSSSource( 'not-a-valid-url' );
        $this->assertFalse( $source->validate_source() );
    }

    public function test_validate_source_rejects_empty_string() {
        $source = new RSSSource( '' );
        $this->assertFalse( $source->validate_source() );
    }

    public function test_validate_source_rejects_url_without_scheme() {
        // filter_var dengan FILTER_VALIDATE_URL membutuhkan scheme
        $source = new RSSSource( 'example.com/feed' );
        $this->assertFalse( $source->validate_source() );
    }

    // ================================================================
    // GET DISPLAY NAME
    // ================================================================

    public function test_get_display_name_returns_rss_feed() {
        $source = new RSSSource( 'https://example.com/feed' );
        $this->assertEquals( 'RSS Feed', $source->get_display_name() );
    }

    // ================================================================
    // INTERFACE
    // ================================================================

    public function test_implements_source_interface() {
        $reflection = new \ReflectionClass( RSSSource::class );
        $this->assertTrue(
            $reflection->implementsInterface( 'Autoblog\\Interfaces\\SourceInterface' )
        );
    }

    // ================================================================
    // PASSES FILTERS — MATCH KEYWORDS
    // ================================================================

    public function test_passes_filters_match_keyword_in_title() {
        $source = new RSSSource( 'https://example.com/feed', 'teknologi' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Perkembangan teknologi AI',
            'Artikel tentang machine learning',
        ) );
        $this->assertTrue( $pass, 'Title mengandung "teknologi" harus lolos' );
    }

    public function test_passes_filters_match_keyword_in_description() {
        $source = new RSSSource( 'https://example.com/feed', 'machine' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Perkembangan AI',
            'Artikel tentang machine learning',
        ) );
        $this->assertTrue( $pass, 'Description mengandung "machine" harus lolos' );
    }

    public function test_passes_filters_no_match_rejected() {
        $source = new RSSSource( 'https://example.com/feed', 'teknologi' );

        $fail = $this->invokeMethod( $source, 'passes_filters', array(
            'Kecerdasan buatan masa kini',
            'Diskusi umum tentang AI',
        ) );
        $this->assertFalse( $fail, 'Tanpa kata "teknologi" harus diblokir' );
    }

    public function test_passes_filters_multiple_match_keywords_or_logic() {
        // Cukup satu keyword yang cocok
        $source = new RSSSource( 'https://example.com/feed', 'teknologi, AI, data' );

        // "data" ada di description
        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Artikel Machine Learning',
            'Analisis data besar',
        ) );
        $this->assertTrue( $pass, '"data" ada di description, harus lolos' );

        // Tidak ada satupun keyword yang cocok
        $fail = $this->invokeMethod( $source, 'passes_filters', array(
            'Resep Masakan',
            'Cara membuat nasi goreng',
        ) );
        $this->assertFalse( $fail, 'Tanpa keyword satupun harus diblokir' );
    }

    // ================================================================
    // PASSES FILTERS — NEGATIVE KEYWORDS
    // ================================================================

    public function test_passes_filters_negative_keyword_in_title_blocked() {
        $source = new RSSSource( 'https://example.com/feed', '', 'judi' );

        $fail = $this->invokeMethod( $source, 'passes_filters', array(
            'Situs judi online terpercaya',
            'Daftar situs terbaik',
        ) );
        $this->assertFalse( $fail, 'Title mengandung "judi" harus diblokir' );
    }

    public function test_passes_filters_negative_keyword_in_description_blocked() {
        $source = new RSSSource( 'https://example.com/feed', '', 'promo' );

        $fail = $this->invokeMethod( $source, 'passes_filters', array(
            'Tips Kesehatan Keluarga',
            'Dapatkan promo special hari ini',
        ) );
        $this->assertFalse( $fail, 'Description mengandung "promo" harus diblokir' );
    }

    public function test_passes_filters_no_negative_match_allowed() {
        $source = new RSSSource( 'https://example.com/feed', '', 'judi, slot' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Game online mobile',
            'Teknologi game modern',
        ) );
        $this->assertTrue( $pass, 'Tanpa kata terlarang harus lolos' );
    }

    public function test_passes_filters_multiple_negative_keywords_any_one_blocks() {
        // Jika salah satu negative keyword cocok, langsung block
        $source = new RSSSource( 'https://example.com/feed', '', 'judi, slot, promo' );

        $fail = $this->invokeMethod( $source, 'passes_filters', array(
            'Promo spesial akhir tahun',
            'Diskon besar-besaran',
        ) );
        $this->assertFalse( $fail, '"promo" di title harus diblokir walaupun "judi" tidak ada' );
    }

    // ================================================================
    // PASSES FILTERS — COMBINED MATCH + NEGATIVE
    // ================================================================

    public function test_passes_filters_both_match_and_negative() {
        // Keyword harus mengandung "teknologi" DAN tidak mengandung "judi"
        $source = new RSSSource( 'https://example.com/feed', 'teknologi', 'judi' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Perkembangan teknologi AI',
            'Machine learning modern',
        ) );
        $this->assertTrue( $pass, 'Match "teknologi" + tidak ada "judi" => lolos' );

        $fail_match = $this->invokeMethod( $source, 'passes_filters', array(
            'Resep Masakan',
            'Cara memasak nasi',
        ) );
        $this->assertFalse( $fail_match, 'Tidak match "teknologi" => diblokir' );

        $fail_negative = $this->invokeMethod( $source, 'passes_filters', array(
            'Teknologi judi online',
            'Sistem judi terbaru',
        ) );
        $this->assertFalse( $fail_negative, 'Match "teknologi" tapi ada "judi" => diblokir' );
    }

    // ================================================================
    // PASSES FILTERS — EDGE CASES
    // ================================================================

    public function test_passes_filters_empty_keywords_allows_all() {
        $source = new RSSSource( 'https://example.com/feed' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Any random title',
            'Any random description',
        ) );
        $this->assertTrue( $pass, 'Tanpa filter keyword, semua item lolos' );
    }

    public function test_passes_filters_case_insensitive() {
        $source = new RSSSource( 'https://example.com/feed', 'TEKNOLOGI' );

        // Title lowercase
        $pass_lower = $this->invokeMethod( $source, 'passes_filters', array(
            'perkembangan teknologi AI',
            'deskripsi',
        ) );
        $this->assertTrue( $pass_lower, 'Case insensitive: title lowercase tetap match' );

        // Title mixed case
        $pass_mixed = $this->invokeMethod( $source, 'passes_filters', array(
            'Perkembangan Teknologi AI',
            'deskripsi',
        ) );
        $this->assertTrue( $pass_mixed, 'Case insensitive: title mixed case tetap match' );
    }

    public function test_passes_filters_whitespace_around_keywords() {
        // Keywords dengan spasi ekstra di sekitar koma
        $source = new RSSSource( 'https://example.com/feed', '  teknologi ,  AI  ' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Perkembangan teknologi AI',
            'deskripsi',
        ) );
        $this->assertTrue( $pass, 'Whitespace di sekitar koma di-trim, harus tetap match' );
    }

    public function test_passes_filters_strpos_matches_substring() {
        // "teknologi" akan match "teknologiinformasi" karena strpos mencari substring
        $source = new RSSSource( 'https://example.com/feed', 'teknologi' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            'Perkembangan teknologiinformasi terkini',
            'deskripsi',
        ) );
        $this->assertTrue( $pass, 'strpos: "teknologi" adalah substring dari "teknologiinformasi" => harus match' );
    }

    public function test_passes_filters_empty_title_checks_description_only() {
        $source = new RSSSource( 'https://example.com/feed', 'keyword' );

        $pass = $this->invokeMethod( $source, 'passes_filters', array(
            '',
            'Mengandung keyword yang dicari',
        ) );
        $this->assertTrue( $pass, 'Keyword di description harus tetap match walau title kosong' );
    }

    // ================================================================
    // FETCH DATA — ERROR PATHS
    // ================================================================

    public function test_fetch_data_returns_empty_array_for_invalid_url() {
        $source = new RSSSource( '' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_returns_empty_array_for_empty_http_body() {
        // Default mock: wp_remote_retrieve_body return ''
        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_returns_empty_array_for_wp_error_response() {
        $GLOBALS['_autoblog_mock_is_wp_error']     = true;
        $GLOBALS['_autoblog_mock_remote_response'] = new \WP_Error( 'http_error', 'Connection failed' );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_returns_empty_array_for_invalid_xml() {
        $this->mockHttpResponse( 'This is not valid XML' );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_returns_empty_array_for_xml_without_channel() {
        $this->mockHttpResponse( '<?xml version="1.0"?><rss version="2.0"><notchannel></notchannel></rss>' );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // FETCH DATA — SUCCESS PATH
    // ================================================================

    public function test_fetch_data_parses_rss_items_successfully() {
        $this->mockHttpResponse( $this->getRssXml() );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertCount( 2, $result );
        $this->assertEquals( 'Perkembangan Teknologi AI 2026', $result[0]['title'] );
        $this->assertEquals( 'Resep Masakan Rumahan', $result[1]['title'] );
    }

    public function test_fetch_data_sets_source_type_and_url() {
        $this->mockHttpResponse( $this->getRssXml() );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertEquals( 'rss', $result[0]['source_type'] );
        $this->assertEquals( 'https://example.com/feed.xml', $result[0]['source_url'] );
    }

    public function test_fetch_data_extracts_item_fields_correctly() {
        $this->mockHttpResponse( $this->getRssXml() );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $item = $result[0];

        $this->assertArrayHasKey( 'title', $item );
        $this->assertArrayHasKey( 'link', $item );
        $this->assertArrayHasKey( 'description', $item );
        $this->assertArrayHasKey( 'content', $item );
        $this->assertArrayHasKey( 'pubDate', $item );
        $this->assertArrayHasKey( 'guid', $item );
        $this->assertArrayHasKey( 'source_type', $item );
        $this->assertArrayHasKey( 'source_url', $item );

        $this->assertEquals( 'https://example.com/ai-2026', $item['link'] );
        $this->assertEquals( 'Perkembangan terbaru teknologi AI dan machine learning', $item['description'] );
        $this->assertEquals( 'Mon, 01 Jan 2026 00:00:00 GMT', $item['pubDate'] );
        $this->assertEquals( 'https://example.com/ai-2026', $item['guid'] );
    }

    public function test_fetch_data_uses_content_encoded_namespace() {
        $this->mockHttpResponse( $this->getRssXml() );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        // Item 1 has content:encoded
        $this->assertStringContainsString( 'Artikel pendek tentang AI.', $result[0]['content'] );
    }

    public function test_fetch_data_falls_back_to_full_content_for_short_content() {
        // Content:encoded hanya "Artikel pendek tentang AI." (~5 kata < 50)
        // fetch_full_content akan dipanggil, tapi Readability tidak terinstall
        // sehingga fetch_full_content return false, dan content tetap dari content:encoded
        $this->mockHttpResponse( $this->getRssXml() );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertStringContainsString( 'Artikel pendek tentang AI.', $result[0]['content'] );
    }

    public function test_fetch_data_respects_match_keywords_filter() {
        $this->mockHttpResponse( $this->getRssXml() );

        // Hanya item dengan "Teknologi" di title/description yang lolos
        $source = new RSSSource( 'https://example.com/feed.xml', 'Teknologi' );
        $result = $source->fetch_data();

        // Item 1 (Teknologi AI) lolos, Item 2 (Resep Masakan) diblokir
        $this->assertCount( 1, $result );
        $this->assertEquals( 'Perkembangan Teknologi AI 2026', $result[0]['title'] );
    }

    public function test_fetch_data_all_items_filtered_out_returns_empty() {
        $this->mockHttpResponse( $this->getRssXml() );

        // Tidak ada item yang mengandung "python"
        $source = new RSSSource( 'https://example.com/feed.xml', 'python' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_fetch_data_negative_keywords_block_items() {
        $this->mockHttpResponse( $this->getRssXml() );

        // Item dengan "resep" di title harus diblokir
        $source = new RSSSource( 'https://example.com/feed.xml', '', 'resep' );
        $result = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertEquals( 'Perkembangan Teknologi AI 2026', $result[0]['title'] );
    }

    public function test_fetch_data_handles_rss_without_content_namespace() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Simple Feed</title>
    <item>
      <title>Simple Article</title>
      <link>https://example.com/article</link>
      <description>A simple article description here</description>
      <pubDate>Wed, 03 Jan 2026 00:00:00 GMT</pubDate>
      <guid>https://example.com/article</guid>
    </item>
  </channel>
</rss>';
        $this->mockHttpResponse( $xml );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertEquals( 'Simple Article', $result[0]['title'] );
        // content:encoded tidak ada, content akan tetap dari content:encoded = ''
        // lalu fetch_full_content dipanggil (Readability tidak ada) -> content = ''
        $this->assertEquals( '', $result[0]['content'] );
    }

    // ================================================================
    // FETCH FULL CONTENT — via reflection
    // ================================================================

    public function test_fetch_full_content_returns_false_when_readability_missing() {
        // Class FiveFilters\Readability\Readability tidak ada di vendor/ test,
        // jadi method harus return false
        $source = new RSSSource( 'https://example.com/feed.xml' );

        $result = $this->invokeMethod( $source, 'fetch_full_content', array(
            'https://example.com/article',
        ) );

        $this->assertFalse( $result );
    }

    // ================================================================
    // FETCH DATA — ITEM WITH EMPTY TITLE/DESCRIPTION
    // ================================================================

    public function test_fetch_data_handles_item_with_empty_description() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Feed</title>
    <item>
      <title>Title Only Item</title>
      <link>https://example.com/title-only</link>
      <pubDate>Thu, 04 Jan 2026 00:00:00 GMT</pubDate>
      <guid>https://example.com/title-only</guid>
    </item>
  </channel>
</rss>';
        $this->mockHttpResponse( $xml );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $result = $source->fetch_data();

        $this->assertCount( 1, $result );
        $this->assertEquals( 'Title Only Item', $result[0]['title'] );
        $this->assertEquals( '', $result[0]['description'] );
    }

    // ================================================================
    // FETCH DATA — GUARD: cache guard tidak ada — edge case
    // ================================================================

    public function test_fetch_data_returns_same_results_on_multiple_calls() {
        $this->mockHttpResponse( $this->getRssXml() );

        $source = new RSSSource( 'https://example.com/feed.xml' );
        $first  = $source->fetch_data();
        $second = $source->fetch_data();

        // Kedua panggilan harus menghasilkan data yang sama (tidak ada state mutation)
        $this->assertCount( count( $first ), $second );
        $this->assertEquals( $first[0]['title'], $second[0]['title'] );
    }
}
