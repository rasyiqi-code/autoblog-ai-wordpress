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
    <tr valign="top">
        <th scope="row">AI Model</th>
        <td>
            <!-- OpenAI Models -->
            <div class="model-select-container" id="container_model_openai">
                <select name="autoblog_openai_model">
                    <option value="gpt-4o" <?php selected( get_option('autoblog_openai_model'), 'gpt-4o' ); ?>>GPT-4o (Most Capable)</option>
                    <option value="gpt-4-turbo" <?php selected( get_option('autoblog_openai_model'), 'gpt-4-turbo' ); ?>>GPT-4 Turbo</option>
                    <option value="gpt-3.5-turbo" <?php selected( get_option('autoblog_openai_model'), 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Fast/Cheap)</option>
                </select>
            </div>

            <!-- Anthropic Models -->
            <div class="model-select-container" id="container_model_anthropic" style="display:none;">
                <select name="autoblog_anthropic_model">
                    <option value="claude-3-5-sonnet-20240620" <?php selected( get_option('autoblog_anthropic_model'), 'claude-3-5-sonnet-20240620' ); ?>>Claude 3.5 Sonnet</option>
                    <option value="claude-3-opus-20240229" <?php selected( get_option('autoblog_anthropic_model'), 'claude-3-opus-20240229' ); ?>>Claude 3 Opus</option>
                    <option value="claude-3-haiku-20240307" <?php selected( get_option('autoblog_anthropic_model'), 'claude-3-haiku-20240307' ); ?>>Claude 3 Haiku</option>
                </select>
            </div>

            <!-- Gemini Models -->
            <div class="model-select-container" id="container_model_gemini" style="display:none;">
                <select name="autoblog_gemini_model">
                    <option value="auto" <?php selected( get_option('autoblog_gemini_model'), 'auto' ); ?>>Auto (Best for request)</option>
                    <!-- Gemini 3 Series -->
                    <option value="gemini-3.1-pro" <?php selected( get_option('autoblog_gemini_model'), 'gemini-3.1-pro' ); ?>>Gemini 3.1 Pro</option>
                    <option value="gemini-3.0-pro" <?php selected( get_option('autoblog_gemini_model'), 'gemini-3.0-pro' ); ?>>Gemini 3 Pro</option>
                    <option value="gemini-3.0-flash" <?php selected( get_option('autoblog_gemini_model'), 'gemini-3.0-flash' ); ?>>Gemini 3 Flash</option>
                    <!-- Gemini 2.5 Series -->
                    <option value="gemini-2.5-pro" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.5-pro' ); ?>>Gemini 2.5 Pro</option>
                    <option value="gemini-2.5-flash" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.5-flash' ); ?>>Gemini 2.5 Flash</option>
                    <option value="gemini-2.5-flash-lite" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.5-flash-lite' ); ?>>Gemini 2.5 Flash Lite</option>
                    <!-- Gemini 2 Series -->
                    <option value="gemini-2.0-flash" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-flash' ); ?>>Gemini 2 Flash</option>
                    <option value="gemini-2.0-flash-lite" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-flash-lite' ); ?>>Gemini 2 Flash Lite</option>
                    <option value="gemini-2.0-flash-exp" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-flash-exp' ); ?>>Gemini 2 Flash Exp</option>
                    <option value="gemini-2.0-pro-exp" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-pro-exp' ); ?>>Gemini 2 Pro Exp</option>
                    <!-- Gemma 3 Series -->
                    <option value="gemma-3-27b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-27b-it' ); ?>>Gemma 3 27B</option>
                    <option value="gemma-3-12b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-12b-it' ); ?>>Gemma 3 12B</option>
                    <option value="gemma-3-4b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-4b-it' ); ?>>Gemma 3 4B</option>
                    <option value="gemma-3-2b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-2b-it' ); ?>>Gemma 3 2B</option>
                    <option value="gemma-3-1b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-1b-it' ); ?>>Gemma 3 1B</option>
                </select>
            </div>

            <!-- Groq Models -->
            <div class="model-select-container" id="container_model_groq" style="display:none;">
                <select name="autoblog_groq_model">
                    <option value="auto" <?php selected( get_option('autoblog_groq_model'), 'auto' ); ?>>Auto (Best for request)</option>
                    <option value="gpt-oss-120b" <?php selected( get_option('autoblog_groq_model'), 'gpt-oss-120b' ); ?>>GPT OSS 120B</option>
                    <option value="gpt-oss-20b" <?php selected( get_option('autoblog_groq_model'), 'gpt-oss-20b' ); ?>>GPT OSS 20B</option>
                    <option value="llama-4-scout" <?php selected( get_option('autoblog_groq_model'), 'llama-4-scout' ); ?>>Llama 4 Scout</option>
                    <option value="llama-4-maverick" <?php selected( get_option('autoblog_groq_model'), 'llama-4-maverick' ); ?>>Llama 4 Maverick</option>
                    <option value="llama-3.3-70b-versatile" <?php selected( get_option('autoblog_groq_model'), 'llama-3.3-70b-versatile' ); ?>>Llama 3.3 70B</option>
                    <option value="qwen-3-32b" <?php selected( get_option('autoblog_groq_model'), 'qwen-3-32b' ); ?>>Qwen 3 32B</option>
                    <option value="kimi-k2" <?php selected( get_option('autoblog_groq_model'), 'kimi-k2' ); ?>>Kimi K2</option>
                    <option value="llama3-70b-8192" <?php selected( get_option('autoblog_groq_model'), 'llama3-70b-8192' ); ?>>Llama 3 70B (Legacy)</option>
                    <option value="llama3-8b-8192" <?php selected( get_option('autoblog_groq_model'), 'llama3-8b-8192' ); ?>>Llama 3 8B (Legacy)</option>
                    <option value="mixtral-8x7b-32768" <?php selected( get_option('autoblog_groq_model'), 'mixtral-8x7b-32768' ); ?>>Mixtral 8x7B</option>
                    <option value="gemma-7b-it" <?php selected( get_option('autoblog_groq_model'), 'gemma-7b-it' ); ?>>Gemma 7B (Google)</option>
                </select>
            </div>

            <!-- OpenRouter Models -->
            <div class="model-select-container" id="container_model_openrouter" style="display:none;">
                <select name="autoblog_openrouter_model">
                    <option value="openrouter/auto" <?php selected( get_option('autoblog_openrouter_model'), 'openrouter/auto' ); ?>>Auto (Best for request)</option>
                    <option value="qwen/qwen3-vl-30b-a3b-thinking" <?php selected( get_option('autoblog_openrouter_model'), 'qwen/qwen3-vl-30b-a3b-thinking' ); ?>>Qwen: Qwen3 VL 30B A3B Thinking ($0/1M)</option>
                    <option value="qwen/qwen3-vl-235b-a22b-thinking" <?php selected( get_option('autoblog_openrouter_model'), 'qwen/qwen3-vl-235b-a22b-thinking' ); ?>>Qwen: Qwen3 VL 235B A22B Thinking ($0/1M)</option>
                    <option value="qwen/qwen3-next-80b-a3b-instruct:free" <?php selected( get_option('autoblog_openrouter_model'), 'qwen/qwen3-next-80b-a3b-instruct:free' ); ?>>Qwen: Qwen3 Next 80B A3B Instruct (Free)</option>
                    <option value="nvidia/nemotron-nano-9b-v2:free" <?php selected( get_option('autoblog_openrouter_model'), 'nvidia/nemotron-nano-9b-v2:free' ); ?>>NVIDIA: Nemotron Nano 9B V2 (Free)</option>
                    <option value="openai/gpt-oss-120b:free" <?php selected( get_option('autoblog_openrouter_model'), 'openai/gpt-oss-120b:free' ); ?>>OpenAI: gpt-oss-120b (Free)</option>
                    <option value="openai/gpt-oss-20b:free" <?php selected( get_option('autoblog_openrouter_model'), 'openai/gpt-oss-20b:free' ); ?>>OpenAI: gpt-oss-20b (Free)</option>
                    <option value="z-ai/glm-4.5-air:free" <?php selected( get_option('autoblog_openrouter_model'), 'z-ai/glm-4.5-air:free' ); ?>>Z.ai: GLM 4.5 Air (Free)</option>
                    <option value="qwen/qwen3-235b-a22b-thinking-2507" <?php selected( get_option('autoblog_openrouter_model'), 'qwen/qwen3-235b-a22b-thinking-2507' ); ?>>Qwen: Qwen3 235B A22B Thinking 2507 ($0/1M)</option>
                    <option value="qwen/qwen3-coder:free" <?php selected( get_option('autoblog_openrouter_model'), 'qwen/qwen3-coder:free' ); ?>>Qwen: Qwen3 Coder 480B A35B (Free)</option>
                    <option value="cognitivecomputations/dolphin-mistral-24b-venice-edition:free" <?php selected( get_option('autoblog_openrouter_model'), 'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' ); ?>>Venice: Uncensored (Free)</option>
                    <option value="google/gemma-3n-e2b-it:free" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3n-e2b-it:free' ); ?>>Google: Gemma 3n 2B (Free)</option>
                    <option value="deepseek/deepseek-r1-0528:free" <?php selected( get_option('autoblog_openrouter_model'), 'deepseek/deepseek-r1-0528:free' ); ?>>DeepSeek: R1 0528 (Free)</option>
                    <option value="google/gemma-3n-e4b-it:free" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3n-e4b-it:free' ); ?>>Google: Gemma 3n 4B (Free)</option>
                    <option value="qwen/qwen3-4b:free" <?php selected( get_option('autoblog_openrouter_model'), 'qwen/qwen3-4b:free' ); ?>>Qwen: Qwen3 4B (Free)</option>
                    <option value="mistralai/mistral-small-3.1-24b-instruct:free" <?php selected( get_option('autoblog_openrouter_model'), 'mistralai/mistral-small-3.1-24b-instruct:free' ); ?>>Mistral: Mistral Small 3.1 24B (Free)</option>
                    <option value="google/gemma-3-4b-it:free" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3-4b-it:free' ); ?>>Google: Gemma 3 4B (Free)</option>
                    <option value="google/gemma-3-12b-it:free" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3-12b-it:free' ); ?>>Google: Gemma 3 12B (Free)</option>
                    <option value="google/gemma-3-27b-it:free" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3-27b-it:free' ); ?>>Google: Gemma 3 27B (Free)</option>
                    <option value="meta-llama/llama-3.3-70b-instruct:free" <?php selected( get_option('autoblog_openrouter_model'), 'meta-llama/llama-3.3-70b-instruct:free' ); ?>>Meta: Llama 3.3 70B Instruct (Free)</option>
                    <option value="meta-llama/llama-3.2-3b-instruct:free" <?php selected( get_option('autoblog_openrouter_model'), 'meta-llama/llama-3.2-3b-instruct:free' ); ?>>Meta: Llama 3.2 3B Instruct (Free)</option>
                    <option value="nousresearch/hermes-3-llama-3.1-405b:free" <?php selected( get_option('autoblog_openrouter_model'), 'nousresearch/hermes-3-llama-3.1-405b:free' ); ?>>Nous: Hermes 3 405B Instruct (Free)</option>
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
            <select name="autoblog_embedding_provider">
                <option value="openai" <?php selected( get_option('autoblog_embedding_provider'), 'openai' ); ?>>OpenAI (text-embedding-3-small)</option>
                <option value="gemini_001" <?php selected( get_option('autoblog_embedding_provider'), 'gemini_001' ); ?>>Google Gemini (gemini-embedding-001)</option>
                <option value="hf" <?php selected( get_option('autoblog_embedding_provider'), 'hf' ); ?>>Hugging Face (MiniLM-L6-v2)</option>
            </select>
            <p class="description">Model untuk vektorisasi file Knowledge Base (RAG).</p>
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

    <!-- Thumbnail Methods Selection -->
    <tr valign="top">
        <th scope="row">Enabled Thumbnail Methods</th>
        <td>
            <fieldset>
                <label>
                    <input name="autoblog_enable_dalle" type="checkbox" value="1" <?php checked( '1', get_option( 'autoblog_enable_dalle', '1' ) ); ?> />
                    OpenAI DALL-E 3 (AI Generated)
                </label><br>
                <label>
                    <input name="autoblog_enable_stock_pexels" type="checkbox" value="1" <?php checked( '1', get_option( 'autoblog_enable_stock_pexels', '1' ) ); ?> />
                    Pexels (Stock Photos)
                </label><br>
                <label>
                    <input name="autoblog_enable_stock_openverse" type="checkbox" value="1" <?php checked( '1', get_option( 'autoblog_enable_stock_openverse', '1' ) ); ?> />
                    Openverse (Open Source Media)
                </label>
            </fieldset>
            <p class="description">Aktifkan metode yang ingin Anda gunakan. Jika dimatikan, metode tersebut tidak akan muncul di pilihan "Post Thumbnail Source" di bawah.</p>
        </td>
    </tr>

    <!-- Thumbnail Source -->
    <tr valign="top">
        <th scope="row">Post Thumbnail Source</th>
        <td>
            <select name="autoblog_thumbnail_source" id="autoblog_thumbnail_source">
                <?php if ( get_option( 'autoblog_enable_dalle', '1' ) === '1' ) : ?>
                    <option value="openai" <?php selected( get_option('autoblog_thumbnail_source'), 'openai' ); ?>>OpenAI DALL-E 3</option>
                <?php endif; ?>

                <?php if ( get_option( 'autoblog_enable_stock_pexels', '1' ) === '1' ) : ?>
                    <option value="pexels" <?php selected( get_option('autoblog_thumbnail_source', 'pexels'), 'pexels' ); ?>>Pexels (Stock Photos)</option>
                <?php endif; ?>

                <?php if ( get_option( 'autoblog_enable_stock_openverse', '1' ) === '1' ) : ?>
                    <option value="openverse" <?php selected( get_option('autoblog_thumbnail_source'), 'openverse' ); ?>>Openverse</option>
                <?php endif; ?>

                <option value="random_stock" <?php selected( get_option('autoblog_thumbnail_source'), 'random_stock' ); ?>>Mix: Pexels -> Openverse (Fallback chain)</option>
            </select>
            <p class="description">Pilih sumber gambar utama. Pexels disarankan sebagai default untuk menghindari biaya AI tambahan.</p>
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
<script>
    jQuery(document).ready(function($) {
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

        // Bind event change
        $('#autoblog_ai_provider').change(function() {
            toggleModelDropdowns();
        });

        // Jalankan saat halaman dimuat
        toggleModelDropdowns();
    });
</script>
