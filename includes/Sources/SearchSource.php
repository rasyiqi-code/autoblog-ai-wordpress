<?php

namespace Autoblog\Sources;

use Autoblog\Interfaces\SourceInterface;
use Autoblog\Utils\Logger;
use GuzzleHttp\Client;

/**
 * Fetches content using Search APIs (SerpApi, Brave).
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Sources
 * @author     Rasyiqi
 */
class SearchSource implements SourceInterface {

	/**
	 * The search query.
	 *
	 * @var string
	 */
	private $query;

    /**
     * Match Keywords.
     * @var string
     */
    private $match_keywords;
    private $negative_keywords;

    /**
     * Settings
     */
    private $provider;
    private $serpapi_key;
    // Bug #12 Fix: Deklarasi property brave_key yang direferensikan di fetch_brave()
    private $brave_key;



	/**
	 * Initialize the class.
	 *
	 * @param string $query The search query.
     * @param string $match_keywords
     * @param string $negative_keywords
	 */
	public function __construct( $query, $match_keywords = '', $negative_keywords = '' ) {
		// Auto-sanitize query to remove accidental http/https prefix from URL field usage
        $this->query = preg_replace( '#^https?://#', '', $query );
        // Also remove www. if it exists at start, though less critical
        $this->query = preg_replace( '#^www\.#', '', $this->query );

        $this->match_keywords = $match_keywords;
        $this->negative_keywords = $negative_keywords;
        
        $this->provider = get_option( 'autoblog_search_provider', 'serpapi' );
        $this->serpapi_key = get_option( 'autoblog_serpapi_key' );

	}

	/**
	 * Fetch data from the Search source with Fallback.
	 *
	 * @return array Array of raw data items.
	 */
	public function fetch_data() {
		
		$data = array();

		if ( ! $this->validate_source() ) {
			return $data;
		}

        // Only SerpApi is supported now
        try {
            $data = $this->fetch_serpapi();
        } catch ( \Exception $e ) {
            Logger::log( "SerpApi failed: " . $e->getMessage(), 'error' );
        }

		return $data;
	}

    /**
     * Fetch using SerpApi with Context Aggregation.
     * Collects: AI Overview, AI Mode, Bing Copilot, AND Organic Results.
     */
    private function fetch_serpapi() {
        if ( empty( $this->serpapi_key ) ) return array();

        $client = new Client();
        $base_url = "https://serpapi.com/search";
        $items = [];
        $organic_fallback = []; // Buffer for Priority 4

        // 1. Google AI Mode (Highest Priority)
        try {
            $params = [
                'engine' => 'google_ai_mode', 
                'q' => $this->query,
                'api_key' => $this->serpapi_key,
                'gl' => 'us', 'hl' => 'en'
            ];
            $response = $client->get( $base_url, [ 'query' => $params, 'http_errors' => false ] );
            $json = json_decode( $response->getBody(), true );

            if ( isset( $json['error'] ) ) {
                 Logger::log( "SerpApi AI Mode (Priority 1) Error: " . $json['error'], 'warning' );
            } elseif ( ! empty( $json['ai_overview'] ) ) {
                // Correctly extract content from ai_overview object
                $content = $this->extract_ai_overview_content( $json['ai_overview'] );
                if ( $content ) {
                    Logger::log("Fetched SerpApi: AI Mode (Priority 1)", 'info');
                    return [ $this->build_item( $this->query, $content, "google_ai_mode" ) ];
                } else {
                     Logger::log( "SerpApi AI Mode (Priority 1) 'ai_overview' found but extraction failed.", 'warning' );
                }
            } elseif ( ! empty( $json['text_blocks'] ) ) {
                // Fallback: Check for root-level text_blocks (User Hint + Docs Verified)
                $content = '';
                foreach ( $json['text_blocks'] as $block ) {
                    $type = isset($block['type']) ? $block['type'] : 'paragraph';
                    
                    if ( $type === 'heading' && isset($block['snippet']) ) {
                        $content .= "### " . $block['snippet'] . "\n\n";
                    } elseif ( $type === 'list' && ! empty($block['list']) ) {
                        foreach ( $block['list'] as $li ) {
                            $li_text = isset($li['snippet']) ? $li['snippet'] : (isset($li['title']) ? $li['title'] : '');
                            if ( $li_text ) $content .= "- " . $li_text . "\n";
                        }
                        $content .= "\n";
                    } else {
                        // Standard paragraph or unknown
                        if ( isset( $block['snippet'] ) ) $content .= $block['snippet'] . "\n\n";
                        elseif ( isset( $block['text'] ) ) $content .= $block['text'] . "\n\n";
                    }
                }
                
                if ( $content ) {
                    Logger::log("Fetched SerpApi: AI Mode via text_blocks (Priority 1)", 'info');
                    return [ $this->build_item( $this->query, $content, "google_ai_mode" ) ];
                }
            } else {
                Logger::log( "SerpApi AI Mode (Priority 1) returned no 'text_blocks'. content keys: " . substr(print_r(array_keys($json), true), 0, 100), 'warning' );
            }
        } catch ( \Exception $e ) {
            Logger::log( "SerpApi AI Mode failed: " . $e->getMessage(), 'warning' );
        }

        // 2. Bing Chat (Copilot) - Priority 2
        try {
            $params = [
                'engine' => 'bing_copilot',
                'q' => $this->query,
                'api_key' => $this->serpapi_key,
                'tone' => 'Balanced'
            ];
            $response = $client->get( $base_url, [ 'query' => $params, 'http_errors' => false ] );
            $json = json_decode( $response->getBody(), true );

            if ( isset( $json['error'] ) ) {
                 Logger::log( "SerpApi Bing Copilot (Priority 2) Error: " . $json['error'], 'warning' );
            } else {
                 $chat_text = '';
                 // 1. Check for 'copilot_answer' object (Standard)
                 if ( ! empty( $json['copilot_answer'] ) ) {
                     if ( is_string( $json['copilot_answer'] ) ) {
                         $chat_text = $json['copilot_answer'];
                     } elseif ( isset( $json['copilot_answer']['text_blocks'] ) ) {
                          foreach( $json['copilot_answer']['text_blocks'] as $block ) {
                              if ( isset( $block['snippet'] ) ) $chat_text .= $block['snippet'] . "\n\n";
                              elseif ( isset( $block['text'] ) ) $chat_text .= $block['text'] . "\n\n";
                          }
                     }
                 } 
                 // 2. Check for Root Level keys (Fallback/Observed in logs)
                 elseif ( ! empty( $json['text_blocks'] ) || ! empty( $json['header'] ) ) {
                     if ( ! empty( $json['header'] ) ) {
                         $chat_text .= "**" . $json['header'] . "**\n\n";
                     }
                     if ( ! empty( $json['text_blocks'] ) ) {
                         foreach( $json['text_blocks'] as $block ) {
                              if ( isset( $block['snippet'] ) ) $chat_text .= $block['snippet'] . "\n\n";
                              elseif ( isset( $block['text'] ) ) $chat_text .= $block['text'] . "\n\n";
                         }
                     }
                 }
                 
                 if ( $chat_text ) {
                     Logger::log("Fetched SerpApi: Bing Copilot (Priority 2)", 'info');
                     return [ $this->build_item( "Bing Copilot: " . $this->query, $chat_text, "bing_copilot" ) ];
                 } else {
                     Logger::log( "SerpApi Bing Copilot (Priority 2) content was empty. Keys: " . substr(print_r(array_keys($json), true), 0, 200), 'warning' );
                 }
            }
        } catch ( \Exception $e ) {
            Logger::log( "SerpApi Bing Copilot failed: " . $e->getMessage(), 'warning' );
        }

        // 3. Google Standard (Check for AI Overview) - Priority 3
        try {
            $params = [
                'engine' => 'google', // Standard Engine results usually contain AI Overview
                'q' => $this->query,
                'api_key' => $this->serpapi_key,
                'gl' => 'us', 'hl' => 'en'
            ];
            $response = $client->get( $base_url, [ 'query' => $params, 'http_errors' => false ] );
            $json = json_decode( $response->getBody(), true );

            // Buffer organic results for Priority 4 to avoid double billing
            if ( ! empty( $json['organic_results'] ) ) {
                $organic_fallback = $json['organic_results'];
            }

            if ( isset( $json['error'] ) ) {
                 Logger::log( "SerpApi Google Standard (Priority 3) Error: " . $json['error'], 'warning' );
            } elseif ( ! empty( $json['ai_overview'] ) ) {
                 $content = $this->extract_ai_overview_content( $json['ai_overview'] );
                 if ( $content ) {
                     Logger::log("Fetched SerpApi: AI Overview via Standard (Priority 3)", 'info');
                     return [ $this->build_item( $this->query, $content, "google_ai_overview" ) ];
                 }
            }
        } catch ( \Exception $e ) {
            Logger::log( "SerpApi AI Overview (Standard) failed: " . $e->getMessage(), 'warning' );
        }

        // 4. Standard Organic Results (LAST RESORT)
        Logger::log("All AI methods failed. Falling back to Standard Organic Results (Last Resort).", 'warning');
        
        try {
            // Use buffered results if available
            if ( ! empty( $organic_fallback ) ) {
                Logger::log("Using cached Google Organic results from Priority 3.", 'info');
                $organic_items = $this->process_organic_results( $organic_fallback );
                return array_slice( $organic_items, 0, 3 );
            }

            // Otherwise fetch again (rare case if Priority 3 failed with error but we still want to try?)
            // If Priority 3 failed, likely this will too, but let's try just in case P3 was specific error.
            $params = [
                'engine' => 'google',
                'q' => $this->query,
                'api_key' => $this->serpapi_key,
                'gl' => 'us', 'hl' => 'en'
            ];
            $organic_items = $this->fetch_standard_results( $client, $base_url, $params );
            
            return array_slice( $organic_items, 0, 3 );
            
        } catch ( \Exception $e ) {
            Logger::log( "SerpApi Organic Fallback failed: " . $e->getMessage(), 'error' );
        }

        return [];
    }
    
    /**
     * Helper to process raw organic results into items
     */
    private function process_organic_results( $results ) {
        $items = [];
        foreach ( $results as $result ) {
            $link = $result['link'];
            $full_content = $this->fetch_full_content( $link );
            
            // Fallback to Snippet
            if ( ! $full_content ) {
                Logger::log( "Failed to scrape content for: " . $link . ". Using snippet fallback.", 'warning' );
                $full_content = isset($result['snippet']) ? $result['snippet'] : '';
            }

            if ( $full_content ) {
                // Apply filters
                if ( ! $this->passes_filters( $result['title'] . ' ' . strip_tags($full_content) ) ) continue;

                $items[] = array(
                    'title' => $result['title'],
                    'link' => $link,
                    'description' => isset($result['snippet']) ? $result['snippet'] : '',
                    'content' => $full_content,
                    'source_type' => 'google_standard_fallback',
                    'source_url' => $this->query
                );
            }
        }
        return $items;
    }

    /**
     * Helper to extract text from AI Overview object.
     */
    private function extract_ai_overview_content( $overview ) {
        $text = "";
        if ( is_string( $overview ) ) return $overview;
        if ( isset( $overview['text_blocks'] ) ) {
            foreach( $overview['text_blocks'] as $block ) {
                if( isset($block['snippet']) ) $text .= $block['snippet'] . "\n\n";
            }
        }
        return $text;
    }

    /**
     * Fetch using Brave Search API.
     */
    private function fetch_brave() {
        $items = []; // Bug #12 Fix: Deklarasi $items array
        if ( empty( $this->brave_key ) ) {
            Logger::log( 'Brave Search skipped: API Key is missing.', 'warning' );
            return array();
        }
        
        $client = new Client();
        
        // 1. Main Search Request
        try {
            Logger::log( "Requesting Brave Search for: " . $this->query, 'info' );
            $response = $client->get( 'https://api.search.brave.com/res/v1/web/search', [
                'headers' => [ 
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip',
                    'X-Subscription-Token' => $this->brave_key 
                ],
                'query' => [ 'q' => $this->query, 'count' => 5, 'summary' => 1 ]
            ]);

            $json = json_decode( $response->getBody(), true );
            
            // 1. Main Search Request - Manual Web Results ONLY (AI features removed as per user request)
            if ( ! empty( $json['web']['results'] ) ) {
                Logger::log( "Found " . count($json['web']['results']) . " web results. Processing...", 'info' );
                
                // Process up to 5 web results (Standard behavior)
                foreach ( $json['web']['results'] as $result ) {
                    $link = $result['url'];
                    $full_content = $this->fetch_full_content( $link );
                    
                    // Fallback to Description if Full Content fails
                    if ( ! $full_content ) {
                         Logger::log( "Failed to scrape content for: " . $link . ". Using snippet fallback.", 'warning' );
                         $full_content = isset($result['description']) ? $result['description'] : '';
                    }

                    if ( $full_content ) {
                        // Apply filters
                        if ( ! $this->passes_filters( $result['title'] . ' ' . strip_tags($full_content) ) ) {
                            Logger::log( "Skipped result (filters): " . $result['title'], 'info' );
                            continue;
                        }
                        
                        $items[] = array(
                            'title' => $result['title'],
                            'link' => $link,
                            'description' => isset($result['description']) ? $result['description'] : '',
                            'content' => $full_content, // Might be full or snippet
                            'source_type' => 'brave_search',
                            'source_url' => $this->query
                        );
                    }
                }
            } else {
                Logger::log( "No Web Results found in Brave response.", 'warning' );
            }
            return $items;

        } catch ( \Exception $e ) {
            Logger::log( 'Brave Search Error: ' . $e->getMessage(), 'error' );
            return [];
        }
    }

    /**
     * Fetch Standard Results (fallback for SerpApi).
     */
    private function fetch_standard_results( $client, $url, $params ) {
        $response = $client->get( $url, [ 'query' => $params ] );
        $json = json_decode( $response->getBody(), true );
        $items = [];

        if ( ! empty( $json['organic_results'] ) ) {
             foreach ( $json['organic_results'] as $result ) {
                  $link = $result['link'];
                  $full_content = $this->fetch_full_content( $link );
                  
                  // Fallback to Snippet
                  if ( ! $full_content ) {
                       Logger::log( "Failed to scrape content for: " . $link . ". Using snippet fallback.", 'warning' );
                       $full_content = isset($result['snippet']) ? $result['snippet'] : '';
                  }

                  if ( $full_content ) {
                       // Apply filters
                      if ( ! $this->passes_filters( $result['title'] . ' ' . strip_tags($full_content) ) ) continue;

                      $items[] = array(
                        'title' => $result['title'],
                        'link' => $link,
                        'description' => isset($result['snippet']) ? $result['snippet'] : '',
                        'content' => $full_content,
                        'source_type' => 'google_standard_fallback',
                        'source_url' => $this->query
                      );
                  }
             }
        }
        return $items;
    }

    private function build_item( $title, $content, $type ) {
        return array(
            'title' => $title,
            'content' => $content,
            'source_type' => $type,
            'source_url' => $this->query,
            'link' => '', // AI answer doesn't have a direct link usually
            'description' => substr( strip_tags($content), 0, 150 ) . '...',
            'guid' => md5( $content )
        );
    }

    /**
     * Fetch full article content using Readability.
     */
    private function fetch_full_content( $url ) {
        if ( ! class_exists( 'FiveFilters\Readability\Readability' ) ) return false;

        // Use cURL for better handling of redirects, SSL, and headers
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        // Randomize User Agents to avoid simple blocking
        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0'
        ];
        $random_ua = $user_agents[array_rand($user_agents)];

        curl_setopt($ch, CURLOPT_USERAGENT, $random_ua);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7", // Localized for Indo sites
            "Cache-Control: no-cache",
            "Referer: https://www.google.com/", // Fake referer
            "Upgrade-Insecure-Requests: 1",
            "Sec-Fetch-Dest: document",
            "Sec-Fetch-Mode: navigate",
            "Sec-Fetch-Site: cross-site",
            "Sec-Fetch-User: ?1"
        ]);
        
        // Increase timeout for slow responders
        curl_setopt($ch, CURLOPT_TIMEOUT, 20); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Cookie Jar (some sites need cookies to persist)
        // Bug #11 Fix: Cookie path unik per-request agar tidak race condition
        $cookie_file = sys_get_temp_dir() . '/autoblog_cookie_' . uniqid() . '.txt';
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);

        $html = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ( ! $html || $error ) {
             Logger::log( "cURL failed for {$url}: {$error}", 'warning' );
             return false;
        }

        try {
            $readability = new \FiveFilters\Readability\Readability( new \FiveFilters\Readability\Configuration() );
            $readability->parse( $html );
            return $readability->getContent();
        } catch ( \Exception $e ) { 
            Logger::log( "Readability parsing failed for {$url}: " . $e->getMessage(), 'warning' );
            return false; 
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
                    $found = true; break;
                }
            }
            if ( ! $found ) return false;
        }
        // Negative Keywords
        if ( ! empty( $this->negative_keywords ) ) {
             $negatives = array_map( 'trim', explode( ',', $this->negative_keywords ) );
             foreach ( $negatives as $negative ) {
                 if ( ! empty( $negative ) && strpos( $text, strtolower( $negative ) ) !== false ) return false;
             }
        }
        return true; 
    }

	public function validate_source() {
        // We don't enforce strict key check here because we support fallback.
        // If Primary Key is missing, we want execution to proceed to fetch_data() so it can try Secondary.
        
        if ( empty( $this->serpapi_key ) ) {
             Logger::log( 'SerpApi Key is missing. Search will fail.', 'error' );
             return false;
        }

        if ( empty( $this->query ) ) {
            Logger::log( 'Search query is empty.', 'warning' ); 
            return false;
        }
		return true;
	}

	public function get_display_name() { return 'Web Search (SerpApi)'; }
}
