<?php
/**
 * Frontend AJAX placeholder (Phase 1 target).
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Frontend_Ajax {

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
	 * Update checkout cart quantities and totals.
	 */
	public function update_order_review() {
		check_ajax_referer( 'checkflow-checkout', 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cart unavailable.', 'checkflow' ),
				),
				400
			);
		}

		$quantities = isset( $_POST['quantities'] ) ? wp_unslash( $_POST['quantities'] ) : array();
		if ( ! is_array( $quantities ) ) {
			$quantities = array();
		}

		foreach ( $quantities as $item_key => $qty ) {
			$item_key = wc_clean( (string) $item_key );
			$qty      = max( 0, absint( $qty ) );
			if ( '' === $item_key ) {
				continue;
			}
			WC()->cart->set_quantity( $item_key, $qty, false );
		}

		WC()->cart->calculate_totals();

		wp_send_json_success( $this->build_checkout_payload() );
	}

	/**
	 * Apply coupon code via AJAX.
	 */
	public function apply_coupon() {
		check_ajax_referer( 'checkflow-checkout', 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cart unavailable.', 'checkflow' ),
				),
				400
			);
		}

		$coupon = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$coupon = sanitize_text_field( $coupon );
		if ( '' === $coupon ) {
			wp_send_json_error(
				array(
					'message' => __( 'Coupon code is required.', 'checkflow' ),
				),
				400
			);
		}

		$ok = WC()->cart->apply_coupon( $coupon );
		WC()->cart->calculate_totals();

		if ( ! $ok ) {
			$msg = __( 'Failed to apply coupon.', 'checkflow' );
			if ( function_exists( 'wc_get_notices' ) ) {
				$notices = wc_get_notices( 'error' );
				if ( ! empty( $notices ) && isset( $notices[0]['notice'] ) ) {
					$msg = wp_strip_all_tags( $notices[0]['notice'] );
				}
			}
			wp_send_json_error(
				array(
					'message' => $msg,
				),
				400
			);
		}

		wp_send_json_success(
			array_merge(
				$this->build_checkout_payload(),
				array(
					'message'              => __( 'Coupon applied.', 'checkflow' ),
					'coupon_code'          => $coupon,
					'coupon_discount_html' => $this->get_coupon_discount_html( $coupon ),
				)
			)
		);
	}

	/**
	 * Remove coupon code via AJAX.
	 */
	public function remove_coupon() {
		check_ajax_referer( 'checkflow-checkout', 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array(
					'message' => __( 'Cart unavailable.', 'checkflow' ),
				),
				400
			);
		}

		$coupon = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$coupon = sanitize_text_field( $coupon );
		if ( '' === $coupon ) {
			wp_send_json_error(
				array(
					'message' => __( 'Coupon code is required.', 'checkflow' ),
				),
				400
			);
		}

		WC()->cart->remove_coupon( $coupon );
		WC()->cart->calculate_totals();

		wp_send_json_success(
			array_merge(
				$this->build_checkout_payload(),
				array(
					'message' => __( 'Coupon removed.', 'checkflow' ),
				)
			)
		);
	}

	/**
	 * Standard checkout response payload.
	 *
	 * @return array<string,mixed>
	 */
	private function build_checkout_payload() {
		$coupon_totals = array();
		foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
			$coupon_totals[ $coupon_code ] = $this->get_coupon_discount_html( $coupon_code );
		}

		return array(
			'total'         => wp_kses_post( WC()->cart->get_total() ),
			'cart_count'    => absint( WC()->cart->get_cart_contents_count() ),
			'coupons'       => array_values( WC()->cart->get_applied_coupons() ),
			'coupon_totals' => $coupon_totals,
		);
	}

	/**
	 * Coupon discount formatted as price text for review rows.
	 *
	 * @param string $coupon_code Coupon code.
	 * @return string
	 */
	private function get_coupon_discount_html( $coupon_code ) {
		$amount = (float) WC()->cart->get_coupon_discount_amount(
			(string) $coupon_code,
			WC()->cart->display_cart_ex_tax
		);
		if ( function_exists( 'wc_price' ) ) {
			return '-' . wp_strip_all_tags( wc_price( $amount ) );
		}
		return '-' . (string) $amount;
	}
}
