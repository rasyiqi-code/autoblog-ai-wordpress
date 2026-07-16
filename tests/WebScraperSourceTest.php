<?php
/**
 * Unit Test untuk WebScraperSource.
 *
 * Memverifikasi bahwa:
 * 1. Properti $selector dideklarasikan dengan benar (regresi Bug #2).
 * 2. Constructor menyimpan semua nilai dengan benar.
 * 3. validate_source() berfungsi untuk URL valid/tidak valid.
 * 4. fetch_data() mengembalikan array kosong jika source tidak valid.
 * 5. Tidak ada dynamic property warning.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Sources\WebScraperSource;

/**
 * Unit Test untuk WebScraperSource.
 *
 * @group unit
 * @group regression
 */
class WebScraperSourceTest extends TestCase {

    // ================================================================
    // TEST 1: Constructor menyimpan URL dengan benar
    // ================================================================

    public function test_constructor_stores_url() {
        $source = new WebScraperSource( 'https://example.com/article' );

        $reflection = new \ReflectionClass( $source );
        $url_prop   = $reflection->getProperty( 'url' );
        $url_prop->setAccessible( true );

        $this->assertEquals( 'https://example.com/article', $url_prop->getValue( $source ) );
    }

    // ================================================================
    // TEST 2: Constructor menyimpan selector dengan benar
    //         (Regresi test untuk Bug #2 — properti harus ada)
    // ================================================================

    public function test_constructor_stores_selector() {
        $source = new WebScraperSource( 'https://example.com', 'article.content' );

        $reflection = new \ReflectionClass( $source );
        $sel_prop   = $reflection->getProperty( 'selector' );
        $sel_prop->setAccessible( true );

        $this->assertEquals( 'article.content', $sel_prop->getValue( $source ) );
    }

    // ================================================================
    // TEST 3: Constructor menyimpan match_keywords
    // ================================================================

    public function test_constructor_stores_match_keywords() {
        $source = new WebScraperSource( 'https://example.com', '', 'AI, teknologi' );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'match_keywords' );
        $prop->setAccessible( true );

        $this->assertEquals( 'AI, teknologi', $prop->getValue( $source ) );
    }

    // ================================================================
    // TEST 4: Constructor menyimpan negative_keywords
    // ================================================================

    public function test_constructor_stores_negative_keywords() {
        $source = new WebScraperSource( 'https://example.com', '', '', 'spam, promo' );

        $reflection = new \ReflectionClass( $source );
        $prop       = $reflection->getProperty( 'negative_keywords' );
        $prop->setAccessible( true );

        $this->assertEquals( 'spam, promo', $prop->getValue( $source ) );
    }

    // ================================================================
    // TEST 5: Properti $selector tidak menyebabkan dynamic property warning
    //         (Regresi test untuk Bug #2 — PHP 8.2+)
    // ================================================================

    public function test_no_dynamic_property_warning_for_selector() {
        // Inisialisasi yang memicu dynamic property jika tidak dideklarasikan
        $source = new WebScraperSource( 'https://test.com', '#main-content' );

        // Verifikasi properti bisa diakses dari method class
        $validate = $source->validate_source();
        $this->assertTrue( $validate );
    }

    // ================================================================
    // TEST 6: validate_source() true untuk URL valid
    // ================================================================

    public function test_validate_source_accepts_valid_url() {
        $source = new WebScraperSource( 'https://example.com/feed.xml' );
        $this->assertTrue( $source->validate_source() );
    }

    // ================================================================
    // TEST 7: validate_source() false untuk URL tidak valid
    // ================================================================

    public function test_validate_source_rejects_invalid_url() {
        $source = new WebScraperSource( 'not-a-valid-url' );
        $this->assertFalse( $source->validate_source() );
    }

    // ================================================================
    // TEST 8: fetch_data() mengembalikan array kosong jika URL tidak valid
    // ================================================================

    public function test_fetch_data_returns_empty_array_for_invalid_url() {
        $source = new WebScraperSource( '' );
        $result = $source->fetch_data();

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // ================================================================
    // TEST 9: get_display_name() mengembalikan string
    // ================================================================

    public function test_get_display_name() {
        $source = new WebScraperSource( 'https://example.com' );
        $this->assertEquals( 'Web Scraper', $source->get_display_name() );
    }

    // ================================================================
    // TEST 10: Interface SourceInterface diimplementasikan
    // ================================================================

    public function test_implements_source_interface() {
        $reflection = new \ReflectionClass( WebScraperSource::class );
        $this->assertTrue( $reflection->implementsInterface( 'Autoblog\\Interfaces\\SourceInterface' ) );
    }
}
