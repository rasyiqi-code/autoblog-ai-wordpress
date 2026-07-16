<?php
/**
 * Unit Test untuk Autoblog\\Core\\Deactivator — Plugin Deactivation Hook.
 *
 * Deactivator::deactivate() adalah static method yang dipanggil saat
 * plugin dinonaktifkan. Saat ini method hanya berisi komentar.
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
use Autoblog\Core\Deactivator;

class DeactivatorTest extends TestCase {

    /**
     * Test bahwa deactivate() dapat dipanggil secara static tanpa
     * argumen dan tidak melempar exception.
     *
     * Method saat ini empty — hanya komentar.
     * Test ini berfungsi sebagai regression guard: jika method
     * berubah signature (required params), test akan fail.
     */
    public function test_deactivate_returns_null_when_called_statically() {
        $result = Deactivator::deactivate();

        // Method tidak memiliki return statement → null (PHP default)
        $this->assertNull( $result,
            'deactivate() harus mengembalikan null (void function)'
        );
    }

    /**
     * Test bahwa deactivate() dapat dipanggil tanpa error.
     *
     * PHPUnit akan menangkap exception jika method melempar.
     * Tidak ada exception = pass.
     */
    public function test_deactivate_can_be_called_without_exception() {
        Deactivator::deactivate();

        $this->assertTrue( true, 'deactivate() harus selesai tanpa error' );
    }

    /**
     * Test bahwa deactivate() mempertahankan kompatibilitas signature
     * dengan register_deactivation_hook.
     *
     * WordPress memanggil hook deactivation dengan 1 argumen (bool
     * $network_wide). Method harus bisa menerima argumen tambahan
     * tanpa fatal error (PHP mengabaikan argumen ekstra pada
     * function tanpa parameter).
     */
    public function test_deactivate_ignores_extra_arguments() {
        // Simulasi panggilan dari WordPress dengan $network_wide
        $result = Deactivator::deactivate( true );

        $this->assertNull( $result,
            'deactivate() harus mengabaikan argumen $network_wide'
        );
    }
}
