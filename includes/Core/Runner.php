<?php

namespace Autoblog\Core;

use Autoblog\Utils\Logger;
use Autoblog\Sources\RSSSource;
use Autoblog\Sources\WebScraperSource;
use Autoblog\Sources\FileSource;
use Autoblog\Sources\SearchSource;  // NEW
use Autoblog\Intelligence\DataSizer;
use Autoblog\Intelligence\AngleInjector;
use Autoblog\Generators\ArticleWriter;
use Autoblog\Generators\ThumbnailGenerator;
use Autoblog\Generators\ChartGenerator;
use Autoblog\Publisher\PostManager;
use Autoblog\Intelligence\VectorStore;

/**
 * Orchestrates the entire autoblog pipeline.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Core
 * @author     Rasyiqi
 */
class Runner {

	/**
	 * Run the full pipeline.
	 */
	/**
	 * Run the full pipeline.
	 */
	public function run_pipeline() {

		Logger::log( 'Starting Autoblog Pipeline...', 'info' );

        try {

            // 0. Baca Data Source Mode (both | kb_only | triggers_only)
            $data_source_mode = get_option( 'autoblog_data_source_mode', 'both' );
            Logger::log( "Data Source Mode: {$data_source_mode}", 'info' );

            // 1. Gather Sources (hanya jika bukan kb_only)
            $sources = array();
            if ( $data_source_mode !== 'kb_only' ) {
                $sources = $this->get_configured_sources();
            }

            $all_items = array();
            
            // --- 1. Knowledge Base (RAG) Ingestion ---
            // Skip jika mode = triggers_only
            $knowledge_base = get_option( 'autoblog_knowledge', array() );
            $kb_updated = false;

            // Initialize Vector Store (dibutuhkan untuk semua mode kecuali triggers_only)
            $vector_store = new VectorStore();

            if ( $data_source_mode !== 'triggers_only' && ! empty( $knowledge_base ) ) {
                Logger::log( 'Checking Knowledge Base for new files...', 'info' );
                foreach ( $knowledge_base as $index => &$kb_item ) {
                    try {
                        // Check if already embedded
                        if ( isset( $kb_item['embedded'] ) && $kb_item['embedded'] === true ) {
                            continue;
                        }

                        // Not embedded yet
                        Logger::log( "Processing new KB file: " . $kb_item['name'], 'info' );
                        $file_source = new FileSource( $kb_item['path'] );
                        $items = $file_source->fetch_data();
                        
                        if ( ! empty( $items ) ) {
                            $total_embedded = 0;
                            foreach ( $items as $item ) {
                                $content = isset( $item['content'] ) ? $item['content'] : '';
                                $source_url = isset( $item['source_url'] ) ? $item['source_url'] : 'unknown';
                                $total_embedded += $vector_store->add_document( $content, $source_url );
                            }
                            
                            // Hanya tandai embedded jika minimal 1 chunk berhasil
                            if ( $total_embedded > 0 ) {
                                $kb_item['embedded'] = true;
                                $kb_updated = true;
                                Logger::log( "KB file {$kb_item['name']}: {$total_embedded} chunks berhasil di-embed.", 'info' );
                            } else {
                                Logger::log( "KB file {$kb_item['name']}: GAGAL — 0 chunks berhasil. File tidak ditandai embedded.", 'error' );
                            }
                        }
                    } catch ( \Exception $e ) {
                        Logger::log( "Error processing KB item {$kb_item['name']}: " . $e->getMessage(), 'error' );
                    }
                }
                unset( $kb_item ); // Break reference

                // Save status back to option
                if ( $kb_updated ) {
                    update_option( 'autoblog_knowledge', $knowledge_base );
                    Logger::log( 'Knowledge Base updated and saved.', 'info' );
                }
            }


            // --- 2. Content Triggers (RSS, Search, Web) ---
            // Skip jika mode = kb_only
            if ( $data_source_mode !== 'kb_only' ) {
            Logger::log( 'Found ' . count( $sources ) . ' configured triggers.', 'info' );

            foreach ( $sources as $source_config ) {
                try {
                    $source = null;
                    
                    // Skip files in main loop (Legacy check)
                    if ( $source_config['type'] === 'file' ) continue;

                    switch ( $source_config['type'] ) {
                        case 'rss':
                            $match_keywords = isset( $source_config['match_keywords'] ) ? $source_config['match_keywords'] : '';
                            $negative_keywords = isset( $source_config['negative_keywords'] ) ? $source_config['negative_keywords'] : '';
                            $source = new RSSSource( $source_config['url'], $match_keywords, $negative_keywords );
                            break;
                        case 'web':
                            $match_keywords = isset( $source_config['match_keywords'] ) ? $source_config['match_keywords'] : '';
                            $negative_keywords = isset( $source_config['negative_keywords'] ) ? $source_config['negative_keywords'] : '';
                            $source = new WebScraperSource( $source_config['url'], $source_config['selector'], $match_keywords, $negative_keywords );
                            break;
                        case 'web_search':
                            $match_keywords = isset( $source_config['match_keywords'] ) ? $source_config['match_keywords'] : '';
                            $negative_keywords = isset( $source_config['negative_keywords'] ) ? $source_config['negative_keywords'] : '';
                            $source = new SearchSource( $source_config['url'], $match_keywords, $negative_keywords ); 
                            break;
                    }

                    if ( $source ) {
                        $items = $source->fetch_data();
                        $all_items = array_merge( $all_items, $items );
                    }
                    
                    sleep( 2 ); 
                } catch ( \Exception $e ) {
                    Logger::log( "Error processing source type {$source_config['type']}: " . $e->getMessage(), 'error' );
                }
            }
            } // end if data_source_mode !== kb_only

            // --- Mode kb_only: Generate topik dari Knowledge Base ---
            // Alur hemat token:
            //   1. Ambil ringkasan KB (tanpa AI call)
            //   2. Generate 1 ide topik via AI (prompt ringkas, hemat token)
            //   3. Vector search KB untuk cari data yang cocok
            //   4. Buat item dari ide + data relevan
            if ( $data_source_mode === 'kb_only' && empty( $all_items ) ) {
                Logger::log( 'Mode kb_only: Generating ide topik dari Knowledge Base...', 'info' );

                // Step 1: Ringkasan KB (tanpa AI call — gratis)
                $kb_summary = $vector_store->get_brief_summary();
                if ( empty( $kb_summary ) ) {
                    Logger::log( 'Mode kb_only: Knowledge Base kosong. Upload file terlebih dahulu.', 'warning' );
                    return;
                }

                // Step 2: Generate 1 ide topik via AI (prompt singkat, hemat token)
                $topic_idea = $this->generate_topic_idea( $kb_summary );
                if ( empty( $topic_idea ) ) {
                    Logger::log( 'Mode kb_only: Gagal generate ide topik.', 'error' );
                    return;
                }
                Logger::log( "Mode kb_only: Ide topik = \"{$topic_idea}\"", 'info' );

                // Step 3: Vector search KB — cari data yang cocok (tanpa AI call tambahan, hanya embedding query)
                $relevant_chunks = $vector_store->search( $topic_idea, 5 );
                $combined_content = '';
                foreach ( $relevant_chunks as $chunk ) {
                    $combined_content .= $chunk['text'] . "\n\n";
                }
                Logger::log( 'Mode kb_only: Vector search menemukan ' . count($relevant_chunks) . ' chunk relevan.', 'info' );

                // Step 4: Buat item dari ide + data
                $all_items[] = array(
                    'title'       => $topic_idea,
                    'content'     => ! empty( $combined_content ) ? $combined_content : $kb_summary,
                    'source_url'  => 'knowledge_base',
                    'source_type' => 'kb_internal',
                );
            }

            if ( empty( $all_items ) ) {
                Logger::log( 'No items found from any source.', 'warning' );
                return;
            }
            
            $target_data = $all_items;

            if ( empty( $target_data ) ) {
                 Logger::log( 'Target data is empty after filtering.', 'warning' );
                 return;
            }

            $primary_item = $target_data[0];
            $query_text = isset($primary_item['title']) ? $primary_item['title'] : substr($primary_item['content'], 0, 200);

            // 2. Retrieve Relevant Context via Vector Search
            // Skip jika mode = triggers_only (tidak ada KB) atau kb_only (sudah di-search di atas)
            $relevant_chunks = array();
            if ( $data_source_mode !== 'triggers_only' && $data_source_mode !== 'kb_only' ) {
                $relevant_chunks = $vector_store->search( $query_text, 3 );
            }
            
            $context_string = '';
            if ( ! empty( $relevant_chunks ) ) {
                foreach ( $relevant_chunks as $chunk ) {
                    $context_string .= "--- RELEVANT KNOWLEDGE ---\n";
                    $context_string .= $chunk['text'] . "\n\n";
                }
                Logger::log( "Vector Search retrieved " . count($relevant_chunks) . " chunks for query: '{$query_text}'", 'info' );
            }

            // 3. Intelligence: Angle Injection
            $injector = new AngleInjector();
            // Pass context to help AI understand the domain better
            $angle = $injector->add_human_perception( $primary_item['content'], $context_string );

            // ... (Rest of pipeline) ...
            // Again, line 142 in original file was `// ... (Rest of pipeline) ...` ?
            // No, line 142 was `// ... (Rest of pipeline) ...` in step 146? 
            // Wait, let's look at step 146.
            // Line 142: // ... (Rest of pipeline) ...
            // Line 144: if ( ! $angle ) {
            // It seems the file on disk DOES contain these comments placeholder? 
            // "The above content shows the entire, complete file contents of the requested file."
            // If so, the explicit code is literally `// ...`.
            // This is strange. The user might have given me a file with placeholders.
            // I will strictly implement robust error handling around what is there.

            if ( ! $angle ) {
                 Logger::log( 'Failed to generate angle (Primary & Fallback). Aborting pipeline.', 'error' );
                 return;
            }

            Logger::log( "Generated Angle: {$angle}", 'info' );

            // --- 3.5 Deep Research (Advanced Feature) ---
            $research_context = '';
            if ( get_option( 'autoblog_enable_deep_research' ) ) {
                require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Intelligence/ResearchAgent.php';
                $research_agent = new \Autoblog\Intelligence\ResearchAgent();
                $research_report = $research_agent->conduct_research( $query_text );
                if ( ! empty( $research_report ) ) {
                    $research_context = "\n\n--- DEEP RESEARCH REPORT ---\n" . $research_report;
                    Logger::log( "Deep Research completed and added to context.", 'info' );
                }
            }

            // Combine Contexts (Vector + Research)
            $full_context = $context_string . $research_context;
            
            // Rate Limit Protection: Sleep 3 seconds before next AI call (Article Generation)
            sleep( 3 );

            // 4. Content Generation: Article
            $writer = new ArticleWriter();

            // --- Personality Fine-Tuning (Advanced Feature) ---
            if ( get_option( 'autoblog_enable_personality' ) ) {
                $personality_samples = get_option( 'autoblog_personality_samples', '' );
                if ( ! empty( $personality_samples ) ) {
                     $full_context .= "\n\n--- STYLE AND TONE GUIDE ---\nEmulate the following writing style:\n" . $personality_samples;
                     Logger::log( "Personality samples injected into context.", 'info' );
                }
            }

            // Pass enriched context to writer
            $html_content = $writer->write_article( $target_data, $angle, isset($full_context) ? $full_context : $context_string );

            if ( ! $html_content ) {
                 Logger::log( 'Failed to generate article content.', 'error' );
                 return;
            }

            // --- 4.5 Interlinking (Advanced Feature) ---
            if ( get_option( 'autoblog_enable_interlinking' ) ) {
                 require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Intelligence/Interlinker.php';
                 $interlinker = new \Autoblog\Intelligence\Interlinker();
                 $related_posts = $interlinker->get_relevant_posts( $query_text );
                 // Inject links
                 if ( ! empty( $related_posts ) ) {
                     $html_content = $interlinker->inject_links( $html_content, $related_posts );
                     Logger::log( "Injected " . count($related_posts) . " internal links.", 'info' );
                 }
            }

            // 5. Content Generation: Thumbnail
            $thumbnail_gen = new ThumbnailGenerator();
            // Create a prompt based on title + angle
            $title_prompt = isset( $primary_item['title'] ) ? $primary_item['title'] : substr( $primary_item['content'], 0, 50 );
            $image_prompt = "A blog post illustration for: '{$title_prompt}'. Concept: {$angle}. High quality, digital art.";
            $thumbnail_url = $thumbnail_gen->generate_thumbnail( $image_prompt );

            // 6. Publisher
            $publisher = new PostManager();
            
            $source_url = isset( $primary_item['source_url'] ) ? $primary_item['source_url'] : '';
            $source_type = isset( $primary_item['source_type'] ) ? $primary_item['source_type'] : '';

            // For Search sources, we want a NEW post every time plugin runs, not update existing.
            // So we append a timestamp to the source_url (query) to make it unique for PostManager.
            if ( strpos( $source_type, 'search' ) !== false || strpos( $source_type, 'ai_' ) !== false || strpos( $source_type, 'bing_' ) !== false ) {
                $source_url .= ' | ' . date('Y-m-d H:i:s'); 
            }

            $source_data = array(
                'title'      => isset( $primary_item['title'] ) ? $primary_item['title'] : '',
                'source_url' => $source_url
            );

            $post_id = $publisher->create_or_update_post( $source_data, $html_content, $thumbnail_url );

            if ( is_wp_error( $post_id ) ) {
                 Logger::log( 'Failed to publish post.', 'error' );
            } else {
                 Logger::log( "Successfully published/updated Post ID: {$post_id}", 'info' );
            }

        } catch ( \Exception $e ) {
            Logger::log( 'Critical error in Autoblog pipeline: ' . $e->getMessage(), 'error' );
        }

	}

    /**
     * Get configured sources. 
     * In a real plugin, this would come from get_option('autoblog_sources').
     * For now, returning a dummy array for testing if options are empty.
     */
    private function get_configured_sources() {
        // TODO: Implement UI to save these sources
        // For now, we check if there are any saved sources, if not return a default
        $sources = get_option( 'autoblog_sources', array() );
        
        if ( ! is_array( $sources ) ) {
            $sources = array();
        } else {
             // Filter out any non-array entries just in case
             $sources = array_filter( $sources, 'is_array' );
        }

        if ( empty( $sources ) ) {
            // Default mock source for testing logic flow
            // $sources[] = array( 'type' => 'rss', 'url' => 'https://techcrunch.com/feed/' );
        }

        return $sources;
    }

    /**
     * Generate 1 ide topik artikel berdasarkan ringkasan KB.
     *
     * Prompt didesain SANGAT singkat agar hemat token.
     * AI hanya terima ringkasan KB + daftar topik lama → hasilkan 1 ide baru.
     * Topik yang sudah pernah digenerate disimpan untuk menghindari duplikat.
     *
     * @param string $kb_summary Ringkasan singkat dari VectorStore::get_brief_summary().
     * @return string|false Ide topik (1 kalimat) atau false jika gagal.
     */
    private function generate_topic_idea( $kb_summary ) {
        $ai_client = new \Autoblog\Utils\AIClient();

        // Ambil topik yang sudah pernah dihasilkan (anti-duplikat)
        $used_topics = get_option( 'autoblog_used_topics', array() );
        if ( ! is_array( $used_topics ) ) {
            $used_topics = array();
        }

        // Format topik lama untuk prompt
        $used_topics_text = '';
        if ( ! empty( $used_topics ) ) {
            $used_topics_text = "\n\nTOPIK YANG SUDAH PERNAH DITULIS (JANGAN ulangi):\n";
            foreach ( array_slice( $used_topics, -10 ) as $t ) {
                $used_topics_text .= "- {$t}\n";
            }
        }

        // Prompt ringkas — hemat token
        $prompt  = "Kamu adalah content strategist. Berdasarkan Knowledge Base berikut, ";
        $prompt .= "buat 1 IDE TOPIK ARTIKEL yang menarik dan unik dalam BAHASA INDONESIA.\n\n";
        $prompt .= "KNOWLEDGE BASE:\n{$kb_summary}\n";
        $prompt .= $used_topics_text;
        $prompt .= "\nATURAN:\n";
        $prompt .= "- Balas HANYA dengan 1 kalimat judul topik, tanpa penjelasan tambahan.\n";
        $prompt .= "- Topik harus relevan dengan isi Knowledge Base.\n";
        $prompt .= "- Topik harus menarik, spesifik, dan belum pernah ditulis.";

        // Gunakan provider + model yang aktif
        $provider = get_option( 'autoblog_ai_provider', 'openai' );
        $model_option = 'autoblog_' . $provider . '_model';
        $model = get_option( $model_option, 'gpt-4o' );

        // Temperature rendah agar fokus dan tidak terlalu random
        $topic = $ai_client->generate_text( $prompt, $model, $provider, 0.7 );

        if ( ! empty( $topic ) ) {
            // Bersihkan — kadang AI menambahkan tanda kutip
            $topic = trim( $topic, " \t\n\r\0\x0B\"'" );

            // Simpan ke tracking anti-duplikat (max 20 terakhir)
            $used_topics[] = $topic;
            if ( count( $used_topics ) > 20 ) {
                $used_topics = array_slice( $used_topics, -20 );
            }
            update_option( 'autoblog_used_topics', $used_topics );

            return $topic;
        }

        return false;
    }

}
