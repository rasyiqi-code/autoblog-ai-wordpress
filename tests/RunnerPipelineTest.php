<?php
/**
 * Unit Test untuk Core\Runner — pipeline orchestration, source iteration, error handling.
 *
 * Tiga pendekatan pengujian:
 * 1. Pipeline Orchestration — partial mock Runner, hanya mock 4 phase methods,
 *    verifikasi urutan dan kondisi pemanggilan.
 * 2. Real Execution — Runner asli, OptionCache mock options, source invalid URL
 *    agar fetch_data() mengembalikan array kosong (tanpa API call).
 * 3. Error Handling — mock phase throws exception, verifikasi pipeline tetap jalan.
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Core\Runner;
use Autoblog\Utils\OptionCache;

class RunnerPipelineTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        // Default: disable dynamic search agar IdeationAgent tidak dipanggil
        $_autoblog_mock_options['autoblog_enable_dynamic_search'] = '0';
        // Default: disable deep research agar ResearchAgent tidak dipanggil
        $_autoblog_mock_options['autoblog_enable_deep_research']  = '0';
        // Default: data source mode = 'both' agar ingestion memproses sources
        $_autoblog_mock_options['autoblog_data_source_mode']      = 'both';
        // Default: embedding provider (dibutuhkan VectorStore)
        $_autoblog_mock_options['autoblog_embedding_provider']    = 'openai';
        // Default: search provider eksplisit agar tidak bergantung pada default constructor
        $_autoblog_mock_options['autoblog_search_provider']       = 'serpapi';
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // PIPELINE ORCHESTRATION (PARTIAL MOCK)
    // ====================================================================

    /**
     * Test pipeline normal: ingestion → ideation (returns idea) → production.
     *
     * Verifikasi bahwa ke-4 phase dipanggil dalam urutan yang benar ketika
     * ideation mengembalikan sebuah idea.
     */
    public function test_run_pipeline_calls_all_phases_in_order() {
        $idea = [ 'title' => 'Test Idea', 'angle' => 'Test Angle' ];

        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->expects( $this->once() )->method( 'run_ingestion_phase' )->with( [] );
        $runner->expects( $this->once() )->method( 'run_ideation_phase' )->willReturn( $idea );
        $runner->expects( $this->once() )->method( 'run_production_phase' )->with( $idea, [] );
        // Maintenance tidak dipanggil tanpa living_content override
        $runner->expects( $this->never() )->method( 'run_maintenance_phase' );

        $runner->run_pipeline();
    }

    /**
     * Test pipeline skip production saat ideation null.
     *
     * Jika ideation mengembalikan null, production harus di-skip.
     */
    public function test_run_pipeline_skips_production_when_ideation_null() {
        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->expects( $this->once() )->method( 'run_ingestion_phase' );
        $runner->expects( $this->once() )->method( 'run_ideation_phase' )->willReturn( null );
        $runner->expects( $this->never() )->method( 'run_production_phase' );
        $runner->expects( $this->never() )->method( 'run_maintenance_phase' );

        $runner->run_pipeline();
    }

    /**
     * Test pipeline dengan living_content override saat ideation null.
     *
     * Jika ideation null TAPI ada overrides['living_content'] = true,
     * maintenance phase HARUS tetap dipanggil setelah return.
     */
    public function test_run_pipeline_runs_maintenance_when_ideation_null_with_living_content_override() {
        $overrides = [ 'living_content' => true ];

        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->expects( $this->once() )->method( 'run_ingestion_phase' )->with( $overrides );
        $runner->expects( $this->once() )->method( 'run_ideation_phase' )->willReturn( null );
        $runner->expects( $this->never() )->method( 'run_production_phase' );
        // Maintenance HARUS dipanggil karena living_content = true
        $runner->expects( $this->once() )->method( 'run_maintenance_phase' );

        $runner->run_pipeline( $overrides );
    }

    /**
     * Test pipeline dengan living_content override saat ideation sukses.
     *
     * Jika ideation mengembalikan idea DAN living_content = true,
     * maintenance harus dipanggil SETELAH production.
     */
    public function test_run_pipeline_runs_maintenance_after_production_with_living_content() {
        $idea      = [ 'title' => 'Test', 'angle' => 'Angle' ];
        $overrides = [ 'living_content' => true ];

        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->expects( $this->once() )->method( 'run_ingestion_phase' );
        $runner->expects( $this->once() )->method( 'run_ideation_phase' )->willReturn( $idea );
        $runner->expects( $this->once() )->method( 'run_production_phase' );
        $runner->expects( $this->once() )->method( 'run_maintenance_phase' );

        $runner->run_pipeline( $overrides );
    }

    // ====================================================================
    // PIPELINE ERROR HANDLING (PARTIAL MOCK)
    // ====================================================================

    /**
     * Test pipeline error handling — exception di ingestion phase.
     *
     * run_ingestion_phase throws → run_pipeline catch → log → method selesai
     * tanpa exception propagasi.
     */
    public function test_run_pipeline_catches_exception_in_ingestion() {
        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->method( 'run_ingestion_phase' )
            ->willThrowException( new \RuntimeException( 'Ingestion failed' ) );

        // Ideation dan production TIDAK boleh dipanggil jika ingestion gagal
        $runner->expects( $this->never() )->method( 'run_ideation_phase' );
        $runner->expects( $this->never() )->method( 'run_production_phase' );

        // Method harus selesai tanpa exception (catch di dalam run_pipeline)
        $runner->run_pipeline();
        $this->assertTrue( true, 'Pipeline harus menangkap exception ingestion tanpa propagasi' );
    }

    /**
     * Test pipeline error handling — exception di ideation phase.
     */
    public function test_run_pipeline_catches_exception_in_ideation() {
        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->expects( $this->once() )->method( 'run_ingestion_phase' );
        $runner->method( 'run_ideation_phase' )
            ->willThrowException( new \RuntimeException( 'Ideation failed' ) );

        // Production tidak boleh dipanggil jika ideation gagal
        $runner->expects( $this->never() )->method( 'run_production_phase' );

        $runner->run_pipeline();
        $this->assertTrue( true, 'Pipeline harus menangkap exception ideation tanpa propagasi' );
    }

    /**
     * Test pipeline error handling — exception di production phase.
     */
    public function test_run_pipeline_catches_exception_in_production() {
        $idea  = [ 'title' => 'Test', 'angle' => 'Angle' ];

        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->method( 'run_ingestion_phase' );
        $runner->method( 'run_ideation_phase' )->willReturn( $idea );
        $runner->method( 'run_production_phase' )
            ->willThrowException( new \RuntimeException( 'Production failed' ) );

        $runner->run_pipeline();
        $this->assertTrue( true, 'Pipeline harus menangkap exception production tanpa propagasi' );
    }

    // ====================================================================
    // REAL EXECUTION — INGESTION PHASE
    // ====================================================================

    /**
     * Test run_ingestion_phase dengan array sources kosong.
     *
     * Tanpa sources, stage_ingestion() langsung selesai.
     * VectorStore dibuat (AIClient + Guzzle, tanpa API call).
     */
    public function test_run_ingestion_phase_completes_with_no_sources() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [];

        $runner = new Runner();

        // Harus selesai tanpa exception
        $runner->run_ingestion_phase();

        $this->assertTrue( true, 'Ingestion phase harus selesai tanpa error dengan sources kosong' );
    }

    /**
     * Test run_ingestion_phase dengan RSS source invalid URL.
     *
     * RSSSource dengan URL invalid → validate_source() false → fetch_data() []
     * Tidak ada add_document() → ingestion selesai dengan count 0.
     */
    public function test_run_ingestion_phase_with_invalid_rss_source() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [
                'type' => 'rss',
                'url'  => 'not-a-valid-url',
            ],
        ];

        $runner = new Runner();
        $runner->run_ingestion_phase();

        // Ingestion data harus tercatat dengan 1 source diproses (walaupun 0 item)
        $ingestion_data = OptionCache::get( 'autoblog_last_ingestion_data', [] );
        $this->assertNotEmpty( $ingestion_data, 'Ingestion data harus tercatat' );
        $this->assertEquals( 'completed', $ingestion_data['status'] );
        $this->assertSame( 1, $ingestion_data['count'] ?? 0, 'Satu source harus diproses (walaupun 0 item)' );
    }

    /**
     * Test run_ingestion_phase dengan web_search source + dynamic search disabled.
     *
     * Web_search dengan dynamic search disabled → langsung pakai query → SearchSource
     * dibuat. SearchSource dengan provider default (serpapi) dan tanpa serpapi key
     * → validate_source() false karena serpapi_key kosong.
     */
    public function test_run_ingestion_phase_with_web_search() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [
                'type' => 'web_search',
                'url'  => 'AI technology',
            ],
        ];

        $runner = new Runner();
        $runner->run_ingestion_phase();

        // Harus selesai tanpa exception (SearchSource gagal validasi karena
        // serpapi_key kosong, tapi stage_ingestion() catch exception-nya)
        $this->assertTrue( true, 'Ingestion dengan web_search tanpa API key harus aman' );
    }

    /**
     * Test run_ingestion_phase dengan file source — file harus di-skip.
     *
     * stage_ingestion() memiliki logika: if ($config['type'] === 'file') continue;
     */
    public function test_run_ingestion_phase_skips_file_source() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [
                'type' => 'file',
                'url'  => '/path/to/file.pdf',
            ],
        ];

        $runner = new Runner();
        $runner->run_ingestion_phase();

        // File source di-skip, ingestion tetap selesai
        $this->assertTrue( true, 'File source harus di-skip tanpa error' );
    }

    /**
     * Test run_ingestion_phase dengan web source (WebScraperSource) invalid URL.
     *
     * WebScraperSource dengan URL invalid → validate_source() false → fetch_data() []
     */
    public function test_run_ingestion_phase_with_invalid_web_source() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [
                'type'     => 'web',
                'url'      => '',
                'selector' => '#main',
            ],
        ];

        $runner = new Runner();
        $runner->run_ingestion_phase();

        $this->assertTrue( true, 'Web source dengan URL kosong harus aman' );
    }

    /**
     * Test run_ingestion_phase dengan multiple sources, beberapa invalid.
     *
     * Mencampur RSS (invalid) + web_search (invalid/no key).
     * Keduanya gagal validasi → ingestion selesai tanpa add_document.
     */
    public function test_run_ingestion_phase_with_mixed_sources() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [
                'type' => 'rss',
                'url'  => 'invalid-rss-url',
            ],
            [
                'type' => 'web_search',
                'url'  => 'AI technology',
            ],
        ];

        $runner = new Runner();
        $runner->run_ingestion_phase();

        $ingestion_data = OptionCache::get( 'autoblog_last_ingestion_data', [] );
        $this->assertNotEmpty( $ingestion_data );
        $this->assertEquals( 'completed', $ingestion_data['status'] );
    }

    // ====================================================================
    // REAL EXECUTION — IDEATION PHASE
    // ====================================================================

    /**
     * Test run_ideation_phase — tanpa API key, IdeationAgent gracefully
     * mengembalikan null (tidak throw).
     *
     * IdeationAgent::brainstorm_topics() menangani kegagalan API secara
     * graceful dengan return empty array → stage_ideation return null.
     */
    public function test_run_ideation_phase_returns_null_without_ai() {
        $runner = new Runner();

        $result = $runner->run_ideation_phase();

        // Tanpa API key, brainstorm_topics gagal graceful → return null
        $this->assertNull( $result, 'Ideation harus return null tanpa AI API key' );
    }

    // ====================================================================
    // REAL EXECUTION — PRODUCTION PHASE
    // ====================================================================

    /**
     * Test run_production_phase dengan null idea (tanpa ideation data).
     *
     * run_production_phase(null) akan cek OptionCache 'autoblog_last_ideation_data'.
     * Karena tidak ada, method log warning dan return.
     */
    public function test_run_production_phase_returns_early_without_idea() {
        $runner = new Runner();

        // Panggil dengan null idea dan tanpa ideation data di OptionCache
        $runner->run_production_phase( null );

        // Tidak ada exception — method return early
        $this->assertTrue( true, 'Production phase harus return early tanpa exception' );
    }

    /**
     * Test run_production_phase dengan idea array langsung.
     *
     * Memberikan idea langsung (tidak perlu OptionCache).
     * Production akan mencoba menulis artikel → error ditangani secara
     * graceful tanpa exception propagasi (exception internal di-catch).
     */
    public function test_run_production_phase_handles_errors_gracefully() {
        $idea = [ 'title' => 'Test Topik', 'angle' => 'Test Sudut' ];

        $runner = new Runner();

        // Production phase harus selesai tanpa exception — error internal
        // ditangkap oleh try-catch method
        $runner->run_production_phase( $idea );
        $this->assertTrue( true, 'Production phase harus handle error tanpa exception' );
    }

    // ====================================================================
    // REAL EXECUTION — MAINTENANCE PHASE
    // ====================================================================

    /**
     * Test run_maintenance_phase — memuat ContentRefresher dan menjalankannya.
     * Akan gagal karena ContentRefresher membutuhkan WordPress.
     * Yang penting: tidak ada fatal error, exception ditangkap dengan baik.
     */
    public function test_run_maintenance_phase_completes_without_error() {
        $runner = new Runner();
        $runner->run_maintenance_phase();

        // ContentRefresher akan gagal (tidak ada WordPress context)
        // Tapi exception-nya di-catch oleh run_maintenance_phase()
        $this->assertTrue( true, 'Maintenance phase harus handle error tanpa crash' );
    }

    // ====================================================================
    // OVERRIDES — OVERRIDES PASSTHROUGH
    // ====================================================================

    /**
     * Test bahwa overrides diteruskan ke phase methods.
     *
     * Verifikasi bahwa run_pipeline() menyampaikan $overrides ke
     * run_ingestion_phase() dan run_production_phase().
     */
    public function test_run_pipeline_passes_overrides_to_phases() {
        $overrides = [
            'dynamic_search' => true,
            'deep_research'  => false,
            'living_content' => false,
        ];
        $idea = [ 'title' => 'Override Test', 'angle' => 'Testing' ];

        $runner = $this->getMockBuilder( Runner::class )
            ->onlyMethods( [ 'run_ingestion_phase', 'run_ideation_phase', 'run_production_phase', 'run_maintenance_phase' ] )
            ->getMock();

        $runner->expects( $this->once() )
            ->method( 'run_ingestion_phase' )
            ->with( $overrides );

        $runner->expects( $this->once() )
            ->method( 'run_ideation_phase' )
            ->willReturn( $idea );

        $runner->expects( $this->once() )
            ->method( 'run_production_phase' )
            ->with( $idea, $overrides );

        $runner->run_pipeline( $overrides );
    }
}
