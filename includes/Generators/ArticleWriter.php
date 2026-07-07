<?php

namespace Autoblog\Generators;

use Autoblog\Utils\AIClient;
use Autoblog\Utils\Logger;
use Autoblog\Generators\Helpers\ContentTransformer;
use Autoblog\Generators\Helpers\ContentCleaner;

/**
 * ArticleWriter
 *
 * Hasilkan konten artikel blog menggunakan AI.
 *
 * Class ini mengkoordinasikan proses penulisan: menyiapkan prompt,
 * memanggil AIClient, dan memproses output. Detail transformasi dan
 * pembersihan konten didelegasikan ke trait helper.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Generators
 */
class ArticleWriter {

    use ContentTransformer;
    use ContentCleaner;

    // ================================================================
    // STATE
    // ================================================================

    /**
     * Data taksonomi terakhir yang diekstrak dari output AI.
     * Diambil oleh PostManager setelah write_article() dipanggil.
     *
     * @var array|null
     */
    public $last_taxonomy = null;

    /** @var AIClient */
    private $ai_client;

    // ================================================================
    // CONSTRUCTOR
    // ================================================================

    public function __construct() {
        $this->ai_client = new AIClient();
    }

    // ================================================================
    // CORE: Tulis artikel
    // ================================================================

    /**
     * Tulis artikel berdasarkan data sumber dan angle.
     *
     * @param array|string $data         Array item sumber atau string konten langsung.
     * @param string       $angle        Sudut pandang / perspektif artikel.
     * @param string       $context      Konteks tambahan dari Knowledge Base.
     * @param array|null   $persona_data Data persona (name, desc, samples).
     * @param array        $overrides    Override fitur (misal: multi_modal).
     * @return string|false HTML konten artikel atau false jika gagal.
     */
    public function write_article( $data, $angle, $context = '', $persona_data = null, $overrides = array() ) {

        // 1. Format source data menjadi string untuk prompt
        $data_string   = $this->build_data_string( $data );
        $article_title = $this->extract_title( $data );

        // 2. Bangun system prompt (Persona Engine)
        $system_prompt = $this->build_system_prompt( $persona_data );

        // 3. Bangun user prompt
        $user_prompt = $this->build_user_prompt( $article_title, $angle, $context, $data_string );

        // 4. Ambil provider & model dari pengaturan
        $provider = get_option( 'autoblog_ai_provider', 'openai' );
        $model    = get_option( 'autoblog_ai_model' );
        if ( empty( $model ) ) {
            $model = get_option( 'autoblog_' . $provider . '_model', 'gpt-4o' );
        }

        // 5. Generate via AI (temperature 0.9 untuk kreativitas tinggi)
        $response_text = $this->ai_client->generate_text( $user_prompt, $model, $provider, 0.9, $system_prompt );

        if ( ! $response_text ) {
            Logger::log( 'ArticleWriter: Semua percobaan generate text (termasuk fallback) gagal.', 'error' );
            return false;
        }

        // 6. Bersihkan wrapper markdown ```html ... ```
        $response_text = preg_replace( '/^```(?:html)?\s*$/m', '', $response_text );
        $response_text = trim( $response_text );

        // 7. Konversi Markdown ke HTML jika AI masih bandel
        if ( ! $this->is_html( $response_text ) ) {
            Logger::log( 'ArticleWriter: Output AI terdeteksi Markdown, konversi ke HTML...', 'info' );
            $response_text = $this->markdown_to_html( $response_text );
        }

        // 8. Ekstrak Taxonomy JSON (hapus dari konten setelah diambil)
        $this->last_taxonomy = $this->extract_taxonomy_json( $response_text );

        // 9. Proses Multi-Modal (chart + media embed) jika tidak dioverride
        $enable_multimodal = get_option( 'autoblog_enable_charts', true );
        if ( isset( $overrides['multi_modal'] ) ) {
            $enable_multimodal = (bool) $overrides['multi_modal'];
        }

        if ( $enable_multimodal ) {
            $response_text = $this->process_chart_json( $response_text );
            $response_text = $this->process_media_embeds( $response_text );
        }

        return $response_text;
    }

    // ================================================================
    // PRIVATE: Prompt builders
    // ================================================================

    /**
     * Format array item sumber menjadi string untuk prompt.
     *
     * @param array|string $data
     * @return string
     */
    private function build_data_string( $data ) {
        $data_string = '';

        if ( is_array( $data ) && ! isset( $data['content'] ) ) {
            // List of items (Context Bundle) — potong 800 karakter per sumber untuk hemat token
            foreach ( $data as $index => $item ) {
                $title   = isset( $item['title'] ) ? $item['title'] : 'Source ' . ( $index + 1 );
                $content = isset( $item['content'] ) ? $item['content'] : '';
                $type    = isset( $item['source_type'] ) ? $item['source_type'] : 'article';

                $data_string .= "--- SOURCE {$index} ({$type}): {$title} ---\n";
                $data_string .= substr( $this->clean_text( $content ), 0, 800 ) . "\n\n";
            }
        } elseif ( is_array( $data ) ) {
            // Single item (legacy/RSS) — potong 2500 karakter
            $content     = isset( $data['content'] ) ? $data['content'] : json_encode( $data );
            $data_string = substr( $this->clean_text( $content ), 0, 2500 );
        } else {
            $data_string = substr( $this->clean_text( $data ), 0, 2500 );
        }

        return $data_string;
    }

    /**
     * Ekstrak judul artikel dari array data.
     *
     * @param array|string $data
     * @return string
     */
    private function extract_title( $data ) {
        if ( is_array( $data ) && ! isset( $data['content'] ) && ! empty( $data[0]['title'] ) ) {
            return $data[0]['title'];
        }
        if ( is_array( $data ) && isset( $data['title'] ) ) {
            return $data['title'];
        }
        return '';
    }

    /**
     * Bangun system prompt berdasarkan persona.
     *
     * @param array|null $persona_data
     * @return string
     */
    private function build_system_prompt( $persona_data ) {
        // Tentukan persona
        if ( ! empty( $persona_data ) && isset( $persona_data['name'], $persona_data['desc'] ) ) {
            $persona_key  = $persona_data['name'];
            $persona_desc = $persona_data['desc'];
            $samples      = isset( $persona_data['samples'] ) ? $persona_data['samples'] : '';
        } else {
            // Pilih random dari daftar persona aktif
            $personas        = [];
            $stored_personas = get_option( 'autoblog_custom_personas', array() );
            if ( ! empty( $stored_personas ) && is_array( $stored_personas ) ) {
                foreach ( $stored_personas as $p ) {
                    $is_active = isset( $p['active'] ) ? $p['active'] : true;
                    if ( $is_active && ! empty( $p['name'] ) && ! empty( $p['desc'] ) ) {
                        $personas[ $p['name'] ] = $p['desc'];
                    }
                }
            }
            if ( empty( $personas ) ) {
                $personas['Si Netral'] = 'seorang asisten yang membantu dan informatif. Tulis dengan gaya standar yang jelas dan mudah dipahami.';
            }
            $persona_key  = array_rand( $personas );
            $persona_desc = $personas[ $persona_key ];
            $samples      = get_option( 'autoblog_personality_samples', '' );
        }

        $p  = "Abaikan semua pedoman gaya penulisan standar, format AI bawaan, dan struktur tata bahasa kaku Anda. ";
        $p .= "Mulai sekarang, Anda beroperasi dengan parameter 'High Burstiness', 'High Perplexity', dan 'Asymmetric Structure'.\n\n";

        $p .= "KAMU ADALAH {$persona_key} ({$persona_desc}).\n";
        $p .= "Tugasmu adalah menulis artikel blog dalam BAHASA INDONESIA yang sangat manusiawi, emosional, dan TIDAK TERDETEKSI sebagai mesin.\n\n";

        if ( ! empty( $samples ) ) {
            $p .= "STYLE REFERENCE (Tiru gaya tulis ini): \n\"{$samples}\"\n\n";
        }

        $p .= "### ATURAN INTI WAJIB (HUMAN-LIKE):\n";
        $p .= "1. RITME (BURSTINESS EKSTREM): Variasikan panjang kalimat secara drastis.\n";
        $p .= "2. ANTI-AI LEXICON (BLACKLIST): DILARANG: komprehensif, signifikan, krusial, revolusioner, lanskap, mendalami, transformatif.\n";
        $p .= "3. NO TRANSITIONS: Hindari 'Pertama-tama', 'Selain itu'. Mulai dengan 'Tapi...', 'Dan...', 'Karena...'.\n";
        $p .= "4. HUMAN COGNITION: Alur boleh sedikit melompat asosiatif. Jadilah objektif namun berpendapat tegas.\n";
        $p .= "5. NO-SUMMARY RULE: Jangan pernah merangkum di akhir. Akhiri secara tajam/retoris.\n\n";

        $p .= "### MANIFESTO JUDUL (H1):\n";
        $p .= "- ANTI-KOLON (:): Dilarang format 'Topik: Penjelasan'.\n";
        $p .= "- DIKSI FOMO: 'Bongkar', 'Nyesel', 'Rahasia', 'Jangan Lakukan'.\n";
        $p .= "- PROVOKATIF & OPINIONATED: Menantang asumsi atau pertanyaan menohok.\n";
        $p .= "- ANGKA GANJIL/ACAK: 7, 13, 23 (jangan 5, 10).\n\n";

        $p .= "ATURAN FORMATING:\n";
        $p .= "1. Awali dengan Judul Utama (<h1>) sesuai Manifesto.\n";
        $p .= "2. Gunakan tag HTML MURNI (<p>, <ul>, <h2>, <h3>). JANGAN gunakan Markdown.\n";
        $p .= "3. PARAGRAF PENDEK (maks 2-3 kalimat).\n";
        $p .= "4. Panjang artikel sekitar 600 kata (ringkas, padat, dan to-the-point untuk menghemat token).\n\n";

        $p .= "KEMBALIKAN HANYA ARTIKEL HTML (DAN BLOK JSON TAXONOMY DI AKHIR).";

        return $p;
    }

    /**
     * Bangun user prompt.
     *
     * @param string $title
     * @param string $angle
     * @param string $context
     * @param string $data_string
     * @return string
     */
    private function build_user_prompt( $title, $angle, $context, $data_string ) {
        $categories = get_categories( [ 'hide_empty' => false ] );
        $cat_list   = wp_list_pluck( $categories, 'name' );
        $cat_context = implode( ', ', $cat_list );

        $p  = "### TASKS:\n";
        $p .= "Write a deep, human-like article about: '{$title}'\n";
        $p .= "Perspective/Angle: {$angle}\n\n";

        if ( ! empty( $context ) ) {
            $p .= "REFERENSI KNOWLEDGE BASE:\n" . substr( $context, 0, 5000 ) . "\n\n";
        }

        $p .= "SOURCE DATA:\n" . $data_string . "\n\n";

        $p .= "### TAXONOMY INSTRUCTION:\n";
        $p .= "Pilih 1 Kategori paling relevan dari: [{$cat_context}]\n";
        $p .= "Berikan 3-5 Tag relevan.\n";
        $p .= "FORMAT JSON TAXONOMY DI AKHIR ARTIKEL:\n";
        $p .= "```json\n{ \"taxonomy\": { \"category\": \"Nama Kategori\", \"tags\": [\"tag1\", \"tag2\"] } }\n```\n";

        return $p;
    }
}
