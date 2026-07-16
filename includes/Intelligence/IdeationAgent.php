<?php

namespace Autoblog\Intelligence;

use Autoblog\Utils\AIClient;
use Autoblog\Utils\Logger;
use Autoblog\Utils\OptionCache;
use Autoblog\Publisher\PostManager;

/**
 * Ideation Agent: The "Editor-in-Chief" of the pipeline.
 * Responsible for scanning the Knowledge Base and Seed keywords to brainstorm 
 * unique, non-repetitive topic and angle combinations.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Intelligence
 * @author     Rasyiqi
 */
class IdeationAgent {

    /**
     * @var AIClient
     */
    private $ai_client;

    /**
     * @var PostManager
     */
    private $post_manager;

    public function __construct() {
        $this->ai_client    = new AIClient();
        $this->post_manager = new PostManager();
    }

    /**
     * Brainstorm unique topics based on available knowledge and seed seeds.
     *
     * @param string $seed      The core keyword/topic seed.
     * @param string $kb_summary A brief summary of what's currently in the Vector Store.
     * @param int    $count     How many ideas to generate.
     * @return array List of arrays containing 'title' and 'angle'.
     */
    public function brainstorm_topics( $seed, $kb_summary = '', $count = 1 ) {
        Logger::log( "IdeationAgent: Starting brainstorm for seed: '{$seed}'", 'debug' );
        
        $used_topics = OptionCache::get( 'autoblog_used_topics', array() );
        if ( ! is_array( $used_topics ) ) $used_topics = array();

        $used_topics_text = "";
        if ( ! empty( $used_topics ) ) {
            $used_topics_text = "\n\nDaftar topik yang SUDAH PERNAH ditulis (JANGAN diulangi):\n";
            foreach ( array_slice( $used_topics, -20 ) as $t ) {
                $used_topics_text .= "- {$t}\n";
            }
        }

        $prompt  = "Kamu adalah Pemimpin Redaksi (Editor-in-Chief) majalah teknologi dan bisnis papan atas.\n";
        $prompt .= "Tugasmu adalah melakukan BRAINSTORMING topik artikel yang unik, mendalam, dan provokatif.\n\n";
        $prompt .= "SEED KEYWORD: '{$seed}'\n";
        
        if ( ! empty( $kb_summary ) ) {
            $prompt .= "\nRINGKASAN DATA (Knowledge Base) YANG TERSEDIA:\n{$kb_summary}\n";
            $prompt .= "Gunakan data di atas untuk mencari celah informasi atau sudut pandang yang belum banyak dibahas.\n";
        }

        $prompt .= $used_topics_text;

        $prompt .= "\nINSTRUKSI KHUSUS:\n";
        $prompt .= "1. Buatlah {$count} ide topik.\n";
        $prompt .= "2. Setiap ide harus memiliki 'Judul' yang menangkap perhatian (Click-worthy) dan 'Angle' (Sudut Pandang) yang spesifik.\n";
        $prompt .= "3. JANGAN gunakan bahasa klise AI. Jadilah kreatif, sarkas, atau kontrian jika perlu.\n";
        $prompt .= "\nINSTRUKSI KHUSUS PENULISAN JUDUL (MANIFESTO):\n";
        $prompt .= "1. ANTI-KOLON (:): Dilarang menggunakan format 'Topik: Penjelasan'. Judul harus mengalir sebagai satu kalimat atau frasa utuh yang alami.\n";
        $prompt .= "2. DIKSI MANUSIA (FOMO): Hindari kata kaku seperti 'Komprehensif', 'Strategi', 'Lanskap', 'Menavigasi'. Gunakan kata pemicu emosi atau rasa sakit seperti 'Bongkar', 'Nyesel', 'Rahasia', 'Tanpa Ribet', 'Jangan Lakukan'.\n";
        $prompt .= "3. PROVOKATIF: Tantang asumsi pembaca atau ajukan pertanyaan menohok. Jangan hanya memberi tahu isi.\n";
        $prompt .= "4. FLEXIBLE HOW-TO: Hindari rumus kaku 'Cara X dalam Y Langkah'. Tambahkan benefit spesifik atau atasi keraguan (Contoh: '...Bahkan Kalau Kamu Bukan Ahli').\n";
        $prompt .= "5. IRAMA VARIATIF: Panjang judul harus bervariasi, jangan seragam.\n";
        $prompt .= "6. ANGKA GANJIL: Jika daftar, gunakan angka ganjil atau acak (7, 13, 23) daripada angka bulat (5, 10).\n\n";

        $prompt .= "FORMAT OUTPUT: JSON valid berupa array of objects. Contoh: [{\"title\": \"...\", \"angle\": \"...\"}]\n";
        $prompt .= "Kembalikan HANYA JSON!";

        $provider = OptionCache::get( 'autoblog_ai_provider', 'openai' );
        $model = \Autoblog\Utils\ModelCatalog::get_active_model( $provider );
        // Use higher temperature for brainstorming
        $response = $this->ai_client->generate_text( $prompt, $model, $provider, 0.85 );

        if ( ! $response ) {
            Logger::log( "IdeationAgent: Gagal mendapatkan ide dari AI.", 'error' );
            return array();
        }

        // Strip possible markdown blocks
        $json_clean = preg_replace( '/^```json\s*|```\s*$/i', '', trim( $response ) );
        Logger::log( "IdeationAgent: Cleaned JSON for decoding: " . substr($json_clean, 0, 100) . "...", 'debug' );
        
        $ideas = json_decode( $json_clean, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            Logger::log( "IdeationAgent: JSON Decode Error: " . json_last_error_msg(), 'error' );
        }

        if ( ! is_array( $ideas ) ) {
            Logger::log( "IdeationAgent: AI tidak memberikan JSON valid. Response: " . substr($response, 0, 100), 'warning' );
            return array();
        }

        // Deduplication against existing database (Bug #13 Fix: cegah duplikat internal)
        $unique_ideas = array();
        $seen_titles = array(); // Track internal dupes in this batch
        foreach ( $ideas as $idea ) {
            if ( isset( $idea['title'] ) && ! $this->post_manager->post_exists_by_title( $idea['title'] ) ) {
                // Skip if we already added this exact title in this run
                $title_key = mb_strtolower( trim( $idea['title'] ) );
                if ( isset( $seen_titles[$title_key] ) ) {
                    continue;
                }
                $seen_titles[$title_key] = true;
                
                $unique_ideas[] = $idea;
                // Add to temporary used topics to prevent internal duplicates in this run
                $used_topics[] = $idea['title'];
            }
        }

        // Update used topics log
        if ( count( $used_topics ) > 50 ) $used_topics = array_slice( $used_topics, -50 );
        OptionCache::set( 'autoblog_used_topics', $used_topics );

        return $unique_ideas;
    }

    /**
     * Generate 1 specific search query for data ingestion if current KB is thin.
     */
    public function propose_research_query( $seed, $kb_summary = '' ) {
        $prompt = "Berdasarkan seed keyword '{$seed}', buat 1 kueri pencarian Google spesifik (3-6 kata) untuk memperdalam wawasan kita tentang topik tersebut.\n";
        if ( ! empty( $kb_summary ) ) {
            $prompt .= "Data kita saat ini sudah mencakup: {$kb_summary}. Cari sesuatu yang BARU atau BELUM lengkap.\n";
        }
        $prompt .= "Kembalikan HANYA kueri pencariannya saja tanpa penjelasan.";

        $provider = OptionCache::get( 'autoblog_ai_provider', 'openai' );
        $model = \Autoblog\Utils\ModelCatalog::get_active_model( $provider );
        $query = $this->ai_client->generate_text( $prompt, $model, $provider );
        return trim( $query, " \"'" );
    }

}
