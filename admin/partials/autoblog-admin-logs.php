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

<style>
    .agent-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    .agent-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 20px;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        position: relative;
    }
    .agent-card h3 {
        margin-top: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .agent-data {
        font-size: 13px;
        color: #50575e;
        margin-top: 10px;
    }
    .agent-data strong {
        color: #1d2327;
    }
    .agent-icon {
        font-size: 24px;
        margin-bottom: 10px;
    }
</style>

<div class="wrap">
    <h1>🚀 Pipeline Dashboard (Agentic Command Center)</h1>
    <p>Pantau aktivitas Agen AI Anda secara real-time melalui alur kerja 3 fase.</p>

    <div class="agent-grid">
        <!-- Phase 1: Collector Agent -->
        <div class="agent-card">
            <div class="agent-icon">📥</div>
            <h3>Collector Agent <?php echo get_status_badge( $ingestion['status'] ); ?></h3>
            <div class="agent-data">
                <p><strong>Last Sync:</strong> <?php echo isset($ingestion['timestamp']) ? $ingestion['timestamp'] : '-'; ?></p>
                <p><strong>Ingested:</strong> <?php echo isset($ingestion['count']) ? $ingestion['count'] : 0; ?> source(s)</p>
                <?php if ( ! empty( $ingestion['sources'] ) ) : ?>
                    <ul style="font-size: 11px; color: #666; max-height: 50px; overflow: auto; background: #f9f9f9; padding: 5px; border: 1px solid #eee;">
                        <?php foreach ( array_slice($ingestion['sources'], -3) as $src ) : ?>
                            <li><?php echo esc_html( $src ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div style="margin-top: 15px;">
                <button type="button" class="button button-secondary run-agent" data-agent="collector">Run Collector</button>
            </div>
        </div>

        <!-- Phase 2: Ideator Agent -->
        <div class="agent-card">
            <div class="agent-icon">🧠</div>
            <h3>Ideator Agent <?php echo get_status_badge( $ideation['status'] ); ?></h3>
            <div class="agent-data">
                <p><strong>Last Brainstorm:</strong> <?php echo isset($ideation['timestamp']) ? $ideation['timestamp'] : '-'; ?></p>
                <?php if ( isset( $ideation['title'] ) ) : ?>
                    <p><strong>Selected Topic:</strong><br>
                    <span style="font-style: italic; color: #2271b1;">"<?php echo esc_html( $ideation['title'] ); ?>"</span></p>
                <?php else : ?>
                    <p>Waiting for next brainstorm session...</p>
                <?php endif; ?>
            </div>
            <div style="margin-top: 15px;">
                <button type="button" class="button button-secondary run-agent" data-agent="ideator">Run Ideator</button>
            </div>
        </div>

        <!-- Phase 3: Writer Agent -->
        <div class="agent-card">
            <div class="agent-icon">✍️</div>
            <h3>Writer Agent <?php echo get_status_badge( $production['status'] ); ?></h3>
            <div class="agent-data">
                <p><strong>Last Published:</strong> <?php echo isset($production['timestamp']) ? $production['timestamp'] : '-'; ?></p>
                <?php if ( isset( $production['topic'] ) ) : ?>
                    <p><strong>Target:</strong> <?php echo esc_html( $production['topic'] ); ?></p>
                    <p><strong>Result:</strong> 
                        <?php if ( isset( $production['post_id'] ) ) : ?>
                            <a href="<?php echo get_edit_post_link( $production['post_id'] ); ?>" target="_blank">View Post ID: <?php echo $production['post_id']; ?></a>
                        <?php else : ?>
                            <?php echo esc_html( $production['status'] ); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div style="margin-top: 15px;">
                <button type="button" class="button button-secondary run-agent" data-agent="writer">Run Writer</button>
            </div>
        </div>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2>📟 System Logs (Debug Console)</h2>
        <textarea class="autoblog-log-viewer" style="width: 100%; height: 300px; font-family: monospace; background: #1d2327; color: #00ff00; padding: 10px;" readonly><?php echo esc_textarea( $log_content ); ?></textarea>
        
        <form method="post" style="margin-top: 10px;">
            <?php submit_button( 'Clear Logs', 'secondary', 'autoblog_clear_logs' ); ?>
        </form>
    </div>
</div>
