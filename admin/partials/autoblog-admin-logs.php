<?php
$upload_dir = wp_upload_dir();
$log_file   = $upload_dir['basedir'] . '/autoblog-logs/debug.log';
$log_content = '';

if ( file_exists( $log_file ) ) {
    $log_content = file_get_contents( $log_file );
} else {
    $log_content = "No logs found.";
}

if ( isset( $_POST['autoblog_clear_logs'] ) ) {
    file_put_contents( $log_file, '' );
    $log_content = "Logs cleared.";
    echo '<div class="notice notice-success is-dismissible"><p>Logs cleared.</p></div>';
}

$ingestion  = get_option( 'autoblog_last_ingestion_data', array( 'status' => 'idle' ) );
$ideation   = get_option( 'autoblog_last_ideation_data', array( 'status' => 'idle' ) );
$production = get_option( 'autoblog_last_production_data', array( 'status' => 'idle' ) );
?>



<div class="postbox" style="margin-bottom:0;">
    <div class="postbox-header">
        <h2 class="hndle">🚀 Agentic Command Center</h2>
    </div>
    <div class="inside">
        <p class="description">Aliran eksekusi 3 fase agen AI secara asinkron. Klik node agen untuk memicu jalankan parsial.</p>
        
        <div class="agent-flow-container">
            <div class="flow-line">
                <div class="flow-line-fill" id="autoblog-flow-line-fill" style="width: 0%;"></div>
            </div>
            
            <!-- Collector Node -->
            <div class="agent-node" id="node-collector" data-agent="collector">
                <div class="icon">📥</div>
                <div class="name">Collector Agent</div>
                <div class="status-lbl">
                    <span class="status-dot <?php echo esc_attr($ingestion['status']); ?>"></span>
                    <span class="lbl-text"><?php echo esc_html(strtoupper($ingestion['status'])); ?></span>
                </div>
                <div class="meta-desc">Ingested: <strong id="node-collector-count"><?php echo isset($ingestion['count']) ? intval($ingestion['count']) : 0; ?></strong> sources</div>
            </div>

            <!-- Ideator Node -->
            <div class="agent-node" id="node-ideator" data-agent="ideator">
                <div class="icon">🧠</div>
                <div class="name">Ideator Agent</div>
                <div class="status-lbl">
                    <span class="status-dot <?php echo esc_attr($ideation['status']); ?>"></span>
                    <span class="lbl-text"><?php echo esc_html(strtoupper($ideation['status'])); ?></span>
                </div>
                <div class="meta-desc" id="node-ideator-title" title="<?php echo isset($ideation['title']) ? esc_attr($ideation['title']) : ''; ?>">
                    <?php echo isset($ideation['title']) ? esc_html($ideation['title']) : 'No topic selected'; ?>
                </div>
            </div>

            <!-- Writer Node -->
            <div class="agent-node" id="node-writer" data-agent="writer">
                <div class="icon">✍️</div>
                <div class="name">Writer Agent</div>
                <div class="status-lbl">
                    <span class="status-dot <?php echo esc_attr($production['status']); ?>"></span>
                    <span class="lbl-text"><?php echo esc_html(strtoupper($production['status'])); ?></span>
                </div>
                <div class="meta-desc" id="node-writer-post">
                    Result ID: <strong id="node-writer-postid"><?php echo isset($production['post_id']) && $production['post_id'] > 0 ? intval($production['post_id']) : '-'; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Logs Console (Compact) -->
<div class="postbox" style="margin-top: 15px; margin-bottom: 0;">
    <div class="postbox-header" style="display:flex; justify-content:space-between; align-items:center; padding-right: 15px;">
        <h2 class="hndle" style="border:none;">📟 System logs (Debug Console)</h2>
        <form method="post" style="margin:0;">
            <?php submit_button( 'Hapus Log', 'secondary', 'autoblog_clear_logs', false, array('style' => 'padding: 2px 8px; font-size: 11px; min-height: 24px; line-height:1;') ); ?>
        </form>
    </div>
    <div class="inside" style="padding-top:15px;">
        <div class="autoblog-log-viewer" style="width: 100%; height: 200px; font-family: 'Courier New', Courier, monospace; background: #0f172a; color: #f1f5f9; padding: 12px; border-radius: 4px; border: 1px solid #334155; font-size: 12px; line-height: 1.5; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; box-sizing: border-box;"></div>
    </div>
</div>
