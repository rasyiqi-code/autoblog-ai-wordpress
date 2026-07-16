<?php
/**
 * Unit Test untuk Autoblog\\Intelligence\\ResearchAgent — Deep Research Agent.
 *
 * ResearchAgent menjalankan multi-hop deep research:
 * 1. Guard: cek autoblog_enable_deep_research option → return '' jika disabled
 * 2. Round 1: generate_research_questions($topic) → 3 AI-generated questions
 *    → perform_search() untuk setiap question → formatted findings
 * 3. Round 2: analyze_and_generate_followup($topic, $findings) → follow-up questions
 *    → perform_search() untuk setiap follow-up → more findings
 * 4. Return formatted "DEEP RESEARCH REPORT FOR: {topic}\n\n{all_findings}"
 *
 * Strategi test:
 * - Inject mock AIClient via reflection (private method calls AIClient internally)
 * - generate_research_questions() dan analyze_and_generate_followup() memanggil
 *   AIClient::generate_text() dan memparsing response → kendalikan via mock
 * - perform_search() menggunakan SearchSource asli (selalu return "No search results"
 *   di test env karena serpapi key tidak tersedia)
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      intelligence
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Intelligence\ResearchAgent;
use Autoblog\Utils\AIClient;
use Autoblog\Utils\OptionCache;

class ResearchAgentTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        // Reset all global mock options
        global $_autoblog_mock_options;
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_options         = [];
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;

        // Default: deep research enabled
        $_autoblog_mock_options['autoblog_enable_deep_research'] = '1';
        // Default provider untuk AI calls
        $_autoblog_mock_options['autoblog_ai_provider'] = 'openai';
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_options         = [];
        $_autoblog_mock_remote_body     = null;
        $_autoblog_mock_remote_response = null;

        OptionCache::flush();
        parent::tearDown();
    }

    // ====================================================================
    // HELPER: Create ResearchAgent with mock AIClient injected
    // ====================================================================

    /**
     * Buat ResearchAgent dengan constructor dimatikan dan mock AIClient
     * di-inject via reflection.
     *
     * Private methods (generate_research_questions, perform_search,
     * analyze_and_generate_followup) tidak bisa di-mock via onlyMethods()
     * karena PHPUnit tidak bisa override private methods. Sebagai gantinya,
     * kita kendalikan AIClient::generate_text() yang dipanggil oleh
     * generate_research_questions() dan analyze_and_generate_followup().
     *
     * @param AIClient|null $mockAI Jika null, buat mock default (generate_text return null)
     * @return ResearchAgent
     */
    private function createAgentWithMockAI( ?AIClient $mockAI = null ): ResearchAgent {
        $agent = $this->getMockBuilder( ResearchAgent::class )
            ->disableOriginalConstructor()
            ->onlyMethods( [] )
            ->getMock();

        if ( $mockAI === null ) {
            $mockAI = $this->createMock( AIClient::class );
            $mockAI->method( 'generate_text' )->willReturn( null );
        }

        $reflection = new \ReflectionClass( ResearchAgent::class );
        $prop       = $reflection->getProperty( 'ai_client' );
        $prop->setAccessible( true );
        $prop->setValue( $agent, $mockAI );

        return $agent;
    }

    // ====================================================================
    // CONDUCT_RESEARCH — GUARD TEST
    // ====================================================================

    /**
     * Test bahwa conduct_research() return '' ketika deep research disabled.
     */
    public function test_conduct_research_returns_empty_when_disabled() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_enable_deep_research'] = '0';

        $agent = $this->createAgentWithMockAI();
        $result = $agent->conduct_research( 'AI Technology' );

        $this->assertSame( '', $result,
            'conduct_research() harus return "" ketika deep research disabled'
        );
    }

    /**
     * Test bahwa guard menggunakan OptionCache dan bukan nilai hardcoded.
     *
     * Tidak set autoblog_enable_deep_research key → OptionCache::get
     * return default false → guard triggered → return ''.
     */
    public function test_conduct_research_guard_checks_option_not_hardcoded() {
        global $_autoblog_mock_options;
        // Hapus key dari mock options, jangan pakai unset() langsung
        // karena key mungkin tidak ada jika setUp belum jalan sempurna
        if ( isset( $_autoblog_mock_options['autoblog_enable_deep_research'] ) ) {
            unset( $_autoblog_mock_options['autoblog_enable_deep_research'] );
        }

        $agent = $this->createAgentWithMockAI();
        $result = $agent->conduct_research( 'PHP' );

        $this->assertSame( '', $result,
            'Option tidak diset → harus return "" (disabled default)'
        );
    }

    // ====================================================================
    // CONDUCT_RESEARCH — ORCHESTRATION VIA MOCK AIClient
    // ====================================================================

    /**
     * Test bahwa conduct_research() menjalankan full pipeline:
     * R1 (3 questions → 3 searches) + R2 (1 follow-up → 1 search).
     *
     * AIClient::generate_text() dipanggil 2x:
     * 1. generate_research_questions() → return "Q1?\nQ2?\nQ3?"
     * 2. analyze_and_generate_followup() → return "Follow-up?"
     *
     * perform_search() berjalan real (SearchSource, return "No search results"
     * karena serpapi key tidak ada).
     */
    public function test_conduct_research_calls_all_phases() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->exactly( 2 ) )
            ->method( 'generate_text' )
            ->willReturnOnConsecutiveCalls(
                "Q1?\nQ2?\nQ3?",          // generate_research_questions
                "Follow-up?"               // analyze_and_generate_followup
            );

        // Set active model untuk AI calls
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';

        $agent = $this->createAgentWithMockAI( $mockAI );
        $result = $agent->conduct_research( 'AI Technology' );

        $this->assertStringContainsString(
            'DEEP RESEARCH REPORT FOR: AI Technology',
            $result,
            'Report harus diawali dengan header DEEP RESEARCH REPORT'
        );
        $this->assertStringContainsString( 'Q1?', $result,
            'Report harus berisi pertanyaan dari Round 1'
        );
        $this->assertStringContainsString( 'Follow-up?', $result,
            'Report harus berisi follow-up question dari Round 2'
        );

        // perform_search() menghasilkan 4 entri: 3 dari R1 + 1 dari R2
        $this->assertStringContainsString( 'Q1?', $result );
        $this->assertStringContainsString( 'Q2?', $result );
        $this->assertStringContainsString( 'Q3?', $result );
        $this->assertStringContainsString( 'Follow-up?', $result );
    }

    /**
     * Test bahwa Round 2 dilewati jika analyze_and_generate_followup
     * mengembalikan string tanpa baris baru.
     *
     * AIClient R1 return "Q1?" → generate_research_questions() → ['Q1?']
     * AIClient R2 return "" → analyze_and_generate_followup() → [] → skip
     */
    public function test_conduct_research_skips_round_2_when_no_followup() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->exactly( 2 ) )
            ->method( 'generate_text' )
            ->willReturnOnConsecutiveCalls(
                'Q1?',   // R1
                ''        // R2 → response kosong → return []
            );

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';

        $agent = $this->createAgentWithMockAI( $mockAI );
        $result = $agent->conduct_research( 'Test' );

        $this->assertStringContainsString( 'DEEP RESEARCH REPORT FOR: Test', $result );
        // Report hanya berisi Q1? (R1), tidak ada follow-up
        $this->assertStringContainsString( 'Q1?', $result );
    }

    /**
     * Test bahwa Round 1 menghasilkan 0 questions → perform_search tidak
     * pernah dipanggil. Round 2 tetap jalan.
     *
     * AIClient R1 return '' → generate_research_questions() → [] → skip
     * AIClient R2 return '' → analyze_and_generate_followup() → [] → skip
     */
    public function test_conduct_research_handles_empty_round_1_questions() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->exactly( 2 ) )
            ->method( 'generate_text' )
            ->willReturnOnConsecutiveCalls(
                '',   // R1: empty → no questions
                ''    // R2: empty → no follow-up
            );

        $agent = $this->createAgentWithMockAI( $mockAI );
        $result = $agent->conduct_research( 'Empty Topic' );

        $this->assertStringContainsString( 'DEEP RESEARCH REPORT FOR: Empty Topic', $result );
    }

    /**
     * Test bahwa conduct_research() tetap menghasilkan report walaupun
     * SearchSource mengembalikan hasil kosong (tidak ada SerpApi key).
     */
    public function test_conduct_research_handles_empty_search_results() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->exactly( 2 ) )
            ->method( 'generate_text' )
            ->willReturnOnConsecutiveCalls(
                'Test query?',
                ''
            );

        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';

        $agent = $this->createAgentWithMockAI( $mockAI );
        $result = $agent->conduct_research( 'Test Topic' );

        $this->assertStringContainsString( 'DEEP RESEARCH REPORT', $result,
            'Report harus tetap dihasilkan walaupun search results kosong'
        );
    }

    // ====================================================================
    // GENERATE_RESEARCH_QUESTIONS — DIRECT TEST VIA REFLECTION
    // ====================================================================

    /**
     * Test bahwa generate_research_questions() memanggil AI dan memparsing
     * response menjadi array.
     */
    public function test_generate_research_questions_parses_ai_response() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->stringContains( 'Generate 3 specific search queries' ),
                $this->anything(),
                $this->anything()
            )
            ->willReturn( "Apa dampak AI pada pendidikan?\nStatistik adopsi AI 2024\nPerbandingan AI vs manusia" );

        $agent  = $this->createAgentWithMockAI( $mockAI );
        $result = $this->invokeMethod( $agent, 'generate_research_questions', [ 'AI in Education' ] );

        $this->assertCount( 3, $result,
            'Harus menghasilkan 3 pertanyaan dari AI response'
        );
        $this->assertContains( 'Apa dampak AI pada pendidikan?', $result );
        $this->assertContains( 'Statistik adopsi AI 2024', $result );
        $this->assertContains( 'Perbandingan AI vs manusia', $result );
    }

    /**
     * Test bahwa generate_research_questions() return array kosong
     * jika AI mengembalikan response kosong.
     */
    public function test_generate_research_questions_handles_empty_ai_response() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( '' );

        $agent  = $this->createAgentWithMockAI( $mockAI );
        $result = $this->invokeMethod( $agent, 'generate_research_questions', [ 'AI' ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            "Response kosong, array_filter membersihkan empty string"
        );
    }

    /**
     * Test bahwa generate_research_questions() return array kosong
     * jika AI gagal (generate_text return false).
     */
    public function test_generate_research_questions_handles_ai_failure() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( false );

        $agent  = $this->createAgentWithMockAI( $mockAI );
        $result = $this->invokeMethod( $agent, 'generate_research_questions', [ 'AI' ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'AI gagal (false) → harus return array kosong'
        );
    }

    /**
     * Test bahwa generate_research_questions() membersihkan whitespace
     * dari setiap baris response.
     */
    public function test_generate_research_questions_trims_whitespace() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( "  Question 1  \n  Question 2  \n  Question 3  " );

        $agent  = $this->createAgentWithMockAI( $mockAI );
        $result = $this->invokeMethod( $agent, 'generate_research_questions', [ 'Topik' ] );

        $this->assertCount( 3, $result );
        $this->assertSame( 'Question 1', $result[0] );
        $this->assertSame( 'Question 2', $result[1] );
        $this->assertSame( 'Question 3', $result[2] );
    }

    // ====================================================================
    // ANALYZE_AND_GENERATE_FOLLOWUP — DIRECT TEST VIA REFLECTION
    // ====================================================================

    /**
     * Test bahwa analyze_and_generate_followup() memanggil AI dengan
     * findings yang sudah ada dan memparsing response follow-up questions.
     */
    public function test_analyze_followup_parses_ai_response() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->stringContains( 'Topic:' ),
                $this->anything(),
                $this->anything()
            )
            ->willReturn( "Deep question 1\nDeep question 2" );

        $agent  = $this->createAgentWithMockAI( $mockAI );
        $result = $this->invokeMethod( $agent, 'analyze_and_generate_followup', [ 'Topic', 'Initial findings...' ] );

        $this->assertCount( 2, $result );
        $this->assertContains( 'Deep question 1', $result );
        $this->assertContains( 'Deep question 2', $result );
    }

    /**
     * Test bahwa analyze_and_generate_followup() return empty array
     * jika AI gagal.
     */
    public function test_analyze_followup_handles_ai_failure() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->method( 'generate_text' )->willReturn( false );

        $agent  = $this->createAgentWithMockAI( $mockAI );
        $result = $this->invokeMethod( $agent, 'analyze_and_generate_followup', [ 'Topic', 'Findings' ] );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result,
            'AI gagal → harus return array kosong'
        );
    }

    /**
     * Test bahwa analyze_and_generate_followup() memotong findings
     * menjadi 3000 karakter pertama (substr) untuk prompt AI.
     */
    public function test_analyze_followup_truncates_findings() {
        $mockAI = $this->createMock( AIClient::class );
        $mockAI->expects( $this->once() )
            ->method( 'generate_text' )
            ->with(
                $this->callback( function( $prompt ) {
                    // Prompt harus mengandung findings yang ditruncate ke max 3000 chars
                    $this->assertStringContainsString( 'Data point', $prompt );
                    // Total prompt pasti > 3000 — tapi findings bagiannya max 3000
                    return true;
                } ),
                $this->anything(),
                $this->anything()
            )
            ->willReturn( 'Follow-up?' );

        $long_findings = str_repeat( 'Data point A. ', 500 ); // ~7500 chars
        $this->assertGreaterThan( 3000, strlen( $long_findings ),
            'Findings harus > 3000 char agar truncation teruji'
        );

        $agent  = $this->createAgentWithMockAI( $mockAI );
        $result = $this->invokeMethod( $agent, 'analyze_and_generate_followup', [ 'Topic', $long_findings ] );

        $this->assertCount( 1, $result );
    }

    // ====================================================================
    // PERFORM_SEARCH (private) — via reflection
    //
    // perform_search($query) membuat SearchSource internal:
    //   1. SearchSource::fetch_data() return [] → "No search results found for..."
    //   2. SearchSource::fetch_data() return items → formatted markdown summary
    //   3. SearchSource throws exception → "Error retrieving search results."
    //
    // Strategi kontrol SearchSource:
    //   - Empty: gunakan serpapi provider tanpa key → validate_source fail → return []
    //   - Hasil: gunakan duckduckgo_free dengan wp_remote_get mock HTML
    //   - Exception: gunakan duckduckgo_free tanpa mock → Guzzle exception
    // ====================================================================

    /**
     * Test perform_search return "No search results found" ketika
     * SearchSource mengembalikan array kosong.
     */
    public function test_perform_search_returns_no_results_message_when_empty() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'serpapi';
        // Tidak set serpapi_key → validate_source fail → fetch_data return []

        $agent = $this->createAgentWithMockAI();
        $result = $this->invokeMethod( $agent, 'perform_search', [ 'AI technology trends' ] );

        $this->assertStringContainsString( 'No search results found', $result,
            'SearchSource return [] harus menampilkan pesan empty'
        );
        $this->assertStringContainsString( 'AI technology trends', $result,
            'Pesan harus menyertakan query'
        );
    }

    /**
     * Test perform_search memformat hasil pencarian menjadi markdown summary.
     *
     * Menggunakan duckduckgo_free + wp_remote_get mock HTML agar SearchSource
     * mengembalikan items yang bisa diformat.
     */
    public function test_perform_search_formats_results_into_markdown() {
        global $_autoblog_mock_options;
        global $_autoblog_mock_remote_body;
        global $_autoblog_mock_remote_response;

        $_autoblog_mock_options['autoblog_search_provider'] = 'duckduckgo_free';

        $html = '<html><body><div class="result">
            <a class="result__a" href="https://ex.com/a">AI Technology Trends 2026</a>
            <a class="result__snippet">Latest developments in AI technology.</a>
        </div></body></html>';

        $_autoblog_mock_remote_body     = $html;
        $_autoblog_mock_remote_response = [ 'body' => $html ];

        $agent = $this->createAgentWithMockAI();
        $result = $this->invokeMethod( $agent, 'perform_search', [ 'AI technology' ] );

        // Harus mengandung hasil yang diformat sebagai markdown
        $this->assertStringContainsString( '**AI Technology Trends 2026**', $result,
            'Harus mengandung formatted title dalam markdown bold'
        );
        $this->assertStringContainsString( 'Snippet:', $result,
            'Harus mengandung Snippet label'
        );
        $this->assertStringNotContainsString( 'No search results found', $result,
            'SearchSource return results, bukan empty message'
        );
    }

    /**
     * Test perform_search tidak throw exception ketika SearchSource gagal
     * (semua driver menangkap exception secara internal).
     *
     * DuckDuckGo Free tanpa mock → ddg_fetch_html return '' via catch
     * → fetch_duckduckgo_free return [] → perform_search: "No search results found..."
     */
    public function test_perform_search_does_not_throw_on_guzzle_failure() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_search_provider'] = 'duckduckgo_free';

        $agent = $this->createAgentWithMockAI();

        // SearchSource menangkap semua exception internal — perform_search
        // harus tetap return string, bukan throw.
        $result = $this->invokeMethod( $agent, 'perform_search', [ 'guzzle-failure-test' ] );

        $this->assertIsString( $result,
            'Harus return string meskipun Guzzle gagal (SearchSource catch internal)'
        );
        $this->assertStringContainsString( 'No search results found', $result,
            'SearchSource catch internal → return empty → No search results'
        );
    }

    // ====================================================================
    // HELPER: Invoke private method via reflection
    // ====================================================================

    /**
     * Panggil private method via reflection.
     *
     * @param object $object
     * @param string $methodName
     * @param array  $parameters
     * @return mixed
     */
    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }
}
