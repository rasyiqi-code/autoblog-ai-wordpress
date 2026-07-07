<?php
/**
 * Main admin display — Halaman utama settings plugin Autoblog.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */
?>

<style>
/* CSS Tokens & Custom Stylesheet */
.autoblog-settings-wrapper {
    --primary: #2271b1;
    --primary-hover: #135e96;
    --bg-main: #f8fafc;
    --border-color: #ccd0d4;
    --text-main: #1d2327;
    --text-muted: #646970;
    max-width: 1100px;
    margin: 10px 0;
}
.autoblog-header-container {
    background: #ffffff;
    padding: 16px 20px 0 20px;
    border-radius: 6px 6px 0 0;
    border: 1px solid var(--border-color);
    border-bottom: none;
}
.autoblog-header-container h1 {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 12px 0;
    color: var(--text-main);
}
/* Nav Tabs Modern Native */
.nav-tab-wrapper {
    border-bottom: 1px solid var(--border-color);
    margin: 0;
    padding: 0;
}
.nav-tab-wrapper a.nav-tab {
    font-size: 13px;
    font-weight: 600;
    padding: 6px 12px 8px;
    margin-right: 4px;
}
/* Tab Content Box */
.tab-content {
    background: #ffffff;
    padding: 16px 20px;
    border-radius: 0 0 6px 6px;
    border: 1px solid var(--border-color);
}
/* Compact Cards */
.autoblog-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 15px;
}
.autoblog-card h2 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 4px 0;
    color: var(--text-main);
}
.autoblog-card p.description {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0 0 12px 0;
}
/* WordPress Native form-table Compact Tuning */
.autoblog-settings-wrapper .form-table {
    margin: 0;
}
.autoblog-settings-wrapper .form-table th {
    width: 220px;
    padding: 10px 10px 10px 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-main);
    vertical-align: middle;
}
.autoblog-settings-wrapper .form-table td {
    padding: 8px 0;
    vertical-align: middle;
}
.autoblog-settings-wrapper .form-table td select,
.autoblog-settings-wrapper .form-table td input[type=text],
.autoblog-settings-wrapper .form-table td input[type=password] {
    max-width: 380px;
    width: 100%;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 13px;
    background: #f8fafc;
}
.autoblog-settings-wrapper .form-table td select:focus,
.autoblog-settings-wrapper .form-table td input:focus {
    border-color: var(--primary);
    background: #ffffff;
    box-shadow: 0 0 0 1px var(--primary);
    outline: none;
}
.autoblog-settings-wrapper .form-table td p.description {
    margin: 4px 0 0 0;
    font-size: 11px;
    color: var(--text-muted);
}
.autoblog-settings-wrapper hr {
    border: 0;
    border-top: 1px solid #f0f0f1;
    margin: 10px 0;
}
/* Command Center Layout */
.command-center-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 15px;
    margin-bottom: 15px;
}
@media (max-width: 900px) {
    .command-center-grid {
        grid-template-columns: 1fr;
    }
}
/* Overrides Compact List */
.overrides-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.overrides-list label {
    font-size: 12px;
    display: flex;
    align-items: center;
}
</style>

<div class="wrap autoblog-settings-wrapper">
    <div class="autoblog-header-container">
        <h1>Autoblog AI Settings</h1>

        <?php
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'api_keys';
        ?>

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

    <div class="tab-content">
        <?php
        switch ( $active_tab ) {

            case 'data_sources':
                require_once 'autoblog-admin-data-sources.php';
                break;

            case 'ai_engine':
                ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'autoblog_ai' ); ?>
                    <div class="autoblog-card">
                        <h2>🤖 AI Engine & Model Settings</h2>
                        <p class="description">Atur provider utama, model, embedding, dan media generator artikel Anda.</p>
                        <?php require_once 'autoblog-admin-ai-engine.php'; ?>
                    </div>
                    <?php submit_button('Simpan Pengaturan', 'primary'); ?>
                </form>
                <?php
                break;

            case 'writing_style':
                require_once 'autoblog-admin-personas.php';
                break;

            case 'advanced':
                require_once 'autoblog-admin-advanced.php';
                break;

            case 'tools':
                // Rombak ke Agentic Command Center
                ?>
                <div class="command-center-grid">
                    <!-- Kolom Kiri: Visual Agent Flow Diagram -->
                    <?php require_once 'autoblog-admin-logs.php'; ?>

                    <!-- Kolom Kanan: Quick Controls & Overrides -->
                    <div class="autoblog-card" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <h2 style="margin-bottom:12px;">⚡ Overrides & Quick Actions</h2>
                            <?php
                            $get_global_status = function( $option_name ) {
                                $is_active = ( get_option( $option_name, '0' ) === '1' );
                                if ( $is_active ) {
                                    return ' <span style="color: #46b450; font-size: 10px; font-weight: bold;">[Aktif]</span>';
                                }
                                return ' <span style="color: #64748b; font-size: 10px;">[Mati]</span>';
                            };
                            ?>
                            <ul class="overrides-list">
                                <li><label><input type="checkbox" class="autoblog-override" data-feature="dynamic_search" <?php checked( get_option('autoblog_enable_dynamic_search'), true ); ?>> <span style="margin-left:5px;">🔍 Brainstorm Query <?php echo $get_global_status('autoblog_enable_dynamic_search'); ?></span></label></li>
                                <li><label><input type="checkbox" class="autoblog-override" data-feature="deep_research" <?php checked( get_option('autoblog_enable_deep_research'), true ); ?>> <span style="margin-left:5px;">🧠 Deep Research <?php echo $get_global_status('autoblog_enable_deep_research'); ?></span></label></li>
                                <li><label><input type="checkbox" class="autoblog-override" data-feature="interlinking" <?php checked( get_option('autoblog_enable_interlinking'), true ); ?>> <span style="margin-left:5px;">🔗 Auto Interlinking <?php echo $get_global_status('autoblog_enable_interlinking'); ?></span></label></li>
                                <li><label><input type="checkbox" class="autoblog-override" data-feature="multi_modal" <?php checked( get_option('autoblog_enable_multimodal'), true ); ?>> <span style="margin-left:5px;">📊 Multi-Modal Charts <?php echo $get_global_status('autoblog_enable_multimodal'); ?></span></label></li>
                                <li><label><input type="checkbox" class="autoblog-override" data-feature="living_content" <?php checked( get_option('autoblog_enable_living_content'), true ); ?>> <span style="margin-left:5px;">🔄 Content Refresh <?php echo $get_global_status('autoblog_enable_living_content'); ?></span></label></li>
                            </ul>
                        </div>
                        
                        <div style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                            <input type="button" id="autoblog-run-now-btn" class="button button-primary button-hero" value="▶ Picu Pipeline Sekarang" style="width:100%; text-align:center; font-weight:700;">
                            <div id="autoblog-run-status" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Cron Schedule Settings (Compact Native Form) -->
                <div class="autoblog-card">
                    <h2>📅 Penjadwalan Otomatis (Cron Job)</h2>
                    <p class="description">Atur frekuensi background runner untuk publikasi & pembaruan otomatis.</p>
                    <form method="post" action="options.php">
                        <?php settings_fields( 'autoblog_ops' ); ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Cron Job Interval</th>
                                <td>
                                    <select name="autoblog_cron_schedule">
                                        <option value="hourly" <?php selected( get_option('autoblog_cron_schedule'), 'hourly' ); ?>>Setiap Jam (Hourly)</option>
                                        <option value="twicedaily" <?php selected( get_option('autoblog_cron_schedule'), 'twicedaily' ); ?>>Dua Kali Sehari</option>
                                        <option value="daily" <?php selected( get_option('autoblog_cron_schedule'), 'daily' ); ?>>Sekali Sehari (Daily)</option>
                                        <option value="weekly" <?php selected( get_option('autoblog_cron_schedule'), 'weekly' ); ?>>Setiap Minggu (Weekly)</option>
                                        <option value="monthly" <?php selected( get_option('autoblog_cron_schedule'), 'monthly' ); ?>>Setiap Bulan (Monthly)</option>
                                    </select>
                                    <p class="description">Frekuensi pemicuan pipeline untuk mempublikasikan artikel baru.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Content Refresh Interval</th>
                                <td>
                                    <select name="autoblog_refresh_schedule">
                                        <option value="daily" <?php selected( get_option('autoblog_refresh_schedule', 'daily'), 'daily' ); ?>>Sekali Sehari (Rekomendasi)</option>
                                        <option value="twicedaily" <?php selected( get_option('autoblog_refresh_schedule'), 'twicedaily' ); ?>>Dua Kali Sehari</option>
                                        <option value="hourly" <?php selected( get_option('autoblog_refresh_schedule'), 'hourly' ); ?>>Setiap Jam</option>
                                        <option value="weekly" <?php selected( get_option('autoblog_refresh_schedule'), 'weekly' ); ?>>Setiap Minggu</option>
                                        <option value="monthly" <?php selected( get_option('autoblog_refresh_schedule'), 'monthly' ); ?>>Setiap Bulan</option>
                                    </select>
                                    <p class="description">Seberapa sering sistem mencari artikel lama untuk diperbarui (Living Content).</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Default Post Status</th>
                                <td>
                                    <select name="autoblog_post_status">
                                        <option value="draft" <?php selected( get_option('autoblog_post_status', 'draft'), 'draft' ); ?>>Draft (Sangat Aman)</option>
                                        <option value="publish" <?php selected( get_option('autoblog_post_status'), 'publish' ); ?>>Published (Langsung Terbit)</option>
                                    </select>
                                    <p class="description">Status awal postingan baru yang dibuat otomatis oleh AI.</p>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top: 15px; border-top:1px solid #f0f0f1; padding-top:15px;">
                            <?php submit_button('Simpan Jadwal', 'secondary'); ?>
                        </div>
                    </form>
                </div>
                <?php
                break;

            case 'api_keys':
            default:
                ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'autoblog_keys' ); ?>
                    <div class="autoblog-card">
                        <h2>🔑 API Credentials</h2>
                        <p class="description">Masukkan kredensial API untuk pencarian web, stock image, dan model LLM kustom.</p>
                        <?php require_once 'autoblog-admin-api-keys.php'; ?>
                    </div>
                    <?php submit_button('Simpan Kredensial', 'primary'); ?>
                </form>
                <?php
                break;
        }
        ?>
    </div>
</div>
