<?php
/**
 * Conflict Detector class.
 *
 * Detects potential conflicts between active plugins including hook conflicts,
 * script/style conflicts, duplicate functionality, and PHP errors.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Conflict Detector class.
 */
class PPD_Conflict_Detector {

	/**
	 * Plugin inspector instance.
	 *
	 * @var PPD_Plugin_Inspector
	 */
	private $inspector;

	/**
	 * Detected conflicts.
	 *
	 * @var array
	 */
	private $conflicts = array();

	/**
	 * Known plugin categories for duplicate detection.
	 *
	 * @var array
	 */
	private $plugin_categories = array(
		'seo'      => array( 'yoast', 'seo', 'all-in-one-seo', 'rank-math', 'seopress' ),
		'cache'    => array( 'cache', 'w3-total-cache', 'wp-super-cache', 'wp-rocket', 'litespeed' ),
		'security' => array( 'security', 'wordfence', 'sucuri', 'ithemes-security', 'all-in-one-wp-security' ),
		'backup'   => array( 'backup', 'updraftplus', 'backupbuddy', 'duplicator', 'backwpup' ),
		'forms'    => array( 'contact-form', 'gravity-forms', 'wpforms', 'ninja-forms', 'formidable' ),
		'slider'   => array( 'slider', 'revolution-slider', 'layer-slider', 'meta-slider' ),
	);

	/**
	 * Constructor.
	 *
	 * @param PPD_Plugin_Inspector $inspector Plugin inspector instance.
	 */
	public function __construct( PPD_Plugin_Inspector $inspector ) {
		$this->inspector = $inspector;
	}

	/**
	 * Detect conflicts between active plugins.
	 *
	 * @return array Array of detected conflicts.
	 */
	public function detect_conflicts() {
		$this->conflicts = array();

		// Detect different types of conflicts.
		$this->detect_hook_conflicts();
		$this->detect_script_conflicts();
		$this->detect_duplicate_functionality();
		$this->detect_php_errors();

		return $this->conflicts;
	}

	/**
	 * Detect hook priority conflicts.
	 */
	private function detect_hook_conflicts() {
		global $wp_filter;

		$critical_hooks = array(
			'init',
			'wp_head',
			'wp_footer',
			'the_content',
			'the_title',
			'wp_enqueue_scripts',
		);

		foreach ( $critical_hooks as $hook_name ) {
			if ( ! isset( $wp_filter[ $hook_name ] ) ) {
				continue;
			}

			$hook = $wp_filter[ $hook_name ];

			// Check for multiple plugins using the same priority.
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				if ( count( $callbacks ) < 2 ) {
					continue;
				}

				$plugins_at_priority = array();

				foreach ( $callbacks as $callback_data ) {
					$callback    = $callback_data['function'];
					$plugin_slug = $this->inspector->get_plugin_from_callback( $callback );

					if ( $plugin_slug ) {
						$plugins_at_priority[] = $plugin_slug;
					}
				}

				// If multiple plugins use the same priority, it might be a conflict.
				$unique_plugins = array_unique( $plugins_at_priority );
				if ( count( $unique_plugins ) > 1 ) {
					$this->add_conflict(
						'hook_priority',
						$unique_plugins,
						sprintf(
							/* translators: 1: hook name, 2: priority number */
							__( 'Più plugin stanno utilizzando la stessa priorità (%2$d) sull\'hook "%1$s", il che potrebbe causare comportamenti imprevisti.', 'performance-doctor' ),
							$hook_name,
							$priority
						),
						array(
							'hook'     => $hook_name,
							'priority' => $priority,
						)
					);
				}
			}

			// Check for very high or very low priorities (potential override attempts).
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				if ( abs( $priority ) > 1000 ) {
					foreach ( $callbacks as $callback_data ) {
						$callback    = $callback_data['function'];
						$plugin_slug = $this->inspector->get_plugin_from_callback( $callback );

						if ( $plugin_slug ) {
							$this->add_conflict(
								'extreme_priority',
								array( $plugin_slug ),
								sprintf(
									/* translators: 1: plugin slug, 2: hook name, 3: priority number */
									__( 'Il plugin "%1$s" sta utilizzando una priorità estrema (%3$d) sull\'hook "%2$s", il che potrebbe sovrascrivere altri plugin.', 'performance-doctor' ),
									$plugin_slug,
									$hook_name,
									$priority
								),
								array(
									'hook'     => $hook_name,
									'priority' => $priority,
								)
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Detect script and style conflicts.
	 */
	/**
	 * Detect script and style conflicts.
	 */
	private function detect_script_conflicts() {
		global $wp_scripts, $wp_styles;

		// This is a simplified check - in a real scenario, we'd need to track
		// dequeue operations during the enqueue process.
		
		// Check for jQuery conflicts (common issue).
		if ( isset( $wp_scripts->registered['jquery'] ) ) {
			$jquery_scripts = array();
			$main_jquery = $wp_scripts->registered['jquery'];
			
			// Collect all jQuery-related scripts.
			foreach ( $wp_scripts->registered as $handle => $script ) {
				if ( isset( $script->src ) && ( $handle === 'jquery' || strpos( $script->src, 'jquery' ) !== false ) ) {
					// Try to extract version from src or ver property.
					$version = isset( $script->ver ) ? $script->ver : 'unknown';
					
					// Try to determine which plugin registered this.
					$plugin_slug = '';
					if ( isset( $script->src ) ) {
						// Extract plugin name from path.
						if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $script->src, $matches ) ) {
							$plugin_slug = $matches[1];
						}
					}
					
					$jquery_scripts[] = array(
						'handle'  => $handle,
						'src'     => isset( $script->src ) ? $script->src : '',
						'version' => $version,
						'plugin'  => $plugin_slug,
					);
				}
			}
			
			// Only report if there are multiple jQuery versions.
			if ( count( $jquery_scripts ) > 1 ) {
				// Group by version to show which versions are loaded.
				$versions = array();
				$plugins = array();
				
				foreach ( $jquery_scripts as $jquery_script ) {
					if ( ! empty( $jquery_script['version'] ) ) {
						$versions[ $jquery_script['version'] ] = true;
					}
					if ( ! empty( $jquery_script['plugin'] ) ) {
						$plugins[] = $jquery_script['plugin'];
					}
				}
				
				$plugins = array_unique( $plugins );
				
				// Create detailed description.
				$description = sprintf(
					/* translators: 1: number of jQuery scripts, 2: number of versions */
					_n(
						'Rilevati %1$d script jQuery con %2$d versione diversa.',
						'Rilevati %1$d script jQuery con %2$d versioni diverse.',
						count( $versions ),
						'performance-doctor'
					),
					count( $jquery_scripts ),
					count( $versions )
				);
				
				if ( ! empty( $plugins ) ) {
					$description .= ' ' . sprintf(
						/* translators: %s: list of plugin names */
						__( 'Plugin coinvolti: %s', 'performance-doctor' ),
						implode( ', ', $plugins )
					);
				}
				
				$this->add_conflict(
					'jquery_conflict',
					$plugins,
					$description,
					array(
						'jquery_scripts' => $jquery_scripts,
						'versions'       => array_keys( $versions ),
					)
				);
			}
		}

		// Check for duplicate script handles (potential conflicts).
		$script_sources = array();
		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( ! empty( $script->src ) ) {
				$src = $script->src;
				// Normalize src (remove query strings).
				if ( strpos( $src, '?' ) !== false ) {
					$src = explode( '?', $src )[0];
				}
				
				if ( ! isset( $script_sources[ $src ] ) ) {
					$script_sources[ $src ] = array();
				}
				$script_sources[ $src ][] = $handle;
			}
		}

		foreach ( $script_sources as $src => $handles ) {
			if ( count( $handles ) > 1 ) {
				$this->add_conflict(
					'duplicate_script',
					array(),
					sprintf(
						/* translators: 1: script source, 2: number of handles */
						__( 'Lo script "%1$s" è registrato %2$d volte con handle diversi (%3$s).', 'performance-doctor' ),
						basename( $src ),
						count( $handles ),
						implode( ', ', $handles )
					),
					array(
						'src'     => $src,
						'handles' => $handles,
					)
				);
			}
		}
	}

	/**
	 * Detect duplicate functionality.
	 */
	private function detect_duplicate_functionality() {
		$plugins        = $this->inspector->get_active_plugins();
		$plugins_by_category = array();

		// Categorize plugins.
		foreach ( $plugins as $slug => $plugin_data ) {
			$plugin_name_lower = strtolower( $plugin_data['name'] );
			$plugin_slug_lower = strtolower( $slug );

			foreach ( $this->plugin_categories as $category => $keywords ) {
				foreach ( $keywords as $keyword ) {
					if ( strpos( $plugin_name_lower, $keyword ) !== false || strpos( $plugin_slug_lower, $keyword ) !== false ) {
						if ( ! isset( $plugins_by_category[ $category ] ) ) {
							$plugins_by_category[ $category ] = array();
						}
						$plugins_by_category[ $category ][] = $slug;
						break;
					}
				}
			}
		}

		// Check for duplicates in each category.
		foreach ( $plugins_by_category as $category => $plugin_slugs ) {
			if ( count( $plugin_slugs ) > 1 ) {
				$category_names = array(
					'seo'      => __( 'SEO', 'performance-doctor' ),
					'cache'    => __( 'Cache', 'performance-doctor' ),
					'security' => __( 'Sicurezza', 'performance-doctor' ),
					'backup'   => __( 'Backup', 'performance-doctor' ),
					'forms'    => __( 'Moduli', 'performance-doctor' ),
					'slider'   => __( 'Slider', 'performance-doctor' ),
				);

				$this->add_conflict(
					'duplicate_functionality',
					$plugin_slugs,
					sprintf(
						/* translators: 1: category name */
						__( 'Sono attivi più plugin di tipo %1$s. Questo può causare conflitti e problemi di prestazioni. Mantieni attivo solo uno.', 'performance-doctor' ),
						isset( $category_names[ $category ] ) ? $category_names[ $category ] : $category
					),
					array(
						'category' => $category,
					)
				);
			}
		}
	}

	/**
	 * Detect PHP errors related to plugins.
	 *
	 * This is a simplified implementation. In a production environment,
	 * you'd want to integrate with error logging systems.
	 */
	private function detect_php_errors() {
		// Check if error log exists and is readable.
		$error_log = ini_get( 'error_log' );

		if ( ! $error_log || ! file_exists( $error_log ) || ! is_readable( $error_log ) ) {
			// Try WordPress debug log.
			$error_log = WP_CONTENT_DIR . '/debug.log';
		}

		if ( file_exists( $error_log ) && is_readable( $error_log ) ) {
			// Read last 1000 lines of error log.
			$lines = $this->tail_file( $error_log, 1000 );

			if ( $lines ) {
				$plugins = $this->inspector->get_active_plugins();

				foreach ( $plugins as $slug => $plugin_data ) {
					$plugin_dir = basename( $plugin_data['dir'] );
					$error_count = 0;

					foreach ( $lines as $line ) {
						// Check if error mentions this plugin.
						if ( stripos( $line, $plugin_dir ) !== false || stripos( $line, $slug ) !== false ) {
							$error_count++;
						}
					}

					if ( $error_count > 0 ) {
						$this->add_conflict(
							'php_error',
							array( $slug ),
							sprintf(
								/* translators: 1: error count */
								_n(
									'%d errore PHP trovato nel log degli errori relativo a questo plugin.',
									'%d errori PHP trovati nel log degli errori relativi a questo plugin.',
									$error_count,
									'performance-doctor'
								),
								$error_count
							),
							array(
								'error_count' => $error_count,
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Read last N lines from a file.
	 *
	 * @param string $file File path.
	 * @param int    $lines Number of lines to read.
	 * @return array|false Array of lines or false on failure.
	 */
	private function tail_file( $file, $lines = 100 ) {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem || ! $wp_filesystem->exists( $file ) ) {
			return false;
		}

		// Read file content.
		$content = $wp_filesystem->get_contents( $file );
		if ( false === $content ) {
			return false;
		}

		// Split into lines.
		$file_lines = explode( "\n", $content );
		
		// Get last N lines.
		return array_slice( $file_lines, -$lines );
	}

	/**
	 * Add a conflict to the list.
	 *
	 * @param string $type Conflict type.
	 * @param array  $plugins Involved plugin slugs.
	 * @param string $description Conflict description.
	 * @param array  $details Additional details.
	 */
	private function add_conflict( $type, $plugins, $description, $details = array() ) {
		// Create a unique key to avoid duplicates.
		$plugins_sorted = $plugins;
		sort( $plugins_sorted );
		$conflict_key = $type . '_' . md5( implode( '_', $plugins_sorted ) . '_' . serialize( $details ) );

		// Check if this conflict already exists.
		foreach ( $this->conflicts as $existing_conflict ) {
			if ( isset( $existing_conflict['_key'] ) && $existing_conflict['_key'] === $conflict_key ) {
				return; // Skip duplicate.
			}
		}

		// Add file paths for involved plugins.
		$plugin_paths = array();
		foreach ( $plugins as $plugin_slug ) {
			$plugin_data = $this->inspector->get_plugin_data( $plugin_slug );
			if ( $plugin_data ) {
				$plugin_paths[ $plugin_slug ] = array(
					'name' => $plugin_data['name'],
					'file' => str_replace( WP_PLUGIN_DIR . '/', '', $plugin_data['path'] ),
					'dir'  => str_replace( WP_PLUGIN_DIR . '/', '', $plugin_data['dir'] ),
				);
			}
		}

		$this->conflicts[] = array(
			'_key'        => $conflict_key,
			'type'        => $type,
			'plugins'     => $plugins,
			'plugin_paths' => $plugin_paths,
			'description' => $description,
			'details'     => $details,
			'severity'    => $this->get_conflict_severity( $type ),
		);
	}

	/**
	 * Get conflict severity level.
	 *
	 * @param string $type Conflict type.
	 * @return string Severity level (high, medium, low).
	 */
	private function get_conflict_severity( $type ) {
		$severity_map = array(
			'php_error'               => 'high',
			'duplicate_functionality' => 'high',
			'jquery_conflict'         => 'high',
			'extreme_priority'        => 'medium',
			'hook_priority'           => 'medium',
			'duplicate_script'        => 'low',
		);

		return isset( $severity_map[ $type ] ) ? $severity_map[ $type ] : 'low';
	}

	/**
	 * Get detected conflicts.
	 *
	 * @return array Array of conflicts.
	 */
	public function get_conflicts() {
		return $this->conflicts;
	}

	/**
	 * Get conflicts by severity.
	 *
	 * @param string $severity Severity level.
	 * @return array Array of conflicts.
	 */
	public function get_conflicts_by_severity( $severity ) {
		return array_filter(
			$this->conflicts,
			function ( $conflict ) use ( $severity ) {
				return $conflict['severity'] === $severity;
			}
		);
	}
}
