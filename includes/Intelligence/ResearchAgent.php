<?php

namespace Autoblog\Intelligence;

use Autoblog\Utils\AIClient;
use Autoblog\Sources\SearchSource;
use Autoblog\Utils\Logger;

/**
 * Deep Research Agent.
 * Performs multi-step research to gather facts before writing.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Intelligence
 * @author     Rasyiqi
 */
class ResearchAgent {

    private $ai_client;

    public function __construct() {
        $this->ai_client = new AIClient();
    }

    /**
     * Conduct deep research on a topic.
     * 
     * @param string $topic The topic to research.
     * @return string A comprehensive research report.
     */
    public function conduct_research( $topic ) {
        if ( ! get_option( 'autoblog_enable_deep_research' ) ) {
            return '';
        }

        Logger::log( "ResearchAgent: Starting Multi-Hop Deep Research on '{$topic}'...", 'info' );

        $all_findings = "";

        // --- ROUND 1: Exploratory Search ---
        Logger::log( "ResearchAgent: [Round 1] Generating initial questions...", 'info' );
        $questions_r1 = $this->generate_research_questions( $topic );
        
        $findings_r1 = "";
        foreach ( $questions_r1 as $q ) {
            $res = $this->perform_search( $q );
            $findings_r1 .= "## Q1: {$q}\n{$res}\n\n";
        }
        $all_findings .= $findings_r1;

        // --- ROUND 2: Deep Dive (Follow-up) ---
        // AI analyzes Round 1 findings and asks deeper questions
        Logger::log( "ResearchAgent: [Round 2] Analyzing findings and generating follow-up questions...", 'info' );
        $questions_r2 = $this->analyze_and_generate_followup( $topic, $findings_r1 );
        
        if ( ! empty( $questions_r2 ) ) {
            foreach ( $questions_r2 as $q ) {
                $res = $this->perform_search( $q );
                $all_findings .= "## Q2 (Deep Dive): {$q}\n{$res}\n\n";
            }
        }

        Logger::log( "ResearchAgent: Research complete.", 'info' );

        return "DEEP RESEARCH REPORT FOR: {$topic}\n\n" . $all_findings;
    }

    /**
     * Analyze previous findings and generate follow-up questions.
     */
    private function analyze_and_generate_followup( $topic, $current_findings ) {
        $prompt  = "Topic: '{$topic}'\n";
        $prompt .= "Initial Research Findings:\n" . substr($current_findings, 0, 3000) . "\n\n";
        $prompt .= "Based on the findings above, identify MISSING information or areas needing clarifications.\n";
        $prompt .= "Generate 2 follow-up search queries to dig deeper or verify facts.\n";
        $prompt .= "Return ONLY the 2 queries, one per line.";

        // Uses default model
        $response = $this->ai_client->generate_text( $prompt ); 
        
        if ( ! $response ) return [];

        $lines = explode( "\n", $response );
        return array_filter( array_map( 'trim', $lines ) );
    }

    private function generate_research_questions( $topic ) {
        // AI Call to get 3 key questions
        $prompt = "I want to write an in-depth article about: '{$topic}'. \n";
        $prompt .= "Generate 3 specific search queries to find hard data, statistics, or expert opinions that would make this article authoritative. \n";
        $prompt .= "Return ONLY the 3 queries, one per line.";

        $response = $this->ai_client->generate_text( $prompt );
        $lines = explode( "\n", $response );
        return array_filter( array_map( 'trim', $lines ) );
    }

    private function perform_search( $query ) {
        // Gunakan SearchSource untuk mencari data nyata di web
        // Memanfaatkan konfigurasi SerpApi/Brave yang sudah ada
        try {
            $search_source = new SearchSource( $query );
            $results = $search_source->fetch_data();

            if ( empty( $results ) ) {
                return "No search results found for '{$query}'.";
            }

            $summary = "";
            // Ambil 3 hasil teratas
            $top_results = array_slice( $results, 0, 3 );
            
            foreach ( $top_results as $item ) {
                $title = isset( $item['title'] ) ? $item['title'] : 'No Title';
                $desc  = isset( $item['description'] ) ? $item['description'] : '';
                $content = isset( $item['content'] ) ? mb_substr( strip_tags($item['content']), 0, 300 ) : '';
                
                $summary .= "- **{$title}**\n";
                $summary .= "  Snippet: {$desc}\n";
                $summary .= "  Content: {$content}...\n\n";
            }

            return $summary;

        } catch ( \Exception $e ) {
            Logger::log( "ResearchAgent: Search error for '{$query}': " . $e->getMessage(), 'error' );
            return "Error retrieving search results.";
        }
    }
}
