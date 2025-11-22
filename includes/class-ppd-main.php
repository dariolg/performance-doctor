<?php
/**
 * Main plugin class.
 *
 * Orchestrates all plugin components and handles initialization.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Main plugin class.
 */
class PPD_Main {

	/**
	 * Performance analyzer instance.
	 *
	 * @var PPD_Performance_Analyzer
	 */
	private $performance_analyzer;

	/**
	 * Conflict detector instance.
	 *
	 * @var PPD_Conflict_Detector
	 */
	private $conflict_detector;

	/**
	 * Recommendation engine instance.
	 *
	 * @var PPD_Recommendation_Engine
	 */
	private $recommendation_engine;

	/**
	 * Admin page instance.
	 *
	 * @var PPD_Admin_Page
	 */
	private $admin_page;

	/**
	 * Plugin inspector instance.
	 *
	 * @var PPD_Plugin_Inspector
	 */
	private $plugin_inspector;

	/**
	 * Backup manager instance.
	 *
	 * @var PPD_Backup_Manager
	 */
	private $backup_manager;

	/**
	 * Performance scorer instance.
	 *
	 * @var PPD_Performance_Scorer
	 */
	private $performance_scorer;

	/**
	 * Optimization engine instance.
	 *
	 * @var PPD_Optimization_Engine
	 */
	private $optimization_engine;

	/**
	 * Optimization hooks instance.
	 *
	 * @var PPD_Optimization_Hooks
	 */
	private $optimization_hooks;

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Initialize optimization hooks (frontend and admin).
		$this->optimization_hooks = new PPD_Optimization_Hooks();
		$this->optimization_hooks->init();

		// Only load admin functionality in admin area.
		if ( is_admin() ) {
			$this->init_admin();
		}
	}

	/**
	 * Initialize admin functionality.
	 */
	private function init_admin() {
		// Initialize core components.
		$this->plugin_inspector       = new PPD_Plugin_Inspector();
		$this->performance_analyzer   = new PPD_Performance_Analyzer( $this->plugin_inspector );
		$this->conflict_detector      = new PPD_Conflict_Detector( $this->plugin_inspector );
		$this->recommendation_engine  = new PPD_Recommendation_Engine();
		$this->backup_manager         = new PPD_Backup_Manager();
		$this->performance_scorer     = new PPD_Performance_Scorer();
		$this->optimization_engine    = new PPD_Optimization_Engine( $this->backup_manager );

		// Initialize admin page.
		$this->admin_page = new PPD_Admin_Page(
			$this->performance_analyzer,
			$this->conflict_detector,
			$this->recommendation_engine,
			$this->backup_manager,
			$this->performance_scorer,
			$this->optimization_engine
		);

		// Register admin hooks.
		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ppd_run_analysis', array( $this->admin_page, 'ajax_run_analysis' ) );
		add_action( 'wp_ajax_ppd_export_results', array( $this->admin_page, 'ajax_export_results' ) );
		add_action( 'wp_ajax_ppd_apply_optimization', array( $this->admin_page, 'ajax_apply_optimization' ) );
		add_action( 'wp_ajax_ppd_revert_optimization', array( $this->admin_page, 'ajax_revert_optimization' ) );
		add_action( 'wp_ajax_ppd_rollback_change', array( $this->admin_page, 'ajax_rollback_change' ) );
	}

	/**
	 * Get performance analyzer instance.
	 *
	 * @return PPD_Performance_Analyzer
	 */
	public function get_performance_analyzer() {
		return $this->performance_analyzer;
	}

	/**
	 * Get conflict detector instance.
	 *
	 * @return PPD_Conflict_Detector
	 */
	public function get_conflict_detector() {
		return $this->conflict_detector;
	}

	/**
	 * Get recommendation engine instance.
	 *
	 * @return PPD_Recommendation_Engine
	 */
	public function get_recommendation_engine() {
		return $this->recommendation_engine;
	}

	/**
	 * Get plugin inspector instance.
	 *
	 * @return PPD_Plugin_Inspector
	 */
	public function get_plugin_inspector() {
		return $this->plugin_inspector;
	}
}
