<?php
/**
 * Backup Manager class.
 *
 * Manages backups and rollback of plugin modifications.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Backup Manager class.
 */
class PPD_Backup_Manager {

	/**
	 * Maximum number of backups to keep.
	 *
	 * @var int
	 */
	private $max_backups = 50;

	/**
	 * Backup retention days.
	 *
	 * @var int
	 */
	private $retention_days = 30;

	/**
	 * Create a backup before applying changes.
	 *
	 * @param string $type Backup type (optimization, script_conflict, etc.).
	 * @param string $action Action being performed.
	 * @param array  $data Data to backup.
	 * @return int|false Backup ID or false on failure.
	 */
	public function create_backup( $type, $action, $data = array() ) {
		$backups = $this->get_all_backups();

		$backup = array(
			'id'             => uniqid( 'ppd_', true ),
			'timestamp'      => current_time( 'mysql' ),
			'type'           => $type,
			'action'         => $action,
			'previous_state' => $data,
			'can_rollback'   => true,
			'rolled_back'    => false,
		);

		$backups[] = $backup;

		// Clean old backups.
		$backups = $this->cleanup_old_backups( $backups );

		// Save backups.
		update_option( 'ppd_backups', $backups );

		return $backup['id'];
	}

	/**
	 * Rollback to a specific backup.
	 *
	 * @param string $backup_id Backup ID.
	 * @return bool True on success, false on failure.
	 */
	public function rollback( $backup_id ) {
		$backups = $this->get_all_backups();
		$backup  = null;

		foreach ( $backups as $key => $b ) {
			if ( $b['id'] === $backup_id ) {
				$backup = $b;
				$index  = $key;
				break;
			}
		}

		if ( ! $backup || ! $backup['can_rollback'] || $backup['rolled_back'] ) {
			return false;
		}

		// Restore previous state.
		$success = $this->restore_state( $backup['type'], $backup['previous_state'] );

		if ( $success ) {
			// Mark as rolled back.
			$backups[ $index ]['rolled_back']   = true;
			$backups[ $index ]['rollback_time'] = current_time( 'mysql' );
			update_option( 'ppd_backups', $backups );
		}

		return $success;
	}

	/**
	 * Restore state from backup.
	 *
	 * @param string $type Backup type.
	 * @param array  $state Previous state data.
	 * @return bool True on success.
	 */
	private function restore_state( $type, $state ) {
		switch ( $type ) {
			case 'optimization':
				return $this->restore_optimization_state( $state );

			case 'script_conflict':
				return $this->restore_script_state( $state );

			case 'option':
				return $this->restore_option_state( $state );

			default:
				return false;
		}
	}

	/**
	 * Restore optimization state.
	 *
	 * @param array $state State data.
	 * @return bool Success.
	 */
	private function restore_optimization_state( $state ) {
		if ( isset( $state['options'] ) && is_array( $state['options'] ) ) {
			foreach ( $state['options'] as $option_name => $option_value ) {
				update_option( $option_name, $option_value );
			}
		}

		if ( isset( $state['filters'] ) && is_array( $state['filters'] ) ) {
			// Restore removed filters.
			foreach ( $state['filters'] as $filter_data ) {
				if ( isset( $filter_data['remove'] ) && $filter_data['remove'] ) {
					remove_filter( $filter_data['hook'], $filter_data['callback'], $filter_data['priority'] );
				}
			}
		}

		return true;
	}

	/**
	 * Restore script state.
	 *
	 * @param array $state State data.
	 * @return bool Success.
	 */
	private function restore_script_state( $state ) {
		if ( isset( $state['disabled_scripts'] ) ) {
			delete_option( 'ppd_disabled_scripts' );
			if ( ! empty( $state['disabled_scripts'] ) ) {
				update_option( 'ppd_disabled_scripts', $state['disabled_scripts'] );
			}
		}

		return true;
	}

	/**
	 * Restore option state.
	 *
	 * @param array $state State data.
	 * @return bool Success.
	 */
	private function restore_option_state( $state ) {
		if ( isset( $state['option_name'] ) && isset( $state['option_value'] ) ) {
			update_option( $state['option_name'], $state['option_value'] );
			return true;
		}

		return false;
	}

	/**
	 * Get all backups.
	 *
	 * @return array Backups.
	 */
	public function get_all_backups() {
		$backups = get_option( 'ppd_backups', array() );
		return is_array( $backups ) ? $backups : array();
	}

	/**
	 * Get backup by ID.
	 *
	 * @param string $backup_id Backup ID.
	 * @return array|null Backup data or null.
	 */
	public function get_backup( $backup_id ) {
		$backups = $this->get_all_backups();

		foreach ( $backups as $backup ) {
			if ( $backup['id'] === $backup_id ) {
				return $backup;
			}
		}

		return null;
	}

	/**
	 * Clean up old backups.
	 *
	 * @param array $backups Current backups.
	 * @return array Cleaned backups.
	 */
	private function cleanup_old_backups( $backups ) {
		// Remove backups older than retention period.
		$cutoff_date = strtotime( "-{$this->retention_days} days" );
		$backups     = array_filter(
			$backups,
			function ( $backup ) use ( $cutoff_date ) {
				return strtotime( $backup['timestamp'] ) > $cutoff_date;
			}
		);

		// Keep only max_backups most recent.
		if ( count( $backups ) > $this->max_backups ) {
			usort(
				$backups,
				function ( $a, $b ) {
					return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
				}
			);
			$backups = array_slice( $backups, 0, $this->max_backups );
		}

		return array_values( $backups );
	}

	/**
	 * Delete a specific backup.
	 *
	 * @param string $backup_id Backup ID.
	 * @return bool True on success.
	 */
	public function delete_backup( $backup_id ) {
		$backups = $this->get_all_backups();
		$backups = array_filter(
			$backups,
			function ( $backup ) use ( $backup_id ) {
				return $backup['id'] !== $backup_id;
			}
		);

		update_option( 'ppd_backups', array_values( $backups ) );
		return true;
	}

	/**
	 * Delete all backups.
	 *
	 * @return bool True on success.
	 */
	public function delete_all_backups() {
		delete_option( 'ppd_backups' );
		return true;
	}

	/**
	 * Get backup statistics.
	 *
	 * @return array Statistics.
	 */
	public function get_stats() {
		$backups = $this->get_all_backups();

		$stats = array(
			'total'       => count( $backups ),
			'rolled_back' => 0,
			'active'      => 0,
			'by_type'     => array(),
		);

		foreach ( $backups as $backup ) {
			if ( $backup['rolled_back'] ) {
				$stats['rolled_back']++;
			} else {
				$stats['active']++;
			}

			$type = $backup['type'];
			if ( ! isset( $stats['by_type'][ $type ] ) ) {
				$stats['by_type'][ $type ] = 0;
			}
			$stats['by_type'][ $type ]++;
		}

		return $stats;
	}
}
