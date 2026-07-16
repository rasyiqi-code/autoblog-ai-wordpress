<?php
/**
 * Unit Test untuk DataSizer (filter & sort).
 *
 * Memverifikasi bahwa:
 * 1. filter() mengecualikan item tanpa content.
 * 2. filter() mengecualikan item dengan konten < 50 karakter.
 * 3. filter() mempertahankan item valid.
 * 4. sort() mengurutkan dari yang terbaru.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\DataSizer;

class DataSizerTest extends TestCase {

    /** @var DataSizer */
    private $sizer;

    protected function setUp(): void {
        parent::setUp();
        $this->sizer = new DataSizer();
    }

    public function test_filter_excludes_items_without_content() {
        $items = [
            [ 'title' => 'Item tanpa konten' ],
            [ 'content' => 'Konten valid yang cukup panjang untuk masuk ke filtered list.' ],
        ];

        $result = $this->sizer->filter( $items );

        $this->assertCount( 1, $result );
        $this->assertEquals( 'Konten valid yang cukup panjang untuk masuk ke filtered list.', $result[0]['content'] );
    }

    public function test_filter_excludes_items_with_short_content() {
        $items = [
            [ 'content' => 'Pendek' ], // 7 chars < 50
            [ 'content' => 'Konten yang cukup panjang untuk masuk ke filtered list karena lebih dari 50 karakter.' ],
        ];

        $result = $this->sizer->filter( $items );

        $this->assertCount( 1, $result );
        $this->assertStringContainsString( 'panjang', $result[0]['content'] );
    }

    public function test_filter_preserves_empty_array() {
        $result = $this->sizer->filter( [] );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_filter_preserves_all_valid_items() {
        $items = [
            [ 'content' => 'A. ' . str_repeat( 'x', 60 ) ],
            [ 'content' => 'B. ' . str_repeat( 'y', 60 ) ],
            [ 'content' => 'C. ' . str_repeat( 'z', 60 ) ],
        ];

        $result = $this->sizer->filter( $items );

        $this->assertCount( 3, $result );
    }

    public function test_sort_orders_by_pubDate_newest_first() {
        $items = [
            [ 'title' => 'Lama',   'pubDate' => '2024-01-01' ],
            [ 'title' => 'Baru',   'pubDate' => '2024-06-15' ],
            [ 'title' => 'Tengah', 'pubDate' => '2024-03-10' ],
        ];

        $result = $this->sizer->sort( $items );

        $this->assertCount( 3, $result );
        $this->assertEquals( 'Baru',   $result[0]['title'] );
        $this->assertEquals( 'Tengah', $result[1]['title'] );
        $this->assertEquals( 'Lama',   $result[2]['title'] );
    }

    public function test_sort_handles_items_without_pubDate() {
        $items = [
            [ 'title' => 'Tanpa tanggal' ],
            [ 'title' => 'Dengan tanggal', 'pubDate' => '2024-06-15' ],
        ];

        $result = $this->sizer->sort( $items );

        // Item tanpa tanggal (strtotime false = 0) harus di akhir
        $this->assertCount( 2, $result );
        $this->assertEquals( 'Dengan tanggal', $result[0]['title'] );
        $this->assertEquals( 'Tanpa tanggal', $result[1]['title'] );
    }

    public function test_sort_preserves_empty_array() {
        $result = $this->sizer->sort( [] );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }
}
