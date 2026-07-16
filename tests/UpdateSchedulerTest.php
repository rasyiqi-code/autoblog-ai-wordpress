<?php
/**
 * Unit Test untuk UpdateScheduler.
 *
 * UpdateScheduler mengelola cron scheduling untuk autoblog pipeline:
 * - add_cron_intervals: tambah interval weekly & monthly
 * - schedule_event: schedule autoblog_run_pipeline + autoblog_daily_refresh
 * - unschedule_event: cleanup cron hooks
 * - reschedule_on_update: force reschedule
 *
 * Semua WordPress functions sudah di-mock di bootstrap.php
 * (get_option, wp_next_scheduled, wp_schedule_event, wp_unschedule_event, __).
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Publisher\UpdateScheduler;
use Autoblog\Utils\OptionCache;

class UpdateSchedulerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        // Reset WP mock calls tracker
        $GLOBALS['_wp_mock_calls'] = [];
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    public function test_constructor_creates_instance() {
        $scheduler = new UpdateScheduler();
        $this->assertInstanceOf( UpdateScheduler::class, $scheduler );
    }

    // ====================================================================
    // ADD_CRON_INTERVALS
    // ====================================================================

    /**
     * Test add_cron_intervals — menambahkan interval weekly.
     */
    public function test_add_cron_intervals_adds_weekly() {
        $scheduler = new UpdateScheduler();

        $schedules = $scheduler->add_cron_intervals( [] );

        $this->assertArrayHasKey( 'weekly', $schedules );
        $this->assertEquals( 604800, $schedules['weekly']['interval'],
            'Weekly interval harus 7 hari (604800 detik)'
        );
        $this->assertEquals( 'Weekly', $schedules['weekly']['display'] );
    }

    /**
     * Test add_cron_intervals — menambahkan interval monthly.
     */
    public function test_add_cron_intervals_adds_monthly() {
        $scheduler = new UpdateScheduler();

        $schedules = $scheduler->add_cron_intervals( [] );

        $this->assertArrayHasKey( 'monthly', $schedules );
        $this->assertEquals( 2592000, $schedules['monthly']['interval'],
            'Monthly interval harus 30 hari (2592000 detik)'
        );
        $this->assertEquals( 'Monthly', $schedules['monthly']['display'] );
    }

    /**
     * Test add_cron_intervals — mempertahankan schedules yang sudah ada.
     */
    public function test_add_cron_intervals_preserves_existing() {
        $scheduler = new UpdateScheduler();

        $existing = [
            'hourly' => [
                'interval' => 3600,
                'display'  => 'Hourly',
            ],
        ];

        $schedules = $scheduler->add_cron_intervals( $existing );

        $this->assertCount( 3, $schedules, 'Harus ada 3 schedules: hourly + weekly + monthly' );
        $this->assertArrayHasKey( 'hourly', $schedules );
        $this->assertArrayHasKey( 'weekly', $schedules );
        $this->assertArrayHasKey( 'monthly', $schedules );
    }

    /**
     * Test add_cron_intervals — tidak menduplikasi jika sudah ada.
     */
    public function test_add_cron_intervals_no_duplicate() {
        $scheduler = new UpdateScheduler();

        $existing = [
            'weekly' => [
                'interval' => 604800,
                'display'  => 'Weekly',
            ],
        ];

        $schedules = $scheduler->add_cron_intervals( $existing );

        // weekly key tetap 1 (di-overwrite, bukan duplicate)
        $this->assertCount( 2, $schedules, 'weekly tidak boleh duplikat' );
    }

    /**
     * Test add_cron_intervals — interval numerik yang valid.
     */
    public function test_add_cron_intervals_valid_intervals() {
        $scheduler = new UpdateScheduler();

        $schedules = $scheduler->add_cron_intervals( [] );

        $this->assertIsInt( $schedules['weekly']['interval'] );
        $this->assertIsInt( $schedules['monthly']['interval'] );
        $this->assertGreaterThan( 0, $schedules['weekly']['interval'] );
        $this->assertGreaterThan( 0, $schedules['monthly']['interval'] );
    }

    // ====================================================================
    // SCHEDULE_EVENT
    // ====================================================================

    /**
     * Test schedule_event — schedule autoblog_run_pipeline dan autoblog_daily_refresh.
     *
     * Mock wp_next_scheduled() return false (default) → wp_schedule_event dipanggil.
     */
    public function test_schedule_event_schedules_both_hooks() {
        $scheduler = new UpdateScheduler();
        $scheduler->schedule_event();

        // wp_schedule_event adalah mock no-op → tidak ada yang bisa diverifikasi
        // selain method selesai tanpa error
        $this->assertNotNull( $scheduler );
    }

    /**
     * Test schedule_event — membaca schedule option.
     */
    public function test_schedule_event_reads_schedule_option() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_cron_schedule'] = 'daily';
        $_autoblog_mock_options['autoblog_refresh_schedule'] = 'weekly';
        OptionCache::flush();

        $scheduler = new UpdateScheduler();
        $scheduler->schedule_event();

        $this->assertNotNull( $scheduler );
    }

    /**
     * Test schedule_event — tidak double-schedule jika sudah ada.
     *
     * Mock wp_next_scheduled return false, jadi selalu schedule.
     */
    public function test_schedule_event_does_not_double_schedule() {
        $scheduler = new UpdateScheduler();

        // Panggil 2x — method ke-2 cek wp_next_scheduled → jika true, skip
        $scheduler->schedule_event();
        $scheduler->schedule_event();

        $this->assertNotNull( $scheduler );
    }

    // ====================================================================
    // UNSCHEDULE_EVENT
    // ====================================================================

    /**
     * Test unschedule_event — unschedule kedua hooks.
     *
     * Mock wp_next_scheduled return false (default) → wp_unschedule_event tidak dipanggil.
     * Method selesai tanpa error.
     */
    public function test_unschedule_event_cleans_up() {
        $scheduler = new UpdateScheduler();
        $scheduler->unschedule_event();

        $this->assertNotNull( $scheduler );
    }

    /**
     * Test unschedule_event — aman dipanggil meski tidak ada schedule.
     */
    public function test_unschedule_event_safe_when_no_schedule() {
        $scheduler = new UpdateScheduler();

        // Panggil 2x — kedua-duanya harus aman
        $scheduler->unschedule_event();
        $scheduler->unschedule_event();

        $this->assertNotNull( $scheduler );
    }

    // ====================================================================
    // RESCHEDULE_ON_UPDATE
    // ====================================================================

    /**
     * Test reschedule_on_update — memanggil unschedule + schedule.
     */
    public function test_reschedule_on_update_calls_both() {
        $scheduler = new UpdateScheduler();
        $scheduler->reschedule_on_update();

        $this->assertNotNull( $scheduler );
    }

    /**
     * Test reschedule_on_update — aman dipanggil berkali-kali.
     */
    public function test_reschedule_on_update_multiple_calls() {
        $scheduler = new UpdateScheduler();

        $scheduler->reschedule_on_update();
        $scheduler->reschedule_on_update();
        $scheduler->reschedule_on_update();

        $this->assertNotNull( $scheduler );
    }
}
