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

<div class="wrap autoblog-settings-wrapper">
    <div class="autoblog-header-container">
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
    </div>

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
                    
                    <div style="background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px;">
                        <p style="margin-top:0;"><strong>Opsi Cepat (Overrides):</strong></p>
                        <ul style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 10px;">
                            <li><label><input type="checkbox" class="autoblog-override" data-feature="dynamic_search" <?php checked( get_option('autoblog_enable_dynamic_search'), true ); ?>> <span style="margin-left:5px;">🔍 Dynamic Search Agent (Brainstorm Query)</span></label></li>
                            <li><label><input type="checkbox" class="autoblog-override" data-feature="deep_research" <?php checked( get_option('autoblog_enable_deep_research'), true ); ?>> <span style="margin-left:5px;">🧠 Deep Research Agent (Multi-hop Search)</span></label></li>
                            <li><label><input type="checkbox" class="autoblog-override" data-feature="interlinking" <?php checked( get_option('autoblog_enable_interlinking'), true ); ?>> <span style="margin-left:5px;">🔗 Autonomous Interlinking (Smart Linking)</span></label></li>
                            <li><label><input type="checkbox" class="autoblog-override" data-feature="multi_modal" <?php checked( get_option('autoblog_enable_charts'), true ); ?>> <span style="margin-left:5px;">📊 Multi-Modal Content (Charts & Embeds)</span></label></li>
                            <li><label><input type="checkbox" class="autoblog-override" data-feature="living_content" <?php checked( get_option('autoblog_enable_living_content'), true ); ?>> <span style="margin-left:5px;">🔄 Living Content Refresh (Update 1 stale post)</span></label></li>
                        </ul>
                        <p class="description" style="margin-top: 15px; margin-bottom:0;">Catatan: Centang untuk mengaktifkan fitur tersebut pada pemicuan manual ini (tidak merubah pengaturan global).</p>
                    </div>

                    <input type="button" id="autoblog-run-now-btn" class="button button-primary" value="▶ Run Now">
                    <div id="autoblog-run-status" style="margin-top: 15px;"></div>
                </div>

                <!-- Cron Schedule (dipindah dari General) -->
                <div class="card" style="max-width: 100%; margin-top: 30px;">
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
                                        <option value="weekly" <?php selected( get_option('autoblog_cron_schedule'), 'weekly' ); ?>>Weekly</option>
                                        <option value="monthly" <?php selected( get_option('autoblog_cron_schedule'), 'monthly' ); ?>>Monthly</option>
                                    </select>
                                    <p class="description">Seberapa sering pipeline dijalankan untuk membuat artikel baru.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Living Content Schedule</th>
                                <td>
                                    <select name="autoblog_refresh_schedule">
                                        <option value="daily" <?php selected( get_option('autoblog_refresh_schedule', 'daily'), 'daily' ); ?>>Daily (Recommended)</option>
                                        <option value="twicedaily" <?php selected( get_option('autoblog_refresh_schedule'), 'twicedaily' ); ?>>Twice Daily</option>
                                        <option value="hourly" <?php selected( get_option('autoblog_refresh_schedule'), 'hourly' ); ?>>Hourly (High Resource)</option>
                                        <option value="weekly" <?php selected( get_option('autoblog_refresh_schedule'), 'weekly' ); ?>>Weekly</option>
                                        <option value="monthly" <?php selected( get_option('autoblog_refresh_schedule'), 'monthly' ); ?>>Monthly</option>
                                    </select>
                                    <p class="description">Seberapa sering sistem mencari artikel lama untuk diperbarui (Living Content).</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Default Post Status</th>
                                <td>
                                    <select name="autoblog_post_status">
                                        <option value="draft" <?php selected( get_option('autoblog_post_status', 'draft'), 'draft' ); ?>>Draft (Safe Mode)</option>
                                        <option value="publish" <?php selected( get_option('autoblog_post_status'), 'publish' ); ?>>Published (Live)</option>
                                    </select>
                                    <p class="description">Status awal artikel saat berhasil dibuat atau diperbarui.</p>
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
