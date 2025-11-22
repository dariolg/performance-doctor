<?php

/**
 * Optimization Engine class.
 *
 * Applies and reverts performance optimizations.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

/**
 * Optimization Engine class.
 */
class PPD_Optimization_Engine
{

	/**
	 * Backup manager instance.
	 *
	 * @var PPD_Backup_Manager
	 */
	private $backup_manager;

	/**
	 * Constructor.
	 *
	 * @param PPD_Backup_Manager $backup_manager Backup manager instance.
	 */
	public function __construct($backup_manager)
	{
		$this->backup_manager = $backup_manager;
	}

	/**
	 * Get available optimizations.
	 *
	 * @return array Optimizations.
	 */
	public function get_available_optimizations()
	{
		return array(
			'lazy_loading'      => array(
				'id'          => 'lazy_loading',
				'name'        => __('Lazy Loading Immagini', 'performance-doctor'),
				'description' => __('Carica le immagini solo quando visibili nella viewport', 'performance-doctor'),
				'impact'      => 'high',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'defer_js'          => array(
				'id'          => 'defer_js',
				'name'        => __('Defer JavaScript', 'performance-doctor'),
				'description' => __('Posticipa il caricamento di JavaScript non critico', 'performance-doctor'),
				'impact'      => 'high',
				'difficulty'  => 'medium',
				'reversible'  => true,
			),
			'disable_emoji'     => array(
				'id'          => 'disable_emoji',
				'name'        => __('Disabilita Emoji WordPress', 'performance-doctor'),
				'description' => __('Rimuove gli script emoji di WordPress (se non necessari)', 'performance-doctor'),
				'impact'      => 'low',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'minify_html'       => array(
				'id'          => 'minify_html',
				'name'        => __('Minificazione HTML', 'performance-doctor'),
				'description' => __('Rimuove spazi bianchi e commenti dall\'HTML', 'performance-doctor'),
				'impact'      => 'medium',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'preload_fonts'     => array(
				'id'          => 'preload_fonts',
				'name'        => __('Preload Font', 'performance-doctor'),
				'description' => __('Precarica i font per ridurre il tempo di rendering', 'performance-doctor'),
				'impact'      => 'medium',
				'difficulty'  => 'medium',
				'reversible'  => true,
			),
			'disable_embeds'    => array(
				'id'          => 'disable_embeds',
				'name'        => __('Disabilita Embed WordPress', 'performance-doctor'),
				'description' => __('Rimuove la funzionalità embed di WordPress', 'performance-doctor'),
				'impact'      => 'low',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'limit_revisions'   => array(
				'id'          => 'limit_revisions',
				'name'        => __('Limita Revisioni Post', 'performance-doctor'),
				'description' => __('Limita il numero di revisioni salvate per post', 'performance-doctor'),
				'impact'      => 'low',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'disable_heartbeat' => array(
				'id'          => 'disable_heartbeat',
				'name'        => __('Ottimizza Heartbeat API', 'performance-doctor'),
				'description' => __('Riduce la frequenza dell\'Heartbeat API di WordPress', 'performance-doctor'),
				'impact'      => 'medium',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'disable_xmlrpc'    => array(
				'id'          => 'disable_xmlrpc',
				'name'        => __('Disabilita XML-RPC', 'performance-doctor'),
				'description' => __('Migliora la sicurezza e le prestazioni bloccando le richieste XML-RPC', 'performance-doctor'),
				'impact'      => 'medium',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'remove_query_strings' => array(
				'id'          => 'remove_query_strings',
				'name'        => __('Rimuovi Query Strings', 'performance-doctor'),
				'description' => __('Rimuove le stringhe di query dalle risorse statiche per migliorare il caching', 'performance-doctor'),
				'impact'      => 'medium',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'disable_self_pingbacks' => array(
				'id'          => 'disable_self_pingbacks',
				'name'        => __('Disabilita Self Pingbacks', 'performance-doctor'),
				'description' => __('Impedisce al sito di inviare pingback a se stesso', 'performance-doctor'),
				'impact'      => 'low',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'disable_dashicons' => array(
				'id'          => 'disable_dashicons',
				'name'        => __('Disabilita Dashicons Frontend', 'performance-doctor'),
				'description' => __('Rimuove il CSS delle Dashicons per gli utenti non loggati', 'performance-doctor'),
				'impact'      => 'low',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
			'cleanup_head'      => array(
				'id'          => 'cleanup_head',
				'name'        => __('Pulizia Header WordPress', 'performance-doctor'),
				'description' => __('Rimuove link inutili (RSD, WLW, Shortlink, Generator) dall\'header', 'performance-doctor'),
				'impact'      => 'low',
				'difficulty'  => 'easy',
				'reversible'  => true,
			),
		);
	}

	/**
	 * Apply an optimization.
	 *
	 * @param string $optimization_id Optimization ID.
	 * @return array Result with success status and message.
	 */
	public function apply_optimization($optimization_id)
	{
		$optimizations = $this->get_available_optimizations();

		if (! isset($optimizations[$optimization_id])) {
			return array(
				'success' => false,
				'message' => __('Ottimizzazione non trovata.', 'performance-doctor'),
			);
		}

		// Check if already applied.
		if ($this->is_optimization_active($optimization_id)) {
			return array(
				'success' => false,
				'message' => __('Ottimizzazione già applicata.', 'performance-doctor'),
			);
		}

		// Create backup.
		$backup_data = $this->get_current_state($optimization_id);
		$backup_id   = $this->backup_manager->create_backup(
			'optimization',
			$optimization_id,
			$backup_data
		);

		// Apply optimization.
		$result = $this->execute_optimization($optimization_id);

		if ($result['success']) {
			// Mark as active.
			$this->mark_optimization_active($optimization_id, $backup_id);
		} else {
			// Rollback backup if failed.
			$this->backup_manager->delete_backup($backup_id);
		}

		return $result;
	}

	/**
	 * Revert an optimization.
	 *
	 * @param string $optimization_id Optimization ID.
	 * @return array Result.
	 */
	public function revert_optimization($optimization_id)
	{
		if (! $this->is_optimization_active($optimization_id)) {
			return array(
				'success' => false,
				'message' => __('Ottimizzazione non attiva.', 'performance-doctor'),
			);
		}

		$active_opts = get_option('ppd_active_optimizations', array());
		$backup_id   = isset($active_opts[$optimization_id]) ? $active_opts[$optimization_id]['backup_id'] : null;

		// Rollback using backup.
		if ($backup_id && $this->backup_manager->rollback($backup_id)) {
			unset($active_opts[$optimization_id]);
			update_option('ppd_active_optimizations', $active_opts);

			return array(
				'success' => true,
				'message' => __('Ottimizzazione annullata con successo.', 'performance-doctor'),
			);
		}

		return array(
			'success' => false,
			'message' => __('Errore durante l\'annullamento dell\'ottimizzazione.', 'performance-doctor'),
		);
	}

	/**
	 * Execute specific optimization.
	 *
	 * @param string $optimization_id Optimization ID.
	 * @return array Result.
	 */
	private function execute_optimization($optimization_id)
	{
		switch ($optimization_id) {
			case 'lazy_loading':
				return $this->apply_lazy_loading();

			case 'defer_js':
				return $this->apply_defer_js();

			case 'disable_emoji':
				return $this->apply_disable_emoji();

			case 'minify_html':
				return $this->apply_minify_html();

			case 'preload_fonts':
				return $this->apply_preload_fonts();

			case 'disable_embeds':
				return $this->apply_disable_embeds();

			case 'limit_revisions':
				return $this->apply_limit_revisions();

			case 'disable_heartbeat':
				return $this->apply_disable_heartbeat();

			case 'disable_xmlrpc':
				return $this->apply_disable_xmlrpc();

			case 'remove_query_strings':
				return $this->apply_remove_query_strings();

			case 'disable_self_pingbacks':
				return $this->apply_disable_self_pingbacks();

			case 'disable_dashicons':
				return $this->apply_disable_dashicons();

			case 'cleanup_head':
				return $this->apply_cleanup_head();

			default:
				return array(
					'success' => false,
					'message' => __('Ottimizzazione non implementata.', 'performance-doctor'),
				);
		}
	}

	/**
	 * Apply lazy loading optimization.
	 *
	 * @return array Result.
	 */
	private function apply_lazy_loading()
	{
		update_option('ppd_opt_lazy_loading', true);

		return array(
			'success' => true,
			'message' => __('Lazy loading immagini attivato.', 'performance-doctor'),
		);
	}

	/**
	 * Apply defer JS optimization.
	 *
	 * @return array Result.
	 */
	private function apply_defer_js()
	{
		update_option('ppd_opt_defer_js', true);

		return array(
			'success' => true,
			'message' => __('Defer JavaScript attivato.', 'performance-doctor'),
		);
	}

	/**
	 * Apply disable emoji optimization.
	 *
	 * @return array Result.
	 */
	private function apply_disable_emoji()
	{
		update_option('ppd_opt_disable_emoji', true);

		return array(
			'success' => true,
			'message' => __('Emoji WordPress disabilitati.', 'performance-doctor'),
		);
	}

	/**
	 * Apply minify HTML optimization.
	 *
	 * @return array Result.
	 */
	private function apply_minify_html()
	{
		update_option('ppd_opt_minify_html', true);

		return array(
			'success' => true,
			'message' => __('Minificazione HTML attivata.', 'performance-doctor'),
		);
	}

	/**
	 * Apply preload fonts optimization.
	 *
	 * @return array Result.
	 */
	private function apply_preload_fonts()
	{
		update_option('ppd_opt_preload_fonts', true);

		return array(
			'success' => true,
			'message' => __('Preload font attivato.', 'performance-doctor'),
		);
	}

	/**
	 * Apply disable embeds optimization.
	 *
	 * @return array Result.
	 */
	private function apply_disable_embeds()
	{
		update_option('ppd_opt_disable_embeds', true);

		return array(
			'success' => true,
			'message' => __('Embed WordPress disabilitati.', 'performance-doctor'),
		);
	}

	/**
	 * Apply limit revisions optimization.
	 *
	 * @return array Result.
	 */
	private function apply_limit_revisions()
	{
		update_option('ppd_opt_limit_revisions', 5);

		return array(
			'success' => true,
			'message' => __('Revisioni post limitate a 5.', 'performance-doctor'),
		);
	}

	/**
	 * Apply disable heartbeat optimization.
	 *
	 * @return array Result.
	 */
	private function apply_disable_heartbeat()
	{
		update_option('ppd_opt_disable_heartbeat', true);

		return array(
			'success' => true,
			'message' => __('Heartbeat API ottimizzato.', 'performance-doctor'),
		);
	}

	/**
	 * Apply disable XML-RPC optimization.
	 *
	 * @return array Result.
	 */
	private function apply_disable_xmlrpc()
	{
		update_option('ppd_opt_disable_xmlrpc', true);

		return array(
			'success' => true,
			'message' => __('XML-RPC disabilitato.', 'performance-doctor'),
		);
	}

	/**
	 * Apply remove query strings optimization.
	 *
	 * @return array Result.
	 */
	private function apply_remove_query_strings()
	{
		update_option('ppd_opt_remove_query_strings', true);

		return array(
			'success' => true,
			'message' => __('Rimozione query strings attivata.', 'performance-doctor'),
		);
	}

	/**
	 * Apply disable self pingbacks optimization.
	 *
	 * @return array Result.
	 */
	private function apply_disable_self_pingbacks()
	{
		update_option('ppd_opt_disable_self_pingbacks', true);

		return array(
			'success' => true,
			'message' => __('Self pingbacks disabilitati.', 'performance-doctor'),
		);
	}

	/**
	 * Apply disable dashicons optimization.
	 *
	 * @return array Result.
	 */
	private function apply_disable_dashicons()
	{
		update_option('ppd_opt_disable_dashicons', true);

		return array(
			'success' => true,
			'message' => __('Dashicons disabilitate sul frontend.', 'performance-doctor'),
		);
	}

	/**
	 * Apply cleanup head optimization.
	 *
	 * @return array Result.
	 */
	private function apply_cleanup_head()
	{
		update_option('ppd_opt_cleanup_head', true);

		return array(
			'success' => true,
			'message' => __('Pulizia header WordPress attivata.', 'performance-doctor'),
		);
	}

	/**
	 * Get current state for backup.
	 *
	 * @param string $optimization_id Optimization ID.
	 * @return array State data.
	 */
	private function get_current_state($optimization_id)
	{
		$option_name = 'ppd_opt_' . $optimization_id;

		return array(
			'options' => array(
				$option_name => get_option($option_name, false),
			),
		);
	}

	/**
	 * Check if optimization is active.
	 *
	 * @param string $optimization_id Optimization ID.
	 * @return bool True if active.
	 */
	public function is_optimization_active($optimization_id)
	{
		$active_opts = get_option('ppd_active_optimizations', array());
		return isset($active_opts[$optimization_id]);
	}

	/**
	 * Mark optimization as active.
	 *
	 * @param string $optimization_id Optimization ID.
	 * @param string $backup_id Backup ID.
	 */
	private function mark_optimization_active($optimization_id, $backup_id)
	{
		$active_opts = get_option('ppd_active_optimizations', array());

		$active_opts[$optimization_id] = array(
			'applied_at' => current_time('mysql'),
			'backup_id'  => $backup_id,
		);

		update_option('ppd_active_optimizations', $active_opts);
	}

	/**
	 * Get active optimizations.
	 *
	 * @return array Active optimizations.
	 */
	public function get_active_optimizations()
	{
		return get_option('ppd_active_optimizations', array());
	}

	/**
	 * Estimate impact of optimization.
	 *
	 * @param string $optimization_id Optimization ID.
	 * @return array Impact estimation.
	 */
	public function estimate_impact($optimization_id)
	{
		$optimizations = $this->get_available_optimizations();

		if (! isset($optimizations[$optimization_id])) {
			return null;
		}

		$opt = $optimizations[$optimization_id];

		$impact_scores = array(
			'high'   => 15,
			'medium' => 8,
			'low'    => 3,
		);

		$score_improvement = isset($impact_scores[$opt['impact']]) ? $impact_scores[$opt['impact']] : 0;

		return array(
			'score_improvement' => $score_improvement,
			'impact_level'      => $opt['impact'],
			'difficulty'        => $opt['difficulty'],
			'estimated_time'    => __('Istantaneo', 'performance-doctor'),
		);
	}
}
