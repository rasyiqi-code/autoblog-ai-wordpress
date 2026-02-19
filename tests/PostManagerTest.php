<?php

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Publisher\PostManager;

class PostManagerTest extends TestCase {

    public function test_simple_paragraph_conversion() {
        $input = '<p>Hello World</p>';
        $manager = new PostManager();
        $output = $this->invokeMethod($manager, 'convert_to_gutenberg_blocks', [$input]);
        
        $this->assertStringContainsString('<!-- wp:paragraph -->', $output);
        $this->assertStringContainsString('<p>Hello World</p>', $output);
    }

    public function test_heading_conversion() {
        $input = '<h1>Title</h1>';
        $manager = new PostManager();
        $output = $this->invokeMethod($manager, 'convert_to_gutenberg_blocks', [$input]);
        
        $this->assertStringContainsString('<!-- wp:heading {"level":1} -->', $output);
        $this->assertStringContainsString('<h1>Title</h1>', $output);
    }

    public function test_list_conversion() {
        $input = '<ul><li>Item 1</li></ul>';
        $manager = new PostManager();
        $output = $this->invokeMethod($manager, 'convert_to_gutenberg_blocks', [$input]);
        
        $this->assertStringContainsString('<!-- wp:list -->', $output);
        $this->assertStringContainsString('<ul><li>Item 1</li></ul>', $output);
    }

    public function test_image_process() {
        $input = '<img src="test.jpg" alt="test">';
        $manager = new PostManager();
        $output = $this->invokeMethod($manager, 'convert_to_gutenberg_blocks', [$input]);
        
        $this->assertStringContainsString('<!-- wp:image -->', $output);
        $this->assertStringContainsString('<img src="test.jpg" alt="test"/>', $output);
    }
    
    public function test_complex_content() {
        $input = '<p>Start <img src="embedded.jpg" alt="embedded"> End</p>';
        $manager = new PostManager();
        $output = $this->invokeMethod($manager, 'convert_to_gutenberg_blocks', [$input]);
        
        // Should contain image block AND paragraph blocks
        $this->assertStringContainsString('<!-- wp:image -->', $output);
        $this->assertStringContainsString('src="embedded.jpg"', $output);
        $this->assertStringContainsString('<!-- wp:paragraph -->', $output);
        $this->assertStringContainsString('<p>Start  End</p>', $output);
    }

    public function test_utf8_encoding() {
        $input = '<p>Hello world from — integration test</p>';
        $manager = new PostManager();
        $output = $this->invokeMethod($manager, 'convert_to_gutenberg_blocks', [$input]);
        
        $this->assertStringContainsString('—', $output);
        $this->assertStringNotContainsString('encoding="UTF-8"', $output);
    }

    public function test_security_sanitization() {
        $input = '<p>Hello <script>alert("XSS")</script> World</p>';
        $manager = new PostManager();
        $output = $this->invokeMethod($manager, 'convert_to_gutenberg_blocks', [$input]);
        
        // Script tag should be removed
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('alert("XSS")', $output);
        $this->assertStringContainsString('<p>Hello  World</p>', $output);
    }

    /**
     * Helper to call private methods
     */
    private function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
