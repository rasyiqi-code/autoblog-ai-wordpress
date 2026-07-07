<?php

namespace Autoblog\Generators\Helpers;

use Autoblog\Utils\Logger;

/**
 * Trait ContentCleaner
 *
 * Berisi helper untuk membersihkan, mendeteksi, dan mengekstrak
 * data dari konten artikel:
 * - Bersihkan teks dari HTML/JS/junk
 * - Deteksi apakah konten adalah HTML atau Markdown
 * - Ekstrak blok JSON Taxonomy dari output AI
 *
 * Di-use oleh ArticleWriter.
 *
 * @package Autoblog\Generators\Helpers
 */
trait ContentCleaner {

    // ================================================================
    // TEXT CLEANING
    // ================================================================

    /**
     * Bersihkan teks dari HTML tags, script/style, dan whitespace ganda.
     * Berguna untuk menyiapkan source data sebelum dikirim ke AI.
     *
     * @param string $text
     * @return string
     */
    private function clean_text( $text ) {
        if ( empty( $text ) ) {
            return '';
        }

        // Hapus tag <script> dan <style> beserta kontennya
        $text = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $text );

        // Strip semua HTML tags
        $text = strip_tags( $text );

        // Decode HTML entities
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5 );

        // Hapus common sharing boilerplate untuk menghemat token
        $boilerplate_patterns = [
            '/(share|follow|tweet|like)\s+(us|on|this|page|twitter|facebook|linkedin|instagram)\b/i',
            '/read\s+more\b.*/i',
            '/subscribe\s+to\s+our\s+newsletter\b/i',
            '/all\s+rights\s+reserved\b/i',
            '/copyright\s+©\s*\d{4}/i',
        ];
        $text = preg_replace($boilerplate_patterns, '', $text);

        // Normalisasi whitespace ganda
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( $text );
    }

    // ================================================================
    // FORMAT DETECTION
    // ================================================================

    /**
     * Deteksi apakah string mengandung HTML.
     *
     * Dianggap HTML jika ada minimal 2 block-level HTML tags.
     * Jika tidak, kemungkinan output Markdown dari AI.
     *
     * @param string $text
     * @return bool
     */
    private function is_html( $text ) {
        if ( empty( $text ) ) {
            return false;
        }

        $count = preg_match_all( '/<(h[1-6]|p|ul|ol|li|div|blockquote|table|section|article)\b/i', $text );

        return $count >= 2;
    }

    // ================================================================
    // TAXONOMY EXTRACTION
    // ================================================================

    /**
     * Ekstrak data taksonomi (Category & Tags) dari output AI.
     *
     * Mencari blok JSON dengan key 'taxonomy' di dalam konten.
     * Jika ditemukan, menghapus blok tersebut dari $content (by reference)
     * dan mengembalikan datanya.
     *
     * @param string $content Konten artikel (passed by reference, blok JSON akan dihapus).
     * @return array|null
     */
    private function extract_taxonomy_json( &$content ) {
        // Regex rekursif untuk menangkap blok JSON meskipun ada nested braces
        $regex = '/(?:```(?:html|json)?\s*)?(\{(?:[^{}]+|(?R))*\})(?:\s*```)?/isu';

        if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $json_str = trim( $match[1] );

                // Konversi smart quotes ke tanda petik standar
                $json_str  = str_replace(
                    [ "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}" ],
                    '"',
                    $json_str
                );

                $json_data = json_decode( $json_str, true );

                if ( $json_data && isset( $json_data['taxonomy'] ) ) {
                    // Hapus blok JSON dari konten (termasuk pembungkus ```json ... ```)
                    $content = str_replace( $match[0], '', $content );
                    Logger::log( 'Taxonomy JSON extracted: ' . print_r( $json_data['taxonomy'], true ), 'debug' );
                    return $json_data['taxonomy'];
                }
            }
        }

        Logger::log( 'Taxonomy JSON NOT found or malformed in AI response.', 'warning' );
        return null;
    }
}
