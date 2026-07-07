<?php

namespace Autoblog\Sources\Drivers;

use Autoblog\Utils\Logger;
use GuzzleHttp\Client;

/**
 * Trait DuckDuckGoDriver
 *
 * Mengambil hasil pencarian melalui halaman HTML DuckDuckGo (tanpa API key).
 * Di-use oleh SearchSource. Mengasumsikan method passes_filters() dan
 * fetch_full_content() tersedia dari class yang menggunakannya.
 *
 * @package Autoblog\Sources\Drivers
 */
trait DuckDuckGoDriver {

    /**
     * Ambil data dari DuckDuckGo HTML (gratis, tanpa API key).
     *
     * @return array
     */
    private function fetch_duckduckgo_free() {
        $client = new Client( [ 'timeout' => 15, 'http_errors' => false ] );
        $url    = 'https://html.duckduckgo.com/html/?q=' . urlencode( $this->query );
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

        try {
            $response = $client->get( $url, [
                'headers' => [
                    'User-Agent' => $ua,
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ],
            ]);

            if ( $response->getStatusCode() !== 200 ) {
                Logger::log( 'DuckDuckGo Free HTTP Status: ' . $response->getStatusCode(), 'warning' );
                return [];
            }

            $html = (string) $response->getBody();
            if ( empty( $html ) ) {
                return [];
            }

            $dom = new \DOMDocument();
            @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
            $xpath = new \DOMXPath( $dom );
            $nodes = $xpath->query( "//div[contains(@class, 'web-result')]" );

            $items = [];
            $count = 0;

            foreach ( $nodes as $node ) {
                if ( $count >= 5 ) {
                    break;
                }

                $title_node   = $xpath->query( ".//a[contains(@class, 'result__a')]", $node );
                $snippet_node = $xpath->query( ".//a[contains(@class, 'result__snippet')]", $node );

                if ( $title_node->length === 0 ) {
                    continue;
                }

                $title = trim( $title_node->item(0)->textContent );
                $link  = $title_node->item(0)->getAttribute('href');

                // Ekstrak URL asli dari parameter uddg
                if ( preg_match( '/uddg=(https?[^&]+)/', $link, $matches ) ) {
                    $link = urldecode( $matches[1] );
                }

                $snippet = '';
                if ( $snippet_node->length > 0 ) {
                    $snippet = trim( $snippet_node->item(0)->textContent );
                }

                $full_content = ! empty( $link ) ? $this->fetch_full_content( $link ) : '';
                if ( empty( $full_content ) ) {
                    $full_content = $snippet;
                }

                if ( $this->passes_filters( $title . ' ' . $full_content ) ) {
                    $items[] = [
                        'title'       => $title,
                        'link'        => $link,
                        'description' => $snippet,
                        'content'     => $full_content,
                        'source_type' => 'duckduckgo_free',
                        'source_url'  => $this->query,
                    ];
                    $count++;
                }
            }

            Logger::log( 'DuckDuckGo Free: berhasil memuat ' . count( $items ) . ' hasil.', 'info' );
            return $items;

        } catch ( \Exception $e ) {
            Logger::log( 'DuckDuckGo Free failed: ' . $e->getMessage(), 'error' );
        }

        return [];
    }
}
