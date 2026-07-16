<?php
/**
 * Unit Test untuk VectorStore::cosine_similarity().
 *
 * Pure function test — tidak perlu mock, tidak perlu HTTP, tidak perlu file IO.
 * Cukup instantiate VectorStore dan akses private method via reflection.
 *
 * Coverage:
 * - Identical vectors → 1.0
 * - Opposite vectors → -1.0
 * - Orthogonal vectors → 0.0
 * - Different dimensions → 0 (Bug #4 fix)
 * - Zero norm vector → 0
 * - Single element vectors
 * - Positive/negative combinations
 * - Large values
 * - Decimal precision
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\VectorStore;
use Autoblog\Utils\OptionCache;

class CosineSimilarityTest extends TestCase {

    /** @var VectorStore */
    private $store;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_embedding_provider'] = 'openai';
        $this->store = new VectorStore();
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        unset( $_autoblog_mock_options['autoblog_embedding_provider'] );
        OptionCache::flush();

        // Cleanup: hapus file vector store yang dibuat oleh setUp()
        $tmp_dir = sys_get_temp_dir();
        $store_file = $tmp_dir . '/vector_store_openai.json';
        if ( file_exists( $store_file ) ) {
            @unlink( $store_file );
        }

        parent::tearDown();
    }

    // ================================================================
    // IDENTICAL
    // ================================================================

    public function test_identical_vectors_returns_one() {
        $score = $this->invokeCosineSimilarity( [ 0.5, 0.5, 0.5 ], [ 0.5, 0.5, 0.5 ] );
        $this->assertEqualsWithDelta( 1.0, $score, 0.0001 );
    }

    public function test_identical_unit_vectors_returns_one() {
        $score = $this->invokeCosineSimilarity( [ 1.0, 0.0 ], [ 1.0, 0.0 ] );
        $this->assertEqualsWithDelta( 1.0, $score, 0.0001 );
    }

    // ================================================================
    // OPPOSITE
    // ================================================================

    public function test_opposite_vectors_returns_negative_one() {
        $score = $this->invokeCosineSimilarity( [ 0.5, 0.5 ], [ -0.5, -0.5 ] );
        $this->assertEqualsWithDelta( -1.0, $score, 0.0001 );
    }

    public function test_opposite_unit_vectors_returns_negative_one() {
        $score = $this->invokeCosineSimilarity( [ 1.0, 0.0 ], [ -1.0, 0.0 ] );
        $this->assertEqualsWithDelta( -1.0, $score, 0.0001 );
    }

    // ================================================================
    // ORTHOGONAL (dot product = 0)
    // ================================================================

    public function test_orthogonal_vectors_returns_zero() {
        $score = $this->invokeCosineSimilarity( [ 1.0, 0.0 ], [ 0.0, 1.0 ] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    public function test_orthogonal_3d_vectors_returns_zero() {
        // [1,0,0] · [0,1,0] = 0
        $score = $this->invokeCosineSimilarity( [ 1.0, 0.0, 0.0 ], [ 0.0, 1.0, 0.0 ] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }
    // ================================================================
    // DIFFERENT DIMENSIONS (Bug #4 fix)
    // ================================================================

    /** @group regression */
    public function test_different_dimensions_returns_zero() {
        $score = $this->invokeCosineSimilarity( [ 0.1, 0.2, 0.3 ], [ 0.1, 0.2 ] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    /** @group regression */
    public function test_empty_first_vector_returns_zero() {
        $score = $this->invokeCosineSimilarity( [], [ 0.1, 0.2 ] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    /** @group regression */
    public function test_empty_second_vector_returns_zero() {
        $score = $this->invokeCosineSimilarity( [ 0.1, 0.2 ], [] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    // ================================================================
    // ZERO NORM
    // ================================================================

    public function test_zero_vector_returns_zero() {
        $score = $this->invokeCosineSimilarity( [ 0.0, 0.0 ], [ 0.5, 0.5 ] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    public function test_both_zero_vectors_returns_zero() {
        $score = $this->invokeCosineSimilarity( [ 0.0, 0.0 ], [ 0.0, 0.0 ] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    // ================================================================
    // PARTIAL SIMILARITY
    // ================================================================

    public function test_partial_similarity_positive() {
        // [1,0] and [0.707, 0.707] → angle 45° → cos = 0.707...
        $score = $this->invokeCosineSimilarity( [ 1.0, 0.0 ], [ 0.707, 0.707 ] );
        $this->assertEqualsWithDelta( 0.707, $score, 0.01 );
    }

    public function test_partial_similarity_60_degrees() {
        // [1,0] and [0.5, 0.866] → angle 60° → cos = 0.5
        $score = $this->invokeCosineSimilarity( [ 1.0, 0.0 ], [ 0.5, 0.866 ] );
        $this->assertEqualsWithDelta( 0.5, $score, 0.01 );
    }

    // ================================================================
    // SINGLE ELEMENT VECTORS
    // ================================================================

    public function test_single_element_identical() {
        $score = $this->invokeCosineSimilarity( [ 3.0 ], [ 3.0 ] );
        $this->assertEqualsWithDelta( 1.0, $score, 0.0001 );
    }

    public function test_single_element_opposite() {
        $score = $this->invokeCosineSimilarity( [ 3.0 ], [ -3.0 ] );
        $this->assertEqualsWithDelta( -1.0, $score, 0.0001 );
    }

    // ================================================================
    // LARGE VALUES
    // ================================================================

    public function test_large_values() {
        // Scale shouldn't affect cosine similarity
        $score = $this->invokeCosineSimilarity( [ 1000, 2000 ], [ 3000, 4000 ] );
        // [1,2] and [3,4] cos = (3+8)/(sqrt5 * 5) = 11/(2.236*5) = 11/11.18 ≈ 0.984
        $this->assertEqualsWithDelta( 0.984, $score, 0.01 );
    }

    // ================================================================
    // NEGATIVE & POSITIVE MIXED
    // ================================================================

    public function test_mixed_sign_vectors() {
        // [1, -1] and [1, 1] → dot = 0 → orthogonal
        $score = $this->invokeCosineSimilarity( [ 1.0, -1.0 ], [ 1.0, 1.0 ] );
        $this->assertEqualsWithDelta( 0.0, $score, 0.0001 );
    }

    public function test_both_negative_vectors() {
        // [ -1, -1 ] and [ -2, -2 ] → both same direction → cos = 1
        $score = $this->invokeCosineSimilarity( [ -1.0, -1.0 ], [ -2.0, -2.0 ] );
        $this->assertEqualsWithDelta( 1.0, $score, 0.0001 );
    }

    // ================================================================
    // HELPER: Invoke private cosine_similarity via Reflection
    // ================================================================

    private function invokeCosineSimilarity( array $vec1, array $vec2 ) {
        $reflection = new \ReflectionClass( $this->store );
        $method     = $reflection->getMethod( 'cosine_similarity' );
        $method->setAccessible( true );
        return $method->invokeArgs( $this->store, [ $vec1, $vec2 ] );
    }
}
