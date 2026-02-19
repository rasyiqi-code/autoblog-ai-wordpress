<?php

namespace Autoblog\Sources;

use Autoblog\Interfaces\SourceInterface;
use Autoblog\Utils\Logger;
use GuzzleHttp\Client;
use DOMDocument;
use DOMXPath;

/**
 * Scrapes content from web pages.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Sources
 * @author     Rasyiqi
 */
class WebScraperSource implements SourceInterface {

	/**
	 * The URL to scrape.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * The CSS selector to target content.
	 *
	 * @var string
	 */
    /**
     * Match Keywords.
     */
    private $match_keywords;
    private $negative_keywords;

	/**
	 * Initialize the class.
	 *
	 * @param string $url      The URL to scrape.
	 * @param string $selector The CSS selector.
     * @param string $match_keywords
     * @param string $negative_keywords
	 */
	public function __construct( $url, $selector = '', $match_keywords = '', $negative_keywords = '' ) {
		$this->url      = $url;
		$this->selector = $selector;
        $this->match_keywords = $match_keywords;
        $this->negative_keywords = $negative_keywords;
	}

	/**
	 * Fetch data from the web source.
	 *
	 * @return array Array of raw data items.
	 */
	public function fetch_data() {
		
		$data = array();

		if ( ! $this->validate_source() ) {
			return $data;
		}

		try {
			
            // Use Readability if selector is empty (Auto-Mode)
            if ( empty( $this->selector ) ) {
                return $this->fetch_with_readability();
            }

            // Otherwise, use basic scraping
			$client = new Client();
			$response = $client->request( 'GET', $this->url );
			
			if ( $response->getStatusCode() == 200 ) {
				$html = (string) $response->getBody();
				
				$dom = new DOMDocument();
				@$dom->loadHTML( $html );
				
				$xpath = new DOMXPath( $dom );
                
                // Simple Selector to XPath conversion
                $xpath_query = "//" . $this->selector; 
                if ( strpos($this->selector, '#') === 0 ) {
                    $id = substr($this->selector, 1);
                    $xpath_query = "//*[@id='$id']";
                } elseif ( strpos($this->selector, '.') === 0 ) {
                    $class = substr($this->selector, 1);
                     $xpath_query = "//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]";
                }
 
				$nodes = $xpath->query( $xpath_query );

				foreach ( $nodes as $node ) {
                    $content = $dom->saveHTML( $node );
                    $text_content = $node->textContent;

                    // Apply Filters
                    if ( ! $this->passes_filters( $text_content ) ) {
                        continue;
                    }

					$data[] = array(
						'content'     => $content,
                        'text_content'=> $text_content,
						'source_type' => 'web',
                        'source_url'  => $this->url,
                        'title'       => 'Scraped Content' // Fallback title
					);
				}

                // If scraping failed, fallback to readability?
                if ( empty( $data ) ) {
                    Logger::log( "Selector {$this->selector} yielded no results. Trying Auto-Readability fallback.", 'info' );
                    return $this->fetch_with_readability();
                }

			}

		} catch ( \Exception $e ) {
			Logger::log( 'Error scraping URL: ' . $e->getMessage(), 'error' );
		}

		return $data;

	}

    /**
     * Fetch using Readability (Auto-Detect Main Content).
     */
    private function fetch_with_readability() {
        if ( ! class_exists( 'FiveFilters\Readability\Readability' ) ) {
             Logger::log( 'Readability library not found.', 'error' );
             return array();
        }

        try {
            $html = file_get_contents( $this->url );
            if ( ! $html ) return array();

            $readability = new \FiveFilters\Readability\Readability( new \FiveFilters\Readability\Configuration() );
            $readability->parse( $html );
            
            $content = $readability->getContent();
            $title = $readability->getTitle();

             // Apply Filters
             if ( ! $this->passes_filters( $title . ' ' . strip_tags($content) ) ) {
                return array();
            }

            return array(
                array(
                    'title' => $title,
                    'content' => $content,
                    'source_type' => 'web_auto',
                    'source_url' => $this->url
                )
            );

        } catch ( \Exception $e ) {
            Logger::log( 'Readability Error: ' . $e->getMessage(), 'error' );
            return array();
        }
    }

    /**
     * Check filters.
     */
    private function passes_filters( $text ) {
        $text = strtolower( $text );

        // Match Keywords
        if ( ! empty( $this->match_keywords ) ) {
            $keywords = array_map( 'trim', explode( ',', $this->match_keywords ) );
            $found = false;
            foreach ( $keywords as $keyword ) {
                if ( ! empty( $keyword ) && strpos( $text, strtolower( $keyword ) ) !== false ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) return false;
        }

        // Negative Keywords
        if ( ! empty( $this->negative_keywords ) ) {
            $negatives = array_map( 'trim', explode( ',', $this->negative_keywords ) );
            foreach ( $negatives as $negative ) {
                if ( ! empty( $negative ) && strpos( $text, strtolower( $negative ) ) !== false ) {
                    return false;
                }
            }
        }

        return true; 
    }

	/**
	 * Validate if the source is accessible and valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source() {
		
		if ( filter_var( $this->url, FILTER_VALIDATE_URL ) === false ) {
			Logger::log( 'Invalid Web URL: ' . $this->url, 'warning' );
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
		return 'Web Scraper';
	}

}
