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

		// Bug #13 Fix: Rotasi log otomatis jika ukuran melebihi 5MB
		// Mencegah disk penuh karena log menumpuk tanpa batas saat cron berjalan terus
		$max_log_size = 5 * 1024 * 1024; // 5MB
		if ( file_exists( $log_file ) && filesize( $log_file ) > $max_log_size ) {
			$backup_file = $log_dir . '/debug.log.old';
			// Hapus backup lama dan ganti dengan yang sekarang
			if ( file_exists( $backup_file ) ) {
				@unlink( $backup_file );
			}
			@rename( $log_file, $backup_file );
		}

		$timestamp = current_time( 'mysql' );
		$formatted_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

		error_log( $formatted_message, 3, $log_file );

	}

}
