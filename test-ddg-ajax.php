<?php
/**
 * DuckDuckGo Test Runner (WordPress Admin AJAX)
 *
 * Tambahkan action ini ke hooks, panggil via browser:
 * https://your-site.com/wp-admin/admin-ajax.php?action=autoblog_test_ddg&query=teknologi+AI
 *
 * HAPUS FILE INI SETELAH TEST SELESAI.
 */

add_action( 'wp_ajax_autoblog_test_ddg', 'autoblog_run_ddg_test' );
add_action( 'wp_ajax_nopriv_autoblog_test_ddg', 'autoblog_run_ddg_test' ); // Allow non-logged in for easy test

function autoblog_run_ddg_test() {
    // Force provider duckduckgo_free untuk test ini
    add_filter( 'option_autoblog_search_provider', function() { return 'duckduckgo_free'; } );
    add_filter( 'option_autoblog_serpapi_key',     function() { return ''; } );

    $query         = isset( $_GET['query'] ) ? sanitize_text_field( $_GET['query'] ) : 'teknologi AI terbaru';
    $match_kw      = isset( $_GET['match'] ) ? sanitize_text_field( $_GET['match'] ) : '';
    $negative_kw   = isset( $_GET['neg'] )   ? sanitize_text_field( $_GET['neg'] )   : '';

    $start  = microtime( true );
    $source = new \Autoblog\Sources\SearchSource( $query, $match_kw, $negative_kw );

    $valid   = $source->validate_source();
    $display = $source->get_display_name();
    $data    = $source->fetch_data();
    $elapsed = round( microtime( true ) - $start, 2 );

    $results = [];
    foreach ( $data as $item ) {
        $results[] = [
            'title'       => $item['title']       ?? '',
            'link'        => $item['link']        ?? '',
            'source_type' => $item['source_type'] ?? '',
            'content_len' => strlen( strip_tags( $item['content'] ?? '' ) ),
            'snippet'     => substr( strip_tags( $item['content'] ?? '' ), 0, 300 ),
        ];
    }

    wp_send_json( [
        'query'        => $query,
        'match_kw'     => $match_kw,
        'negative_kw'  => $negative_kw,
        'valid_source' => $valid,
        'display_name' => $display,
        'elapsed_sec'  => $elapsed,
        'count'        => count( $data ),
        'results'      => $results,
    ] );
}
