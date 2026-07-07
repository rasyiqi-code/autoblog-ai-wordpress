<?php

namespace Autoblog\Sources\Drivers;

use Autoblog\Utils\Logger;
use GuzzleHttp\Client;

/**
 * Trait SerpApiDriver
 *
 * Mengambil hasil pencarian melalui SerpApi dengan urutan prioritas:
 * 1. Google AI Mode  → 2. Bing Copilot  → 3. Google Standard (AI Overview)  → 4. Organic Fallback
 *
 * Di-use oleh SearchSource. Mengasumsikan property $query, $serpapi_key,
 * dan method passes_filters(), fetch_full_content(), build_item() tersedia.
 *
 * @package Autoblog\Sources\Drivers
 */
trait SerpApiDriver {

    /**
     * Fetch data dari SerpApi dengan Context Aggregation berlapis.
     *
     * @return array
     */
    private function fetch_serpapi() {
        if ( empty( $this->serpapi_key ) ) {
            return [];
        }

        $client           = new Client();
        $base_url         = 'https://serpapi.com/search';
        $organic_fallback = [];

        // ---- Priority 1: Google AI Mode ----
        try {
            $params   = [ 'engine' => 'google_ai_mode', 'q' => $this->query, 'api_key' => $this->serpapi_key, 'gl' => 'us', 'hl' => 'en' ];
            $response = $client->get( $base_url, [ 'query' => $params, 'http_errors' => false ] );
            $json     = json_decode( $response->getBody(), true );

            if ( isset( $json['error'] ) ) {
                Logger::log( 'SerpApi AI Mode (P1) Error: ' . $json['error'], 'warning' );
            } elseif ( ! empty( $json['ai_overview'] ) ) {
                $content = $this->extract_ai_overview_content( $json['ai_overview'] );
                if ( $content ) {
                    Logger::log( 'Fetched SerpApi: AI Mode (P1)', 'info' );
                    return [ $this->build_item( $this->query, $content, 'google_ai_mode' ) ];
                }
            } elseif ( ! empty( $json['text_blocks'] ) ) {
                $content = $this->parse_text_blocks( $json['text_blocks'] );
                if ( $content ) {
                    Logger::log( 'Fetched SerpApi: AI Mode via text_blocks (P1)', 'info' );
                    return [ $this->build_item( $this->query, $content, 'google_ai_mode' ) ];
                }
            }
        } catch ( \Exception $e ) {
            Logger::log( 'SerpApi AI Mode failed: ' . $e->getMessage(), 'warning' );
        }

        // ---- Priority 2: Bing Copilot ----
        try {
            $params   = [ 'engine' => 'bing_copilot', 'q' => $this->query, 'api_key' => $this->serpapi_key, 'tone' => 'Balanced' ];
            $response = $client->get( $base_url, [ 'query' => $params, 'http_errors' => false ] );
            $json     = json_decode( $response->getBody(), true );

            if ( isset( $json['error'] ) ) {
                Logger::log( 'SerpApi Bing Copilot (P2) Error: ' . $json['error'], 'warning' );
            } else {
                $chat_text = '';
                if ( ! empty( $json['copilot_answer'] ) ) {
                    if ( is_string( $json['copilot_answer'] ) ) {
                        $chat_text = $json['copilot_answer'];
                    } elseif ( isset( $json['copilot_answer']['text_blocks'] ) ) {
                        $chat_text = $this->parse_text_blocks( $json['copilot_answer']['text_blocks'] );
                    }
                } elseif ( ! empty( $json['text_blocks'] ) || ! empty( $json['header'] ) ) {
                    if ( ! empty( $json['header'] ) ) {
                        $chat_text .= '**' . $json['header'] . "**\n\n";
                    }
                    if ( ! empty( $json['text_blocks'] ) ) {
                        $chat_text .= $this->parse_text_blocks( $json['text_blocks'] );
                    }
                }

                if ( $chat_text ) {
                    Logger::log( 'Fetched SerpApi: Bing Copilot (P2)', 'info' );
                    return [ $this->build_item( 'Bing Copilot: ' . $this->query, $chat_text, 'bing_copilot' ) ];
                }
            }
        } catch ( \Exception $e ) {
            Logger::log( 'SerpApi Bing Copilot failed: ' . $e->getMessage(), 'warning' );
        }

        // ---- Priority 3: Google Standard + AI Overview ----
        try {
            $params   = [ 'engine' => 'google', 'q' => $this->query, 'api_key' => $this->serpapi_key, 'gl' => 'us', 'hl' => 'en' ];
            $response = $client->get( $base_url, [ 'query' => $params, 'http_errors' => false ] );
            $json     = json_decode( $response->getBody(), true );

            // Buffer organic untuk P4 (hindari double billing)
            if ( ! empty( $json['organic_results'] ) ) {
                $organic_fallback = $json['organic_results'];
            }

            if ( isset( $json['error'] ) ) {
                Logger::log( 'SerpApi Google Standard (P3) Error: ' . $json['error'], 'warning' );
            } elseif ( ! empty( $json['ai_overview'] ) ) {
                $content = $this->extract_ai_overview_content( $json['ai_overview'] );
                if ( $content ) {
                    Logger::log( 'Fetched SerpApi: AI Overview via Standard (P3)', 'info' );
                    return [ $this->build_item( $this->query, $content, 'google_ai_overview' ) ];
                }
            }
        } catch ( \Exception $e ) {
            Logger::log( 'SerpApi Google Standard (P3) failed: ' . $e->getMessage(), 'warning' );
        }

        // ---- Priority 4: Organic Fallback (last resort) ----
        Logger::log( 'Semua metode AI gagal. Fallback ke Organic Results.', 'warning' );

        try {
            if ( ! empty( $organic_fallback ) ) {
                Logger::log( 'Menggunakan cached Organic results dari P3.', 'info' );
                return array_slice( $this->process_organic_results( $organic_fallback ), 0, 3 );
            }

            // Jarang terjadi: P3 error tapi tetap mau coba
            $params       = [ 'engine' => 'google', 'q' => $this->query, 'api_key' => $this->serpapi_key, 'gl' => 'us', 'hl' => 'en' ];
            $organic_items = $this->fetch_standard_results( $client, $base_url, $params );
            return array_slice( $organic_items, 0, 3 );

        } catch ( \Exception $e ) {
            Logger::log( 'SerpApi Organic Fallback failed: ' . $e->getMessage(), 'error' );
        }

        return [];
    }

    // ================================================================
    // HELPERS: Parsing & Building
    // ================================================================

    /**
     * Parse array text_blocks menjadi string konten.
     *
     * @param array $blocks
     * @return string
     */
    private function parse_text_blocks( $blocks ) {
        $content = '';
        foreach ( $blocks as $block ) {
            $type = isset( $block['type'] ) ? $block['type'] : 'paragraph';
            if ( $type === 'heading' && isset( $block['snippet'] ) ) {
                $content .= '### ' . $block['snippet'] . "\n\n";
            } elseif ( $type === 'list' && ! empty( $block['list'] ) ) {
                foreach ( $block['list'] as $li ) {
                    $li_text = isset( $li['snippet'] ) ? $li['snippet'] : ( isset( $li['title'] ) ? $li['title'] : '' );
                    if ( $li_text ) { $content .= '- ' . $li_text . "\n"; }
                }
                $content .= "\n";
            } else {
                if ( isset( $block['snippet'] ) ) { $content .= $block['snippet'] . "\n\n"; }
                elseif ( isset( $block['text'] ) ) { $content .= $block['text'] . "\n\n"; }
            }
        }
        return $content;
    }

    /**
     * Ekstrak teks dari objek AI Overview.
     *
     * @param mixed $overview
     * @return string
     */
    private function extract_ai_overview_content( $overview ) {
        if ( is_string( $overview ) ) {
            return $overview;
        }
        if ( isset( $overview['text_blocks'] ) ) {
            return $this->parse_text_blocks( $overview['text_blocks'] );
        }
        return '';
    }

    /**
     * Proses raw organic results menjadi array item.
     *
     * @param array $results
     * @return array
     */
    private function process_organic_results( $results ) {
        $items = [];
        foreach ( $results as $result ) {
            $link         = $result['link'];
            $full_content = $this->fetch_full_content( $link );

            if ( ! $full_content ) {
                Logger::log( 'Gagal scrape: ' . $link . '. Gunakan snippet.', 'warning' );
                $full_content = isset( $result['snippet'] ) ? $result['snippet'] : '';
            }

            if ( $full_content && $this->passes_filters( $result['title'] . ' ' . strip_tags( $full_content ) ) ) {
                $items[] = [
                    'title'       => $result['title'],
                    'link'        => $link,
                    'description' => isset( $result['snippet'] ) ? $result['snippet'] : '',
                    'content'     => $full_content,
                    'source_type' => 'google_standard_fallback',
                    'source_url'  => $this->query,
                ];
            }
        }
        return $items;
    }

    /**
     * Fetch standard organic results melalui SerpApi Google Engine.
     *
     * @param \GuzzleHttp\Client $client
     * @param string             $url
     * @param array              $params
     * @return array
     */
    private function fetch_standard_results( $client, $url, $params ) {
        $response = $client->get( $url, [ 'query' => $params ] );
        $json     = json_decode( $response->getBody(), true );
        $items    = [];

        if ( ! empty( $json['organic_results'] ) ) {
            foreach ( $json['organic_results'] as $result ) {
                $link         = $result['link'];
                $full_content = $this->fetch_full_content( $link );

                if ( ! $full_content ) {
                    Logger::log( 'Gagal scrape: ' . $link . '. Gunakan snippet.', 'warning' );
                    $full_content = isset( $result['snippet'] ) ? $result['snippet'] : '';
                }

                if ( $full_content && $this->passes_filters( $result['title'] . ' ' . strip_tags( $full_content ) ) ) {
                    $items[] = [
                        'title'       => $result['title'],
                        'link'        => $link,
                        'description' => isset( $result['snippet'] ) ? $result['snippet'] : '',
                        'content'     => $full_content,
                        'source_type' => 'google_standard_fallback',
                        'source_url'  => $this->query,
                    ];
                }
            }
        }
        return $items;
    }
}
