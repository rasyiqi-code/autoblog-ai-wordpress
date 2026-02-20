<?php
// SEED DEFAULT PERSONAS (Run Once)
// We also use this list to identify protected personas
$default_names = ['Si Kritis', 'Si Storyteller', 'Si Realistis', 'Si Santuy', 'Si Profesional'];

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
        ],
        [
            'name' => 'Si Profesional', 
            'desc' => 'seorang jurnalis senior yang ahli dalam menyederhanakan konsep rumit. Gaya bahasamu sopan, autoritatif, dan sangat terstruktur.',
            'active' => true,
            'is_default' => true
        ]
    ];
    update_option( 'autoblog_custom_personas', $defaults );
} else {
    // Sync missing defaults (e.g. adding 'Si Profesional' to existing installations)
    $existing_personas = get_option( 'autoblog_custom_personas', [] );
    $needs_update = false;

    $defaults = [
        'Si Kritis' => 'seorang pengamat industri yang skeptis, to-the-point, dan benci basa-basi marketing. kamu sering menggunakan kalimat pendek yang menohok.',
        'Si Storyteller' => 'seorang pencerita ulung yang suka menggunakan metafora hidup dan anekdot pribadi. Tulisanmu mengalir seperti obrolan di warkop.',
        'Si Realistis' => 'orang yang praktis, fokus pada "apa yang bekerja", dan sering menggunakan kata "Gini lho," atau "Jujur aja,".',
        'Si Santuy' => 'anak muda Jakarta Selatan yang cerdas tapi santai. Sering pakai istilah gaul (slang) yang relevan tapi tetap berbobot.',
        'Si Profesional' => 'seorang jurnalis senior yang ahli dalam menyederhanakan konsep rumit. Gaya bahasamu sopan, autoritatif, dan sangat terstruktur.'
    ];

    foreach ( $defaults as $d_name => $d_desc ) {
        $found_index = -1;
        foreach ( $existing_personas as $idx => $ep ) {
            if ( $ep['name'] === $d_name ) {
                $found_index = $idx;
                break;
            }
        }

        if ( $found_index === -1 ) {
            // Add missing default
            $existing_personas[] = [
                'name' => $d_name,
                'desc' => $d_desc,
                'active' => true,
                'is_default' => true
            ];
            $needs_update = true;
        } else {
            // Ensure existing default is flagged as protected
            if ( ! isset( $existing_personas[$found_index]['is_default'] ) || ! $existing_personas[$found_index]['is_default'] ) {
                $existing_personas[$found_index]['is_default'] = true;
                $needs_update = true;
            }
        }
    }

    if ( $needs_update ) {
        update_option( 'autoblog_custom_personas', $existing_personas );
    }
}

// Logic: Add New Persona
if ( isset( $_POST['autoblog_add_persona'] ) ) {
    check_admin_referer( 'autoblog_add_persona_nonce' );
    $new_name = sanitize_text_field( $_POST['persona_name'] );
    $new_desc = sanitize_textarea_field( $_POST['persona_desc'] );
    
    if ( ! empty( $new_name ) && ! empty( $new_desc ) ) {
        $personas = get_option( 'autoblog_custom_personas', [] );
        $personas[] = [
            'name' => $new_name,
            'desc' => $new_desc,
            'active' => true,
            'is_default' => false
        ];
        update_option( 'autoblog_custom_personas', $personas );
        echo '<div class="notice notice-success is-dismissible"><p>Persona baru berhasil ditambahkan.</p></div>';
    }
}

// Logic: Delete Persona
if ( isset( $_GET['action'] ) && $_GET['action'] === 'autoblog_delete_persona' && isset( $_GET['name'] ) ) {
    check_admin_referer( 'autoblog_delete_persona_nonce' );
    $target_name = sanitize_text_field( $_GET['name'] );
    
    if ( ! in_array( $target_name, $default_names ) ) {
        $personas = get_option( 'autoblog_custom_personas', [] );
        $filtered = array_filter( $personas, function($p) use ($target_name) {
            return $p['name'] !== $target_name;
        });
        update_option( 'autoblog_custom_personas', array_values($filtered) );
        echo '<div class="notice notice-success is-dismissible"><p>Persona berhasil dihapus.</p></div>';
    }
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
<!-- ================================================================ -->
<!-- SECTION: Manage Custom Personas                                  -->
<!-- ================================================================ -->
<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>🎭 Manajemen Persona Master</h2>
    <p>Kelola daftar persona yang bisa dipilih oleh para penulis Anda.</p>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="20%">Nama Persona</th>
                <th width="60%">Character Prompt (Description)</th>
                <th width="20%">Tindakan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $existing_personas as $p ) : ?>
                <tr>
                    <td><strong><?php echo esc_html($p['name']); ?></strong> 
                        <?php if ( (isset($p['is_default']) && $p['is_default']) || in_array($p['name'], $default_names) ) : ?>
                            <span class="badge" style="background:#e5e5e5; padding:2px 6px; font-size:10px; border-radius:3px;">DEFAULT</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="description" style="font-size:12px;"><?php echo esc_html($p['desc']); ?></span></td>
                    <td>
                        <?php if ( ! in_array($p['name'], $default_names) && (!isset($p['is_default']) || !$p['is_default']) ) : ?>
                            <?php 
                            $delete_url = wp_nonce_url( 
                                add_query_arg( [ 'action' => 'autoblog_delete_persona', 'name' => $p['name'] ] ), 
                                'autoblog_delete_persona_nonce' 
                            ); 
                            ?>
                            <a href="<?php echo $delete_url; ?>" class="button-link-delete" style="color:#d63638;" onclick="return confirm('Hapus persona ini?')">Hapus</a>
                        <?php else : ?>
                            <span class="description">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <hr>
    <h3>➕ Tambah Persona Baru</h3>
    <form method="post">
        <?php wp_nonce_field( 'autoblog_add_persona_nonce' ); ?>
        <div style="display:flex; gap:10px; margin-bottom:10px;">
            <input type="text" name="persona_name" placeholder="Nama Persona (cth: Si Tekno)" style="flex:1;" required>
        </div>
        <div style="display:flex; gap:10px; margin-bottom:10px;">
            <textarea name="persona_desc" rows="3" placeholder="Deskripsi/Prompt Persona (cth: Kamu adalah seorang kutu buku yang sangat teliti...)" style="flex:1;" required></textarea>
        </div>
        <input type="submit" name="autoblog_add_persona" class="button button-secondary" value="Tambahkan Persona">
    </form>
</div>
