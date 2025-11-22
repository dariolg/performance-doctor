<?php

/**
 * Recommendation Engine class.
 *
 * Generates actionable recommendations based on performance analysis and conflict detection.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

/**
 * Recommendation Engine class.
 */
class PPD_Recommendation_Engine
{

	/**
	 * Generate recommendations based on analysis results.
	 *
	 * @param array $performance_metrics Performance metrics from analyzer.
	 * @param array $conflicts Conflicts from detector.
	 * @return array Array of recommendations.
	 */
	public function generate_recommendations($performance_metrics, $conflicts)
	{
		$recommendations = array();

		// Generate performance-based recommendations.
		$recommendations = array_merge(
			$recommendations,
			$this->get_performance_recommendations($performance_metrics)
		);

		// Generate conflict-based recommendations.
		$recommendations = array_merge(
			$recommendations,
			$this->get_conflict_recommendations($conflicts)
		);

		// Sort by priority.
		usort($recommendations, array($this, 'sort_by_priority'));

		return $recommendations;
	}

	/**
	 * Get performance-based recommendations.
	 *
	 * @param array $metrics Performance metrics.
	 * @return array Recommendations.
	 */
	private function get_performance_recommendations($metrics)
	{
		$recommendations = array();

		foreach ($metrics as $plugin_slug => $data) {
			if (! isset($data['load_level'])) {
				continue;
			}

			// Analyze specific bottlenecks.
			$issues = array();

			// 1. High Execution Time (CPU/PHP)
			if ($data['execution_time'] > 0.5) { // More than 500ms
				$issues[] = array(
					'type' => 'cpu',
					/* translators: %s: execution time in seconds */
					'msg'  => sprintf(__('Tempo di esecuzione elevato (%s s)', 'performance-doctor'), number_format($data['execution_time'], 2)),
				);
			}

			// 2. High Database Usage
			if ($data['db_queries'] > 50) {
				$issues[] = array(
					'type' => 'db',
					/* translators: %d: number of database queries */
					'msg'  => sprintf(__('Numero eccessivo di query al database (%d)', 'performance-doctor'), $data['db_queries']),
				);
			}

			// 3. High Memory Usage
			if ($data['memory_usage'] > 10 * 1024 * 1024) { // More than 10MB
				$issues[] = array(
					'type' => 'mem',
					/* translators: %s: memory usage formatted size */
					'msg'  => sprintf(__('Consumo di memoria elevato (%s)', 'performance-doctor'), size_format($data['memory_usage'])),
				);
			}

			if (! empty($issues)) {
				$description = sprintf(
					/* translators: %s: plugin name */
					__('Il plugin "%s" sta rallentando il sito. Problemi rilevati:', 'performance-doctor'),
					$data['name']
				);

				$desc_list = '<ul>';
				foreach ($issues as $issue) {
					$desc_list .= '<li>' . esc_html($issue['msg']) . '</li>';
				}
				$desc_list .= '</ul>';

				// Generate specific mitigation actions based on issue type.
				$actions = array();

				// General actions first.
				$actions[] = __('Verifica se ci sono aggiornamenti disponibili che risolvono problemi di performance', 'performance-doctor');

				foreach ($issues as $issue) {
					switch ($issue['type']) {
						case 'cpu':
							$actions[] = __('Se è un plugin di backup o statistica, riduci la frequenza delle operazioni nelle impostazioni', 'performance-doctor');
							$actions[] = __('Usa un plugin di "Heartbeat Control" per ridurre il carico admin-ajax', 'performance-doctor');
							break;
						case 'db':
							$actions[] = __('Installa un sistema di Object Cache (es. Redis o Memcached) per ridurre le query', 'performance-doctor');
							$actions[] = __('Verifica se il plugin ha opzioni per disabilitare log o statistiche interne', 'performance-doctor');
							break;
						case 'mem':
							$actions[] = __('Questo plugin potrebbe avere memory leak. Segnalalo agli sviluppatori', 'performance-doctor');
							break;
					}
				}

				// Non-destructive mitigation advice.
				$actions[] = __('Usa un plugin di "Asset Manager" per caricare questo plugin solo nelle pagine dove è realmente necessario', 'performance-doctor');

				$recommendations[] = array(
					'type'        => 'performance',
					'severity'    => 'high',
					'plugin'      => $plugin_slug,
					/* translators: %s: plugin name */
					'title'       => sprintf(__('Rallentamento Rilevato: %s', 'performance-doctor'), $data['name']),
					'description' => $description . $desc_list,
					'actions'     => array_unique($actions),
				);
			}
		}

		return $recommendations;
	}

	/**
	 * Get conflict-based recommendations.
	 *
	 * @param array $conflicts Detected conflicts.
	 * @return array Recommendations.
	 */
	private function get_conflict_recommendations($conflicts)
	{
		$recommendations = array();

		foreach ($conflicts as $conflict) {
			$severity = $conflict['severity'];
			$type     = $conflict['type'];

			switch ($type) {
				case 'duplicate_functionality':
					$recommendations[] = array(
						'type'        => 'conflict',
						'severity'    => $severity,
						'plugin'      => implode(', ', $conflict['plugins']),
						'title'       => __('Rimuovi Plugin Duplicati', 'performance-doctor'),
						'description' => $conflict['description'],
						'actions'     => array(
							__('Scegli il plugin che meglio si adatta alle tue esigenze', 'performance-doctor'),
							__('Disattiva ed elimina gli altri plugin della stessa categoria', 'performance-doctor'),
							__('Esegui un backup completo prima di rimuovere qualsiasi plugin', 'performance-doctor'),
						),
					);
					break;

				case 'jquery_conflict':
					$recommendations[] = array(
						'type'        => 'conflict',
						'severity'    => $severity,
						'plugin'      => '',
						'title'       => __('Risolvi Conflitto jQuery', 'performance-doctor'),
						'description' => $conflict['description'],
						'actions'     => array(
							__('Identifica quale plugin sta caricando una versione personalizzata di jQuery', 'performance-doctor'),
							__('Controlla le impostazioni del plugin per opzioni di compatibilità jQuery', 'performance-doctor'),
							__('Contatta lo sviluppatore del plugin per segnalare il conflitto', 'performance-doctor'),
							__('Considera l\'uso di un plugin per gestire i conflitti di script', 'performance-doctor'),
						),
					);
					break;

				case 'php_error':
					$plugin_name = ! empty($conflict['plugins']) ? $conflict['plugins'][0] : '';
					$recommendations[] = array(
						'type'        => 'error',
						'severity'    => $severity,
						'plugin'      => $plugin_name,
						'title'       => sprintf(
							/* translators: %s: plugin slug */
							__('Correggi Errori PHP in "%s"', 'performance-doctor'),
							$plugin_name
						),
						'description' => $conflict['description'],
						'actions'     => array(
							__('Controlla il log degli errori per dettagli specifici', 'performance-doctor'),
							__('Aggiorna il plugin all\'ultima versione', 'performance-doctor'),
							__('Verifica la compatibilità con la tua versione di PHP e WordPress', 'performance-doctor'),
							__('Segnala l\'errore allo sviluppatore del plugin', 'performance-doctor'),
						),
					);
					break;

				case 'extreme_priority':
					$plugin_name = ! empty($conflict['plugins']) ? $conflict['plugins'][0] : '';
					$recommendations[] = array(
						'type'        => 'conflict',
						'severity'    => $severity,
						'plugin'      => $plugin_name,
						'title'       => __('Verifica Priorità Hook Estrema', 'performance-doctor'),
						'description' => $conflict['description'],
						'actions'     => array(
							__('Questo potrebbe essere intenzionale, ma verifica con lo sviluppatore', 'performance-doctor'),
							__('Controlla se ci sono conflitti con altri plugin', 'performance-doctor'),
							__('Monitora il comportamento del sito per problemi inaspettati', 'performance-doctor'),
						),
					);
					break;

				case 'hook_priority':
				case 'duplicate_script':
					// These are lower priority, add simple recommendations.
					$recommendations[] = array(
						'type'        => 'conflict',
						'severity'    => $severity,
						'plugin'      => implode(', ', $conflict['plugins']),
						'title'       => __('Possibile Conflitto Rilevato', 'performance-doctor'),
						'description' => $conflict['description'],
						'actions'     => array(
							__('Monitora il sito per comportamenti inaspettati', 'performance-doctor'),
							__('Testa la funzionalità dei plugin coinvolti', 'performance-doctor'),
							__('Contatta gli sviluppatori se riscontri problemi', 'performance-doctor'),
						),
					);
					break;
			}
		}

		return $recommendations;
	}

	/**
	 * Sort recommendations by priority.
	 *
	 * @param array $a First recommendation.
	 * @param array $b Second recommendation.
	 * @return int Comparison result.
	 */
	private function sort_by_priority($a, $b)
	{
		$priority_map = array(
			'high'   => 3,
			'medium' => 2,
			'low'    => 1,
		);

		$priority_a = isset($priority_map[$a['severity']]) ? $priority_map[$a['severity']] : 0;
		$priority_b = isset($priority_map[$b['severity']]) ? $priority_map[$b['severity']] : 0;

		return $priority_b - $priority_a;
	}

	/**
	 * Get code snippet for a recommendation.
	 *
	 * @param string $type Recommendation type.
	 * @param array  $data Additional data.
	 * @return string Code snippet.
	 */
	public function get_code_snippet($type, $data = array())
	{
		$snippets = array(
			'cache_object' => "// Aggiungi al file wp-config.php\ndefine( 'WP_CACHE', true );\n\n// Installa e configura un plugin di cache degli oggetti",
			'disable_hooks' => "// Esempio: disabilitare un hook specifico\nremove_action( 'hook_name', 'function_name', priority );",
			'lazy_load' => "// Abilita il lazy loading per le immagini\nadd_filter( 'wp_lazy_loading_enabled', '__return_true' );",
		);

		return isset($snippets[$type]) ? $snippets[$type] : '';
	}
}
