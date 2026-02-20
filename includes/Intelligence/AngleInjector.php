<?php

namespace Autoblog\Intelligence;

use Autoblog\Utils\AIClient;
use Autoblog\Utils\Logger;

/**
 * Generates unique angles/perspectives for content using AI.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Intelligence
 * @author     Rasyiqi
 */
class AngleInjector {

	/**
	 * The AI Client instance.
	 *
	 * @var AIClient
	 */
	private $ai_client;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->ai_client = new AIClient();
	}

	/**
	 * Generate an angle for the given content.
	 *
	 * @param string $content The raw content to analyze.
	 * @return string|false The generated angle or false on failure.
	 */
	public function add_human_perception( $content, $context = '' ) {
		
        // CLEAN content first to remove HTML/Scripts/Styles
        // This is critical to save tokens and avoid confusing the AI with raw code
        $cleaned_content = $this->clean_text( $content );

        // Truncate content to avoid token limits if too long
        // 4000 chars of CLEAN text is plenty for an angle
        $truncated_content = substr( $cleaned_content, 0, 4000 );

		$prompt = "You are an expert editor. Analyze the following content and suggest a unique, human-like angle for a blog post. \n\n";
        
        if ( ! empty( $context ) ) {
            $prompt .= "CONTEXT / KNOWLEDGE BASE (Use for background info): \n" . substr($context, 0, 3000) . "\n\n";
        }

        $prompt .= "Avoid generic summaries. Suggest a specific perspective, such as: \n";
        $prompt .= "- A contrarian view \n";
        $prompt .= "- A deep dive into a specific detail \n";
        $prompt .= "- A simple explanation for beginners \n";
        $prompt .= "- Connecting it to a larger trend \n\n";
        $prompt .= "Content: \n" . $truncated_content . "\n\n";
        $prompt .= "Output ONLY the angle description.";

        // Get Active Provider
        $provider = get_option( 'autoblog_ai_provider', 'openai' );
        
        // Get Model based on Provider
        $model_option_name = 'autoblog_' . $provider . '_model';
        $model = get_option( $model_option_name, 'gpt-4o' );

		$angle = $this->ai_client->generate_text( $prompt, $model, $provider );

        if ( ! $angle ) {
            Logger::log( "AngleInjector: Rantai fallback generate_text gagal sepenuhnya. Mengembalikan false.", 'error' );
            return false;
        }

        return $angle;
	}

    /**
     * Clean text to remove junk characters and save tokens.
     * (Duplicated from ArticleWriter for standalone usage)
     */
    private function clean_text( $text ) {
        if ( empty( $text ) ) return '';

        // 1. Remove script and style tags and their content
        $text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
        
        // 2. Strip HTML tags
        $text = strip_tags( $text );
        
        // 3. Decode HTML entities (convert &nbsp; to space, &amp; to &, etc)
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5 );
        
        // 4. Remove multiple whitespace/newlines
        $text = preg_replace( '/\s+/', ' ', $text );
        
        // 5. Trim
        return trim( $text );
    }

}
