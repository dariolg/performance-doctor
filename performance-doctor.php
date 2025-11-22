<?php
/**
 * Plugin Name: Performance Doctor
 * Plugin URI: https://dariologiudice.it
 * Description: Analizza le prestazioni e la compatibilità di tutti i plugin attivi, identifica conflitti e fornisce raccomandazioni per l'ottimizzazione.
 * Version: 1.2.0
 * Author: Dario Lo Giudice
 * Author URI: https://dariologiudice.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: performance-doctor
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'PPD_VERSION', '1.2.0' );

/**
 * Plugin directory path.
 */
define( 'PPD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'PPD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'PPD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class_name The fully-qualified class name.
 */
function ppd_autoloader( $class_name ) {
	// Only autoload classes from this plugin.
	if ( strpos( $class_name, 'PPD_' ) !== 0 ) {
		return;
	}

	// Convert class name to file name.
	$class_file = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

	// Determine the directory based on class prefix.
	if ( strpos( $class_name, 'PPD_Admin' ) === 0 ) {
		$file_path = PPD_PLUGIN_DIR . 'admin/' . $class_file;
	} else {
		$file_path = PPD_PLUGIN_DIR . 'includes/' . $class_file;
	}

	// Include the file if it exists.
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}
spl_autoload_register( 'ppd_autoloader' );

/**
 * Initialize the plugin.
 */
function ppd_init() {
	// Initialize the main plugin class.
	$plugin = new PPD_Main();
	$plugin->init();
}
add_action( 'plugins_loaded', 'ppd_init' );

/**
 * Activation hook.
 * Currently no activation tasks needed as we don't store persistent data.
 */
function ppd_activate() {
	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
		wp_die(
			esc_html__( 'Performance Doctor richiede WordPress 5.8 o superiore.', 'performance-doctor' ),
			esc_html__( 'Errore di Attivazione Plugin', 'performance-doctor' ),
			array( 'back_link' => true )
		);
	}

	// Check PHP version.
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		wp_die(
			esc_html__( 'Performance Doctor richiede PHP 7.4 o superiore.', 'performance-doctor' ),
			esc_html__( 'Errore di Attivazione Plugin', 'performance-doctor' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'ppd_activate' );

/**
 * Deactivation hook.
 * Currently no deactivation tasks needed.
 */
function ppd_deactivate() {
	// Clean up any transients that might be cached.
	delete_transient( 'ppd_analysis_results' );
	delete_transient( 'ppd_last_analysis_time' );
}
register_deactivation_hook( __FILE__, 'ppd_deactivate' );
