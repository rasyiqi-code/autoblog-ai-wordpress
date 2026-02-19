<?php

namespace Autoblog\Sources;

use Autoblog\Interfaces\SourceInterface;
use Autoblog\Utils\Logger;

/**
 * Validates and fetches data from RSS feeds.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Sources
 * @author     Rasyiqi
 */
class RSSSource implements SourceInterface {

	/**
	 * The URL of the RSS feed.
	 *
	 * @var string
	 */
	private $url;

    /**
     * Match Keywords (comma separated).
     * @var string
     */
    private $match_keywords;

    /**
     * Negative Keywords (comma separated).
     * @var string
     */
    private $negative_keywords;

    /**
     * Initialize the class.
     *
     * @param string $url The URL of the RSS feed.
     * @param string $match_keywords Optional match keywords.
     * @param string $negative_keywords Optional negative keywords.
     */
    public function __construct( $url, $match_keywords = '', $negative_keywords = '' ) {
        $this->url = $url;
        $this->match_keywords = $match_keywords;
        $this->negative_keywords = $negative_keywords;
    }

	/**
	 * Fetch data from the RSS source.
	 *
	 * @return array Array of raw data items.
	 */
	public function fetch_data() {
		
		$data = array();

		if ( ! $this->validate_source() ) {
			return $data;
		}

		try {
			
			$rss = simplexml_load_file( $this->url );

			if ( $rss ) {
				foreach ( $rss->channel->item as $item ) {
                    $title = (string) $item->title;
                    $description = (string) $item->description;
                    $link = (string) $item->link;

                    // 1. SMART FILTERING (Pre-Fetch)
                    if ( ! $this->passes_filters( $title, $description ) ) {
                        continue; // Skip this item
                    }

                    // 2. FULL CONTENT FETCHING (Smart Fetch)
                    // If content is short/empty, try to fetch full text from URL
                    $content = (string) $item->children( 'content', true )->encoded;
                    if ( empty( $content ) || str_word_count( strip_tags( $content ) ) < 50 ) {
                        $full_content = $this->fetch_full_content( $link );
                        if ( $full_content ) {
                            $content = $full_content;
                        }
                    }

					$data[] = array(
						'title'       => $title,
						'link'        => $link,
						'description' => $description,
						'content'     => $content,
						'pubDate'     => (string) $item->pubDate,
						'guid'        => (string) $item->guid,
						'source_type' => 'rss',
                        'source_url'  => $this->url
					);
				}
			}

		} catch ( \Exception $e ) {
			Logger::log( 'Error fetching RSS feed: ' . $e->getMessage(), 'error' );
		}

		return $data;

	}

    /**
     * Check if article passes keyword filters.
     */
    private function passes_filters( $title, $description ) {
        $text = strtolower( $title . ' ' . $description );

        // check match keywords (Must contain AT LEAST ONE)
        if ( ! empty( $this->match_keywords ) ) {
            $keywords = array_map( 'trim', explode( ',', $this->match_keywords ) );
            $found = false;
            foreach ( $keywords as $keyword ) {
                if ( ! empty( $keyword ) && strpos( $text, strtolower( $keyword ) ) !== false ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) return false; // Skipped (No match)
        }

        // check negative keywords (Must NOT contain ANY)
        if ( ! empty( $this->negative_keywords ) ) {
            $negatives = array_map( 'trim', explode( ',', $this->negative_keywords ) );
            foreach ( $negatives as $negative ) {
                if ( ! empty( $negative ) && strpos( $text, strtolower( $negative ) ) !== false ) {
                    return false; // Skipped (Negative match)
                }
            }
        }

        return true;
    }

    /**
     * Fetch full article content using Readability.
     */
    private function fetch_full_content( $url ) {
        if ( ! class_exists( 'FiveFilters\Readability\Readability' ) ) {
            return false;
        }

        try {
            $html = file_get_contents( $url );
            if ( ! $html ) return false;

            $readability = new \FiveFilters\Readability\Readability( new \FiveFilters\Readability\Configuration() );
            $readability->parse( $html );
            return $readability->getContent();

        } catch ( \Exception $e ) {
            Logger::log( 'Error fetching full content for ' . $url . ': ' . $e->getMessage(), 'warning' );
            return false;
        }
    }

	/**
	 * Validate if the source is accessible and valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source() {
		
		if ( filter_var( $this->url, FILTER_VALIDATE_URL ) === false ) {
			Logger::log( 'Invalid RSS URL: ' . $this->url, 'warning' );
			return false;
		}

		return true;
	}

	/**
	 * Get the type of the source.
	 *
	 * @return string Source type.
	 */
	public function get_display_name() {
		return 'RSS Feed';
	}

}
