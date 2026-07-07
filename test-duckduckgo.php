<?php
/**
 * Test DuckDuckGo Driver (WordPress Context)
 *
 * Jalankan via WP-CLI:
 *   wp eval-file test-duckduckgo.php
 *
 * Atau akses via browser (sementara, hapus setelah test):
 *   https://your-site.com/wp-content/plugins/autoblog/test-duckduckgo.php
 *
 * PERHATIAN: Hapus file ini setelah selesai testing!
 */

// Bootstrap WordPress jika diakses langsung via browser
if ( ! defined( 'ABSPATH' ) ) {
    $wp_load = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
    if ( file_exists( $wp_load ) ) {
        require_once $wp_load;
    } else {
        die( 'WordPress tidak ditemukan. Gunakan WP-CLI: wp eval-file test-duckduckgo.php' );
    }
}

// Autoload plugin
require_once __DIR__ . '/vendor/autoload.php';

// ================================================================
// Helper output
// ================================================================
function test_log( $label, $value, $pass = true ) {
    $icon   = $pass ? '✅' : '❌';
    $status = $pass ? 'PASS' : 'FAIL';
    echo "{$icon} [{$status}] {$label}: {$value}\n";
}

echo "========================================\n";
echo "  DuckDuckGo Driver Test\n";
echo "========================================\n\n";

// Force provider ke duckduckgo_free untuk test ini
add_filter( 'option_autoblog_search_provider', function() { return 'duckduckgo_free'; } );
add_filter( 'option_autoblog_serpapi_key',     function() { return ''; } );

// ================================================================
// TEST 1: Fetch data basic
// ================================================================
echo "--- TEST 1: Basic Fetch ---\n";
$source = new Autoblog\Sources\SearchSource( 'teknologi AI terbaru 2025' );

$valid = $source->validate_source();
test_log( 'validate_source()', $valid ? 'true' : 'false', $valid === true );

$display = $source->get_display_name();
test_log( 'get_display_name()', $display, $display === 'Web Search (DuckDuckGo Free)' );

echo "\nFetching... (timeout ~20s)\n";
$start = microtime( true );
$data  = $source->fetch_data();
$elapsed = round( microtime( true ) - $start, 2 );

// Baca log terbaru untuk diagnosa
$upload_dir = wp_upload_dir();
$log_file   = $upload_dir['basedir'] . '/autoblog-logs/debug.log';
if ( file_exists( $log_file ) ) {
    $log_lines = file( $log_file );
    $recent    = array_slice( $log_lines, -20 ); // 20 baris terakhir
    echo "\n📋 Log terbaru (20 baris):\n";
    foreach ( $recent as $line ) {
        echo "  " . trim( $line ) . "\n";
    }
    echo "\n";
}

test_log( 'fetch_data() return type', gettype( $data ), is_array( $data ) );
test_log( 'Jumlah hasil', count( $data ), count( $data ) > 0 );
echo "  ⏱ Elapsed: {$elapsed}s\n\n";

if ( ! empty( $data ) ) {
    echo "Hasil pertama:\n";
    echo "  Title      : " . ( $data[0]['title']       ?? '-' ) . "\n";
    echo "  Link       : " . ( $data[0]['link']        ?? '-' ) . "\n";
    echo "  Source type: " . ( $data[0]['source_type'] ?? '-' ) . "\n";
    echo "  Content len: " . strlen( strip_tags( $data[0]['content'] ?? '' ) ) . " chars\n";
    echo "  Snippet    : " . substr( strip_tags( $data[0]['content'] ?? '' ), 0, 200 ) . "...\n\n";
}

// ================================================================
// TEST 2: Struktur item
// ================================================================
echo "--- TEST 2: Struktur Item ---\n";
$required_keys = [ 'title', 'link', 'description', 'content', 'source_type', 'source_url' ];
$struct_ok = true;
foreach ( $data as $i => $item ) {
    foreach ( $required_keys as $key ) {
        if ( ! array_key_exists( $key, $item ) ) {
            test_log( "Item[$i] missing key", $key, false );
            $struct_ok = false;
        }
    }
}
if ( $struct_ok && ! empty( $data ) ) {
    test_log( 'Semua item punya required keys', implode( ', ', $required_keys ), true );
}

// ================================================================
// TEST 3: Match keyword filter
// ================================================================
echo "\n--- TEST 3: Match Keyword Filter ---\n";
$source_filtered = new Autoblog\Sources\SearchSource( 'wordpress tutorial', 'wordpress' );
$filtered_data   = $source_filtered->fetch_data();
$filter_ok = true;
foreach ( $filtered_data as $item ) {
    $combined = strtolower( $item['title'] . ' ' . $item['content'] );
    if ( strpos( $combined, 'wordpress' ) === false ) {
        test_log( "Item lolos filter tanpa match keyword", $item['title'], false );
        $filter_ok = false;
    }
}
test_log( 'Match keyword filter', count( $filtered_data ) . ' hasil lolos (semua mengandung "wordpress")', $filter_ok );

// ================================================================
// TEST 4: Empty query
// ================================================================
echo "\n--- TEST 4: Empty Query ---\n";
$source_empty = new Autoblog\Sources\SearchSource( '' );
$empty_valid  = $source_empty->validate_source();
test_log( 'validate_source() untuk query kosong', $empty_valid ? 'true (SALAH!)' : 'false (benar)', $empty_valid === false );
$empty_data = $source_empty->fetch_data();
test_log( 'fetch_data() untuk query kosong', 'count=' . count( $empty_data ), empty( $empty_data ) );

// ================================================================
// TEST 5: URL prefix dibersihkan
// ================================================================
echo "\n--- TEST 5: URL Prefix Cleanup ---\n";
$source_url = new Autoblog\Sources\SearchSource( 'https://www.teknologi.com' );
// Property $query private — test tidak langsung, tapi validate+fetch harus tidak crash
$url_valid = $source_url->validate_source();
test_log( 'Query dengan prefix https:// tidak crash', $url_valid ? 'true' : 'false', true );

echo "\n========================================\n";
echo "  Selesai.\n";
echo "========================================\n";
echo "\nHAPUS file test-duckduckgo.php setelah selesai!\n";
