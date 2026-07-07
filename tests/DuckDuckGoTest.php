<?php

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Sources\SearchSource;

/**
 * Test DuckDuckGoDriver melalui SearchSource.
 *
 * Test ini melakukan request HTTP nyata ke DuckDuckGo HTML (tanpa API key).
 * Jalankan dengan: vendor/bin/phpunit tests/DuckDuckGoTest.php --testdox
 *
 * @group integration
 */
class DuckDuckGoTest extends TestCase {

    /**
     * Override get_option agar SearchSource pakai provider duckduckgo_free.
     */
    protected function setUp(): void {
        parent::setUp();

        // Hapus override sebelumnya jika ada
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [
            'autoblog_search_provider' => 'duckduckgo_free',
            'autoblog_serpapi_key'     => '',
        ];
    }

    // ================================================================
    // TEST 1: Fetch data dari DuckDuckGo - query valid
    // ================================================================

    public function test_fetch_data_returns_results_for_valid_query() {
        $source = new SearchSource( 'teknologi AI terbaru 2025' );
        $data   = $source->fetch_data();

        echo "\n[DuckDuckGo] Query: 'teknologi AI terbaru 2025'\n";
        echo "[DuckDuckGo] Jumlah hasil: " . count( $data ) . "\n";

        if ( ! empty( $data ) ) {
            echo "[DuckDuckGo] Hasil pertama:\n";
            echo "  Title   : " . ( $data[0]['title']   ?? '-' ) . "\n";
            echo "  Link    : " . ( $data[0]['link']    ?? '-' ) . "\n";
            echo "  Content : " . substr( strip_tags( $data[0]['content'] ?? '' ), 0, 150 ) . "...\n";
        }

        // Validasi: harus mengembalikan array (bisa kosong tapi tidak false/null)
        $this->assertIsArray( $data, 'fetch_data() harus mengembalikan array' );
        // Validasi: provider duckduckgo_free tidak membutuhkan serpapi_key
        $this->assertTrue( $source->validate_source(), 'validate_source() harus true untuk duckduckgo_free' );
    }

    // ================================================================
    // TEST 2: Validasi struktur setiap item hasil
    // ================================================================

    public function test_each_item_has_required_keys() {
        $source = new SearchSource( 'cara membuat website' );
        $data   = $source->fetch_data();

        if ( empty( $data ) ) {
            $this->markTestSkipped( 'DuckDuckGo tidak mengembalikan hasil — mungkin rate limited.' );
        }

        $required_keys = [ 'title', 'link', 'description', 'content', 'source_type', 'source_url' ];

        foreach ( $data as $i => $item ) {
            foreach ( $required_keys as $key ) {
                $this->assertArrayHasKey(
                    $key,
                    $item,
                    "Item [{$i}] tidak punya key '{$key}'"
                );
            }
            $this->assertEquals( 'duckduckgo_free', $item['source_type'], "source_type harus 'duckduckgo_free'" );
        }

        echo "\n[DuckDuckGo] Semua " . count( $data ) . " item punya struktur yang benar.\n";
    }

    // ================================================================
    // TEST 3: Match keyword filter
    // ================================================================

    public function test_match_keyword_filter_works() {
        // Hanya ambil hasil yang mengandung kata "wordpress"
        $source = new SearchSource( 'wordpress plugin development', 'wordpress' );
        $data   = $source->fetch_data();

        echo "\n[DuckDuckGo] Query dengan match_keyword='wordpress'\n";
        echo "[DuckDuckGo] Hasil yang lolos filter: " . count( $data ) . "\n";

        foreach ( $data as $item ) {
            $combined = strtolower( $item['title'] . ' ' . $item['content'] );
            $this->assertStringContainsString(
                'wordpress',
                $combined,
                "Item [{$item['title']}] lolos filter tapi tidak mengandung 'wordpress'"
            );
        }
    }

    // ================================================================
    // TEST 4: Negative keyword filter
    // ================================================================

    public function test_negative_keyword_filter_blocks_results() {
        // Cari "python programming" tapi blokir hasil yang mengandung "java"
        $source = new SearchSource( 'python programming tutorial', '', 'java' );
        $data   = $source->fetch_data();

        echo "\n[DuckDuckGo] Query dengan negative_keyword='java'\n";
        echo "[DuckDuckGo] Hasil setelah filter: " . count( $data ) . "\n";

        foreach ( $data as $item ) {
            $combined = strtolower( $item['title'] . ' ' . $item['content'] );
            $this->assertStringNotContainsString(
                'java',
                $combined,
                "Item [{$item['title']}] lolos filter padahal mengandung 'java'"
            );
        }
    }

    // ================================================================
    // TEST 5: Query kosong harus gagal validate_source
    // ================================================================

    public function test_empty_query_fails_validation() {
        $source = new SearchSource( '' );
        $this->assertFalse(
            $source->validate_source(),
            'validate_source() harus false untuk query kosong'
        );
        $this->assertEmpty( $source->fetch_data(), 'fetch_data() harus return [] untuk query kosong' );
    }

    // ================================================================
    // TEST 6: get_display_name benar untuk provider ini
    // ================================================================

    public function test_get_display_name_for_duckduckgo() {
        $source = new SearchSource( 'test' );
        $this->assertEquals(
            'Web Search (DuckDuckGo Free)',
            $source->get_display_name()
        );
    }
}
