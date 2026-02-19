<?php

namespace Autoblog\Interfaces;

/**
 * Interface for all data sources.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Interfaces
 * @author     Rasyiqi
 */
interface SourceInterface {

	/**
	 * Fetch data from the source.
	 *
	 * @return array Array of raw data items.
	 */
	public function fetch_data();

	/**
	 * Validate if the source is accessible and valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_source();

	/**
	 * Get the type of the source.
	 *
	 * @return string Source type (e.g., 'rss', 'web', 'file').
	 */
	public function get_display_name();

}
