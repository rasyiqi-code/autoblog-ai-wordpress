<?php
/**
 * @deprecated Gunakan autoblog-admin-data-sources.php sebagai gantinya.
 * File ini dipertahankan untuk backward compatibility.
 * Semua fungsionalitas Knowledge Base Upload sudah dipindahkan ke tab Data Sources.
 */
if ( isset( $_POST['autoblog_upload_file'] ) && ! empty( $_FILES['autoblog_file'] ) ) {
    check_admin_referer( 'autoblog_upload_verify' );

    $uploaded_file = $_FILES['autoblog_file'];
    $upload_overrides = array( 'test_form' => false );

    // Upload file using WP functions
    $movefile = wp_handle_upload( $uploaded_file, $upload_overrides );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        // PROCESSED & STORED IN VECTOR STORE (Handled by Runner in next run, or immediate)
        // For UI listing, we store in 'autoblog_knowledge' option
        $knowledge_base = get_option( 'autoblog_knowledge', array() );
        if ( ! is_array( $knowledge_base ) ) {
            $knowledge_base = array();
        }
        
        $new_item = array(
            'id'   => uniqid('doc_'),
            'name' => basename($movefile['file']),
            'path' => $movefile['file'], // Absolute path
            'url'  => $movefile['url'],
            'date' => date('Y-m-d H:i:s')
        );
        
        $knowledge_base[] = $new_item;
        update_option( 'autoblog_knowledge', $knowledge_base );
        
        // Also Trigger Immediate Vector Ingestion? 
        // Ideally, Runner picks this up. For now, let's keep it simple.
        
        echo '<div class="notice notice-success is-dismissible"><p>File added to Knowledge Base! Run the pipeline to process embeddings.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>File upload failed: ' . $movefile['error'] . '</p></div>';
    }
}
?>

<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>Upload Source File</h2>
    <p>Upload Excel, PDF, Word, or Text files to be processed by Autoblog.</p>
    
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'autoblog_upload_verify' ); ?>
        <input type="file" name="autoblog_file" accept=".xlsx,.csv,.pdf,.docx,.txt,.md" required />
        <?php submit_button( 'Upload & Process', 'primary', 'autoblog_upload_file' ); ?>
    </form>
</div>

<br>

<div class="card" style="max-width: 100%;">
    <h3>Current Knowledge Base</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>File Name</th>
                <th>Date Added</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $kb = get_option('autoblog_knowledge', array());
            if ( ! empty( $kb ) ) : 
                foreach ( $kb as $index => $item ) : ?>
                <tr>
                    <td><?php echo esc_html( isset($item['name']) ? $item['name'] : basename($item['path']) ); ?></td>
                    <td><?php echo esc_html( isset($item['date']) ? $item['date'] : '-' ); ?></td>
                    <td><span class="dashicons dashicons-database"></span> Stored</td>
                    <td>
                        <a href="<?php echo wp_nonce_url( '?page=autoblog&tab=upload&delete_kb=' . $index, 'autoblog_delete_kb' ); ?>" class="button button-small button-link-delete" onclick="return confirm('Remove from Knowledge Base?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">No files in Knowledge Base.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
// Handle Deletion logic inline for simplicity
if ( isset( $_GET['delete_kb'] ) ) {
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'autoblog_delete_kb' ) ) {
        wp_die( 'Security check gagal.' );
    }
    $idx = intval( $_GET['delete_kb'] );
    $kb = get_option('autoblog_knowledge', array());
    if ( isset( $kb[$idx] ) ) {
        unset( $kb[$idx] );
        update_option( 'autoblog_knowledge', array_values($kb) );
        echo "<script>window.location.href='?page=autoblog&tab=upload';</script>";
    }
}
?>
