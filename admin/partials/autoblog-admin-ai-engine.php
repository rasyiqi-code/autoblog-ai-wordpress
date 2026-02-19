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
                    <!-- Gemini 3 Series -->
                    <option value="gemini-3.0-pro" <?php selected( get_option('autoblog_gemini_model'), 'gemini-3.0-pro' ); ?>>Gemini 3 Pro</option>
                    <option value="gemini-3.0-flash" <?php selected( get_option('autoblog_gemini_model'), 'gemini-3.0-flash' ); ?>>Gemini 3 Flash</option>
                    <!-- Gemini 2.5 Series -->
                    <option value="gemini-2.5-pro" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.5-pro' ); ?>>Gemini 2.5 Pro</option>
                    <option value="gemini-2.5-flash" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.5-flash' ); ?>>Gemini 2.5 Flash</option>
                    <option value="gemini-2.5-flash-lite" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.5-flash-lite' ); ?>>Gemini 2.5 Flash Lite</option>
                    <!-- Gemini 2 Series -->
                    <option value="gemini-2.0-pro-exp" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-pro-exp' ); ?>>Gemini 2.0 Pro Exp (Experimental)</option>
                    <option value="gemini-2.0-flash" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-flash' ); ?>>Gemini 2.0 Flash</option>
                    <option value="gemini-2.0-flash-lite" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-flash-lite' ); ?>>Gemini 2.0 Flash Lite</option>
                    <option value="gemini-2.0-flash-exp" <?php selected( get_option('autoblog_gemini_model'), 'gemini-2.0-flash-exp' ); ?>>Gemini 2.0 Flash Exp (Experimental)</option>
                    <!-- Gemma 3 Series -->
                    <option value="gemma-3-27b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-27b-it' ); ?>>Gemma 3 27B</option>
                    <option value="gemma-3-12b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-12b-it' ); ?>>Gemma 3 12B</option>
                    <option value="gemma-3-4b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-4b-it' ); ?>>Gemma 3 4B</option>
                    <option value="gemma-3-1b-it" <?php selected( get_option('autoblog_gemini_model'), 'gemma-3-1b-it' ); ?>>Gemma 3 1B</option>
                </select>
            </div>

            <!-- Groq Models -->
            <div class="model-select-container" id="container_model_groq" style="display:none;">
                <select name="autoblog_groq_model">
                    <option value="llama3-70b-8192" <?php selected( get_option('autoblog_groq_model'), 'llama3-70b-8192' ); ?>>Llama 3 70B</option>
                    <option value="llama3-8b-8192" <?php selected( get_option('autoblog_groq_model'), 'llama3-8b-8192' ); ?>>Llama 3 8B</option>
                    <option value="mixtral-8x7b-32768" <?php selected( get_option('autoblog_groq_model'), 'mixtral-8x7b-32768' ); ?>>Mixtral 8x7B</option>
                    <option value="gemma-7b-it" <?php selected( get_option('autoblog_groq_model'), 'gemma-7b-it' ); ?>>Gemma 7B (Google)</option>
                </select>
            </div>

            <!-- OpenRouter Models -->
            <div class="model-select-container" id="container_model_openrouter" style="display:none;">
                <select name="autoblog_openrouter_model">
                    <option value="openrouter/auto" <?php selected( get_option('autoblog_openrouter_model'), 'openrouter/auto' ); ?>>Auto (Best for request)</option>
                    <option value="qwen/qwen-2.5-vl-72b-instruct:free" <?php selected( get_option('autoblog_openrouter_model'), 'qwen/qwen-2.5-vl-72b-instruct:free' ); ?>>Qwen 2.5 VL 72B (Free)</option>
                    <option value="google/gemma-3-27b-it" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3-27b-it' ); ?>>Gemma 3 27B</option>
                    <option value="liquid/lfm-1b-instruct" <?php selected( get_option('autoblog_openrouter_model'), 'liquid/lfm-1b-instruct' ); ?>>Liquid LFM 1.2B Instruct</option>
                    <option value="mistralai/mistral-small-24b-instruct-2501" <?php selected( get_option('autoblog_openrouter_model'), 'mistralai/mistral-small-24b-instruct-2501' ); ?>>Mistral Small 3 24B</option>
                    <option value="nousresearch/hermes-3-llama-3.1-405b" <?php selected( get_option('autoblog_openrouter_model'), 'nousresearch/hermes-3-llama-3.1-405b' ); ?>>Nous Hermes 3 405B</option>
                    <option value="google/gemma-3-12b-it" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3-12b-it' ); ?>>Gemma 3 12B</option>
                    <option value="google/gemma-3-4b-it" <?php selected( get_option('autoblog_openrouter_model'), 'google/gemma-3-4b-it' ); ?>>Gemma 3 4B</option>
                    <option value="meta-llama/llama-3.2-3b-instruct:free" <?php selected( get_option('autoblog_openrouter_model'), 'meta-llama/llama-3.2-3b-instruct:free' ); ?>>Llama 3.2 3B (Free)</option>
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
</table>

<!-- JavaScript untuk toggle model dropdown berdasarkan provider -->
<script>
    jQuery(document).ready(function($) {
        /**
         * Toggle visibility dropdown model sesuai AI provider yang dipilih.
         * Menyembunyikan semua container lalu menampilkan yang relevan.
         */
        function toggleModelDropdowns() {
            var provider = $('#autoblog_ai_provider').val();

            // Sembunyikan semua container model
            $('.model-select-container').hide();

            // Tampilkan container sesuai provider
            var containerId = '#container_model_' + provider;
            $(containerId).show();
        }

        // Bind event change
        $('#autoblog_ai_provider').change(function() {
            toggleModelDropdowns();
        });

        // Jalankan saat halaman dimuat
        toggleModelDropdowns();
    });
</script>
