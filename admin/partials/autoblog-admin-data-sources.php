<?php
/**
 * Tab Data Sources — Gabungan Knowledge Base + Content Triggers + Mode selector (Native WP style)
 *
 * Tab ini menyatukan semua pengaturan sumber data pipeline dengan kelas CSS bawaan WordPress.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */

// --- Tampilkan notice dari transient (setelah redirect) ---
$success_notice = get_transient( 'autoblog_admin_notice' );
if ( $success_notice ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success_notice ) . '</p></div>';
    delete_transient( 'autoblog_admin_notice' );
}
$error_notice = get_transient( 'autoblog_admin_notice_error' );
if ( $error_notice ) {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_notice ) . '</p></div>';
    delete_transient( 'autoblog_admin_notice_error' );
}

$current_mode = get_option( 'autoblog_data_source_mode', 'both' );
$knowledge_base = get_option( 'autoblog_knowledge', array() );
if ( ! is_array( $knowledge_base ) ) { $knowledge_base = array(); }
$sources = get_option( 'autoblog_sources', array() );
if ( ! is_array( $sources ) ) { $sources = array(); }
?>

<!-- ================================================================ -->
<!-- DATA SOURCE MODE SELECTOR                                        -->
<!-- ================================================================ -->
<div class="postbox">
    <div class="postbox-header">
        <h2 class="hndle">📥 Data Source Mode & settings</h2>
    </div>
    <div class="inside">
        <p class="description">Tentukan sumber data dan pencarian web untuk pipeline artikel.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'autoblog_ds' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Mode Sumber Data</th>
                    <td>
                        <select name="autoblog_data_source_mode" id="autoblog_data_source_mode">
                            <option value="both" <?php selected( $current_mode, 'both' ); ?>>🔄 Keduanya (Knowledge Base + Triggers)</option>
                            <option value="kb_only" <?php selected( $current_mode, 'kb_only' ); ?>>📚 Hanya KB (Internal)</option>
                            <option value="triggers_only" <?php selected( $current_mode, 'triggers_only' ); ?>>🌐 Hanya Triggers (External)</option>
                        </select>
                        <p class="description">Keduanya = Triggers + Konteks KB. Hanya KB = murni file RAG. Hanya Triggers = eksternal feed saja.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Default Search Provider</th>
                    <td>
                        <select name="autoblog_search_provider">
                            <option value="duckduckgo_free" <?php selected( get_option('autoblog_search_provider', 'serpapi'), 'duckduckgo_free' ); ?>>DuckDuckGo (Free / No API Key)</option>
                            <option value="serpapi" <?php selected( get_option('autoblog_search_provider', 'serpapi'), 'serpapi' ); ?>>SerpApi (Google AI/Bing Copilot)</option>
                        </select>
                        <p class="description">Provider pencarian web untuk trigger Web Search dan Deep Research.</p>
                    </td>
                </tr>
            </table>
            <div style="margin-top: 15px; border-top:1px solid #dcdcde; padding-top:12px;">
                <?php submit_button( 'Simpan Pengaturan', 'primary', 'submit', false ); ?>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- SECTION 1: KNOWLEDGE BASE (Internal)                             -->
<!-- ================================================================ -->
<?php
$kb_disabled = ( $current_mode === 'triggers_only' );
?>

<?php if ( $kb_disabled ) : ?>
    <div class="notice notice-info inline">
        <p><strong>📚 Knowledge Base (Internal) — Dinonaktifkan</strong><br>
        Section ini disembunyikan karena Data Source Mode = "Hanya Triggers". Ubah mode di atas ke "Keduanya" atau "Hanya Knowledge Base" untuk mengakses fitur ini.</p>
    </div>
<?php else : ?>

    <div id="section-knowledge-base" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">📚 Knowledge Base (Internal)</h2>
        </div>
        <div class="inside">
            <p class="description">Upload file Excel, PDF, Word, atau Text untuk diproses dan dijadikan sumber konteks RAG.</p>
            <form method="post" enctype="multipart/form-data" style="margin-bottom: 25px;">
                <?php wp_nonce_field( 'autoblog_datasource_verify' ); ?>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <input type="file" name="autoblog_file" accept=".xlsx,.csv,.pdf,.docx,.txt,.md" required />
                    <?php submit_button( 'Upload & Process', 'primary', 'autoblog_upload_file', false ); ?>
                </div>
            </form>

            <hr style="margin: 20px 0;">
            <h3 style="font-size:14px; font-weight:600; margin-bottom:10px;">File Tersimpan</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nama File</th>
                        <th>Tanggal Ditambahkan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $knowledge_base ) ) : ?>
                        <?php foreach ( $knowledge_base as $index => $item ) : ?>
                        <tr>
                            <td><?php echo esc_html( isset($item['name']) ? $item['name'] : basename($item['path']) ); ?></td>
                            <td><?php echo esc_html( isset($item['date']) ? $item['date'] : '-' ); ?></td>
                            <td>
                                <?php if ( isset($item['embedded']) && $item['embedded'] ) : ?>
                                    <span style="color: #46b450; font-weight:600;">✅ Embedded</span>
                                <?php else : ?>
                                    <span style="color: #f0ad4e; font-weight:600;">⏳ Menunggu Pipeline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo wp_nonce_url( '?page=autoblog&tab=data_sources&delete_kb=' . $index, 'autoblog_delete_kb' ); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('Hapus file ini dari Knowledge Base?')">Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4" style="color:#646970; font-style:italic; text-align: center;">Belum ada file di Knowledge Base.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- ================================================================ -->
<!-- SECTION 2: CONTENT TRIGGERS (External)                           -->
<!-- ================================================================ -->
<?php
$triggers_disabled = ( $current_mode === 'kb_only' );
?>

<?php if ( $triggers_disabled ) : ?>
    <div class="notice notice-info inline" style="margin-top: 20px;">
        <p><strong>🌐 Content Triggers (External) — Dinonaktifkan</strong><br>
        Section ini disembunyikan karena Data Source Mode = "Hanya Knowledge Base". Ubah mode di atas ke "Keduanya" atau "Hanya Triggers" untuk mengakses fitur ini.</p>
    </div>
<?php else : ?>

    <div id="section-content-triggers" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">🌐 Content Triggers (External)</h2>
        </div>
        <div class="inside">
            <p class="description">Tambahkan sumber konten eksternal: RSS Feed, Web Scraper, atau Web Search.</p>
            <form method="post" action="" style="margin-bottom: 25px;">
                <?php wp_nonce_field( 'autoblog_datasource_verify' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Source Type</th>
                        <td>
                            <select name="source_type" id="autoblog_source_type">
                                <option value="rss">RSS Feed</option>
                                <option value="web">Web Scraper</option>
                                <option value="web_search">Web Search (DuckDuckGo/SerpApi)</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top" id="row_url">
                        <th scope="row" id="label_url">URL / Query</th>
                        <td>
                            <input type="text" name="source_url" id="input_url" class="regular-text" required placeholder="https://site1.com/feed, https://site2.com/feed" />
                            <p class="description" id="desc_url">Masukkan URL RSS Feed.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Match Keywords (Opsional)</th>
                        <td>
                            <input type="text" name="match_keywords" class="regular-text" placeholder="AI, WordPress (pisahkan koma)" />
                            <p class="description">Hanya proses artikel yang mengandung salah satu kata kunci di atas.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Negative Keywords (Opsional)</th>
                        <td>
                            <input type="text" name="negative_keywords" class="regular-text" placeholder="promo, sponsored (pisahkan koma)" />
                            <p class="description">Abaikan artikel yang mengandung salah satu kata kunci di atas.</p>
                        </td>
                    </tr>
                    <tr valign="top" id="row_selector" style="display:none;">
                        <th scope="row">CSS Selector</th>
                        <td>
                            <input type="text" name="source_selector" class="regular-text" placeholder="article.content atau #main" />
                            <p class="description">Wajib untuk Web Scraper. Target container konten.</p>
                        </td>
                    </tr>
                </table>
                <div style="margin-top: 15px; border-top:1px solid #dcdcde; padding-top:12px;">
                    <?php submit_button( 'Tambah Source', 'primary', 'autoblog_add_source', false ); ?>
                </div>
            </form>

            <hr style="margin: 20px 0;">
            <h3 style="font-size:14px; font-weight:600; margin-bottom:10px;">Triggers Terdaftar</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>URL / Query</th>
                        <th>Filters</th>
                        <th>Selector</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $sources ) ) : ?>
                        <?php foreach ( $sources as $index => $source ) : ?>
                            <tr>
                                <td><?php echo esc_html( strtoupper( $source['type'] ) ); ?></td>
                                <td><?php echo esc_html( $source['url'] ); ?></td>
                                <td>
                                    <?php if(!empty($source['match_keywords'])): ?>
                                        <strong>Include:</strong> <?php echo esc_html($source['match_keywords']); ?><br>
                                    <?php endif; ?>
                                    <?php if(!empty($source['negative_keywords'])): ?>
                                        <span style="color:#d63638;"><strong>Exclude:</strong> <?php echo esc_html($source['negative_keywords']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( isset( $source['selector'] ) ? $source['selector'] : '-' ); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url( '?page=autoblog&tab=data_sources&autoblog_delete_source=' . $index, 'autoblog_delete_source' ); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Hapus source ini?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5" style="color:#646970; font-style:italic; text-align: center;">Belum ada trigger yang dikonfigurasi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
