<?php
/**
 * Tab AI & API Settings (Unified Form with Dynamic Keys List at the Top - Compact Layout)
 *
 * Menggabungkan tab AI Engine dan API Keys menjadi satu halaman terpadu.
 * Menggunakan tata letak tabel terstruktur lebar penuh (compact & grid-like)
 * dengan override CSS micro-UI untuk kerapian, keteraturan visual, dan keselarasan vertikal.
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

// Normalisasi key active provider untuk label
$active_key_id = $selected_provider;
if ( $selected_provider === 'gemini' ) {
    $active_key_id = 'google';
} elseif ( $selected_provider === 'hf' ) {
    $active_key_id = 'huggingface';
}

// Helper untuk menampilkan badge status key
if ( ! function_exists( 'get_key_badge' ) ) {
    function get_key_badge( $key_provider, $active_key_id, $embedding_key_name, $search_provider, $need_search_key, $need_pexels ) {
        $badges = [];

        if ( $key_provider === $active_key_id ) {
            $badges[] = '<span class="autoblog-badge autoblog-badge-active">AKTIF</span>';
        }
        if ( $key_provider === $embedding_key_name ) {
            $badges[] = '<span class="autoblog-badge autoblog-badge-secondary" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">RAG</span>';
        }
        if ( $key_provider === $search_provider && $need_search_key ) {
            $badges[] = '<span class="autoblog-badge autoblog-badge-secondary" style="background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe;">SEARCH</span>';
        }
        if ( $key_provider === 'pexels' && $need_pexels ) {
            $badges[] = '<span class="autoblog-badge autoblog-badge-secondary" style="background:#e0f2fe; color:#0369a1; border-color:#bae6fd;">IMAGE</span>';
        }

        if ( empty( $badges ) ) {
            return '<span class="autoblog-badge autoblog-badge-secondary">CADANGAN</span>';
        }
        return implode( ' ', $badges );
    }
}
?>

<style>
    /* Wrapper Global Settings */
    .autoblog-settings-wrap {
        margin-top: 15px;
    }
    
    /* Override Tinggi & Padding Element agar Sejajar & Compact */
    .autoblog-settings-wrap input[type="text"],
    .autoblog-settings-wrap select,
    .autoblog-settings-wrap textarea {
        border: 1px solid #8c8f94;
        border-radius: 4px;
        background-color: #fff;
        color: #2c3338;
        box-shadow: inset 0 1px 2px rgba(0,0,0,.07);
        transition: border-color 0.1s ease-in-out, box-shadow 0.1s ease-in-out;
        margin: 0;
        box-sizing: border-box;
        font-size: 12px;
        height: 30px;
        line-height: 20px;
        padding: 4px 8px;
        vertical-align: middle;
    }
    
    /* Textarea compact dengan efek focus-expand */
    .autoblog-settings-wrap textarea {
        height: 30px;
        resize: none;
        overflow-y: auto;
    }
    .autoblog-settings-wrap textarea:focus {
        height: 60px;
        resize: vertical;
    }
    
    .autoblog-settings-wrap input[type="text"]:focus,
    .autoblog-settings-wrap select:focus,
    .autoblog-settings-wrap textarea:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
        outline: 2px solid transparent;
    }
    
    /* Unifikasi Button Sizing */
    .autoblog-settings-wrap .button {
        height: 30px !important;
        line-height: 28px !important;
        padding: 0 12px !important;
        font-size: 12px !important;
        margin: 0 !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        vertical-align: middle;
    }
    .autoblog-settings-wrap .button-small {
        height: 26px !important;
        line-height: 24px !important;
        padding: 0 8px !important;
        font-size: 11px !important;
    }
    
    /* Table Compact Styling */
    .autoblog-compact-table {
        width: 100%;
        border: 1px solid #c3c4c7;
        border-collapse: collapse;
        margin-top: 12px;
        background: #fff;
    }
    .autoblog-compact-table th,
    .autoblog-compact-table td {
        padding: 8px 10px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #f0f0f1;
        border-top: none;
        text-align: left;
    }
    .autoblog-compact-table thead th {
        background-color: #f6f7f7;
        font-weight: 700;
        font-size: 12px;
        color: #2c3338;
        border-bottom: 2px solid #c3c4c7;
        padding: 8px 10px !important;
    }
    .autoblog-compact-table tbody tr:hover {
        background-color: #f8fafc;
    }
    
    /* WordPress Form Table Tweaks */
    .autoblog-settings-wrap .form-table {
        margin-top: 10px;
    }
    .autoblog-settings-wrap .form-table th {
        width: 200px;
        padding: 12px 10px 12px 0;
        font-weight: 600;
        font-size: 13px;
        vertical-align: middle;
    }
    .autoblog-settings-wrap .form-table td {
        padding: 12px 0;
        vertical-align: middle;
    }
    
    /* Desain Badge Atribusi Premium */
    .autoblog-badge {
        display: inline-block;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        vertical-align: middle;
        margin-right: 4px;
        border: 1px solid transparent;
        line-height: 12px;
    }
    .autoblog-badge-active {
        background: #dcfce7;
        color: #166534;
        border-color: #bbf7d0;
    }
    .autoblog-badge-secondary {
        background: #f1f5f9;
        color: #475569;
        border-color: #e2e8f0;
    }
</style>

<div class="autoblog-settings-wrap">
    <!-- ================================================================ -->
    <!-- SECTION 1: AI Engine & Model Settings (Unified LLM Center) -->
    <!-- ================================================================ -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">🤖 AI Engine & Model Settings</h2>
        </div>
        <div class="inside">
            <p class="description" style="margin-bottom:0;">Kelola kredensial API Key, Base URL kustom untuk provider LLM Anda, dan tentukan provider aktif menggunakan kolom <strong>Aktif</strong> di bawah.</p>
            
            <!-- Table Dinamis Custom Keys (Compact Grid Layout) -->
            <table class="autoblog-compact-table">
                <thead>
                    <tr>
                        <th scope="col" style="width: 55px; text-align: center;">Aktif</th>
                        <th scope="col" style="width: 120px;">Provider</th>
                        <th scope="col">API Key(s) (Satu per baris)</th>
                        <th scope="col">Base URL (Custom / Bawaan)</th>
                        <th scope="col" style="width: 130px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="custom-keys-tbody">
                    <?php
                    if ( is_array( $custom_keys ) && ! empty( $custom_keys ) ) {
                        foreach ( $custom_keys as $prov_id => $prov_key ) {
                            $prov_name = isset( $providers[$prov_id]['name'] ) ? $providers[$prov_id]['name'] : $prov_id;
                            
                            $check_id = $prov_id;
                            if ( $prov_id === 'google' ) {
                                $check_id = 'gemini';
                            } elseif ( $prov_id === 'huggingface' ) {
                                $check_id = 'hf';
                            }
                            
                            $badge_html = get_key_badge( $prov_id, $active_key_id, $embedding_key_name, $search_provider, $need_search_key, $need_pexels );
                            
                            $default_endpoint = isset( $providers[$prov_id]['api'] ) ? trim( $providers[$prov_id]['api'] ) : '';
                            $current_endpoint = isset( $custom_endpoints[$prov_id] ) ? trim( $custom_endpoints[$prov_id] ) : '';
                            
                            if ( empty( $current_endpoint ) ) {
                                $current_endpoint = $default_endpoint;
                            }
                            ?>
                            <tr class="custom-key-row" data-provider="<?php echo esc_attr($prov_id); ?>">
                                <!-- Col 1: Active Radio -->
                                <td style="text-align: center; vertical-align: middle;">
                                    <input type="radio" class="active-provider-radio" name="autoblog_ai_provider" value="<?php echo esc_attr($prov_id); ?>" <?php checked($selected_provider, $prov_id); ?> style="margin: 0; cursor: pointer;" />
                                </td>
                                <!-- Col 2: Provider Name & Badge -->
                                <td style="vertical-align: middle;">
                                    <span class="provider-label-text" style="font-weight: 700; font-size: 13px; color: #1d2327; display: block;"><?php echo esc_html($prov_name); ?></span>
                                    <div class="provider-badge-container" style="margin-top: 2px;"><?php echo $badge_html; ?></div>
                                </td>
                                <!-- Col 3: API Key Textarea -->
                                <td style="vertical-align: middle;">
                                    <textarea name="autoblog_custom_api_keys[<?php echo esc_attr($prov_id); ?>]" style="width: 100%;" placeholder="Masukkan satu atau lebih API key (satu per baris)..."><?php echo esc_textarea($prov_key); ?></textarea>
                                </td>
                                <!-- Col 4: Base URL Input -->
                                <td style="vertical-align: middle;">
                                    <input type="text" name="autoblog_custom_api_endpoints[<?php echo esc_attr($prov_id); ?>]" value="<?php echo esc_attr($current_endpoint); ?>" placeholder="e.g. https://api.openai.com/v1" style="width: 100%;" />
                                    <span style="font-size: 10px; color: #64748b; display: block; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Bawaan: <?php echo esc_attr($default_endpoint); ?>">
                                        <?php echo $default_endpoint ? 'Bawaan: <code>' . esc_html($default_endpoint) . '</code>' : ''; ?>
                                    </span>
                                </td>
                                <!-- Col 5: Actions & Status -->
                                <td style="vertical-align: middle; text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                                        <button type="button" class="button button-small test-connection-btn" data-provider="<?php echo esc_attr($prov_id); ?>">Test</button>
                                        <button type="button" class="button button-small remove-custom-key" style="color: #d63638; border-color: #d63638;">Remove</button>
                                    </div>
                                    <div class="test-connection-status" style="font-weight: 600; font-size: 10px; margin-top: 4px; display: block; line-height: 1.2;"></div>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr id="no-custom-keys-row">
                            <td colspan="5" style="padding: 15px; color: #64748b; font-style: italic; font-size: 12px; text-align: center;">
                                Belum ada custom provider key yang ditambahkan. Gunakan menu di bawah untuk menambahkannya.
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>

            <!-- Tambah Key & AI Model selection (Unified Row) -->
            <div style="margin-top: 15px; display: flex; gap: 15px; align-items: center; justify-content: space-between; flex-wrap: wrap; padding-top: 15px; border-top: 1px solid #f0f0f1;">
                <!-- Left: Add Key Select + Button -->
                <div style="display: flex; gap: 8px; align-items: center;">
                    <select id="new-custom-provider-select" style="min-width: 160px;">
                        <option value="">-- Pilih Provider Baru --</option>
                        <?php
                        foreach ( $providers as $p_id => $p_data ) {
                            if ( isset( $custom_keys[$p_id] ) ) {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr( $p_id ); ?>"><?php echo esc_html( $p_data['name'] ); ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <button type="button" class="button button-secondary" id="btn-add-custom-key">+ Tambah Key</button>
                </div>

                <!-- Right: AI Model selection -->
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="autoblog_ai_model" style="font-weight: 600; font-size: 13px; color: #1d2327;">AI Model:</label>
                    <select name="autoblog_ai_model" id="autoblog_ai_model" style="min-width: 250px;">
                        <!-- JS dinamis populate -->
                    </select>
                </div>
            </div>
            
            <div id="active_key_warning" style="display:none; color:#d63638; font-weight:bold; margin-top:10px; font-size:11.5px; padding: 6px 10px; background: #fef2f2; border-left: 3px solid #d63638; border-radius: 4px;"></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 2: Helper Services Credentials -->
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
                        <div style="margin-top:4px;"><?php echo get_key_badge('serpapi', $active_key_id, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></div>
                    </th>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <textarea name="autoblog_serpapi_key" style="width: 25em;" placeholder="Masukkan SerpApi key (bisa multi-key, satu per baris)..."><?php echo esc_textarea( get_option('autoblog_serpapi_key') ); ?></textarea>
                            <button type="button" class="button test-connection-btn" data-provider="serpapi">Test Connection</button>
                            <span class="test-connection-status" style="font-weight:600; font-size:11px; vertical-align:middle;"></span>
                        </div>
                        <p class="description" style="margin-top: 4px;">Untuk integrasi Google AI Overview, AI Mode, dan Bing Copilot. Bisa multi-key (satu per baris).</p>
                    </td>
                </tr>

                <!-- Pexels -->
                <tr valign="top">
                    <th scope="row">
                        Pexels API Key
                        <div style="margin-top:4px;"><?php echo get_key_badge('pexels', $active_key_id, $embedding_key_name, $search_provider, $need_search_key, $need_pexels); ?></div>
                    </th>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <textarea name="autoblog_pexels_key" style="width: 25em;" placeholder="Masukkan Pexels API key (bisa multi-key, satu per baris)..."><?php echo esc_textarea( get_option('autoblog_pexels_key') ); ?></textarea>
                            <button type="button" class="button test-connection-btn" data-provider="pexels">Test Connection</button>
                            <span class="test-connection-status" style="font-weight:600; font-size:11px; vertical-align:middle;"></span>
                        </div>
                        <p class="description" style="margin-top: 4px;">Untuk pencarian stock photo gratis berkualitas tinggi sebagai featured image.</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 3: Advanced AI & Media Settings -->
    <!-- ================================================================ -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">⚙️ Advanced AI & Media Settings</h2>
        </div>
        <div class="inside">
            <p class="description">Konfigurasi RAG (Knowledge Base), fallback provider, search grounding, dan stock photo fallback.</p>
            
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
                        <div id="rag_key_warning" style="display:none; color:#d63638; font-weight:bold; margin-top:8px; font-size:11.5px; padding: 6px 10px; background: #fef2f2; border-left: 3px solid #d63638; border-radius: 4px; max-width: 450px;">
                            ⚠️ API Key untuk RAG terpilih kosong. Silakan isi di atas agar Knowledge Base aktif!
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

                <!-- Smart Fallback -->
                <tr valign="top">
                    <th scope="row">Smart Fallback</th>
                    <td>
                        <label for="autoblog_enable_fallback" style="font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <input name="autoblog_enable_fallback" type="checkbox" id="autoblog_enable_fallback" value="1" <?php checked( '1', get_option( 'autoblog_enable_fallback' ) ); ?> style="margin: 0;" />
                            <span>Enable Smart Model Switching</span>
                        </label>
                        <p class="description">Otomatis dialihkan ke provider cadangan jika provider utama mengalami limit/error.</p>
                    </td>
                </tr>

                <!-- Gemini Grounding -->
                <tr valign="top" id="row_gemini_grounding" style="display:none;">
                    <th scope="row">Gemini Search Grounding</th>
                    <td>
                        <label for="autoblog_gemini_grounding" style="font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <input name="autoblog_gemini_grounding" type="checkbox" id="autoblog_gemini_grounding" value="1" <?php checked( '1', get_option( 'autoblog_gemini_grounding' ) ); ?> style="margin: 0;" />
                            <span>Enable Native Google Search Grounding</span>
                        </label>
                        <p class="description">Mungkinkan Gemini mengakses Google Search secara real-time untuk akurasi fakta.</p>
                        
                        <div id="gemini_tester_box" style="margin-top: 10px; padding: 12px; background: #f8fafc; border: 1px solid #dcdcde; border-radius: 4px; max-width:450px;">
                            <h4 style="margin-top:0; font-size:12px; font-weight:700;">🧪 Gemini Grounding Tester</h4>
                            <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <label for="gemini_test_model" style="font-weight:600; font-size:11px; min-width:80px;">Model:</label>
                                    <select id="gemini_test_model" style="flex-grow:1; padding:3px 6px; font-size:11px; height: 26px;">
                                        <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                                        <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                                    </select>
                                </div>
                                <div style="display:flex; gap:6px;">
                                    <input type="text" id="gemini_test_prompt" placeholder="Tanya info real-time..." style="flex-grow:1; padding:4px; font-size:11px; height: 26px;" />
                                    <button type="button" id="btn_test_grounding" class="button button-secondary" style="padding: 2px 8px; font-size:11px; height: 26px !important; line-height: 24px !important;">Test</button>
                                </div>
                            </div>
                            <div id="gemini_test_result" style="margin-top:8px; display:none; padding:8px; background:#fff; border-left:3px solid #2271b1; font-family:monospace; font-size:11px; white-space:pre-wrap; max-height: 120px; overflow-y: auto; border: 1px solid #dcdcde;"></div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
