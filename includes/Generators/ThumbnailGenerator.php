<?php

namespace Autoblog\Generators;

use Autoblog\Utils\Logger;
use Autoblog\Utils\OptionCache;
use GuzzleHttp\Client;

/**
 * Generates thumbnail images using AI (DALL-E).
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Generators
 * @author     Rasyiqi
 */
class ThumbnailGenerator {

	private $openai_key;
	private $pexels_key;

	/** @var \GuzzleHttp\Client|null Guzzle Client untuk testing (dependency injection) */
	private $http_client = null;

	public function __construct() {
		$this->openai_key  = OptionCache::get( 'autoblog_openai_key' );
		$this->pexels_key  = OptionCache::get( 'autoblog_pexels_key' );

		// Bug #7 Fix: Fallback ke multi-key system jika legacy key kosong
		if ( empty( $this->openai_key ) ) {
			$custom_keys = OptionCache::get( 'autoblog_custom_api_keys', [] );
			$openai_pool = isset( $custom_keys['openai'] ) ? $custom_keys['openai'] : '';
			if ( ! empty( $openai_pool ) ) {
				$pool = array_filter( array_map( 'trim', preg_split( '/[\n,]+/', $openai_pool ) ) );
				$this->openai_key = ! empty( $pool ) ? $pool[0] : '';
			}
		}
	}

	// ================================================================
	// HTTP CLIENT GETTER/SETTER (untuk testing)
	// ================================================================

	/**
	 * Set Guzzle Client kustom (misal mock client untuk integration test).
	 *
	 * @param \GuzzleHttp\Client $client
	 */
	public function set_http_client( \GuzzleHttp\Client $client ) {
		$this->http_client = $client;
	}

	/**
	 * Dapatkan Guzzle Client. Jika sudah diset via set_http_client(),
	 * gunakan itu. Jika tidak, buat instance baru dengan konfigurasi $config.
	 *
	 * @param array $config
	 * @return \GuzzleHttp\Client
	 */
	private function get_http_client( $config = [] ) {
		if ( $this->http_client !== null ) {
			return $this->http_client;
		}
		return new \GuzzleHttp\Client( $config );
	}

	/**
	 * Generate an image based on a prompt or search.
	 *
	 * @param string $prompt The prompt or keywords for the image.
	 * @return string|false The URL of the image or false on failure.
	 */
	public function generate_thumbnail( $prompt ) {
		// Default to pexels if not set
		$source = OptionCache::get( 'autoblog_thumbnail_source', 'pexels' );
		Logger::log( "ThumbnailGenerator: Using source '{$source}' for prompt: '{$prompt}'", 'info' );

		switch ( $source ) {
			case 'openai':
				return $this->generate_dalle( $prompt );

			case 'openverse':
				return $this->search_openverse( $prompt );

			case 'random_stock':
				$url = $this->search_pexels( $prompt );
				if ( ! $url ) {
					$url = $this->search_openverse( $prompt );
				}
				return $url;

			case 'pexels':
			default:
				return $this->search_pexels( $prompt );
		}
	}

	/**
	 * Search for high-quality images on Pexels.
	 */
	private function search_pexels( $query ) {
		if ( empty( $this->pexels_key ) ) {
			Logger::log( 'Pexels API Key is missing.', 'warning' );
			return false;
		}

		try {
			$response = $this->get_http_client()->get( 'https://api.pexels.com/v1/search', [
				'headers' => [ 'Authorization' => $this->pexels_key ],
				'query'   => [
					'query'    => $query,
					'per_page' => 1,
					'orientation' => 'landscape'
				]
			]);

			$body = json_decode( (string) $response->getBody(), true );

			if ( ! empty( $body['photos'][0]['src']['large2x'] ) ) {
				$url = $body['photos'][0]['src']['large2x'];
				Logger::log( "Pexels: Found image URL: {$url}", 'info' );
				return $url;
			} else {
				Logger::log( "Pexels: No photos found for query: '{$query}'", 'warning' );
			}
		} catch ( \Exception $e ) {
			Logger::log( 'Pexels Search Error: ' . $e->getMessage(), 'error' );
		}

		return false;
	}

	/**
	 * Search for openly licensed images on WordPress Openverse.
	 */
	private function search_openverse( $query ) {
		try {
			// Openverse API (Creative Commons search)
			$response = $this->get_http_client()->get( 'https://api.openverse.org/v1/images/', [
				'query' => [
					'q'        => $query,
					'page_size' => 1,
					'license_type' => 'commercial', // Filter for commercial use
				]
			]);

			$body = json_decode( (string) $response->getBody(), true );

			if ( ! empty( $body['results'][0]['url'] ) ) {
				return $body['results'][0]['url'];
			}
		} catch ( \Exception $e ) {
			Logger::log( 'Openverse Search Error: ' . $e->getMessage(), 'error' );
		}

		return false;
	}

	/**
	 * Original DALL-E generation.
	 */
	private function generate_dalle( $prompt ) {
		if ( empty( $this->openai_key ) ) {
			Logger::log( 'OpenAI API Key is missing for DALL-E.', 'error' );
			return false;
		}

		try {
			$response = $this->get_http_client()->post( 'https://api.openai.com/v1/images/generations', [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->openai_key,
					'Content-Type'  => 'application/json',
				],
				'json'    => [
					'model'  => 'dall-e-3',
					'prompt' => $prompt,
					'n'      => 1,
					'size'   => '1024x1024',
				],
			] );

			$body = json_decode( (string) $response->getBody(), true );

			if ( isset( $body['data'][0]['url'] ) ) {
				return $body['data'][0]['url'];
			}

		} catch ( \Exception $e ) {
			Logger::log( 'OpenAI Image Generation Error: ' . $e->getMessage(), 'error' );
		}

		return false;
	}

	/**
	 * Download image from URL and save to WordPress Media Library.
	 *
	 * @param string $url     The URL of the image.
	 * @param int    $post_id The post ID to attach the image to.
	 * @return int|WP_Error The attachment ID or WP_Error on failure.
	 */
	public function save_to_media_library( $url, $post_id = 0 ) {
		
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$desc = "Generated Thumbnail for Post ID {$post_id}";
		$id   = media_sideload_image( $url, $post_id, $desc, 'id' );

        if ( is_wp_error( $id ) ) {
            Logger::log( 'Error saving image to media library: ' . $id->get_error_message(), 'error' );
        }

		return $id;
	}

}
