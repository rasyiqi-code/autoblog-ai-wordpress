<?php

namespace Autoblog\Core;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Autoblog
 * @subpackage Autoblog/includes/Core
 * @author     Rasyiqi
 */
class Autoblog {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version = defined( 'AUTOBLOG_VERSION' ) ? AUTOBLOG_VERSION : '1.0.0';
		$this->plugin_name = 'autoblog';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Autoblog_Loader. Orchestrates the hooks of the plugin.
	 * - Autoblog_i18n. Defines internationalization functionality.
	 * - Autoblog_Admin. Defines all hooks for the admin area.
	 * - Autoblog_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Core/Loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'Core/i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-autoblog-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-autoblog-public.php';

		$this->loader = new Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Autoblog_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . '../admin/class-autoblog-admin.php';
		$plugin_admin = new \Autoblog\Admin\Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		// Handler data sources (upload/hapus) HARUS di admin_init agar redirect bisa kirim header
		$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_data_source_actions' );

		// AJAX handlers (Run Pipeline & Get Logs tanpa reload)
		$this->loader->add_action( 'wp_ajax_autoblog_run_pipeline', $plugin_admin, 'ajax_run_pipeline' );
		$this->loader->add_action( 'wp_ajax_autoblog_run_collector', $plugin_admin, 'ajax_run_collector' );
		$this->loader->add_action( 'wp_ajax_autoblog_run_ideator', $plugin_admin, 'ajax_run_ideator' );
		$this->loader->add_action( 'wp_ajax_autoblog_run_writer', $plugin_admin, 'ajax_run_writer' );
		$this->loader->add_action( 'wp_ajax_autoblog_ai_predict_taxonomy', $plugin_admin, 'ajax_ai_predict_taxonomy' );
		$this->loader->add_action( 'wp_ajax_autoblog_get_logs', $plugin_admin, 'ajax_get_logs' );
		$this->loader->add_action( 'wp_ajax_autoblog_test_gemini_grounding', $plugin_admin, 'ajax_test_gemini_grounding' );

        // Scheduler
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '../includes/Publisher/UpdateScheduler.php';
        $scheduler = new \Autoblog\Publisher\UpdateScheduler();
        $this->loader->add_action( 'admin_init', $scheduler, 'schedule_event' );
        $this->loader->add_action( 'update_option_autoblog_cron_schedule', $scheduler, 'reschedule_on_update' );
        $this->loader->add_action( 'update_option_autoblog_refresh_schedule', $scheduler, 'reschedule_on_update' );
        $this->loader->add_filter( 'cron_schedules', $scheduler, 'add_cron_intervals' );

        // Runner
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '../includes/Core/Runner.php';
        $runner = new \Autoblog\Core\Runner();
        $this->loader->add_action( 'autoblog_run_pipeline', $runner, 'run_pipeline' );

        // Living Content (Content Refresher)
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '../includes/Core/ContentRefresher.php';
        $refresher = new \Autoblog\Core\ContentRefresher();
        $this->loader->add_action( 'autoblog_daily_refresh', $refresher, 'refresh_old_content' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		// $plugin_public = new Autoblog_Public( $this->get_plugin_name(), $this->get_version() );

		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
