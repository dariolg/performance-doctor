<?php
/**
 * Performance Scorer class.
 *
 * Calculates performance score for the WordPress site.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Performance Scorer class.
 */
class PPD_Performance_Scorer {

	/**
	 * Calculate overall performance score.
	 *
	 * @param array $performance_metrics Performance metrics from analyzer.
	 * @param array $conflicts Conflicts from detector.
	 * @return array Score data.
	 */
	public function calculate_score( $performance_metrics, $conflicts ) {
		$metrics = array();

		// Calculate individual metric scores.
		$metrics['plugin_load']    = $this->score_plugin_load( $performance_metrics );
		$metrics['conflicts']      = $this->score_conflicts( $conflicts );
		$metrics['database']       = $this->score_database( $performance_metrics );
		$metrics['memory']         = $this->score_memory( $performance_metrics );
		$metrics['hooks']          = $this->score_hooks( $performance_metrics );

		// Calculate weighted overall score.
		$weights = array(
			'plugin_load' => 0.30,
			'conflicts'   => 0.25,
			'database'    => 0.20,
			'memory'      => 0.15,
			'hooks'       => 0.10,
		);

		$overall_score = 0;
		foreach ( $metrics as $key => $metric ) {
			$overall_score += $metric['score'] * $weights[ $key ];
		}

		$overall_score = round( $overall_score );

		return array(
			'overall_score' => $overall_score,
			'grade'         => $this->get_grade( $overall_score ),
			'metrics'       => $metrics,
			'timestamp'     => current_time( 'mysql' ),
		);
	}

	/**
	 * Score plugin load performance.
	 *
	 * @param array $performance_metrics Performance metrics.
	 * @return array Score data.
	 */
	private function score_plugin_load( $performance_metrics ) {
		$high_load_count   = 0;
		$medium_load_count = 0;
		$total_plugins     = count( $performance_metrics );

		foreach ( $performance_metrics as $metrics ) {
			if ( isset( $metrics['load_level'] ) ) {
				if ( $metrics['load_level'] === 'high' ) {
					$high_load_count++;
				} elseif ( $metrics['load_level'] === 'medium' ) {
					$medium_load_count++;
				}
			}
		}

		// Calculate score (100 = no high load plugins).
		$score = 100;
		if ( $total_plugins > 0 ) {
			$score -= ( $high_load_count * 20 );
			$score -= ( $medium_load_count * 10 );
			$score = max( 0, $score );
		}

		return array(
			'score'       => $score,
			'label'       => __( 'Carico Plugin', 'performance-doctor' ),
			'value'       => sprintf(
				/* translators: 1: high load count, 2: total plugins */
				__( '%1$d plugin con carico elevato su %2$d totali', 'performance-doctor' ),
				$high_load_count,
				$total_plugins
			),
			'description' => __( 'Misura il carico complessivo dei plugin attivi', 'performance-doctor' ),
		);
	}

	/**
	 * Score conflicts.
	 *
	 * @param array $conflicts Conflicts.
	 * @return array Score data.
	 */
	private function score_conflicts( $conflicts ) {
		$high_severity_count   = 0;
		$medium_severity_count = 0;
		$total_conflicts       = count( $conflicts );

		foreach ( $conflicts as $conflict ) {
			if ( isset( $conflict['severity'] ) ) {
				if ( $conflict['severity'] === 'high' ) {
					$high_severity_count++;
				} elseif ( $conflict['severity'] === 'medium' ) {
					$medium_severity_count++;
				}
			}
		}

		// Calculate score (100 = no conflicts).
		$score = 100;
		$score -= ( $high_severity_count * 25 );
		$score -= ( $medium_severity_count * 10 );
		$score = max( 0, $score );

		return array(
			'score'       => $score,
			'label'       => __( 'Conflitti', 'performance-doctor' ),
			'value'       => sprintf(
				/* translators: %d: number of conflicts */
				_n( '%d conflitto rilevato', '%d conflitti rilevati', $total_conflicts, 'performance-doctor' ),
				$total_conflicts
			),
			'description' => __( 'Numero e gravità dei conflitti tra plugin', 'performance-doctor' ),
		);
	}

	/**
	 * Score database performance.
	 *
	 * @param array $performance_metrics Performance metrics.
	 * @return array Score data.
	 */
	private function score_database( $performance_metrics ) {
		$total_queries = 0;
		$plugin_count  = count( $performance_metrics );

		foreach ( $performance_metrics as $metrics ) {
			if ( isset( $metrics['db_queries'] ) ) {
				$total_queries += $metrics['db_queries'];
			}
		}

		// Calculate score based on average queries per plugin.
		$avg_queries = $plugin_count > 0 ? $total_queries / $plugin_count : 0;
		$score       = 100;

		if ( $avg_queries > 20 ) {
			$score = 40;
		} elseif ( $avg_queries > 10 ) {
			$score = 60;
		} elseif ( $avg_queries > 5 ) {
			$score = 80;
		}

		return array(
			'score'       => $score,
			'label'       => __( 'Query Database', 'performance-doctor' ),
			'value'       => sprintf(
				/* translators: %d: number of queries */
				__( '%d query totali', 'performance-doctor' ),
				$total_queries
			),
			'description' => __( 'Numero di query al database eseguite dai plugin', 'performance-doctor' ),
		);
	}

	/**
	 * Score memory usage.
	 *
	 * @param array $performance_metrics Performance metrics.
	 * @return array Score data.
	 */
	private function score_memory( $performance_metrics ) {
		$total_memory = 0;

		foreach ( $performance_metrics as $metrics ) {
			if ( isset( $metrics['memory_usage'] ) ) {
				$total_memory += $metrics['memory_usage'];
			}
		}

		// Calculate score based on total memory (in MB).
		$memory_mb = $total_memory / 1024 / 1024;
		$score     = 100;

		if ( $memory_mb > 100 ) {
			$score = 40;
		} elseif ( $memory_mb > 50 ) {
			$score = 60;
		} elseif ( $memory_mb > 25 ) {
			$score = 80;
		}

		return array(
			'score'       => $score,
			'label'       => __( 'Uso Memoria', 'performance-doctor' ),
			'value'       => size_format( $total_memory, 2 ),
			'description' => __( 'Memoria totale utilizzata dai plugin', 'performance-doctor' ),
		);
	}

	/**
	 * Score hooks usage.
	 *
	 * @param array $performance_metrics Performance metrics.
	 * @return array Score data.
	 */
	private function score_hooks( $performance_metrics ) {
		$total_hooks  = 0;
		$plugin_count = count( $performance_metrics );

		foreach ( $performance_metrics as $metrics ) {
			if ( isset( $metrics['hook_count'] ) ) {
				$total_hooks += $metrics['hook_count'];
			}
		}

		// Calculate score based on average hooks per plugin.
		$avg_hooks = $plugin_count > 0 ? $total_hooks / $plugin_count : 0;
		$score     = 100;

		if ( $avg_hooks > 30 ) {
			$score = 60;
		} elseif ( $avg_hooks > 20 ) {
			$score = 80;
		}

		return array(
			'score'       => $score,
			'label'       => __( 'Hook WordPress', 'performance-doctor' ),
			'value'       => sprintf(
				/* translators: %d: number of hooks */
				__( '%d hook totali', 'performance-doctor' ),
				$total_hooks
			),
			'description' => __( 'Numero di hook WordPress utilizzati dai plugin', 'performance-doctor' ),
		);
	}

	/**
	 * Get grade from score.
	 *
	 * @param int $score Score (0-100).
	 * @return string Grade (A-F).
	 */
	private function get_grade( $score ) {
		if ( $score >= 90 ) {
			return 'A';
		} elseif ( $score >= 80 ) {
			return 'B';
		} elseif ( $score >= 70 ) {
			return 'C';
		} elseif ( $score >= 60 ) {
			return 'D';
		} else {
			return 'F';
		}
	}

	/**
	 * Save score to history.
	 *
	 * @param array $score_data Score data.
	 * @return bool Success.
	 */
	public function save_to_history( $score_data ) {
		$history = get_option( 'ppd_performance_history', array() );

		$history[] = $score_data;

		// Keep only last 30 records.
		if ( count( $history ) > 30 ) {
			$history = array_slice( $history, -30 );
		}

		return update_option( 'ppd_performance_history', $history );
	}

	/**
	 * Get performance history.
	 *
	 * @param int $limit Number of records to return.
	 * @return array History records.
	 */
	public function get_history( $limit = 10 ) {
		$history = get_option( 'ppd_performance_history', array() );

		if ( $limit > 0 ) {
			$history = array_slice( $history, -$limit );
		}

		return $history;
	}

	/**
	 * Get score trend.
	 *
	 * @return array Trend data.
	 */
	public function get_trend() {
		$history = $this->get_history( 5 );

		if ( count( $history ) < 2 ) {
			return array(
				'direction' => 'stable',
				'change'    => 0,
			);
		}

		$latest   = end( $history );
		$previous = prev( $history );

		$change    = $latest['overall_score'] - $previous['overall_score'];
		$direction = 'stable';

		if ( $change > 5 ) {
			$direction = 'improving';
		} elseif ( $change < -5 ) {
			$direction = 'declining';
		}

		return array(
			'direction' => $direction,
			'change'    => $change,
		);
	}
}
