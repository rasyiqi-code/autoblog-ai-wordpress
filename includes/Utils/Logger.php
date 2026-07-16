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
		// Bug #15 Fix: Atomic rotation - rename dulu baru tulis ke path baru
		$max_log_size = 5 * 1024 * 1024; // 5MB
		if ( file_exists( $log_file ) && filesize( $log_file ) > $max_log_size ) {
			$backup_file = $log_dir . '/debug.log.old';
			// Rotasi atomic: rename file lama dulu ke backup
			if ( file_exists( $backup_file ) ) {
				@unlink( $backup_file );
			}
			// LOCK_EX memastikan tidak ada proses lain yg menulis saat rename
			$lock_fn = $log_file . '.lock';
			$lock_fp = @fopen( $lock_fn, 'c' );
			if ( $lock_fp && flock( $lock_fp, LOCK_EX ) ) {
				$current_size = @filesize( $log_file );
				if ( $current_size && $current_size > $max_log_size ) {
					@rename( $log_file, $backup_file );
				}
				flock( $lock_fp, LOCK_UN );
			}
			if ( $lock_fp ) {
				@fclose( $lock_fp );
			}
			@unlink( $lock_fn );
		}

		$timestamp = current_time( 'mysql' );
		$formatted_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

		error_log( $formatted_message, 3, $log_file );

	}

}
