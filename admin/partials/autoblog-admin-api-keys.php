<?php
/**
 * Tab API Keys — Menampilkan semua field API key provider.
 *
 * File ini hanya berisi input kredensial, tidak ada konfigurasi AI/model.
 * Dirender di dalam <form> dari autoblog-admin-display.php.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */
?>

<table class="form-table">
    <!-- OpenAI -->
    <tr valign="top">
        <th scope="row">OpenAI API Key</th>
        <td>
            <input type="password" name="autoblog_openai_key"
                   value="<?php echo esc_attr( get_option('autoblog_openai_key') ); ?>"
                   class="regular-text" />
            <p class="description">Digunakan untuk GPT-4o, text-embedding-3-small, dan DALL·E.</p>
        </td>
    </tr>

    <!-- Anthropic -->
    <tr valign="top">
        <th scope="row">Anthropic API Key</th>
        <td>
            <input type="password" name="autoblog_anthropic_key"
                   value="<?php echo esc_attr( get_option('autoblog_anthropic_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Claude 3.5 Sonnet, Opus, Haiku.</p>
        </td>
    </tr>

    <!-- Google Gemini -->
    <tr valign="top">
        <th scope="row">Google Gemini API Key</th>
        <td>
            <input type="password" name="autoblog_gemini_key"
                   value="<?php echo esc_attr( get_option('autoblog_gemini_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Gemini Pro/Flash dan embedding-001.</p>
        </td>
    </tr>

    <!-- Groq -->
    <tr valign="top">
        <th scope="row">Groq API Key</th>
        <td>
            <input type="password" name="autoblog_groq_key"
                   value="<?php echo esc_attr( get_option('autoblog_groq_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Llama 3 dan Mixtral via Groq inference.</p>
        </td>
    </tr>

    <!-- Hugging Face -->
    <tr valign="top">
        <th scope="row">Hugging Face API Key</th>
        <td>
            <input type="password" name="autoblog_hf_key"
                   value="<?php echo esc_attr( get_option('autoblog_hf_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk model MiniLM-L6-v2 embedding dan model HF lainnya.</p>
        </td>
    </tr>

    <!-- OpenRouter -->
    <tr valign="top">
        <th scope="row">OpenRouter API Key</th>
        <td>
            <input type="password" name="autoblog_openrouter_key"
                   value="<?php echo esc_attr( get_option('autoblog_openrouter_key') ); ?>"
                   class="regular-text" />
            <p class="description">Akses ke berbagai model via OpenRouter (Qwen, Gemma, Llama, dll).</p>
        </td>
    </tr>

    <!-- SerpApi -->
    <tr valign="top">
        <th scope="row">SerpApi Key</th>
        <td>
            <input type="password" name="autoblog_serpapi_key"
                   value="<?php echo esc_attr( get_option('autoblog_serpapi_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Google AI Overview, AI Mode, dan Bing Copilot integration.</p>
        </td>
    </tr>

    <!-- Pexels -->
    <tr valign="top">
        <th scope="row">Pexels API Key</th>
        <td>
            <input type="password" name="autoblog_pexels_key"
                   value="<?php echo esc_attr( get_option('autoblog_pexels_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk mencari gambar stok gratis berkualitas tinggi sebagai thumbnail post.</p>
        </td>
    </tr>
</table>
