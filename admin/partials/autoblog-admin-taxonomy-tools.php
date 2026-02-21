<?php
/**
 * UI Halaman Alat Taksonomi — Memungkinkan auto-set kategori/tag secara manual.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin/partials
 */
?>

<div class="wrap">
    <h1>Auto-Set Taxonomy Tools</h1>
    <p>Gunakan alat ini untuk memindai postingan yang belum memiliki kategori/tag dan mencoba menetapkannya kembali menggunakan data JSON yang dihasilkan AI di dalam konten.</p>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <?php
        $default_category_id   = get_option('default_category', 1);
        $default_category_name = get_cat_name($default_category_id);
        if (!$default_category_name) {
            $default_category_name = 'Uncategorized';
        }
        ?>
        <h2>Postingan Tanpa Kategori (<?php echo esc_html($default_category_name); ?>)</h2>
        <?php
        $args = array(
            'post_type'      => 'post',
            'cat'            => $default_category_id,
            'posts_per_page' => 20,
            'post_status'    => 'any'
        );
        $query = new WP_Query( $args );

        if ( $query->have_posts() ) : ?>
            <div class="autoblog-taxonomy-list" style="margin-top: 20px;">
                <div style="display: flex; font-weight: bold; border-bottom: 2px solid #ccd0d4; padding: 10px 0;">
                    <div style="width: 50px; text-align: center;"><input id="cb-select-all-1" type="checkbox"></div>
                    <div style="flex-grow: 1;">Judul Post</div>
                    <div style="width: 150px;">Tanggal</div>
                    <div style="width: 100px;">Status</div>
                </div>
                <div id="autoblog-taxonomy-posts">
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <div style="display: flex; border-bottom: 1px solid #ccd0d4; padding: 10px 0; align-items: center;">
                            <div style="width: 50px; text-align: center;">
                                <input type="checkbox" name="post_ids[]" value="<?php the_ID(); ?>">
                            </div>
                            <div style="flex-grow: 1;"><strong><?php the_title(); ?></strong></div>
                            <div style="width: 150px;"><?php echo get_the_date(); ?></div>
                            <div style="width: 100px;"><?php echo get_post_status(); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                <button type="button" id="btn-run-ai-taxonomy" class="button button-primary">🧠 AI Predict Taxonomy (Selected)</button>
                <span id="taxonomy-status-spinner" class="spinner" style="float: none;"></span>
            </div>
            <div id="taxonomy-result-msg" style="margin-top: 15px; font-weight: bold; padding: 10px; border-radius: 4px; display: none;"></div>
        <?php else : ?>
            <p>Tidak ada postingan di kategori '<?php echo esc_html($default_category_name); ?>' saat ini. 🎉</p>
        <?php endif; 
        wp_reset_postdata(); ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Select All
    $('#cb-select-all-1').change(function() {
        $('#autoblog-taxonomy-posts input[type="checkbox"]').prop('checked', $(this).prop('checked'));
    });

    function run_taxonomy_action(action, btnId) {
        var selected = [];
        $('#autoblog-taxonomy-posts input[name="post_ids[]"]:checked').each(function() {
            selected.push($(this).val());
        });

        if (selected.length === 0) {
            alert('Pilih minimal satu postingan.');
            return;
        }

        var $btn = $('#' + btnId);
        var $spinner = $('#taxonomy-status-spinner');
        var $msg = $('#taxonomy-result-msg');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.text('Sedang memproses...').css({
            'color': '#666',
            'background': '#f0f0f1',
            'display': 'block'
        });

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
                $btn.prop('disabled', false);
                if (response.success) {
                    $msg.text(response.data.message).css({
                        'color': 'green',
                        'background': '#ecf7ed'
                    });
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $msg.text(response.data.message).css({
                        'color': 'red',
                        'background': '#fbeaea'
                    });
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $msg.text('Terjadi kesalahan server.').css({
                    'color': 'red',
                    'background': '#fbeaea'
                });
            }
        });
    }

    // Run AI Predict
    $('#btn-run-ai-taxonomy').click(function() {
        run_taxonomy_action('autoblog_ai_predict_taxonomy', 'btn-run-ai-taxonomy');
    });
});
</script>
