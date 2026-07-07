<div class="wrap autoblog-settings-wrap">
    <style>
        /* Custom Compact Form Sizing */
        .autoblog-settings-wrap .autoblog-input,
        .autoblog-settings-wrap .autoblog-select,
        .autoblog-settings-wrap .autoblog-textarea {
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
        .autoblog-settings-wrap .autoblog-textarea {
            height: 30px;
            resize: none;
            overflow-y: auto;
        }
        .autoblog-settings-wrap .autoblog-textarea:focus {
            height: 60px;
            resize: vertical;
        }
        
        .autoblog-settings-wrap .autoblog-input:focus,
        .autoblog-settings-wrap .autoblog-select:focus,
        .autoblog-settings-wrap .autoblog-textarea:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: 2px solid transparent;
        }
        
        /* Custom Button Sizing */
        .autoblog-settings-wrap .autoblog-btn {
            height: 30px;
            line-height: 28px;
            padding: 0 12px;
            font-size: 12px;
            margin: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            vertical-align: middle;
            border: 1px solid #2271b1;
            border-radius: 3px;
            background: #f6f7f7;
            color: #2271b1;
            cursor: pointer;
            text-decoration: none;
        }
        .autoblog-settings-wrap .autoblog-btn:hover {
            background: #f0f6fc;
            border-color: #0a4b78;
            color: #0a4b78;
        }
        .autoblog-settings-wrap .autoblog-btn-primary {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }
        .autoblog-settings-wrap .autoblog-btn-primary:hover {
            background: #135e96;
            border-color: #135e96;
            color: #fff;
        }
        .autoblog-settings-wrap .autoblog-btn-danger {
            border-color: #d63638;
            color: #d63638;
            background: #fff;
        }
        .autoblog-settings-wrap .autoblog-btn-danger:hover {
            background: #fcf2f2;
            border-color: #b32d2e;
            color: #b32d2e;
        }
        .autoblog-settings-wrap .autoblog-btn-small {
            height: 24px;
            line-height: 22px;
            padding: 0 8px;
            font-size: 11px;
        }
        
        /* Custom Table (Grid Compact) */
        .autoblog-settings-wrap .autoblog-table {
            width: 100%;
            border: 1px solid #c3c4c7;
            border-collapse: collapse;
            margin-top: 12px;
            background: #fff;
        }
        .autoblog-settings-wrap .autoblog-table th,
        .autoblog-settings-wrap .autoblog-table td {
            padding: 6px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f1;
            border-top: none;
            text-align: left;
        }
        .autoblog-settings-wrap .autoblog-table thead th {
            background-color: #f6f7f7;
            font-weight: 700;
            font-size: 12px;
            color: #2c3338;
            border-bottom: 2px solid #c3c4c7;
            padding: 6px 10px;
        }
        .autoblog-settings-wrap .autoblog-table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        /* WordPress Form Table Tweaks (Tinggi & padding sel) */
        .autoblog-settings-wrap .form-table th {
            width: 200px;
            padding: 10px 10px 10px 0;
            font-weight: 600;
            font-size: 13px;
            vertical-align: middle;
        }
        .autoblog-settings-wrap .form-table td {
            padding: 10px 0;
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
    <h1>Autoblog AI Settings</h1>
    <hr class="wp-header-end">

    <?php
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'api_keys';
    ?>

    <nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu" style="margin-bottom: 15px;">
        <a href="?page=autoblog&tab=api_keys"
           class="nav-tab <?php echo $active_tab == 'api_keys' ? 'nav-tab-active' : ''; ?>">
            🤖 AI Settings
        </a>
        <a href="?page=autoblog&tab=data_sources"
           class="nav-tab <?php echo $active_tab == 'data_sources' ? 'nav-tab-active' : ''; ?>">
            📥 Data Sources
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
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">🤖 AI Engine & Model Settings</h2>
                        </div>
                        <div class="inside">
                            <p class="description">Atur provider utama, model, embedding, dan media generator artikel Anda.</p>
                            <?php require_once 'autoblog-admin-ai-engine.php'; ?>
                        </div>
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
                ?>
                <div class="command-center-grid">
                    <!-- Kolom Kiri: Visual Agent Flow Diagram -->
                    <?php require_once 'autoblog-admin-logs.php'; ?>

                    <!-- Kolom Kanan: Quick Controls & Overrides -->
                    <div class="postbox" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
                        <div class="postbox-header">
                            <h2 class="hndle">⚡ Overrides & Quick Actions</h2>
                        </div>
                        <div class="inside" style="flex-grow:1; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
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
                            
                            <div style="margin-top: 20px; border-top: 1px solid #dcdcde; padding-top: 15px;">
                                <input type="button" id="autoblog-run-now-btn" class="button button-primary button-hero" value="▶ Picu Pipeline Sekarang" style="width:100%; text-align:center; font-weight:700;">
                                <div id="autoblog-run-status" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">📅 Penjadwalan Otomatis (Cron Job)</h2>
                    </div>
                    <div class="inside">
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
                            <div style="margin-top: 15px; border-top:1px solid #dcdcde; padding-top:15px;">
                                <?php submit_button('Simpan Jadwal', 'secondary'); ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
                break;

            case 'api_keys':
            default:
                ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'autoblog_keys' ); ?>
                    <?php require_once 'autoblog-admin-api-keys.php'; ?>
                    <div style="margin-top: 15px;">
                        <?php submit_button('Simpan Kredensial', 'primary'); ?>
                    </div>
                </form>
                <?php
                break;
        }
        ?>
    </div>
</div>
