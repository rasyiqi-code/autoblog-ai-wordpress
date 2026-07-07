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

<style>
.agent-flow-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 25px 20px;
    position: relative;
    margin-bottom: 12px;
}
.agent-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 10px;
    width: 140px;
    text-align: center;
    z-index: 2;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}
.agent-node:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}
.agent-node.active {
    border-color: var(--primary);
    box-shadow: 0 0 10px rgba(37,99,235,0.15);
}
.agent-node .icon {
    font-size: 22px;
    margin-bottom: 4px;
}
.agent-node .name {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-main);
}
.agent-node .status-lbl {
    font-size: 9px;
    font-weight: 600;
    margin-top: 4px;
    display: flex;
    align-items: center;
}
.agent-node .meta-desc {
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 6px;
    border-top: 1px solid #f0f0f1;
    padding-top: 4px;
    width: 100%;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.flow-line {
    position: absolute;
    top: 50%;
    left: 90px;
    right: 90px;
    height: 2px;
    background: var(--border-color);
    z-index: 1;
    transform: translateY(-50%);
}
.flow-line-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #10b981, #3b82f6);
    transition: width 0.4s ease;
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
    vertical-align: middle;
}
.status-dot.idle { background: #94a3b8; }
.status-dot.completed { background: #10b981; }
.status-dot.skipped { background: #eab308; }
.status-dot.running {
    background: #3b82f6;
    animation: pulse-dot 1.2s infinite;
}
.status-dot.failed {
    background: #ef4444;
    animation: pulse-dot 1.2s infinite;
}
@keyframes pulse-dot {
    0% { transform: scale(0.9); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.5; }
    100% { transform: scale(0.9); opacity: 1; }
}
</style>

<div class="autoblog-card" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
    <div>
        <h2>🚀 Agentic Command Center</h2>
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
<div class="autoblog-card" style="margin-top: 15px; margin-bottom: 0;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <h2 style="margin:0;">📟 System logs (Debug Console)</h2>
        <form method="post" style="margin:0;">
            <?php submit_button( 'Hapus Log', 'secondary', 'autoblog_clear_logs', false, array('style' => 'padding: 2px 8px; font-size: 11px; min-height: 24px; line-height:1;') ); ?>
        </form>
    </div>
    <div class="autoblog-log-viewer" style="width: 100%; height: 200px; font-family: 'Courier New', Courier, monospace; background: #0f172a; color: #f1f5f9; padding: 12px; border-radius: 6px; border: 1px solid #334155; font-size: 12px; line-height: 1.5; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; box-sizing: border-box;"></div>
</div>
