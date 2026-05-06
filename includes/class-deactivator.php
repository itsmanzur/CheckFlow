<?php
/**
 * Plugin deactivation tasks.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Deactivator {

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		// Reserved for cleanup tasks like unscheduling cron.
	}
}
