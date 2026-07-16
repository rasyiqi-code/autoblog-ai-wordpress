<?php
/**
 * Unit Test untuk OptionCache langsung.
 *
 * Memverifikasi method static cache:
 * - get() dengan/ tanpa cache hit
 * - set() + invalidate cache
 * - delete()
 * - invalidate() untuk single key dan semua
 * - flush()
 *
 * Test ini memanggil OptionCache::method() langsung tanpa melalui
 * class lain, sehingga mendeteksi regresi cache secara independen.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Utils\OptionCache;

class OptionCacheUnitTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    public function test_get_returns_default_when_option_not_set() {
        $result = OptionCache::get( 'nonexistent_option', 'default_value' );
        $this->assertEquals( 'default_value', $result );
    }

    public function test_get_returns_stored_value() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['test_option'] = 'stored_value';

        $result = OptionCache::get( 'test_option' );
        $this->assertEquals( 'stored_value', $result );
    }

    public function test_get_caches_value_for_subsequent_calls() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['cache_test'] = 'first_value';

        // First call — should read from mock and cache
        $first = OptionCache::get( 'cache_test' );
        $this->assertEquals( 'first_value', $first );

        // Ubah mock option — cache harus masih return first_value
        $_autoblog_mock_options['cache_test'] = 'second_value';

        // Second call — should hit cache
        $second = OptionCache::get( 'cache_test' );
        $this->assertEquals( 'first_value', $second,
            'Should return cached value even after mock option changes' );
    }

    public function test_set_updates_option_and_invalidates_cache() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['test_set'] = 'old_value';

        // Read pertama — cache
        OptionCache::get( 'test_set' );

        // Set nilai baru via OptionCache
        OptionCache::set( 'test_set', 'new_value' );

        // Read kedua — harus ambil fresh dari mock
        $result = OptionCache::get( 'test_set' );
        $this->assertEquals( 'new_value', $result,
            'set() harus invalidate cache dan get() berikutnya ambil fresh' );
    }

    public function test_delete_removes_option_and_invalidates_cache() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['test_delete'] = 'will_be_deleted';

        OptionCache::get( 'test_delete' ); // cache it
        OptionCache::delete( 'test_delete' );

        $this->assertArrayNotHasKey( 'test_delete', $_autoblog_mock_options,
            'delete_option harus dipanggil' );

        $result = OptionCache::get( 'test_delete', 'not_found' );
        $this->assertEquals( 'not_found', $result,
            'Setelah delete, get harus return default' );
    }

    public function test_invalidate_single_key() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['opt_a'] = 'value_a';
        $_autoblog_mock_options['opt_b'] = 'value_b';

        OptionCache::get( 'opt_a' );
        OptionCache::get( 'opt_b' );

        OptionCache::invalidate( 'opt_a' );

        // opt_a harus di-reload
        $_autoblog_mock_options['opt_a'] = 'new_a';
        $this->assertEquals( 'new_a', OptionCache::get( 'opt_a' ),
            'invalidate single key harus reload value' );

        // opt_b harus tetap cached
        $_autoblog_mock_options['opt_b'] = 'new_b';
        $this->assertEquals( 'value_b', OptionCache::get( 'opt_b' ),
            'invalidate single key tidak boleh mempengaruhi key lain' );
    }

    public function test_invalidate_null_clears_all_cache() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['opt_x'] = 'old_x';
        $_autoblog_mock_options['opt_y'] = 'old_y';

        OptionCache::get( 'opt_x' );
        OptionCache::get( 'opt_y' );

        OptionCache::invalidate(); // null = clear all

        $_autoblog_mock_options['opt_x'] = 'new_x';
        $_autoblog_mock_options['opt_y'] = 'new_y';

        $this->assertEquals( 'new_x', OptionCache::get( 'opt_x' ),
            'invalidate() null harus reload opt_x' );
        $this->assertEquals( 'new_y', OptionCache::get( 'opt_y' ),
            'invalidate() null harus reload opt_y' );
    }

    public function test_flush_clears_all_cache() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['flush_test'] = 'before_flush';

        OptionCache::get( 'flush_test' );

        OptionCache::flush();

        $_autoblog_mock_options['flush_test'] = 'after_flush';
        $result = OptionCache::get( 'flush_test' );
        $this->assertEquals( 'after_flush', $result,
            'flush() harus hapus semua cache' );
    }
}
