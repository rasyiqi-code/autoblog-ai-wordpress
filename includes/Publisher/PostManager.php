<?php

namespace Autoblog\Publisher;

use Autoblog\Generators\ThumbnailGenerator;
use Autoblog\Intelligence\Interlinker;
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
	 * Interlinker instance.
	 * 
	 * @var Interlinker
	 */
	private $interlinker;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->thumbnail_generator = new ThumbnailGenerator();
		$this->interlinker          = new Interlinker();
	}

	/**
	 * Create or update a post based on source data.
	 *
	 * @param array  $source_item   The raw source item (must contain 'title', 'source_url').
	 * @param string $html_content  The generated HTML content.
	 * @param string $thumbnail_url Optional URL of the generated thumbnail.
	 * @param int    $author_id     Optional specific WordPress User ID for the post author.
	 * @param array  $taxonomy      Optional array with 'category' (string) and 'tags' (array).
	 * @param array  $overrides     Optional feature overrides.
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function create_or_update_post( $source_item, $html_content, $thumbnail_url = null, $author_id = null, $taxonomy = null, $overrides = array() ) {

		$source_url = isset( $source_item['source_url'] ) ? $source_item['source_url'] : '';
        $title      = isset( $source_item['title'] ) ? $source_item['title'] : 'Auto Generated Post';
        
        // Extract title from <h1> if available in content (AI often adds it)
        if ( preg_match( '/<h1>(.*?)<\/h1>/i', $html_content, $match ) ) {
            $title = $match[1];
            // Remove <h1> from content as WP handles title separately
            $html_content = str_replace( $match[0], '', $html_content );
        }
        
        // Extract title from Markdown # if AI ignored the HTML instruction
        if ( preg_match( '/^#\s+(.*?)$/mi', $html_content, $match ) ) {
            $title = $match[1];
            // Remove # Heading from content
            $html_content = str_replace( $match[0], '', $html_content );
        }

		// Autonomous Interlinking if enabled and not overridden
		$enable_interlink = get_option( 'autoblog_enable_interlinking' );
		if ( isset( $overrides['interlinking'] ) ) {
			$enable_interlink = (bool) $overrides['interlinking'];
		}

		if ( $enable_interlink ) {
			$links = $this->interlinker->get_relevant_posts( $title );
			if ( ! empty( $links ) ) {
				$html_content = $this->interlinker->inject_links( $html_content, $links );
				Logger::log( "Interlinker: Injected " . count($links) . " internal links into post content.", 'info' );
			}
		}

		// Check if post exists
		$existing_post_id = $this->get_post_by_source_url( $source_url );

		$post_data = array(
			'post_title'   => wp_strip_all_tags( $title ),
			'post_content' => $this->convert_to_gutenberg_blocks( $html_content ),
			'post_status'  => 'draft', // Default to draft for safety
			'post_type'    => 'post',
			'post_author'  => $author_id ? $author_id : (get_current_user_id() ? get_current_user_id() : 1), // Priority to provided author_id
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


		// Handle Taxonomy (Category & Tags)
		if ( ! empty( $taxonomy ) ) {
			// 1. Kategorisasi
			if ( ! empty( $taxonomy['category'] ) ) {
                $category_name = trim( $taxonomy['category'] );
                
                // Coba cari berdasarkan nama (case-insensitive via get_term_by)
				$term = get_term_by( 'name', $category_name, 'category' );
                
                // Jika tidak ketemu, coba cari berdasarkan slug
                if ( ! $term ) {
                    $term = get_term_by( 'slug', sanitize_title( $category_name ), 'category' );
                }

                $cat_id = 0;
				if ( $term && ! is_wp_error( $term ) ) {
					$cat_id = $term->term_id;
                    Logger::log( "Assigned existing category '{$category_name}' (ID: {$cat_id}) to post ID {$post_id}", 'info' );
				} else {
					Logger::log( "Category '{$category_name}' not found by name or slug. Skipping auto-creation to prevent spam.", 'warning' );
				}

                if ( $cat_id > 0 ) {
                    wp_set_post_categories( $post_id, array( $cat_id ) );
                }
			}

			// 2. Tagging
			if ( ! empty( $taxonomy['tags'] ) && is_array( $taxonomy['tags'] ) ) {
				wp_set_post_tags( $post_id, $taxonomy['tags'], true ); // Append = true
				Logger::log( "Assigned tags [" . implode(',', $taxonomy['tags']) . "] to post ID {$post_id}", 'info' );
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
     * Cek apakah ada post (publish/draft) yang menggunakan judul serupa.
     * Pencegahan Duplikat Artikel/Anti-Spam.
     *
     * @param string $title Judul artikel.
     * @return bool True jika post dengan judul ini sudah ada.
     */
    public function post_exists_by_title( $title ) {
        if ( empty( $title ) ) {
            return false;
        }

        $args = array(
            'post_type'      => 'post',
            'title'          => trim($title),
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids'
        );

        $query = new \WP_Query( $args );
        return $query->have_posts();
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

        // Terapkan wpautop untuk memaksa setiap newline/paragraf dibungkus tag <p> 
        // secara native oleh WP sebelum masuk ke DOM parser, agar tidak jadi 1 blok raksasa.
        $html = wpautop( $html );

        // Suppress libxml errors for malformed HTML
        libxml_use_internal_errors( true );

        $dom = new \DOMDocument();
        
        // Use XML encoding to force UTF-8 natively inside DOMDocument without converting characters to HTML-ENTITIES.
        // Gutenberg string-matches characters exactly. HTML-Entities will cause "Block contains unexpected or invalid content."
        $html_with_xml = '<?xml encoding="utf-8" ?>' . $html;
        $dom->loadHTML( $html_with_xml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

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
        if ( $node instanceof \DOMProcessingInstruction ) {
            return '';
        }

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

            case 'div':
            case 'article':
            case 'section':
            case 'main':
            case 'header':
            case 'footer':
                // Container tags: Recurse into their children to preserve inner paragraphs and headings
                foreach ( $node->childNodes as $child ) {
                    $content .= $this->process_dom_node( $child );
                }
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
