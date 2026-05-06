<?php
/**
 * Frontend asset controller placeholder (Phase 1 target).
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Frontend_Assets {

	/** @var self|null */
	private static $instance = null;

	private function __construct() {}

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Conditionally enqueue checkout assets.
	 */
	public function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		wp_enqueue_style(
			'checkflow-checkout',
			CHECKFLOW_URL . 'public/css/checkflow.css',
			array(),
			CHECKFLOW_VERSION
		);

		wp_enqueue_script(
			'checkflow-checkout-ajax',
			CHECKFLOW_URL . 'public/js/checkflow-ajax.js',
			array(),
			CHECKFLOW_VERSION,
			true
		);

		wp_localize_script(
			'checkflow-checkout-ajax',
			'checkflowCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'checkflow-checkout' ),
				'strings' => array(
					'updating' => __( 'Updating total...', 'checkflow' ),
				),
			)
		);
	}
}
