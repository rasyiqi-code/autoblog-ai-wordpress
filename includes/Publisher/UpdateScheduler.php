<?php

namespace Autoblog\Publisher;

/**
 * Handles scheduling of autoblog updates.
 *
 * @package    Autoblog
 * @subpackage Autoblog/includes/Publisher
 * @author     Rasyiqi
 */
class UpdateScheduler {

	/**
	 * Schedule the cron event.
	 */
	public function schedule_event() {
        
        $schedule = get_option( 'autoblog_cron_schedule', 'hourly' );

		if ( ! wp_next_scheduled( 'autoblog_run_pipeline' ) ) {
			wp_schedule_event( time(), $schedule, 'autoblog_run_pipeline' );
		}

        // Schedule Daily Content Refresh
        if ( ! wp_next_scheduled( 'autoblog_daily_refresh' ) ) {
            wp_schedule_event( time(), 'daily', 'autoblog_daily_refresh' );
        }
	}

    /**
     * Unschedule the cron event.
     */
    public function unschedule_event() {
        $timestamp = wp_next_scheduled( 'autoblog_run_pipeline' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'autoblog_run_pipeline' );
        }
    }

}
