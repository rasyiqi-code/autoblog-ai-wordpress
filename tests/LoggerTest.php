<?php
/**
 * Unit Test untuk Logger — static log() method.
 *
 * Logger::log() menulis log ke file dengan format:
 * [timestamp] [level] message
 *
 * Strategi test:
 * - wp_upload_dir() mock return sys_get_temp_dir()
 * - Logger menulis ke {temp}/autoblog-logs/debug.log
 * - Test membaca file log setelah write dan verifikasi isi
 * - Cleanup file di tearDown
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Utils\Logger;

class LoggerTest extends TestCase {

    /** @var string Path ke log file */
    private $log_file;

    protected function setUp(): void {
        parent::setUp();

        // wp_upload_dir() mock return sys_get_temp_dir()
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/autoblog-logs/debug.log';

        // Pastikan directory log ada (rotation tests butuh directory sebelum write fixture)
        $log_dir = dirname( $this->log_file );
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }

        // Bersihkan file log sebelum test
        if ( file_exists( $this->log_file ) ) {
            unlink( $this->log_file );
        }
        // Bersihkan juga backup jika ada
        $backup_file = $upload_dir['basedir'] . '/autoblog-logs/debug.log.old';
        if ( file_exists( $backup_file ) ) {
            unlink( $backup_file );
        }
    }

    protected function tearDown(): void {
        // Bersihkan file log setelah test
        if ( file_exists( $this->log_file ) ) {
            unlink( $this->log_file );
        }
        // Bersihkan directory jika kosong
        $log_dir = dirname( $this->log_file );
        if ( is_dir( $log_dir ) ) {
            $files = glob( $log_dir . '/*' );
            if ( empty( $files ) ) {
                rmdir( $log_dir );
            }
        }

        parent::tearDown();
    }

    // ====================================================================
    // BASIC LOGGING
    // ====================================================================

    /**
     * Test bahwa log() membuat file log baru jika belum ada.
     */
    public function test_log_creates_log_file() {
        $this->assertFileDoesNotExist( $this->log_file,
            'Log file harus belum ada sebelum test' );

        Logger::log( 'Test log message' );

        $this->assertFileExists( $this->log_file,
            'Log file harus dibuat setelah log() dipanggil' );
    }

    /**
     * Test bahwa log() menulis message ke file.
     */
    public function test_log_writes_message() {
        Logger::log( 'Test log message' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( 'Test log message', $content );
    }

    /**
     * Test bahwa log() menambahkan level ke format.
     */
    public function test_log_writes_level() {
        Logger::log( 'Info message', 'info' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[info]', $content );
    }

    /**
     * Test bahwa log() menambahkan timestamp ke format.
     */
    public function test_log_writes_timestamp() {
        Logger::log( 'Timestamp test' );

        $content = file_get_contents( $this->log_file );
        // Format: [2026-07-14 12:00:00]
        $this->assertMatchesRegularExpression( '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content,
            'Log harus mengandung timestamp format Y-m-d H:i:s'
        );
    }

    /**
     * Test bahwa log() menambahkan newline di akhir.
     */
    public function test_log_writes_newline() {
        Logger::log( 'Newline test' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringEndsWith( PHP_EOL, $content,
            'Log harus diakhiri newline'
        );
    }

    /**
     * Test format lengkap: [timestamp] [level] message
     */
    public function test_log_full_format() {
        Logger::log( 'Format test', 'warning' );

        $content = file_get_contents( $this->log_file );
        $this->assertMatchesRegularExpression(
            '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[warning\] Format test/',
            $content,
            'Format harus [timestamp] [level] message'
        );
    }

    /**
     * Test bahwa log() append ke file yang sudah ada (tidak overwrite).
     */
    public function test_log_appends_to_existing_file() {
        Logger::log( 'First message' );
        Logger::log( 'Second message' );

        $content = file_get_contents( $this->log_file );
        $lines   = explode( PHP_EOL, trim( $content ) );

        $this->assertCount( 2, $lines, 'Harus ada 2 baris log' );
        $this->assertStringContainsString( 'First message', $lines[0] );
        $this->assertStringContainsString( 'Second message', $lines[1] );
    }

    // ====================================================================
    // LOG LEVELS
    // ====================================================================

    /**
     * Test default level adalah 'info'.
     */
    public function test_log_default_level_is_info() {
        Logger::log( 'Default level test' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[info]', $content,
            'Default log level harus info'
        );
    }

    /**
     * Test level 'info'.
     */
    public function test_log_level_info() {
        Logger::log( 'Info level message', 'info' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[info]', $content );
    }

    /**
     * Test level 'error'.
     */
    public function test_log_level_error() {
        Logger::log( 'Error level message', 'error' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[error]', $content );
    }

    /**
     * Test level 'warning'.
     */
    public function test_log_level_warning() {
        Logger::log( 'Warning level message', 'warning' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[warning]', $content );
    }

    /**
     * Test level 'debug'.
     */
    public function test_log_level_debug() {
        Logger::log( 'Debug level message', 'debug' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[debug]', $content );
    }

    /**
     * Test custom level unknown.
     */
    public function test_log_level_custom() {
        Logger::log( 'Custom level', 'critical' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[critical]', $content,
            'Level apapun harus diterima'
        );
    }

    // ====================================================================
    // EDGE CASES
    // ====================================================================

    /**
     * Test empty message — tetap nulis walau message kosong.
     *
     * Format: [timestamp] [info] [empty]
     */
    public function test_log_empty_message() {
        Logger::log( '' );

        $content = file_get_contents( $this->log_file );
        $this->assertMatchesRegularExpression(
            '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[info\] $/',
            $content,
            'Message kosong: format tetap ditulis, message setelah level kosong'
        );
    }

    /**
     * Test message dengan special characters.
     */
    public function test_log_special_chars() {
        $message = 'Price: $100, Rate: 50%, Path: /var/log, Quote: "test"';
        Logger::log( $message );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( $message, $content );
    }

    /**
     * Test message dengan newline di dalamnya.
     */
    public function test_log_message_with_newlines() {
        Logger::log( "Line 1\nLine 2\nLine 3" );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( 'Line 1', $content );
    }

    /**
     * Test multiple sequential calls dalam loop.
     */
    public function test_log_multiple_calls() {
        for ( $i = 1; $i <= 10; $i++ ) {
            Logger::log( "Message {$i}", 'info' );
        }

        $content = file_get_contents( $this->log_file );
        $lines   = explode( PHP_EOL, trim( $content ) );

        $this->assertCount( 10, $lines, 'Harus ada 10 baris log' );
        $this->assertStringContainsString( 'Message 5', $lines[4] );
        $this->assertStringContainsString( 'Message 10', $lines[9] );
    }

    /**
     * Test log dengan level uppercase.
     */
    public function test_log_level_uppercase() {
        Logger::log( 'Uppercase level', 'ERROR' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( '[ERROR]', $content,
            'Level uppercase harus dipertahankan'
        );
    }

    /**
     * Test bahwa directory log dibuat otomatis.
     */
    public function test_log_creates_directory() {
        $log_dir = dirname( $this->log_file );

        // Hapus directory dulu
        if ( is_dir( $log_dir ) ) {
            array_map( 'unlink', glob( $log_dir . '/*' ) );
            rmdir( $log_dir );
        }
        $this->assertDirectoryDoesNotExist( $log_dir );

        Logger::log( 'Directory creation test' );

        $this->assertDirectoryExists( $log_dir,
            'Directory autoblog-logs harus dibuat otomatis'
        );
    }

    // ====================================================================
    // LOG ROTATION
    // ====================================================================

    /**
     * Test log rotation — file >5MB di-rename ke .old.
     *
     * Catatan: Test ini membuat file 6MB di disk (bukan di memory semua).
     * file_put_contents + str_repeat membutuhkan ~6MB memory.
     *
     * @group slow
     */
    public function test_log_rotation_renames_large_file() {
        // Buat file log >5MB manual
        $large_data = str_repeat( 'A', 6 * 1024 * 1024 ); // 6MB
        file_put_contents( $this->log_file, $large_data );
        $this->assertFileExists( $this->log_file );

        $log_dir    = dirname( $this->log_file );
        $backup_file = $log_dir . '/debug.log.old';

        Logger::log( 'After rotation test' );

        // File asli harus ada (dengan pesan baru)
        $this->assertFileExists( $this->log_file,
            'File log asli harus ada setelah rotasi'
        );
        // File backup harus ada
        $this->assertFileExists( $backup_file,
            'File backup .old harus dibuat'
        );
        // File backup harus berisi data lama (6MB)
        $this->assertEquals( 6 * 1024 * 1024, filesize( $backup_file ),
            'Backup harus berisi 6MB data lama'
        );
        // File baru harus lebih kecil (hanya pesan baru)
        $this->assertLessThan( 1024, filesize( $this->log_file ),
            'File baru harus berisi hanya pesan terbaru (< 1KB)'
        );

        // Cleanup backup
        if ( file_exists( $backup_file ) ) {
            unlink( $backup_file );
        }
    }

    /**
     * Test log rotation — backup lama dihapus saat rotasi lagi.
     *
     * @group slow
     */
    public function test_log_rotation_replaces_old_backup() {
        $log_dir    = dirname( $this->log_file );
        $backup_file = $log_dir . '/debug.log.old';

        // Buat backup file lama
        file_put_contents( $backup_file, 'old backup data' );
        $this->assertFileExists( $backup_file );

        // Buat file log >5MB
        $large_data = str_repeat( 'B', 6 * 1024 * 1024 );
        file_put_contents( $this->log_file, $large_data );

        Logger::log( 'Rotation with existing backup' );

        // Backup lama harus diganti dengan yang baru
        $this->assertFileExists( $backup_file );
        $this->assertEquals( 6 * 1024 * 1024, filesize( $backup_file ),
            'Backup harus berisi data baru (bukan \"old backup data\")'
        );
    }

    /**
     * Test log tidak dirotasi jika ukuran <5MB.
     */
    public function test_log_no_rotation_for_small_file() {
        file_put_contents( $this->log_file, 'Small log content' );

        $log_dir    = dirname( $this->log_file );
        $backup_file = $log_dir . '/debug.log.old';

        Logger::log( 'Small file test' );

        $this->assertFileDoesNotExist( $backup_file,
            'Backup tidak boleh dibuat untuk file <5MB'
        );
        // File asli harus append pesan baru
        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( 'Small file test', $content );
    }

    // ====================================================================
    // WRITE PERMISSION / ERROR HANDLING
    // ====================================================================

    /**
     * Test bahwa Logger tidak crash saat directory tidak bisa dibuat.
     *
     * wp_mkdir_p() mock return true (bootstrap) → selalu sukses.
     * Test ini verifikasi tidak ada exception yang di-throw.
     */
    public function test_log_handles_write_gracefully() {
        Logger::log( 'Write test' );

        $this->assertFileExists( $this->log_file );
        $this->assertNotNull( Logger::class );
    }

    /**
     * Test log dengan message sangat panjang.
     */
    public function test_log_long_message() {
        $long_message = str_repeat( 'Very long log message. ', 100 );
        Logger::log( $long_message );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( 'Very long log message', $content );
    }

    /**
     * Test log dengan unicode characters.
     */
    public function test_log_unicode_message() {
        Logger::log( 'Log dengan karakter Unicode: áéíóú ñ 中文 日本語' );

        $content = file_get_contents( $this->log_file );
        $this->assertStringContainsString( 'áéíóú', $content );
        $this->assertStringContainsString( '中文', $content );
    }
}
