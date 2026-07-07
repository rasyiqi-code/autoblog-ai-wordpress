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
    <!-- OpenAI -->
    <tr valign="top">
        <th scope="row">OpenAI API Key <?php echo get_key_badge('openai', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_openai_key"
                   value="<?php echo esc_attr( get_option('autoblog_openai_key') ); ?>"
                   class="regular-text" />
            <p class="description">Digunakan untuk GPT-4o, text-embedding-3-small, dan DALL·E.</p>
        </td>
    </tr>

    <!-- Anthropic -->
    <tr valign="top">
        <th scope="row">Anthropic API Key <?php echo get_key_badge('anthropic', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_anthropic_key"
                   value="<?php echo esc_attr( get_option('autoblog_anthropic_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Claude 3.5 Sonnet, Opus, Haiku.</p>
        </td>
    </tr>

    <!-- Google Gemini -->
    <tr valign="top">
        <th scope="row">Google Gemini API Key <?php echo get_key_badge('gemini', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_gemini_key"
                   value="<?php echo esc_attr( get_option('autoblog_gemini_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Gemini Pro/Flash dan embedding-001.</p>
        </td>
    </tr>

    <!-- Groq -->
    <tr valign="top">
        <th scope="row">Groq API Key <?php echo get_key_badge('groq', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_groq_key"
                   value="<?php echo esc_attr( get_option('autoblog_groq_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Llama 3.3 dan Mixtral via Groq inference.</p>
        </td>
    </tr>

    <!-- Hugging Face -->
    <tr valign="top">
        <th scope="row">Hugging Face API Key <?php echo get_key_badge('hf', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_hf_key"
                   value="<?php echo esc_attr( get_option('autoblog_hf_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk model MiniLM-L6-v2 embedding dan model HF lainnya.</p>
        </td>
    </tr>

    <!-- OpenRouter -->
    <tr valign="top">
        <th scope="row">OpenRouter API Key <?php echo get_key_badge('openrouter', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_openrouter_key"
                   value="<?php echo esc_attr( get_option('autoblog_openrouter_key') ); ?>"
                   class="regular-text" />
            <p class="description">Akses ke berbagai model via OpenRouter (Qwen, Gemma, Llama, dll).</p>
        </td>
    </tr>

    <!-- SerpApi -->
    <tr valign="top">
        <th scope="row">SerpApi Key <?php echo get_key_badge('serpapi', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_serpapi_key"
                   value="<?php echo esc_attr( get_option('autoblog_serpapi_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk Google AI Overview, AI Mode, dan Bing Copilot integration.</p>
        </td>
    </tr>

    <!-- Pexels -->
    <tr valign="top">
        <th scope="row">Pexels API Key <?php echo get_key_badge('pexels', $active_provider, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></th>
        <td>
            <input type="password" name="autoblog_pexels_key"
                   value="<?php echo esc_attr( get_option('autoblog_pexels_key') ); ?>"
                   class="regular-text" />
            <p class="description">Untuk mencari gambar stok gratis berkualitas tinggi sebagai thumbnail post.</p>
        </td>
    </tr>
</table>
