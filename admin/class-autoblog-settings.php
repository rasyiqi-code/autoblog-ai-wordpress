<?php

namespace Autoblog\Admin;

/**
 * AdminSettings
 *
 * Menangani registrasi settings WordPress dan pengambilan model/provider
 * dinamis dari models.dev API.
 *
 * Di-load oleh Autoblog.php dan dipanggil via hook 'admin_init'.
 * Method static dipakai juga oleh class lain (AIClient, Admin).
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
    // STATIC: Catalog model & provider dari models.dev API
    // ================================================================

    /**
     * Ambil catalog model terupdate dari models.dev (cache 1 hari).
     *
     * @return array [ provider_id => [ model_id => model_name ] ]
     */
    public static function get_dynamic_models() {
        $cache = get_transient( 'autoblog_models_dev_cache_v2' );
        if ( false !== $cache ) {
            return $cache;
        }

        $response = wp_remote_get( 'https://models.dev/api.json', [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) {
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return [];
        }

        $filtered = [];
        foreach ( $data as $p_id => $p_data ) {
            if ( isset( $p_data['models'] ) && is_array( $p_data['models'] ) ) {
                $filtered[ $p_id ] = [];
                foreach ( $p_data['models'] as $m_id => $m_data ) {
                    $filtered[ $p_id ][ $m_id ] = isset( $m_data['name'] ) ? $m_data['name'] : $m_id;
                }
            }
        }

        set_transient( 'autoblog_models_dev_cache_v2', $filtered, DAY_IN_SECONDS );
        return $filtered;
    }

    /**
     * Alias untuk get_dynamic_models() — backward compatibility.
     *
     * @return array
     */
    public static function get_merged_models() {
        return self::get_dynamic_models();
    }

    /**
     * Ambil semua provider dari models.dev (cache 1 hari).
     *
     * @return array [ provider_id => [ name, api, env ] ]
     */
    public static function get_dynamic_providers() {
        $cache = get_transient( 'autoblog_providers_cache_v2' );
        if ( false !== $cache ) {
            return $cache;
        }

        $response = wp_remote_get( 'https://models.dev/api.json', [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) {
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return [];
        }

        $providers = [];
        foreach ( $data as $p_id => $p_data ) {
            $providers[ $p_id ] = [
                'name' => isset( $p_data['name'] ) ? $p_data['name'] : $p_id,
                'api'  => isset( $p_data['api'] )  ? $p_data['api']  : '',
                'env'  => isset( $p_data['env'] )  ? $p_data['env']  : [],
            ];
        }

        set_transient( 'autoblog_providers_cache_v2', $providers, DAY_IN_SECONDS );
        return $providers;
    }
}
