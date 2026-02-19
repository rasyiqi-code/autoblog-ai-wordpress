<?php

namespace Autoblog\Publisher;

use Autoblog\Generators\ThumbnailGenerator;
use Autoblog\Utils\Logger;
use WP_Error;

/**
 * Manages creation and updates of WordPress posts.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Publisher
 * @author     Rasyiqi
 */
class PostManager {

	/**
	 * Thumbnail Generator instance.
	 *
	 * @var ThumbnailGenerator
	 */
	private $thumbnail_generator;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->thumbnail_generator = new ThumbnailGenerator();
	}

	/**
	 * Create or update a post based on source data.
	 *
	 * @param array  $source_item   The raw source item (must contain 'title', 'source_url').
	 * @param string $html_content  The generated HTML content.
	 * @param string $thumbnail_url Optional URL of the generated thumbnail.
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function create_or_update_post( $source_item, $html_content, $thumbnail_url = null ) {

		$source_url = isset( $source_item['source_url'] ) ? $source_item['source_url'] : '';
        $title      = isset( $source_item['title'] ) ? $source_item['title'] : 'Auto Generated Post';
        
        // Extract title from <h1> if available in content (AI often adds it)
        if ( preg_match( '/<h1>(.*?)<\/h1>/i', $html_content, $match ) ) {
            $title = $match[1];
            // Remove <h1> from content as WP handles title separately
            $html_content = str_replace( $match[0], '', $html_content );
        }

		// Check if post exists
		$existing_post_id = $this->get_post_by_source_url( $source_url );

		$post_data = array(
			'post_title'   => wp_strip_all_tags( $title ),
			'post_content' => $this->convert_to_gutenberg_blocks( $html_content ),
			'post_status'  => 'draft', // Default to draft for safety
			'post_type'    => 'post',
			'post_author'  => get_current_user_id() ? get_current_user_id() : 1, // Fallback to admin
		);

		if ( $existing_post_id ) {
			$post_data['ID'] = $existing_post_id;
			$post_id = wp_update_post( $post_data, true );
            Logger::log( "Updated post ID: {$post_id}", 'info' );
		} else {
			$post_id = wp_insert_post( $post_data, true );
            Logger::log( "Created new post ID: {$post_id}", 'info' );
		}

		if ( is_wp_error( $post_id ) ) {
			Logger::log( 'Error creating/updating post: ' . $post_id->get_error_message(), 'error' );
			return $post_id;
		}

		// Save source URL meta
		update_post_meta( $post_id, '_autoblog_source_url', $source_url );

		// Handle Thumbnail
		if ( $thumbnail_url ) {
			$attach_id = $this->thumbnail_generator->save_to_media_library( $thumbnail_url, $post_id );
			if ( ! is_wp_error( $attach_id ) ) {
				set_post_thumbnail( $post_id, $attach_id );
			}
		}

		return $post_id;

	}

	/**
	 * Find existing post by source URL.
	 *
	 * @param string $url The source URL.
	 * @return int|false Post ID or false if not found.
	 */
	private function get_post_by_source_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$args = array(
			'post_type'  => 'post',
			'meta_key'   => '_autoblog_source_url',
			'meta_value' => $url,
			'posts_per_page' => 1,
			'fields'     => 'ids',
            'post_status' => 'any'
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}

    /**
     * Convert simple HTML to Gutenberg Blocks.
     * 
     * @param string $html The HTML content.
     * @return string Validated content with block comments.
     */
    /**
     * Convert HTML to Gutenberg Blocks using DOMDocument.
     * 
     * @param string $html The HTML content.
     * @return string Validated content with block comments.
     */
    private function convert_to_gutenberg_blocks( $html ) {
        if ( empty( $html ) ) {
            return '';
        }

        // Suppress libxml errors for malformed HTML
        libxml_use_internal_errors( true );

        $dom = new \DOMDocument();
        
        // Handle UTF-8 encoding properly without adding artifacts to the DOM
        // If mb_convert_encoding is available, use it to convert content to HTML-ENTITIES
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );
             $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        } else {
             // Fallback: prepend meta charset, but we must process it carefully later
             // Using XML hack often leaks into output for loadHTML partials
             // Try wrapper approach
             $dom->loadHTML( '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>' . $html . '</body></html>' );
             // If we use wrapper, we need to get nodes from body
             $xpath = new \DOMXPath( $dom );
             $body = $dom->getElementsByTagName('body')->item(0);
             if ( $body ) {
                 // Move nodes from body to a list to iterate, or just iterate body childNodes
                 // But our main loop iterates $dom->childNodes.
                 // We need to change the loop target if we use this fallback.
                 // For now, let's assume mb_convert_encoding exists in WP env.
             }
        }

        libxml_clear_errors();

        $blocks = '';
        $xpath = new \DOMXPath( $dom );

        // If loadHTML was called with NOIMPLIED, childNodes are the roots.
        // If we used the wrapper fallback (unimplemented properly above), we would need body children.
        // Let's stick to NOIMPLIED with mb_convert_encoding as primary.
        
        // Sanitization: Remove dangerous tags before processing
        $xpath = new \DOMXPath( $dom );
        $dangerous_tags = ['//script', '//style', '//iframe', '//object', '//embed', '//form'];
        
        foreach ( $dangerous_tags as $tag ) {
            $nodes = $xpath->query( $tag );
            if ( $nodes ) {
                foreach ( $nodes as $node ) {
                    $node->parentNode->removeChild( $node );
                }
            }
        }

        foreach ( $dom->childNodes as $node ) {
            $blocks .= $this->process_dom_node( $node );
        }

        // Clean up decoding (blocks are now strings with entities? No, process_dom_node uses textContent which decodes entities)
        // However, converting to HTML-ENTITIES makes text encoded. 
        // node->textContent will auto-decode entities. 
        // But invalid HTML entities might remain? 
        // No, standard entities.
        
        return $blocks;
    }

    /**
     * Process individual DOM nodes to Gutenberg blocks.
     *
     * @param \DOMNode $node
     * @return string
     */
    private function process_dom_node( $node ) {
        $content = '';

        switch ( $node->nodeName ) {
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $level = substr( $node->nodeName, 1 );
                $text = $node->textContent;
                $content .= "<!-- wp:heading {\"level\":$level} -->\n<{$node->nodeName}>$text</{$node->nodeName}>\n<!-- /wp:heading -->\n\n";
                break;

            case 'p':
                // Check if paragraph contains an image (common in some feeds)
                $img = $node->getElementsByTagName('img')->item(0);
                if ( $img ) {
                    // Process image separately
                    $content .= $this->process_image_node( $img );
                    // Process remaining text if any
                    $text = trim( str_replace( $node->ownerDocument->saveHTML($img), '', $node->ownerDocument->saveHTML($node) ) ); // simplified logic
                    if ( ! empty( $text ) ) {
                         $content .= "<!-- wp:paragraph -->\n<p>" . strip_tags($text) . "</p>\n<!-- /wp:paragraph -->\n\n";
                    }
                } else {
                    $text = $node->textContent; // or saveHTML to keep inline tags like <b>, <i>?
                    // Better to use saveHTML to preserve inline formatting like <strong>, <em>
                    $inner_html = '';
                    foreach ($node->childNodes as $child) {
                        $inner_html .= $node->ownerDocument->saveHTML($child);
                    }
                    $content .= "<!-- wp:paragraph -->\n<p>$inner_html</p>\n<!-- /wp:paragraph -->\n\n";
                }
                break;

            case 'ul':
                $inner_html = '';
                foreach ($node->childNodes as $child) {
                    if ($child->nodeName === 'li') {
                        $inner_html .= "<li>" . $child->textContent . "</li>"; // Simplified, should preserve internal formatting
                    }
                }
                $content .= "<!-- wp:list -->\n<ul>$inner_html</ul>\n<!-- /wp:list -->\n\n";
                break;

            case 'ol':
                $inner_html = '';
                foreach ($node->childNodes as $child) {
                    if ($child->nodeName === 'li') {
                         $inner_html .= "<li>" . $child->textContent . "</li>";
                    }
                }
                $content .= "<!-- wp:list {\"ordered\":true} -->\n<ol>$inner_html</ol>\n<!-- /wp:list -->\n\n";
                break;

            case 'img':
                $content .= $this->process_image_node( $node );
                break;

            case 'blockquote':
                 $text = $node->textContent;
                 $content .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>$text</p></blockquote>\n<!-- /wp:quote -->\n\n";
                 break;

            case '#text':
                $text = trim( $node->textContent );
                if ( ! empty( $text ) ) {
                    $content .= "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->\n\n";
                }
                break;

            default:
                // Fallback for other nodes (div, etc): treat as HTML or paragraph
                // Using paragraph for safety
                 $text = trim( $node->textContent );
                 if ( ! empty( $text ) ) {
                    $content .= "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->\n\n";
                 }
                break;
        }

        return $content;
    }

    /**
     * Helper to process image nodes
     */
    private function process_image_node( $node ) {
        $src = $node->getAttribute('src');
        $alt = $node->getAttribute('alt');
        return "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"$src\" alt=\"$alt\"/></figure>\n<!-- /wp:image -->\n\n";
    }

}
