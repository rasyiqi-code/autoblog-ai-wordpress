<?php
/**
 * Main admin display — Halaman utama settings plugin Autoblog.
 *
 * File ini mengelola navigasi tab dan merender konten setiap tab.
 * Tab disusun mengikuti alur pipeline:
 * API Keys → Data Sources → AI Engine → Writing Style → Advanced → Tools & Logs
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */
?>

<div class="wrap">
    <h1>Autoblog AI Settings</h1>

    <?php
    // Tab aktif, default ke 'api_keys'
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'api_keys';
    ?>

    <!-- ============================================================ -->
    <!-- TAB NAVIGATION — Urutan mengikuti alur pipeline               -->
    <!-- ============================================================ -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=autoblog&tab=api_keys"
           class="nav-tab <?php echo $active_tab == 'api_keys' ? 'nav-tab-active' : ''; ?>">
            🔑 API Keys
        </a>
        <a href="?page=autoblog&tab=data_sources"
           class="nav-tab <?php echo $active_tab == 'data_sources' ? 'nav-tab-active' : ''; ?>">
            📥 Data Sources
        </a>
        <a href="?page=autoblog&tab=ai_engine"
           class="nav-tab <?php echo $active_tab == 'ai_engine' ? 'nav-tab-active' : ''; ?>">
            🤖 AI Engine
        </a>
        <a href="?page=autoblog&tab=writing_style"
           class="nav-tab <?php echo $active_tab == 'writing_style' ? 'nav-tab-active' : ''; ?>">
            ✍️ Writing Style
        </a>
        <a href="?page=autoblog&tab=advanced"
           class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">
            ⚡ Advanced
        </a>
        <a href="?page=autoblog&tab=tools"
           class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">
            🛠️ Tools & Logs
        </a>
    </h2>

    <!-- ============================================================ -->
    <!-- TAB CONTENT — Konten setiap tab dirender via require_once     -->
    <!-- ============================================================ -->
    <div class="tab-content">
        <?php
        switch ( $active_tab ) {

            // --- Tab: Data Sources (gabungan KB + Triggers + Mode) ---
            case 'data_sources':
                require_once 'autoblog-admin-data-sources.php';
                break;

            // --- Tab: AI Engine (Provider, Model, Embedding, Search, Fallback) ---
            case 'ai_engine':
                ?>
                <form method="post" action="options.php">
                    <?php
                        settings_fields( 'autoblog_ai' );
                    ?>
                    <?php require_once 'autoblog-admin-ai-engine.php'; ?>
                    <?php submit_button(); ?>
                </form>
                <?php
                break;

            // --- Tab: Writing Style (Personas + Personality Samples) ---
            case 'writing_style':
                require_once 'autoblog-admin-personas.php';
                break;

            // --- Tab: Advanced (Deep Research, Interlinking, dll) ---
            case 'advanced':
                require_once 'autoblog-admin-advanced.php';
                break;

            // --- Tab: Tools & Logs (Run Now + Cron + System Logs) ---
            case 'tools':
                ?>
                <!-- Manual Trigger (AJAX — tanpa reload halaman) -->
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2>Manual Trigger</h2>
                    <p>Jalankan seluruh pipeline autoblog secara manual.</p>
                    <input type="button" id="autoblog-run-now-btn" class="button button-primary" value="▶ Run Now">
                    <div id="autoblog-run-status" style="margin-top: 10px;"></div>
                </div>

                <!-- Cron Schedule (dipindah dari General) -->
                <div class="card" style="max-width: 100%; margin-top: 10px;">
                    <h2>Jadwal Otomatis (Cron)</h2>
                    <form method="post" action="options.php">
                        <?php
                            settings_fields( 'autoblog_ops' );
                        ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Cron Schedule</th>
                                <td>
                                    <select name="autoblog_cron_schedule">
                                        <option value="hourly" <?php selected( get_option('autoblog_cron_schedule'), 'hourly' ); ?>>Hourly</option>
                                        <option value="twicedaily" <?php selected( get_option('autoblog_cron_schedule'), 'twicedaily' ); ?>>Twice Daily</option>
                                        <option value="daily" <?php selected( get_option('autoblog_cron_schedule'), 'daily' ); ?>>Daily</option>
                                    </select>
                                    <p class="description">Seberapa sering pipeline dijalankan secara otomatis.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( 'Simpan Jadwal' ); ?>
                    </form>
                </div>

                <?php
                // System Logs
                require_once 'autoblog-admin-logs.php';
                break;

            // --- Tab: API Keys (default saat pertama buka) ---
            case 'api_keys':
            default:
                ?>
                <form method="post" action="options.php">
                    <?php
                        settings_fields( 'autoblog_keys' );
                    ?>
                    <?php require_once 'autoblog-admin-api-keys.php'; ?>
                    <?php submit_button(); ?>
                </form>
                <?php
                break;
        }
        ?>
    </div>
</div>
