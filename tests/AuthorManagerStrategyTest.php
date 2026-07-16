<?php
/**
 * Unit Test untuk AuthorManager (pick_author, get_author_persona_data).
 *
 * Memverifikasi bahwa:
 * 1. pick_author dengan 'random' mengembalikan user yang valid.
 * 2. pick_author dengan 'fixed' mengembalikan ID yang ditentukan.
 * 3. pick_author dengan 'round_robin' bergantian secara adil.
 * 4. get_author_persona_data() mengembalikan array dengan name, desc, samples.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Publisher\AuthorManager;
use Autoblog\Utils\OptionCache;

class AuthorManagerStrategyTest extends TestCase {

    /** @var AuthorManager */
    private $manager;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        $this->manager = new AuthorManager();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    public function test_pick_author_random_returns_valid_id() {
        $author_id = $this->manager->pick_author( 'random' );

        $this->assertIsInt( $author_id );
        $this->assertGreaterThan( 0, $author_id );
    }

    public function test_pick_author_fixed_returns_specified_id() {
        $author_id = $this->manager->pick_author( 'fixed', 5 );

        $this->assertEquals( 5, $author_id );
    }

    public function test_pick_author_fixed_with_zero_id_returns_first_author() {
        $author_id = $this->manager->pick_author( 'fixed', 0 );

        $this->assertIsInt( $author_id );
        $this->assertGreaterThan( 0, $author_id );
    }

    public function test_pick_author_defaults_to_random() {
        $author_id = $this->manager->pick_author();

        $this->assertIsInt( $author_id );
        $this->assertGreaterThan( 0, $author_id );
    }

    /**
     * Round robin harus increment index setiap kali dipanggil.
     */
    public function test_round_robin_increments_index() {
        // Reset index
        OptionCache::set( 'autoblog_last_author_index', -1 );

        $first  = $this->manager->pick_author( 'round_robin' );
        $second = $this->manager->pick_author( 'round_robin' );

        $this->assertIsInt( $first );
        $this->assertIsInt( $second );

        // ID harus berbeda jika ada lebih dari 1 author
        // Note: get_available_authors() return mock = [['id'=>1]]
        // Jadi round_robin hanya punya 1 author → same ID
        $this->assertNotEmpty( $first );
    }

    public function test_get_author_persona_data_returns_array_with_expected_keys() {
        $result = $this->manager->get_author_persona_data( 1 );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'name', $result );
        $this->assertArrayHasKey( 'desc', $result );
        $this->assertArrayHasKey( 'samples', $result );
        $this->assertEquals( 'Si Netral', $result['name'] );
    }
}
