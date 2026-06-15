<?php
/**
 * Frontend AJAX checkout engine.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Frontend_Ajax {

	const NONCE_ACTION = 'checkflow-checkout';
	const RATE_LIMIT   = 10;
	const RATE_WINDOW  = MINUTE_IN_SECONDS;

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
		$this->guard_request( 'update_order_review' );

		if ( ! $this->cart_available() ) {
			$this->send_error( __( 'Cart unavailable.', 'checkflow' ), array(), 400 );
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

		$this->send_success(
			$this->build_checkout_payload(),
			__( 'Checkout totals updated.', 'checkflow' )
		);
	}

	/**
	 * Apply coupon code via AJAX.
	 */
	public function apply_coupon() {
		$this->guard_request( 'apply_coupon' );

		if ( ! $this->cart_available() ) {
			$this->send_error( __( 'Cart unavailable.', 'checkflow' ), array(), 400 );
		}

		$coupon = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$coupon = sanitize_text_field( $coupon );
		if ( '' === $coupon ) {
			$this->send_error( __( 'Coupon code is required.', 'checkflow' ), array( 'coupon_code' ), 400 );
		}

		$ok = WC()->cart->apply_coupon( $coupon );
		WC()->cart->calculate_totals();

		if ( ! $ok ) {
			$msg = $this->pull_wc_error_notice( __( 'Failed to apply coupon.', 'checkflow' ) );
			$this->send_error( $msg, array( 'coupon_code' ), 400 );
		}

		$this->send_success(
			array_merge(
				$this->build_checkout_payload(),
				array(
					'coupon_code'          => $coupon,
					'coupon_discount_html' => $this->get_coupon_discount_html( $coupon ),
				)
			),
			__( 'Coupon applied.', 'checkflow' )
		);
	}

	/**
	 * Remove coupon code via AJAX.
	 */
	public function remove_coupon() {
		$this->guard_request( 'remove_coupon' );

		if ( ! $this->cart_available() ) {
			$this->send_error( __( 'Cart unavailable.', 'checkflow' ), array(), 400 );
		}

		$coupon = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$coupon = sanitize_text_field( $coupon );
		if ( '' === $coupon ) {
			$this->send_error( __( 'Coupon code is required.', 'checkflow' ), array( 'coupon_code' ), 400 );
		}

		WC()->cart->remove_coupon( $coupon );
		WC()->cart->calculate_totals();

		$this->send_success(
			$this->build_checkout_payload(),
			__( 'Coupon removed.', 'checkflow' )
		);
	}

	/**
	 * Validate a single checkout field.
	 */
	public function validate_field() {
		$this->guard_request( 'validate_field' );

		$field    = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value    = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
		$type     = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : $this->guess_field_type( $field );
		$required = isset( $_POST['required'] ) ? (bool) absint( $_POST['required'] ) : $this->is_required_field( $field );
		$errors   = array();

		if ( '' === $field ) {
			$this->send_error( __( 'Field key is required.', 'checkflow' ), array( 'field' ), 400 );
		}

		if ( $required && '' === trim( $value ) ) {
			$errors[] = __( 'This field is required.', 'checkflow' );
		}

		if ( '' !== $value ) {
			if ( 'email' === $type && ! is_email( $value ) ) {
				$errors[] = __( 'Please enter a valid email address.', 'checkflow' );
			}
			if ( 'tel' === $type && ! preg_match( '/^[0-9+\-\s().]{6,20}$/', $value ) ) {
				$errors[] = __( 'Please enter a valid phone number.', 'checkflow' );
			}
			if ( 'postcode' === $type && strlen( $value ) > 20 ) {
				$errors[] = __( 'Postcode is too long.', 'checkflow' );
			}
		}

		if ( ! empty( $errors ) ) {
			$this->send_error(
				__( 'Field validation failed.', 'checkflow' ),
				array(
					$field => $errors,
				),
				422
			);
		}

		$this->send_success(
			array(
				'field' => $field,
				'valid' => true,
			),
			__( 'Field is valid.', 'checkflow' )
		);
	}

	/**
	 * Return available WooCommerce shipping methods for posted address data.
	 */
	public function get_shipping_methods() {
		$this->guard_request( 'get_shipping_methods' );

		if ( ! $this->cart_available() || ! WC()->customer ) {
			$this->send_error( __( 'Shipping is unavailable.', 'checkflow' ), array(), 400 );
		}

		$address = array(
			'country'   => $this->posted_address_value( 'country', WC()->customer->get_shipping_country() ),
			'state'     => $this->posted_address_value( 'state', WC()->customer->get_shipping_state() ),
			'postcode'  => $this->posted_address_value( 'postcode', WC()->customer->get_shipping_postcode() ),
			'city'      => $this->posted_address_value( 'city', WC()->customer->get_shipping_city() ),
			'address_1' => $this->posted_address_value( 'address_1', WC()->customer->get_shipping_address_1() ),
			'address_2' => $this->posted_address_value( 'address_2', WC()->customer->get_shipping_address_2() ),
		);

		WC()->customer->set_shipping_country( $address['country'] );
		WC()->customer->set_shipping_state( $address['state'] );
		WC()->customer->set_shipping_postcode( $address['postcode'] );
		WC()->customer->set_shipping_city( $address['city'] );
		WC()->customer->set_shipping_address_1( $address['address_1'] );
		WC()->customer->set_shipping_address_2( $address['address_2'] );
		WC()->customer->save();

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$methods = array();
		foreach ( WC()->shipping()->get_packages() as $package_index => $package ) {
			if ( empty( $package['rates'] ) ) {
				continue;
			}
			foreach ( $package['rates'] as $rate_id => $rate ) {
				$methods[] = array(
					'id'      => (string) $rate_id,
					'label'   => wp_strip_all_tags( $rate->get_label() ),
					'cost'    => (float) $rate->get_cost(),
					'html'    => wp_kses_post( wc_price( (float) $rate->get_cost() ) ),
					'package' => absint( $package_index ),
				);
			}
		}

		$this->send_success(
			array_merge(
				$this->build_checkout_payload(),
				array(
					'shipping_methods' => $methods,
				)
			),
			__( 'Shipping methods loaded.', 'checkflow' )
		);
	}

	/**
	 * Add the configured order bump product to cart.
	 */
	public function add_order_bump() {
		$this->guard_request( 'add_order_bump' );

		if ( ! $this->cart_available() || ! function_exists( 'wc_get_product' ) ) {
			$this->send_error( __( 'Cart unavailable.', 'checkflow' ), array(), 400 );
		}

		$settings   = CheckFlow_Admin::instance()->get_order_bump_settings();
		$product_id = absint( apply_filters( 'checkflow_order_bump_product_id', $settings['product_id'] ) );
		$posted_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( empty( $settings['enabled'] ) || ! $product_id || $posted_id !== $product_id ) {
			$this->send_error( __( 'Order bump is not configured.', 'checkflow' ), array( 'product_id' ), 400 );
		}
		if ( ! $this->order_bump_rules_match( $settings ) ) {
			$this->send_error( __( 'This order bump is not available for the current cart.', 'checkflow' ), array( 'product_id' ), 400 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			$this->send_error( __( 'Order bump product is unavailable.', 'checkflow' ), array( 'product_id' ), 400 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( absint( $cart_item['product_id'] ) === $product_id ) {
				WC()->cart->calculate_totals();
				$this->send_success(
					$this->build_checkout_payload(),
					__( 'Order bump already added.', 'checkflow' )
				);
			}
		}

		$added = WC()->cart->add_to_cart( $product_id, 1 );
		if ( ! $added ) {
			$this->send_error( __( 'Could not add order bump.', 'checkflow' ), array( 'product_id' ), 400 );
		}

		WC()->cart->calculate_totals();
		$this->send_success(
			$this->build_checkout_payload(),
			__( 'Order bump added.', 'checkflow' )
		);
	}

	/**
	 * Add the configured upsell/downsell product to cart.
	 */
	public function accept_upsell() {
		$this->guard_request( 'accept_upsell' );

		if ( ! $this->cart_available() || ! function_exists( 'wc_get_product' ) ) {
			$this->send_error( __( 'Cart unavailable.', 'checkflow' ), array(), 400 );
		}

		$settings = CheckFlow_Admin::instance()->get_upsell_settings();
		$slot     = isset( $_POST['slot'] ) ? sanitize_key( wp_unslash( $_POST['slot'] ) ) : 'main';
		$slot     = in_array( $slot, array( 'main', 'downsell' ), true ) ? $slot : 'main';
		$product_id = 'downsell' === $slot ? absint( $settings['downsell_product_id'] ) : absint( $settings['offer_product_id'] );
		$posted_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( empty( $settings['enabled'] ) || ! $product_id || $posted_id !== $product_id ) {
			$this->send_error( __( 'Upsell offer is not configured.', 'checkflow' ), array( 'product_id' ), 400 );
		}
		if ( 'pre_purchase' === $settings['flow_type'] && ! $this->upsell_rules_match( $settings ) ) {
			$this->send_error( __( 'This upsell offer is not available for the current cart.', 'checkflow' ), array( 'product_id' ), 400 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			$this->send_error( __( 'Upsell product is unavailable.', 'checkflow' ), array( 'product_id' ), 400 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( absint( $cart_item['product_id'] ) === $product_id ) {
				WC()->cart->calculate_totals();
				$this->send_success(
					array_merge(
						$this->build_checkout_payload(),
						array(
							'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
							'slot'         => $slot,
						)
					),
					__( 'Upsell offer already added.', 'checkflow' )
				);
			}
		}

		$base_price       = (float) $product->get_price();
		$discounted_price = $this->discounted_upsell_price( $base_price, (string) $settings['discount_type'], (float) $settings['discount_value'] );
		$cart_item_data   = array(
			'checkflow_upsell'                => true,
			'checkflow_upsell_slot'           => $slot,
			'checkflow_upsell_discount_type'  => (string) $settings['discount_type'],
			'checkflow_upsell_discount_value' => (string) $settings['discount_value'],
			'checkflow_upsell_original_price' => (string) $base_price,
			'checkflow_upsell_discount_price' => (string) $discounted_price,
		);

		$added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
		if ( ! $added ) {
			$this->send_error( __( 'Could not add upsell offer.', 'checkflow' ), array( 'product_id' ), 400 );
		}
		$this->record_upsell_acceptance( 'downsell' === $slot ? 'downsell_accepted' : 'accepted', $base_price, $discounted_price );
		if ( WC()->session ) {
			WC()->session->set( 'checkflow_upsell_' . $slot . '_accepted', time() );
		}

		WC()->cart->calculate_totals();
		$this->send_success(
			array_merge(
				$this->build_checkout_payload(),
				array(
					'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
					'slot'         => $slot,
				)
			),
			__( 'Upsell offer added.', 'checkflow' )
		);
	}

	/**
	 * Track lightweight local upsell events for admin performance cards.
	 */
	public function track_upsell_event() {
		$this->guard_request( 'track_upsell_event' );

		$event = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
		if ( ! in_array( $event, array( 'shown', 'skipped', 'downsell_shown' ), true ) ) {
			$this->send_error( __( 'Invalid upsell event.', 'checkflow' ), array( 'event' ), 400 );
		}

		$stats = $this->record_upsell_event( $event );
		$this->send_success(
			array(
				'event' => $event,
				'stats' => $stats,
			),
			__( 'Upsell event tracked.', 'checkflow' )
		);
	}

	/**
	 * @param string $event Event key.
	 * @return array<string,int>
	 */
	private function record_upsell_event( $event ) {
		$allowed = array( 'shown', 'accepted', 'skipped', 'downsell_shown', 'downsell_accepted' );
		if ( ! in_array( $event, $allowed, true ) ) {
			return CheckFlow_Admin::instance()->get_upsell_stats();
		}
		$stats = CheckFlow_Admin::instance()->get_upsell_stats();
		$stats[ $event ] = isset( $stats[ $event ] ) ? absint( $stats[ $event ] ) + 1 : 1;
		update_option( CheckFlow_Admin::UPSELL_STATS_OPTION, $stats, false );
		return $stats;
	}

	/**
	 * Track an accepted offer with lightweight money metrics for the admin cards.
	 *
	 * @param string $event Accepted event key.
	 * @param float  $base_price Original unit price.
	 * @param float  $discounted_price Final unit price.
	 * @return array<string,int>
	 */
	private function record_upsell_acceptance( $event, $base_price, $discounted_price ) {
		$stats = $this->record_upsell_event( $event );
		$revenue_cents  = max( 0, (int) round( $discounted_price * 100 ) );
		$discount_cents = max( 0, (int) round( ( $base_price - $discounted_price ) * 100 ) );

		$stats['revenue_cents'] = isset( $stats['revenue_cents'] ) ? absint( $stats['revenue_cents'] ) + $revenue_cents : $revenue_cents;
		$stats['discount_cents'] = isset( $stats['discount_cents'] ) ? absint( $stats['discount_cents'] ) + $discount_cents : $discount_cents;
		update_option( CheckFlow_Admin::UPSELL_STATS_OPTION, $stats, false );

		return $stats;
	}

	/**
	 * @param float  $base_price Base unit price.
	 * @param string $discount_type Discount type.
	 * @param float  $discount_value Discount value.
	 * @return float
	 */
	private function discounted_upsell_price( $base_price, $discount_type, $discount_value ) {
		if ( $base_price <= 0 || $discount_value <= 0 ) {
			return $base_price;
		}
		if ( 'percent' === $discount_type ) {
			$discount_value = min( 100, max( 0, $discount_value ) );
			return max( 0, $base_price - ( $base_price * ( $discount_value / 100 ) ) );
		}
		if ( 'fixed' === $discount_type ) {
			return max( 0, $base_price - $discount_value );
		}
		return $base_price;
	}

	/**
	 * @param array<string,mixed> $settings Order bump settings.
	 * @return bool
	 */
	private function order_bump_rules_match( $settings ) {
		$total = (float) WC()->cart->get_subtotal();
		if ( '' !== (string) $settings['min_total'] && $total < (float) $settings['min_total'] ) {
			return false;
		}
		if ( '' !== (string) $settings['max_total'] && $total > (float) $settings['max_total'] ) {
			return false;
		}

		$cart_product_ids = $this->cart_product_ids();
		$include_products = $this->csv_to_ints( $settings['include_products'] );
		if ( $include_products && ! array_intersect( $include_products, $cart_product_ids ) ) {
			return false;
		}
		$exclude_products = $this->csv_to_ints( $settings['exclude_products'] );
		if ( $exclude_products && array_intersect( $exclude_products, $cart_product_ids ) ) {
			return false;
		}
		$include_categories = $this->csv_to_ints( $settings['include_categories'] );
		if ( $include_categories && ! $this->cart_has_categories( $include_categories ) ) {
			return false;
		}
		$countries = $this->csv_to_strings( $settings['countries'] );
		if ( $countries && ! in_array( $this->checkout_country(), $countries, true ) ) {
			return false;
		}
		$payment_methods = $this->csv_to_strings( $settings['payment_methods'] );
		if ( $payment_methods && ! in_array( $this->chosen_payment_method(), $payment_methods, true ) ) {
			return false;
		}
		if ( 'guest' === $settings['customer_rule'] && is_user_logged_in() ) {
			return false;
		}
		if ( 'logged_in' === $settings['customer_rule'] && ! is_user_logged_in() ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $settings Upsell settings.
	 * @return bool
	 */
	private function upsell_rules_match( $settings ) {
		$total = (float) WC()->cart->get_subtotal();
		if ( '' !== (string) $settings['trigger_min_total'] && $total < (float) $settings['trigger_min_total'] ) {
			return false;
		}
		if ( '' !== (string) $settings['trigger_max_total'] && $total > (float) $settings['trigger_max_total'] ) {
			return false;
		}

		$cart_product_ids = $this->cart_product_ids();
		$trigger_products = $this->csv_to_ints( $settings['trigger_products'] );
		if ( $trigger_products && ! array_intersect( $trigger_products, $cart_product_ids ) ) {
			return false;
		}
		$trigger_categories = $this->csv_to_ints( $settings['trigger_categories'] );
		if ( $trigger_categories && ! $this->cart_has_categories( $trigger_categories ) ) {
			return false;
		}
		$countries = $this->csv_to_strings( $settings['countries'] );
		if ( $countries && ! in_array( $this->checkout_country(), $countries, true ) ) {
			return false;
		}
		$payment_methods = $this->csv_to_strings( $settings['payment_methods'] );
		if ( $payment_methods && ! in_array( $this->chosen_payment_method(), $payment_methods, true ) ) {
			return false;
		}
		if ( 'guest' === $settings['customer_rule'] && is_user_logged_in() ) {
			return false;
		}
		if ( 'logged_in' === $settings['customer_rule'] && ! is_user_logged_in() ) {
			return false;
		}
		return true;
	}

	/**
	 * @return array<int,int>
	 */
	private function cart_product_ids() {
		$ids = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$ids[] = absint( $cart_item['product_id'] );
			if ( ! empty( $cart_item['variation_id'] ) ) {
				$ids[] = absint( $cart_item['variation_id'] );
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param array<int,int> $category_ids Category IDs.
	 * @return bool
	 */
	private function cart_has_categories( $category_ids ) {
		foreach ( $this->cart_product_ids() as $product_id ) {
			$product_categories = wc_get_product_term_ids( $product_id, 'product_cat' );
			if ( array_intersect( array_map( 'absint', $product_categories ), $category_ids ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string
	 */
	private function checkout_country() {
		$country = WC()->customer ? WC()->customer->get_shipping_country() : '';
		if ( '' === $country && WC()->customer ) {
			$country = WC()->customer->get_billing_country();
		}
		return strtolower( (string) $country );
	}

	/**
	 * @return string
	 */
	private function chosen_payment_method() {
		return WC()->session ? sanitize_key( (string) WC()->session->get( 'chosen_payment_method', '' ) ) : '';
	}

	/**
	 * @param mixed $value CSV value.
	 * @return array<int,int>
	 */
	private function csv_to_ints( $value ) {
		return array_values( array_filter( array_map( 'absint', preg_split( '/[,\s]+/', (string) $value ) ) ) );
	}

	/**
	 * @param mixed $value CSV value.
	 * @return array<int,string>
	 */
	private function csv_to_strings( $value ) {
		$items = preg_split( '/[,\s]+/', strtolower( (string) $value ) );
		return array_values( array_filter( array_map( 'sanitize_key', is_array( $items ) ? $items : array() ) ) );
	}

	/**
	 * Securely submit WooCommerce checkout.
	 *
	 * WooCommerce owns the full payment/order processing flow here; CheckFlow
	 * provides nonce/rate-limit guardrails before handing off.
	 */
	public function place_order() {
		$this->guard_request( 'place_order' );

		if ( ! function_exists( 'WC' ) || ! WC()->checkout() ) {
			$this->send_error( __( 'Checkout unavailable.', 'checkflow' ), array(), 400 );
		}

		if ( empty( $_POST['woocommerce-process-checkout-nonce'] ) && ! empty( $_POST['woocommerce_checkout_nonce'] ) ) {
			$_POST['woocommerce-process-checkout-nonce'] = sanitize_text_field( wp_unslash( $_POST['woocommerce_checkout_nonce'] ) );
		}

		WC()->checkout()->process_checkout();
	}

	/**
	 * Verify nonce and rate limit.
	 *
	 * @param string $action Action name.
	 */
	private function guard_request( $action ) {
		if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			$this->log_security_event( 'invalid_nonce_' . $action );
			$this->send_error(
				__( 'Checkout security check failed. Please refresh and try again.', 'checkflow' ),
				array( 'nonce' ),
				403
			);
		}

		if ( ! $this->check_rate_limit( $action ) ) {
			$this->log_security_event( 'rate_limited_' . $action );
			$this->send_error(
				__( 'Too many checkout requests. Please wait a moment and try again.', 'checkflow' ),
				array( 'rate_limit' ),
				429
			);
		}
	}

	/**
	 * @return bool
	 */
	private function cart_available() {
		return function_exists( 'WC' ) && WC()->cart;
	}

	/**
	 * @param string $action Action name.
	 * @return bool
	 */
	private function check_rate_limit( $action ) {
		$key   = 'checkflow_rate_' . md5( $this->client_fingerprint() . '|' . sanitize_key( $action ) );
		$count = absint( get_transient( $key ) );
		$limit = in_array( $action, array( 'validate_field', 'track_upsell_event' ), true ) ? 60 : self::RATE_LIMIT;

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	/**
	 * @return string
	 */
	private function client_fingerprint() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			if ( $customer_id ) {
				return $ip . '|' . $customer_id;
			}
		}
		return $ip;
	}

	/**
	 * @param string $event Event name.
	 */
	private function log_security_event( $event ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'CheckFlow checkout security event: %s from %s',
					sanitize_key( $event ),
					$this->client_fingerprint()
				)
			);
		}
	}

	/**
	 * @param array<string,mixed> $data Payload.
	 * @param string              $message Message.
	 * @param int                 $status HTTP status.
	 */
	private function send_success( array $data, $message = '', $status = 200 ) {
		if ( '' !== $message ) {
			$data['message'] = $message;
		}
		wp_send_json(
			array(
				'success' => true,
				'data'    => $data,
				'message' => $message,
				'errors'  => array(),
			),
			$status
		);
	}

	/**
	 * @param string              $message Message.
	 * @param array<int|string,mixed> $errors Errors.
	 * @param int                 $status HTTP status.
	 */
	private function send_error( $message, array $errors = array(), $status = 400 ) {
		wp_send_json(
			array(
				'success' => false,
				'data'    => array(
					'message' => $message,
				),
				'message' => $message,
				'errors'  => $errors,
			),
			$status
		);
	}

	/**
	 * @param string $fallback Fallback message.
	 * @return string
	 */
	private function pull_wc_error_notice( $fallback ) {
		if ( function_exists( 'wc_get_notices' ) ) {
			$notices = wc_get_notices( 'error' );
			if ( ! empty( $notices ) && isset( $notices[0]['notice'] ) ) {
				wc_clear_notices();
				return wp_strip_all_tags( $notices[0]['notice'] );
			}
		}
		return $fallback;
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
			'subtotal'      => wp_kses_post( WC()->cart->get_cart_subtotal() ),
			'shipping'      => wp_kses_post( WC()->cart->get_cart_shipping_total() ),
			'discount'      => wp_kses_post( wc_price( (float) WC()->cart->get_discount_total() ) ),
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

	/**
	 * Read either normalized or WooCommerce checkout address keys.
	 *
	 * @param string $key Address suffix.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private function posted_address_value( $key, $fallback = '' ) {
		$keys = array( $key, 'shipping_' . $key, 'billing_' . $key );
		foreach ( $keys as $posted_key ) {
			if ( isset( $_POST[ $posted_key ] ) ) {
				return wc_clean( wp_unslash( $_POST[ $posted_key ] ) );
			}
		}
		return wc_clean( $fallback );
	}

	/**
	 * @param string $field Field key.
	 * @return string
	 */
	private function guess_field_type( $field ) {
		if ( false !== strpos( $field, 'email' ) ) {
			return 'email';
		}
		if ( false !== strpos( $field, 'phone' ) || false !== strpos( $field, 'tel' ) ) {
			return 'tel';
		}
		if ( false !== strpos( $field, 'postcode' ) || false !== strpos( $field, 'zip' ) ) {
			return 'postcode';
		}
		return 'text';
	}

	/**
	 * @param string $field Field key.
	 * @return bool
	 */
	private function is_required_field( $field ) {
		$required = array(
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_city',
			'billing_phone',
			'billing_email',
		);
		return in_array( $field, $required, true );
	}
}
