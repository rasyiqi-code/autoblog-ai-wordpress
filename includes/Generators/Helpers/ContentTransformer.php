<?php

namespace Autoblog\Generators\Helpers;

use Autoblog\Utils\Logger;

/**
 * Trait ContentTransformer
 *
 * Berisi helper untuk mentransformasi konten artikel:
 * - Render chart JSON → img tag
 * - Render media embed (YouTube, X/Twitter)
 * - Sisipkan elemen di titik median artikel
 * - Konversi Markdown → HTML
 * - Konversi inline Markdown (bold/italic)
 *
 * Di-use oleh ArticleWriter.
 *
 * @package Autoblog\Generators\Helpers
 */
trait ContentTransformer {

    // ================================================================
    // CHART: Deteksi & render Chart JSON dari output AI
    // ================================================================

    /**
     * Deteksi blok JSON chart dalam konten dan ganti dengan <img> dari ChartGenerator.
     *
     * @param string $content
     * @return string
     */
    private function process_chart_json( $content ) {
        // Bug #14 Fix: Batasi panjang konten untuk cegah PCRE stack overflow dari recursive regex
        if ( strlen( $content ) > 50000 ) {
            Logger::log( 'ContentTransformer: Konten terlalu panjang (>50KB), lewati chart processing.', 'warning' );
            return $content;
        }
        $regex = '/(?:```(?:html|json)?\s*)?(\{(?:[^{}]+|(?R))*\})(?:\s*```)?/isu';

        if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $json_str  = $this->normalize_json_quotes( trim( $match[1] ) );
                $json_data = json_decode( $json_str, true );

                if ( $json_data && isset( $json_data['chart'] ) ) {
                    $chart_config = $json_data['chart'];

                    if ( ! class_exists( 'Autoblog\Generators\ChartGenerator' ) ) {
                        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'ChartGenerator.php';
                    }

                    $chart_gen = new \Autoblog\Generators\ChartGenerator();
                    $chart_url = $chart_gen->generate_chart_url(
                        isset( $chart_config['labels'] ) ? $chart_config['labels'] : [],
                        isset( $chart_config['data'] )   ? $chart_config['data']   : [],
                        isset( $chart_config['type'] )   ? $chart_config['type']   : 'bar',
                        isset( $chart_config['title'] )  ? $chart_config['title']  : 'Chart'
                    );

                    if ( $chart_url ) {
                        $chart_html  = "<figure class='autoblog-chart' style='margin: 25px 0; text-align: center;'>";
                        $chart_html .= "<img src='{$chart_url}' alt='" . esc_attr( $chart_config['title'] ) . "' style='max-width: 100%; border-radius: 8px;'>";
                        $chart_html .= "<figcaption style='font-style: italic; font-size: 0.9em; margin-top: 8px;'>" . esc_html( $chart_config['title'] ) . "</figcaption>";
                        $chart_html .= "</figure>";

                        $content = str_replace( $match[0], '', $content );
                        $content = $this->inject_at_median( $content, $chart_html );
                        Logger::log( 'Chart Injected at Median Point.', 'info' );
                    }
                }
            }
        }

        return $content;
    }

    // ================================================================
    // MEDIA EMBED: YouTube / X (Twitter)
    // ================================================================

    /**
     * Deteksi blok JSON media embed dan ganti dengan iframe/URL.
     *
     * @param string $content
     * @return string
     */
    private function process_media_embeds( $content ) {
        // Bug #14 Fix: Batasi panjang konten untuk cegah PCRE stack overflow
        if ( strlen( $content ) > 50000 ) {
            Logger::log( 'ContentTransformer: Konten terlalu panjang (>50KB), lewati media embed processing.', 'warning' );
            return $content;
        }
        $regex = '/(?:```(?:html|json)?\s*)?(\{(?:[^{}]+|(?R))*\})(?:\s*```)?/isu';

        if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $json_str  = $this->normalize_json_quotes( trim( $match[1] ) );
                $json_data = json_decode( $json_str, true );

                if ( $json_data && isset( $json_data['media'] ) ) {
                    $media = $json_data['media'];
                    $type  = isset( $media['type'] ) ? strtolower( $media['type'] ) : '';
                    $id    = isset( $media['id'] )   ? $media['id']                 : '';

                    $embed_html = '';

                    if ( $type === 'youtube' ) {
                        // Ekstrak video ID jika full URL di-pass
                        if ( strpos( $id, 'youtu' ) !== false ) {
                            preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $id, $m );
                            $id = isset( $m[1] ) ? $m[1] : $id;
                        }
                        $embed_html = "<div class='autoblog-embed' style='margin: 25px 0;'><iframe width='100%' height='400' src='https://www.youtube.com/embed/{$id}' frameborder='0' allowfullscreen></iframe></div>";
                    } elseif ( $type === 'twitter' || $type === 'x' ) {
                        // WordPress akan auto-embed jika URL ada di baris sendiri
                        $embed_html = "\n\nhttps://twitter.com/x/status/{$id}\n\n";
                    }

                    if ( ! empty( $embed_html ) ) {
                        $content = str_replace( $match[0], '', $content );
                        $content = $this->inject_at_median( $content, $embed_html );
                        Logger::log( "Media Embed ({$type}) Injected at Median Point.", 'info' );
                    }
                }
            }
        }

        return $content;
    }

    // ================================================================
    // INJEKSI: Sisipkan elemen di titik median artikel
    // ================================================================

    /**
     * Sisipkan HTML di titik tengah (median) jumlah paragraf.
     *
     * @param string $content
     * @param string $element_html
     * @return string
     */
    private function inject_at_median( $content, $element_html ) {
        $parts       = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
        $total_parts = count( $parts );

        // Konten terlalu pendek — taruh di akhir
        if ( $total_parts < 4 ) {
            return $content . $element_html;
        }

        $median_index = floor( ( $total_parts / 2 ) / 2 ) * 2;
        $new_content  = '';

        foreach ( $parts as $i => $part ) {
            $new_content .= $part;
            if ( $i === $median_index + 1 ) {
                $new_content .= "\n" . $element_html . "\n";
            }
        }

        return $new_content;
    }

    // ================================================================
    // MARKDOWN → HTML CONVERTER
    // ================================================================

    /**
     * Konversi Markdown ke HTML.
     *
     * Fallback saat AI mengembalikan Markdown meskipun diminta HTML.
     *
     * @param string $markdown
     * @return string
     */
    private function markdown_to_html( $markdown ) {
        if ( empty( $markdown ) ) {
            return '';
        }

        $lines     = explode( "\n", $markdown );
        $html      = '';
        $in_list   = false;
        $list_type = '';

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // Baris kosong: tutup list
            if ( empty( $trimmed ) ) {
                if ( $in_list ) {
                    $html .= "</{$list_type}>\n";
                    $in_list   = false;
                    $list_type = '';
                }
                continue;
            }

            // Heading: # sampai ######
            if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trimmed, $m ) ) {
                if ( $in_list ) { $html .= "</{$list_type}>\n"; $in_list = false; }
                $level = strlen( $m[1] );
                $html .= "<h{$level}>" . $this->convert_inline_markdown( trim( $m[2] ) ) . "</h{$level}>\n";
                continue;
            }

            // Unordered list: - item atau * item
            if ( preg_match( '/^[\-\*]\s+(.+)$/', $trimmed, $m ) ) {
                if ( ! $in_list || $list_type !== 'ul' ) {
                    if ( $in_list ) { $html .= "</{$list_type}>\n"; }
                    $html .= "<ul>\n"; $in_list = true; $list_type = 'ul';
                }
                $html .= "<li>" . $this->convert_inline_markdown( trim( $m[1] ) ) . "</li>\n";
                continue;
            }

            // Ordered list: 1. item
            if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $m ) ) {
                if ( ! $in_list || $list_type !== 'ol' ) {
                    if ( $in_list ) { $html .= "</{$list_type}>\n"; }
                    $html .= "<ol>\n"; $in_list = true; $list_type = 'ol';
                }
                $html .= "<li>" . $this->convert_inline_markdown( trim( $m[1] ) ) . "</li>\n";
                continue;
            }

            // Blockquote: > text
            if ( preg_match( '/^>\s+(.+)$/', $trimmed, $m ) ) {
                if ( $in_list ) { $html .= "</{$list_type}>\n"; $in_list = false; }
                $html .= "<blockquote><p>" . $this->convert_inline_markdown( trim( $m[1] ) ) . "</p></blockquote>\n";
                continue;
            }

            // Default: paragraf
            if ( $in_list ) { $html .= "</{$list_type}>\n"; $in_list = false; }
            $html .= "<p>" . $this->convert_inline_markdown( $trimmed ) . "</p>\n";
        }

        if ( $in_list ) {
            $html .= "</{$list_type}>\n";
        }

        return $html;
    }

    /**
     * Konversi inline Markdown (bold, italic) ke HTML.
     *
     * @param string $text
     * @return string
     */
    private function convert_inline_markdown( $text ) {
        // Bold: **text** atau __text__
        $text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
        $text = preg_replace( '/__(.+?)__/',     '<strong>$1</strong>', $text );

        // Italic: *text* atau _text_ (setelah bold agar tidak conflict)
        $text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
        $text = preg_replace( '/_(.+?)_/',   '<em>$1</em>', $text );

        return $text;
    }

    // ================================================================
    // UTILITY: Normalisasi smart quotes di JSON string
    // ================================================================

    /**
     * Ganti smart quotes ke tanda petik ASCII agar json_decode tidak gagal.
     *
     * @param string $str
     * @return string
     */
    private function normalize_json_quotes( $str ) {
        return str_replace(
            [ "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}" ],
            '"',
            $str
        );
    }
}
