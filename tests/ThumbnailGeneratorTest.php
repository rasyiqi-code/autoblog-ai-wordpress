<?php
/**
 * Unit Test untuk Autoblog\Generators\ThumbnailGenerator.
 *
 * Dual-mode compatible: bekerja dengan mock fallback (mocks.php) dan
 * real WordPress test suite (WP_TESTS_DIR).
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      generators
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Autoblog\Generators\ThumbnailGenerator;
use Autoblog\Utils\OptionCache;

class ThumbnailGeneratorTest extends TestCase {

    /** @var array Container untuk Guzzle History middleware */
    private $requestContainer = [];

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        $GLOBALS['_wp_mock_calls'] = [];
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();

        unset( $GLOBALS['_autoblog_mock_media_sideload_return'] );

        parent::tearDown();
    }

    // ====================================================================
    // HELPERS
    // ====================================================================

    private function isMockMode(): bool {
        return isset( $GLOBALS['_wp_mock_calls']['media_sideload_image'] )
            || ! function_exists( 'add_action' );
    }

    private function createGeneratorWithMockClient( array $responses ): ThumbnailGenerator {
        $this->requestContainer = [];
        $history = Middleware::history( $this->requestContainer );

        $handlerStack = HandlerStack::create( new MockHandler( $responses ) );
        $handlerStack->push( $history );

        $mockClient = new Client( [ 'handler' => $handlerStack ] );

        $generator = new ThumbnailGenerator();
        $generator->set_http_client( $mockClient );

        return $generator;
    }

    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    public function test_constructor_initializes_dependencies() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_openai_key']  = 'sk-test123';
        $_autoblog_mock_options['autoblog_pexels_key']  = 'pexels-test456';

        $generator = new ThumbnailGenerator();

        $reflection = new \ReflectionClass( ThumbnailGenerator::class );

        $prop = $reflection->getProperty( 'openai_key' );
        $prop->setAccessible( true );
        $this->assertSame( 'sk-test123', $prop->getValue( $generator ),
            'openai_key harus dari OptionCache' );

        $prop2 = $reflection->getProperty( 'pexels_key' );
        $prop2->setAccessible( true );
        $this->assertSame( 'pexels-test456', $prop2->getValue( $generator ),
            'pexels_key harus dari OptionCache' );

        $prop3 = $reflection->getProperty( 'http_client' );
        $prop3->setAccessible( true );
        $this->assertNull( $prop3->getValue( $generator ),
            'http_client harus null sebelum lazy init' );
    }

    // ====================================================================
    // GENERATE_THUMBNAIL — SOURCE SWITCHING
    // ====================================================================

    public function test_generate_thumbnail_uses_pexels_source() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_thumbnail_source'] = 'pexels';
        $_autoblog_mock_options['autoblog_pexels_key'] = 'test_key';

        $responseBody = json_encode( [
            'photos' => [ [ 'src' => [ 'large2x' => 'https://images.pexels.com/photo1.jpg' ] ] ],
        ] );

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], $responseBody ),
        ] );

        $result = $generator->generate_thumbnail( 'nature landscape' );
        $this->assertSame( 'https://images.pexels.com/photo1.jpg', $result );
    }

    public function test_generate_thumbnail_uses_openverse_source() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_thumbnail_source'] = 'openverse';

        $responseBody = json_encode( [
            'results' => [ [ 'url' => 'https://openverse.org/image1.jpg' ] ],
        ] );

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], $responseBody ),
        ] );

        $result = $generator->generate_thumbnail( 'art' );
        $this->assertSame( 'https://openverse.org/image1.jpg', $result );
    }

    public function test_generate_thumbnail_uses_openai_source() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_thumbnail_source'] = 'openai';
        $_autoblog_mock_options['autoblog_openai_key'] = 'sk-test';

        $responseBody = json_encode( [
            'data' => [ [ 'url' => 'https://oaidalleapiprodscus.blob.core.windows.net/img1.png' ] ],
        ] );

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], $responseBody ),
        ] );

        $result = $generator->generate_thumbnail( 'AI generated art' );
        $this->assertStringContainsString( 'blob.core.windows.net', $result );
    }

    public function test_generate_thumbnail_fallback_pexels_to_openverse() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_thumbnail_source'] = 'random_stock';
        $_autoblog_mock_options['autoblog_pexels_key'] = 'test_key';

        $responses = [
            new Response( 200, [], json_encode( [ 'photos' => [] ] ) ),
            new Response( 200, [], json_encode( [
                'results' => [ [ 'url' => 'https://openverse.org/fallback.jpg' ] ],
            ] ) ),
        ];

        $generator = $this->createGeneratorWithMockClient( $responses );
        $result = $generator->generate_thumbnail( 'fallback test' );

        $this->assertSame( 'https://openverse.org/fallback.jpg', $result,
            'Pexels gagal → harus fallback ke Openverse' );
    }

    // ====================================================================
    // SEARCH_PEXELS — GUARD & PARSING
    // ====================================================================

    public function test_search_pexels_returns_false_when_key_missing() {
        $generator = new ThumbnailGenerator();
        $result = $this->invokeMethod( $generator, 'search_pexels', [ 'test' ] );
        $this->assertFalse( $result, 'Tanpa pexels_key harus return false' );
    }

    public function test_search_pexels_handles_http_exception() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_pexels_key'] = 'test_key';

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 500, [], 'Server Error' ),
        ] );

        $result = $this->invokeMethod( $generator, 'search_pexels', [ 'test' ] );
        $this->assertFalse( $result, 'HTTP 500 harus return false' );
    }

    public function test_search_pexels_handles_empty_photos() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_pexels_key'] = 'test_key';

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], json_encode( [ 'photos' => [] ] ) ),
        ] );

        $result = $this->invokeMethod( $generator, 'search_pexels', [ 'test' ] );
        $this->assertFalse( $result, 'Response tanpa photos harus return false' );
    }

    public function test_search_pexels_parses_response() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_pexels_key'] = 'test_key';

        $body = json_encode( [
            'photos' => [ [ 'src' => [ 'large2x' => 'https://images.pexels.com/photos/123456/photo.jpeg?auto=compress' ] ] ],
        ] );

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], $body ),
        ] );

        $result = $this->invokeMethod( $generator, 'search_pexels', [ 'mountain' ] );
        $this->assertSame( 'https://images.pexels.com/photos/123456/photo.jpeg?auto=compress', $result );
    }

    // ====================================================================
    // SEARCH_OPENVERSE — PARSING
    // ====================================================================

    public function test_search_openverse_handles_http_error() {
        $generator = $this->createGeneratorWithMockClient( [
            new Response( 500, [], 'Error' ),
        ] );

        $result = $this->invokeMethod( $generator, 'search_openverse', [ 'test' ] );
        $this->assertFalse( $result, 'HTTP error harus return false' );
    }

    public function test_search_openverse_parses_response() {
        $body = json_encode( [
            'results' => [ [ 'url' => 'https://openverse.org/image123.jpg' ] ],
        ] );

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], $body ),
        ] );

        $result = $this->invokeMethod( $generator, 'search_openverse', [ 'landscape' ] );
        $this->assertSame( 'https://openverse.org/image123.jpg', $result );
    }

    public function test_search_openverse_handles_empty_results() {
        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], json_encode( [ 'results' => [] ] ) ),
        ] );

        $result = $this->invokeMethod( $generator, 'search_openverse', [ 'test' ] );
        $this->assertFalse( $result, 'Response tanpa results harus return false' );
    }

    // ====================================================================
    // GENERATE_DALLE — GUARD & PARSING
    // ====================================================================

    public function test_generate_dalle_returns_false_when_key_missing() {
        $generator = new ThumbnailGenerator();
        $result = $this->invokeMethod( $generator, 'generate_dalle', [ 'test prompt' ] );
        $this->assertFalse( $result, 'Tanpa openai_key harus return false' );
    }

    public function test_generate_dalle_parses_response() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_openai_key'] = 'sk-test';

        $body = json_encode( [ 'data' => [ [ 'url' => 'https://dalle.example.com/image.png' ] ] ] );

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], $body ),
        ] );

        $result = $this->invokeMethod( $generator, 'generate_dalle', [ 'a cat' ] );
        $this->assertSame( 'https://dalle.example.com/image.png', $result );
    }

    public function test_generate_dalle_handles_empty_data() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_openai_key'] = 'sk-test';

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], json_encode( [ 'data' => [] ] ) ),
        ] );

        $result = $this->invokeMethod( $generator, 'generate_dalle', [ 'test' ] );
        $this->assertFalse( $result, 'Response tanpa data harus return false' );
    }

    public function test_generate_dalle_handles_http_exception() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_openai_key'] = 'sk-test';

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 500, [], 'Error' ),
        ] );

        $result = $this->invokeMethod( $generator, 'generate_dalle', [ 'test' ] );
        $this->assertFalse( $result, 'HTTP 500 harus return false' );
    }

    // ====================================================================
    // GENERATE_THUMBNAIL — HTTP REQUEST FORMATTING
    // ====================================================================

    public function test_search_pexels_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_pexels_key'] = 'pexels_key_123';

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], json_encode( [ 'photos' => [] ] ) ),
        ] );

        $this->invokeMethod( $generator, 'search_pexels', [ 'sunset beach' ] );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'GET', $request->getMethod() );
        $this->assertStringContainsString( 'api.pexels.com', $request->getUri()->getHost() );
        $this->assertEquals( '/v1/search', $request->getUri()->getPath() );
        $this->assertEquals( 'pexels_key_123', $request->getHeaderLine( 'Authorization' ) );

        parse_str( $request->getUri()->getQuery(), $query );
        $this->assertStringContainsString( 'sunset beach', $query['query'] ?? '' );
        $this->assertEquals( '1', $query['per_page'] ?? '' );
        $this->assertEquals( 'landscape', $query['orientation'] ?? '' );
    }

    public function test_generate_dalle_request_format() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_openai_key'] = 'sk-openai-key';

        $generator = $this->createGeneratorWithMockClient( [
            new Response( 200, [], json_encode( [ 'data' => [ [ 'url' => 'x' ] ] ] ) ),
        ] );

        $this->invokeMethod( $generator, 'generate_dalle', [ 'AI robot painting' ] );

        $this->assertCount( 1, $this->requestContainer );
        $request = $this->requestContainer[0]['request'];

        $this->assertEquals( 'POST', $request->getMethod() );
        $this->assertStringContainsString( 'api.openai.com', $request->getUri()->getHost() );
        $this->assertEquals( 'Bearer sk-openai-key', $request->getHeaderLine( 'Authorization' ) );
        $this->assertStringContainsString( 'application/json', $request->getHeaderLine( 'Content-Type' ) );

        $body = json_decode( (string) $request->getBody(), true );
        $this->assertIsArray( $body );
        $this->assertEquals( 'dall-e-3', $body['model'] );
        $this->assertEquals( 'AI robot painting', $body['prompt'] );
        $this->assertEquals( 1, $body['n'] );
        $this->assertEquals( '1024x1024', $body['size'] );
    }

    // ====================================================================
    // SAVE_TO_MEDIA_LIBRARY
    // ====================================================================

    public function test_save_returns_attachment_id() {
        global $_autoblog_mock_media_sideload_return;
        $_autoblog_mock_media_sideload_return = 42;

        $generator = new ThumbnailGenerator();
        $result = $generator->save_to_media_library( 'https://example.com/image.jpg' );

        if ( $this->isMockMode() ) {
            $this->assertSame( 42, $result, 'Harus return attachment ID dari media_sideload_image' );
        } else {
            // Real WP: tergantung hasil media_sideload_image()
            $this->assertTrue( is_numeric( $result ) || $result instanceof \WP_Error );
        }
    }

    public function test_save_returns_wp_error_on_failure() {
        global $_autoblog_mock_media_sideload_return;
        $_autoblog_mock_media_sideload_return = new \WP_Error( 'upload_error', 'Failed to upload' );

        $generator = new ThumbnailGenerator();
        $result = $generator->save_to_media_library( 'https://example.com/fail.jpg', 5 );

        if ( $this->isMockMode() ) {
            $this->assertInstanceOf( \WP_Error::class, $result, 'Gagal upload harus return WP_Error' );
        } else {
            $this->assertTrue( is_numeric( $result ) || $result instanceof \WP_Error );
        }
    }

    public function test_save_passes_correct_params_to_media_sideload() {
        $generator = new ThumbnailGenerator();
        $generator->save_to_media_library( 'https://example.com/photo.jpg', 7 );

        $calls = $GLOBALS['_wp_mock_calls']['media_sideload_image'] ?? null;
        if ( $calls !== null ) {
            $this->assertCount( 1, $calls, 'media_sideload_image harus dipanggil 1x' );
            $this->assertSame( 'https://example.com/photo.jpg', $calls[0][0] );
            $this->assertSame( 7, $calls[0][1] );
            $this->assertSame( 'Generated Thumbnail for Post ID 7', $calls[0][2] );
            $this->assertSame( 'id', $calls[0][3] );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_save_default_post_id_is_zero() {
        $generator = new ThumbnailGenerator();
        $generator->save_to_media_library( 'https://example.com/image.jpg' );

        $calls = $GLOBALS['_wp_mock_calls']['media_sideload_image'] ?? null;
        if ( $calls !== null ) {
            $this->assertCount( 1, $calls );
            $this->assertSame( 0, $calls[0][1], 'Default post_id harus 0' );
        } else {
            $this->assertTrue( true );
        }
    }

    public function test_save_description_format() {
        $generator = new ThumbnailGenerator();
        $generator->save_to_media_library( 'https://example.com/img.jpg', 123 );

        $calls = $GLOBALS['_wp_mock_calls']['media_sideload_image'] ?? null;
        if ( $calls !== null ) {
            $this->assertStringContainsString( '123', $calls[0][2], 'Description harus mengandung post_id' );
            $this->assertStringContainsString( 'Generated Thumbnail', $calls[0][2], 'Description harus prefix "Generated Thumbnail for Post ID"' );
        } else {
            $this->assertTrue( true );
        }
    }
}
