<?php
/**
 * Tab API Keys — Menampilkan semua field API key provider dengan prioritas dinamis.
 *
 * File ini hanya berisi input kredensial, tidak ada konfigurasi AI/model.
 * Dirender di dalam <form> dari autoblog-admin-display.php.
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
    
    // Check if main provider
    if ( $key_provider === $active_provider ) {
        $badges[] = '<span style="background:#d63638; color:#fff; padding:3px 6px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-left:5px;">WAJIB - AI PROVIDER AKTIF</span>';
    }
    
    // Check if embedding provider
    if ( $key_provider === $embedding_key_name ) {
        $badges[] = '<span style="background:#dba617; color:#fff; padding:3px 6px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-left:5px;">WAJIB UNTUK RAG (KNOWLEDGE BASE)</span>';
    }
    
    // Check if search provider and needed
    if ( $key_provider === $search_provider && $need_search_key ) {
        $badges[] = '<span style="background:#2271b1; color:#fff; padding:3px 6px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-left:5px;">WAJIB UNTUK WEB SEARCH</span>';
    }

    // Check if pexels thumbnail is needed
    if ( $key_provider === 'pexels' && $need_pexels ) {
        $badges[] = '<span style="background:#2271b1; color:#fff; padding:3px 6px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-left:5px;">WAJIB UNTUK THUMBNAIL</span>';
    }

    if ( empty( $badges ) ) {
        return '<span style="background:#64748b; color:#fff; padding:3px 6px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-left:5px;">OPSIONAL / TIDAK TERPAKAI</span>';
    }
    
    return implode( ' ', $badges );
}
?>

<table class="form-table">
    <!-- SerpApi -->
    <tr valign="top">
        <th scope="row">SerpApi Key <?php echo get_key_badge('serpapi', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="password" name="autoblog_serpapi_key"
                       value="<?php echo esc_attr( get_option('autoblog_serpapi_key') ); ?>"
                       class="regular-text" />
                <button type="button" class="button test-connection-btn" data-provider="serpapi">Test Connection</button>
                <span class="test-connection-status" style="font-weight:bold; font-size:12.5px;"></span>
            </div>
            <p class="description">Untuk Google AI Overview, AI Mode, dan Bing Copilot integration.</p>
        </td>
    </tr>

    <!-- Pexels -->
    <tr valign="top">
        <th scope="row">Pexels API Key <?php echo get_key_badge('pexels', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="password" name="autoblog_pexels_key"
                       value="<?php echo esc_attr( get_option('autoblog_pexels_key') ); ?>"
                       class="regular-text" />
                <button type="button" class="button test-connection-btn" data-provider="pexels">Test Connection</button>
                <span class="test-connection-status" style="font-weight:bold; font-size:12.5px;"></span>
            </div>
            <p class="description">Untuk mencari gambar stok gratis berkualitas tinggi sebagai thumbnail post.</p>
        </td>
    </tr>
</table>

<div class="card" style="margin-top: 30px; max-width: 100%; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; background: #fff;">
    <h2 style="margin-top:0; font-size:16px;">🔑 AI Provider API Keys</h2>
    <p class="description" style="margin-bottom:20px;">Tambahkan API key untuk provider LLM/AI Anda dari models.dev. Status prioritas akan muncul dinamis.</p>
    
    <table class="form-table" id="custom-keys-table">
        <?php
        $custom_keys = get_option( 'autoblog_custom_api_keys', array() );
        $dynamic_providers = \Autoblog\Admin\Admin::get_dynamic_providers();
        
        if ( is_array( $custom_keys ) && ! empty( $custom_keys ) ) {
            foreach ( $custom_keys as $prov_id => $prov_key ) {
                $prov_name = isset( $dynamic_providers[$prov_id]['name'] ) ? $dynamic_providers[$prov_id]['name'] : $prov_id;
                
                // Normalisasi ID untuk pencocokan status badge prioritas
                $check_id = $prov_id;
                if ( $prov_id === 'google' ) {
                    $check_id = 'gemini';
                } elseif ( $prov_id === 'huggingface' ) {
                    $check_id = 'hf';
                }
                
                $badge_html = get_key_badge( $check_id, $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels );
                ?>
                <tr valign="top" class="custom-key-row" data-provider="<?php echo esc_attr($prov_id); ?>">
                    <th scope="row" style="width: 280px;">
                        <?php echo esc_html($prov_name); ?> API Key
                        <div style="margin-top: 5px;"><?php echo $badge_html; ?></div>
                    </th>
                    <td>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <input type="password" name="autoblog_custom_api_keys[<?php echo esc_attr($prov_id); ?>]" value="<?php echo esc_attr($prov_key); ?>" class="regular-text" style="width:25em;" />
                            <button type="button" class="button test-connection-btn" data-provider="<?php echo esc_attr($prov_id); ?>">Test Connection</button>
                            <button type="button" class="button remove-custom-key" style="color:#d63638; border-color:#d63638;">Remove</button>
                            <span class="test-connection-status" style="font-weight:bold; font-size:12.5px;"></span>
                        </div>
                    </td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr id="no-custom-keys-row">
                <td colspan="2" style="padding:10px 0; color:#64748b; font-style:italic;">Belum ada custom provider key yang ditambahkan. Gunakan menu di bawah untuk menambahkannya.</td>
            </tr>
            <?php
        }
        ?>
    </table>
    
    <div style="margin-top: 20px; display: flex; gap: 10px; align-items: center; padding-top: 15px; border-top: 1px solid #f0f0f1;">
        <select id="new-custom-provider-select" style="max-width: 250px;">
            <option value="">-- Pilih Provider Baru --</option>
            <?php
            foreach ( $dynamic_providers as $p_id => $p_data ) {
                // Lewati yang sudah ditambahkan key-nya
                if ( isset( $custom_keys[$p_id] ) ) {
                    continue;
                }
                ?>
                <option value="<?php echo esc_attr($p_id); ?>"><?php echo esc_html($p_data['name']); ?></option>
                <?php
            }
            ?>
        </select>
        <button type="button" class="button button-secondary" id="btn-add-custom-key">+ Add Provider Key</button>
    </div>
</div>
