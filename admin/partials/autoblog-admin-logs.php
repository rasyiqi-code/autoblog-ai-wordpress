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
?>

<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>System Logs</h2>
    <textarea class="autoblog-log-viewer" style="width: 100%; height: 400px; font-family: monospace; background: #f0f0f1;" readonly><?php echo esc_textarea( $log_content ); ?></textarea>
    
    <form method="post" style="margin-top: 10px;">
        <?php submit_button( 'Clear Logs', 'secondary', 'autoblog_clear_logs' ); ?>
    </form>
</div>
