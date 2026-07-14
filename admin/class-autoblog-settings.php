<?php

namespace Autoblog\Admin;

use Autoblog\Utils\ModelCatalog;

/**
 * AdminSettings
 *
 * Menangani registrasi settings WordPress.
 * Catalog model & provider didelegasikan ke ModelCatalog (includes/Utils/)
 * agar dapat diakses dari mana saja via PSR-4 autoload.
 *
 * @package    Autoblog
 * @subpackage Autoblog/admin
 */
class AdminSettings {

    /**
     * Register semua setting plugin ke WordPress Settings API.
     *
     * Setiap tab menggunakan option group sendiri agar submit di satu tab
     * tidak menghapus setting di tab lain.
     */
    public function register_settings() {

        // ── Tab: API Keys ──
        register_setting( 'autoblog_keys', 'autoblog_openai_key' );
        register_setting( 'autoblog_keys', 'autoblog_anthropic_key' );
        register_setting( 'autoblog_keys', 'autoblog_gemini_key' );
        register_setting( 'autoblog_keys', 'autoblog_groq_key' );
        register_setting( 'autoblog_keys', 'autoblog_hf_key' );
        register_setting( 'autoblog_keys', 'autoblog_openrouter_key' );
        register_setting( 'autoblog_keys', 'autoblog_serpapi_key' );
        register_setting( 'autoblog_keys', 'autoblog_pexels_key' );
        register_setting( 'autoblog_keys', 'autoblog_custom_api_keys' );
        register_setting( 'autoblog_keys', 'autoblog_custom_api_endpoints' );
        register_setting( 'autoblog_keys', 'autoblog_custom_api_models' );

        // Duplikat registrasi ke group keys untuk form terpadu
        register_setting( 'autoblog_keys', 'autoblog_ai_provider' );
        register_setting( 'autoblog_keys', 'autoblog_openai_model' );
        register_setting( 'autoblog_keys', 'autoblog_anthropic_model' );
        register_setting( 'autoblog_keys', 'autoblog_gemini_model' );
        register_setting( 'autoblog_keys', 'autoblog_groq_model' );
        register_setting( 'autoblog_keys', 'autoblog_openrouter_model' );
        register_setting( 'autoblog_keys', 'autoblog_hf_model' );
        register_setting( 'autoblog_keys', 'autoblog_embedding_provider' );
        register_setting( 'autoblog_keys', 'autoblog_gemini_grounding' );
        register_setting( 'autoblog_keys', 'autoblog_thumbnail_source' );
        register_setting( 'autoblog_keys', 'autoblog_enable_dalle' );
        register_setting( 'autoblog_keys', 'autoblog_enable_stock_pexels' );
        register_setting( 'autoblog_keys', 'autoblog_enable_stock_openverse' );
        register_setting( 'autoblog_keys', 'autoblog_enable_fallback' );
        register_setting( 'autoblog_keys', 'autoblog_ai_model' );

        // ── Tab: AI Engine ──
        register_setting( 'autoblog_ai', 'autoblog_ai_provider' );
        register_setting( 'autoblog_ai', 'autoblog_openai_model' );
        register_setting( 'autoblog_ai', 'autoblog_anthropic_model' );
        register_setting( 'autoblog_ai', 'autoblog_gemini_model' );
        register_setting( 'autoblog_ai', 'autoblog_groq_model' );
        register_setting( 'autoblog_ai', 'autoblog_openrouter_model' );
        register_setting( 'autoblog_ai', 'autoblog_hf_model' );
        register_setting( 'autoblog_ai', 'autoblog_embedding_provider' );
        register_setting( 'autoblog_ai', 'autoblog_gemini_grounding' );
        register_setting( 'autoblog_ai', 'autoblog_thumbnail_source' );
        register_setting( 'autoblog_ai', 'autoblog_enable_dalle' );
        register_setting( 'autoblog_ai', 'autoblog_enable_stock_pexels' );
        register_setting( 'autoblog_ai', 'autoblog_enable_stock_openverse' );
        register_setting( 'autoblog_ai', 'autoblog_enable_fallback' );
        register_setting( 'autoblog_ai', 'autoblog_ai_model' );

        // ── Tab: Data Sources ──
        register_setting( 'autoblog_ds', 'autoblog_data_source_mode' );
        register_setting( 'autoblog_ds', 'autoblog_search_provider' );

        // ── Tab: Writing Style / Persona ──
        register_setting( 'autoblog_style', 'autoblog_enable_personality' );
        register_setting( 'autoblog_style', 'autoblog_personality_samples' );
        register_setting( 'autoblog_style', 'autoblog_author_strategy' );
        register_setting( 'autoblog_style', 'autoblog_author_fixed_id' );

        // ── Tab: Advanced Intelligence ──
        register_setting( 'autoblog_adv', 'autoblog_enable_dynamic_search' );
        register_setting( 'autoblog_adv', 'autoblog_enable_deep_research' );
        register_setting( 'autoblog_adv', 'autoblog_enable_interlinking' );
        register_setting( 'autoblog_adv', 'autoblog_enable_living_content' );
        register_setting( 'autoblog_adv', 'autoblog_enable_multimodal' );

        // ── Tab: Tools & Logs ──
        register_setting( 'autoblog_ops', 'autoblog_cron_schedule' );
        register_setting( 'autoblog_ops', 'autoblog_refresh_schedule' );
        register_setting( 'autoblog_ops', 'autoblog_post_status' );
    }

    // ================================================================
    // STATIC: Delegasi ke ModelCatalog (DRY)
    // ================================================================

    /** @return array */
    public static function get_dynamic_models() {
        return ModelCatalog::get_dynamic_models();
    }

    /** @return array */
    public static function get_merged_models() {
        return ModelCatalog::get_merged_models();
    }

    /** @return array */
    public static function get_dynamic_providers() {
        return ModelCatalog::get_dynamic_providers();
    }
}
