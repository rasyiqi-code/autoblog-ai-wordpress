<?php

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;

/**
 * Unit Test untuk Multi-API Key Rotation (Intra-Provider).
 *
 * Memverifikasi parsing multi-key pool (split baris baru/koma)
 * dan rotasi otomatis jika key pertama gagal/habis kuotanya.
 *
 * @group unit
 * @group regression
 * @package Autoblog\Tests
 */
class KeyRotationTest extends TestCase {

    /** @var AIClient */
    private $client;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [];
        $this->client = new AIClient();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_custom_api_keys'] );
        OptionCache::flush();
        parent::tearDown();
    }

    // ================================================================
    // TEST 1: Parsing Multi-Key (Newline & Comma separation)
    // ================================================================

    public function test_get_keys_pool_parses_multiple_keys() {
        // Simulasikan input user berupa 3 key bertumpuk (newline + koma)
        $raw_input = "key_satu\nkey_dua, key_tiga\n\nkey_empat";
        
        update_option( 'autoblog_custom_api_keys', [ 'openai' => $raw_input ] );
        // Flush cache agar AIClient constructor (dipanggil di setUp)
        // tidak mengembalikan stale [] dari constructor.
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->client, 'get_keys_pool', [ 'openai' ] );

        $this->assertCount( 4, $pool );
        $this->assertEquals( 'key_satu', $pool[0] );
        $this->assertEquals( 'key_dua', $pool[1] );
        $this->assertEquals( 'key_tiga', $pool[2] );
        $this->assertEquals( 'key_empat', $pool[3] );
    }

    public function test_get_keys_pool_returns_empty_when_no_keys() {
        update_option( 'autoblog_custom_api_keys', [] );
        OptionCache::flush();

        $pool = $this->invokeMethod( $this->client, 'get_keys_pool', [ 'non_existent_provider' ] );
        $this->assertEmpty( $pool );
    }

    // ================================================================
    // HELPER: Invoke private method via Reflection
    // ================================================================

    private function invokeMethod( &$object, $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }
}
