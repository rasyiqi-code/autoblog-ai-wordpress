<?php
/**
 * UI Halaman Alat Taksonomi — Memungkinkan auto-set kategori/tag secara manual.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */
?>

<div class="wrap autoblog-tx-wrap">
    <div class="autoblog-tx-header">
        <h1>Auto-Set Taxonomy Tools</h1>
        <p>Gunakan alat ini untuk memindai postingan yang belum memiliki kategori/tag secara spesifik dan biarkan AI mencoba menetapkannya kembali menggunakan data semantik dari dalam konten.</p>
    </div>

    <div class="autoblog-tx-card">
        <?php
        $default_category_id   = get_option('default_category', 1);
        $default_category_name = get_cat_name($default_category_id);
        if (!$default_category_name) {
            $default_category_name = 'Uncategorized';
        }
        ?>
        <h2>📌 Postingan di Kategori Default (<?php echo esc_html($default_category_name); ?>)</h2>
        
        <?php
        $args = array(
            'post_type'      => 'post',
            'cat'            => $default_category_id,
            'posts_per_page' => 50,
            'post_status'    => 'any'
        );
        $query = new WP_Query( $args );

        if ( $query->have_posts() ) : ?>
            
            <div class="autoblog-tx-table-container">
                <table class="autoblog-tx-table" id="autoblog-taxonomy-posts">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input id="cb-select-all-1" type="checkbox" class="autoblog-tx-checkbox" title="Select All">
                            </th>
                            <th>Judul Postingan</th>
                            <th style="width: 150px;">Tanggal</th>
                            <th style="width: 120px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ( $query->have_posts() ) : $query->the_post(); 
                            $status = get_post_status();
                            $badge_class = 'status-other';
                            if ($status === 'publish') $badge_class = 'status-publish';
                            elseif ($status === 'draft') $badge_class = 'status-draft';
                            elseif ($status === 'pending') $badge_class = 'status-pending';
                            elseif ($status === 'future') $badge_class = 'status-future';
                        ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="post_ids[]" value="<?php the_ID(); ?>" class="autoblog-tx-checkbox">
                                </td>
                                <td>
                                    <strong><a href="<?php echo get_edit_post_link(); ?>" style="color: #0f172a; text-decoration: none;" target="_blank"><?php the_title(); ?></a></strong>
                                </td>
                                <td><?php echo get_the_date('M j, Y'); ?></td>
                                <td>
                                    <span class="autoblog-tx-badge <?php echo $badge_class; ?>">
                                        <?php echo esc_html($status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="autoblog-tx-actions">
                <button type="button" id="btn-run-ai-taxonomy" class="autoblog-tx-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                    Jalankan AI Predict (Selected)
                </button>
                <span id="taxonomy-status-spinner" class="spinner" style="float: none; margin: 0;"></span>
            </div>

            <div id="taxonomy-result-msg" class="autoblog-tx-alert"></div>
            
        <?php else : ?>
            
            <div class="autoblog-tx-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <p>Mantap! Tidak ada postingan yang nyasar di kategori '<strong><?php echo esc_html($default_category_name); ?></strong>' saat ini. 🎉</p>
            </div>
            
        <?php endif; 
        wp_reset_postdata(); ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Select All Checkbox
    $('#cb-select-all-1').on('change', function() {
        $('#autoblog-taxonomy-posts input[name="post_ids[]"]').prop('checked', $(this).prop('checked'));
    });

    function run_taxonomy_action(action, btnId) {
        var selected = [];
        $('#autoblog-taxonomy-posts input[name="post_ids[]"]:checked').each(function() {
            selected.push($(this).val());
        });

        if (selected.length === 0) {
            alert('Pilih minimal satu postingan terlebih dahulu.');
            return;
        }

        var $btn = $('#' + btnId);
        var $spinner = $('#taxonomy-status-spinner');
        var $msg = $('#taxonomy-result-msg');
        var originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('Memproses AI...');
        $spinner.addClass('is-active');
        
        $msg.removeClass('is-info is-error').addClass('is-loading').html('Sedang menghubungkan ke AI...').css('display', 'flex');

        $.ajax({
            url: autoblog_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                nonce: autoblog_ajax.nonce,
                post_ids: selected
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false).html(originalBtnHtml);
                
                if (response.success) {
                    $msg.removeClass('is-loading is-error').addClass('is-info').html(
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> ' + 
                        response.data.message
                    );
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $msg.removeClass('is-loading is-info').addClass('is-error').html(
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> ' + 
                        response.data.message
                    );
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false).html(originalBtnHtml);
                $msg.removeClass('is-loading is-info').addClass('is-error').html(
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> ' + 
                    'Terjadi kesalahan server atau timeout saat menghubungi AI.'
                );
            }
        });
    }

    // Run AI Predict Button
    $('#btn-run-ai-taxonomy').on('click', function(e) {
        e.preventDefault();
        run_taxonomy_action('autoblog_ai_predict_taxonomy', 'btn-run-ai-taxonomy');
    });
});
</script>
