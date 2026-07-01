<?php
/**
 * CheckFlow checkout analytics event foundation.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Analytics {

	/** @var self|null */
	private static $instance = null;

	/** @var array<int,string> */
	private $allowed_events = array(
		'checkout_view',
		'checkout_started',
		'payment_selected',
		'coupon_applied',
		'coupon_removed',
		'order_placed',
		'cart_updated',
		'add_to_cart',
	);

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
	 * Enqueue browser-side checkout analytics.
	 */
	public function enqueue() {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		$path    = CHECKFLOW_PATH . 'public/js/checkflow-analytics.js';
		$version = CHECKFLOW_VERSION . '.' . ( file_exists( $path ) ? filemtime( $path ) : time() );

		wp_enqueue_script(
			'checkflow-analytics',
			CHECKFLOW_URL . 'public/js/checkflow-analytics.js',
			array(),
			$version,
			true
		);

		wp_localize_script(
			'checkflow-analytics',
			'checkflowAnalytics',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'checkflow_analytics' ),
				'checkoutUrl'  => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
				'isCheckout'   => function_exists( 'is_checkout' ) && is_checkout() && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() ),
				'isOrderDone'  => function_exists( 'is_order_received_page' ) && is_order_received_page(),
				'cartContext'  => $this->cart_context(),
			)
		);
	}

	/**
	 * Browser AJAX logging endpoint.
	 */
	public function ajax_log_event() {
		check_ajax_referer( 'checkflow_analytics', 'nonce' );

		$event_name = isset( $_POST['event_name'] ) ? sanitize_key( wp_unslash( $_POST['event_name'] ) ) : '';
		if ( ! in_array( $event_name, $this->allowed_events, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown analytics event.', 'checkflow' ) ), 400 );
		}

		$context = $this->sanitize_json_payload( isset( $_POST['context'] ) ? wp_unslash( $_POST['context'] ) : array() );
		$logged  = $this->log_event(
			$event_name,
			array(
				'event_id' => isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '',
				'source'   => 'browser',
				'page_url' => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
				'context'  => $context,
			)
		);

		if ( ! $logged ) {
			wp_send_json_error( array( 'message' => __( 'Could not log analytics event.', 'checkflow' ) ), 500 );
		}

		wp_send_json_success( array( 'logged' => true ) );
	}

	/**
	 * Log coupon application without storing shopper PII.
	 *
	 * @param string $coupon Coupon code.
	 */
	public function track_coupon_applied( $coupon ) {
		$this->log_event(
			'coupon_applied',
			array(
				'source'  => 'server',
				'context' => array(
					'coupon' => wc_format_coupon_code( $coupon ),
				),
			)
		);
	}

	/**
	 * Log coupon removal without storing shopper PII.
	 *
	 * @param string $coupon Coupon code.
	 */
	public function track_coupon_removed( $coupon ) {
		$this->log_event(
			'coupon_removed',
			array(
				'source'  => 'server',
				'context' => array(
					'coupon' => wc_format_coupon_code( $coupon ),
				),
			)
		);
	}

	/**
	 * Log successful order placement.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function track_order_placed( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->log_event(
			'order_placed',
			array(
				'source'   => 'server',
				'order_id' => $order->get_id(),
				'value'    => (float) $order->get_total(),
				'currency' => $order->get_currency(),
				'context'  => array(
					'status'        => $order->get_status(),
					'payment'       => $order->get_payment_method(),
					'item_count'    => count( $order->get_items() ),
					'coupon_count'  => count( $order->get_coupon_codes() ),
				),
			)
		);
	}

	/**
	 * Store one normalized analytics event.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $args Event args.
	 * @return bool
	 */
	public function log_event( $event_name, $args = array() ) {
		if ( ! in_array( $event_name, $this->allowed_events, true ) ) {
			return false;
		}

		global $wpdb;
		$table = $this->ensure_table();
		$args  = is_array( $args ) ? $args : array();

		$context = isset( $args['context'] ) && is_array( $args['context'] ) ? $this->sanitize_context( $args['context'] ) : array();
		$cart    = $this->cart_context();
		$event_id = isset( $args['event_id'] ) && '' !== $args['event_id'] ? sanitize_text_field( (string) $args['event_id'] ) : $this->event_id( $event_name );

		$inserted = $wpdb->insert(
			$table,
			array(
				'event_name' => sanitize_key( $event_name ),
				'event_id'   => substr( $event_id, 0, 100 ),
				'source'     => isset( $args['source'] ) ? sanitize_key( (string) $args['source'] ) : 'server',
				'page_url'   => isset( $args['page_url'] ) ? esc_url_raw( (string) $args['page_url'] ) : '',
				'session_id' => $this->session_id(),
				'cart_hash'  => $cart['cart_hash'],
				'order_id'   => isset( $args['order_id'] ) ? absint( $args['order_id'] ) : null,
				'value'      => isset( $args['value'] ) ? (float) $args['value'] : $cart['value'],
				'currency'   => isset( $args['currency'] ) ? sanitize_text_field( (string) $args['currency'] ) : $cart['currency'],
				'context'    => wp_json_encode( array_merge( $cart, $context ) ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s' )
		);

		return (bool) $inserted;
	}

	/**
	 * @return string
	 */
	private function ensure_table() {
		global $wpdb;
		$table = CheckFlow_Activator::analytics_events_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			CheckFlow_Activator::create_analytics_events_table();
		}
		return $table;
	}

	/**
	 * @return bool
	 */
	private function should_enqueue() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			return true;
		}
		return false;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function cart_context() {
		$context = array(
			'cart_hash'   => '',
			'value'       => null,
			'currency'    => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'item_count'  => 0,
			'product_ids' => array(),
		);
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $context;
		}

		$product_ids = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$product_ids[] = absint( $item['product_id'] );
			}
		}

		$context['cart_hash']   = md5( wp_json_encode( WC()->cart->get_cart_for_session() ) );
		$context['value']       = (float) WC()->cart->get_total( 'edit' );
		$context['item_count']  = absint( WC()->cart->get_cart_contents_count() );
		$context['product_ids'] = array_values( array_unique( array_filter( $product_ids ) ) );
		return $context;
	}

	/**
	 * @return string
	 */
	private function session_id() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return substr( hash( 'sha256', (string) WC()->session->get_customer_id() ), 0, 64 );
		}
		return '';
	}

	/**
	 * @param string $event_name Event name.
	 * @return string
	 */
	private function event_id( $event_name ) {
		return substr( 'cfa_' . sanitize_key( $event_name ) . '_' . wp_generate_uuid4(), 0, 100 );
	}

	/**
	 * @param mixed $payload JSON payload.
	 * @return array<string,mixed>
	 */
	private function sanitize_json_payload( $payload ) {
		if ( is_string( $payload ) ) {
			$decoded = json_decode( wp_unslash( $payload ), true );
			$payload = is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $payload ) ? $this->sanitize_context( $payload ) : array();
	}

	/**
	 * Remove PII-like keys and sanitize scalar context.
	 *
	 * @param array<string,mixed> $context Raw context.
	 * @return array<string,mixed>
	 */
	private function sanitize_context( $context ) {
		$blocked = array( 'name', 'email', 'phone', 'address', 'address_1', 'address_2', 'first_name', 'last_name' );
		$clean   = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || in_array( $key, $blocked, true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->is_list_array( $value ) ? array_values( array_map( 'sanitize_text_field', array_map( 'strval', $value ) ) ) : $this->sanitize_context( $value );
			} elseif ( is_bool( $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_numeric( $value ) ) {
				$clean[ $key ] = 0 + $value;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}

	/**
	 * PHP 7.4-compatible array_is_list.
	 *
	 * @param array<mixed> $value Array value.
	 * @return bool
	 */
	private function is_list_array( $value ) {
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
