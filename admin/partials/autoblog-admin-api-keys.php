<?php
/**
 * Tab API Keys — Mengelola kredensial API.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */

$active_provider    = get_option( 'autoblog_ai_provider', 'openai' );
$embedding_provider = get_option( 'autoblog_embedding_provider', 'openai' );
// Normalisasi embedding key check
$embedding_key_name = $embedding_provider;
if ( $embedding_provider === 'gemini_001' ) {
    $embedding_key_name = 'gemini';
}

$search_provider       = get_option( 'autoblog_search_provider', 'serpapi' );
$enable_deep_research  = get_option( 'autoblog_enable_deep_research', '0' ) === '1';
$enable_dynamic_search = get_option( 'autoblog_enable_dynamic_search', '0' ) === '1';
$need_search_key       = $enable_deep_research || $enable_dynamic_search;

$thumbnail_source = get_option( 'autoblog_thumbnail_source', 'pexels' );
$need_pexels      = ( $thumbnail_source === 'pexels' || $thumbnail_source === 'random_stock' );

function get_key_badge( $key_provider, $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels ) {
    $badges = array();
    
    $style_base = 'display:inline-block; padding:2px 8px; border-radius:12px; font-size:9px; font-weight:600; letter-spacing:0.04em; margin-left:6px; text-transform:uppercase; vertical-align:middle;';

    // Check if main provider
    if ( $key_provider === $active_provider ) {
        $badges[] = '<span style="' . $style_base . ' background:#fee2e2; color:#b91c1c;">WAJIB - AKTIF</span>';
    }
    
    // Check if embedding provider
    if ( $key_provider === $embedding_key_name ) {
        $badges[] = '<span style="' . $style_base . ' background:#fef3c7; color:#b45309;">WAJIB UNTUK RAG</span>';
    }
    
    // Check if search provider and needed
    if ( $key_provider === $search_provider && $need_search_key ) {
        $badges[] = '<span style="' . $style_base . ' background:#dbeafe; color:#1d4ed8;">WAJIB UNTUK SEARCH</span>';
    }

    // Check if pexels thumbnail is needed
    if ( $key_provider === 'pexels' && $need_pexels ) {
        $badges[] = '<span style="' . $style_base . ' background:#e0f2fe; color:#0369a1;">WAJIB UNTUK THUMBNAIL</span>';
    }

    if ( empty( $badges ) ) {
        return '<span style="' . $style_base . ' background:#f1f5f9; color:#64748b;">OPSIONAL</span>';
    }
    
    return implode( ' ', $badges );
}
?>

<table class="form-table">
    <!-- SerpApi -->
    <tr valign="top">
        <th scope="row">
            SerpApi Key
            <div><?php echo get_key_badge('serpapi', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></div>
        </th>
        <td>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="password" name="autoblog_serpapi_key" value="<?php echo esc_attr( get_option('autoblog_serpapi_key') ); ?>" />
                <button type="button" class="button test-connection-btn" data-provider="serpapi">Test Connection</button>
                <span class="test-connection-status" style="font-weight:600; font-size:11px;"></span>
            </div>
            <p class="description">Untuk Google AI Overview, AI Mode, dan Bing Copilot integration.</p>
        </td>
    </tr>

    <!-- Pexels -->
    <tr valign="top">
        <th scope="row">
            Pexels API Key
            <div><?php echo get_key_badge('pexels', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></div>
        </th>
        <td>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="password" name="autoblog_pexels_key" value="<?php echo esc_attr( get_option('autoblog_pexels_key') ); ?>" />
                <button type="button" class="button test-connection-btn" data-provider="pexels">Test Connection</button>
                <span class="test-connection-status" style="font-weight:600; font-size:11px;"></span>
            </div>
            <p class="description">Untuk mencari gambar stok gratis berkualitas tinggi sebagai thumbnail post.</p>
        </td>
    </tr>
</table>

<hr>

<h2>🔑 AI Provider API Keys</h2>
<p class="description">Kelola API key untuk provider LLM/AI Anda. Status prioritas diupdate dinamis.</p>

<table class="form-table" id="custom-keys-table">
    <?php
    $custom_keys = get_option( 'autoblog_custom_api_keys', array() );
    $dynamic_providers = \Autoblog\Admin\Admin::get_dynamic_providers();
    
    if ( is_array( $custom_keys ) && ! empty( $custom_keys ) ) {
        foreach ( $custom_keys as $prov_id => $prov_key ) {
            $prov_name = isset( $dynamic_providers[$prov_id]['name'] ) ? $dynamic_providers[$prov_id]['name'] : $prov_id;
            
            $check_id = $prov_id;
            if ( $prov_id === 'google' ) {
                $check_id = 'gemini';
            } elseif ( $prov_id === 'huggingface' ) {
                $check_id = 'hf';
            }
            
            $badge_html = get_key_badge( $check_id, $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels );
            ?>
            <tr valign="top" class="custom-key-row" data-provider="<?php echo esc_attr($prov_id); ?>">
                <th scope="row">
                    <?php echo esc_html($prov_name); ?> API Key
                    <div><?php echo $badge_html; ?></div>
                </th>
                <td>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <input type="password" name="autoblog_custom_api_keys[<?php echo esc_attr($prov_id); ?>]" value="<?php echo esc_attr($prov_key); ?>" />
                        <button type="button" class="button test-connection-btn" data-provider="<?php echo esc_attr($prov_id); ?>">Test Connection</button>
                        <button type="button" class="button remove-custom-key" style="color:#d63638; border-color:#d63638;">Remove</button>
                        <span class="test-connection-status" style="font-weight:600; font-size:11px;"></span>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr id="no-custom-keys-row">
            <td colspan="2" style="padding:10px 0; color:var(--text-muted); font-style:italic; font-size:12px;">
                Belum ada custom provider key yang ditambahkan. Gunakan menu di bawah untuk menambahkannya.
            </td>
        </tr>
        <?php
    }
    ?>
</table>

<div style="margin-top: 15px; display: flex; gap: 8px; align-items: center; padding-top: 12px; border-top: 1px solid #f0f0f1;">
    <select id="new-custom-provider-select" style="max-width: 200px; padding: 4px 6px; font-size:12px;">
        <option value="">-- Pilih Provider Baru --</option>
        <?php
        foreach ( $dynamic_providers as $p_id => $p_data ) {
            if ( isset( $custom_keys[$p_id] ) ) {
                continue;
            }
            ?>
            <option value="<?php echo esc_attr($p_id); ?>"><?php echo esc_html($p_data['name']); ?></option>
            <?php
        }
        ?>
    </select>
    <button type="button" class="button button-secondary" id="btn-add-custom-key" style="padding: 2px 8px; font-size:12px;">+ Tambah Key</button>
</div>
