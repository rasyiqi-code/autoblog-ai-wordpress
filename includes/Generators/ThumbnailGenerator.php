<?php

namespace Autoblog\Generators;

use Autoblog\Utils\Logger;
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
	private $client;

	public function __construct() {
		$this->openai_key = get_option( 'autoblog_openai_key' );
		$this->client     = new Client();
	}

	/**
	 * Generate an image based on a prompt.
	 *
	 * @param string $prompt The prompt for the image.
	 * @return string|false The URL of the generated image or false on failure.
	 */
	public function generate_thumbnail( $prompt ) {

		if ( empty( $this->openai_key ) ) {
			Logger::log( 'OpenAI API Key is missing for image generation.', 'error' );
			return false;
		}

		try {
			$response = $this->client->post( 'https://api.openai.com/v1/images/generations', [
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
