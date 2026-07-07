<?php
/**
 * Main admin display — Halaman utama settings plugin Autoblog.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */
?>

<style>
/* Tuning Compact untuk Native WordPress form-table */
.autoblog-settings-form {
    margin-top: 15px;
}
.autoblog-settings-form h2 {
    font-size: 15px;
    font-weight: 600;
    margin: 20px 0 5px 0;
    color: #1d2327;
}
.autoblog-settings-form p.description {
    font-size: 12px;
    color: #646970;
    margin: 0 0 15px 0;
}
.autoblog-settings-form .form-table th {
    width: 220px;
    padding: 12px 10px 12px 0;
    font-size: 13px;
    font-weight: 600;
    color: #1d2327;
}
.autoblog-settings-form .form-table td {
    padding: 10px 0;
    vertical-align: middle;
}
.autoblog-settings-form .form-table td select,
.autoblog-settings-form .form-table td input[type=text],
.autoblog-settings-form .form-table td input[type=password] {
    max-width: 400px;
    width: 100%;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    padding: 5px 8px;
    font-size: 13px;
    background: #fff;
}
.autoblog-settings-form .form-table td select:focus,
.autoblog-settings-form .form-table td input:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}
.autoblog-settings-form .form-table td p.description {
    margin: 4px 0 0 0;
    font-size: 11px;
    color: #646970;
}
.autoblog-settings-form hr {
    border: 0;
    border-top: 1px solid #dcdcde;
    margin: 15px 0;
}
/* Command Center Layout */
.command-center-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 15px;
    margin-top: 15px;
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

<div class="wrap">
    <h1>Autoblog AI Settings</h1>
    <hr class="wp-header-end">

    <?php
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'api_keys';
    ?>

    <nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu" style="margin-bottom: 15px;">
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
    </nav>

    <div class="autoblog-settings-form">
        <?php
        switch ( $active_tab ) {

            case 'data_sources':
                require_once 'autoblog-admin-data-sources.php';
                break;

            case 'ai_engine':
                ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'autoblog_ai' ); ?>
                    <h2>🤖 AI Engine & Model Settings</h2>
                    <p class="description">Atur provider utama, model, embedding, dan media generator artikel Anda.</p>
                    <?php require_once 'autoblog-admin-ai-engine.php'; ?>
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
                ?>
                <div class="command-center-grid">
                    <!-- Kolom Kiri: Visual Agent Flow Diagram -->
                    <?php require_once 'autoblog-admin-logs.php'; ?>

                    <!-- Kolom Kanan: Quick Controls & Overrides -->
                    <div style="background: #ffffff; border: 1px solid #ccd0d4; padding: 15px; display:flex; flex-direction:column; justify-content:space-between; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                        <div>
                            <h2 style="margin: 0 0 12px 0; font-size: 14px; font-weight:600;">⚡ Overrides & Quick Actions</h2>
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
                        
                        <div style="margin-top: 15px; border-top: 1px solid #dcdcde; padding-top: 15px;">
                            <input type="button" id="autoblog-run-now-btn" class="button button-primary button-hero" value="▶ Picu Pipeline Sekarang" style="width:100%; text-align:center; font-weight:700;">
                            <div id="autoblog-run-status" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 25px; background: #ffffff; border: 1px solid #ccd0d4; padding: 15px 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2 style="margin:0 0 5px 0;">📅 Penjadwalan Otomatis (Cron Job)</h2>
                    <p class="description" style="margin:0 0 15px 0;">Atur frekuensi background runner untuk publikasi & pembaruan otomatis.</p>
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
                        <div style="margin-top: 15px; border-top:1px solid #dcdcde; padding-top:15px;">
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
                    <h2>🔑 API Credentials</h2>
                    <p class="description" style="margin-bottom: 20px;">Masukkan kredensial API untuk pencarian web, stock image, dan model LLM kustom.</p>
                    <?php require_once 'autoblog-admin-api-keys.php'; ?>
                    <?php submit_button('Simpan Kredensial', 'primary'); ?>
                </form>
                <?php
                break;
        }
        ?>
    </div>
</div>
