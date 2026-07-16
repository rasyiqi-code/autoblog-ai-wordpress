<?php
/**
 * Unit Test untuk ChartGenerator.
 *
 * Memverifikasi bahwa:
 * 1. generate_chart_url() mengembalikan URL QuickChart.io.
 * 2. URL mengandung parameter chart yang valid.
 * 3. Tipe chart (bar, line, pie, doughnut) tercermin di URL.
 * 4. Label dan data disertakan dalam encoded config.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Generators\ChartGenerator;

class ChartGeneratorTest extends TestCase {

    /** @var ChartGenerator */
    private $chart;

    protected function setUp(): void {
        parent::setUp();
        $this->chart = new ChartGenerator();
    }

    public function test_generates_quickchart_url() {
        $url = $this->chart->generate_chart_url(
            [ 'Jan', 'Feb', 'Mar' ],
            [ 10, 20, 15 ],
            'bar',
            'Test Chart'
        );

        $this->assertStringStartsWith( 'https://quickchart.io/chart?c=', $url );
    }

    public function test_url_contains_encoded_chart_config() {
        $url = $this->chart->generate_chart_url(
            [ 'A', 'B' ],
            [ 5, 10 ],
            'bar',
            'Simple'
        );

        // Ambil bagian encoded JSON
        $encoded = parse_url( $url, PHP_URL_QUERY );
        $this->assertStringContainsString( 'c=', $encoded );

        // Decode untuk verifikasi isi
        parse_str( $encoded, $params );
        $config = json_decode( $params['c'], true );

        $this->assertIsArray( $config );
        $this->assertEquals( 'bar', $config['type'] );
        $this->assertEquals( [ 'A', 'B' ], $config['data']['labels'] );
        $this->assertEquals( [ 5, 10 ], $config['data']['datasets'][0]['data'] );
    }

    public function test_line_chart_type() {
        $url = $this->chart->generate_chart_url( [ 'X' ], [ 1 ], 'line', 'Line Chart' );

        parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
        $config = json_decode( $params['c'], true );

        $this->assertEquals( 'line', $config['type'] );
    }

    public function test_pie_chart_type() {
        $url = $this->chart->generate_chart_url( [ 'X' ], [ 1 ], 'pie', 'Pie Chart' );

        parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
        $config = json_decode( $params['c'], true );

        $this->assertEquals( 'pie', $config['type'] );
    }

    public function test_doughnut_chart_type() {
        $url = $this->chart->generate_chart_url( [ 'X' ], [ 1 ], 'doughnut', 'Donut' );

        parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
        $config = json_decode( $params['c'], true );

        $this->assertEquals( 'doughnut', $config['type'] );
    }

    public function test_chart_has_title_in_options() {
        $url = $this->chart->generate_chart_url( [ 'X' ], [ 1 ], 'bar', 'Revenue 2024' );

        parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
        $config = json_decode( $params['c'], true );

        $this->assertEquals( 'Revenue 2024', $config['options']['title']['text'] );
        $this->assertTrue( $config['options']['title']['display'] );
    }

    public function test_defaults_to_bar_chart() {
        $url = $this->chart->generate_chart_url( [ 'X' ], [ 1 ] );

        parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
        $config = json_decode( $params['c'], true );

        $this->assertEquals( 'bar', $config['type'] );
    }

    public function test_handles_empty_labels() {
        $url = $this->chart->generate_chart_url( [], [], 'bar', 'Empty' );

        parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
        $config = json_decode( $params['c'], true );

        $this->assertEmpty( $config['data']['labels'] );
        $this->assertEmpty( $config['data']['datasets'][0]['data'] );
    }
}
