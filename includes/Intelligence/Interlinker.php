<?php

namespace Autoblog\Intelligence;

use Autoblog\Utils\Logger;
use WP_Query;

/**
 * Autonomous SEO Interlinker.
 * Scans existing posts and suggests internal links.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Intelligence
 * @author     Rasyiqi
 */
class Interlinker {

    /**
     * Get relevant internal links for the given content context.
     * 
     * @param string $content_topic The main topic of the new article.
     * @return array Array of ['url' => '...', 'title' => '...']
     */
    public function get_relevant_posts( $content_topic ) {
        if ( ! get_option( 'autoblog_enable_interlinking' ) ) {
            return [];
        }

        Logger::log( "Interlinker: Searching for posts relevant to '{$content_topic}'...", 'info' );

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            's'              => $content_topic, // Simple keyword search
            'fields'         => 'ids'
        ];

        $query = new WP_Query( $args );
        $links = [];

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $links[] = [
                    'title' => get_the_title( $post_id ),
                    'url'   => get_permalink( $post_id )
                ];
            }
        }

        return $links;
    }

    /**
     * Inject links into HTML content (Naive implementation).
     * In a robust version, we would use AI to place these naturally.
     */
    public function inject_links( $html, $links ) {
        if ( empty( $links ) ) return $html;

        $append_html = "<div class='autoblog-internal-links'><h3>Related Reading:</h3><ul>";
        foreach ( $links as $link ) {
            $append_html .= "<li><a href='{$link['url']}'>{$link['title']}</a></li>";
        }
        $append_html .= "</ul></div>";

        // Append to bottom for safety, or use DOM to inject after 2nd paragraph
        return $html . "\n" . $append_html;
    }
}
