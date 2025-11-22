<?php
/**
 * Plugin Inspector utility class.
 *
 * Provides helper methods for plugin introspection and metadata extraction.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin Inspector class.
 */
class PPD_Plugin_Inspector {

	/**
	 * Cache for plugin data.
	 *
	 * @var array
	 */
	private $plugin_cache = array();

	/**
	 * Get all active plugins with their metadata.
	 *
	 * @return array Array of active plugins with metadata.
	 */
	public function get_active_plugins() {
		if ( ! empty( $this->plugin_cache ) ) {
			return $this->plugin_cache;
		}

		$active_plugins = get_option( 'active_plugins', array() );
		$plugins_data   = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

			if ( ! file_exists( $plugin_path ) ) {
				continue;
			}

			$plugin_data = get_plugin_data( $plugin_path, false, false );
			$plugin_slug = $this->get_plugin_slug( $plugin_file );

			$plugins_data[ $plugin_slug ] = array(
				'file'        => $plugin_file,
				'path'        => $plugin_path,
				'slug'        => $plugin_slug,
				'name'        => $plugin_data['Name'],
				'version'     => $plugin_data['Version'],
				'author'      => $plugin_data['Author'],
				'description' => $plugin_data['Description'],
				'dir'         => dirname( $plugin_path ),
			);
		}

		$this->plugin_cache = $plugins_data;
		return $plugins_data;
	}

	/**
	 * Get plugin slug from plugin file path.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return string Plugin slug.
	 */
	public function get_plugin_slug( $plugin_file ) {
		$parts = explode( '/', $plugin_file );
		return isset( $parts[0] ) ? $parts[0] : basename( $plugin_file, '.php' );
	}

	/**
	 * Get plugin slug from a file path.
	 *
	 * @param string $file_path Full file path.
	 * @return string|null Plugin slug or null if not found.
	 */
	public function get_plugin_from_file( $file_path ) {
		$file_path = wp_normalize_path( $file_path );
		$plugins   = $this->get_active_plugins();

		foreach ( $plugins as $slug => $plugin_data ) {
			$plugin_dir = wp_normalize_path( $plugin_data['dir'] );
			if ( strpos( $file_path, $plugin_dir ) === 0 ) {
				return $slug;
			}
		}

		return null;
	}

	/**
	 * Get plugin slug from a callback.
	 *
	 * @param callable $callback The callback to inspect.
	 * @return string|null Plugin slug or null if not found.
	 */
	public function get_plugin_from_callback( $callback ) {
		try {
			if ( is_string( $callback ) ) {
				// Function name.
				if ( function_exists( $callback ) ) {
					$reflection = new ReflectionFunction( $callback );
					$file_path  = $reflection->getFileName();
					return $this->get_plugin_from_file( $file_path );
				}
			} elseif ( is_array( $callback ) && count( $callback ) === 2 ) {
				// Class method.
				$class  = $callback[0];
				$method = $callback[1];

				if ( is_object( $class ) ) {
					$reflection = new ReflectionClass( $class );
				} elseif ( is_string( $class ) && class_exists( $class ) ) {
					$reflection = new ReflectionClass( $class );
				} else {
					return null;
				}

				$file_path = $reflection->getFileName();
				return $this->get_plugin_from_file( $file_path );
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				// Closure or invokable object.
				$reflection = new ReflectionObject( $callback );
				$file_path  = $reflection->getFileName();
				return $this->get_plugin_from_file( $file_path );
			}
		} catch ( ReflectionException $e ) {
			// If reflection fails, return null.
			return null;
		}

		return null;
	}

	/**
	 * Check if a file belongs to WordPress core.
	 *
	 * @param string $file_path File path to check.
	 * @return bool True if file is part of WordPress core.
	 */
	public function is_wordpress_core( $file_path ) {
		$file_path      = wp_normalize_path( $file_path );
		$wp_includes    = wp_normalize_path( ABSPATH . WPINC );
		$wp_admin       = wp_normalize_path( ABSPATH . 'wp-admin' );
		$wp_content     = wp_normalize_path( WP_CONTENT_DIR );

		// Check if file is in wp-includes or wp-admin but not in wp-content.
		if ( strpos( $file_path, $wp_includes ) === 0 || strpos( $file_path, $wp_admin ) === 0 ) {
			return true;
		}

		// Make sure it's not in wp-content (plugins, themes, etc.).
		if ( strpos( $file_path, $wp_content ) === 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get plugin metadata by slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return array|null Plugin metadata or null if not found.
	 */
	public function get_plugin_data( $slug ) {
		$plugins = $this->get_active_plugins();
		return isset( $plugins[ $slug ] ) ? $plugins[ $slug ] : null;
	}

	/**
	 * Clear the plugin cache.
	 */
	public function clear_cache() {
		$this->plugin_cache = array();
	}
}
