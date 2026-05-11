<?php
/**
 * Admin menu, assets, screen render.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CheckFlow_Admin
 */
final class CheckFlow_Admin {

	const PAGE_SLUG = 'checkflow-dashboard';

	const SETTINGS_OPTION = 'checkflow_quick_settings';

	const TEMPLATE_OPTION = 'checkflow_checkout_template';

	const COURIER_SETTINGS_OPTION = 'checkflow_courier_settings';

	/** @var self|null */
	private static $instance;

	private function __construct() {}

	/** @return self */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * @return string
	 */
	public static function caps() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return 'manage_woocommerce';
		}
		return 'manage_options';
	}

	public function register_menu() {
		add_menu_page(
			checkflow_str( 'common.checkflow' ),
			checkflow_str( 'common.checkflow' ),
			self::caps(),
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-cart',
			56
		);

		$sections = $this->get_sections();
		foreach ( $sections as $slug => $section ) {
			if ( self::PAGE_SLUG === $slug ) {
				continue;
			}
			add_submenu_page(
				self::PAGE_SLUG,
				$section['title'],
				$section['title'],
				self::caps(),
				$slug,
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->is_checkflow_hook( $hook ) ) {
			return;
		}

		$admin_css_version = CHECKFLOW_VERSION . '.' . filemtime( CHECKFLOW_PATH . 'assets/admin.css' );
		$admin_js_version  = CHECKFLOW_VERSION . '.' . filemtime( CHECKFLOW_PATH . 'assets/admin.js' );

		wp_enqueue_style(
			'checkflow-admin-google-font',
			'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'checkflow-admin',
			CHECKFLOW_URL . 'assets/admin.css',
			array(),
			$admin_css_version
		);

		wp_register_script(
			'checkflow-admin',
			CHECKFLOW_URL . 'assets/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			$admin_js_version,
			true
		);

		$i18n = CheckFlow_I18n::instance();
		$loc  = $i18n->get_active_admin_locale();
		wp_localize_script(
			'checkflow-admin',
			'checkflowAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'checkflow_admin' ),
				'locale'  => $loc,
				'settings' => $this->get_quick_settings(),
				'checkoutTemplate' => $this->get_checkout_template(),
				'checkoutTemplates' => $this->get_checkout_templates(),
				'courierSettings' => $this->get_courier_settings(),
				'screens' => array(
					'dashboard'    => array(
						'title' => checkflow_str( 'nav.dashboard' ),
						'sub'   => checkflow_str( 'screen.dashboard.sub' ),
					),
					'orders'       => array(
						'title' => checkflow_str( 'nav.orders' ),
						'sub'   => checkflow_str( 'screen.orders.sub' ),
					),
					'pixel'        => array(
						'title' => checkflow_str( 'nav.pixel' ),
						'sub'   => checkflow_str( 'screen.pixel.sub' ),
					),
					'courier'      => array(
						'title' => checkflow_str( 'nav.courier' ),
						'sub'   => checkflow_str( 'screen.courier.sub' ),
					),
					'field_editor' => array(
						'title' => checkflow_str( 'nav.field_editor' ),
						'sub'   => checkflow_str( 'screen.field_editor.sub' ),
					),
					'templates'    => array(
						'title' => checkflow_str( 'nav.templates' ),
						'sub'   => checkflow_str( 'screen.templates.sub' ),
					),
					'order_bump'   => array(
						'title' => checkflow_str( 'nav.order_bump' ),
						'sub'   => checkflow_str( 'screen.order_bump.sub' ),
					),
					'upsell'       => array(
						'title' => checkflow_str( 'nav.upsell' ),
						'sub'   => checkflow_str( 'screen.upsell.sub' ),
					),
					'bkash_nagad'  => array(
						'title' => checkflow_str( 'nav.bkash_nagad' ),
						'sub'   => checkflow_str( 'screen.bkash_nagad.sub' ),
					),
					'settings'     => array(
						'title' => checkflow_str( 'nav.settings' ),
						'sub'   => checkflow_str( 'screen.settings.sub' ),
					),
				),
				'chartDays' => array(
					checkflow_str( 'chart.d0' ),
					checkflow_str( 'chart.d1' ),
					checkflow_str( 'chart.d2' ),
					checkflow_str( 'chart.d3' ),
					checkflow_str( 'chart.d4' ),
					checkflow_str( 'chart.d5' ),
					checkflow_str( 'chart.d6' ),
				),
				'chartVals'     => array( 38, 55, 42, 71, 63, 88, 47 ),
				'adminPageBase' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				'fieldEditor'   => class_exists( 'CheckFlow_Field_Editor' ) ? CheckFlow_Field_Editor::instance()->get_admin_rows() : array(),
			)
		);
		wp_enqueue_script( 'checkflow-admin' );
	}

	public function render_page() {
		if ( ! current_user_can( self::caps() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'checkflow' ) );
		}
		require CHECKFLOW_PATH . 'views/admin-shell.php';
	}

	public function body_class( $cls ) {
		$hook = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( 'admin.php' === $hook && isset( $_GET['page'] ) ) {
			$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 0 === strpos( $page, 'checkflow' ) ) {
				return $cls . ' checkflow-admin-screen checkflow-page-' . sanitize_html_class( $page );
			}
		}
		return $cls;
	}

	/**
	 * Save a quick setting toggle.
	 */
	public function ajax_toggle_setting() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'checkflow' ),
				),
				403
			);
		}

		$key     = isset( $_POST['setting'] ) ? sanitize_key( wp_unslash( $_POST['setting'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) ? (bool) absint( $_POST['enabled'] ) : false;
		$allowed = array_keys( $this->get_quick_setting_defaults() );

		if ( ! in_array( $key, $allowed, true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unknown setting.', 'checkflow' ),
				),
				400
			);
		}

		$settings         = $this->get_quick_settings();
		$settings[ $key ] = $enabled;
		update_option( self::SETTINGS_OPTION, $settings, false );

		wp_send_json_success(
			array(
				'message'  => __( 'Setting saved.', 'checkflow' ),
				'setting'  => $key,
				'enabled'  => $enabled,
				'settings' => $settings,
			)
		);
	}

	/**
	 * Return dashboard stats. Uses mock data until analytics tables are wired.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'checkflow' ),
				),
				403
			);
		}

		$period = isset( $_POST['period'] ) ? sanitize_key( wp_unslash( $_POST['period'] ) ) : '7d';
		if ( ! in_array( $period, array( '7d', '30d', 'all' ), true ) ) {
			$period = '7d';
		}

		wp_send_json_success(
			array(
				'period'      => $period,
				'dailyOrders' => array( 38, 55, 42, 71, 63, 88, 47 ),
				'source'      => 'mock',
			)
		);
	}

	/**
	 * Recent WooCommerce orders for the CheckFlow admin.
	 *
	 * @param int $limit Number of orders.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_orders( $limit = 12 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'limit'   => max( 1, absint( $limit ) ),
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
				'status'  => array_keys( wc_get_order_statuses() ),
			)
		);

		$rows = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$rows[] = $this->format_order_row( $order );
		}

		return $rows;
	}

	/**
	 * Lightweight order metrics for the Orders screen.
	 *
	 * @return array<string,string>
	 */
	public function get_order_metrics() {
		$orders = $this->get_recent_orders( 50 );
		if ( ! function_exists( 'wc_get_orders' ) || ! $orders ) {
		return array(
			'total_orders' => '0',
			'processing'   => '0',
			'pending'      => '0',
			'revenue'      => function_exists( 'wc_price' ) ? $this->clean_money_text( wc_price( 0 ) ) : '0',
		);
		}

		$raw_orders = wc_get_orders(
			array(
				'limit'   => 50,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
				'status'  => array_keys( wc_get_order_statuses() ),
			)
		);

		$revenue    = 0.0;
		$processing = 0;
		$pending    = 0;
		foreach ( $raw_orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$status = (string) $order->get_status();
			if ( in_array( $status, array( 'completed', 'processing' ), true ) ) {
				$revenue += (float) $order->get_total();
				$processing++;
			}
			if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
				$pending++;
			}
		}

		return array(
			'total_orders' => (string) count( $raw_orders ),
			'processing'   => (string) $processing,
			'pending'      => (string) $pending,
			'revenue'      => function_exists( 'wc_price' ) ? $this->clean_money_text( wc_price( $revenue ) ) : number_format_i18n( $revenue, 2 ),
		);
	}

	/**
	 * Update a WooCommerce order status from the CheckFlow drawer.
	 */
	public function ajax_update_order_status() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'checkflow' ) ),
				403
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'WooCommerce is not available.', 'checkflow' ) ),
				400
			);
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$allowed  = array( 'processing', 'completed', 'cancelled' );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order || ! in_array( $status, $allowed, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid order status request.', 'checkflow' ) ),
				400
			);
		}

		try {
			$order->update_status(
				$status,
				sprintf(
					/* translators: %s: status name. */
					__( 'CheckFlow changed order status to %s.', 'checkflow' ),
					wc_get_order_status_name( $status )
				),
				true
			);
			$order = wc_get_order( $order_id );
		} catch ( Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
				500
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Order status updated.', 'checkflow' ),
				'order'   => $this->format_order_row( $order ),
			)
		);
	}

	/**
	 * Add an internal or customer order note from the CheckFlow drawer.
	 */
	public function ajax_add_order_note() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'checkflow' ) ),
				403
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'WooCommerce is not available.', 'checkflow' ) ),
				400
			);
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$type     = isset( $_POST['note_type'] ) ? sanitize_key( wp_unslash( $_POST['note_type'] ) ) : 'internal';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order || '' === trim( $note ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Write a note before saving.', 'checkflow' ) ),
				400
			);
		}

		$is_customer_note = 'customer' === $type;
		$note_id          = $order->add_order_note( $note, $is_customer_note, true );

		wp_send_json_success(
			array(
				'message'   => $is_customer_note ? __( 'Customer note saved.', 'checkflow' ) : __( 'Internal note saved.', 'checkflow' ),
				'note_id'   => (string) $note_id,
				'note_type' => $is_customer_note ? 'customer' : 'internal',
				'note'      => $note,
			)
		);
	}

	/**
	 * Save courier provider settings for the base integration layer.
	 */
	public function ajax_save_courier_settings() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'checkflow' ) ),
				403
			);
		}

		$settings = $this->get_courier_settings();
		foreach ( array_keys( $this->get_courier_providers() ) as $provider ) {
			$enabled_key = $provider . '_enabled';
			$mode_key    = $provider . '_mode';
			$token_key   = $provider . '_token';
			$settings[ $enabled_key ] = isset( $_POST[ $enabled_key ] ) ? (bool) absint( wp_unslash( $_POST[ $enabled_key ] ) ) : false;
			$settings[ $mode_key ]    = isset( $_POST[ $mode_key ] ) && 'live' === sanitize_key( wp_unslash( $_POST[ $mode_key ] ) ) ? 'live' : 'sandbox';
			if ( isset( $_POST[ $token_key ] ) ) {
				$settings[ $token_key ] = sanitize_text_field( wp_unslash( $_POST[ $token_key ] ) );
			}
		}
		$settings['default_provider'] = isset( $_POST['default_provider'] ) ? sanitize_key( wp_unslash( $_POST['default_provider'] ) ) : 'pathao';
		if ( ! isset( $this->get_courier_providers()[ $settings['default_provider'] ] ) ) {
			$settings['default_provider'] = 'pathao';
		}

		update_option( self::COURIER_SETTINGS_OPTION, $settings, false );

		wp_send_json_success(
			array(
				'message'  => __( 'Courier settings saved.', 'checkflow' ),
				'settings' => $settings,
			)
		);
	}

	/**
	 * Prepare a courier draft on the WooCommerce order.
	 */
	public function ajax_prepare_courier() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'checkflow' ) ),
				403
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'WooCommerce is not available.', 'checkflow' ) ),
				400
			);
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$providers = $this->get_courier_providers();
		$settings  = $this->get_courier_settings();
		if ( '' === $provider ) {
			$provider = $settings['default_provider'];
		}
		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order || ! isset( $providers[ $provider ] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid courier request.', 'checkflow' ) ),
				400
			);
		}
		if ( empty( $settings[ $provider . '_enabled' ] ) ) {
			wp_send_json_error(
				array( 'message' => sprintf( __( 'Enable %s before preparing courier draft.', 'checkflow' ), $providers[ $provider ]['label'] ) ),
				400
			);
		}

		$label            = $providers[ $provider ]['label'];
		$current_provider = (string) $order->get_meta( '_checkflow_courier_provider', true );
		$current_status   = (string) $order->get_meta( '_checkflow_courier_status', true );
		$already_ready    = $provider === $current_provider && 'draft_ready' === $current_status;
		if ( ! $already_ready ) {
			$order->update_meta_data( '_checkflow_courier_provider', $provider );
			$order->update_meta_data( '_checkflow_courier_status', 'draft_ready' );
			$order->update_meta_data( '_checkflow_courier', sprintf( 'Draft ready - %s', $label ) );
			$order->add_order_note( sprintf( __( 'CheckFlow prepared courier draft for %s.', 'checkflow' ), $label ), false, true );
			$order->save();
		}

		wp_send_json_success(
			array(
				'message' => $already_ready ? sprintf( __( 'Courier draft already ready for %s.', 'checkflow' ), $label ) : sprintf( __( 'Courier draft ready for %s.', 'checkflow' ), $label ),
				'order'   => $this->format_order_row( $order ),
			)
		);
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function get_courier_providers() {
		return array(
			'pathao'    => array( 'label' => 'Pathao' ),
			'redx'      => array( 'label' => 'RedX' ),
			'steadfast' => array( 'label' => 'SteadFast' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_courier_settings() {
		$defaults = array(
			'default_provider'  => 'pathao',
			'pathao_enabled'    => true,
			'pathao_mode'       => 'sandbox',
			'pathao_token'      => '',
			'redx_enabled'      => false,
			'redx_mode'         => 'sandbox',
			'redx_token'        => '',
			'steadfast_enabled' => false,
			'steadfast_mode'    => 'sandbox',
			'steadfast_token'   => '',
		);
		$saved = get_option( self::COURIER_SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * @param WC_Order $order Order object.
	 * @return array<string,mixed>
	 */
	private function format_order_row( $order ) {
		$payment_id    = (string) $order->get_payment_method();
		$payment_title = (string) $order->get_payment_method_title();
		$status        = (string) $order->get_status();
		$courier       = (string) $order->get_meta( '_checkflow_courier', true );
		if ( '' === $courier ) {
			$courier = (string) $order->get_meta( 'checkflow_courier', true );
		}

		return array(
			'order_id'      => (string) $order->get_id(),
			'id'            => '#' . $order->get_order_number(),
			'customer'      => $this->order_customer_name( $order ),
			'email'         => (string) $order->get_billing_email(),
			'phone'         => (string) $order->get_billing_phone(),
			'address'       => $this->order_address( $order ),
			'payment'       => $this->payment_label( $payment_id, $payment_title ),
			'payment_class' => $this->payment_class( $payment_id, $payment_title ),
			'courier'       => '' !== $courier ? $courier : __( 'Not booked', 'checkflow' ),
			'courier_provider' => (string) $order->get_meta( '_checkflow_courier_provider', true ),
			'courier_status' => (string) $order->get_meta( '_checkflow_courier_status', true ),
			'amount'        => $this->clean_money_text( $order->get_formatted_order_total() ),
			'status'        => wc_get_order_status_name( $status ),
			'status_class'  => $this->order_status_class( $status ),
			'status_key'    => $status,
			'date'          => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, Y' ) : '',
			'items'         => $this->order_items_summary( $order ),
			'edit_url'      => $order->get_edit_order_url(),
		);
	}

	/**
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function order_customer_name( $order ) {
		$name = trim( (string) $order->get_formatted_billing_full_name() );
		if ( '' !== $name ) {
			return $name;
		}
		$email = (string) $order->get_billing_email();
		return '' !== $email ? $email : __( 'Guest customer', 'checkflow' );
	}

	/**
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function order_address( $order ) {
		$parts = array_filter(
			array(
				$order->get_billing_address_1(),
				$order->get_billing_address_2(),
				$order->get_billing_city(),
				$order->get_billing_state(),
				$order->get_billing_postcode(),
				$order->get_billing_country(),
			)
		);

		return $parts ? implode( ', ', array_map( 'wp_strip_all_tags', $parts ) ) : __( 'No billing address', 'checkflow' );
	}

	/**
	 * @param WC_Order $order Order object.
	 * @return array<int,array<string,string>>
	 */
	private function order_items_summary( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$items[] = array(
				'name'  => wp_strip_all_tags( $item->get_name() ),
				'qty'   => (string) $item->get_quantity(),
				'total' => $this->clean_money_text( wc_price( (float) $item->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			);
		}

		return $items;
	}

	/**
	 * Normalize WooCommerce price HTML into clean admin text.
	 *
	 * @param string $value Price HTML/text.
	 * @return string
	 */
	private function clean_money_text( $value ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * @param string $id Gateway ID.
	 * @param string $title Gateway title.
	 * @return string
	 */
	private function payment_label( $id, $title ) {
		if ( '' !== $title ) {
			return $title;
		}
		return '' !== $id ? strtoupper( str_replace( '_', ' ', $id ) ) : __( 'Unknown', 'checkflow' );
	}

	/**
	 * @param string $id Gateway ID.
	 * @param string $title Gateway title.
	 * @return string
	 */
	private function payment_class( $id, $title ) {
		$haystack = strtolower( $id . ' ' . $title );
		if ( false !== strpos( $haystack, 'bkash' ) ) {
			return 'bkash';
		}
		if ( false !== strpos( $haystack, 'nagad' ) ) {
			return 'nagad';
		}
		if ( false !== strpos( $haystack, 'cod' ) || false !== strpos( $haystack, 'cash' ) ) {
			return 'cod';
		}
		return 'gateway-card';
	}

	/**
	 * @param string $status Order status without wc- prefix.
	 * @return string
	 */
	private function order_status_class( $status ) {
		if ( in_array( $status, array( 'completed', 'processing' ), true ) ) {
			return 'paid';
		}
		if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
			return 'pend';
		}
		return 'fail';
	}

	/**
	 * Save active checkout template.
	 */
	public function ajax_save_checkout_template() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'checkflow' ),
				),
				403
			);
		}

		$template  = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
		$templates = $this->get_checkout_templates();
		if ( ! isset( $templates[ $template ] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Unknown checkout template.', 'checkflow' ),
				),
				400
			);
		}

		update_option( self::TEMPLATE_OPTION, $template, false );

		wp_send_json_success(
			array(
				'message'  => __( 'Checkout template saved.', 'checkflow' ),
				'template' => $template,
				'label'    => $templates[ $template ]['name'],
			)
		);
	}

	/**
	 * @param string $hook Current admin hook.
	 * @return bool
	 */
	private function is_checkflow_hook( $hook ) {
		return false !== strpos( (string) $hook, 'checkflow' );
	}

	/**
	 * @return array<string,array{title:string}>
	 */
	private function get_sections() {
		return array(
			self::PAGE_SLUG              => array( 'title' => checkflow_str( 'nav.dashboard' ) ),
			'checkflow-orders'          => array( 'title' => checkflow_str( 'nav.orders' ) ),
			'checkflow-pixel'           => array( 'title' => checkflow_str( 'nav.pixel' ) ),
			'checkflow-courier'         => array( 'title' => checkflow_str( 'nav.courier' ) ),
			'checkflow-field-editor'    => array( 'title' => checkflow_str( 'nav.field_editor' ) ),
			'checkflow-templates'       => array( 'title' => checkflow_str( 'nav.templates' ) ),
			'checkflow-order-bump'      => array( 'title' => checkflow_str( 'nav.order_bump' ) ),
			'checkflow-upsell'          => array( 'title' => checkflow_str( 'nav.upsell' ) ),
			'checkflow-bkash-nagad'     => array( 'title' => checkflow_str( 'nav.bkash_nagad' ) ),
			'checkflow-settings'        => array( 'title' => checkflow_str( 'nav.settings' ) ),
		);
	}

	/**
	 * @return array<string,bool>
	 */
	private function get_quick_setting_defaults() {
		return array(
			'direct_checkout' => true,
			'popup_checkout'  => true,
			'slide_checkout'  => false,
			'order_bump'      => true,
			'urgency_timer'   => false,
			'recaptcha'       => true,
			'guest_checkout'  => true,
		);
	}

	/**
	 * @return array<string,bool>
	 */
	public function get_quick_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = array_merge( $this->get_quick_setting_defaults(), $saved );
		foreach ( $settings as $key => $value ) {
			$settings[ $key ] = (bool) $value;
		}

		return $settings;
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function get_checkout_templates() {
		return array(
			'default_one_page' => array(
				'name'        => __( 'Default One Page', 'checkflow' ),
				'description' => __( 'Clean Shopify-like two-column checkout with balanced spacing.', 'checkflow' ),
				'tag'         => __( 'Safe default', 'checkflow' ),
				'field_preset' => 'minimal',
				'field_preset_label' => __( 'Minimal Checkout', 'checkflow' ),
			),
			'bangladesh_cod'  => array(
				'name'        => __( 'Bangladesh COD', 'checkflow' ),
				'description' => __( 'Phone-first checkout for cash on delivery and local delivery trust.', 'checkflow' ),
				'tag'         => __( 'COD focused', 'checkflow' ),
				'field_preset' => 'bangladesh_cod',
				'field_preset_label' => __( 'Bangladesh COD', 'checkflow' ),
			),
			'minimal_digital' => array(
				'name'        => __( 'Minimal Digital', 'checkflow' ),
				'description' => __( 'Reduced visual weight for digital products, courses, and services.', 'checkflow' ),
				'tag'         => __( 'Lean flow', 'checkflow' ),
				'field_preset' => 'digital',
				'field_preset_label' => __( 'Digital Product', 'checkflow' ),
			),
			'trust_checkout'  => array(
				'name'        => __( 'Trust Checkout', 'checkflow' ),
				'description' => __( 'Stronger reassurance, payment confidence, and summary emphasis.', 'checkflow' ),
				'tag'         => __( 'Trust heavy', 'checkflow' ),
				'field_preset' => 'minimal',
				'field_preset_label' => __( 'Minimal Checkout', 'checkflow' ),
			),
			'compact_mobile'  => array(
				'name'        => __( 'Compact Mobile', 'checkflow' ),
				'description' => __( 'Tighter spacing for mobile-first stores and shorter checkout pages.', 'checkflow' ),
				'tag'         => __( 'Mobile first', 'checkflow' ),
				'field_preset' => 'minimal',
				'field_preset_label' => __( 'Minimal Checkout', 'checkflow' ),
			),
		);
	}

	/**
	 * @return string
	 */
	public function get_checkout_template() {
		$template  = sanitize_key( (string) get_option( self::TEMPLATE_OPTION, 'default_one_page' ) );
		$templates = $this->get_checkout_templates();
		return isset( $templates[ $template ] ) ? $template : 'default_one_page';
	}
}
