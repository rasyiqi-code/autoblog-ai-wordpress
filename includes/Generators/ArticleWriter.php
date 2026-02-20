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
	 * @return string|false The generated HTML content or false on failure.
	 */
	public function write_article( $data, $angle, $context = '', $persona_data = null ) {
		
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

		$prompt = "KAMU BUKAN AI. Kamu adalah {$persona_key} ({$persona_desc}). \n";
        $prompt .= "Tugasmu adalah menulis artikel blog dalam BAHASA INDONESIA yang sangat manusiawi, emosional, dan TIDAK TERDETEKSI sebagai mesin. \n\n";
        
        if ( ! empty( $samples ) ) {
            $prompt .= "STYLE REFERENCE (Tiru gaya tulis ini): \n\"{$samples}\"\n\n";
        }

        if ( ! empty( $context ) ) {
             $prompt .= "REFERENSI KNOWLEDGE BASE (Gunakan fakta/data dari sini jika relevan, tapi jangan copy-paste): \n";
             $prompt .= substr( $context, 0, 5000 ) . "\n\n";
        }
        
        $prompt .= "Perspective/Angle: {$angle} \n\n";
        $prompt .= "Source Data: \n" . $data_string . "\n\n";
        
        $prompt .= "ATURAN PSIKOLOGIS & GAYA BAHASA (WAJIB DIPATUHI): \n";
        $prompt .= "1. LOGIC LEAPS (Loncatan Logika): Jangan terlalu linier (A->B->C). Sesekali melompatlah ke ide yang berhubungan secara emosional atau reflektif di tengah paragraf. \n";
        $prompt .= "2. PERSONAL BIAS (Subjektif): Jangan netral. Ambil posisi yang kuat. Marah, senang, atau kecewa. Katakan 'Menurut saya pribadi...' atau 'Saya jujur agak kesal dengan...' \n";
        $prompt .= "3. LOCAL SLANG (Bahasa Gaul): Gunakan Bahasa Indonesia yang luwes. Selipkan istilah gaul/idiom yang wajar dipakai di sosmed/Jakarta (contoh: 'parah sih', 'jujurly', 'mending rakit pc', 'kaum mendang-mending', dll) TAPI jangan berlebihan (cringe). \n";
        $prompt .= "4. INTERNAL MONOLOGUE (Gumam Batin): Tuliskan apa yang kamu pikirkan dalam kurung atau kalimat sela. Contoh: '(oke, mungkin ini terdengar klise, tapi dengarkan dulu..)'. \n";
        $prompt .= "5. SELF CORRECTION: Koreksi dirimu sendiri. 'Ide ini bagus. Eh, ralat, ini ide brilian malah.' \n";
        $prompt .= "6. BURSTINESS (Irama): Variasikan panjang kalimat secara ekstrem. Kadang satu paragraf penuh penjelasan teknis. \n";
        $prompt .= "Lalu satu kalimat pendek. \n";
        $prompt .= "Benar-benar pendek. \n";
        $prompt .= "Ini menciptakan dampak emosional. \n";
        $prompt .= "7. ORDINARY ANALOGIES (Analogi Receh): Pakai analogi kehidupan sehari-hari orang Indonesia. Jangan 'bak mesin terlumasi', tapi 'kayak nunggu ojol pas hujan'. \n";
        $prompt .= "8. SHOW, DON'T TELL: Libatkan panca indra. Jangan bilang 'laptopnya panas', bilang 'kipas laptopnya menderu seperti pesawat mau take-off'. \n";
        $prompt .= "9. BROKEN PATTERNS: Jika membuat list, jangan rapi. Poin 1 panjang, poin 2 pendek, poin 3 berupa pertanyaan retoris/sarkas. \n";
        $prompt .= "10. NO CLICHES: HARAM menggunakan kata: 'Di era digital ini', 'Kesimpulannya', 'Membuka kunci', 'Ranah', 'Signifikan'. Hapus semua kata-kata robot itu. \n\n";

        $prompt .= "STYLE REFERENCE (Tiru Gaya Ini): \n";
        $prompt .= "'Jujur, pas pertama nyoba, saya skeptis. Masa sih bisa segampang itu? Tapi pas tombolnya dipencet... wush. Kenceng banget, kayak motor baru ganti oli. Saya sampe mikir, 'kemana aja gue selama ini?'. Oke, mungkin agak lebay, tapi serius, ini game changer.' \n\n";

        // Prompt: Output HARUS HTML valid
        // Tambahan: Instruksi untuk Multi-Modal Chart
        // Jika artikel memuat data statistik yang cocok divisualisasikan, AI diminta menyertakan blok JSON khusus.
        $prompt  = "Kamu adalah penulis artikel blog profesional. Tulis artikel Lengkap, Informatif, dan Enak Dibaca dalam Format HTML.\n";
        $prompt .= "Topik: '{$title}'\n";
        $prompt .= "Angle: {$angle}\n";
        $prompt .= "Konteks Tambahan:\n{$context}\n\n";

        $prompt .= "ATURAN FORMATING:\n";
        $prompt .= "1. Kamu WAJIB mengawali artikel dengan Judul Utama yang sangat menawan (SEO-Friendly & Provokatif). Judul ini WAJIB dibungkus dengan tag <h1>Judul Utama</h1> di baris paling pertama.\n";
        $prompt .= "2. Gunakan tag HTML MURNI (Gunakan <p> untuk paragraf pembuka, <ul>/<ol> untuk list, <h2>/<h3> untuk sub-judul).\n";
        $prompt .= "3. JAGA PARAGRAF TETAP PENDEK. Maksimal 2-3 kalimat per paragraf (<p>). Jangan biarkan teks menumpuk rapat seperti dinding teks, buatlah lebih banyak jarak.\n";
        $prompt .= "4. HARAM menggunakan Markdown (NO **bold**, NO # Heading). Gunakan HTML tag secara langsung (<strong>, <em>, <h2>).\n";
        $prompt .= "5. Panjang artikel minimal 800 kata.\n";
        
        // Fetch existing categories to provide as options to AI
        $categories = get_categories( array( 'hide_empty' => false ) );
        $cat_list = array();
        foreach ( $categories as $cat ) {
            $cat_list[] = $cat->name;
        }
        $cat_context = implode( ', ', $cat_list );

        $prompt .= "FITUR MULTI-MODAL (CHART, MEDIA, & TAXONOMY):\n";
        $prompt .= "1. Jika konten mengandung data statistik, sertakan JSON Chart DI MANA SAJA (akan dipindah ke tengah).\n";
        $prompt .= "2. Jika ada YouTube/Twitter relevan, sertakan JSON Media ID.\n";
        $prompt .= "3. KLASIFIKASI TAXONOMY: Pilih 1 Kategori paling relevan dari daftar di bawah ini dan berikan 3-5 Tag yang cocok.\n";
        $prompt .= "   DAFTAR KATEGORI YANG TERSEDIA: [{$cat_context}]\n";
        $prompt .= "   FORMAT JSON TAXONOMY:\n";
        $prompt .= "   ```json\n";
        $prompt .= "   {\n";
        $prompt .= "     \"taxonomy\": {\n";
        $prompt .= "       \"category\": \"Nama Kategori Dari Daftar Di Atas\",\n";
        $prompt .= "       \"tags\": [\"tag1\", \"tag2\", \"tag3\"]\n";
        $prompt .= "     }\n";
        $prompt .= "   }\n";
        $prompt .= "   ```\n";
        $prompt .= "Jika tidak ada data/media, JANGAN sertakan blok JSON masing-masing. Namun, TAXONOMY WAJIB disertakan.\n\n";

        $prompt .= "KEMBALIKAN HANYA ARTIKEL HTML (DAN BLOK JSON DI BAWAH).";

        // Get Active Provider
        $provider = get_option( 'autoblog_ai_provider', 'openai' );
        
        // Get Model based on Provider
        $model_option_name = 'autoblog_' . $provider . '_model';
        $model = get_option( $model_option_name, 'gpt-4o' );

        // Use Temperature 0.9 for high creativity/randomness
        $response_text = $this->ai_client->generate_text( $prompt, $model, $provider, 0.9 );

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

        // 1. Multi-Modal: Deteksi dan render Chart/Media/Taxonomy
        $taxonomy_data = $this->extract_taxonomy_json( $response_text );
        $response_text = $this->process_chart_json( $response_text );
        $response_text = $this->process_media_embeds( $response_text );

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
        if ( preg_match( '/(?:```|”|"|\'|&rdquo;|&quot;)?\s*json(?:```|”|"|\'|&rdquo;|&quot;)?\s*(\{.*"chart".*\})\s*(?:```|”|"|\'|&rdquo;|&quot;)?/is', $content, $matches ) ||
             preg_match( '/(\{.*"chart".*\})\s*(?:```|”|"|\'|&rdquo;|&quot;)?\s*$/is', $content, $matches ) ) {
            
            $json_str = trim( $matches[1] );
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
                    $content = str_replace( $matches[0], '', $content );
                    
                    // Sisipkan di tengah artikel (Median Injection)
                    $content = $this->inject_at_median( $content, $chart_html );
                    Logger::log( "Chart Injected at Median Point.", 'info' );
                }
            } else {
                $content = str_replace( $matches[0], '', $content );
            }
        }
        return $content;
    }

    /**
     * Deteksi dan render Media Embed (YouTube/X/Vimeo) dari JSON.
     */
    private function process_media_embeds( $content ) {
        if ( preg_match( '/(?:```|”|"|\'|&rdquo;|&quot;)?\s*json(?:```|”|"|\'|&rdquo;|&quot;)?\s*(\{.*"media".*\})\s*(?:```|”|"|\'|&rdquo;|&quot;)?/is', $content, $matches ) ||
             preg_match( '/(\{.*"media".*\})\s*(?:```|”|"|\'|&rdquo;|&quot;)?\s*$/is', $content, $matches ) ) {
            
            $json_str = trim( $matches[1] );
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
                    $content = str_replace( $matches[0], '', $content );
                    
                    // Sisipkan di tengah artikel (Median Injection)
                    $content = $this->inject_at_median( $content, $embed_html );
                    Logger::log( "Media Embed ({$type}) Injected at Median Point.", 'info' );
                }
            } else {
                $content = str_replace( $matches[0], '', $content );
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
        if ( preg_match( '/(?:```|”|"|\'|&rdquo;|&quot;)?\s*json(?:```|”|"|\'|&rdquo;|&quot;)?\s*(\{.*"taxonomy".*\})\s*(?:```|”|"|\'|&rdquo;|&quot;)?/is', $content, $matches ) ) {
            $json_str = trim( $matches[1] );
            $json_data = json_decode( $json_str, true );
            
            // Hapus blok JSON dari konten
            $content = str_replace( $matches[0], '', $content );

            if ( $json_data && isset( $json_data['taxonomy'] ) ) {
                return $json_data['taxonomy'];
            }
        }
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
