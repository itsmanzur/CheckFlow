<?php
/**
 * Plugin activation tasks.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Activator {

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		if ( ! get_option( 'checkflow_version' ) ) {
			add_option( 'checkflow_version', CHECKFLOW_VERSION );
		} else {
			update_option( 'checkflow_version', CHECKFLOW_VERSION );
		}

		self::create_event_log_table();
	}

	/**
	 * Create/update CheckFlow local tracking event log table.
	 */
	public static function create_event_log_table() {
		global $wpdb;

		$table_name      = self::event_log_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_name varchar(64) NOT NULL,
			event_id varchar(100) NOT NULL,
			page_url text NOT NULL,
			context longtext NULL,
			provider_state longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_name (event_name),
			KEY event_id (event_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * @return string
	 */
	public static function event_log_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'checkflow_event_log';
	}
}
