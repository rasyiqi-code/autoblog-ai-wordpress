<?php

namespace Autoblog\Core;

use Autoblog\Utils\Logger;
use Autoblog\Intelligence\ResearchAgent;
use Autoblog\Generators\ArticleWriter;
use Autoblog\Intelligence\AngleInjector;
use WP_Query;

/**
 * Handles "Living Content" features.
 * Automatically refreshes old content with new information.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Core
 * @author     Rasyiqi
 */
class ContentRefresher {

    /**
     * Run the refresh process on a single old post.
     * 
     * @param bool $force Whether to bypass the global enable option.
     */
    public function refresh_old_content( $force = false ) {
        // License Protection
        $license_status = get_option( 'agencyos_license_autoblog-ai_status' );
        if ( $license_status !== 'active' ) {
            Logger::log( 'Content Refresh aborted: License is not active.', 'warning' );
            return;
        }

        if ( ! $force && ! get_option( 'autoblog_enable_living_content' ) ) {
            return;
        }

        Logger::log( 'ContentRefresher: Searching for stale content...', 'info' );

        // 1. Find a candidate post (Older than 6 months)
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'rand', // Random pick to spread refresh
            'date_query'     => array(
                array(
                    'column' => 'post_date',
                    'before' => '6 months ago',
                ),
            ),
            // Exclude posts refreshed recently
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_autoblog_last_refreshed',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_autoblog_last_refreshed',
                    'value'   => date( 'Y-m-d', strtotime( '-3 months' ) ), // Don't refresh if done in last 3 months
                    'compare' => '<',
                    'type'    => 'DATE',
                ),
            ),
        );

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            Logger::log( 'ContentRefresher: No stale content found.', 'info' );
            return;
        }

        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $title = get_the_title();

            Logger::log( "ContentRefresher: Refreshing Post ID {$post_id}: '{$title}'", 'info' );

            // 2. Research Fresh Info
            $research_agent = new ResearchAgent();
            // Force enable deep research for refresh to get new value
            add_filter( 'option_autoblog_enable_deep_research', '__return_true' );
            $research_context = $research_agent->conduct_research( $title );
            
            if ( empty( $research_context ) ) {
                 Logger::log( "ContentRefresher: Research failed. Skipping update.", 'warning' );
                 continue;
            }

            // 3. Generate New Angle (Focus on 'Review/Update')
            $injector = new AngleInjector();
            $angle = "Update Terbaru " . date('Y'); // Sederhana dulu

            // 4. Rewrite Content
            $writer = new ArticleWriter();
            // Context includes old content summary + new research
            $full_context = "ORIGINAL CONTENT SUMMARY: " . mb_substr( strip_tags( get_the_content() ), 0, 500 ) . "\n\n";
            $full_context .= "NEW RESEARCH FINDINGS:\n" . $research_context;
            
            // Bug #10 Fix: write_article() mengharapkan $data sebagai array, bukan string $title
            $data = array( array( 'title' => $title, 'content' => $full_context, 'source_type' => 'refresh' ) );
            $new_content_html = $writer->write_article( $data, $angle, $full_context );

            if ( $new_content_html ) {
                // Add Update Notice
                $notice = "<p><em>*Artikel ini telah diperbarui pada " . date_i18n( 'j F Y' ) . " untuk memastikan akurasi informasi terbaru.*</em></p><hr>";
                $final_content = $notice . $new_content_html;

                // 5. Update Post
                $updated_post = array(
                    'ID'           => $post_id,
                    'post_content' => $final_content,
                );

                wp_update_post( $updated_post );
                update_post_meta( $post_id, '_autoblog_last_refreshed', date( 'Y-m-d H:i:s' ) );
                
                Logger::log( "ContentRefresher: Success! Post ID {$post_id} updated.", 'info' );
            } else {
                Logger::log( "ContentRefresher: Writing failed for ID {$post_id}.", 'error' );
            }
        }
        
        wp_reset_postdata();
    }
}
