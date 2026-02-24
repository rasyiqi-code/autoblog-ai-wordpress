<?php
/**
 * Tab Data Sources — Gabungan Knowledge Base + Content Triggers + Mode selector.
 *
 * Tab ini menyatukan semua pengaturan sumber data pipeline:
 * 1. Data Source Mode (dropdown kontrol utama)
 * 2. Section Knowledge Base (Internal) — upload & kelola file
 * 3. Section Content Triggers (External) — RSS, Web Scraper, Search
 *
 * Section yang tidak aktif berdasarkan mode disembunyikan total (display: none).
 * Info notice ditampilkan sebagai pengganti section yang disembunyikan.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */

// ====================================================================
// HANDLER SUDAH DIPINDAHKAN
// Semua operasi mutasi (upload KB, hapus KB, tambah trigger, hapus trigger)
// ditangani oleh Admin::handle_data_source_actions() di admin_init hook.
// Hal ini diperlukan karena wp_safe_redirect() butuh header HTTP yang
// belum dikirim (sebelum output HTML dimulai).
// ====================================================================

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

// ====================================================================
// DATA RETRIEVAL
// ====================================================================
$current_mode = get_option( 'autoblog_data_source_mode', 'both' );
$knowledge_base = get_option( 'autoblog_knowledge', array() );
if ( ! is_array( $knowledge_base ) ) { $knowledge_base = array(); }
$sources = get_option( 'autoblog_sources', array() );
if ( ! is_array( $sources ) ) { $sources = array(); }
?>

<!-- ================================================================ -->
<!-- STYLE UNTUK SECTION DISABLED (grey-out berdasarkan mode)         -->
<!-- ================================================================ -->
<style>
    /* Notice untuk section yang disembunyikan */
    .autoblog-hidden-notice {
        background: #f0f6fc;
        border: 1px dashed #c3d9ed;
        border-left: 4px solid #2271b1;
        padding: 12px 16px;
        margin: 20px 0 10px;
        color: #2271b1;
        border-radius: 2px;
    }
    .autoblog-hidden-notice strong {
        display: block;
        margin-bottom: 4px;
    }
</style>

<!-- ================================================================ -->
<!-- DATA SOURCE MODE SELECTOR                                        -->
<!-- ================================================================ -->
<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>📥 Data Source Mode</h2>
    <p>Tentukan sumber data yang digunakan pipeline untuk generate artikel.</p>

    <form method="post" action="options.php">
        <?php
            settings_fields( 'autoblog_ds' );
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Mode Sumber Data</th>
                <td>
                    <select name="autoblog_data_source_mode" id="autoblog_data_source_mode">
                        <option value="both" <?php selected( $current_mode, 'both' ); ?>>🔄 Keduanya (Knowledge Base + Triggers)</option>
                        <option value="kb_only" <?php selected( $current_mode, 'kb_only' ); ?>>📚 Hanya Knowledge Base (Internal)</option>
                        <option value="triggers_only" <?php selected( $current_mode, 'triggers_only' ); ?>>🌐 Hanya Content Triggers (External)</option>
                    </select>
                    <p class="description">
                        <strong>Keduanya:</strong> Artikel dari triggers, diperkaya konteks Knowledge Base.<br>
                        <strong>Hanya KB:</strong> Artikel dibuat murni dari isi file Knowledge Base.<br>
                        <strong>Hanya Triggers:</strong> Artikel dari sumber eksternal tanpa konteks KB.
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Simpan Mode', 'primary' ); ?>
    </form>
</div>

<!-- ================================================================ -->
<!-- SECTION 1: KNOWLEDGE BASE (Internal)                             -->
<!-- ================================================================ -->
<?php
$kb_disabled = ( $current_mode === 'triggers_only' );
?>

<?php if ( $kb_disabled ) : ?>
    <div class="autoblog-hidden-notice">
        <strong>📚 Knowledge Base (Internal) — Dinonaktifkan</strong>
        Section ini disembunyikan karena Data Source Mode = "Hanya Triggers".
        Ubah mode di atas ke "Keduanya" atau "Hanya Knowledge Base" untuk mengakses fitur ini.
    </div>
<?php else : ?>

    <div id="section-knowledge-base">
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>📚 Knowledge Base (Internal)</h2>
            <p>Upload file Excel, PDF, Word, atau Text untuk diproses dan dijadikan sumber konteks RAG.</p>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'autoblog_datasource_verify' ); ?>
                <input type="file" name="autoblog_file"
                       accept=".xlsx,.csv,.pdf,.docx,.txt,.md" required />
                <?php submit_button( 'Upload & Process', 'primary', 'autoblog_upload_file' ); ?>
            </form>
        </div>

        <div class="card" style="max-width: 100%; margin-top: 10px;">
            <h3>File Tersimpan</h3>
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
                                    <span style="color: #46b450;">✅ Embedded</span>
                                <?php else : ?>
                                    <span style="color: #f0ad4e;">⏳ Menunggu Pipeline</span>
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
                        <tr><td colspan="4">Belum ada file di Knowledge Base.</td></tr>
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
    <div class="autoblog-hidden-notice">
        <strong>🌐 Content Triggers (External) — Dinonaktifkan</strong>
        Section ini disembunyikan karena Data Source Mode = "Hanya Knowledge Base".
        Ubah mode di atas ke "Keduanya" atau "Hanya Triggers" untuk mengakses fitur ini.
    </div>
<?php else : ?>

    <div id="section-content-triggers">
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>🌐 Content Triggers (External)</h2>
            <p>Tambahkan sumber konten eksternal: RSS Feed, Web Scraper, atau Web Search.</p>

            <form method="post" action="">
                <?php wp_nonce_field( 'autoblog_datasource_verify' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Source Type</th>
                        <td>
                            <select name="source_type" id="autoblog_source_type">
                                <option value="rss">RSS Feed</option>
                                <option value="web">Web Scraper</option>
                                <option value="web_search">Web Search (SerpApi/Brave)</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top" id="row_url">
                        <th scope="row" id="label_url">URL</th>
                        <td>
                            <input type="text" name="source_url" id="input_url"
                                   class="regular-text" required
                                   placeholder="https://site1.com/feed, https://site2.com/feed" />
                            <p class="description" id="desc_url">Bisa memasukkan beberapa URL dipisah koma.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Match Keywords (Opsional)</th>
                        <td>
                            <input type="text" name="match_keywords" class="regular-text"
                                   placeholder="contoh: AI, WordPress, coding" />
                            <p class="description">Hanya proses artikel yang mengandung kata-kata ini (pisah koma).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Negative Keywords (Opsional)</th>
                        <td>
                            <input type="text" name="negative_keywords" class="regular-text"
                                   placeholder="contoh: promo, sponsored, gambling" />
                            <p class="description">Abaikan artikel yang mengandung kata-kata ini (pisah koma).</p>
                        </td>
                    </tr>
                    <tr valign="top" id="row_selector" style="display:none;">
                        <th scope="row">CSS Selector</th>
                        <td>
                            <input type="text" name="source_selector" class="regular-text"
                                   placeholder="article.content atau #main" />
                            <p class="description">Wajib untuk Web Scraper. Target container konten.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Tambah Source', 'primary', 'autoblog_add_source' ); ?>
            </form>
        </div>

        <!-- Tabel daftar triggers -->
        <div class="card" style="max-width: 100%; margin-top: 10px;">
            <h3>Triggers Terdaftar</h3>
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
                                        <span style="color:red;"><strong>Exclude:</strong> <?php echo esc_html($source['negative_keywords']); ?></span>
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
                        <tr><td colspan="5">Belum ada trigger yang dikonfigurasi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- ================================================================ -->
<!-- JAVASCRIPT: Toggle label & selector berdasarkan source type      -->
<!-- ================================================================ -->
<script>
    jQuery(document).ready(function($) {
        /**
         * Toggle tampilan row CSS Selector dan label URL/Query
         * berdasarkan tipe source yang dipilih.
         */
        $('#autoblog_source_type').change(function() {
            var type = $(this).val();

            if (type === 'web') {
                $('#row_selector').show();
                $('#label_url').text('URL');
                $('#input_url').attr('placeholder', 'https://site1.com, https://site2.com');
                $('#desc_url').text('Masukkan URL untuk di-scrape.');
            } else if (type === 'web_search') {
                $('#row_selector').hide();
                $('#label_url').text('Search Query');
                $('#input_url').attr('placeholder', 'latest AI trends, wordpress tips');
                $('#desc_url').text('Masukkan query pencarian (pisah koma). Menggunakan SerpApi/Brave sesuai pengaturan AI Engine.');
            } else {
                // Default: RSS
                $('#row_selector').hide();
                $('#label_url').text('URL');
                $('#input_url').attr('placeholder', 'https://site1.com/feed, https://site2.com/feed');
                $('#desc_url').text('Masukkan URL RSS Feed.');
            }
        });
    });
</script>
