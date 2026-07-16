<?php
/**
 * Unit Test untuk Autoblog\\Core\\Activator — Plugin Activation Hook.
 *
 * Activator::activate() adalah static method yang dipanggil saat
 * plugin diaktifkan. Saat ini method hanya berisi komentar.
 *
 * Test memverifikasi:
 * - Method dapat dipanggil secara static
 * - Method tidak melempar exception
 * - Method tidak memerlukan argumen
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      entrypoint
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\Activator;

class ActivatorTest extends TestCase {

    /**
     * Test bahwa activate() dapat dipanggil secara static tanpa argumen
     * dan tidak melempar exception.
     *
     * Method saat ini empty — hanya komentar.
     * Test ini berfungsi sebagai regression guard: jika method
     * berubah signature (required params), test akan fail.
     */
    public function test_activate_returns_null_when_called_statically() {
        $result = Activator::activate();

        // Method tidak memiliki return statement → null (PHP default)
        $this->assertNull( $result,
            'activate() harus mengembalikan null (void function)'
        );
    }

    /**
     * Test bahwa activate() dapat dipanggil tanpa error.
     *
     * PHPUnit akan menangkap exception jika method melempar.
     * Tidak ada exception = pass.
     */
    public function test_activate_can_be_called_without_exception() {
        // Tidak ada exception yang diharapkan
        Activator::activate();

        $this->assertTrue( true, 'activate() harus selesai tanpa error' );
    }

    /**
     * Test bahwa activate() mempertahankan kompatibilitas signature
     * dengan register_activation_hook.
     *
     * WordPress memanggil hook activation dengan 1 argumen (bool
     * $network_wide). Method harus bisa menerima argumen tambahan
     * tanpa fatal error (PHP mengabaikan argumen ekstra pada
     * function tanpa parameter).
     */
    public function test_activate_ignores_extra_arguments() {
        // Simulasi panggilan dari WordPress dengan $network_wide
        // PHP akan mengabaikan argumen berlebih
        $result = Activator::activate( true );

        $this->assertNull( $result,
            'activate() harus mengabaikan argumen $network_wide'
        );
    }
}
