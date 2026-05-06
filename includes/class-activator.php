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
	}
}
