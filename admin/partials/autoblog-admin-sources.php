<?php
/**
 * @deprecated Gunakan autoblog-admin-data-sources.php sebagai gantinya.
 * File ini dipertahankan untuk backward compatibility.
 * Semua fungsionalitas Content Sources sudah dipindahkan ke tab Data Sources.
 */
// Handle form submission for adding/deleting sources
if ( isset( $_POST['autoblog_add_source'] ) ) {
    check_admin_referer( 'autoblog_add_source_verify' );

    $sources = get_option( 'autoblog_sources', array() );
    if ( ! is_array( $sources ) ) {
        $sources = array();
    }
    $urls = array_map('trim', explode(',', $_POST['source_url']));
    $type = sanitize_text_field( $_POST['source_type'] );
    $selector = sanitize_text_field( $_POST['source_selector'] );
    $match = sanitize_text_field( $_POST['match_keywords'] );
    $negative = sanitize_text_field( $_POST['negative_keywords'] );

    $count = 0;
    foreach ( $urls as $url ) {
        if ( empty( $url ) ) continue;
        
        // Sanitize based on type
        if ( $type === 'web_search' ) {
            $clean_url = sanitize_text_field( $url ); // Keep as text for queries
        } else {
            $clean_url = esc_url_raw( $url ); // Enforce valid URL for RSS/Scraper
        }
        
        $new_source = array(
            'type' => $type,
            'url'  => $clean_url,
            'match_keywords' => $match,
            'negative_keywords' => $negative,
            'selector'  => $selector
        );
        $sources[] = $new_source;
        $count++;
    }
    
    update_option( 'autoblog_sources', $sources );
    echo '<div class="notice notice-success is-dismissible"><p>' . $count . ' Source(s) added successfully.</p></div>';
}

if ( isset( $_GET['autoblog_delete_source'] ) ) {
    $index = intval( $_GET['autoblog_delete_source'] );
    $sources = get_option( 'autoblog_sources', array() );
    if ( ! is_array( $sources ) ) {
        $sources = array();
    }
    
    if ( isset( $sources[ $index ] ) ) {
        unset( $sources[ $index ] );
        update_option( 'autoblog_sources', array_values( $sources ) );
        echo '<div class="notice notice-success is-dismissible"><p>Source deleted successfully.</p></div>';
    }
}

$sources = get_option( 'autoblog_sources', array() );
if ( ! is_array( $sources ) ) {
    $sources = array();
}
?>

<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>Add New Source</h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'autoblog_add_source_verify' ); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Source Type</th>
                <td>
                    <select name="source_type" id="autoblog_source_type">
                        <option value="rss">RSS Feed</option>
                        <option value="web">Web Scraper</option>
                        <option value="web_search">Web Search (SerpApi/Brave)</option>
                    </select>
                </td>
            </tr>
            <tr valign="top" id="row_url">
                <th scope="row" id="label_url">URL</th>
                <td>
                    <input type="text" name="source_url" id="input_url" class="regular-text" required placeholder="https://site1.com/feed, https://site2.com/feed" />
                    <p class="description" id="desc_url">You can enter multiple URLs separated by commas.</p>
                </td>
            <tr valign="top">
                <th scope="row">Match Keywords (Optional)</th>
                <td>
                    <input type="text" name="match_keywords" class="regular-text" placeholder="e.g. AI, WordPress, coding" />
                    <p class="description">Only process articles containing these words (comma separated).</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Negative Keywords (Optional)</th>
                <td>
                    <input type="text" name="negative_keywords" class="regular-text" placeholder="e.g. promo, sponsored, gambling" />
                    <p class="description">Skip articles containing these words (comma separated).</p>
                </td>
            </tr>
            <tr valign="top" id="row_selector" style="display:none;">
                <th scope="row">CSS Selector</th>
                <td>
                    <input type="text" name="source_selector" class="regular-text" placeholder="article.content or #main" />
                    <p class="description">Required for Web Scraper. Target the content container.</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Add Source', 'primary', 'autoblog_add_source' ); ?>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        
        // Function to toggle source selector and labels
        $('#autoblog_source_type').change(function() {
            var type = $(this).val();
            if (type == 'web') {
                $('#row_selector').show();
                $('#label_url').text('URL');
                $('#input_url').attr('placeholder', 'https://site1.com, https://site2.com');
                $('#desc_url').text('Enter URLs to scrape.');
            } else if (type == 'web_search') {
                $('#row_selector').hide();
                $('#label_url').text('Search Query');
                $('#input_url').attr('placeholder', 'latest AI trends, wordpress tips');
                $('#desc_url').text('Enter search queries (comma separated). Uses SerpApi or Brave based on settings.');
            } else {
                $('#row_selector').hide();
                $('#label_url').text('URL');
                $('#input_url').attr('placeholder', 'https://site1.com/feed, https://site2.com/feed');
                $('#desc_url').text('Enter RSS Feed URLs.');
            }
        });

        // Function to toggle model dropdowns based on provider
        function toggleModelDropdowns() {
            var provider = $('#autoblog_ai_provider').val();
            
            // Hide all model containers first
            $('.model-select-container').hide();
            
            // Show the relevant container
            if (provider == 'openai') {
                $('#container_model_openai').show();
            } else if (provider == 'anthropic') {
                $('#container_model_anthropic').show();
            } else if (provider == 'gemini') {
                $('#container_model_gemini').show();
            } else if (provider == 'groq') {
                $('#container_model_groq').show();
            } else if (provider == 'openrouter') {
                $('#container_model_openrouter').show();
            } else if (provider == 'hf') {
                $('#container_model_hf').show();
            }
        }

        // Bind change event
        $('#autoblog_ai_provider').change(function() {
            toggleModelDropdowns();
        });

        // Initial run
        toggleModelDropdowns();

    });
</script>

<br>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Type</th>
            <th>URL / Query</th>
            <th>Filters</th>
            <th>Selector</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ( ! empty( $sources ) ) : ?>
            <?php foreach ( $sources as $index => $source ) : ?>
                <tr>
                    <td><?php echo esc_html( strtoupper( $source['type'] ) ); ?></td>
                    <td><?php echo esc_html( $source['url'] ); ?></td>
                    <td>
                        <?php if(!empty($source['match_keywords'])): ?>
                            <strong>Include:</strong> <?php echo esc_html($source['match_keywords']); ?><br>
                        <?php endif; ?>
                        <?php if(!empty($source['negative_keywords'])): ?>
                            <span style="color:red;"><strong>Exclude:</strong> <?php echo esc_html($source['negative_keywords']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( isset( $source['selector'] ) ? $source['selector'] : '-' ); ?></td>
                    <td>
                        <a href="?page=autoblog&tab=sources&autoblog_delete_source=<?php echo $index; ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="4">No sources configured yet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
