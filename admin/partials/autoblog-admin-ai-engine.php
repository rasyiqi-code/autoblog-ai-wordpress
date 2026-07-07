<?php
/**
 * Tab AI Engine — Konfigurasi AI Provider, Model, Embedding, dan Search.
 *
 * Semua pengaturan terkait "mesin AI" digabungkan di sini:
 * - Active AI Provider + Model selector
 * - Embedding Provider (RAG)
 * - Search Provider
 * - Smart Fallback toggle
 *
 * Dirender di dalam <form> dari autoblog-admin-display.php.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */
?>

<table class="form-table">

    <!-- Active AI Provider -->
    <tr valign="top">
        <th scope="row">Active AI Provider</th>
        <td>
            <select name="autoblog_ai_provider" id="autoblog_ai_provider">
                <option value="openai" <?php selected( get_option('autoblog_ai_provider'), 'openai' ); ?>>OpenAI</option>
                <option value="anthropic" <?php selected( get_option('autoblog_ai_provider'), 'anthropic' ); ?>>Anthropic</option>
                <option value="gemini" <?php selected( get_option('autoblog_ai_provider'), 'gemini' ); ?>>Google Gemini</option>
                <option value="groq" <?php selected( get_option('autoblog_ai_provider'), 'groq' ); ?>>Groq (Llama/Mixtral)</option>
                <option value="openrouter" <?php selected( get_option('autoblog_ai_provider'), 'openrouter' ); ?>>OpenRouter</option>
                <option value="hf" <?php selected( get_option('autoblog_ai_provider'), 'hf' ); ?>>Hugging Face</option>
            </select>
            <p class="description">Provider utama untuk generate konten artikel.</p>
        </td>
    </tr>

    <!-- AI Model (dynamic berdasarkan provider) -->
    <?php
    $merged_models = AgencyOS_Autoblog_Admin::get_merged_models();
    ?>
    <tr valign="top">
        <th scope="row">AI Model</th>
        <td>
            <!-- OpenAI Models -->
            <?php
            $selected_openai = get_option('autoblog_openai_model', 'gpt-4o');
            $openai_models = isset($merged_models['openai']) ? $merged_models['openai'] : array();
            if ( ! isset( $openai_models[$selected_openai] ) ) {
                $openai_models[$selected_openai] = $selected_openai;
            }
            ?>
            <div class="model-select-container" id="container_model_openai">
                <select name="autoblog_openai_model">
                    <?php foreach ( $openai_models as $m_id => $m_name ) : ?>
                        <option value="<?php echo esc_attr($m_id); ?>" <?php selected( $selected_openai, $m_id ); ?>><?php echo esc_html($m_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Anthropic Models -->
            <?php
            $selected_anthropic = get_option('autoblog_anthropic_model', 'claude-3-5-sonnet-20240620');
            $anthropic_models = isset($merged_models['anthropic']) ? $merged_models['anthropic'] : array();
            if ( ! isset( $anthropic_models[$selected_anthropic] ) ) {
                $anthropic_models[$selected_anthropic] = $selected_anthropic;
            }
            ?>
            <div class="model-select-container" id="container_model_anthropic" style="display:none;">
                <select name="autoblog_anthropic_model">
                    <?php foreach ( $anthropic_models as $m_id => $m_name ) : ?>
                        <option value="<?php echo esc_attr($m_id); ?>" <?php selected( $selected_anthropic, $m_id ); ?>><?php echo esc_html($m_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Gemini Models -->
            <?php
            $selected_gemini = get_option('autoblog_gemini_model', 'gemini-3.1-pro');
            $gemini_models = isset($merged_models['google']) ? $merged_models['google'] : array();
            if ( ! isset( $gemini_models[$selected_gemini] ) ) {
                $gemini_models[$selected_gemini] = $selected_gemini;
            }
            ?>
            <div class="model-select-container" id="container_model_gemini" style="display:none;">
                <select name="autoblog_gemini_model">
                    <?php foreach ( $gemini_models as $m_id => $m_name ) : ?>
                        <option value="<?php echo esc_attr($m_id); ?>" <?php selected( $selected_gemini, $m_id ); ?>><?php echo esc_html($m_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Groq Models -->
            <?php
            $selected_groq = get_option('autoblog_groq_model', 'llama-3.3-70b-versatile');
            $groq_models = isset($merged_models['groq']) ? $merged_models['groq'] : array();
            if ( ! isset( $groq_models[$selected_groq] ) ) {
                $groq_models[$selected_groq] = $selected_groq;
            }
            ?>
            <div class="model-select-container" id="container_model_groq" style="display:none;">
                <select name="autoblog_groq_model">
                    <?php foreach ( $groq_models as $m_id => $m_name ) : ?>
                        <option value="<?php echo esc_attr($m_id); ?>" <?php selected( $selected_groq, $m_id ); ?>><?php echo esc_html($m_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- OpenRouter Models -->
            <?php
            $selected_openrouter = get_option('autoblog_openrouter_model', 'openrouter/auto');
            $openrouter_models = isset($merged_models['openrouter']) ? $merged_models['openrouter'] : array();
            if ( ! isset( $openrouter_models[$selected_openrouter] ) ) {
                $openrouter_models[$selected_openrouter] = $selected_openrouter;
            }
            ?>
            <div class="model-select-container" id="container_model_openrouter" style="display:none;">
                <select name="autoblog_openrouter_model">
                    <?php foreach ( $openrouter_models as $m_id => $m_name ) : ?>
                        <option value="<?php echo esc_attr($m_id); ?>" <?php selected( $selected_openrouter, $m_id ); ?>><?php echo esc_html($m_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- HF Models (free-form input) -->
            <div class="model-select-container" id="container_model_hf" style="display:none;">
                <input type="text" name="autoblog_hf_model"
                       value="<?php echo esc_attr( get_option('autoblog_hf_model') ); ?>"
                       class="regular-text" placeholder="e.g. meta-llama/Llama-2-7b-chat-hf" />
                <p class="description">Masukkan ID model Hugging Face lengkap.</p>
            </div>
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

    <!-- Search Provider -->
    <tr valign="top">
        <th scope="row">Default Search Provider</th>
        <td>
            <select name="autoblog_search_provider">
                <option value="serpapi" <?php selected( get_option('autoblog_search_provider', 'serpapi'), 'serpapi' ); ?>>SerpApi (Google AI/Bing Copilot)</option>
                <option value="brave" <?php selected( get_option('autoblog_search_provider'), 'brave' ); ?>>Brave Search (Free)</option>
                <option value="google_cse" <?php selected( get_option('autoblog_search_provider'), 'google_cse' ); ?>>Google Custom Search</option>
            </select>
            <p class="description">Provider pencarian web untuk trigger Web Search dan Deep Research.</p>
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
            <p class="description">Jika aktif, plugin otomatis switch ke provider lain (misal Gemini → Groq → OpenAI) jika provider utama gagal atau melebihi kuota.</p>
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
            <p class="description">Mungkinkan Gemini mengakses Google Search secara real-time untuk akurasi fakta yang lebih tinggi (Tanpa perlu API Search eksternal).</p>
            
            <div id="gemini_tester_box" style="margin-top: 15px; padding: 15px; background: #f0f0f1; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h4 style="margin-top:0;">🧪 Gemini Grounding Tester</h4>
                <p class="description" style="margin-bottom:10px;">Coba tanyakan sesuatu yang membutuhkan data real-time (misal: "Siapa pemenang Oscar 2024?" atau "Harga Bitcoin hari ini").</p>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <label for="gemini_test_model" style="font-weight:bold; min-width:100px;">Pilih Model:</label>
                        <select id="gemini_test_model" style="flex-grow:1;">
                            <option value="gemini-3.1-pro">Gemini 3.1 Pro (Akurasi)</option>
                            <option value="gemini-3.0-flash">Gemini 3 Flash (Kecepatan)</option>
                            <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                            <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
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

<!-- JavaScript untuk toggle model dropdown berdasarkan provider -->
<?php
$keys_filled = array(
    'openai'     => ! empty( get_option( 'autoblog_openai_key' ) ),
    'gemini_001' => ! empty( get_option( 'autoblog_gemini_key' ) ),
    'hf'         => ! empty( get_option( 'autoblog_hf_key' ) ),
);
?>
<script>
    jQuery(document).ready(function($) {
        var filledKeys = <?php echo json_encode( $keys_filled ); ?>;

        /**
         * Toggle visibility dropdown model dan opsi khusus provider.
         */
        function toggleModelDropdowns() {
            var provider = $('#autoblog_ai_provider').val();

            // Sembunyikan semua container model dan opsi khusus
            $('.model-select-container').hide();
            $('#row_gemini_grounding').hide();

            // Tampilkan container sesuai provider
            var containerId = '#container_model_' + provider;
            $(containerId).show();

            // Tampilkan Grounding jika provider adalah Gemini
            if (provider === 'gemini') {
                $('#row_gemini_grounding').show();
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
            toggleModelDropdowns();
        });
        
        $('#autoblog_embedding_provider').change(function() {
            checkRAGKey();
        });

        // Jalankan saat halaman dimuat
        toggleModelDropdowns();
        checkRAGKey();
    });
</script>
