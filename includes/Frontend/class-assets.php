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
	 * Detect checkout pages even when WooCommerce conditionals are late or theme-filtered.
	 *
	 * @return bool
	 */
	private function is_checkout_request() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'is_page' ) && is_page( 'checkout' ) ) {
			return true;
		}

		$post = get_post();
		if ( $post && isset( $post->post_content ) ) {
			$content = (string) $post->post_content;
			return false !== strpos( $content, '[woocommerce_checkout]' ) || false !== strpos( $content, 'wp:woocommerce/checkout' );
		}

		return false;
	}

	/**
	 * Conditionally enqueue checkout assets.
	 */
	public function enqueue() {
		$settings          = CheckFlow_Admin::instance()->get_quick_settings();
		$upsell_settings   = CheckFlow_Admin::instance()->get_upsell_settings();
		$is_checkout_page  = $this->is_checkout_request();
		$is_order_received = function_exists( 'is_order_received_page' ) && is_order_received_page();
		$is_checkout       = $is_checkout_page && ! $is_order_received;
		$has_order_upsell  = $is_order_received && ! empty( $upsell_settings['enabled'] ) && 'post_purchase' === $upsell_settings['flow_type'];
		$storefront_mode   = '';

		if ( ! $is_checkout_page ) {
			if ( ! empty( $settings['popup_checkout'] ) ) {
				$storefront_mode = 'popup';
			} elseif ( ! empty( $settings['slide_checkout'] ) ) {
				$storefront_mode = 'slide';
			}
		}

		if ( ! $is_checkout && ! $has_order_upsell && '' === $storefront_mode ) {
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

		if ( '' !== $storefront_mode ) {
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
					'mode'        => $storefront_mode,
					'strings'     => array(
						'title'       => __( 'Added to cart', 'checkflow' ),
						'description' => __( 'Your item is ready. Choose your next step.', 'checkflow' ),
						'slideTitle'  => __( 'Cart updated', 'checkflow' ),
						'slideDesc'   => __( 'Your item was added successfully.', 'checkflow' ),
						'summaryTitle' => __( 'Ready for checkout', 'checkflow' ),
						'summaryDesc' => __( 'Review your cart or continue shopping.', 'checkflow' ),
						'checkout'    => __( 'Checkout now', 'checkflow' ),
						'cart'        => __( 'View cart', 'checkflow' ),
						'continue'    => __( 'Continue shopping', 'checkflow' ),
					),
				)
			);
		}

		if ( ! $is_checkout && ! $has_order_upsell ) {
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
				'fieldMeta' => class_exists( 'CheckFlow_Field_Editor' ) ? CheckFlow_Field_Editor::instance()->get_checkout_field_meta() : array(),
				'cartContext' => class_exists( 'CheckFlow_Field_Editor' ) ? CheckFlow_Field_Editor::instance()->get_cart_context() : array(),
				'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
				'strings' => array(
					'updating' => __( 'Updating total...', 'checkflow' ),
					'upsellAdded' => __( 'Offer added. Redirecting to checkout...', 'checkflow' ),
				),
			)
		);
	}
}
