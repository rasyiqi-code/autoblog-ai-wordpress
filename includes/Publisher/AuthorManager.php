<?php

namespace Autoblog\Publisher;

use Autoblog\Utils\Logger;

/**
 * AuthorManager: Manages AI Author personas and mappings to WordPress users.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Publisher
 * @author     Rasyiqi
 */
class AuthorManager {

    /**
     * Get a list of available WordPress users that can be used as AI Authors.
     * Typically users with 'author', 'editor', or 'administrator' roles.
     *
     * @return array List of user objects (ID and display_name).
     */
    public function get_available_authors() {
        $args = array(
            'role__in' => array( 'administrator', 'editor', 'author' ),
            'fields'   => array( 'ID', 'display_name' ),
        );

        $users = get_users( $args );
        $authors = array();

        foreach ( $users as $user ) {
            $authors[] = array(
                'id'           => $user->ID,
                'display_name' => $user->display_name,
            );
        }

        return $authors;
    }

    /**
     * Pick an author ID based on a specific strategy.
     * 
     * @param string $strategy 'random' | 'fixed' | 'round_robin'
     * @param int    $fixed_id If strategy is 'fixed', use this ID.
     * @return int The selected WordPress User ID.
     */
    public function pick_author( $strategy = 'random', $fixed_id = 0 ) {
        $authors = $this->get_available_authors();

        if ( empty( $authors ) ) {
            Logger::log( "AuthorManager: No available authors found. Defaulting to ID 1.", 'warning' );
            return 1;
        }

        switch ( $strategy ) {
            case 'fixed':
                return $fixed_id > 0 ? $fixed_id : $authors[0]['id'];

            case 'round_robin':
                $last_index = (int) get_option( 'autoblog_last_author_index', 0 );
                $next_index = ( $last_index + 1 ) % count( $authors );
                update_option( 'autoblog_last_author_index', $next_index );
                return $authors[$next_index]['id'];

            case 'random':
            default:
                $random_key = array_rand( $authors );
                return $authors[$random_key]['id'];
        }
    }

    /**
     * Get the mapped persona data for a specific user.
     * 
     * @param int $user_id
     * @return array Persona data including name, description, and writing samples.
     */
    public function get_author_persona_data( $user_id ) {
        $persona_name = get_user_meta( $user_id, '_autoblog_persona_name', true );
        $custom_samples = get_user_meta( $user_id, '_autoblog_personality_samples', true );

        $all_personas = get_option( 'autoblog_custom_personas', array() );
        $selected_persona = null;

        foreach ( $all_personas as $p ) {
            if ( $p['name'] === $persona_name ) {
                $selected_persona = $p;
                break;
            }
        }

        // Fallback to "Si Netral" if no persona assigned
        if ( ! $selected_persona ) {
            $selected_persona = array(
                'name' => 'Si Netral',
                'desc' => 'seorang asisten yang membantu dan informatif. Tulis dengan gaya standar yang jelas dan mudah dipahami.'
            );
        }

        return array(
            'name'    => $selected_persona['name'],
            'desc'    => $selected_persona['desc'],
            'samples' => ! empty( $custom_samples ) ? $custom_samples : get_option( 'autoblog_personality_samples', '' ),
        );
    }

    /**
     * Assign a specific AI Persona name to a WordPress User ID.
     */
    public function update_author_persona( $user_id, $persona_name, $samples = '' ) {
        update_user_meta( $user_id, '_autoblog_persona_name', $persona_name );
        if ( ! is_null( $samples ) ) {
            update_user_meta( $user_id, '_autoblog_personality_samples', $samples );
        }
    }
}
