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

// Fetch Agent Statuses
$ingestion = get_option( 'autoblog_last_ingestion_data', array( 'status' => 'idle' ) );
$ideation  = get_option( 'autoblog_last_ideation_data', array( 'status' => 'idle' ) );
$production = get_option( 'autoblog_last_production_data', array( 'status' => 'idle' ) );

function get_status_badge( $status ) {
    $color = '#999';
    $label = strtoupper( $status );
    
    switch ( $status ) {
        case 'running':
            $color = '#2271b1';
            $label = '🔄 RUNNING';
            break;
        case 'completed':
            $color = '#46b450';
            $label = '✅ COMPLETED';
            break;
        case 'failed':
            $color = '#d63638';
            $label = '❌ FAILED';
            break;
        case 'skipped':
            $color = '#dba617';
            $label = '⚠️ SKIPPED';
            break;
        default:
            $label = '💤 IDLE';
    }
    
    return "<span style='background: {$color}; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;'>{$label}</span>";
}
?>

<div class="card" style="margin-top: 10px;">
    <h2 style="margin-bottom: 20px;">🚀 Pipeline Dashboard (Agentic Command Center)</h2>
    <p style="margin-top:-10px; margin-bottom: 25px; color: #64748b; font-size: 13.5px;">Pantau aktivitas Agen AI Anda secara real-time melalui alur kerja 3 fase.</p>

    <div class="agent-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
        <!-- Phase 1: Collector Agent -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; position: relative;">
            <div style="font-size: 24px; margin-bottom: 10px;">📥</div>
            <h3 style="margin-top: 0; font-size: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px;">
                Collector Agent 
                <?php echo get_status_badge( $ingestion['status'] ); ?>
            </h3>
            <div style="font-size: 13px; color: #475569; margin-top: 15px; line-height: 1.6;">
                <p style="margin: 0 0 8px;"><strong>Last Sync:</strong> <?php echo isset($ingestion['timestamp']) ? $ingestion['timestamp'] : '-'; ?></p>
                <p style="margin: 0 0 8px;"><strong>Ingested:</strong> <?php echo isset($ingestion['count']) ? $ingestion['count'] : 0; ?> source(s)</p>
                <?php if ( ! empty( $ingestion['sources'] ) ) : ?>
                    <ul style="font-size: 11px; color: #64748b; background: #ffffff; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; margin: 10px 0 0;">
                        <?php foreach ( array_slice($ingestion['sources'], -3) as $src ) : ?>
                            <li style="margin-bottom: 4px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 4px;"><?php echo esc_html( $src ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div style="margin-top: 20px;">
                <button type="button" class="button run-agent" style="width: 100%; border-radius: 6px; padding: 5px;" data-agent="collector">Run Collector</button>
            </div>
        </div>

        <!-- Phase 2: Ideator Agent -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; position: relative;">
            <div style="font-size: 24px; margin-bottom: 10px;">🧠</div>
            <h3 style="margin-top: 0; font-size: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px;">
                Ideator Agent 
                <?php echo get_status_badge( $ideation['status'] ); ?>
            </h3>
            <div style="font-size: 13px; color: #475569; margin-top: 15px; line-height: 1.6;">
                <p style="margin: 0 0 8px;"><strong>Last Brainstorm:</strong> <?php echo isset($ideation['timestamp']) ? $ideation['timestamp'] : '-'; ?></p>
                <?php if ( isset( $ideation['title'] ) ) : ?>
                    <p style="margin: 0;"><strong>Selected Topic:</strong><br>
                    <span style="font-style: italic; color: #2563eb;">"<?php echo esc_html( $ideation['title'] ); ?>"</span></p>
                <?php else : ?>
                    <p style="margin: 0;">Waiting for next brainstorm session...</p>
                <?php endif; ?>
            </div>
            <div style="margin-top: 20px;">
                <button type="button" class="button run-agent" style="width: 100%; border-radius: 6px; padding: 5px;" data-agent="ideator">Run Ideator</button>
            </div>
        </div>

        <!-- Phase 3: Writer Agent -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; position: relative;">
            <div style="font-size: 24px; margin-bottom: 10px;">✍️</div>
            <h3 style="margin-top: 0; font-size: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px;">
                Writer Agent 
                <?php echo get_status_badge( $production['status'] ); ?>
            </h3>
            <div style="font-size: 13px; color: #475569; margin-top: 15px; line-height: 1.6;">
                <p style="margin: 0 0 8px;"><strong>Last Published:</strong> <?php echo isset($production['timestamp']) ? $production['timestamp'] : '-'; ?></p>
                <?php if ( isset( $production['topic'] ) ) : ?>
                    <p style="margin: 0 0 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr( $production['topic'] ); ?>"><strong>Target:</strong> <?php echo esc_html( $production['topic'] ); ?></p>
                    <p style="margin: 0;"><strong>Result:</strong> 
                        <?php if ( isset( $production['post_id'] ) ) : ?>
                            <a href="<?php echo get_edit_post_link( $production['post_id'] ); ?>" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">View Post ID: <?php echo $production['post_id']; ?> ↗</a>
                        <?php else : ?>
                            <?php echo esc_html( $production['status'] ); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div style="margin-top: 20px;">
                <button type="button" class="button run-agent" style="width: 100%; border-radius: 6px; padding: 5px;" data-agent="writer">Run Writer</button>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 30px;">
    <h2 style="margin-bottom: 15px;">📟 System Logs (Debug Console)</h2>
    <div style="position: relative;">
        <textarea class="autoblog-log-viewer" style="width: 100%; height: 250px; font-family: 'Courier New', Courier, monospace; background: #0f172a; color: #10b981; padding: 15px; border-radius: 8px; border: 1px solid #334155; font-size: 12.5px; line-height: 1.5; resize: vertical; box-shadow: inset 0 2px 8px rgba(0,0,0,0.2);" readonly><?php echo esc_textarea( $log_content ); ?></textarea>
    </div>
    
    <form method="post" style="margin-top: 15px;">
        <?php submit_button( 'Clear Logs', 'secondary', 'autoblog_clear_logs', false, array('style' => 'border-radius: 6px;') ); ?>
    </form>
</div>
