<?php

namespace Autoblog\Intelligence;

use Autoblog\Utils\AIClient;
use Autoblog\Utils\Logger;

/**
 * Manages Vector Storage and Retrieval for RAG.
 * Uses a flat JSON file for simplicity and portability.
 * Text -> Chunks -> Embeddings -> Search.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Intelligence
 * @author     Rasyiqi
 */
class VectorStore {

    /**
     * Path to the JSON vector store file.
     * @var string
     */
    private $store_path;

    /**
     * In-memory storage of chunks and embeddings.
     * Structure: [ 
     *   [ 'id' => '...', 'text' => '...', 'vector' => [...], 'source' => '...' ], 
     *   ... 
     * ]
     * @var array
     */
    private $memory = [];

    /**
     * AI Client for generating embeddings.
     * @var AIClient
     */
    private $ai_client;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->store_path = $upload_dir['basedir'] . '/autoblog/vector_store.json';
        
        // Ensure directory exists
        if ( ! file_exists( dirname( $this->store_path ) ) ) {
            mkdir( dirname( $this->store_path ), 0755, true );
        }

        $this->ai_client = new AIClient();
        $this->load();
    }

    /**
     * Load vectors from JSON file.
     */
    private function load() {
        Logger::log( "VectorStore: Loading from path: " . $this->store_path, 'debug' );

        if ( file_exists( $this->store_path ) ) {
            $content = file_get_contents( $this->store_path );
            
            if ( empty( $content ) ) {
                Logger::log( "VectorStore: File exists but is empty.", 'warning' );
                $this->memory = [];
                return;
            }

            $data = json_decode( $content, true );
            
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                Logger::log( "VectorStore: JSON Decode Error: " . json_last_error_msg(), 'error' );
                $this->memory = [];
            } else {
                $this->memory = is_array( $data ) ? $data : [];
                Logger::log( "VectorStore: Loaded " . count($this->memory) . " chunks.", 'info' );
            }
        } else {
            Logger::log( "VectorStore: File not found at path.", 'warning' );
            $this->memory = [];
        }
    }

    /**
     * Save vectors to JSON file.
     */
    public function save() {
        file_put_contents( $this->store_path, json_encode( $this->memory ) );
    }

    /**
     * Clear the vector store.
     */
    public function clear() {
        $this->memory = [];
        $this->save();
        Logger::log('Vector Store cleared.', 'info');
    }

    /**
     * Add a document to the store.
     * Chunks it, embeds it, and saves it.
     * 
     * @param string $text Content to store.
     * @param string $source Source identifier (filename/url).
     */
    public function add_document( $text, $source = 'unknown' ) {
        if ( empty( $text ) ) return 0;

        Logger::log("VectorStore: Processing document from $source...", 'info');

        // 1. Chunking (Simple Sentence/Length-based)
        $chunks = $this->chunk_text( $text, 800 ); // ~800 chars (approx 200 tokens)

        $success_count = 0;
        foreach ( $chunks as $chunk ) {
            // 2. Generate Embedding
            // Sleep slightly to avoid strict rate limits if adding many docs
            usleep( 200000 ); // 0.2s pause

            // Get Configured Provider
            $provider = get_option( 'autoblog_embedding_provider', 'openai' );

            $vector = $this->ai_client->create_embedding( $chunk, $provider );

            if ( $vector ) {
                $this->memory[] = [
                    'id'     => uniqid('vec_'),
                    'text'   => $chunk,
                    'vector' => $vector,
                    'source' => $source,
                    'provider' => $provider
                ];
                $success_count++;
            } else {
                Logger::log("Failed to embed chunk from $source using $provider.", 'warning');
            }
        }
        
        $this->save();
        Logger::log("VectorStore: {$success_count}/" . count($chunks) . " chunks berhasil dari $source.", 'info');

        return $success_count;
    }

    /**
     * Search for relevant chunks using Cosine Similarity.
     * 
     * @param string $query User query or Article Title.
     * @param int $limit Number of chunks to return.
     * @return array Top relevant chunks.
     */
    public function search( $query, $limit = 3 ) {
        if ( empty( $this->memory ) ) return [];

        // Get Configured Provider
        $provider = get_option( 'autoblog_embedding_provider', 'openai' );

        // 1. Embed Query
        $query_vector = $this->ai_client->create_embedding( $query, $provider );
        
        if ( ! $query_vector ) return [];

        // 2. Calculate Similarity
        $scores = [];
        foreach ( $this->memory as $index => $item ) {
            if ( ! isset( $item['vector'] ) ) continue;
            
            $similarity = $this->cosine_similarity( $query_vector, $item['vector'] );
            $scores[ $index ] = $similarity;
        }

        // 3. Sort by Score (Desc)
        arsort( $scores );

        // 4. Get Top K
        $results = [];
        $count = 0;
        foreach ( $scores as $index => $score ) {
            if ( $count >= $limit ) break;
            
            // Threshold logic (optional) - e.g. score > 0.7
            if ( $score > 0.4 ) { // somewhat relevant
                $match = $this->memory[ $index ];
                unset( $match['vector'] ); // Don't return heavy vector
                $match['score'] = $score;
                $results[] = $match;
                $count++;
            }
        }

        Logger::log("VectorStore: Search for '{$query}' returned " . count($results) . " results.", 'info');

        return $results;
    }

    /**
     * Calculate Cosine Similarity between two vectors.
     */
    private function cosine_similarity( $vec1, $vec2 ) {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ( $vec1 as $i => $val ) {
            $dotProduct += $val * $vec2[$i];
            $normA += $val * $val;
            $normB += $vec2[$i] * $vec2[$i];
        }

        if ( $normA == 0 || $normB == 0 ) return 0;

        return $dotProduct / ( sqrt( $normA ) * sqrt( $normB ) );
    }

    /**
     * Chunk text into smaller segments.
     * Tries to respect sentence boundaries.
     */
    private function chunk_text( $text, $max_length = 800 ) {
        $chunks = [];
        // Clean basic whitespace
        $text = trim( preg_replace('/\s+/', ' ', $text) );
        
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $current_chunk = '';
        
        foreach ( $sentences as $sentence ) {
            if ( strlen( $current_chunk ) + strlen( $sentence ) < $max_length ) {
                $current_chunk .= $sentence . ' ';
            } else {
                if ( ! empty( $current_chunk ) ) {
                    $chunks[] = trim( $current_chunk );
                }
                $current_chunk = $sentence . ' ';
            }
        }
        
        if ( ! empty( $current_chunk ) ) {
            $chunks[] = trim( $current_chunk );
        }

        return $chunks;
    }

    /**
     * Ringkasan singkat isi KB untuk prompt generate topik.
     *
     * TIDAK memanggil AI — hanya baca data yang sudah ada di memory.
     * Mengumpulkan: nama-nama sumber (file) + beberapa snippet pendek random.
     * Hasilnya dikirim ke AI sebagai konteks minimal untuk generate ide topik.
     *
     * @return string Ringkasan singkat (max ~500 karakter).
     */
    public function get_brief_summary() {
        if ( empty( $this->memory ) ) {
            return '';
        }

        // 1. Kumpulkan unique source names
        $sources = array();
        foreach ( $this->memory as $item ) {
            $source = isset( $item['source'] ) ? basename( $item['source'] ) : 'unknown';
            $sources[ $source ] = true;
        }
        $source_list = implode( ', ', array_keys( $sources ) );

        // 2. Ambil 3-5 snippet random (100 char masing-masing)
        $indices = array_keys( $this->memory );
        shuffle( $indices );
        $snippet_count = min( 5, count( $indices ) );
        $snippets = array();

        for ( $i = 0; $i < $snippet_count; $i++ ) {
            $text = isset( $this->memory[ $indices[$i] ]['text'] )
                ? $this->memory[ $indices[$i] ]['text']
                : '';
            $snippet = mb_substr( trim( $text ), 0, 100 );
            if ( ! empty( $snippet ) ) {
                $snippets[] = '- ' . $snippet;
            }
        }

        // 3. Gabungkan
        $summary  = "Sumber: {$source_list}\n";
        $summary .= "Total chunks: " . count( $this->memory ) . "\n";
        $summary .= "Contoh isi:\n" . implode( "\n", $snippets );

        Logger::log( 'VectorStore: get_brief_summary() generated.', 'info' );

        return $summary;
    }

    /**
     * Ambil topik-topik terbaru dari Knowledge Base untuk mode kb_only.
     *
     * Mengambil N chunk terakhir yang tersimpan di vector store
     * dan menggunakannya sebagai bahan topik artikel.
     * Chunk diurutkan dari yang paling baru (terakhir ditambahkan).
     *
     * @param int $limit Jumlah topik yang dikembalikan.
     * @return array Array berisi [ 'title' => string, 'text' => string, 'source' => string ]
     */
    public function get_recent_topics( $limit = 3 ) {
        if ( empty( $this->memory ) ) {
            Logger::log( 'VectorStore: get_recent_topics() — memory kosong, tidak ada topik.', 'warning' );
            return [];
        }

        // Ambil chunk terakhir (paling baru ditambahkan = terakhir di array)
        $recent_chunks = array_slice( $this->memory, -$limit );

        // Balik urutan agar yang paling baru di depan
        $recent_chunks = array_reverse( $recent_chunks );

        $topics = [];
        foreach ( $recent_chunks as $chunk ) {
            $text = isset( $chunk['text'] ) ? $chunk['text'] : '';

            // Generate judul dari potongan awal teks (max 80 karakter)
            $title = mb_substr( $text, 0, 80 );
            // Potong di kata terakhir yang utuh, tambahkan ellipsis
            $last_space = mb_strrpos( $title, ' ' );
            if ( $last_space !== false && $last_space > 20 ) {
                $title = mb_substr( $title, 0, $last_space ) . '...';
            }

            $topics[] = [
                'title'  => $title,
                'text'   => $text,
                'source' => isset( $chunk['source'] ) ? $chunk['source'] : 'knowledge_base',
            ];
        }

        Logger::log( 'VectorStore: get_recent_topics() mengembalikan ' . count($topics) . ' topik.', 'info' );

        return $topics;
    }

}
