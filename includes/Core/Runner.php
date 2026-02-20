<?php

namespace Autoblog\Core;

use Autoblog\Utils\Logger;
use Autoblog\Sources\RSSSource;
use Autoblog\Sources\WebScraperSource;
use Autoblog\Sources\FileSource;
use Autoblog\Sources\SearchSource;
use Autoblog\Intelligence\DataSizer;
use Autoblog\Intelligence\AngleInjector;
use Autoblog\Generators\ArticleWriter;
use Autoblog\Generators\ThumbnailGenerator;
use Autoblog\Publisher\PostManager;
use Autoblog\Publisher\AuthorManager;
use Autoblog\Intelligence\VectorStore;
use Autoblog\Intelligence\IdeationAgent;

/**
 * Orchestrates the entire autoblog pipeline in a modular way.
 * 1. Ingestion: Collect raw data into Vector Store.
 * 2. Ideation: Brainstorm topics based on Knowledge Base.
 * 3. Production: Write and Publish articles.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Core
 * @author     Rasyiqi
 */
class Runner {

    /**
     * Run the modular pipeline.
     */
    public function run_pipeline() {
        Logger::log( 'Starting Modular Autoblog Pipeline...', 'info' );

        try {
            $this->run_ingestion_phase();

            $selected_idea = $this->run_ideation_phase();
            if ( ! $selected_idea ) return;

            $this->run_production_phase( $selected_idea );

        } catch ( \Exception $e ) {
            Logger::log( 'Critical error in Modular Pipeline: ' . $e->getMessage(), 'error' );
        }
    }

    /**
     * Public manual trigger for Ingestion phase.
     */
    public function run_ingestion_phase() {
        try {
            $data_source_mode = get_option( 'autoblog_data_source_mode', 'both' );
            $sources = $this->get_configured_sources();
            $vector_store = new VectorStore();
            $this->stage_ingestion( $data_source_mode, $sources, $vector_store );
        } catch ( \Throwable $e ) {
            Logger::log( 'Ingestion Phase Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
            throw $e;
        }
    }

    /**
     * Public manual trigger for Ideation phase.
     */
    public function run_ideation_phase() {
        try {
            $sources = $this->get_configured_sources();
            $vector_store = new VectorStore();
            return $this->stage_ideation( $sources, $vector_store );
        } catch ( \Throwable $e ) {
            Logger::log( 'Ideation Phase Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
            throw $e;
        }
    }

    /**
     * Public manual trigger for Production phase.
     * 
     * @param array|null $idea Optional specific idea to publish. If null, uses the latest completed idea.
     */
    public function run_production_phase( $idea = null ) {
        try {
            if ( ! $idea ) {
                $ideation_data = get_option( 'autoblog_last_ideation_data', array() );
                if ( empty( $ideation_data ) || $ideation_data['status'] !== 'completed' ) {
                    Logger::log( 'Production: No completed ideation data found to publish.', 'warning' );
                    return;
                }
                $idea = array(
                    'title' => $ideation_data['title'],
                    'angle' => $ideation_data['angle']
                );
            }

            $vector_store = new VectorStore();
            $this->stage_production( $idea, $vector_store );
        } catch ( \Throwable $e ) {
            Logger::log( 'Production Phase Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
            throw $e;
        }
    }

    /**
     * Phase 1: Ingestion
     * Standardizes all data collection into the Vector Store.
     */
    private function stage_ingestion( $mode, $sources, $vector_store ) {
        if ( $mode === 'kb_only' ) return;

        Logger::log( 'Stage 1 [Ingestion]: Processing content sources...', 'info' );
        
        $processed_count = 0;
        $ingestion_data = array(
            'timestamp' => current_time( 'mysql' ),
            'status'    => 'running',
            'sources'   => array()
        );

        foreach ( $sources as $config ) {
            try {
                if ( $config['type'] === 'file' ) continue;

                $query = isset( $config['url'] ) ? $config['url'] : '';
                
                // Dynamic Search Research if enabled
                if ( $config['type'] === 'web_search' && get_option( 'autoblog_enable_dynamic_search' ) ) {
                    $ideator = new IdeationAgent();
                    $dynamic_q = $ideator->propose_research_query( $query, $vector_store->get_brief_summary() );
                    if ( ! empty( $dynamic_q ) ) {
                        Logger::log( "Ingestion: Using dynamic research query '{$dynamic_q}'", 'info' );
                        $query = $dynamic_q;
                    }
                }

                $source = null;
                switch ( $config['type'] ) {
                    case 'rss':
                        $source = new RSSSource( $config['url'], isset($config['match_keywords']) ? $config['match_keywords'] : '' );
                        break;
                    case 'web':
                        $source = new WebScraperSource( $config['url'], $config['selector'] );
                        break;
                    case 'web_search':
                        $source = new SearchSource( $query );
                        break;
                }

                if ( $source ) {
                    $items = $source->fetch_data();
                    foreach ( $items as $item ) {
                        $content = (isset($item['title']) ? $item['title'] . "\n" : "") . $item['content'];
                        $vector_store->add_document( $content, array( 'source' => $config['type'], 'url' => isset($item['source_url']) ? $item['source_url'] : '' ) );
                    }
                    $processed_count++;
                    $ingestion_data['sources'][] = $config['type'] . ': ' . $query;
                }
            } catch ( \Exception $e ) {
                Logger::log( "Ingestion Error ({$config['type']}): " . $e->getMessage(), 'error' );
            }
        }

        // Also process Knowledge Base files if any
        $kb_files = get_option( 'autoblog_knowledge', array() );
        if ( ! empty( $kb_files ) ) {
             foreach ( $kb_files as &$kb_item ) {
                 if ( isset( $kb_item['embedded'] ) && $kb_item['embedded'] ) continue;
                 try {
                     $fs = new FileSource();
                     $parsed = $fs->parse_file( $kb_item['path'] );
                     foreach ( $parsed as $p ) {
                         $vector_store->add_document( $p['content'], array( 'name' => $kb_item['name'] ) );
                     }
                     $kb_item['embedded'] = true;
                     $processed_count++;
                     $ingestion_data['sources'][] = 'File: ' . $kb_item['name'];
                 } catch ( \Exception $e ) {
                     Logger::log( "KB Ingestion Error ({$kb_item['name']}): " . $e->getMessage(), 'error' );
                 }
             }
             update_option( 'autoblog_knowledge', $kb_files );
        }

        $vector_store->save();
        
        $ingestion_data['status'] = 'completed';
        $ingestion_data['count']  = $processed_count;
        update_option( 'autoblog_last_ingestion_data', $ingestion_data );
    }

    /**
     * Phase 2: Ideation
     */
    private function stage_ideation( $sources, $vector_store ) {
        Logger::log( 'Stage 2 [Ideation]: Brainstorming unique topics...', 'info' );
        
        update_option( 'autoblog_last_ideation_data', array( 'status' => 'running', 'timestamp' => current_time('mysql') ) );

        $ideator = new IdeationAgent();
        $seed = 'Advanced Technology';
        foreach ( $sources as $s ) {
            if ( $s['type'] === 'web_search' && ! empty( $s['url'] ) ) {
                $seed = $s['url'];
                break;
            }
        }

        $ideas = $ideator->brainstorm_topics( $seed, $vector_store->get_brief_summary(), 1 );
        $idea = ! empty( $ideas ) ? $ideas[0] : null;

        if ( $idea ) {
            update_option( 'autoblog_last_ideation_data', array(
                'status'    => 'completed',
                'timestamp' => current_time('mysql'),
                'title'     => $idea['title'],
                'angle'     => $idea['angle']
            ));
        } else {
            update_option( 'autoblog_last_ideation_data', array( 'status' => 'failed', 'timestamp' => current_time('mysql') ) );
        }

        return $idea;
    }

    /**
     * Phase 3: Production
     */
    private function stage_production( $idea, $vector_store ) {
        $topic = $idea['title'];
        $angle = $idea['angle'];

        Logger::log( "Stage 3 [Production]: Writing article for '{$topic}'", 'info' );

        update_option( 'autoblog_last_production_data', array(
            'status'    => 'running',
            'timestamp' => current_time('mysql'),
            'topic'     => $topic
        ));

        $publisher = new PostManager();
        if ( $publisher->post_exists_by_title( $topic ) ) {
            Logger::log( "Production: Skipping '{$topic}' because it already exists.", 'info' );
            update_option( 'autoblog_last_production_data', array( 'status' => 'skipped', 'topic' => $topic, 'timestamp' => current_time('mysql') ) );
            return;
        }

        // Gather RAG Context
        $context = "";
        $chunks = $vector_store->search( $topic . " " . $angle, 8 );
        foreach ( $chunks as $c ) { $context .= $c['text'] . "\n\n"; }

        // Deep Research if enabled
        if ( get_option( 'autoblog_enable_deep_research' ) ) {
            require_once dirname( __DIR__ ) . '/Intelligence/ResearchAgent.php';
            $research_agent = new \Autoblog\Intelligence\ResearchAgent();
            $report = $research_agent->conduct_research( $topic );
            $context .= "\n\n--- RESEARCH REPORT ---\n" . $report;
        }

        // Pick an Author Persona & Style
        $author_mngr  = new AuthorManager();
        $strategy     = get_option( 'autoblog_author_strategy', 'random' );
        $fixed_id     = (int) get_option( 'autoblog_author_fixed_id', 0 );
        $author_id    = $author_mngr->pick_author( $strategy, $fixed_id );
        $persona_data = $author_mngr->get_author_persona_data( $author_id );

        $writer = new ArticleWriter();
        $target_data = array( array( 'title' => $topic, 'content' => $context, 'source_url' => 'modular_pipeline', 'source_type' => 'ai_modular' ) );
        $html = $writer->write_article( $target_data, $angle, $context, $persona_data );

        if ( ! $html ) {
            Logger::log( "Runner: Gagal menulis artikel untuk topik '{$topic}'.", 'error' );
            update_option( 'autoblog_last_production_data', array( 'status' => 'failed', 'topic' => $topic, 'timestamp' => current_time('mysql') ) );
            return;
        }

        $thumb = new ThumbnailGenerator();
        $img_url = $thumb->generate_thumbnail( "Article illustration: {$topic}. Concept: {$angle}" );
            
        $source_info = array( 'title' => $topic, 'source_url' => 'ai_modular_' . time() );
        $post_id = $publisher->create_or_update_post( $source_info, $html, $img_url, $author_id );
        
        update_option( 'autoblog_last_production_data', array(
            'status'    => 'completed',
            'timestamp' => current_time('mysql'),
            'topic'     => $topic,
            'post_id'   => $post_id,
            'author_id' => $author_id
        ));

        Logger::log( "Successfully published article ID: {$post_id} by Author ID: {$author_id}", 'info' );
    }

    private function get_configured_sources() {
        $sources = get_option( 'autoblog_sources', array() );
        return is_array( $sources ) ? array_filter( $sources, 'is_array' ) : array();
    }
}
