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
     * Add custom cron intervals.
     */
    public function add_cron_intervals( $schedules ) {
        $schedules['weekly'] = array(
            'interval' => 604800, // 7 days in seconds
            'display'  => __( 'Weekly', 'autoblog' )
        );
        $schedules['monthly'] = array(
            'interval' => 2592000, // 30 days in seconds
            'display'  => __( 'Monthly', 'autoblog' )
        );
        return $schedules;
    }

	/**
	 * Schedule the cron event.
	 */
	public function schedule_event() {
        
        $schedule = get_option( 'autoblog_cron_schedule', 'hourly' );
        $refresh_schedule = get_option( 'autoblog_refresh_schedule', 'daily' );

		if ( ! wp_next_scheduled( 'autoblog_run_pipeline' ) ) {
			wp_schedule_event( time(), $schedule, 'autoblog_run_pipeline' );
		}

        // Schedule Daily Content Refresh (Dynamic)
        if ( ! wp_next_scheduled( 'autoblog_daily_refresh' ) ) {
            wp_schedule_event( time(), $refresh_schedule, 'autoblog_daily_refresh' );
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

        $refresh_timestamp = wp_next_scheduled( 'autoblog_daily_refresh' );
        if ( $refresh_timestamp ) {
            wp_unschedule_event( $refresh_timestamp, 'autoblog_daily_refresh' );
        }
    }

    /**
     * Force reschedule when options are updated.
     */
    public function reschedule_on_update() {
        $this->unschedule_event();
        $this->schedule_event();
    }

}
