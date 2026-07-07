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

    <!-- AI Model (dynamic dropdown) -->
    <tr valign="top">
        <th scope="row">AI Model</th>
        <td>
            <select name="autoblog_ai_model" id="autoblog_ai_model" style="min-width: 250px;">
                <!-- JavaScript akan mengisi opsi model secara dinamis -->
            </select>
            <p class="description">Pilih model spesifik dari provider terpilih untuk generate artikel.</p>
        </td>
    </tr>

    <!-- Separator visual -->
    <tr><td colspan="2"><hr></td></tr>

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
            
            <div id="rag_key_warning" style="display:none; color:#d63638; font-weight:bold; margin-top:5px; font-size:12.5px;">
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

    <!-- Separator visual -->
    <tr><td colspan="2"><hr></td></tr>

    <!-- Smart Fallback -->
    <tr valign="top">
        <th scope="row">Smart Fallback</th>
        <td>
            <fieldset>
                <label for="autoblog_enable_fallback">
                    <input name="autoblog_enable_fallback" type="checkbox"
                           id="autoblog_enable_fallback" value="1"
                           <?php checked( '1', get_option( 'autoblog_enable_fallback' ) ); ?> />
                    Enable Smart Model Switching
                </label>
            </fieldset>
            <p class="description">Jika aktif, plugin otomatis switch ke provider lain jika provider utama gagal atau melebihi kuota.</p>
        </td>
    </tr>

    <!-- Gemini Grounding -->
    <tr valign="top" id="row_gemini_grounding" style="display:none;">
        <th scope="row">Gemini Search Grounding</th>
        <td>
            <fieldset>
                <label for="autoblog_gemini_grounding">
                    <input name="autoblog_gemini_grounding" type="checkbox"
                           id="autoblog_gemini_grounding" value="1"
                           <?php checked( '1', get_option( 'autoblog_gemini_grounding' ) ); ?> />
                    Enable Native Google Search Grounding
                </label>
            </fieldset>
            <p class="description">Mungkinkan Gemini mengakses Google Search secara real-time untuk akurasi fakta yang lebih tinggi.</p>
            
            <div id="gemini_tester_box" style="margin-top: 15px; padding: 15px; background: #f0f0f1; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h4 style="margin-top:0;">🧪 Gemini Grounding Tester</h4>
                <p class="description" style="margin-bottom:10px;">Coba tanyakan sesuatu yang membutuhkan data real-time.</p>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <label for="gemini_test_model" style="font-weight:bold; min-width:100px;">Pilih Model:</label>
                        <select id="gemini_test_model" style="flex-grow:1;">
                            <option value="gemini-3.1-pro">Gemini 3.1 Pro (Akurasi)</option>
                            <option value="gemini-3.0-flash">Gemini 3 Flash</option>
                            <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="gemini_test_prompt" placeholder="Ketik pertanyaan riset..." style="flex-grow:1;" />
                        <button type="button" id="btn_test_grounding" class="button button-secondary">Run Test</button>
                    </div>
                </div>
                <div id="gemini_test_result" style="margin-top:10px; display:none; padding:10px; background:#fff; border-left:4px solid #72aee6; font-family:monospace; font-size:12px; white-space:pre-wrap;"></div>
            </div>
        </td>
    </tr>
</table>

<?php
$custom_keys = get_option( 'autoblog_custom_api_keys', array() );
$keys_filled = array(
    'openai'     => ! empty( $custom_keys['openai'] ) ? true : ! empty( get_option( 'autoblog_openai_key' ) ),
    'gemini_001' => ! empty( $custom_keys['google'] ) ? true : ! empty( get_option( 'autoblog_gemini_key' ) ),
    'hf'         => ! empty( $custom_keys['huggingface'] ) ? true : ( ! empty( $custom_keys['hf'] ) ? true : ! empty( get_option( 'autoblog_hf_key' ) ) ),
);
$merged_models = \Autoblog\Admin\Admin::get_merged_models();
?>
<script>
    var autoblogCatalogModels = <?php echo json_encode( $merged_models ); ?>;
    var autoblogSelectedModel = <?php echo json_encode( $selected_model ); ?>;

    jQuery(document).ready(function($) {
        var filledKeys = <?php echo json_encode( $keys_filled ); ?>;

        /**
         * Perbarui daftar opsi dropdown model secara dinamis
         */
        function updateAIModelDropdown() {
            var provider = $('#autoblog_ai_provider').val();
            var $modelSelect = $('#autoblog_ai_model');
            $modelSelect.empty();
            
            // Map keys
            var devKey = provider;
            if ( provider === 'gemini' ) {
                devKey = 'google';
            } else if ( provider === 'huggingface' || provider === 'hf' ) {
                devKey = 'huggingface';
            }

            var models = autoblogCatalogModels[devKey] || {};
            var foundSelected = false;
            
            $.each(models, function(m_id, m_name) {
                var isSelected = (m_id === autoblogSelectedModel);
                if (isSelected) {
                    foundSelected = true;
                }
                $modelSelect.append(
                    $('<option></option>').val(m_id).text(m_name).prop('selected', isSelected)
                );
            });
            
            // Jika model kustom/lama tidak ada di list dynamic, tetapkan sebagai opsi terpilih kustom
            if (autoblogSelectedModel && !foundSelected && provider !== 'hf') {
                $modelSelect.append(
                    $('<option></option>').val(autoblogSelectedModel).text(autoblogSelectedModel).prop('selected', true)
                );
            }

            // Tampilkan Grounding & tester jika provider adalah Gemini
            if (provider === 'gemini') {
                $('#row_gemini_grounding').show();
            } else {
                $('#row_gemini_grounding').hide();
            }
        }

        /**
         * Cek ketersediaan API key untuk Embedding Provider
         */
        function checkRAGKey() {
            var provider = $('#autoblog_embedding_provider').val();
            if ( ! filledKeys[provider] ) {
                $('#rag_key_warning').show();
            } else {
                $('#rag_key_warning').hide();
            }
        }

        // Bind event change
        $('#autoblog_ai_provider').change(function() {
            updateAIModelDropdown();
        });
        
        $('#autoblog_embedding_provider').change(function() {
            checkRAGKey();
        });

        // Jalankan saat halaman dimuat
        updateAIModelDropdown();
        checkRAGKey();
    });
</script>
