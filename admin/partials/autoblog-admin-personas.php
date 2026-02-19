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

// Handle Add Persona
if ( isset( $_POST['autoblog_add_persona'] ) ) {
    check_admin_referer( 'autoblog_setup_personas' );

    $personas = get_option( 'autoblog_custom_personas', array() );
    
    $name = sanitize_text_field( $_POST['persona_name'] );
    $desc = sanitize_textarea_field( $_POST['persona_desc'] );

    if ( ! empty( $name ) && ! empty( $desc ) ) {
        $personas[] = array(
            'name' => $name,
            'desc' => $desc,
            'active' => true,
            'is_default' => false
        );
        
        update_option( 'autoblog_custom_personas', $personas );
        echo '<div class="notice notice-success is-dismissible"><p>Persona "'. esc_html($name) .'" added successfully.</p></div>';
    }
}

// Handle Save Active State & Delete
if ( isset( $_POST['autoblog_save_personas'] ) ) {
    check_admin_referer( 'autoblog_setup_personas' );
    
    $personas = get_option( 'autoblog_custom_personas', array() );
    
    // Update Active States
    if ( isset( $_POST['persona_active'] ) && is_array( $_POST['persona_active'] ) ) {
        foreach ( $personas as $k => $v ) {
            $personas[$k]['active'] = in_array( $k, $_POST['persona_active'] );
        }
    } else {
        foreach ( $personas as $k => $v ) {
            $personas[$k]['active'] = false;
        }
    }
    
    // Handle Delete if button clicked
    if ( isset( $_POST['delete_persona_index'] ) && $_POST['delete_persona_index'] !== '' ) {
        $idx = intval( $_POST['delete_persona_index'] );
        if ( isset( $personas[$idx] ) ) {
            // Check if default (flag or name match for legacy)
            $p = $personas[$idx];
            $is_protected = ( isset($p['is_default']) && $p['is_default'] ) || in_array( $p['name'], $default_names );
            
            if ( $is_protected ) {
                 echo '<div class="notice notice-error is-dismissible"><p>Cannot delete default persona.</p></div>';
            } else {
                unset( $personas[$idx] );
                $personas = array_values( $personas ); // Re-index
                echo '<div class="notice notice-success is-dismissible"><p>Persona deleted.</p></div>';
            }
        }
    }

    update_option( 'autoblog_custom_personas', $personas );
    echo '<div class="notice notice-success is-dismissible"><p>Personas updated.</p></div>';
}

// Fetch existing personas
$existing_personas = get_option( 'autoblog_custom_personas', array() );
?>

<div class="card" style="max-width: 100%; margin-top: 20px;">
    <h2>Manage Personas</h2>
    <form method="post">
        <?php wp_nonce_field( 'autoblog_setup_personas' ); ?>
        
        <!-- Add New -->
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Add New Persona</th>
                <td>
                    <input type="text" name="persona_name" class="regular-text" placeholder="Name (e.g. The Expert)" />
                    <textarea name="persona_desc" rows="2" class="large-text" placeholder="Description / Prompt instructions..." style="margin-top:5px;"></textarea>
                    <?php submit_button( 'Add New', 'secondary', 'autoblog_add_persona', false ); ?>
                </td>
            </tr>
        </table>
        
        <hr>

        <!-- List & Toggle -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="5%" class="check-column"><input type="checkbox" id="cb-select-all-1"></th>
                    <th width="20%">Name</th>
                    <th width="65%">Description / Prompt</th>
                    <th width="10%">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $existing_personas ) ) : ?>
                    <?php foreach ( $existing_personas as $index => $persona ) : 
                        $is_def = ( isset($persona['is_default']) && $persona['is_default'] ) || in_array( $persona['name'], $default_names );
                    ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="persona_active[]" value="<?php echo $index; ?>" <?php checked( isset($persona['active']) ? $persona['active'] : false ); ?> />
                            </th>
                            <td>
                                <strong><?php echo esc_html( $persona['name'] ); ?></strong>
                                <?php if($is_def): ?> <span class="dashicons dashicons-lock" title="Default Persona"></span><?php endif; ?>
                            </td>
                            <td><?php echo nl2br( esc_html( $persona['desc'] ) ); ?></td>
                            <td>
                                <?php if ( ! $is_def ) : ?>
                                    <button type="submit" name="delete_persona_index" value="<?php echo $index; ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this persona?')">Delete</button>
                                <?php else: ?>
                                    <span class="description">Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="4">No personas found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <p class="description">Hanya persona yang <strong>dicentang</strong> akan digunakan oleh AI generator (seleksi acak).</p>
        
        <?php submit_button( 'Save Changes', 'primary', 'autoblog_save_personas' ); ?>
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
