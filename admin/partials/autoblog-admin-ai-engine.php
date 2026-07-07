<?php
/**
 * Tab AI Engine — Konfigurasi AI Provider, Model, Embedding, dan Search.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */

$providers = \Autoblog\Admin\Admin::get_dynamic_providers();
$selected_provider = get_option( 'autoblog_ai_provider', 'openai' );

// Ambil model terpilih saat ini secara global
$selected_model = get_option( 'autoblog_ai_model' );
if ( empty( $selected_model ) ) {
    // Fallback untuk backward compatibility
    $selected_model = get_option( 'autoblog_' . $selected_provider . '_model', 'gpt-4o' );
}
?>

<table class="form-table">

    <!-- Active AI Provider -->
    <tr valign="top">
        <th scope="row">Active AI Provider</th>
        <td>
            <select name="autoblog_ai_provider" id="autoblog_ai_provider">
                <?php foreach ( $providers as $p_id => $p_data ) : ?>
                    <option value="<?php echo esc_attr( $p_id ); ?>" <?php selected( $selected_provider, $p_id ); ?>><?php echo esc_html( $p_data['name'] ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">Provider utama untuk generate konten artikel.</p>
        </td>
    </tr>

    <!-- AI Model -->
    <tr valign="top">
        <th scope="row">AI Model</th>
        <td>
            <select name="autoblog_ai_model" id="autoblog_ai_model">
                <!-- JavaScript akan mengisi opsi model secara dinamis -->
            </select>
            <p class="description">Pilih model spesifik dari provider terpilih untuk generate artikel.</p>
        </td>
    </tr>

    <!-- Embedding Provider (RAG) -->
    <tr valign="top">
        <th scope="row">Embedding Provider (RAG)</th>
        <td>
            <select name="autoblog_embedding_provider" id="autoblog_embedding_provider">
                <option value="openai" <?php selected( get_option('autoblog_embedding_provider'), 'openai' ); ?>>OpenAI (text-embedding-3-small)</option>
                <option value="gemini_001" <?php selected( get_option('autoblog_embedding_provider'), 'gemini_001' ); ?>>Google Gemini (gemini-embedding-001)</option>
                <option value="hf" <?php selected( get_option('autoblog_embedding_provider'), 'hf' ); ?>>Hugging Face (MiniLM-L6-v2)</option>
            </select>
            <p class="description">Model untuk vektorisasi file Knowledge Base (RAG).</p>
            <div id="rag_key_warning" style="display:none; color:#d63638; font-weight:bold; margin-top:5px; font-size:11px;">
                ⚠️ Peringatan: API Key untuk provider RAG terpilih masih kosong. Silakan isi di tab API Keys agar Knowledge Base berfungsi!
            </div>
        </td>
    </tr>

    <!-- Thumbnail Source -->
    <tr valign="top">
        <th scope="row">Post Thumbnail Source</th>
        <td>
            <select name="autoblog_thumbnail_source" id="autoblog_thumbnail_source">
                <option value="openai" <?php selected( get_option('autoblog_thumbnail_source'), 'openai' ); ?>>OpenAI DALL-E 3</option>
                <option value="pexels" <?php selected( get_option('autoblog_thumbnail_source', 'pexels'), 'pexels' ); ?>>Pexels (Stock Photos)</option>
                <option value="openverse" <?php selected( get_option('autoblog_thumbnail_source'), 'openverse' ); ?>>Openverse (Creative Commons)</option>
                <option value="random_stock" <?php selected( get_option('autoblog_thumbnail_source'), 'random_stock' ); ?>>Mix: Pexels -> Openverse (Fallback chain)</option>
            </select>
            <p class="description">Pilih sumber gambar utama untuk featured image artikel Anda.</p>
        </td>
    </tr>

    <!-- Smart Fallback -->
    <tr valign="top">
        <th scope="row">Smart Fallback</th>
        <td>
            <label for="autoblog_enable_fallback">
                <input name="autoblog_enable_fallback" type="checkbox" id="autoblog_enable_fallback" value="1" <?php checked( '1', get_option( 'autoblog_enable_fallback' ) ); ?> />
                Enable Smart Model Switching
            </label>
            <p class="description">Plugin otomatis switch ke provider lain jika provider utama gagal atau rate limit.</p>
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
            
            <div id="gemini_tester_box" style="margin-top: 10px; padding: 12px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 4px; max-width:450px;">
                <h4 style="margin-top:0; font-size:12px; font-weight:700;">🧪 Gemini Grounding Tester</h4>
                <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <div style="display:flex; gap:8px; align-items:center;">
                        <label for="gemini_test_model" style="font-weight:600; font-size:11px; min-width:80px;">Model:</label>
                        <select id="gemini_test_model" style="flex-grow:1; padding:3px 6px; font-size:11px;">
                            <option value="gemini-3.1-pro">Gemini 3.1 Pro</option>
                            <option value="gemini-3.0-flash">Gemini 3 Flash</option>
                            <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <input type="text" id="gemini_test_prompt" placeholder="Tanya info real-time..." style="flex-grow:1; padding:4px; font-size:11px;" />
                        <button type="button" id="btn_test_grounding" class="button button-secondary" style="padding: 2px 8px; font-size:11px;">Test</button>
                    </div>
                </div>
                <div id="gemini_test_result" style="margin-top:8px; display:none; padding:8px; background:#fff; border-left:3px solid var(--primary); font-family:monospace; font-size:11px; white-space:pre-wrap; max-height: 120px; overflow-y: auto; border: 1px solid var(--border-color);"></div>
            </div>
        </td>
    </tr>
</table>

