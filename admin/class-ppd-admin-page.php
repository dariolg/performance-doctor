<?php
/**
 * Admin Page class.
 *
 * Handles the admin interface for the plugin.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin Page class.
 */
class PPD_Admin_Page {

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
	 * Constructor.
	 *
	 * @param PPD_Performance_Analyzer  $performance_analyzer Performance analyzer instance.
	 * @param PPD_Conflict_Detector     $conflict_detector Conflict detector instance.
	 * @param PPD_Recommendation_Engine $recommendation_engine Recommendation engine instance.
	 * @param PPD_Backup_Manager        $backup_manager Backup manager instance.
	 * @param PPD_Performance_Scorer    $performance_scorer Performance scorer instance.
	 * @param PPD_Optimization_Engine   $optimization_engine Optimization engine instance.
	 */
	public function __construct( $performance_analyzer, $conflict_detector, $recommendation_engine, $backup_manager = null, $performance_scorer = null, $optimization_engine = null ) {
		$this->performance_analyzer  = $performance_analyzer;
		$this->conflict_detector     = $conflict_detector;
		$this->recommendation_engine = $recommendation_engine;
		$this->backup_manager         = $backup_manager;
		$this->performance_scorer     = $performance_scorer;
		$this->optimization_engine    = $optimization_engine;
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_management_page(
			__( 'Performance Doctor', 'performance-doctor' ),
			__( 'Performance Doctor', 'performance-doctor' ),
			'manage_options',
			'performance-doctor',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tools_page_performance-doctor' !== $hook ) {
			return;
		}

		// Localize script for AJAX.
		wp_localize_script(
			'jquery',
			'ppdAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ppd_analysis' ),
				'strings' => array(
					'error'         => __( 'Si è verificato un errore. Riprova.', 'performance-doctor' ),
					'confirmApply'  => __( 'Applicare questa ottimizzazione?', 'performance-doctor' ),
					'confirmRevert' => __( 'Annullare questa ottimizzazione?', 'performance-doctor' ),
				),
			)
		);

		// Add inline JavaScript for AJAX handling.
		wp_add_inline_script(
			'jquery',
			"
			jQuery(document).ready(function($) {
				// Run analysis button.
				$('#ppd-run-analysis').on('click', function() {
					var button = $(this);
					button.prop('disabled', true);
					$('#ppd-loading').show();
					$('#ppd-results').hide();

					$.ajax({
						url: ppdAdmin.ajaxUrl,
						type: 'POST',
						data: {
							action: 'ppd_run_analysis',
							nonce: ppdAdmin.nonce
						},
						success: function(response) {
							if (response.success) {
								$('#ppd-results').html(response.data.html).show();
								$('#ppd-export-results').show();
								
								// Restore active tab if any
								var savedTab = sessionStorage.getItem('ppd_active_tab');
								if (savedTab) {
									$('.ppd-tab-button[data-tab=\"' + savedTab + '\"]').click();
								} else {
									// Default to dashboard
									$('.ppd-tab-button[data-tab=\"dashboard\"]').click();
								}
							} else {
								alert(response.data.message || ppdAdmin.strings.error);
							}
						},
						error: function() {
							alert(ppdAdmin.strings.error);
						},
						complete: function() {
							button.prop('disabled', false);
							$('#ppd-loading').hide();
						}
					});
				});

				// Export results button.
				$('#ppd-export-results').on('click', function() {
					var button = $(this);
					button.prop('disabled', true);

					$.ajax({
						url: ppdAdmin.ajaxUrl,
						type: 'POST',
						data: {
							action: 'ppd_export_results',
							nonce: ppdAdmin.nonce
						},
						success: function(response) {
							if (response.success) {
								var dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(response.data, null, 2));
								var downloadAnchorNode = document.createElement('a');
								downloadAnchorNode.setAttribute('href', dataStr);
								downloadAnchorNode.setAttribute('download', 'ppd-analysis-' + Date.now() + '.json');
								document.body.appendChild(downloadAnchorNode);
								downloadAnchorNode.click();
								downloadAnchorNode.remove();
							} else {
								alert(response.data.message || ppdAdmin.strings.error);
							}
						},
						error: function() {
							alert(ppdAdmin.strings.error);
						},
						complete: function() {
							button.prop('disabled', false);
						}
					});
				});

				// Apply optimization button (delegated event).
				$(document).on('click', '.ppd-apply-opt', function() {
					var button = $(this);
					var optId = button.data('opt-id');
					
					if (!confirm(ppdAdmin.strings.confirmApply)) {
						return;
					}
					
					button.prop('disabled', true).text('Applicando...');
					
					$.ajax({
						url: ppdAdmin.ajaxUrl,
						type: 'POST',
						data: {
							action: 'ppd_apply_optimization',
							nonce: ppdAdmin.nonce,
							optimization_id: optId
						},
						success: function(response) {
							if (response.success) {
								alert(response.data.message || 'Ottimizzazione applicata con successo!');
								$('#ppd-run-analysis').click();
							} else {
								alert(response.data.message || ppdAdmin.strings.error);
								button.prop('disabled', false).text('Applica');
							}
						},
						error: function() {
							alert(ppdAdmin.strings.error);
							button.prop('disabled', false).text('Applica');
						}
					});
				});

				// Revert optimization button (delegated event).
				$(document).on('click', '.ppd-revert-opt', function() {
					var button = $(this);
					var optId = button.data('opt-id');
					
					if (!confirm(ppdAdmin.strings.confirmRevert)) {
						return;
					}
					
					button.prop('disabled', true).text('Annullando...');
					
					$.ajax({
						url: ppdAdmin.ajaxUrl,
						type: 'POST',
						data: {
							action: 'ppd_revert_optimization',
							nonce: ppdAdmin.nonce,
							optimization_id: optId
						},
						success: function(response) {
							if (response.success) {
								alert(response.data.message || 'Ottimizzazione annullata con successo!');
								$('#ppd-run-analysis').click();
							} else {
								alert(response.data.message || ppdAdmin.strings.error);
								button.prop('disabled', false).text('Annulla');
							}
						},
						error: function() {
							alert(ppdAdmin.strings.error);
							button.prop('disabled', false).text('Annulla');
						}
					});
				});

				// Tab navigation
				$(document).on('click', '.ppd-tab-button', function() {
					var targetTab = $(this).data('tab');
					
					// Update buttons
					$('.ppd-tab-button').removeClass('active');
					$(this).addClass('active');
					
					// Update content
					$('.ppd-tab-content').removeClass('active');
					$('#ppd-tab-' + targetTab).addClass('active');
					
					// Save active tab to session
					sessionStorage.setItem('ppd_active_tab', targetTab);
				});
			});
			"
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Performance Doctor', 'performance-doctor' ); ?></h1>
			<p><?php echo esc_html__( 'Analizza le prestazioni e i conflitti dei plugin attivi sul tuo sito WordPress.', 'performance-doctor' ); ?></p>

			<div class="ppd-actions">
				<button type="button" id="ppd-run-analysis" class="button button-primary">
					<?php echo esc_html__( 'Avvia Analisi', 'performance-doctor' ); ?>
				</button>
				<button type="button" id="ppd-export-results" class="button" style="display:none;">
					<?php echo esc_html__( 'Esporta Risultati', 'performance-doctor' ); ?>
				</button>
			</div>

			<div id="ppd-loading" style="display:none; padding: 20px; background: #fff; border: 1px solid #ccd0d4; margin-top: 20px;">
				<p><span class="spinner is-active" style="float:none; margin: 0 10px 0 0;"></span><?php echo esc_html__( 'Analisi in corso, attendere...', 'performance-doctor' ); ?></p>
			</div>

			<div id="ppd-results" style="display:none;">
				<!-- Results will be loaded here via AJAX -->
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for running analysis.
	 */
	public function ajax_run_analysis() {
		check_ajax_referer( 'ppd_analysis', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'performance-doctor' ) ) );
		}

		// Run analysis.
		$performance_metrics = $this->performance_analyzer->analyze();
		$conflicts           = $this->conflict_detector->detect_conflicts();
		$recommendations     = $this->recommendation_engine->generate_recommendations( $performance_metrics, $conflicts );

		// Calculate performance score.
		$score_data = null;
		if ( $this->performance_scorer ) {
			$score_data = $this->performance_scorer->calculate_score( $performance_metrics, $conflicts );
			$this->performance_scorer->save_to_history( $score_data );
		}

		// Get available optimizations.
		$optimizations = array();
		if ( $this->optimization_engine ) {
			$optimizations = $this->optimization_engine->get_available_optimizations();
		}

		// Prepare response.
		$response = array(
			'html' => $this->render_results_html( $performance_metrics, $conflicts, $recommendations, $score_data, $optimizations ),
		);

		wp_send_json_success( $response );
	}

	/**
	 * AJAX handler for exporting results.
	 */
	public function ajax_export_results() {
		check_ajax_referer( 'ppd_analysis', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'performance-doctor' ) ) );
		}

		// Run analysis again for export.
		$performance_metrics = $this->performance_analyzer->analyze();
		$conflicts           = $this->conflict_detector->detect_conflicts();
		$recommendations     = $this->recommendation_engine->generate_recommendations( $performance_metrics, $conflicts );

		$export_data = array(
			'timestamp'       => current_time( 'mysql' ),
			'site_url'        => get_site_url(),
			'wp_version'      => get_bloginfo( 'version' ),
			'php_version'     => PHP_VERSION,
			'performance'     => $performance_metrics,
			'conflicts'       => $conflicts,
			'recommendations' => $recommendations,
		);

		wp_send_json_success( $export_data );
	}

	/**
	 * AJAX handler for applying optimization.
	 */
	public function ajax_apply_optimization() {
		check_ajax_referer( 'ppd_analysis', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'performance-doctor' ) ) );
		}

		$optimization_id = isset( $_POST['optimization_id'] ) ? sanitize_text_field( wp_unslash( $_POST['optimization_id'] ) ) : '';

		if ( empty( $optimization_id ) || ! $this->optimization_engine ) {
			wp_send_json_error( array( 'message' => __( 'ID ottimizzazione non valido.', 'performance-doctor' ) ) );
		}

		// Apply optimization.
		$result = $this->optimization_engine->apply_optimization( $optimization_id );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => $result['message'],
				'optimization_id' => $optimization_id,
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX handler for reverting optimization.
	 */
	public function ajax_revert_optimization() {
		check_ajax_referer( 'ppd_analysis', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'performance-doctor' ) ) );
		}

		$optimization_id = isset( $_POST['optimization_id'] ) ? sanitize_text_field( wp_unslash( $_POST['optimization_id'] ) ) : '';

		if ( empty( $optimization_id ) || ! $this->optimization_engine ) {
			wp_send_json_error( array( 'message' => __( 'ID ottimizzazione non valido.', 'performance-doctor' ) ) );
		}

		// Revert optimization.
		$result = $this->optimization_engine->revert_optimization( $optimization_id );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => $result['message'],
				'optimization_id' => $optimization_id,
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX handler for rollback change.
	 */
	public function ajax_rollback_change() {
		check_ajax_referer( 'ppd_analysis', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'performance-doctor' ) ) );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';

		if ( empty( $backup_id ) || ! $this->backup_manager ) {
			wp_send_json_error( array( 'message' => __( 'ID backup non valido.', 'performance-doctor' ) ) );
		}

		// Rollback.
		$success = $this->backup_manager->rollback( $backup_id );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Modifica annullata con successo.', 'performance-doctor' ),
				'backup_id' => $backup_id,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Errore durante l\'annullamento della modifica.', 'performance-doctor' ) ) );
		}
	}

	/**
	 * Render results HTML.
	 */
	private function render_results_html( $performance_metrics, $conflicts, $recommendations, $score_data = null, $optimizations = array() ) {
		ob_start();
		?>
		<div class="ppd-results-container">
			<!-- Tab Navigation -->
			<div class="ppd-tab-navigation">
				<button class="ppd-tab-button active" data-tab="dashboard">
					<span class="dashicons dashicons-dashboard"></span>
					<?php echo esc_html__( 'Dashboard', 'performance-doctor' ); ?>
				</button>
				<button class="ppd-tab-button" data-tab="recommendations">
					<span class="dashicons dashicons-lightbulb"></span>
					<?php echo esc_html__( 'Raccomandazioni', 'performance-doctor' ); ?>
					<?php if ( ! empty( $recommendations ) ) : ?>
						<span class="ppd-tab-badge"><?php echo count( $recommendations ); ?></span>
					<?php endif; ?>
				</button>
				<button class="ppd-tab-button" data-tab="metrics">
					<span class="dashicons dashicons-chart-bar"></span>
					<?php echo esc_html__( 'Metriche Plugin', 'performance-doctor' ); ?>
					<span class="ppd-tab-badge"><?php echo count( $performance_metrics ); ?></span>
				</button>
				<button class="ppd-tab-button" data-tab="conflicts">
					<span class="dashicons dashicons-warning"></span>
					<?php echo esc_html__( 'Conflitti', 'performance-doctor' ); ?>
					<?php if ( ! empty( $conflicts ) ) : ?>
						<span class="ppd-tab-badge ppd-badge-warning"><?php echo count( $conflicts ); ?></span>
					<?php endif; ?>
				</button>
			</div>

			<!-- Tab: Dashboard -->
			<div id="ppd-tab-dashboard" class="ppd-tab-content active">
				<!-- Performance Score -->
				<?php if ( $score_data ) : ?>
					<div class="ppd-score-section">
						<h2><?php echo esc_html__( 'Score Prestazioni', 'performance-doctor' ); ?></h2>
						<div class="ppd-score-display">
							<div class="ppd-score-circle ppd-grade-<?php echo esc_attr( strtolower( $score_data['grade'] ) ); ?>">
								<div class="ppd-score-value"><?php echo esc_html( $score_data['overall_score'] ); ?></div>
								<div class="ppd-score-grade"><?php echo esc_html( $score_data['grade'] ); ?></div>
							</div>
							<div class="ppd-score-metrics">
								<?php foreach ( $score_data['metrics'] as $metric ) : ?>
									<div class="ppd-metric">
										<div class="ppd-metric-label"><?php echo esc_html( $metric['label'] ); ?></div>
										<div class="ppd-metric-bar">
											<div class="ppd-metric-fill" style="width: <?php echo esc_attr( $metric['score'] ); ?>%;"></div>
										</div>
										<div class="ppd-metric-value"><?php echo esc_html( $metric['value'] ); ?></div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<!-- Optimizations -->
				<?php if ( ! empty( $optimizations ) && $this->optimization_engine ) : ?>
					<div class="ppd-optimizations-section">
						<h2><?php echo esc_html__( 'Ottimizzazioni Disponibili', 'performance-doctor' ); ?></h2>
						<div class="ppd-optimizations-grid">
							<?php
							$active_opts = $this->optimization_engine->get_active_optimizations();
							foreach ( $optimizations as $opt_id => $opt ) :
								$is_active = isset( $active_opts[ $opt_id ] );
								$impact_class = 'ppd-impact-' . $opt['impact'];
								?>
								<div class="ppd-optimization-card <?php echo $is_active ? 'ppd-opt-active' : ''; ?>">
									<div class="ppd-opt-header">
										<h4><?php echo esc_html( $opt['name'] ); ?></h4>
										<span class="ppd-opt-impact <?php echo esc_attr( $impact_class ); ?>">
											<?php
											$impact_labels = array(
												'high'   => __( 'Alto Impatto', 'performance-doctor' ),
												'medium' => __( 'Medio Impatto', 'performance-doctor' ),
												'low'    => __( 'Basso Impatto', 'performance-doctor' ),
											);
											echo esc_html( $impact_labels[ $opt['impact'] ] ?? '' );
											?>
										</span>
									</div>
									<p class="ppd-opt-description"><?php echo esc_html( $opt['description'] ); ?></p>
									<div class="ppd-opt-actions">
										<?php if ( $is_active ) : ?>
											<button type="button" class="button ppd-revert-opt" data-opt-id="<?php echo esc_attr( $opt_id ); ?>">
												<?php echo esc_html__( 'Annulla', 'performance-doctor' ); ?>
											</button>
											<span class="ppd-opt-status"><?php echo esc_html__( '✓ Attiva', 'performance-doctor' ); ?></span>
										<?php else : ?>
											<button type="button" class="button button-primary ppd-apply-opt" data-opt-id="<?php echo esc_attr( $opt_id ); ?>">
												<?php echo esc_html__( 'Applica', 'performance-doctor' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
				
				<!-- Summary -->
				<div class="ppd-summary">
					<h2><?php echo esc_html__( 'Riepilogo Analisi', 'performance-doctor' ); ?></h2>
					<div class="ppd-summary-stats">
						<div class="ppd-stat">
							<span class="ppd-stat-label"><?php echo esc_html__( 'Plugin Analizzati:', 'performance-doctor' ); ?></span>
							<span class="ppd-stat-value"><?php echo count( $performance_metrics ); ?></span>
						</div>
						<div class="ppd-stat">
							<span class="ppd-stat-label"><?php echo esc_html__( 'Conflitti Rilevati:', 'performance-doctor' ); ?></span>
							<span class="ppd-stat-value"><?php echo count( $conflicts ); ?></span>
						</div>
						<div class="ppd-stat">
							<span class="ppd-stat-label"><?php echo esc_html__( 'Raccomandazioni:', 'performance-doctor' ); ?></span>
							<span class="ppd-stat-value"><?php echo count( $recommendations ); ?></span>
						</div>
					</div>
				</div>
			</div>
			<!-- End Tab: Dashboard -->

			<!-- Tab: Raccomandazioni -->
			<div id="ppd-tab-recommendations" class="ppd-tab-content">
				<?php if ( ! empty( $recommendations ) ) : ?>
					<div class="ppd-recommendations">
						<h2><?php echo esc_html__( 'Raccomandazioni', 'performance-doctor' ); ?></h2>
						<?php foreach ( $recommendations as $recommendation ) : ?>
							<div class="ppd-recommendation ppd-severity-<?php echo esc_attr( $recommendation['severity'] ); ?>">
								<h3><?php echo esc_html( $recommendation['title'] ); ?></h3>
								<p class="ppd-recommendation-description"><?php echo esc_html( $recommendation['description'] ); ?></p>
								<?php if ( ! empty( $recommendation['actions'] ) ) : ?>
									<div class="ppd-recommendation-actions">
										<strong><?php echo esc_html__( 'Azioni Consigliate:', 'performance-doctor' ); ?></strong>
										<ul>
											<?php foreach ( $recommendation['actions'] as $action ) : ?>
												<li><?php echo esc_html( $action ); ?></li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Nessuna raccomandazione trovata. Ottimo lavoro!', 'performance-doctor' ); ?></p></div>
				<?php endif; ?>
			</div>
			<!-- End Tab: Raccomandazioni -->

			<!-- Tab: Metriche Plugin -->
			<div id="ppd-tab-metrics" class="ppd-tab-content">
				<div class="ppd-performance-metrics">
					<h2><?php echo esc_html__( 'Metriche di Prestazioni', 'performance-doctor' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Plugin', 'performance-doctor' ); ?></th>
								<th><?php echo esc_html__( 'Livello di Carico', 'performance-doctor' ); ?></th>
								<th><?php echo esc_html__( 'Tempo di Esecuzione', 'performance-doctor' ); ?></th>
								<th><?php echo esc_html__( 'Query DB', 'performance-doctor' ); ?></th>
								<th><?php echo esc_html__( 'Uso Memoria', 'performance-doctor' ); ?></th>
								<th><?php echo esc_html__( 'Hook', 'performance-doctor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $performance_metrics as $plugin_slug => $metrics ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $metrics['name'] ); ?></strong></td>
									<td>
										<span class="ppd-load-level ppd-load-<?php echo esc_attr( $metrics['load_level'] ?? 'low' ); ?>">
											<?php
											$load_labels = array(
												'high'   => __( 'Alto', 'performance-doctor' ),
												'medium' => __( 'Medio', 'performance-doctor' ),
												'low'    => __( 'Basso', 'performance-doctor' ),
											);
											echo esc_html( $load_labels[ $metrics['load_level'] ?? 'low' ] ?? '-' );
											?>
										</span>
									</td>
									<td><?php echo esc_html( number_format( $metrics['execution_time'] * 1000, 2 ) ); ?> ms</td>
									<td><?php echo esc_html( $metrics['db_queries'] ); ?></td>
									<td><?php echo esc_html( size_format( $metrics['memory_usage'], 2 ) ); ?></td>
									<td><?php echo esc_html( $metrics['hook_count'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<!-- End Tab: Metriche Plugin -->

			<!-- Tab: Conflitti -->
			<div id="ppd-tab-conflicts" class="ppd-tab-content">
				<?php if ( ! empty( $conflicts ) ) : ?>
					<div class="ppd-conflicts">
						<h2><?php echo esc_html__( 'Conflitti Rilevati', 'performance-doctor' ); ?></h2>
						<?php
						// Group conflicts by type.
						$conflicts_by_type = array();
						foreach ( $conflicts as $conflict ) {
							$type = $conflict['type'];
							if ( ! isset( $conflicts_by_type[ $type ] ) ) {
								$conflicts_by_type[ $type ] = array();
							}
							$conflicts_by_type[ $type ][] = $conflict;
						}

						// Type labels in Italian.
						$type_labels = array(
							'hook_priority'           => __( 'Conflitti di Priorità Hook', 'performance-doctor' ),
							'extreme_priority'        => __( 'Priorità Hook Estrema', 'performance-doctor' ),
							'jquery_conflict'         => __( 'Conflitto jQuery', 'performance-doctor' ),
							'duplicate_script'        => __( 'Script Duplicato', 'performance-doctor' ),
							'duplicate_functionality' => __( 'Funzionalità Duplicata', 'performance-doctor' ),
							'php_error'               => __( 'Errori PHP', 'performance-doctor' ),
						);

						foreach ( $conflicts_by_type as $type => $type_conflicts ) :
							$type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
							?>
							<div class="ppd-conflict-group">
								<h3 class="ppd-conflict-type-title"><?php echo esc_html( $type_label ); ?> <span class="ppd-conflict-count">(<?php echo count( $type_conflicts ); ?>)</span></h3>
								<?php foreach ( $type_conflicts as $conflict ) : ?>
									<div class="ppd-conflict ppd-severity-<?php echo esc_attr( $conflict['severity'] ); ?>">
										<p class="ppd-conflict-description"><?php echo esc_html( $conflict['description'] ); ?></p>
										
										<?php if ( ! empty( $conflict['plugin_paths'] ) ) : ?>
											<div class="ppd-conflict-plugins">
												<strong><?php echo esc_html__( 'Plugin Coinvolti:', 'performance-doctor' ); ?></strong>
												<ul class="ppd-plugin-list">
													<?php foreach ( $conflict['plugin_paths'] as $slug => $path_info ) : ?>
														<li>
															<strong><?php echo esc_html( $path_info['name'] ); ?></strong>
															<br>
															<code class="ppd-file-path"><?php echo esc_html( $path_info['file'] ); ?></code>
														</li>
													<?php endforeach; ?>
												</ul>
											</div>
										<?php endif; ?>

										<?php if ( ! empty( $conflict['details'] ) ) : ?>
											<div class="ppd-conflict-details">
												<details>
													<summary><?php echo esc_html__( 'Dettagli Tecnici', 'performance-doctor' ); ?></summary>
													<pre><?php echo esc_html( json_encode( $conflict['details'], JSON_PRETTY_PRINT ) ); ?></pre>
												</details>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Nessun conflitto rilevato. Ottimo!', 'performance-doctor' ); ?></p></div>
				<?php endif; ?>
			</div>
			<!-- End Tab: Conflitti -->
		</div>

		<style>
			/* Tab Navigation Styles */
			.ppd-tab-navigation {
				display: flex;
				gap: 10px;
				margin-bottom: 20px;
				border-bottom: 2px solid #ddd;
				padding-bottom: 0;
			}
			.ppd-tab-button {
				background: #f0f0f1;
				border: none;
				border-bottom: 3px solid transparent;
				padding: 12px 20px;
				cursor: pointer;
				font-size: 14px;
				font-weight: 600;
				color: #50575e;
				display: flex;
				align-items: center;
				gap: 8px;
				transition: all 0.3s ease;
				position: relative;
				margin-bottom: -2px;
			}
			.ppd-tab-button:hover {
				background: #fff;
				color: #2271b1;
			}
			.ppd-tab-button.active {
				background: #fff;
				color: #2271b1;
				border-bottom-color: #2271b1;
			}
			.ppd-tab-button .dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
			}
			.ppd-tab-badge {
				background: #2271b1;
				color: #fff;
				border-radius: 10px;
				padding: 2px 8px;
				font-size: 11px;
				font-weight: bold;
				min-width: 20px;
				text-align: center;
			}
			.ppd-tab-badge.ppd-badge-warning {
				background: #f0b849;
			}
			.ppd-tab-content {
				display: none;
			}
			.ppd-tab-content.active {
				display: block;
				animation: fadeIn 0.3s ease;
			}
			@keyframes fadeIn {
				from { opacity: 0; transform: translateY(10px); }
				to { opacity: 1; transform: translateY(0); }
			}
			
			.ppd-results-container { margin-top: 20px; }
			.ppd-summary { background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.ppd-summary-stats { display: flex; gap: 30px; margin-top: 15px; }
			.ppd-stat { display: flex; flex-direction: column; }
			.ppd-stat-label { font-size: 14px; color: #666; }
			.ppd-stat-value { font-size: 32px; font-weight: bold; color: #2271b1; }
			.ppd-recommendations, .ppd-performance-metrics, .ppd-conflicts { background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.ppd-recommendation, .ppd-conflict { padding: 15px; margin-bottom: 15px; border-left: 4px solid #ccc; background: #f9f9f9; }
			.ppd-severity-high { border-left-color: #dc3232; }
			.ppd-severity-medium { border-left-color: #f0b849; }
			.ppd-severity-low { border-left-color: #46b450; }
			.ppd-recommendation h3 { margin-top: 0; }
			.ppd-recommendation-actions ul { margin: 10px 0; padding-left: 20px; }
			.ppd-load-level { padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
			.ppd-load-high { background: #dc3232; color: #fff; }
			.ppd-load-medium { background: #f0b849; color: #fff; }
			.ppd-load-low { background: #46b450; color: #fff; }
			.ppd-actions { margin: 20px 0; }
			#ppd-loading { padding: 20px; background: #fff; border: 1px solid #ccd0d4; margin-top: 20px; }
			
			/* Conflict-specific styles */
			.ppd-conflict-group { margin-bottom: 25px; }
			.ppd-conflict-type-title { margin: 0 0 15px 0; padding: 10px 15px; background: #f0f0f1; border-left: 4px solid #2271b1; font-size: 16px; }
			.ppd-conflict-count { color: #666; font-size: 14px; font-weight: normal; }
			.ppd-conflict-description { margin: 0 0 10px 0; font-weight: 500; }
			.ppd-conflict-plugins { margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; }
			.ppd-plugin-list { margin: 5px 0 0 0; padding-left: 20px; list-style: disc; }
			.ppd-plugin-list li { margin-bottom: 8px; }
			.ppd-file-path { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 12px; color: #50575e; display: inline-block; margin-top: 3px; }
			.ppd-conflict-details { margin-top: 10px; }
			.ppd-conflict-details summary { cursor: pointer; color: #2271b1; font-size: 13px; }
			.ppd-conflict-details pre { background: #f6f7f7; padding: 10px; border-radius: 3px; font-size: 12px; overflow-x: auto; margin: 10px 0 0 0; }
			
			/* Performance Score styles */
			.ppd-score-section { background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.ppd-score-display { display: flex; gap: 40px; align-items: center; }
			.ppd-score-circle { width: 150px; height: 150px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 8px solid #ddd; position: relative; }
			.ppd-score-circle.ppd-grade-a { border-color: #46b450; background: linear-gradient(135deg, #f0f9f1 0%, #e7f5e8 100%); }
			.ppd-score-circle.ppd-grade-b { border-color: #00a0d2; background: linear-gradient(135deg, #e5f5fa 0%, #d9eff5 100%); }
			.ppd-score-circle.ppd-grade-c { border-color: #f0b849; background: linear-gradient(135deg, #fef8e7 0%, #fdf3d9 100%); }
			.ppd-score-circle.ppd-grade-d { border-color: #f56e28; background: linear-gradient(135deg, #fef0e7 0%, #fde8d9 100%); }
			.ppd-score-circle.ppd-grade-f { border-color: #dc3232; background: linear-gradient(135deg, #fce8e8 0%, #fad9d9 100%); }
			.ppd-score-value { font-size: 48px; font-weight: bold; line-height: 1; }
			.ppd-score-grade { font-size: 24px; font-weight: 600; color: #666; }
			.ppd-score-metrics { flex: 1; }
			.ppd-metric { margin-bottom: 15px; }
			.ppd-metric-label { font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #1d2327; }
			.ppd-metric-bar { height: 8px; background: #f0f0f1; border-radius: 4px; overflow: hidden; }
			.ppd-metric-fill { height: 100%; background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); transition: width 0.3s ease; }
			.ppd-metric-value { font-size: 12px; color: #666; margin-top: 3px; }
			
			/* Optimizations styles */
			.ppd-optimizations-section { background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.ppd-optimizations-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px; }
			.ppd-optimization-card { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; transition: all 0.2s ease; }
			.ppd-optimization-card:hover { box-shadow: 0 2px 4px rgba(0,0,0,.1); }
			.ppd-optimization-card.ppd-opt-active { background: #e7f5e8; border-color: #46b450; }
			.ppd-opt-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
			.ppd-opt-header h4 { margin: 0; font-size: 15px; }
			.ppd-opt-impact { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
			.ppd-impact-high { background: #46b450; color: #fff; }
			.ppd-impact-medium { background: #f0b849; color: #fff; }
			.ppd-impact-low { background: #72aee6; color: #fff; }
			.ppd-opt-description { font-size: 13px; color: #666; margin: 10px 0; line-height: 1.5; }
			.ppd-opt-actions { display: flex; gap: 10px; align-items: center; margin-top: 15px; }
			.ppd-opt-status { color: #46b450; font-weight: 600; font-size: 13px; }
		</style>
		<?php
		return ob_get_clean();
	}
}
