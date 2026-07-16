<?php

namespace Autoblog\Utils;

/**
 * OptionCache
 *
 * Request-scoped static cache untuk WordPress options.
 *
 * get_option() dipanggil 100+ kali selama pipeline runtime.
 * Cache ini menyimpan hasilnya di static array sehingga query
 * ke database hanya sekali per key per request.
 *
 * update_option() otomatis meng-invalidate cache key terkait.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Utils
 */
class OptionCache {

    /**
     * Static cache storage: [ option_key => value ]
     *
     * Penggunaan static memastikan cache hanya berlaku dalam 1 request
     * dan tidak bocor ke request lain.
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Ambil nilai option dengan cache.
     *
     * Sama seperti get_option(), tapi hasilnya di-cache
     * secara statis untuk sisa request ini.
     *
     * @param string $option  Nama option.
     * @param mixed  $default Nilai default jika tidak ditemukan.
     * @return mixed
     */
    public static function get( $option, $default = false ) {
        // Jika sudah di-cache, langsung kembalikan
        if ( array_key_exists( $option, self::$cache ) ) {
            return self::$cache[ $option ];
        }

        // Ambil dari WordPress (termasuk menjalankan hook option_{$name})
        $value = get_option( $option, $default );

        // Simpan ke cache untuk pemanggilan berikutnya
        self::$cache[ $option ] = $value;

        return $value;
    }

    /**
     * Update option dan invalidate cache.
     *
     * Cache di-unset agar pemanggilan get() berikutnya
     * mengambil nilai fresh dari database (termasuk hook WP).
     *
     * @param string $option
     * @param mixed  $value
     * @param bool   $autoload
     * @return bool
     */
    public static function set( $option, $value, $autoload = null ) {
        // Hapus cache agar get() berikutnya mengambil fresh dari DB
        unset( self::$cache[ $option ] );

        return update_option( $option, $value, $autoload );
    }

    /**
     * Hapus option dan invalidate cache.
     *
     * @param string $option
     * @return bool
     */
    public static function delete( $option ) {
        unset( self::$cache[ $option ] );
        return delete_option( $option );
    }

    /**
     * Tandai cache key sebagai invalid (akan di-reload pada get berikutnya).
     *
     * Gunakan ketika nilai option berubah melalui sumber lain
     * (misal filter WP langsung mengubah nilai).
     *
     * @param string|null $option Nama option, atau null untuk invalidate semua.
     */
    public static function invalidate( $option = null ) {
        if ( $option === null ) {
            self::$cache = [];
            return;
        }

        unset( self::$cache[ $option ] );
    }

    /**
     * Bersihkan semua cache.
     */
    public static function flush() {
        self::$cache = [];
    }
}
