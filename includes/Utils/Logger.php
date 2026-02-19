<?php

namespace Autoblog\Utils;

/**
 * Validates and logs messages to a file.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Utils
 * @author     Rasyiqi
 */
class Logger {

	/**
	 * Log a message to the debug log.
	 *
	 * @param string $message The message to log.
	 * @param string $level   The log level (info, error, warning).
	 */
	public static function log( $message, $level = 'info' ) {

		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/autoblog-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$log_file = $log_dir . '/debug.log';
		$timestamp = current_time( 'mysql' );
		$formatted_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

		error_log( $formatted_message, 3, $log_file );

	}

}
