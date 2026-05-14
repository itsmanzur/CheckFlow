<?php
/**
 * CheckFlow local event log and browser tracking foundation.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Pixel_Tracking {

	/** @var self|null */
	private static $instance = null;

	const PURCHASE_META = '_checkflow_meta_purchase_tracked';

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
	 * Enqueue browser tracking when local log or Meta Pixel is configured.
	 */
	public function enqueue() {
		$settings = CheckFlow_Admin::instance()->get_pixel_settings();
		$meta_ready = ! empty( $settings['meta_enabled'] ) && ! empty( $settings['meta_pixel_id'] );
		if ( empty( $settings['local_enabled'] ) && ! $meta_ready ) {
			return;
		}

		$version = CHECKFLOW_VERSION . '.' . filemtime( CHECKFLOW_PATH . 'public/js/checkflow-pixel.js' );
		wp_enqueue_script(
			'checkflow-pixel',
			CHECKFLOW_URL . 'public/js/checkflow-pixel.js',
			array(),
			$version,
			true
		);

		wp_localize_script(
			'checkflow-pixel',
			'checkflowPixel',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'checkflow_pixel' ),
				'localEnabled'  => ! empty( $settings['local_enabled'] ),
				'metaEnabled'   => $meta_ready,
				'metaPixelId'   => (string) $settings['meta_pixel_id'],
				'debug'         => ! empty( $settings['debug_mode'] ),
				'providerState' => $this->provider_state( $settings ),
				'events'        => $this->get_page_events(),
			)
		);
	}

	/**
	 * Store one local browser event.
	 */
	public function ajax_log_event() {
		check_ajax_referer( 'checkflow_pixel', 'nonce' );

		$settings = CheckFlow_Admin::instance()->get_pixel_settings();
		if ( empty( $settings['local_enabled'] ) ) {
			wp_send_json_success( array( 'logged' => false, 'message' => 'Local log disabled.' ) );
		}

		$allowed = array( 'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase' );
		$name    = isset( $_POST['event_name'] ) ? sanitize_text_field( wp_unslash( $_POST['event_name'] ) ) : '';
		if ( ! in_array( $name, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown event.', 'checkflow' ) ), 400 );
		}

		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( '' === $event_id ) {
			$event_id = $this->event_id( strtolower( $name ) . '_' . wp_rand() );
		}
		$event_id = substr( $event_id, 0, 100 );
		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$context  = $this->sanitize_json_payload( isset( $_POST['context'] ) ? wp_unslash( $_POST['context'] ) : array() );

		global $wpdb;
		$table = CheckFlow_Activator::event_log_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			CheckFlow_Activator::create_event_log_table();
		}

		$exists = 'Purchase' === $name ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_id = %s LIMIT 1", $event_id ) ) : false;
		if ( $exists ) {
			wp_send_json_success( array( 'logged' => false, 'duplicate' => true, 'event_id' => $event_id ) );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'event_name'     => $name,
				'event_id'       => $event_id,
				'page_url'       => $page_url,
				'context'        => wp_json_encode( $context ),
				'provider_state' => wp_json_encode( $this->provider_state( $settings ) ),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Could not log event.', 'checkflow' ) ), 500 );
		}

		wp_send_json_success( array( 'logged' => true, 'event_id' => $event_id ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function get_page_events() {
		$events = array();

		if ( function_exists( 'is_product' ) && is_product() && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_the_ID() );
			if ( $product ) {
				$events[] = array(
					'name'     => 'ViewContent',
					'event_id' => $this->event_id( 'view_content_' . $product->get_id() ),
					'params'   => array(
						'content_ids'  => array( (string) $product->get_id() ),
						'content_name' => $product->get_name(),
						'content_type' => 'product',
						'value'        => (float) wc_get_price_to_display( $product ),
						'currency'     => get_woocommerce_currency(),
					),
				);
			}
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
			$events[] = array(
				'name'     => 'InitiateCheckout',
				'event_id' => $this->event_id( 'initiate_checkout' ),
				'params'   => $this->cart_event_params(),
			);
		}

		$purchase = $this->purchase_event();
		if ( $purchase ) {
			$events[] = $purchase;
		}

		return $events;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function cart_event_params() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array(
				'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			);
		}

		$ids = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$ids[] = (string) $item['product_id'];
			}
		}

		return array(
			'content_ids'  => array_values( array_unique( $ids ) ),
			'content_type' => 'product',
			'value'        => (float) WC()->cart->get_total( 'edit' ),
			'currency'     => get_woocommerce_currency(),
			'num_items'    => WC()->cart->get_cart_contents_count(),
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function purchase_event() {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $key && $order->get_order_key() !== $key ) {
			return null;
		}

		if ( $order->get_meta( self::PURCHASE_META, true ) ) {
			return null;
		}

		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$ids[] = (string) $product->get_id();
			}
		}

		$event_id = $this->event_id( 'purchase_' . $order->get_id() );
		$order->update_meta_data( self::PURCHASE_META, $event_id );
		$order->save();

		return array(
			'name'     => 'Purchase',
			'event_id' => $event_id,
			'params'   => array(
				'content_ids'  => array_values( array_unique( $ids ) ),
				'content_type' => 'product',
				'value'        => (float) $order->get_total(),
				'currency'     => $order->get_currency(),
				'num_items'    => $order->get_item_count(),
				'order_id'     => (string) $order->get_order_number(),
			),
		);
	}

	/**
	 * @param string $seed Event seed.
	 * @return string
	 */
	private function event_id( $seed ) {
		return 'cf_' . substr( md5( $seed . '|' . wp_salt( 'nonce' ) ), 0, 16 );
	}

	/**
	 * @param array<string,mixed> $settings Pixel settings.
	 * @return array<string,array<string,bool>>
	 */
	private function provider_state( $settings ) {
		return array(
			'checkflow' => array(
				'enabled'    => ! empty( $settings['local_enabled'] ),
				'configured' => true,
			),
			'meta'      => array(
				'enabled'    => ! empty( $settings['meta_enabled'] ),
				'configured' => ! empty( $settings['meta_pixel_id'] ),
			),
			'google'    => array(
				'enabled'    => ! empty( $settings['google_enabled'] ),
				'configured' => ! empty( $settings['google_measurement_id'] ) && ! empty( $settings['google_conversion_label'] ),
			),
			'tiktok'    => array(
				'enabled'    => ! empty( $settings['tiktok_enabled'] ),
				'configured' => ! empty( $settings['tiktok_pixel_id'] ) || ! empty( $settings['tiktok_api_token'] ),
			),
		);
	}

	/**
	 * @param mixed $payload JSON string or array.
	 * @return array<string,mixed>
	 */
	private function sanitize_json_payload( $payload ) {
		if ( is_string( $payload ) ) {
			$decoded = json_decode( $payload, true );
			$payload = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $payload ) ) {
			return array();
		}

		$clean = array();
		foreach ( $payload as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', array_map( 'strval', $value ) );
			} elseif ( is_numeric( $value ) ) {
				$clean[ $key ] = 0 + $value;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}
}
