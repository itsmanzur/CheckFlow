<?php
/**
 * Uninstall handler for CheckFlow.
 *
 * @package CheckFlow
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Keep user-created settings by default. Only remove runtime marker.
delete_option( 'checkflow_version' );
