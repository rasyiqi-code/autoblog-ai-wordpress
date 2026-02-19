<?php

namespace Autoblog\Core;

/**
 * Define the internationalization functionality.
 *
 * @since      1.0.0
 * @package    Autoblog
 * @subpackage Autoblog/includes/Core
 * @author     Rasyiqi
 */
class i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'autoblog',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}

}
