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
		$settings          = CheckFlow_Admin::instance()->get_quick_settings();
		$is_checkout_page  = function_exists( 'is_checkout' ) && is_checkout();
		$is_order_received = function_exists( 'is_order_received_page' ) && is_order_received_page();
		$is_checkout       = $is_checkout_page && ! $is_order_received;
		$needs_popup       = ! empty( $settings['popup_checkout'] ) && ! $is_checkout_page;

		if ( ! $is_checkout && ! $needs_popup ) {
			return;
		}

		$checkout_css_version  = CHECKFLOW_VERSION . '.' . filemtime( CHECKFLOW_PATH . 'public/css/checkflow.css' );
		$checkout_ajax_version = CHECKFLOW_VERSION . '.' . filemtime( CHECKFLOW_PATH . 'public/js/checkflow-ajax.js' );
		$checkout_app_version  = CHECKFLOW_VERSION . '.' . filemtime( CHECKFLOW_PATH . 'public/js/checkflow.js' );
		$storefront_version    = CHECKFLOW_VERSION . '.' . filemtime( CHECKFLOW_PATH . 'public/js/checkflow-storefront.js' );

		wp_enqueue_style(
			'checkflow-checkout',
			CHECKFLOW_URL . 'public/css/checkflow.css',
			array(),
			$checkout_css_version
		);

		if ( $needs_popup ) {
			wp_enqueue_script(
				'checkflow-storefront',
				CHECKFLOW_URL . 'public/js/checkflow-storefront.js',
				array( 'jquery' ),
				$storefront_version,
				true
			);
			wp_localize_script(
				'checkflow-storefront',
				'checkflowStorefront',
				array(
					'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
					'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
					'strings'     => array(
						'title'       => __( 'Added to cart', 'checkflow' ),
						'description' => __( 'Your item is ready. Choose your next step.', 'checkflow' ),
						'checkout'    => __( 'Checkout now', 'checkflow' ),
						'cart'        => __( 'View cart', 'checkflow' ),
						'continue'    => __( 'Continue shopping', 'checkflow' ),
					),
				)
			);
		}

		if ( ! $is_checkout ) {
			return;
		}

		wp_enqueue_script(
			'checkflow-checkout-ajax',
			CHECKFLOW_URL . 'public/js/checkflow-ajax.js',
			array(),
			$checkout_ajax_version,
			true
		);

		wp_enqueue_script(
			'checkflow-checkout-app',
			CHECKFLOW_URL . 'public/js/checkflow.js',
			array( 'checkflow-checkout-ajax' ),
			$checkout_app_version,
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
