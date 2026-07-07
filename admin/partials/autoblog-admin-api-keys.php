<?php
/**
 * Tab AI & API Settings (Unified & Streamlined Layout)
 *
 * Menghilangkan ambiguitas UX dengan menaruh input API Key & Base URL
 * secara linier langsung di bawah dropdown pemilihan Active AI Provider.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */

$providers         = \Autoblog\Utils\ModelCatalog::get_dynamic_providers();
$selected_provider = get_option( 'autoblog_ai_provider', 'openai' );

// Ambil model terpilih saat ini secara global
$selected_model = get_option( 'autoblog_ai_model' );
if ( empty( $selected_model ) ) {
    $selected_model = get_option( 'autoblog_' . $selected_provider . '_model', 'gpt-4o' );
}

$embedding_provider = get_option( 'autoblog_embedding_provider', 'openai' );
$embedding_key_name = ( $embedding_provider === 'gemini_001' ) ? 'gemini' : $embedding_provider;

$search_provider       = get_option( 'autoblog_search_provider', 'serpapi' );
$enable_deep_research  = get_option( 'autoblog_enable_deep_research', '0' ) === '1';
$enable_dynamic_search = get_option( 'autoblog_enable_dynamic_search', '0' ) === '1';
$need_search_key       = $enable_deep_research || $enable_dynamic_search;

$thumbnail_source = get_option( 'autoblog_thumbnail_source', 'pexels' );
$need_pexels      = ( $thumbnail_source === 'pexels' || $thumbnail_source === 'random_stock' );

$custom_keys      = get_option( 'autoblog_custom_api_keys', [] );
$custom_endpoints = get_option( 'autoblog_custom_api_endpoints', [] );

// Helper badge
if ( ! function_exists( 'get_key_badge' ) ) {
    function get_key_badge( $key_provider, $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels ) {
        $badges     = [];
        $style_base = 'display:inline-block; padding:2px 8px; border-radius:12px; font-size:9px; font-weight:600; letter-spacing:0.04em; margin-left:6px; text-transform:uppercase; vertical-align:middle;';

        if ( $key_provider === $active_provider ) {
            $badges[] = '<span style="' . $style_base . ' background:#fee2e2; color:#b91c1c;">AKTIF</span>';
        }
        if ( $key_provider === $embedding_key_name ) {
            $badges[] = '<span style="' . $style_base . ' background:#fef3c7; color:#b45309;">WAJIB UNTUK RAG</span>';
        }
        if ( $key_provider === $search_provider && $need_search_key ) {
            $badges[] = '<span style="' . $style_base . ' background:#dbeafe; color:#1d4ed8;">WAJIB UNTUK SEARCH</span>';
        }
        if ( $key_provider === 'pexels' && $need_pexels ) {
            $badges[] = '<span style="' . $style_base . ' background:#e0f2fe; color:#0369a1;">WAJIB UNTUK THUMBNAIL</span>';
        }

        if ( empty( $badges ) ) {
            return '<span style="' . $style_base . ' background:#f1f5f9; color:#64748b;">CADANGAN</span>';
        }
        return implode( ' ', $badges );
    }
}

// Normalisasi key name untuk provider aktif
$active_key_id = $selected_provider;
if ( $selected_provider === 'gemini' ) {
    $active_key_id = 'google';
} elseif ( $selected_provider === 'hf' ) {
    $active_key_id = 'huggingface';
}

// Ambil nilai API Key & Endpoint provider aktif dari data custom keys
$active_key_val = isset( $custom_keys[$active_key_id] ) ? $custom_keys[$active_key_id] : '';
$active_api_val = isset( $custom_endpoints[$active_key_id] ) ? $custom_endpoints[$active_key_id] : ( isset( $providers[$active_key_id]['api'] ) ? $providers[$active_key_id]['api'] : '' );
?>

<!-- ================================================================ -->
<!-- SECTION 1: Active AI Engine & Credentials -->
<!-- ================================================================ -->
<div class="postbox">
    <div class="postbox-header">
        <h2 class="hndle">🤖 Active AI Engine & Credentials</h2>
    </div>
    <div class="inside">
        <p class="description">Pilih provider utama yang aktif, isi API key beserta endpoint-nya, dan tentukan model LLM penulisan.</p>
        
        <table class="form-table">
            <!-- Active AI Provider -->
            <tr valign="top">
                <th scope="row">Active AI Provider</th>
                <td>
                    <select name="autoblog_ai_provider" id="autoblog_ai_provider" style="min-width: 250px;">
                        <?php foreach ( $providers as $p_id => $p_data ) : ?>
                            <option value="<?php echo esc_attr( $p_id ); ?>" <?php selected( $selected_provider, $p_id ); ?>><?php echo esc_html( $p_data['name'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Layanan AI utama yang memproses penulisan konten blog Anda.</p>
                </td>
            </tr>

            <!-- API Key for Active Provider -->
            <tr valign="top" id="row_active_api_key">
                <th scope="row">
                    <span id="active_key_label_name">API Key(s)</span>
                    <div style="margin-top:4px;"><span style="display:inline-block; padding:2px 8px; border-radius:12px; font-size:9px; font-weight:600; background:#fee2e2; color:#b91c1c; text-transform:uppercase;">WAJIB - AKTIF</span></div>
                </th>
                <td>
                    <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                        <!-- Kita simpan ke dalam array autoblog_custom_api_keys agar backend membaca dari satu tempat -->
                        <textarea id="active_provider_api_key" name="autoblog_custom_api_keys[<?php echo esc_attr($active_key_id); ?>]" style="width: 25em; height: 55px; -webkit-text-security: disc; font-family: monospace; resize: vertical;" placeholder="Masukkan API Key utama Anda (bisa multi-key, satu per baris)..."><?php echo esc_textarea($active_key_val); ?></textarea>
                        <button type="button" class="button test-connection-btn" id="active_test_connection_btn" data-provider="<?php echo esc_attr($active_key_id); ?>" style="margin-top:2px;">Test Connection</button>
                        <span class="test-connection-status" id="active_connection_status" style="font-weight:600; font-size:11px; margin-top:6px;"></span>
                    </div>
                </td>
            </tr>

            <!-- Base URL for Active Provider -->
            <tr valign="top" id="row_active_api_endpoint">
                <th scope="row">Base URL (Endpoint)</th>
                <td>
                    <input type="text" id="active_provider_api_endpoint" name="autoblog_custom_api_endpoints[<?php echo esc_attr($active_key_id); ?>]" value="<?php echo esc_attr($active_api_val); ?>" placeholder="e.g. https://api.openai.com/v1" style="width: 25em;" />
                    <p class="description" style="margin-top:5px;">Alamat endpoint API. Kosongkan jika ingin menggunakan default bawaan models.dev.</p>
                </td>
            </tr>

            <!-- AI Model -->
            <tr valign="top">
                <th scope="row">AI Model</th>
                <td>
                    <select name="autoblog_ai_model" id="autoblog_ai_model" style="min-width: 250px;">
                        <!-- JS dinamis populate -->
                    </select>
                    <p class="description">Model bahasa spesifik yang digunakan untuk generate artikel.</p>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- ================================================================ -->
<!-- SECTION 2: Helper Services API Keys -->
<!-- ================================================================ -->
<div class="postbox">
    <div class="postbox-header">
        <h2 class="hndle">🔑 Helper Services Credentials</h2>
    </div>
    <div class="inside">
        <p class="description">Kredensial API untuk fitur tambahan seperti pencarian internet dan stock photo.</p>
        
        <table class="form-table">
            <!-- SerpApi -->
            <tr valign="top">
                <th scope="row">
                    SerpApi Key
                    <div><?php echo get_key_badge('serpapi', $selected_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></div>
                </th>
                <td>
                    <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                        <textarea name="autoblog_serpapi_key" style="width: 25em; height: 55px; -webkit-text-security: disc; font-family: monospace; resize: vertical;" placeholder="Masukkan SerpApi key (bisa multi-key, satu per baris)..."><?php echo esc_textarea( get_option('autoblog_serpapi_key') ); ?></textarea>
                        <button type="button" class="button test-connection-btn" data-provider="serpapi" style="margin-top:2px;">Test Connection</button>
                        <span class="test-connection-status" style="font-weight:600; font-size:11px; margin-top:6px;"></span>
                    </div>
                    <p class="description">Untuk integrasi Google AI Overview, AI Mode, dan Bing Copilot. Bisa multi-key (satu per baris).</p>
                </td>
            </tr>

            <!-- Pexels -->
            <tr valign="top">
                <th scope="row">
                    Pexels API Key
                    <div><?php echo get_key_badge('pexels', $selected_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></div>
                </th>
                <td>
                    <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                        <textarea name="autoblog_pexels_key" style="width: 25em; height: 55px; -webkit-text-security: disc; font-family: monospace; resize: vertical;" placeholder="Masukkan Pexels API key (bisa multi-key, satu per baris)..."><?php echo esc_textarea( get_option('autoblog_pexels_key') ); ?></textarea>
                        <button type="button" class="button test-connection-btn" data-provider="pexels" style="margin-top:2px;">Test Connection</button>
                        <span class="test-connection-status" style="font-weight:600; font-size:11px; margin-top:6px;"></span>
                    </div>
                    <p class="description">Untuk pencarian stock photo gratis berkualitas tinggi sebagai featured image.</p>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- ================================================================ -->
<!-- SECTION 3: Smart Fallback & Backup Keys -->
<!-- ================================================================ -->
<div class="postbox">
    <div class="postbox-header">
        <h2 class="hndle">🔄 Smart Fallback & Backup Keys</h2>
    </div>
    <div class="inside">
        <p class="description">Konfigurasi RAG (Knowledge Base), sistem fallback otomatis, dan manajemen API key cadangan.</p>
        
        <table class="form-table">
            <!-- Embedding Provider (RAG) -->
            <tr valign="top">
                <th scope="row">Embedding Provider (RAG)</th>
                <td>
                    <select name="autoblog_embedding_provider" id="autoblog_embedding_provider" style="min-width: 250px;">
                        <option value="openai" <?php selected( $embedding_provider, 'openai' ); ?>>OpenAI (text-embedding-3-small)</option>
                        <option value="gemini_001" <?php selected( $embedding_provider, 'gemini_001' ); ?>>Google Gemini (gemini-embedding-001)</option>
                        <option value="hf" <?php selected( $embedding_provider, 'hf' ); ?>>Hugging Face (MiniLM-L6-v2)</option>
                    </select>
                    <p class="description">Model untuk memvektorkan berkas rujukan Knowledge Base.</p>
                    <div id="rag_key_warning" style="display:none; color:#d63638; font-weight:bold; margin-top:5px; font-size:11px;">
                        ⚠️ Peringatan: API Key untuk provider RAG terpilih belum diisi di tab ini agar RAG berfungsi!
                    </div>
                </td>
            </tr>

            <!-- Thumbnail Source -->
            <tr valign="top">
                <th scope="row">Post Thumbnail Source</th>
                <td>
                    <select name="autoblog_thumbnail_source" id="autoblog_thumbnail_source" style="min-width: 250px;">
                        <option value="openai" <?php selected( $thumbnail_source, 'openai' ); ?>>OpenAI DALL-E 3</option>
                        <option value="pexels" <?php selected( $thumbnail_source, 'pexels' ); ?>>Pexels (Stock Photos)</option>
                        <option value="openverse" <?php selected( $thumbnail_source, 'openverse' ); ?>>Openverse (Creative Commons)</option>
                        <option value="random_stock" <?php selected( $thumbnail_source, 'random_stock' ); ?>>Mix: Pexels -> Openverse (Fallback chain)</option>
                    </select>
                    <p class="description">Sumber gambar utama untuk featured image pos baru Anda.</p>
                </td>
            </tr>

            <!-- Smart Fallback Toggle -->
            <tr valign="top">
                <th scope="row">Smart Fallback</th>
                <td>
                    <label for="autoblog_enable_fallback">
                        <input name="autoblog_enable_fallback" type="checkbox" id="autoblog_enable_fallback" value="1" <?php checked( '1', get_option( 'autoblog_enable_fallback' ) ); ?> />
                        Enable Smart Model Switching (Cross-Provider Fallback)
                    </label>
                    <p class="description">Otomatis dialihkan ke provider cadangan di bawah jika provider aktif kehabisan kuota.</p>
                </td>
            </tr>

            <!-- Gemini Grounding -->
            <tr valign="top" id="row_gemini_grounding" style="display:none;">
                <th scope="row">Gemini Search Grounding</th>
                <td>
                    <label for="autoblog_gemini_grounding">
                        <input name="autoblog_gemini_grounding" type="checkbox" id="autoblog_gemini_grounding" value="1" <?php checked( '1', get_option( 'autoblog_gemini_grounding' ) ); ?> />
                        Enable Native Google Search Grounding
                    </label>
                    <p class="description">Mungkinkan Gemini mengakses Google Search secara real-time untuk akurasi fakta.</p>
                    
                    <div id="gemini_tester_box" style="margin-top: 10px; padding: 12px; background: #f8fafc; border: 1px solid #dcdcde; border-radius: 4px; max-width:450px;">
                        <h4 style="margin-top:0; font-size:12px; font-weight:700;">🧪 Gemini Grounding Tester</h4>
                        <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                            <div style="display:flex; gap:8px; align-items:center;">
                                <label for="gemini_test_model" style="font-weight:600; font-size:11px; min-width:80px;">Model:</label>
                                <select id="gemini_test_model" style="flex-grow:1; padding:3px 6px; font-size:11px;">
                                    <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                                    <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                                </select>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <input type="text" id="gemini_test_prompt" placeholder="Tanya info real-time..." style="flex-grow:1; padding:4px; font-size:11px;" />
                                <button type="button" id="btn_test_grounding" class="button button-secondary" style="padding: 2px 8px; font-size:11px;">Test</button>
                            </div>
                        </div>
                        <div id="gemini_test_result" style="margin-top:8px; display:none; padding:8px; background:#fff; border-left:3px solid #2271b1; font-family:monospace; font-size:11px; white-space:pre-wrap; max-height: 120px; overflow-y: auto; border: 1px solid #dcdcde;"></div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
