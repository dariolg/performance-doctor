<?php
/**
 * Performance Analyzer class.
 *
 * Analyzes plugin performance by measuring execution time, database queries,
 * and memory usage for each active plugin.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Performance Analyzer class.
 */
class PPD_Performance_Analyzer {

	/**
	 * Plugin inspector instance.
	 *
	 * @var PPD_Plugin_Inspector
	 */
	private $inspector;

	/**
	 * Performance metrics for each plugin.
	 *
	 * @var array
	 */
	private $metrics = array();

	/**
	 * Hooks to monitor for performance analysis.
	 *
	 * @var array
	 */
	private $monitored_hooks = array(
		'plugins_loaded',
		'init',
		'wp_loaded',
		'admin_init',
		'admin_menu',
		'wp_enqueue_scripts',
		'admin_enqueue_scripts',
		'wp_head',
		'wp_footer',
		'admin_head',
		'admin_footer',
		'the_content',
		'the_title',
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
	 * Run performance analysis on all active plugins.
	 *
	 * @return array Performance metrics for each plugin.
	 */
	public function analyze() {
		global $wpdb;

		// Initialize metrics for all active plugins.
		$plugins = $this->inspector->get_active_plugins();
		foreach ( $plugins as $slug => $plugin_data ) {
			$this->metrics[ $slug ] = array(
				'name'           => $plugin_data['name'],
				'slug'           => $slug,
				'execution_time' => 0,
				'db_queries'     => 0,
				'memory_usage'   => 0,
				'hook_count'     => 0,
				'hooks_detail'   => array(),
			);
		}

		// Analyze hooks.
		$this->analyze_hooks();

		// Calculate load levels.
		$this->calculate_load_levels();

		return $this->metrics;
	}

	/**
	 * Analyze hooks and measure performance.
	 */
	private function analyze_hooks() {
		global $wp_filter;

		foreach ( $this->monitored_hooks as $hook_name ) {
			if ( ! isset( $wp_filter[ $hook_name ] ) ) {
				continue;
			}

			$hook = $wp_filter[ $hook_name ];

			// Iterate through all priorities.
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_data ) {
					$callback = $callback_data['function'];
					$plugin_slug = $this->inspector->get_plugin_from_callback( $callback );

					if ( ! $plugin_slug || ! isset( $this->metrics[ $plugin_slug ] ) ) {
						continue;
					}

					// Estimate performance impact.
					$impact = $this->estimate_callback_impact( $callback, $hook_name );

					// Update metrics.
					$this->metrics[ $plugin_slug ]['execution_time'] += $impact['time'];
					$this->metrics[ $plugin_slug ]['db_queries']     += $impact['queries'];
					$this->metrics[ $plugin_slug ]['memory_usage']   += $impact['memory'];
					$this->metrics[ $plugin_slug ]['hook_count']++;

					// Store hook details.
					if ( ! isset( $this->metrics[ $plugin_slug ]['hooks_detail'][ $hook_name ] ) ) {
						$this->metrics[ $plugin_slug ]['hooks_detail'][ $hook_name ] = array(
							'count'     => 0,
							'time'      => 0,
							'queries'   => 0,
							'memory'    => 0,
							'priority'  => array(),
						);
					}

					$this->metrics[ $plugin_slug ]['hooks_detail'][ $hook_name ]['count']++;
					$this->metrics[ $plugin_slug ]['hooks_detail'][ $hook_name ]['time']    += $impact['time'];
					$this->metrics[ $plugin_slug ]['hooks_detail'][ $hook_name ]['queries'] += $impact['queries'];
					$this->metrics[ $plugin_slug ]['hooks_detail'][ $hook_name ]['memory']  += $impact['memory'];
					$this->metrics[ $plugin_slug ]['hooks_detail'][ $hook_name ]['priority'][] = $priority;
				}
			}
		}
	}

	/**
	 * Estimate the performance impact of a callback.
	 *
	 * This is an approximation since we can't actually execute the callback
	 * in isolation without side effects.
	 *
	 * @param callable $callback The callback to estimate.
	 * @param string   $hook_name The hook name.
	 * @return array Impact metrics (time, queries, memory).
	 */
	private function estimate_callback_impact( $callback, $hook_name ) {
		global $wpdb;

		$impact = array(
			'time'    => 0,
			'queries' => 0,
			'memory'  => 0,
		);

		// Start measurements.
		$start_time    = microtime( true );
		$start_queries = $wpdb->num_queries;
		$start_memory  = memory_get_usage();

		try {
			// We can't actually call the callback as it might have side effects.
			// Instead, we'll use reflection to estimate complexity.
			$complexity = $this->estimate_callback_complexity( $callback );

			// Base estimation on complexity.
			// These are rough estimates based on typical plugin behavior.
			$impact['time']    = $complexity['lines'] * 0.00001; // ~0.01ms per line of code.
			$impact['queries'] = $complexity['db_calls'];
			$impact['memory']  = $complexity['lines'] * 100; // ~100 bytes per line.

			// Adjust based on hook type.
			$hook_multipliers = array(
				'init'                  => 1.5,
				'plugins_loaded'        => 1.2,
				'admin_init'            => 1.3,
				'wp_enqueue_scripts'    => 1.1,
				'admin_enqueue_scripts' => 1.1,
				'the_content'           => 2.0, // Content filters can be expensive.
				'the_title'             => 1.5,
			);

			$multiplier = isset( $hook_multipliers[ $hook_name ] ) ? $hook_multipliers[ $hook_name ] : 1.0;
			$impact['time'] *= $multiplier;

		} catch ( Exception $e ) {
			// If estimation fails, use minimal values.
			$impact['time']    = 0.0001;
			$impact['queries'] = 0;
			$impact['memory']  = 1024;
		}

		return $impact;
	}

	/**
	 * Estimate callback complexity using reflection.
	 *
	 * @param callable $callback The callback to analyze.
	 * @return array Complexity metrics.
	 */
	private function estimate_callback_complexity( $callback ) {
		$complexity = array(
			'lines'    => 10, // Default assumption.
			'db_calls' => 0,
		);

		try {
			$reflection = null;

			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$reflection = new ReflectionFunction( $callback );
			} elseif ( is_array( $callback ) && count( $callback ) === 2 ) {
				$class  = $callback[0];
				$method = $callback[1];

				if ( is_object( $class ) ) {
					$reflection = new ReflectionMethod( $class, $method );
				} elseif ( is_string( $class ) && class_exists( $class ) ) {
					$reflection = new ReflectionMethod( $class, $method );
				}
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$reflection = new ReflectionMethod( $callback, '__invoke' );
			}

			if ( $reflection ) {
				$start_line = $reflection->getStartLine();
				$end_line   = $reflection->getEndLine();
				$lines      = $end_line - $start_line + 1;

				$complexity['lines'] = max( 1, $lines );

				// Try to detect database calls in the source code.
				$source = $this->get_function_source( $reflection );
				if ( $source ) {
					// Count potential database operations.
					$db_patterns = array(
						'/\$wpdb->/',
						'/->query\(/',
						'/->get_results\(/',
						'/->get_row\(/',
						'/->get_var\(/',
						'/get_posts\(/',
						'/get_post\(/',
						'/wp_query/i',
						'/WP_Query/i',
					);

					foreach ( $db_patterns as $pattern ) {
						$complexity['db_calls'] += preg_match_all( $pattern, $source );
					}
				}
			}
		} catch ( ReflectionException $e ) {
			// Use defaults if reflection fails.
		}

		return $complexity;
	}

	/**
	 * Get the source code of a function/method.
	 *
	 * @param ReflectionFunctionAbstract $reflection Reflection object.
	 * @return string|null Source code or null if unavailable.
	 */
	private function get_function_source( $reflection ) {
		try {
			$filename  = $reflection->getFileName();
			$start_line = $reflection->getStartLine();
			$end_line   = $reflection->getEndLine();

			if ( ! $filename || ! file_exists( $filename ) ) {
				return null;
			}

			$file_content = file( $filename );
			$source_lines = array_slice( $file_content, $start_line - 1, $end_line - $start_line + 1 );

			return implode( '', $source_lines );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Calculate load levels for each plugin.
	 */
	private function calculate_load_levels() {
		// Find max values for normalization.
		$max_time    = 0;
		$max_queries = 0;
		$max_memory  = 0;

		foreach ( $this->metrics as $metrics ) {
			$max_time    = max( $max_time, $metrics['execution_time'] );
			$max_queries = max( $max_queries, $metrics['db_queries'] );
			$max_memory  = max( $max_memory, $metrics['memory_usage'] );
		}

		// Calculate load level for each plugin.
		foreach ( $this->metrics as $slug => &$metrics ) {
			$time_score    = $max_time > 0 ? ( $metrics['execution_time'] / $max_time ) : 0;
			$queries_score = $max_queries > 0 ? ( $metrics['db_queries'] / $max_queries ) : 0;
			$memory_score  = $max_memory > 0 ? ( $metrics['memory_usage'] / $max_memory ) : 0;

			// Weighted average (time is most important).
			$load_score = ( $time_score * 0.5 ) + ( $queries_score * 0.3 ) + ( $memory_score * 0.2 );

			// Determine load level.
			if ( $load_score > 0.7 ) {
				$metrics['load_level'] = 'high';
			} elseif ( $load_score > 0.4 ) {
				$metrics['load_level'] = 'medium';
			} else {
				$metrics['load_level'] = 'low';
			}

			$metrics['load_score'] = round( $load_score * 100, 2 );
		}
	}

	/**
	 * Get performance metrics.
	 *
	 * @return array Performance metrics.
	 */
	public function get_metrics() {
		return $this->metrics;
	}

	/**
	 * Get plugins with high load.
	 *
	 * @return array Array of plugin slugs with high load.
	 */
	public function get_high_load_plugins() {
		$high_load = array();

		foreach ( $this->metrics as $slug => $metrics ) {
			if ( isset( $metrics['load_level'] ) && 'high' === $metrics['load_level'] ) {
				$high_load[] = $slug;
			}
		}

		return $high_load;
	}
}
