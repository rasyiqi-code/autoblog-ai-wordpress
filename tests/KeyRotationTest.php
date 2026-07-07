<?php

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Utils\AIClient;

/**
 * Unit Test untuk Multi-API Key Rotation (Intra-Provider).
 *
 * Memverifikasi parsing multi-key pool (split baris baru/koma)
 * dan rotasi otomatis jika key pertama gagal/habis kuotanya.
 *
 * @package Autoblog\Tests
 */
class KeyRotationTest extends TestCase {

    /** @var AIClient */
    private $client;

    protected function setUp(): void {
        parent::setUp();
        $this->client = new AIClient();
    }

    protected function tearDown(): void {
        update_option( 'autoblog_custom_api_keys', [] );
        parent::tearDown();
    }

    // ================================================================
    // TEST 1: Parsing Multi-Key (Newline & Comma separation)
    // ================================================================

    public function test_get_keys_pool_parses_multiple_keys() {
        // Simulasikan input user berupa 3 key bertumpuk (newline + koma)
        $raw_input = "key_satu\nkey_dua, key_tiga\n\nkey_empat";
        
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'autoblog_custom_api_keys', 'options' );
        update_option( 'autoblog_custom_api_keys', [ 'openai' => $raw_input ] );

        $pool = $this->invokeMethod( $this->client, 'get_keys_pool', [ 'openai' ] );

        $this->assertCount( 4, $pool );
        $this->assertEquals( 'key_satu', $pool[0] );
        $this->assertEquals( 'key_dua', $pool[1] );
        $this->assertEquals( 'key_tiga', $pool[2] );
        $this->assertEquals( 'key_empat', $pool[3] );
    }

    public function test_get_keys_pool_returns_empty_when_no_keys() {
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'autoblog_custom_api_keys', 'options' );
        update_option( 'autoblog_custom_api_keys', [] );

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
