<?php
/**
 * Unit Test untuk Autoblog\\Intelligence\\IdeationAgent — Brainstorming Agent.
 *
 * IdeationAgent bertindak sebagai "Editor-in-Chief":
 * - brainstorm_topics($seed, $kb_summary, $count): generate ide topik via AI JSON,
 *   deduplikasi terhadap existing post, update used_topics log.
 * - propose_research_query($seed, $kb_summary): generate search query dinamis.
 *
 * Dependencies:
 * - AIClient::generate_text() — AI call untuk semua output
 * - PostManager::post_exists_by_title() — deduplikasi
 * - OptionCache — used_topics tracking
 *
 * Strategi test:
 * - Inject mock AIClient dan mock PostManager via reflection
 * - Test brainstorm_topics dengan berbagai skenario AI response
 * - Test propose_research_query secara langsung
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      intelligence
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\IdeationAgent;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;
use Autoblog\Publisher\PostManager;

class IdeationAgentTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        // Default provider
        $_autoblog_mock_options['autoblog_ai_provider'] = 'openai';
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // HELPER: Create IdeationAgent with mock dependencies
    // ====================================================================

    /**
     * Buat IdeationAgent dengan mock AIClient dan PostManager
     * di-inject via reflection.
     *
     * @param AIClient|null    $mockAI    Mock AIClient (default: generate_text return null)
     * @param PostManager|null $mockPM    Mock PostManager (default: post_exists_by_title return false)
     * @return IdeationAgent
     */
    private function createAgentWithMocks( ?AIClient $mockAI = null, ?PostManager $mockPM = null ): IdeationAgent {
        $agent = $this->getMockBuilder( IdeationAgent::class )
            ->disableOriginalConstructor()
            ->onlyMethods( [] )
            ->getMock();

        if ( $mockAI === null ) {
            $mockAI = $this->createMock( AIClient::class );
            $mockAI->method( 'generate_text' )->willReturn( null );
        }

        if ( $mockPM === null ) {
            $mockPM = $this->createMock( PostManager::class );
            $mockPM->method( 'post_exists_by_title' )->willReturn( false );
        }

        $reflection = new \ReflectionClass( IdeationAgent::class );

        $propAI = $reflection->getProperty( 'ai_client' );
        $propAI->setAccessible( true );
        $propAI->setValue( $agent, $mockAI );

        $propPM = $reflection->getProperty( 'post_manager' );
        $propPM->setAccessible( true );
        $propPM->setValue( $agent, $mockPM );

        return $agent;
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    /**
     * Test bahwa constructor menginisialisasi AIClient dan PostManager.
     */
    public function test_constructor_initializes_dependencies() {
        // Buat instance real (bukan mock) — constructor akan jalan
        $agent = new IdeationAgent();

        $reflection = new \ReflectionClass( IdeationAgent::class );

        $propAI = $reflection->getProperty( 'ai_client' );
        $propAI->setAccessible( true );
        $this->assertInstanceOf( AIClient::class, $propAI->getValue( $agent ),
            'IdeationAgent harus memiliki AIClient terinisialisasi'
        );

        $propPM = $reflection->getProperty( 'post_manager' );
        $propPM->setAccessible( true );
        $this->assertInstanceOf( PostManager::class, $propPM->getValue( $agent ),
            'IdeationAgent harus memiliki PostManager terinisialisasi'
        );
    }

    // ====================================================================
    // BRAINSTORM_TOPICS — AI FAILURE / EDGE CASES
    // ====================================================================

    /**
     * Test bahwa brainstorm_topics() return array kosong jika AI gagal
     * (generate_text return false).
     */
    public function test_brainstorm_returns_empty_when_ai_fails() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( false );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'AI Technology' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'AI gagal (false) → harus return array kosong'
        );
    }

    /**
     * Test bahwa brainstorm_topics() return array kosong jika AI
     * mengembalikan response kosong.
     */
    public function test_brainstorm_returns_empty_on_empty_response() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( '' );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'AI' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'Response kosong → harus return array kosong'
        );
    }

    /**
     * Test bahwa brainstorm_topics() return array kosong jika AI
     * mengembalikan JSON tidak valid.
     */
    public function test_brainstorm_returns_empty_on_invalid_json() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( 'Ini bukan JSON' );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'AI' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'JSON tidak valid → harus return array kosong'
        );
    }

    /**
     * Test bahwa brainstorm_topics() return array kosong jika AI
     * mengembalikan JSON yang bukan array (misal string).
     */
    public function test_brainstorm_returns_empty_when_json_is_not_array() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( '{"title": "Test", "angle": "Angle"}' );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'AI' );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'JSON object bukan array → harus return array kosong'
        );
    }

    // ====================================================================
    // BRAINSTORM_TOPICS — JSON PARSING
    // ====================================================================

    /**
     * Test bahwa brainstorm_topics() memparsing JSON valid dari AI
     * dan mengembalikan array ide.
     */
    public function test_brainstorm_parses_valid_json() {
        $validJson = json_encode( [
            [ 'title' => 'Masa Depan AI', 'angle' => 'Dampak AI pada lapangan kerja' ],
            [ 'title' => 'Blockchain untuk Logistik', 'angle' => 'Transparansi rantai pasok' ],
        ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( $validJson );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'Technology' );

        $this->assertCount( 2, $result );
        $this->assertSame( 'Masa Depan AI', $result[0]['title'] );
        $this->assertSame( 'Dampak AI pada lapangan kerja', $result[0]['angle'] );
        $this->assertSame( 'Blockchain untuk Logistik', $result[1]['title'] );
        $this->assertSame( 'Transparansi rantai pasok', $result[1]['angle'] );
    }

    /**
     * Test bahwa brainstorm_topics() membersihkan markdown code block
     * wrapper ```json ... ``` sebelum parsing JSON.
     */
    public function test_brainstorm_strips_json_markdown_block() {
        $response = "```json\n[\n  {\"title\": \"Topik 1\", \"angle\": \"Angle 1\"},\n  {\"title\": \"Topik 2\", \"angle\": \"Angle 2\"}\n]\n```";

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( $response );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'Test' );

        $this->assertCount( 2, $result );
        $this->assertSame( 'Topik 1', $result[0]['title'] );
    }

    /**
     * Test bahwa brainstorm_topics() menggunakan temperature tinggi (0.85)
     * saat memanggil generate_text untuk kreativitas.
     */
    public function test_brainstorm_uses_high_temperature() {
        $validJson = json_encode( [ [ 'title' => 'Creative Topic', 'angle' => 'Fresh angle' ] ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->equalTo( 0.85 ), // temperature tinggi untuk brainstorming
                $this->anything()
            )
            ->willReturn( $validJson );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'Creative' );

        $this->assertCount( 1, $result );
    }

    // ====================================================================
    // BRAINSTORM_TOPICS — DEDUPLIKASI
    // ====================================================================

    /**
     * Test bahwa brainstorm_topics() mendeteksi post yang sudah ada
     * via PostManager::post_exists_by_title() dan mengecualikannya.
     */
    public function test_brainstorm_excludes_existing_posts() {
        $validJson = json_encode( [
            [ 'title' => 'Judul Baru', 'angle' => 'Angle baru' ],
            [ 'title' => 'Judul Lama', 'angle' => 'Sudah pernah ditulis' ],
        ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( $validJson );

        $mockPM = $this->createMock( PostManager::class );
        $mockPM->method( 'post_exists_by_title' )
            ->willReturnMap( [
                [ 'Judul Baru', false ],
                [ 'Judul Lama', true ], // Post sudah ada → eksklusi
            ] );

        $agent = $this->createAgentWithMocks( $mockAI, $mockPM );
        $result = $agent->brainstorm_topics( 'Technology' );

        $this->assertCount( 1, $result,
            'Hanya 1 ide yang unik (Judul Lama sudah dipublikasi)'
        );
        $this->assertSame( 'Judul Baru', $result[0]['title'] );
    }

    /**
     * Test bahwa brainstorm_topics() mencegah duplikasi judul
     * dalam satu run. Bug #13 Fix: tambah internal deduplication
     * agar judul yang sama dalam satu batch hanya muncul sekali.
     */
    public function test_brainstorm_deduplicates_same_title_in_one_run() {
        $validJson = json_encode( [
            [ 'title' => 'Judul Sama', 'angle' => 'Angle A' ],
            [ 'title' => 'Judul Sama', 'angle' => 'Angle B' ],
        ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( $validJson );

        $mockPM = $this->createMock( PostManager::class );
        $mockPM->method( 'post_exists_by_title' )
            ->with( 'Judul Sama' )
            ->willReturn( false );

        $agent = $this->createAgentWithMocks( $mockAI, $mockPM );
        $result = $agent->brainstorm_topics( 'Tech' );

        $this->assertCount( 1, $result,
            'Dua ide dengan judul sama harus di-dedup jadi 1'
        );
    }

    // ====================================================================
    // BRAINSTORM_TOPICS — USED TOPICS TRACKING
    // ====================================================================

    /**
     * Test bahwa brainstorm_topics() menyimpan ide baru ke
     * OptionCache 'autoblog_used_topics'.
     */
    public function test_brainstorm_appends_to_used_topics() {
        $validJson = json_encode( [
            [ 'title' => 'Topik Baru', 'angle' => 'Angle' ],
        ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( $validJson );

        $agent = $this->createAgentWithMocks( $mockAI );
        $agent->brainstorm_topics( 'Test' );

        $used_topics = OptionCache::get( 'autoblog_used_topics', [] );
        $this->assertContains( 'Topik Baru', $used_topics,
            'Topik baru harus ditambahkan ke used_topics'
        );
    }

    /**
     * Test bahwa brainstorm_topics() membatasi used_topics ke max 50 entri.
     */
    public function test_brainstorm_limits_used_topics_to_fifty() {
        // Setup: used_topics sudah penuh (50 entri)
        $existing = [];
        for ( $i = 1; $i <= 50; $i++ ) {
            $existing[] = "Old Topic {$i}";
        }
        OptionCache::set( 'autoblog_used_topics', $existing );

        $validJson = json_encode( [
            [ 'title' => 'Topik Baru 1', 'angle' => 'Angle 1' ],
            [ 'title' => 'Topik Baru 2', 'angle' => 'Angle 2' ],
        ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( $validJson );

        $agent = $this->createAgentWithMocks( $mockAI );
        $agent->brainstorm_topics( 'Test' );

        $used_topics = OptionCache::get( 'autoblog_used_topics', [] );
        $this->assertCount( 50, $used_topics,
            'used_topics harus tetap maksimal 50 entri'
        );

        // Topik lama yang paling awal harus terbuang (array_slice -50)
        $this->assertNotContains( 'Old Topic 1', $used_topics,
            'Topik paling lama harus terbuang karena limit 50'
        );
        $this->assertContains( 'Topik Baru 1', $used_topics,
            'Topik baru harus ada di used_topics'
        );
        $this->assertContains( 'Topik Baru 2', $used_topics );
    }

    /**
     * Test bahwa used_topics yang bukan array (misal string) tidak
     * menyebabkan fatal error.
     */
    public function test_brainstorm_handles_non_array_used_topics() {
        // Set used_topics ke string (korup)
        OptionCache::set( 'autoblog_used_topics', 'bukan_array' );

        $validJson = json_encode( [
            [ 'title' => 'Topik Darurat', 'angle' => 'Angle' ],
        ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( $validJson );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'Test' );

        $this->assertCount( 1, $result,
            'used_topics korup (string) → harus tetap menghasilkan ide'
        );
        $this->assertSame( 'Topik Darurat', $result[0]['title'] );
    }

    // ====================================================================
    // BRAINSTORM_TOPICS — KB SUMMARY
    // ====================================================================

    /**
     * Test bahwa brainstorm_topics() menyertakan kb_summary ke prompt
     * jika diberikan.
     */
    public function test_brainstorm_includes_kb_summary_in_prompt() {
        $validJson = json_encode( [ [ 'title' => 'Topic', 'angle' => 'Angle' ] ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->stringContains( 'RINGKASAN DATA' ),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn( $validJson );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'Test', 'Data: AI adoption 2024' );

        $this->assertCount( 1, $result );
    }

    /**
     * Test bahwa brainstorm_topics() tidak menyertakan kb_summary
     * jika kosong.
     */
    public function test_brainstorm_works_without_kb_summary() {
        $validJson = json_encode( [ [ 'title' => 'Topic', 'angle' => 'Angle' ] ] );

        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->logicalNot( $this->stringContains( 'RINGKASAN DATA' ) ),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn( $validJson );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->brainstorm_topics( 'Test', '' );

        $this->assertCount( 1, $result );
    }

    // ====================================================================
    // PROPOSE_RESEARCH_QUERY
    // ====================================================================

    /**
     * Test bahwa propose_research_query() memanggil AI dan mengembalikan
     * query yang sudah di-trim.
     */
    public function test_propose_research_query_returns_trimmed_query() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( '  "AI adoption statistics 2024"  ' );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->propose_research_query( 'AI' );

        // Trim: trim($query, " \"'") → hapus spasi dan quote
        $this->assertSame( 'AI adoption statistics 2024', $result );
    }

    /**
     * Test bahwa propose_research_query() menyertakan kb_summary
     * ke prompt jika diberikan.
     */
    public function test_propose_research_query_includes_kb_summary() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->stringContains( 'Data kita saat ini' ),
                $this->anything(),
                $this->anything()
            )
            ->willReturn( 'specific query' );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->propose_research_query( 'AI', 'Current data: AI basics' );

        $this->assertSame( 'specific query', $result );
    }

    /**
     * Test bahwa propose_research_query() tidak menyertakan kb_summary
     * jika kosong.
     */
    public function test_propose_research_query_works_without_kb_summary() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->logicalNot( $this->stringContains( 'Data kita saat ini' ) ),
                $this->anything(),
                $this->anything()
            )
            ->willReturn( 'query' );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->propose_research_query( 'AI' );

        $this->assertSame( 'query', $result );
    }

    // ====================================================================
    // PROPOSE_RESEARCH_QUERY — EDGE CASES
    // ====================================================================

    /**
     * Test bahwa propose_research_query() mengembalikan string kosong
     * jika AI gagal.
     */
    public function test_propose_research_query_handles_ai_failure() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( false );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->propose_research_query( 'AI' );

        // trim(false) → '' dalam konteks string
        $this->assertSame( '', $result,
            'AI gagal → harus return string kosong'
        );
    }

    /**
     * Test bahwa propose_research_query() menghapus quote dari query.
     */
    public function test_propose_research_query_strips_quotes() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( '"How AI changes education"' );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->propose_research_query( 'AI Education' );

        $this->assertSame( 'How AI changes education', $result );
    }

    /**
     * Test bahwa propose_research_query() menghapus single quote.
     */
    public function test_propose_research_query_strips_single_quotes() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( "'AI trends 2024'" );

        $agent = $this->createAgentWithMocks( $mockAI );
        $result = $agent->propose_research_query( 'AI' );

        $this->assertSame( 'AI trends 2024', $result );
    }
}
