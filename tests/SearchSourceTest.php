<?php

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Sources\SearchSource;

/**
 * Unit Test untuk SearchSource coordinator.
 *
 * Memverifikasi validasi input query, penanganan prefix skema URL, filter keyword,
 * dan penentuan provider name tanpa bergantung pada koneksi internet.
 *
 * @package Autoblog\Tests
 */
class SearchSourceTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
    }

    // ================================================================
    // TEST 1: Sanitasi query input (Skema URL dibersihkan)
    // ================================================================

    public function test_query_url_prefix_cleaning() {
        $source = new SearchSource( 'https://html.duckduckgo.com' );
        
        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'query' );
        $prop->setAccessible( true );
        $query_val  = $prop->getValue( $source );

        // Protokol skema https:// harus dibuang
        $this->assertEquals( 'html.duckduckgo.com', $query_val );
    }

    // ================================================================
    // TEST 2: Validasi Source
    // ================================================================

    public function test_empty_query_fails_validation() {
        $source = new SearchSource( '' );
        $this->assertFalse( $source->validate_source() );
    }

    public function test_valid_query_passes_validation() {
        $source = new SearchSource( 'Kecerdasan Buatan' );
        $this->assertTrue( $source->validate_source() );
    }

    // ================================================================
    // TEST 3: Pencocokan keyword filter (passes_filters)
    // ================================================================

    public function test_passes_filters_match_keywords() {
        // Harus mengandung kata "teknologi"
        $source = new SearchSource( 'test query', 'teknologi' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Perkembangan teknologi AI 2026' ] );
        $this->assertTrue( $pass, 'Teks mengandung kata "teknologi" harusnya lolos' );

        $fail = $this->invokeMethod( $source, 'passes_filters', [ 'Kecerdasan buatan masa kini' ] );
        $this->assertFalse( $fail, 'Teks tanpa kata "teknologi" harusnya diblokir' );
    }

    public function test_passes_filters_negative_keywords() {
        // Blokir hasil yang mengandung kata "judi" atau "slot"
        $source = new SearchSource( 'test query', '', 'judi, slot' );

        $pass = $this->invokeMethod( $source, 'passes_filters', [ 'Teknologi modern game online' ] );
        $this->assertTrue( $pass, 'Teks tanpa kata terlarang harus lolos' );

        $fail = $this->invokeMethod( $source, 'passes_filters', [ 'Situs judi online terpercaya' ] );
        $this->assertFalse( $fail, 'Teks dengan kata "judi" harus diblokir' );
    }

    // ================================================================
    // TEST 4: Get Display Name sesuai provider terpilih
    // ================================================================

    public function test_get_display_name_default() {
        // Hapus filter dan paksa option ke duckduckgo_free
        remove_all_filters( 'option_autoblog_search_provider' );
        update_option( 'autoblog_search_provider', 'duckduckgo_free' );
        
        $source = new SearchSource( 'test' );
        $this->assertEquals( 'Web Search (DuckDuckGo Free)', $source->get_display_name() );
    }

    public function test_get_display_name_serpapi() {
        remove_all_filters( 'option_autoblog_search_provider' );
        update_option( 'autoblog_search_provider', 'serpapi' );
        
        $source = new SearchSource( 'test' );
        $this->assertEquals( 'Web Search (SerpApi)', $source->get_display_name() );
    }

    public function test_get_display_name_brave() {
        remove_all_filters( 'option_autoblog_search_provider' );
        update_option( 'autoblog_search_provider', 'brave' );

        $source = new SearchSource( 'test' );
        $this->assertEquals( 'Web Search (Brave)', $source->get_display_name() );
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
