<?php
/**
 * Unit Test untuk Autoblog\Admin\Admin.
 *
 * Dual-mode compatible: bekerja dengan mock fallback (mocks.php) dan
 * real WordPress test suite (WP_TESTS_DIR).
 *
 * @package    Autoblog
 * @subpackage Autoblog/tests
 * @group      unit
 * @group      admin
 */

namespace Autoblog\Tests;

use PHPUnit\Framework\TestCase;
use Autoblog\Admin\Admin;
use Autoblog\Utils\OptionCache;

require_once dirname( __DIR__ ) . '/admin/class-autoblog-admin.php';

class AdminTest extends TestCase {

    /** @var Admin */
    private $admin;

    protected function setUp(): void {
        parent::setUp();
        OptionCache::flush();

        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];

        $GLOBALS['_wp_mock_calls'] = [];
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [ 'id' => 'toplevel_page_autoblog' ];

        $this->admin = new Admin( 'autoblog', '1.1.9' );
    }

    protected function tearDown(): void {
        global $_autoblog_mock_options;
        $_autoblog_mock_options = [];
        OptionCache::flush();
        $GLOBALS['_wp_mock_calls'] = [];
        parent::tearDown();
    }

    // ====================================================================
    // HELPERS
    // ====================================================================

    /**
     * Deteksi apakah dalam mock fallback mode (tracking via _wp_mock_calls).
     */
    private function isMockMode(): bool {
        return isset( $GLOBALS['_wp_mock_calls']['add_menu_page'] )
            || ! function_exists( 'add_menu_page' );
    }

    private function invokeMethod( $object, string $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $parameters );
    }

    // ====================================================================
    // CONSTRUCTOR
    // ====================================================================

    public function test_constructor_stores_plugin_name_and_version() {
        $reflection = new \ReflectionClass( Admin::class );

        $propName = $reflection->getProperty( 'plugin_name' );
        $propName->setAccessible( true );
        $this->assertSame( 'autoblog', $propName->getValue( $this->admin ) );

        $propVersion = $reflection->getProperty( 'version' );
        $propVersion->setAccessible( true );
        $this->assertSame( '1.1.9', $propVersion->getValue( $this->admin ) );
    }

    // ====================================================================
    // ADD_PLUGIN_ADMIN_MENU
    // ====================================================================

    public function test_add_plugin_admin_menu_registers_menu_page() {
        $this->admin->add_plugin_admin_menu();

        $calls = $GLOBALS['_wp_mock_calls']['add_menu_page'] ?? null;

        if ( $calls !== null ) {
            // Mock fallback mode: cek tracking
            $this->assertCount( 1, $calls, 'add_menu_page harus dipanggil tepat 1 kali' );
            $this->assertSame( 'Autoblog AI Settings', $calls[0][0] );
            $this->assertSame( 'Autoblog AI', $calls[0][1] );
            $this->assertSame( 'manage_options', $calls[0][2] );
            $this->assertSame( 'autoblog', $calls[0][3] );
            $this->assertSame( 'dashicons-superhero', $calls[0][5] );
            $this->assertSame( 110, $calls[0][6] );
            $this->assertIsCallable( $calls[0][4] );
        } else {
            // Real WordPress mode: cek $GLOBALS['menu']
            global $menu;
            $this->assertNotEmpty( $menu, 'Menu global harus terisi' );
            $found = false;
            foreach ( (array) $menu as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'autoblog' ) {
                    $this->assertSame( 'Autoblog AI Settings', $item[0] );
                    $this->assertSame( 'Autoblog AI', $item[3] );
                    $found = true;
                    break;
                }
            }
            $this->assertTrue( $found, 'Menu "autoblog" harus terdaftar' );
        }
    }

    public function test_add_plugin_admin_menu_registers_submenu() {
        $this->admin->add_plugin_admin_menu();

        $calls = $GLOBALS['_wp_mock_calls']['add_submenu_page'] ?? null;

        if ( $calls !== null ) {
            $this->assertCount( 1, $calls, 'add_submenu_page harus dipanggil tepat 1 kali' );
            $this->assertSame( 'edit.php', $calls[0][0] );
            $this->assertSame( 'Auto-Set Taxonomy', $calls[0][1] );
            $this->assertSame( 'Auto-Set Taxonomy', $calls[0][2] );
            $this->assertSame( 'manage_options', $calls[0][3] );
            $this->assertSame( 'autoblog-taxonomy-tools', $calls[0][4] );
            $this->assertIsCallable( $calls[0][5] );
        } else {
            global $submenu;
            $found = false;
            if ( isset( $submenu['edit.php'] ) ) {
                foreach ( $submenu['edit.php'] as $item ) {
                    if ( isset( $item[2] ) && $item[2] === 'autoblog-taxonomy-tools' ) {
                        $this->assertSame( 'Auto-Set Taxonomy', $item[0] );
                        $found = true;
                        break;
                    }
                }
            }
            $this->assertTrue( $found, 'Submenu "autoblog-taxonomy-tools" harus terdaftar di edit.php' );
        }
    }

    // ====================================================================
    // ADD_DASHBOARD_WIDGETS
    // ====================================================================

    public function test_add_dashboard_widgets_registers_widget() {
        $this->admin->add_dashboard_widgets();

        $calls = $GLOBALS['_wp_mock_calls']['wp_add_dashboard_widget'] ?? null;

        if ( $calls !== null ) {
            $this->assertCount( 1, $calls, 'wp_add_dashboard_widget harus dipanggil tepat 1 kali' );
            $this->assertSame( 'autoblog_crediblemark_promo_widget', $calls[0][0] );
            $this->assertStringContainsString( 'CredibleMark', $calls[0][1] );
            $this->assertIsCallable( $calls[0][2] );
        } else {
            // Real WP: cek apakah widget terdaftar di $wp_meta_boxes
            global $wp_meta_boxes;
            $found = false;
            if ( ! empty( $wp_meta_boxes ) ) {
                foreach ( (array) $wp_meta_boxes as $page => $contexts ) {
                    foreach ( (array) $contexts as $context => $priorities ) {
                        foreach ( (array) $priorities as $priority => $widgets ) {
                            foreach ( (array) $widgets as $id => $widget ) {
                                if ( $id === 'autoblog_crediblemark_promo_widget' ) {
                                    $this->assertStringContainsString( 'CredibleMark', $widget['title'] );
                                    $found = true;
                                    break 4;
                                }
                            }
                        }
                    }
                }
            }
            $this->assertTrue( $found, 'Dashboard widget harus terdaftar' );
        }
    }

    // ====================================================================
    // ENQUEUE_STYLES
    // ====================================================================

    public function test_enqueue_styles_enqueues_main_css() {
        $this->admin->enqueue_styles();

        $calls = $GLOBALS['_wp_mock_calls']['wp_enqueue_style'] ?? null;

        if ( $calls !== null ) {
            $this->assertNotEmpty( $calls, 'wp_enqueue_style harus dipanggil minimal 1 kali' );
            $this->assertSame( 'autoblog', $calls[0][0] );
            $this->assertStringContainsString( 'css/autoblog-admin.css', $calls[0][1] );
            $this->assertSame( '1.1.9', $calls[0][3] );
            $this->assertSame( 'all', $calls[0][4] );
        } else {
            $this->assertTrue( wp_style_is( 'autoblog', 'enqueued' ),
                'Style "autoblog" harus di-enqueue'
            );
        }
    }

    public function test_enqueue_styles_enqueues_taxonomy_css_on_taxonomy_page() {
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [
            'id' => 'posts_page_autoblog-taxonomy-tools',
        ];

        $this->admin->enqueue_styles();

        $calls = $GLOBALS['_wp_mock_calls']['wp_enqueue_style'] ?? null;

        if ( $calls !== null ) {
            $this->assertCount( 2, $calls, 'Harus ada 2 wp_enqueue_style calls (main + taxonomy)' );
            $this->assertSame( 'autoblog', $calls[0][0] );
            $this->assertSame( 'autoblog-taxonomy', $calls[1][0] );
            $this->assertStringContainsString( 'css/autoblog-admin-taxonomy.css', $calls[1][1] );
        } else {
            $this->assertTrue( wp_style_is( 'autoblog', 'enqueued' ) );
            $this->assertTrue( wp_style_is( 'autoblog-taxonomy', 'enqueued' ),
                'Style "autoblog-taxonomy" harus di-enqueue di halaman taxonomy'
            );
        }
    }

    public function test_enqueue_styles_skips_taxonomy_css_on_other_pages() {
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [
            'id' => 'dashboard',
        ];

        $this->admin->enqueue_styles();

        $calls = $GLOBALS['_wp_mock_calls']['wp_enqueue_style'] ?? null;

        if ( $calls !== null ) {
            $this->assertCount( 1, $calls, 'Di halaman lain hanya main CSS yang di-enqueue' );
            $this->assertSame( 'autoblog', $calls[0][0] );
        } else {
            $this->assertTrue( wp_style_is( 'autoblog', 'enqueued' ) );
            $this->assertFalse( wp_style_is( 'autoblog-taxonomy', 'enqueued' ),
                'Style "autoblog-taxonomy" TIDAK boleh di-enqueue di dashboard'
            );
        }
    }

    // ====================================================================
    // ENQUEUE_SCRIPTS
    // ====================================================================

    public function test_enqueue_scripts_enqueues_all_js_files() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [ 'openai' => 'sk-test' ];
        $_autoblog_mock_options['autoblog_ai_model'] = 'gpt-4o';
        OptionCache::flush();

        $this->admin->enqueue_scripts();

        $calls = $GLOBALS['_wp_mock_calls']['wp_enqueue_script'] ?? null;

        if ( $calls !== null ) {
            $handles = array_map( function( $args ) { return $args[0]; }, $calls );
            $this->assertContains( 'autoblog-pipeline', $handles );
            $this->assertContains( 'autoblog-ai-engine', $handles );
            $this->assertContains( 'autoblog-api-keys', $handles );
            $this->assertContains( 'autoblog-data-sources', $handles );
            $this->assertCount( 4, $calls, 'Harus tepat 4 JS file di-enqueue' );
        } else {
            $this->assertTrue( wp_script_is( 'autoblog-pipeline', 'enqueued' ) );
            $this->assertTrue( wp_script_is( 'autoblog-ai-engine', 'enqueued' ) );
            $this->assertTrue( wp_script_is( 'autoblog-api-keys', 'enqueued' ) );
            $this->assertTrue( wp_script_is( 'autoblog-data-sources', 'enqueued' ) );
        }
    }

    public function test_enqueue_scripts_localizes_pipeline_script() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [ 'openai' => 'sk-test' ];
        $_autoblog_mock_options['autoblog_ai_model'] = 'gpt-4o';
        OptionCache::flush();

        $this->admin->enqueue_scripts();

        $localizeCalls = $GLOBALS['_wp_mock_calls']['wp_localize_script'] ?? null;

        if ( $localizeCalls !== null ) {
            $this->assertNotEmpty( $localizeCalls, 'wp_localize_script harus dipanggil' );
            $pipelineLocalize = array_values( array_filter( $localizeCalls, function( $args ) {
                return $args[0] === 'autoblog-pipeline';
            } ) );
            $this->assertNotEmpty( $pipelineLocalize, 'Pipeline script harus di-localize' );
            $l10n = $pipelineLocalize[0][2];
            $this->assertSame( 'autoblog_ajax', $pipelineLocalize[0][1] );
            $this->assertArrayHasKey( 'ajax_url', $l10n );
            $this->assertArrayHasKey( 'nonce', $l10n );
            $this->assertArrayHasKey( 'keys_filled', $l10n );
            $this->assertArrayHasKey( 'catalog_models', $l10n );
            $this->assertArrayHasKey( 'selected_model', $l10n );
            $this->assertArrayHasKey( 'dynamic_providers', $l10n );
            $this->assertEquals( 'gpt-4o', $l10n['selected_model'] );
        } else {
            // Real WP: cek bahwa script terdaftar (localize tidak bisa dicek via API)
            $this->assertTrue( wp_script_is( 'autoblog-pipeline', 'enqueued' ),
                'Pipeline script harus di-enqueue'
            );
        }
    }

    public function test_enqueue_scripts_handles_taxonomy_tools_page() {
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [
            'id' => 'posts_page_autoblog-taxonomy-tools',
        ];

        $this->admin->enqueue_scripts();

        $dequeueCalls = $GLOBALS['_wp_mock_calls']['wp_dequeue_script'] ?? null;

        if ( $dequeueCalls !== null ) {
            $dequeuedHandles = array_map( function( $args ) { return $args[0]; }, $dequeueCalls );
            $this->assertContains( 'inline-edit-post', $dequeuedHandles,
                'inline-edit-post harus di-dequeue di halaman taxonomy tools'
            );
            $inlineCalls = $GLOBALS['_wp_mock_calls']['wp_add_inline_script'] ?? [];
            $this->assertNotEmpty( $inlineCalls, 'wp_add_inline_script harus dipanggil' );
            $this->assertStringContainsString( 'inlineEditPost', $inlineCalls[0][1] );
            $this->assertSame( 'before', $inlineCalls[0][2] );
        } else {
            // Real WP: inline-edit-post harus di-dequeue
            $this->assertFalse( wp_script_is( 'inline-edit-post', 'enqueued' ),
                'inline-edit-post harus di-dequeue'
            );
        }
    }

    public function test_enqueue_scripts_skips_on_unrecognized_screen() {
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [
            'id' => 'dashboard',
        ];

        $this->admin->enqueue_scripts();

        $calls = $GLOBALS['_wp_mock_calls']['wp_enqueue_script'] ?? null;

        if ( $calls !== null ) {
            $this->assertEmpty( $calls, 'Script tidak boleh di-enqueue di halaman yang tidak dikenali' );
        } else {
            $this->assertFalse( wp_script_is( 'autoblog-pipeline', 'enqueued' ),
                'Script tidak boleh di-enqueue di halaman yang tidak dikenali'
            );
        }
    }

    // ====================================================================
    // COMPUTE_KEYS_FILLED (private — via reflection)
    // ====================================================================

    public function test_compute_keys_filled_returns_false_when_no_keys() {
        $result = $this->invokeMethod( $this->admin, 'compute_keys_filled', [] );

        $this->assertArrayHasKey( 'openai', $result );
        $this->assertArrayHasKey( 'gemini_001', $result );
        $this->assertArrayHasKey( 'hf', $result );
        $this->assertFalse( $result['openai'] );
        $this->assertFalse( $result['gemini_001'] );
        $this->assertFalse( $result['hf'] );
    }

    public function test_compute_keys_filled_detects_custom_keys() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'openai' => 'sk-test',
            'google' => 'AIza-test',
        ];
        OptionCache::flush();

        $result = $this->invokeMethod( $this->admin, 'compute_keys_filled', [] );

        $this->assertTrue( $result['openai'] );
        $this->assertTrue( $result['gemini_001'] );
        $this->assertFalse( $result['hf'] );
    }

    public function test_compute_keys_filled_falls_back_to_legacy_options() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_openai_key'] = 'sk-legacy';
        $_autoblog_mock_options['autoblog_gemini_key'] = 'AIza-legacy';
        $_autoblog_mock_options['autoblog_hf_key'] = 'hf-legacy';
        OptionCache::flush();

        $result = $this->invokeMethod( $this->admin, 'compute_keys_filled', [] );

        $this->assertTrue( $result['openai'], 'Legacy openai key harus terdeteksi' );
        $this->assertTrue( $result['gemini_001'], 'Legacy gemini key harus terdeteksi' );
        $this->assertTrue( $result['hf'], 'Legacy HF key harus terdeteksi' );
    }

    public function test_compute_keys_filled_hf_detects_both_custom_and_hf_keys() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_custom_api_keys'] = [
            'hf' => 'hf-custom',
        ];
        OptionCache::flush();

        $result = $this->invokeMethod( $this->admin, 'compute_keys_filled', [] );
        $this->assertTrue( $result['hf'], 'HF key dari custom_keys["hf"] harus true' );
    }

    // ====================================================================
    // IS_TAXONOMY_TOOLS_PAGE (private — via reflection)
    // ====================================================================

    public function test_is_taxonomy_tools_page_returns_true_on_taxonomy_page() {
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [
            'id' => 'posts_page_autoblog-taxonomy-tools',
        ];

        $result = $this->invokeMethod( $this->admin, 'is_taxonomy_tools_page', [] );
        $this->assertTrue( $result );
    }

    public function test_is_taxonomy_tools_page_returns_false_on_other_pages() {
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = (object) [
            'id' => 'toplevel_page_autoblog',
        ];

        $result = $this->invokeMethod( $this->admin, 'is_taxonomy_tools_page', [] );
        $this->assertFalse( $result );
    }

    public function test_is_taxonomy_tools_page_returns_false_when_no_screen() {
        $GLOBALS['_wp_mock_calls']['get_current_screen_return'] = null;

        $result = $this->invokeMethod( $this->admin, 'is_taxonomy_tools_page', [] );
        $this->assertFalse( $result );
    }

    // ====================================================================
    // HANDLE_UPLOAD_FILE (private)
    // ====================================================================

    public function test_handle_upload_file_success() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_knowledge'] = [];

        $this->invokeMethod( $this->admin, 'handle_upload_file', [
            [
                'name'     => 'test.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error'    => UPLOAD_ERR_OK,
                'size'     => 5000,
            ],
        ] );

        $kb = get_option( 'autoblog_knowledge', [] );
        $this->assertCount( 1, $kb, 'Knowledge base harus terisi' );
        $this->assertEquals( 'test.pdf', $kb[0]['name'] );
    }

    public function test_handle_upload_file_rejects_oversized_file() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_knowledge'] = [];

        $this->invokeMethod( $this->admin, 'handle_upload_file', [
            [
                'name'     => 'large.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error'    => UPLOAD_ERR_OK,
                'size'     => 50 * 1024 * 1024,
            ],
        ] );

        $kb = get_option( 'autoblog_knowledge', [] );
        $this->assertEmpty( $kb, 'File terlalu besar → KB harus kosong' );
    }

    public function test_handle_upload_file_rejects_invalid_type() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_knowledge'] = [];

        $this->invokeMethod( $this->admin, 'handle_upload_file', [
            [
                'name'     => 'virus.exe',
                'type'     => 'application/x-msdownload',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error'    => UPLOAD_ERR_OK,
                'size'     => 1000,
            ],
        ] );

        $kb = get_option( 'autoblog_knowledge', [] );
        $this->assertEmpty( $kb, 'Tipe file invalid → KB harus kosong' );
    }

    // ====================================================================
    // HANDLE_DELETE_KB_FILE (private)
    // ====================================================================

    public function test_handle_delete_kb_file_removes_entry_and_file() {
        global $_autoblog_mock_options;

        $tmpFile = tempnam( sys_get_temp_dir(), 'kb_test_' );
        file_put_contents( $tmpFile, 'test content' );

        $_autoblog_mock_options['autoblog_knowledge'] = [
            [ 'id' => 'doc_1', 'name' => 'keep.pdf',   'path' => '/tmp/fake1.pdf', 'date' => '2026-01-01' ],
            [ 'id' => 'doc_2', 'name' => 'delete.pdf', 'path' => $tmpFile,         'date' => '2026-01-02' ],
            [ 'id' => 'doc_3', 'name' => 'keep2.pdf',  'path' => '/tmp/fake3.pdf', 'date' => '2026-01-03' ],
        ];
        OptionCache::flush();

        $this->invokeMethod( $this->admin, 'handle_delete_kb_file', [ 1 ] );

        $kb = get_option( 'autoblog_knowledge', [] );
        $this->assertCount( 2, $kb, 'KB harus memiliki 2 entry setelah hapus' );
        $this->assertEquals( 'doc_1', $kb[0]['id'] );
        $this->assertEquals( 'doc_3', $kb[1]['id'] );
        $this->assertFileDoesNotExist( $tmpFile, 'File di disk harus dihapus' );
    }

    public function test_handle_delete_kb_file_ignores_invalid_index() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_knowledge'] = [
            [ 'id' => 'doc_1', 'name' => 'keep.pdf', 'path' => '/tmp/fake1.pdf', 'date' => '2026-01-01' ],
        ];
        OptionCache::flush();

        $this->invokeMethod( $this->admin, 'handle_delete_kb_file', [ 99 ] );

        $kb = get_option( 'autoblog_knowledge', [] );
        $this->assertCount( 1, $kb, 'Index invalid → KB tidak berubah' );
    }

    // ====================================================================
    // HANDLE_ADD_SOURCE (private)
    // ====================================================================

    public function test_handle_add_source_adds_multiple_urls() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [];

        $this->invokeMethod( $this->admin, 'handle_add_source', [
            [
                'source_url'        => 'https://example.com/rss1, https://example.com/rss2',
                'source_type'       => 'rss',
                'match_keywords'    => 'tech, AI',
                'negative_keywords' => 'spam',
                'source_selector'   => '.content',
            ],
        ] );

        $sources = get_option( 'autoblog_sources', [] );
        $this->assertCount( 2, $sources );
        $this->assertEquals( 'rss', $sources[0]['type'] );
        $this->assertStringContainsString( 'rss1', $sources[0]['url'] );
        $this->assertEquals( 'tech, AI', $sources[0]['match_keywords'] );
        $this->assertEquals( 'spam', $sources[0]['negative_keywords'] );
        $this->assertEquals( '.content', $sources[0]['selector'] );
    }

    public function test_handle_add_source_skips_empty_urls() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [];

        $this->invokeMethod( $this->admin, 'handle_add_source', [
            [
                'source_url'        => 'https://example.com/rss1, , , https://example.com/rss2',
                'source_type'       => 'rss',
                'match_keywords'    => '',
                'negative_keywords' => '',
                'source_selector'   => '',
            ],
        ] );

        $sources = get_option( 'autoblog_sources', [] );
        $this->assertCount( 2, $sources, 'URL kosong harus di-skip' );
    }

    public function test_handle_add_source_web_search_uses_sanitize_text_field() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [];

        $this->invokeMethod( $this->admin, 'handle_add_source', [
            [
                'source_url'        => 'https://example.com/search',
                'source_type'       => 'web_search',
                'match_keywords'    => '',
                'negative_keywords' => '',
                'source_selector'   => '',
            ],
        ] );

        $sources = get_option( 'autoblog_sources', [] );
        $this->assertCount( 1, $sources );
        $this->assertEquals( 'web_search', $sources[0]['type'] );
        $this->assertStringContainsString( 'example.com/search', $sources[0]['url'] );
    }

    // ====================================================================
    // HANDLE_DELETE_SOURCE (private)
    // ====================================================================

    public function test_handle_delete_source_removes_entry() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [ 'type' => 'rss', 'url' => 'https://example.com/rss1', 'match_keywords' => '', 'negative_keywords' => '', 'selector' => '' ],
            [ 'type' => 'rss', 'url' => 'https://example.com/rss2', 'match_keywords' => '', 'negative_keywords' => '', 'selector' => '' ],
            [ 'type' => 'rss', 'url' => 'https://example.com/rss3', 'match_keywords' => '', 'negative_keywords' => '', 'selector' => '' ],
        ];
        OptionCache::flush();

        $this->invokeMethod( $this->admin, 'handle_delete_source', [ 1 ] );

        $sources = get_option( 'autoblog_sources', [] );
        $this->assertCount( 2, $sources, '2 source tersisa setelah hapus' );
        $this->assertStringContainsString( 'rss1', $sources[0]['url'] );
        $this->assertStringContainsString( 'rss3', $sources[1]['url'] );
    }

    public function test_handle_delete_source_ignores_invalid_index() {
        global $_autoblog_mock_options;
        $_autoblog_mock_options['autoblog_sources'] = [
            [ 'type' => 'rss', 'url' => 'https://example.com/rss1', 'match_keywords' => '', 'negative_keywords' => '', 'selector' => '' ],
        ];
        OptionCache::flush();

        $this->invokeMethod( $this->admin, 'handle_delete_source', [ 99 ] );

        $sources = get_option( 'autoblog_sources', [] );
        $this->assertCount( 1, $sources, 'Index invalid → sources tidak berubah' );
    }

    // ====================================================================
    // HANDLE_DATA_SOURCE_ACTIONS — no-op when page mismatch
    // ====================================================================

    public function test_handle_data_source_actions_returns_early_when_page_mismatch() {
        $this->admin->handle_data_source_actions();
        $this->assertTrue( true, 'Tidak ada exception saat page mismatch' );
    }

    // ====================================================================
    // DISPLAY METHODS — verify method callable
    // ====================================================================

    public function test_display_plugin_setup_page_is_callable() {
        $this->assertIsCallable( [ $this->admin, 'display_plugin_setup_page' ] );
    }

    public function test_display_taxonomy_tools_page_is_callable() {
        $this->assertIsCallable( [ $this->admin, 'display_taxonomy_tools_page' ] );
    }

    // ====================================================================
    // RENDER_CREDIBLEMARK_PROMO_WIDGET
    // ====================================================================

    public function test_render_crediblemark_promo_widget_outputs_html() {
        ob_start();
        $this->admin->render_crediblemark_promo_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'CredibleMark', $output );
        $this->assertStringContainsString( 'crediblemark.com', $output );
        $this->assertStringContainsString( 'WhatsApp', $output );
    }

    // ====================================================================
    // STATIC PROXIES
    // ====================================================================

    public function test_get_dynamic_models_delegates_to_model_catalog() {
        $this->assertIsArray( Admin::get_dynamic_models() );
    }

    public function test_get_merged_models_delegates_to_model_catalog() {
        $this->assertIsArray( Admin::get_merged_models() );
    }

    public function test_get_dynamic_providers_delegates_to_model_catalog() {
        $this->assertIsArray( Admin::get_dynamic_providers() );
    }

}
