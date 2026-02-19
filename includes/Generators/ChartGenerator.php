<?php

namespace Autoblog\Generators;

/**
 * Generates chart images using QuickChart.io.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Generators
 * @author     Rasyiqi
 */
class ChartGenerator {

	/**
	 * Generate a chart URL based on data.
	 *
	 * @param array  $labels     Array of labels for the X-axis.
	 * @param array  $data       Array of numerical values.
	 * @param string $chart_type Type of chart (bar, line, pie, doughnut).
	 * @param string $title      Title of the chart.
	 * @return string The URL of the generated chart image.
	 */
	public function generate_chart_url( $labels, $data, $chart_type = 'bar', $title = 'Chart' ) {
		
		$chart_config = array(
			'type' => $chart_type,
			'data' => array(
				'labels' => $labels,
				'datasets' => array(
					array(
						'label' => $title,
						'data' => $data,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                        'borderColor' => 'rgb(54, 162, 235)',
                        'borderWidth' => 1
					)
				)
			),
            'options' => array(
                'title' => array(
                    'display' => true,
                    'text' => $title
                )
            )
		);

		$encoded_config = urlencode( json_encode( $chart_config ) );
		return "https://quickchart.io/chart?c=" . $encoded_config;

	}

}
