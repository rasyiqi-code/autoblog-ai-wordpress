<?php

namespace Autoblog\Utils;

/**
 * ModelCatalog
 *
 * Mengambil dan meng-cache catalog model & provider dari models.dev API.
 *
 * Diletakkan di includes/Utils/ agar dapat di-autoload PSR-4 dan diakses
 * dari mana saja — termasuk AIClient (backend process), AdminAjax,
 * AdminSettings, dan partials view — tanpa dependency circular ke namespace Admin.
 *
 * @package Autoblog\Utils
 */
class ModelCatalog {

    /**
     * Ambil catalog model dari models.dev (cache 1 hari).
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
     * Alias get_dynamic_models() — backward compatibility.
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

    /**
     * Ambil model aktif yang terkonfigurasi untuk provider tertentu.
     *
     * @param string $provider
     * @return string
     */
    public static function get_active_model( $provider ) {
        // Coba ambil dari setelan model kustom per provider
        $custom_models = OptionCache::get( 'autoblog_custom_api_models', [] );
        $model = isset( $custom_models[$provider] ) ? $custom_models[$provider] : '';
        
        if ( empty( $model ) ) {
            $model = OptionCache::get( 'autoblog_ai_model' );
        }
        if ( empty( $model ) ) {
            $model = OptionCache::get( 'autoblog_' . $provider . '_model' );
        }
        
        // Fallback terakhir: ambil model pertama dari catalog untuk provider tersebut
        if ( empty( $model ) ) {
            $models          = self::get_dynamic_models();
            $dev_key         = ( $provider === 'gemini' || $provider === 'google' ) ? 'google'
                             : ( ( $provider === 'huggingface' || $provider === 'hf' ) ? 'huggingface' : $provider );
            $provider_models = isset( $models[$dev_key] ) ? array_keys( $models[$dev_key] ) : [];
            $model           = ! empty( $provider_models ) ? $provider_models[0] : '';
        }
        
        return $model;
    }
}
