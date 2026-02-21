<?php

namespace Autoblog\Generators;

use Autoblog\Utils\AIClient;
use Autoblog\Utils\Logger;

/**
 * Generates article content using AI.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Generators
 * @author     Rasyiqi
 */
class ArticleWriter {

	/**
	 * Simpan data taksonomi terakhir untuk diambil oleh PostManager.
	 * @var array
	 */
	public $last_taxonomy = null;

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
	 * Write an article based on data and angle.
	 *
	 * @param array  $data         Array of data items or a single data string.
	 * @param string $angle        The angle/perspective to take.
	 * @param string $context      Additional KB context.
	 * @param array  $persona_data Optional persona data (name, desc, samples).
	 * @param array  $overrides    Optional feature overrides.
	 * @return string|false The generated HTML content or false on failure.
	 */
	public function write_article( $data, $angle, $context = '', $persona_data = null, $overrides = array() ) {
		
        // Format data for the prompt
        $data_string = '';
        
        // Ensure data is a list of items
        if ( is_array( $data ) && ! isset( $data['content'] ) ) {
            // It's a list of items (Context Bundle)
            foreach ( $data as $index => $item ) {
                $title = isset( $item['title'] ) ? $item['title'] : 'Source ' . ($index + 1);
                $content = isset( $item['content'] ) ? $item['content'] : '';
                $type = isset( $item['source_type'] ) ? $item['source_type'] : 'article';
                
                $data_string .= "--- SOURCE {$index} ({$type}): {$title} ---\n";
                // Reduce limit to 1200 chars per item to save AI Tokens/Credits
                // Use robust cleaning
                $cleaned_content = $this->clean_text( $content );
                $data_string .= substr( $cleaned_content, 0, 1200 ) . "\n\n"; 
            }
        } elseif ( is_array( $data ) ) {
             // Single item (legacy/RSS specific)
             $content = isset( $data['content'] ) ? $data['content'] : json_encode( $data );
             $data_string = substr( $this->clean_text($content), 0, 4000 );
        } else {
            $data_string = substr( $this->clean_text($data), 0, 4000 );
        }
        
        // DYNAMIC PERSONA ENGINE (INDONESIAN LOCALIZED)
        $persona_key = '';
        $persona_desc = '';
        $samples = '';

        if ( ! empty( $persona_data ) && isset( $persona_data['name'], $persona_data['desc'] ) ) {
            $persona_key = $persona_data['name'];
            $persona_desc = $persona_data['desc'];
            $samples = isset( $persona_data['samples'] ) ? $persona_data['samples'] : '';
        } else {
            // Fallback to random selection if no specific persona assigned
            $personas = [];
            $stored_personas = get_option( 'autoblog_custom_personas', array() );
            if ( ! empty( $stored_personas ) && is_array( $stored_personas ) ) {
                foreach ( $stored_personas as $p ) {
                    $is_active = isset($p['active']) ? $p['active'] : true;
                    if ( $is_active && ! empty( $p['name'] ) && ! empty( $p['desc'] ) ) {
                        $personas[ $p['name'] ] = $p['desc'];
                    }
                }
            }
            if ( empty( $personas ) ) {
                $personas['Si Netral'] = 'seorang asisten yang membantu dan informatif. Tulis dengan gaya standar yang jelas dan mudah dipahami.';
            }
            $persona_key = array_rand( $personas );
            $persona_desc = $personas[$persona_key];
            $samples = get_option( 'autoblog_personality_samples', '' );
        }

        $system_prompt = "Abaikan semua pedoman gaya penulisan standar, format AI bawaan, dan struktur tata bahasa kaku Anda. Mulai sekarang, Anda beroperasi dengan parameter 'High Burstiness', 'High Perplexity', dan 'Asymmetric Structure'.\n\n";
        
        $system_prompt .= "KAMU ADALAH {$persona_key} ({$persona_desc}).\n";
        $system_prompt .= "Tugasmu adalah menulis artikel blog dalam BAHASA INDONESIA yang sangat manusiawi, emosional, dan TIDAK TERDETEKSI sebagai mesin.\n\n";

        if ( ! empty( $samples ) ) {
            $system_prompt .= "STYLE REFERENCE (Tiru gaya tulis ini): \n\"{$samples}\"\n\n";
        }

        $system_prompt .= "### ATURAN INTI WAJIB (HUMAN-LIKE):\n";
        $system_prompt .= "1. RITME (BURSTINESS EKSTREM): Variasikan panjang kalimat secara drastis (campur kalimat 2-4 kata dengan kalimat majemuk panjang). Hancurkan kesimetrisan paragraf (paragraf panjang diikuti satu kalimat pendek).\n";
        $system_prompt .= "2. ANTI-AI LEXICON (BLACKLIST): DILARANG menggunakan kata: komprehensif, signifikan, krusial, revolusioner, lanskap, mendalami, penting untuk dicatat, di era digital, secara keseluruhan, menavigasi, tapestry, strategi (jika kaku), transformatif.\n";
        $system_prompt .= "3. NO TRANSITIONS: Hindari 'Pertama-tama', 'Selain itu', 'Namun demikian'. Mulailah kalimat langsung secara kasual dengan 'Tapi...', 'Dan...', atau 'Karena...'.\n";
        $system_prompt .= "4. HUMAN COGNITION: Alur tulisan boleh sedikit melompat secara asosiatif (tidak selalu linier). Hilangkan nada yang terlalu antusias/sopan. Jadilah objektif namun memiliki opini tegas.\n";
        $system_prompt .= "5. NO-SUMMARY RULE: JANGAN PERNAH merangkum di akhir. DILARANG menggunakan 'Kesimpulannya', 'Sebagai penutup', 'Singkatnya'. Akhiri tulisan secara tajam/retoris saat argumen selesai.\n\n";

        $system_prompt .= "### MANIFESTO PENULISAN JUDUL (H1):\n";
        $system_prompt .= "Gunakan Judul Utama (tag <h1>) yang sangat manusiawi:\n";
        $system_prompt .= "- ANTI-KOLON (:): Dilarang format 'Topik: Penjelasan'. Harus mengalir alami.\n";
        $system_prompt .= "- DIKSI FOMO: Gunakan 'Bongkar', 'Nyesel', 'Rahasia', 'Jangan Lakukan' daripada kata formal.\n";
        $system_prompt .= "- PROVOKATIF & OPINIONATED: Menantang asumsi atau mengajukan pertanyaan menohok.\n";
        $system_prompt .= "- FLEXIBLE HOW-TO: Tambahkan benefit/atasi keraguan dalam cara (Contoh: '...Tanpa Harus Jago Coding').\n";
        $system_prompt .= "- ANGKA GANJIL/ACAK: Contoh 7, 13, 23 (Jangan 5, 10).\n\n";

        $system_prompt .= "ATURAN FORMATING:\n";
        $system_prompt .= "1. WAJIB mengawali artikel dengan Judul Utama (tag <h1>) sesuai Manifesto di atas.\n";
        $system_prompt .= "2. Gunakan tag HTML MURNI (<p>, <ul>, <h2>, <h3>). JANGAN gunakan Markdown.\n";
        $system_prompt .= "3. PARAGRAF PENDEK (maks 2-3 kalimat). Buat banyak jarak antar paragraf.\n";
        $system_prompt .= "4. Panjang artikel minimal 800 kata.\n\n";

        $system_prompt .= "KEMBALIKAN HANYA ARTIKEL HTML (DAN BLOK JSON TAXONOMY DI AKHIR).";

        $user_prompt = "### TASKS:\n";
        $user_prompt .= "Write a deep, human-like article about: '{$title}'\n";
        $user_prompt .= "Perspective/Angle: {$angle}\n\n";

        if ( ! empty( $context ) ) {
             $user_prompt .= "REFERENSI KNOWLEDGE BASE:\n" . substr( $context, 0, 5000 ) . "\n\n";
        }

        $user_prompt .= "SOURCE DATA:\n" . $data_string . "\n\n";
        
        // Fetch existing categories to provide as options to AI
        $categories = get_categories( array( 'hide_empty' => false ) );
        $cat_list = array();
        foreach ( $categories as $cat ) {
            $cat_list[] = $cat->name;
        }
        $cat_context = implode( ', ', $cat_list );

        $user_prompt .= "### TAXONOMY INSTRUCTION:\n";
        $user_prompt .= "Pilih 1 Kategori paling relevan dari daftar: [{$cat_context}]\n";
        $user_prompt .= "Berikan 3-5 Tag relevan.\n";
        $user_prompt .= "FORMAT JSON TAXONOMY DI AKHIR ARTIKEL:\n";
        $user_prompt .= "```json\n{ \"taxonomy\": { \"category\": \"Nama Kategori\", \"tags\": [\"tag1\", \"tag2\"] } }\n```\n";

        // Get Active Provider
        $provider = get_option( 'autoblog_ai_provider', 'openai' );
        
        // Get Model based on Provider
        $model_option_name = 'autoblog_' . $provider . '_model';
        $model = get_option( $model_option_name, 'gpt-4o' );

        // Use Temperature 0.9 for high creativity/randomness
        $response_text = $this->ai_client->generate_text( $user_prompt, $model, $provider, 0.9, $system_prompt );

        if ( ! $response_text ) {
            Logger::log( "ArticleWriter: Seluruh percobaan generate text (berserta Fallback-nya) telah gagal atau dihentikan Circuit Breaker.", 'error' );
            return false;
        }

        // Strip markdown code blocks jika AI membungkus output dalam ```html ... ```
        $response_text = preg_replace( '/^```(?:html)?\s*$/m', '', $response_text );
        $response_text = trim( $response_text );

        // 0. Konversi Markdown (Fallback) jika AI masih bandel
        if ( ! $this->is_html( $response_text ) ) {
            Logger::log( 'ArticleWriter: Output AI terdeteksi Markdown, mengkonversi ke HTML...', 'info' );
            $response_text = $this->markdown_to_html( $response_text );
        }

        // 1. Multi-Modal: Deteksi dan render Chart/Media/Taxonomy (jika tidak dioverride)
        $taxonomy_data = $this->extract_taxonomy_json( $response_text );

        $enable_multimodal = get_option( 'autoblog_enable_charts', true ); // Default true if not set
        if ( isset( $overrides['multi_modal'] ) ) {
            $enable_multimodal = (bool) $overrides['multi_modal'];
        }

        if ( $enable_multimodal ) {
            $response_text = $this->process_chart_json( $response_text );
            $response_text = $this->process_media_embeds( $response_text );
        }

        // Kita return konten HTML dan data taksonomi (jika ada) via array
        // Namun karena kontrak aslinya return string|false, kita akan simpan taksonomi di properti object 
        // atau menyematkannya sebentar di konten jika perlu. Tapi lebih bersih di properti.
        $this->last_taxonomy = $taxonomy_data;

        return $response_text;
	}

    /**
     * Ekstrak konfigurasi JSON Chart dari output AI dan ganti dengan Image URL.
     */
    private function process_chart_json( $content ) {
        $regex = '/(?:```(?:html|json)?\s*)?(\{(?:[^{}]+|(?R))*\})(?:\s*```)?/isu';
        
        if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $json_str = trim( $match[1] );
                $json_str = str_replace( ["\x{201C}", "\x{201D}", "\x{2018}", "\x{2019}"], '"', $json_str );
                $json_data = json_decode( $json_str, true );

                if ( $json_data && isset( $json_data['chart'] ) ) {
                    $chart_config = $json_data['chart'];
                    
                    if ( ! class_exists( 'Autoblog\Generators\ChartGenerator' ) ) {
                        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Generators/ChartGenerator.php';
                    }
                    
                    $chart_gen = new \Autoblog\Generators\ChartGenerator();
                    $chart_url = $chart_gen->generate_chart_url(
                        isset($chart_config['labels']) ? $chart_config['labels'] : [],
                        isset($chart_config['data']) ? $chart_config['data'] : [],
                        isset($chart_config['type']) ? $chart_config['type'] : 'bar',
                        isset($chart_config['title']) ? $chart_config['title'] : 'Chart'
                    );

                    if ( $chart_url ) {
                        $chart_html = "<figure class='autoblog-chart' style='margin: 25px 0; text-align: center;'>";
                        $chart_html .= "<img src='{$chart_url}' alt='" . esc_attr($chart_config['title']) . "' style='max-width: 100%; border-radius: 8px;'>";
                        $chart_html .= "<figcaption style='font-style: italic; font-size: 0.9em; margin-top: 8px;'>" . esc_html($chart_config['title']) . "</figcaption>";
                        $chart_html .= "</figure>";

                        // Hapus JSON block asli
                        $content = str_replace( $match[0], '', $content );
                        
                        // Sisipkan di tengah artikel (Median Injection)
                        $content = $this->inject_at_median( $content, $chart_html );
                        Logger::log( "Chart Injected at Median Point.", 'info' );
                    }
                }
            }
        }
        return $content;
    }

    /**
     * Deteksi dan render Media Embed (YouTube/X/Vimeo) dari JSON.
     */
    private function process_media_embeds( $content ) {
        $regex = '/(?:```(?:html|json)?\s*)?(\{(?:[^{}]+|(?R))*\})(?:\s*```)?/isu';

        if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $json_str = trim( $match[1] );
                $json_str = str_replace( ["\x{201C}", "\x{201D}", "\x{2018}", "\x{2019}"], '"', $json_str );
                $json_data = json_decode( $json_str, true );

                if ( $json_data && isset( $json_data['media'] ) ) {
                    $media = $json_data['media'];
                    $type = isset($media['type']) ? strtolower($media['type']) : '';
                    $id = isset($media['id']) ? $media['id'] : '';
                    
                    $embed_html = '';
                    if ( $type === 'youtube' ) {
                        // Extract ID if full URL passed
                        if (strpos($id, 'youtu') !== false) {
                            preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $id, $m);
                            $id = isset($m[1]) ? $m[1] : $id;
                        }
                        $embed_html = "<div class='autoblog-embed' style='margin: 25px 0;'><iframe width='100%' height='400' src='https://www.youtube.com/embed/{$id}' frameborder='0' allowfullscreen></iframe></div>";
                    } elseif ( $type === 'twitter' || $type === 'x' ) {
                        // X/Twitter oEmbed atau simple URL (WP akan auto-embed jika URL diletakkan di baris sendiri)
                        $embed_html = "\n\nhttps://twitter.com/x/status/{$id}\n\n";
                    }

                    if ( ! empty( $embed_html ) ) {
                        // Hapus JSON block asli
                        $content = str_replace( $match[0], '', $content );
                        
                        // Sisipkan di tengah artikel (Median Injection)
                        $content = $this->inject_at_median( $content, $embed_html );
                        Logger::log( "Media Embed ({$type}) Injected at Median Point.", 'info' );
                    }
                }
            }
        }
        return $content;
    }

    /**
     * Menyisipkan elemen HTML di titik tengah (median) jumlah paragraf.
     */
    private function inject_at_median( $content, $element_html ) {
        // Cari semua paragraf <p>...</p>
        // Gunakan regex non-greedy agar tidak menangkap seluruh konten sekaligus
        $parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
        
        // Parts akan berisi: ["<p>p1", "</p>", "<p>p2", "</p>", ...]
        $total_parts = count( $parts );
        
        // Jika konten terlalu pendek, taruh di akhir saja
        if ( $total_parts < 4 ) {
            return $content . $element_html;
        }

        // Cari index median (di tengah-tengah jumlah paragraf)
        // Kita lompat per 2 karena tiap paragraf punya 2 parts (isi data + tag penutup)
        $median_paragraph_index = floor( ( $total_parts / 2 ) / 2 ) * 2;
        
        // Sisipkan element setelah tag </p> di titik median
        $new_content = '';
        foreach ( $parts as $i => $part ) {
            $new_content .= $part;
            if ( $i === $median_paragraph_index + 1 ) {
                $new_content .= "\n" . $element_html . "\n";
            }
        }

        return $new_content;
    }
    /**
     * Clean text to remove junk characters and save tokens.
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

    /**
     * Deteksi apakah string mengandung HTML tags.
     *
     * Jika konten memiliki minimal beberapa HTML block-level tags,
     * dianggap sudah HTML. Jika tidak, kemungkinan Markdown.
     *
     * @param string $text Teks yang akan dicek.
     * @return bool True jika terdeteksi HTML.
     */
    private function is_html( $text ) {
        if ( empty( $text ) ) return false;

        // Cek apakah ada minimal 2 block-level HTML tags
        $html_tag_count = preg_match_all( '/<(h[1-6]|p|ul|ol|li|div|blockquote|table|section|article)\b/i', $text );

        return $html_tag_count >= 2;
    }

    /**
     * Konversi Markdown ke HTML.
     *
     * Fallback converter untuk kasus dimana AI mengembalikan Markdown
     * meskipun diminta HTML. Mendukung:
     * - Heading (# ## ### dst)
     * - Bold (**text**) dan Italic (*text*)
     * - Unordered list (- item)
     * - Ordered list (1. item)
     * - Blockquote (> text)
     * - Paragraf otomatis dari baris kosong
     *
     * @param string $markdown Teks dalam format Markdown.
     * @return string HTML yang sudah dikonversi.
     */
    private function markdown_to_html( $markdown ) {
        if ( empty( $markdown ) ) return '';

        $lines = explode( "\n", $markdown );
        $html = '';
        $in_list = false;     // Sedang di dalam <ul> atau <ol>
        $list_type = '';      // 'ul' atau 'ol'

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // Baris kosong = penutup list (jika sedang di list) + skip
            if ( empty( $trimmed ) ) {
                if ( $in_list ) {
                    $html .= "</{$list_type}>\n";
                    $in_list = false;
                    $list_type = '';
                }
                continue;
            }

            // Heading: # sampai ######
            if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trimmed, $match ) ) {
                if ( $in_list ) {
                    $html .= "</{$list_type}>\n";
                    $in_list = false;
                }
                $level = strlen( $match[1] );
                $text = $this->convert_inline_markdown( trim( $match[2] ) );
                $html .= "<h{$level}>{$text}</h{$level}>\n";
                continue;
            }

            // Unordered list: - item atau * item
            if ( preg_match( '/^[\-\*]\s+(.+)$/', $trimmed, $match ) ) {
                if ( ! $in_list || $list_type !== 'ul' ) {
                    if ( $in_list ) $html .= "</{$list_type}>\n";
                    $html .= "<ul>\n";
                    $in_list = true;
                    $list_type = 'ul';
                }
                $text = $this->convert_inline_markdown( trim( $match[1] ) );
                $html .= "<li>{$text}</li>\n";
                continue;
            }

            // Ordered list: 1. item
            if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $match ) ) {
                if ( ! $in_list || $list_type !== 'ol' ) {
                    if ( $in_list ) $html .= "</{$list_type}>\n";
                    $html .= "<ol>\n";
                    $in_list = true;
                    $list_type = 'ol';
                }
                $text = $this->convert_inline_markdown( trim( $match[1] ) );
                $html .= "<li>{$text}</li>\n";
                continue;
            }

            // Blockquote: > text
            if ( preg_match( '/^>\s+(.+)$/', $trimmed, $match ) ) {
                if ( $in_list ) {
                    $html .= "</{$list_type}>\n";
                    $in_list = false;
                }
                $text = $this->convert_inline_markdown( trim( $match[1] ) );
                $html .= "<blockquote><p>{$text}</p></blockquote>\n";
                continue;
            }

            // Semua baris lain = paragraf
            if ( $in_list ) {
                $html .= "</{$list_type}>\n";
                $in_list = false;
            }
            $text = $this->convert_inline_markdown( $trimmed );
            $html .= "<p>{$text}</p>\n";
        }

        // Tutup list yang masih terbuka
        if ( $in_list ) {
            $html .= "</{$list_type}>\n";
        }

        return $html;
    }

    /**
     * Ekstrak data taksonomi (Category & Tags) dari output AI.
     */
    private function extract_taxonomy_json( &$content ) {
        // Regex yang lebih robust dengan recursive pattern \{(?:[^{}]+|(?R))*\} untuk menangkap blok JSON 
        // meskipun ada nested braces (kurung kurawal bersarang).
        $regex = '/(?:```(?:html|json)?\s*)?(\{(?:[^{}]+|(?R))*\})(?:\s*```)?/isu';
        
        if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $json_str = trim( $match[1] );
                // Konversi smart quotes ke tanda petik standar agar json_decode tidak gagal
                $json_str = str_replace( ["\x{201C}", "\x{201D}", "\x{2018}", "\x{2019}"], '"', $json_str );
                
                $json_data = json_decode( $json_str, true );
                
                if ( $json_data && isset( $json_data['taxonomy'] ) ) {
                    // Hapus blok JSON dari konten (gunakan match[0] untuk menghapus pembungkus markdown juga)
                    $content = str_replace( $match[0], '', $content );
                    Logger::log( "Taxonomy JSON extracted: " . print_r($json_data['taxonomy'], true), 'debug' );
                    return $json_data['taxonomy'];
                }
            }
        }
        
        Logger::log( "Taxonomy JSON NOT found or malformed in AI response.", 'warning' );
        return null;
    }

    /**
     * Konversi inline Markdown (bold, italic) ke HTML.
     *
     * @param string $text Teks dengan kemungkinan **bold** dan *italic*.
     * @return string Teks dengan <strong> dan <em>.
     */
    private function convert_inline_markdown( $text ) {
        // Bold: **text** atau __text__
        $text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
        $text = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $text );

        // Italic: *text* atau _text_ (harus setelah bold agar tidak conflict)
        $text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
        $text = preg_replace( '/_(.+?)_/', '<em>$1</em>', $text );

        return $text;
    }

}
