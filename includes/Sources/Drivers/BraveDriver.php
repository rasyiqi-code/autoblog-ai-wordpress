<?php

namespace Autoblog\Sources\Drivers;

use Autoblog\Utils\Logger;
use GuzzleHttp\Client;

/**
 * Trait BraveDriver
 *
 * Mengambil hasil pencarian melalui Brave Search API.
 *
 * Di-use oleh SearchSource. Mengasumsikan property $query, $brave_key,
 * dan method passes_filters(), fetch_full_content() tersedia.
 *
 * @package Autoblog\Sources\Drivers
 */
trait BraveDriver {

    /**
     * Ambil data dari Brave Search API.
     *
     * @return array
     */
    private function fetch_brave() {
        if ( empty( $this->brave_key ) ) {
            Logger::log( 'Brave Search skipped: API Key is missing.', 'warning' );
            return [];
        }

        $items  = [];

        try {
            Logger::log( 'Requesting Brave Search for: ' . $this->query, 'info' );

            $client   = $this->get_http_client();
            $response = $client->get( 'https://api.search.brave.com/res/v1/web/search', [
                'headers' => [
                    'Accept'               => 'application/json',
                    'Accept-Encoding'      => 'gzip',
                    'X-Subscription-Token' => $this->brave_key,
                ],
                'query' => [
                    'q'       => $this->query,
                    'count'   => 3,
                    'summary' => 1,
                ],
            ]);

            $json = json_decode( $response->getBody(), true );

            if ( empty( $json['web']['results'] ) ) {
                Logger::log( 'Brave Search: tidak ada hasil web.', 'warning' );
                return [];
            }

            Logger::log( 'Brave Search: memproses ' . count( $json['web']['results'] ) . ' hasil.', 'info' );

            foreach ( $json['web']['results'] as $result ) {
                $link         = $result['url'];
                $full_content = $this->fetch_full_content( $link );

                if ( ! $full_content ) {
                    Logger::log( 'Gagal scrape: ' . $link . '. Gunakan description.', 'warning' );
                    $full_content = isset( $result['description'] ) ? $result['description'] : '';
                }

                if ( ! $full_content ) {
                    continue;
                }

                if ( ! $this->passes_filters( $result['title'] . ' ' . strip_tags( $full_content ) ) ) {
                    Logger::log( 'Skipped result (filters): ' . $result['title'], 'info' );
                    continue;
                }

                $items[] = [
                    'title'       => $result['title'],
                    'link'        => $link,
                    'description' => isset( $result['description'] ) ? $result['description'] : '',
                    'content'     => $full_content,
                    'source_type' => 'brave_search',
                    'source_url'  => $this->query,
                ];
            }

            return $items;

        } catch ( \Exception $e ) {
            Logger::log( 'Brave Search Error: ' . $e->getMessage(), 'error' );
            return [];
        }
    }
}
