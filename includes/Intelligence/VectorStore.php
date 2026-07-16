<?php

namespace Autoblog\Intelligence;

use Autoblog\Utils\AIClient;
use Autoblog\Utils\Logger;
use Autoblog\Utils\OptionCache;

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
     * Maximum number of chunks to keep in the vector store.
     * Prevents unbounded growth and O(n) search slowdown.
     */
    const MAX_MEMORY_CHUNKS = 10000;

    /**
     * Pre-filter threshold: hanya hitung cosine similarity untuk chunk
     * yang memiliki kemiripan keyword dengan query.
     * Mengurangi O(n) jadi O(m) dengan m < n.
     */
    const KEYWORD_FILTER_MIN_CHARS = 50;

    /**
     * AI Client for generating embeddings.
     * @var AIClient
     */
    private $ai_client;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        
        // 4. Isolasi Vector Database (Multi-Collection) berdasarkan dimensi model Provider
        $provider = OptionCache::get( 'autoblog_embedding_provider', 'openai' );
        $safe_provider = sanitize_file_name( strtolower( $provider ) );
        
        $this->store_path = $upload_dir['basedir'] . '/autoblog/vector_store_' . $safe_provider . '.json';
        
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
        // Enkode ke JSON dengan penanganan error karakter UTF-8 yang tidak valid
        $json = json_encode( $this->memory, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
        
        if ( $json === false ) {
            Logger::log( 'VectorStore: Gagal melakukan encode JSON: ' . json_last_error_msg(), 'error' );
            return;
        }

        // Simpan secara terisolasi (atomic) dengan temp file di direktori yang sama
        // Agar file utama tidak kosong jika proses terputus di tengah jalan
        $store_dir = dirname( $this->store_path );
        $temp_path = tempnam( $store_dir, 'vs_tmp_' );
        if ( $temp_path === false ) {
            Logger::log( 'VectorStore: Gagal membuat file temporary di: ' . $store_dir, 'error' );
            return;
        }

        $result = file_put_contents( $temp_path, $json, LOCK_EX );
        
        if ( $result !== false ) {
            // rename() bersifat atomic pada filesystem yang sama (Linux)
            $renamed = rename( $temp_path, $this->store_path );
            if ( ! $renamed ) {
                Logger::log( 'VectorStore: Gagal me-rename temp file ke: ' . $this->store_path, 'error' );
                @unlink( $temp_path );
            }
        } else {
            Logger::log( 'VectorStore: Gagal menulis data ke file temporary: ' . $temp_path, 'error' );
            @unlink( $temp_path );
        }
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
     * Chunks it, embeds it (in batch), and saves it.
     * 
     * Batch embedding mengirim seluruh chunk dalam 1 request API
     * untuk provider yang mendukung (OpenAI), mengurangi latency drastis.
     *
     * Optimasi memori: jika jumlah chunk melebihi MAX_MEMORY_CHUNKS,
     * hapus chunk terlama (paling tidak relevan).
     *
     * @param string $text Content to store.
     * @param string $source Source identifier (filename/url).
     */
    public function add_document( $text, $source = 'unknown' ) {
        if ( empty( $text ) ) return 0;

        Logger::log("VectorStore: Processing document from $source...", 'info');

        // 1. Chunking (Simple Sentence/Length-based)
        $chunks = $this->chunk_text( $text, 800 );
        
        if ( empty( $chunks ) ) return 0;

        // 2. Get Configured Provider
        $provider = OptionCache::get( 'autoblog_embedding_provider', 'openai' );

        // 3. Generate Embeddings dalam BATCH
        $batch_results = $this->ai_client->create_embeddings_batch( $chunks, $provider );

        // 4. Simpan hasil ke memory
        $success_count = 0;
        foreach ( $batch_results as $result ) {
            if ( isset( $result['vector'] ) && $result['vector'] !== false ) {
                $this->memory[] = [
                    'id'     => uniqid('vec_'),
                    'text'   => $result['text'],
                    'vector' => $result['vector'],
                    'source' => $source,
                    'provider' => $provider,
                ];
                $success_count++;
            }
        }
        
        // 5. Batasi memori: hapus chunk terlama jika melebihi batas
        if ( count( $this->memory ) > self::MAX_MEMORY_CHUNKS ) {
            $this->memory = array_slice( $this->memory, -self::MAX_MEMORY_CHUNKS );
            Logger::log( "VectorStore: Memory trimmed to " . self::MAX_MEMORY_CHUNKS . " chunks (oldest removed).", 'info' );
        }
        
        // 6. Save sekali untuk semua chunk
        $this->save();
        
        Logger::log("VectorStore: {$success_count}/" . count($chunks) . " chunks berhasil dari $source (batch).", 'info');

        return $success_count;
    }

    /**
     * Search for relevant chunks using Cosine Similarity.
     * 
     * @param string $query User query or Article Title.
     * @param int $limit Number of chunks to return.
     * @return array Top relevant chunks.
     */
    /**
     * Search for relevant chunks using Cosine Similarity.
     * 
     * Optimasi O(n) → O(m):
     * - Keyword pre-filter untuk mengurangi kandidat sebelum cosine similarity
     * - Hanya hitung cosine similarity untuk chunk dengan potensi relevansi
     * 
     * @param string $query User query or Article Title.
     * @param int $limit Number of chunks to return.
     * @return array Top relevant chunks.
     */
    public function search( $query, $limit = 3 ) {
        if ( empty( $this->memory ) ) return [];

        $provider = OptionCache::get( 'autoblog_embedding_provider', 'openai' );

        // 1. Embed Query
        $query_vector = $this->ai_client->create_embedding( $query, $provider );
        
        if ( ! $query_vector ) return [];

        // 2. Keyword pre-filter: ekstrak keyword dari query untuk reduksi kandidat
        $query_keywords = array_unique( array_filter( preg_split( '/[\s,.;:!?]+/', strtolower( $query ) ),
            function( $w ) { return strlen( $w ) > 3; }
        ) );
        
        $has_keyword_filter = ! empty( $query_keywords );

        // 3. Calculate Similarity (dengan keyword pre-filter)
        $scores = [];
        foreach ( $this->memory as $index => $item ) {
            if ( ! isset( $item['vector'] ) ) continue;
            
            // Keyword pre-filter: skip chunk yang tidak mengandung keyword query
            if ( $has_keyword_filter && isset( $item['text'] ) ) {
                $text_lower = strtolower( $item['text'] );
                $has_keyword = false;
                foreach ( $query_keywords as $kw ) {
                    if ( strpos( $text_lower, $kw ) !== false ) {
                        $has_keyword = true;
                        break;
                    }
                }
                if ( ! $has_keyword ) {
                    // Beri score minimal tanpa cosine similarity untuk chunk tanpa keyword
                    $scores[ $index ] = -1;
                    continue;
                }
            }
            
            $similarity = $this->cosine_similarity( $query_vector, $item['vector'] );
            $scores[ $index ] = $similarity;
        }

        // 4. Sort by Score (Desc)
        arsort( $scores );

        // 5. Get Top K
        $results = [];
        $count = 0;
        foreach ( $scores as $index => $score ) {
            if ( $count >= $limit ) break;
            
            if ( $score > 0.4 ) {
                $match = $this->memory[ $index ];
                unset( $match['vector'] );
                $match['score'] = $score;
                $results[] = $match;
                $count++;
            }
        }

        // Jika hasil terlalu sedikit (keyword filter terlalu ketat), fallback tanpa filter
        if ( $has_keyword_filter && count( $results ) < $limit ) {
            Logger::log( "VectorStore: Keyword filter terlalu ketat, fallback tanpa filter.", 'debug' );
            $fallback_scores = [];
            foreach ( $this->memory as $index => $item ) {
                if ( ! isset( $item['vector'] ) ) continue;
                if ( isset( $scores[ $index ] ) && $scores[ $index ] >= 0 ) continue; // sudah dihitung
                $similarity = $this->cosine_similarity( $query_vector, $item['vector'] );
                $fallback_scores[ $index ] = $similarity;
            }
            arsort( $fallback_scores );
            foreach ( $fallback_scores as $index => $score ) {
                if ( $count >= $limit ) break;
                if ( $score > 0.4 ) {
                    $match = $this->memory[ $index ];
                    unset( $match['vector'] );
                    $match['score'] = $score;
                    $results[] = $match;
                    $count++;
                }
            }
        }

        Logger::log("VectorStore: Search for '{$query}' returned " . count($results) . " results.", 'info');

        return $results;
    }

    /**
     * Calculate Cosine Similarity between two vectors.
     */
    private function cosine_similarity( $vec1, $vec2 ) {
        // Bug #4 Fix: Pastikan kedua vektor memiliki dimensi yang sama
        // Mencegah crash Undefined offset saat pindah embedding provider
        if ( count( $vec1 ) !== count( $vec2 ) ) return 0;

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
            $raw_source = isset( $item['source'] ) ? $item['source'] : 'unknown';
            $source_name = 'unknown';

            if ( is_array( $raw_source ) ) {
                if ( isset( $raw_source['name'] ) ) {
                    $source_name = $raw_source['name'];
                } elseif ( isset( $raw_source['source'] ) ) {
                    $source_name = $raw_source['source'];
                }
            } else {
                $source_name = basename( (string) $raw_source );
            }

            $sources[ $source_name ] = true;
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

            $raw_source = isset( $chunk['source'] ) ? $chunk['source'] : 'knowledge_base';
            $source_name = 'unknown';

            if ( is_array( $raw_source ) ) {
                $source_name = isset( $raw_source['name'] ) ? $raw_source['name'] : ( isset( $raw_source['source'] ) ? $raw_source['source'] : 'knowledge_base' );
            } else {
                $source_name = (string) $raw_source;
            }

            $topics[] = [
                'title'  => $title,
                'text'   => $text,
                'source' => $source_name,
            ];
        }

        Logger::log( 'VectorStore: get_recent_topics() mengembalikan ' . count($topics) . ' topik.', 'info' );

        return $topics;
    }

}
