<?php
/**
 * Plugin Name:       CheckFlow
 * Plugin URI:        https://example.com/checkflow
 * Description:       One Page Checkout for WooCommerce — admin panel and configuration.
 * Version:           1.0.0
 * Author:            CheckFlow
 * Text Domain:       checkflow
 * Domain Path:       /languages
 * Requires PHP:      7.4
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHECKFLOW_VERSION', '1.0.0' );
define( 'CHECKFLOW_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHECKFLOW_URL', plugin_dir_url( __FILE__ ) );
define( 'CHECKFLOW_BASENAME', plugin_basename( __FILE__ ) );

require_once CHECKFLOW_PATH . 'includes/class-loader.php';
require_once CHECKFLOW_PATH . 'includes/class-checkflow-i18n.php';
require_once CHECKFLOW_PATH . 'includes/class-checkflow-admin.php';
require_once CHECKFLOW_PATH . 'includes/class-activator.php';
require_once CHECKFLOW_PATH . 'includes/class-deactivator.php';
require_once CHECKFLOW_PATH . 'includes/class-checkflow.php';

register_activation_hook( __FILE__, array( 'CheckFlow_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CheckFlow_Deactivator', 'deactivate' ) );

CheckFlow::instance()->run();

/**
 * Translate CheckFlow UI string by key (respects admin locale + overrides).
 *
 * @param string $key Message key (e.g. nav.dashboard).
 * @return string
 */
function checkflow_str( $key ) {
	return CheckFlow_I18n::instance()->resolve( $key );
}
