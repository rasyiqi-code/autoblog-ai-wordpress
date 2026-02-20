<?php
// SEED DEFAULT PERSONAS (Run Once)
// We also use this list to identify protected personas
$default_names = ['Si Kritis', 'Si Storyteller', 'Si Realistis', 'Si Santuy'];

if ( false === get_option( 'autoblog_custom_personas' ) ) {
    $defaults = [
        [
            'name' => 'Si Kritis', 
            'desc' => 'seorang pengamat industri yang skeptis, to-the-point, dan benci basa-basi marketing. kamu sering menggunakan kalimat pendek yang menohok.',
            'active' => true,
            'is_default' => true
        ],
        [
            'name' => 'Si Storyteller', 
            'desc' => 'seorang pencerita ulung yang suka menggunakan metafora hidup dan anekdot pribadi. Tulisanmu mengalir seperti obrolan di warkop.',
            'active' => true,
            'is_default' => true
        ],
        [
            'name' => 'Si Realistis', 
            'desc' => 'orang yang praktis, fokus pada "apa yang bekerja", dan sering menggunakan kata "Gini lho," atau "Jujur aja,".',
            'active' => true,
            'is_default' => true
        ],
        [
            'name' => 'Si Santuy', 
            'desc' => 'anak muda Jakarta Selatan yang cerdas tapi santai. Sering pakai istilah gaul (slang) yang relevan tapi tetap berbobot.',
            'active' => true,
            'is_default' => true
        ]
    ];
    update_option( 'autoblog_custom_personas', $defaults );
}

// Fetch existing personas
$existing_personas = get_option( 'autoblog_custom_personas', array() );
?>

<!-- ================================================================ -->
<!-- SECTION: AI Author Profiles (Multi-Author)                       -->
<!-- ================================================================ -->
<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>👥 AI Author Profiles</h2>
    <p>Tentukan identitas siapa yang akan muncul sebagai penulis artikel di WordPress.</p>

    <form method="post" action="options.php">
        <?php settings_fields( 'autoblog_style' ); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Author Strategy</th>
                <td>
                    <select name="autoblog_author_strategy">
                        <option value="random" <?php selected( get_option('autoblog_author_strategy'), 'random' ); ?>>Random (Acak)</option>
                        <option value="round_robin" <?php selected( get_option('autoblog_author_strategy'), 'round_robin' ); ?>>Round Robin (Bergantian)</option>
                        <option value="fixed" <?php selected( get_option('autoblog_author_strategy'), 'fixed' ); ?>>Fixed Author (Satu Penulis Tetap)</option>
                    </select>
                    <p class="description">Metode pemilihan akun WordPress untuk setiap artikel baru.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Target Author (Fixed)</th>
                <td>
                    <?php
                    require_once plugin_dir_path( __DIR__ ) . '../includes/Publisher/AuthorManager.php';
                    $author_mngr = new \Autoblog\Publisher\AuthorManager();
                    $authors = $author_mngr->get_available_authors();
                    $fixed_id = get_option( 'autoblog_author_fixed_id' );
                    ?>
                    <select name="autoblog_author_fixed_id">
                        <option value="0">-- Pilih Penulis --</option>
                        <?php foreach ( $authors as $author ) : ?>
                            <option value="<?php echo $author['id']; ?>" <?php selected( $fixed_id, $author['id'] ); ?>>
                                <?php echo esc_html( $author['display_name'] ); ?> (ID: <?php echo $author['id']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Gunakan ini jika Anda memilih strategi 'Fixed Author'.</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Simpan Pengaturan Penulis' ); ?>
    </form>
</div>

<!-- ================================================================ -->
<!-- SECTION: Mapping Penulis ke Persona                              -->
<!-- ================================================================ -->
<?php
// Handle Save Author Mapping
if ( isset( $_POST['autoblog_save_author_mapping'] ) ) {
    check_admin_referer( 'autoblog_save_author_mapping_nonce' );
    
    if ( isset( $_POST['author_persona'] ) && is_array( $_POST['author_persona'] ) ) {
        foreach ( $_POST['author_persona'] as $uid => $persona ) {
            $samples = isset( $_POST['author_samples'][$uid] ) ? $_POST['author_samples'][$uid] : '';
            $author_mngr->update_author_persona( intval($uid), sanitize_text_field($persona), sanitize_textarea_field($samples) );
        }
        echo '<div class="notice notice-success is-dismissible"><p>Author mapping saved successfully.</p></div>';
    }
}
?>

<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>🔗 Mapping Penulis ke Persona</h2>
    <p>Hubungkan setiap penulis WordPress dengan Persona AI dan gaya tulis (fine-tuning) yang unik.</p>

    <form method="post">
        <?php wp_nonce_field( 'autoblog_save_author_mapping_nonce' ); ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="20%">WordPress Author</th>
                    <th width="25%">Assigned Persona</th>
                    <th width="55%">Writing Samples (Custom for this Author)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $authors ) ) : ?>
                    <?php foreach ( $authors as $author ) : 
                        $current_data = $author_mngr->get_author_persona_data( $author['id'] );
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $author['display_name'] ); ?></strong><br>
                                <span class="description">ID: <?php echo $author['id']; ?></span>
                            </td>
                            <td>
                                <select name="author_persona[<?php echo $author['id']; ?>]" style="width:100%;">
                                    <option value="">-- Pilih Persona --</option>
                                    <?php foreach ( $existing_personas as $p ) : ?>
                                        <option value="<?php echo esc_attr($p['name']); ?>" <?php selected( $current_data['name'], $p['name'] ); ?>>
                                            <?php echo esc_html( $p['name'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <textarea name="author_samples[<?php echo $author['id']; ?>]" rows="3" style="width:100%;" placeholder="Contoh tulisan khusus penulis ini... Jika kosong, akan menggunakan setting global."><?php echo esc_textarea( get_user_meta( $author['id'], '_autoblog_personality_samples', true ) ); ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="3">No authors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php submit_button( 'Simpan Mapping Penulis', 'primary', 'autoblog_save_author_mapping' ); ?>
    </form>
</div>

<!-- ================================================================ -->
<!-- SECTION: Personality Fine-Tuning (Writing Samples)               -->
<!-- Dipindahkan dari tab Advanced agar semua gaya tulis di satu tab  -->
<!-- ================================================================ -->
<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>✍️ Personality Fine-Tuning</h2>
    <p>Berikan contoh tulisan Anda agar AI bisa meniru gaya, nada, dan struktur kalimat Anda (Few-Shot Prompting).</p>

    <form method="post" action="options.php">
        <?php
            // Option group terpisah: autoblog_style (agar tidak menimpa setting tab lain)
            settings_fields( 'autoblog_style' );
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Enable Personality</th>
                <td>
                    <fieldset>
                        <label for="autoblog_enable_personality">
                            <input name="autoblog_enable_personality" type="checkbox"
                                   id="autoblog_enable_personality" value="1"
                                   <?php checked( '1', get_option( 'autoblog_enable_personality' ) ); ?> />
                            Aktifkan Custom Personality
                        </label>
                    </fieldset>
                    <p class="description">Jika aktif, AI akan menganalisis sampel tulisan di bawah untuk meniru gaya Anda.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Writing Samples</th>
                <td>
                    <textarea name="autoblog_personality_samples" rows="10" cols="50"
                              class="large-text code"
                              placeholder="Tempel 2-3 paragraf tulisan Anda di sini. AI akan menganalisis nada, struktur kalimat, dan gaya humor Anda."><?php echo esc_textarea( get_option( 'autoblog_personality_samples' ) ); ?></textarea>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Simpan Personality' ); ?>
    </form>
</div>
