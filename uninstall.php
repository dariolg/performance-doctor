<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file ensures that all plugin data is completely removed from the database
 * when the plugin is deleted, leaving zero footprint.
 *
 * @package PerformanceDoctor
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin options from the database.
 */
function ppd_uninstall_cleanup() {
	global $wpdb;

	// Delete all options with our prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'ppd_' ) . '%'
		)
	);

	// Delete all transients with our prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_ppd_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ppd_' ) . '%'
		)
	);

	// Delete site transients (for multisite).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_site_transient_ppd_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_ppd_' ) . '%'
		)
	);

	// For multisite, clean up options from all sites.
	if ( is_multisite() ) {
		$sites = get_sites(
			array(
				'number' => 0,
				'fields' => 'ids',
			)
		);

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			// Delete options for this site.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( 'ppd_' ) . '%'
				)
			);

			// Delete transients for this site.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_ppd_' ) . '%',
					$wpdb->esc_like( '_transient_timeout_ppd_' ) . '%'
				)
			);

			restore_current_blog();
		}
	}

	// Clear any cached data.
	wp_cache_flush();
}

// Execute cleanup.
ppd_uninstall_cleanup();
