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

	const ADMIN_THEME_META = 'checkflow_admin_theme';

	const PIXEL_SETTINGS_OPTION = 'checkflow_pixel_settings';

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
		$dashboard_analytics = $this->get_dashboard_analytics( '7d' );
		wp_localize_script(
			'checkflow-admin',
			'checkflowAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'checkflow_admin' ),
				'locale'  => $loc,
				'adminTheme' => $this->get_admin_theme(),
				'settings' => $this->get_quick_settings(),
				'checkoutTemplate' => $this->get_checkout_template(),
				'checkoutTemplates' => $this->get_checkout_templates(),
				'courierSettings' => $this->get_courier_settings(),
				'pixelSettings' => $this->get_pixel_settings(),
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
				'chartDays' => $dashboard_analytics['daily_labels'],
				'chartVals'     => $dashboard_analytics['daily_orders'],
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
				return $cls . ' checkflow-admin-screen checkflow-admin-theme-' . sanitize_html_class( $this->get_admin_theme() ) . ' checkflow-page-' . sanitize_html_class( $page );
			}
		}
		return $cls;
	}

	/**
	 * Print tiny compatibility shims before third-party admin scripts run.
	 */
	public function print_admin_compat_shims() {
		$hook = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( 'admin.php' !== $hook || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'checkflow' ) ) {
			return;
		}

		$script = 'window.gs_posts_grid_init=window.gs_posts_grid_init||function(){};';
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $script );
			return;
		}
		echo '<script>' . esc_html( $script ) . '</script>';
	}

	/**
	 * Save the current admin UI theme for this user.
	 */
	public function ajax_save_admin_theme() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'checkflow' ) ),
				403
			);
		}

		$theme = isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : 'dark';
		if ( ! in_array( $theme, array( 'dark', 'light' ), true ) ) {
			$theme = 'dark';
		}

		update_user_meta( get_current_user_id(), self::ADMIN_THEME_META, $theme );

		wp_send_json_success(
			array(
				'message' => __( 'Admin theme saved.', 'checkflow' ),
				'theme'   => $theme,
			)
		);
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
	 * Return dashboard stats from WooCommerce orders and CheckFlow local events.
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

		$analytics = $this->get_dashboard_analytics( $period );

		wp_send_json_success(
			array(
				'period'      => $period,
				'dailyOrders' => $analytics['daily_orders'],
				'dailyLabels' => $analytics['daily_labels'],
				'source'      => 'woocommerce',
			)
		);
	}

	/**
	 * Save Pixel Tracking provider and local event log settings.
	 */
	public function ajax_save_pixel_settings() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'checkflow' ) ),
				403
			);
		}

		$settings = array(
			'local_enabled'           => isset( $_POST['local_enabled'] ) ? (bool) absint( wp_unslash( $_POST['local_enabled'] ) ) : false,
			'meta_enabled'            => isset( $_POST['meta_enabled'] ) ? (bool) absint( wp_unslash( $_POST['meta_enabled'] ) ) : false,
			'meta_pixel_id'           => isset( $_POST['meta_pixel_id'] ) ? preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST['meta_pixel_id'] ) ) : '',
			'debug_mode'              => isset( $_POST['debug_mode'] ) ? (bool) absint( wp_unslash( $_POST['debug_mode'] ) ) : false,
			'google_enabled'          => isset( $_POST['google_enabled'] ) ? (bool) absint( wp_unslash( $_POST['google_enabled'] ) ) : false,
			'google_measurement_id'   => isset( $_POST['google_measurement_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_measurement_id'] ) ) : '',
			'google_conversion_label' => isset( $_POST['google_conversion_label'] ) ? sanitize_text_field( wp_unslash( $_POST['google_conversion_label'] ) ) : '',
			'tiktok_enabled'          => isset( $_POST['tiktok_enabled'] ) ? (bool) absint( wp_unslash( $_POST['tiktok_enabled'] ) ) : false,
			'tiktok_pixel_id'         => isset( $_POST['tiktok_pixel_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tiktok_pixel_id'] ) ) : '',
			'tiktok_api_token'        => isset( $_POST['tiktok_api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['tiktok_api_token'] ) ) : '',
			'retention_days'          => isset( $_POST['retention_days'] ) ? max( 1, min( 365, absint( wp_unslash( $_POST['retention_days'] ) ) ) ) : 30,
		);
		foreach ( $this->get_pixel_event_names() as $event_name ) {
			$key = 'event_' . sanitize_key( $event_name );
			$settings[ $key ] = isset( $_POST[ $key ] ) ? (bool) absint( wp_unslash( $_POST[ $key ] ) ) : false;
		}

		update_option( self::PIXEL_SETTINGS_OPTION, $settings, false );
		$this->prune_pixel_events( $settings['retention_days'] );

		wp_send_json_success(
			array(
				'message'  => __( 'Pixel settings saved.', 'checkflow' ),
				'settings' => $settings,
			)
		);
	}

	/**
	 * Add one admin-generated test event to the local log.
	 */
	public function ajax_test_pixel_event() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'checkflow' ) ), 403 );
		}

		$event_name = isset( $_POST['event_name'] ) ? sanitize_text_field( wp_unslash( $_POST['event_name'] ) ) : 'PageView';
		if ( ! in_array( $event_name, $this->get_pixel_event_names(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown event.', 'checkflow' ) ), 400 );
		}

		$event_id = 'cf_admin_test_' . strtolower( $event_name ) . '_' . wp_generate_password( 8, false, false );
		$logged   = $this->insert_pixel_event(
			$event_name,
			$event_id,
			admin_url( 'admin.php?page=checkflow-pixel' ),
			array(
				'source' => 'admin_test',
				'user'   => get_current_user_id(),
			)
		);

		if ( ! $logged ) {
			wp_send_json_error( array( 'message' => __( 'Could not create test event.', 'checkflow' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message'  => sprintf( __( '%s test event logged.', 'checkflow' ), $event_name ),
				'event_id' => $event_id,
			)
		);
	}

	/**
	 * Clear local tracking events.
	 */
	public function ajax_clear_pixel_log() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'checkflow' ) ), 403 );
		}

		global $wpdb;
		$table = $this->ensure_pixel_event_table();
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'expired';
		if ( 'all' === $scope ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" );
			wp_send_json_success( array( 'message' => __( 'All local events cleared.', 'checkflow' ) ) );
		}

		$settings = $this->get_pixel_settings();
		$deleted  = $this->prune_pixel_events( $settings['retention_days'] );
		wp_send_json_success(
			array(
				'message' => sprintf( __( '%d expired local events cleared.', 'checkflow' ), $deleted ),
			)
		);
	}

	/**
	 * Export recent local tracking events as CSV text.
	 */
	public function ajax_export_pixel_log() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( self::caps() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'checkflow' ) ), 403 );
		}

		global $wpdb;
		$table = $this->ensure_pixel_event_table();
		$rows  = $wpdb->get_results(
			"SELECT event_name, event_id, page_url, context, provider_state, created_at FROM {$table} ORDER BY id DESC LIMIT 1000",
			ARRAY_A
		);

		$fh = fopen( 'php://temp', 'r+' );
		fputcsv( $fh, array( 'event_name', 'event_id', 'page_url', 'context', 'provider_state', 'created_at' ) );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			fputcsv( $fh, $row );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );

		wp_send_json_success(
			array(
				'filename' => 'checkflow-events-' . gmdate( 'Y-m-d-His' ) . '.csv',
				'csv'      => $csv,
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
	 * Real dashboard analytics for the CheckFlow overview.
	 *
	 * @param string $period Period key: 7d, 30d, all.
	 * @return array<string,mixed>
	 */
	public function get_dashboard_analytics( $period = '7d' ) {
		$period = in_array( $period, array( '7d', '30d', 'all' ), true ) ? $period : '7d';
		$orders = $this->get_dashboard_orders( $period );
		$previous_orders = 'all' === $period ? array() : $this->get_dashboard_orders( $period, true );
		$event_counts = $this->get_pixel_event_counts_for_period( $period );

		$paid_statuses = array( 'completed', 'processing' );
		$revenue = 0.0;
		$previous_revenue = 0.0;
		$successful_orders = 0;
		$previous_successful_orders = 0;
		$payment_counts = array();
		$courier_counts = array(
			'pathao'    => 0,
			'redx'      => 0,
			'steadfast' => 0,
		);
		$daily_orders = $this->empty_daily_orders( $period );
		$bump = $this->dashboard_bump_seed();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$status = (string) $order->get_status();
			if ( in_array( $status, $paid_statuses, true ) ) {
				$successful_orders++;
				$revenue += (float) $order->get_total();
			}

			$payment = $this->payment_label( (string) $order->get_payment_method(), (string) $order->get_payment_method_title() );
			$payment_counts[ $payment ] = isset( $payment_counts[ $payment ] ) ? $payment_counts[ $payment ] + 1 : 1;

			$courier_provider = sanitize_key( (string) $order->get_meta( '_checkflow_courier_provider', true ) );
			if ( isset( $courier_counts[ $courier_provider ] ) ) {
				$courier_counts[ $courier_provider ]++;
			}

			$created = $order->get_date_created();
			if ( $created ) {
				$key = $created->date_i18n( 'Y-m-d' );
				if ( isset( $daily_orders[ $key ] ) ) {
					$daily_orders[ $key ]++;
				}
			}

			$bump = $this->add_order_to_bump_analytics( $bump, $order );
		}

		foreach ( $previous_orders as $order ) {
			if ( $order instanceof WC_Order && in_array( (string) $order->get_status(), $paid_statuses, true ) ) {
				$previous_successful_orders++;
				$previous_revenue += (float) $order->get_total();
			}
		}

		$average_order = $successful_orders ? $revenue / $successful_orders : 0;
		$previous_average = $previous_successful_orders ? $previous_revenue / $previous_successful_orders : 0;
		$checkout_events = max( 0, absint( $event_counts['InitiateCheckout'] ) );
		$conversion_rate = $checkout_events ? round( ( $successful_orders / max( 1, $checkout_events ) ) * 100, 1 ) : 0;
		$bump_rate = $checkout_events ? round( ( absint( $bump['quantity'] ) / max( 1, $checkout_events ) ) * 100, 1 ) : 0;
		$daily_labels = array_map(
			function ( $day ) {
				return date_i18n( 'M j', strtotime( $day . ' 00:00:00' ) );
			},
			array_keys( $daily_orders )
		);

		return array(
			'cards'        => array(
				'revenue' => array(
					'value' => $this->format_money_for_admin( $revenue ),
					'delta' => $this->format_delta( $revenue, $previous_revenue ),
					'delta_class' => $revenue >= $previous_revenue ? 'up' : 'dn',
					'context' => $this->period_context_label( $period ),
				),
				'orders'  => array(
					'value' => (string) $successful_orders,
					'delta' => $this->format_delta( $successful_orders, $previous_successful_orders ),
					'delta_class' => $successful_orders >= $previous_successful_orders ? 'up' : 'dn',
					'context' => sprintf( 'Conversion: %s%%', number_format_i18n( $conversion_rate, 1 ) ),
				),
				'bump'    => array(
					'value' => $this->format_money_for_admin( (float) $bump['revenue'] ),
					'delta' => $bump_rate > 0 ? sprintf( '%s%%', number_format_i18n( $bump_rate, 1 ) ) : '0%',
					'delta_class' => $bump_rate > 0 ? 'up' : 'dn',
					'context' => sprintf( 'Take rate: %s%%', number_format_i18n( $bump_rate, 1 ) ),
				),
				'average' => array(
					'value' => $this->format_money_for_admin( $average_order ),
					'delta' => $this->format_delta( $average_order, $previous_average ),
					'delta_class' => $average_order >= $previous_average ? 'up' : 'dn',
					'context' => sprintf( 'From %d paid orders', $successful_orders ),
				),
			),
			'funnel'       => $this->dashboard_funnel_rows( $event_counts, $successful_orders ),
			'payment_mix'  => $this->dashboard_payment_mix( $payment_counts ),
			'daily_orders' => array_values( $daily_orders ),
			'daily_labels' => $daily_labels,
			'couriers'     => $courier_counts,
			'bump'         => $bump,
			'source'       => 'woocommerce',
		);
	}

	/**
	 * @param string $period Period key.
	 * @param bool   $previous Whether to fetch the previous matching period.
	 * @return array<int,WC_Order>
	 */
	private function get_dashboard_orders( $period, $previous = false ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$args = array(
			'limit'   => 'all' === $period ? 250 : 200,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
			'status'  => array_keys( wc_get_order_statuses() ),
		);

		$range = $this->period_date_range( $period, $previous );
		if ( $range ) {
			$args['date_created'] = $range['after'] . '...' . $range['before'];
		}

		$orders = wc_get_orders( $args );
		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * @param string $period Period key.
	 * @param bool   $previous Whether to return previous period.
	 * @return array{after:string,before:string}|null
	 */
	private function period_date_range( $period, $previous = false ) {
		$days = '30d' === $period ? 30 : ( '7d' === $period ? 7 : 0 );
		if ( ! $days ) {
			return null;
		}

		$now = current_time( 'timestamp' );
		$end = $previous ? strtotime( '-' . $days . ' days', $now ) : $now;
		$start = strtotime( '-' . $days . ' days', $end );

		return array(
			'after'  => date_i18n( 'Y-m-d H:i:s', $start ),
			'before' => date_i18n( 'Y-m-d H:i:s', $end ),
		);
	}

	/**
	 * @param string $period Period key.
	 * @return array<string,int>
	 */
	private function empty_daily_orders( $period ) {
		$days = '30d' === $period ? 30 : 7;
		$items = array();
		$now = current_time( 'timestamp' );
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$items[ date_i18n( 'Y-m-d', strtotime( '-' . $i . ' days', $now ) ) ] = 0;
		}
		return $items;
	}

	/**
	 * @param string $period Period key.
	 * @return array<string,int>
	 */
	private function get_pixel_event_counts_for_period( $period ) {
		global $wpdb;

		$table = $this->ensure_pixel_event_table();
		$counts = array(
			'PageView'         => 0,
			'ViewContent'      => 0,
			'AddToCart'        => 0,
			'InitiateCheckout' => 0,
			'AddPaymentInfo'   => 0,
			'Purchase'         => 0,
		);
		$where = '';
		$range = $this->period_date_range( $period, false );
		if ( $range ) {
			$where = $wpdb->prepare( ' WHERE created_at BETWEEN %s AND %s', $range['after'], $range['before'] );
		}

		$rows = $wpdb->get_results( "SELECT event_name, COUNT(*) AS total FROM {$table}{$where} GROUP BY event_name", ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$name = sanitize_text_field( (string) $row['event_name'] );
				if ( isset( $counts[ $name ] ) ) {
					$counts[ $name ] = absint( $row['total'] );
				}
			}
		}
		return $counts;
	}

	/**
	 * @param array<string,int> $event_counts Local event totals.
	 * @param int               $successful_orders Paid order count.
	 * @return array<int,array<string,mixed>>
	 */
	private function dashboard_funnel_rows( $event_counts, $successful_orders ) {
		$rows = array(
			array( 'label' => 'Page views', 'value' => absint( $event_counts['PageView'] ), 'class' => 'bl' ),
			array( 'label' => 'Product views', 'value' => absint( $event_counts['ViewContent'] ), 'class' => 'tl' ),
			array( 'label' => 'Checkout started', 'value' => absint( $event_counts['InitiateCheckout'] ), 'class' => 'or' ),
			array( 'label' => 'Order complete', 'value' => absint( max( $event_counts['Purchase'], $successful_orders ) ), 'class' => 'gn' ),
		);
		$max = 1;
		foreach ( $rows as $row ) {
			$max = max( $max, absint( $row['value'] ) );
		}
		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['width'] = max( 7, round( ( absint( $row['value'] ) / $max ) * 100 ) );
			$rows[ $index ]['drop'] = 0 === $index || 0 === absint( $rows[ $index - 1 ]['value'] ) ? '' : sprintf(
				'%s%%',
				number_format_i18n( round( ( absint( $row['value'] ) / max( 1, absint( $rows[ $index - 1 ]['value'] ) ) ) * 100 ) )
			);
		}
		return $rows;
	}

	/**
	 * @param array<string,int> $payment_counts Payment totals.
	 * @return array<int,array<string,mixed>>
	 */
	private function dashboard_payment_mix( $payment_counts ) {
		arsort( $payment_counts );
		$total = max( 1, array_sum( $payment_counts ) );
		$colors = array( '#ff4081', 'var(--or)', 'var(--gn)', 'var(--pr)' );
		$rows = array();
		$index = 0;
		foreach ( array_slice( $payment_counts, 0, 4, true ) as $label => $count ) {
			$rows[] = array(
				'label'   => $label,
				'count'   => absint( $count ),
				'percent' => round( ( absint( $count ) / $total ) * 100 ),
				'color'   => $colors[ $index ] ?? 'var(--tx3)',
			);
			$index++;
		}
		if ( empty( $rows ) ) {
			$rows[] = array( 'label' => __( 'No payments yet', 'checkflow' ), 'count' => 0, 'percent' => 0, 'color' => 'var(--tx3)' );
		}
		return $rows;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function dashboard_bump_seed() {
		$product_id = absint( get_option( 'checkflow_order_bump_product_id', 0 ) );
		$name = $product_id ? get_the_title( $product_id ) : __( 'Configured bump product', 'checkflow' );
		return array(
			'product_id' => $product_id,
			'name'       => $name ? wp_strip_all_tags( $name ) : __( 'Configured bump product', 'checkflow' ),
			'quantity'   => 0,
			'revenue'    => 0.0,
		);
	}

	/**
	 * @param array<string,mixed> $bump Bump analytics.
	 * @param WC_Order            $order Order.
	 * @return array<string,mixed>
	 */
	private function add_order_to_bump_analytics( $bump, $order ) {
		$product_id = absint( $bump['product_id'] );
		if ( ! $product_id ) {
			return $bump;
		}
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			if ( absint( $item->get_product_id() ) === $product_id || absint( $item->get_variation_id() ) === $product_id ) {
				$bump['quantity'] = absint( $bump['quantity'] ) + absint( $item->get_quantity() );
				$bump['revenue'] = (float) $bump['revenue'] + (float) $item->get_total();
			}
		}
		return $bump;
	}

	/**
	 * @param float|int $value Current value.
	 * @param float|int $previous Previous value.
	 * @return string
	 */
	private function format_delta( $value, $previous ) {
		$value = (float) $value;
		$previous = (float) $previous;
		if ( 0.0 === $previous ) {
			return $value > 0 ? '+100%' : '0%';
		}
		$delta = ( ( $value - $previous ) / abs( $previous ) ) * 100;
		return sprintf( '%s%s%%', $delta >= 0 ? '+' : '', number_format_i18n( $delta, 1 ) );
	}

	/**
	 * @param float $amount Amount.
	 * @return string
	 */
	private function format_money_for_admin( $amount ) {
		return function_exists( 'wc_price' ) ? $this->clean_money_text( wc_price( $amount ) ) : number_format_i18n( $amount, 2 );
	}

	/**
	 * @param string $period Period key.
	 * @return string
	 */
	private function period_context_label( $period ) {
		if ( '30d' === $period ) {
			return __( 'vs previous 30 days', 'checkflow' );
		}
		if ( 'all' === $period ) {
			return __( 'all available orders', 'checkflow' );
		}
		return __( 'vs previous 7 days', 'checkflow' );
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
		foreach ( $this->get_pathao_setting_keys() as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$settings[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
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
	 * Build and validate the Pathao booking payload without calling Pathao yet.
	 */
	public function ajax_review_pathao_booking() {
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
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid Pathao review request.', 'checkflow' ) ),
				400
			);
		}

		$settings = $this->get_courier_settings();
		$payload  = $this->build_pathao_payload( $order, $settings );
		$missing  = $this->validate_pathao_payload( $payload, $settings );

		wp_send_json_success(
			array(
				'message' => empty( $missing ) ? __( 'Pathao booking payload is ready for API booking.', 'checkflow' ) : __( 'Pathao booking payload needs attention.', 'checkflow' ),
				'payload' => $payload,
				'missing' => $missing,
				'mode'    => $settings['pathao_mode'],
				'baseUrl' => $this->pathao_base_url( $settings ),
			)
		);
	}

	/**
	 * Book a Pathao order through the configured merchant API.
	 */
	public function ajax_book_pathao_order() {
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
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid Pathao booking request.', 'checkflow' ) ),
				400
			);
		}

		$settings = $this->get_courier_settings();
		if ( empty( $settings['pathao_enabled'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Enable Pathao before live booking.', 'checkflow' ) ),
				400
			);
		}

		$existing_consignment = (string) $order->get_meta( '_checkflow_pathao_consignment_id', true );
		if ( '' !== $existing_consignment ) {
			wp_send_json_success(
				array(
					'message'        => sprintf( __( 'Pathao booking already exists: %s.', 'checkflow' ), $existing_consignment ),
					'consignment_id' => $existing_consignment,
					'order'          => $this->format_order_row( $order ),
				)
			);
		}

		$payload = $this->build_pathao_payload( $order, $settings );
		$missing = $this->validate_pathao_payload( $payload, $settings );
		if ( ! empty( $missing ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Pathao booking needs required information first.', 'checkflow' ),
					'missing' => $missing,
					'payload' => $payload,
				),
				400
			);
		}

		$token_result = $this->pathao_request_access_token( $settings );
		if ( is_wp_error( $token_result ) ) {
			wp_send_json_error(
				array( 'message' => $token_result->get_error_message() ),
				400
			);
		}

		$booking_result = $this->pathao_create_order( $payload, $token_result['access_token'], $settings );
		if ( is_wp_error( $booking_result ) ) {
			wp_send_json_error(
				array( 'message' => $booking_result->get_error_message() ),
				400
			);
		}

		$consignment_id = $this->pathao_response_value( $booking_result, 'consignment_id' );
		$order_status   = $this->pathao_response_value( $booking_result, 'order_status' );
		if ( '' === $consignment_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Pathao responded without a consignment ID. Booking was not stored.', 'checkflow' ) ),
				400
			);
		}

		$order->update_meta_data( '_checkflow_courier_provider', 'pathao' );
		$order->update_meta_data( '_checkflow_courier_status', 'booked' );
		$order->update_meta_data( '_checkflow_courier', sprintf( 'Booked - Pathao %s', $consignment_id ) );
		$order->update_meta_data( '_checkflow_pathao_consignment_id', $consignment_id );
		$order->update_meta_data( '_checkflow_pathao_order_status', $order_status );
		$order->update_meta_data( '_checkflow_pathao_payload', wp_json_encode( $payload ) );
		$order->update_meta_data( '_checkflow_pathao_response', wp_json_encode( $booking_result ) );
		$order->add_order_note( sprintf( __( 'CheckFlow booked Pathao consignment %s.', 'checkflow' ), $consignment_id ), false, true );
		$order->save();

		wp_send_json_success(
			array(
				'message'        => sprintf( __( 'Pathao booking created: %s.', 'checkflow' ), $consignment_id ),
				'consignment_id' => $consignment_id,
				'pathao_status'  => $order_status,
				'response'       => $booking_result,
				'order'          => $this->format_order_row( $order ),
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
			'pathao_base_url'   => '',
			'pathao_client_id'  => '',
			'pathao_client_secret' => '',
			'pathao_username'   => '',
			'pathao_password'   => '',
			'pathao_store_id'   => '',
			'pathao_delivery_type' => '48',
			'pathao_item_type'  => '2',
			'pathao_item_weight' => '0.5',
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
	 * @return array<int,string>
	 */
	private function get_pathao_setting_keys() {
		return array(
			'pathao_base_url',
			'pathao_client_id',
			'pathao_client_secret',
			'pathao_username',
			'pathao_password',
			'pathao_store_id',
			'pathao_delivery_type',
			'pathao_item_type',
			'pathao_item_weight',
		);
	}

	/**
	 * @param WC_Order $order Order object.
	 * @param array<string,mixed> $settings Courier settings.
	 * @return array<string,mixed>
	 */
	private function build_pathao_payload( $order, $settings ) {
		$delivery_type = absint( $settings['pathao_delivery_type'] );
		$item_type     = absint( $settings['pathao_item_type'] );
		$item_weight   = (float) $settings['pathao_item_weight'];
		$address       = $this->order_address( $order );
		$amount        = in_array( $order->get_payment_method(), array( 'cod' ), true ) ? (int) round( (float) $order->get_total() ) : 0;

		return array(
			'store_id'            => absint( $settings['pathao_store_id'] ),
			'merchant_order_id'   => (string) $order->get_order_number(),
			'recipient_name'      => $this->order_customer_name( $order ),
			'recipient_phone'     => (string) $order->get_billing_phone(),
			'recipient_address'   => $address,
			'delivery_type'       => $delivery_type ? $delivery_type : 48,
			'item_type'           => $item_type ? $item_type : 2,
			'item_quantity'       => max( 1, $order->get_item_count() ),
			'item_weight'         => $item_weight > 0 ? $item_weight : 0.5,
			'amount_to_collect'   => $amount,
			'special_instruction' => sprintf( 'WooCommerce order %s via CheckFlow', $order->get_order_number() ),
			'item_description'    => $this->pathao_item_description( $order ),
		);
	}

	/**
	 * @param array<string,mixed> $payload Pathao payload.
	 * @param array<string,mixed> $settings Courier settings.
	 * @return array<int,string>
	 */
	private function validate_pathao_payload( $payload, $settings ) {
		$missing = array();
		$required_settings = array(
			'pathao_client_id'     => __( 'Pathao client ID', 'checkflow' ),
			'pathao_client_secret' => __( 'Pathao client secret', 'checkflow' ),
			'pathao_username'      => __( 'Pathao username/email', 'checkflow' ),
			'pathao_password'      => __( 'Pathao password', 'checkflow' ),
			'pathao_store_id'      => __( 'Pathao store ID', 'checkflow' ),
		);
		foreach ( $required_settings as $key => $label ) {
			if ( empty( $settings[ $key ] ) ) {
				$missing[] = $label;
			}
		}

		$required_payload = array(
			'recipient_name'    => __( 'recipient name', 'checkflow' ),
			'recipient_phone'   => __( 'recipient phone', 'checkflow' ),
			'recipient_address' => __( 'recipient address', 'checkflow' ),
		);
		foreach ( $required_payload as $key => $label ) {
			if ( empty( $payload[ $key ] ) ) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

	/**
	 * @param array<string,mixed> $settings Courier settings.
	 * @return string
	 */
	private function pathao_base_url( $settings ) {
		if ( ! empty( $settings['pathao_base_url'] ) ) {
			return esc_url_raw( $settings['pathao_base_url'] );
		}
		return 'live' === $settings['pathao_mode'] ? 'https://api-hermes.pathao.com' : 'https://hermes-api.p-stageenv.xyz';
	}

	/**
	 * @param array<string,mixed> $settings Courier settings.
	 * @return array<string,string>|WP_Error
	 */
	private function pathao_request_access_token( $settings ) {
		$response = wp_remote_post(
			trailingslashit( $this->pathao_base_url( $settings ) ) . 'aladdin/api/v1/issue-token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'client_id'     => (string) $settings['pathao_client_id'],
						'client_secret' => (string) $settings['pathao_client_secret'],
						'username'      => (string) $settings['pathao_username'],
						'password'      => (string) $settings['pathao_password'],
						'grant_type'    => 'password',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'checkflow_pathao_token_failed', sprintf( __( 'Pathao token request failed: %s', 'checkflow' ), $response->get_error_message() ) );
		}

		$body = $this->pathao_decode_response( $response );
		$code = (int) wp_remote_retrieve_response_code( $response );
		$token = $this->pathao_response_value( $body, 'access_token' );
		if ( $code < 200 || $code >= 300 || '' === $token ) {
			return new WP_Error( 'checkflow_pathao_token_rejected', $this->pathao_api_error_message( $response, $body, __( 'Pathao token request was rejected.', 'checkflow' ) ) );
		}

		return array( 'access_token' => $token );
	}

	/**
	 * @param array<string,mixed> $payload Booking payload.
	 * @param string              $access_token Bearer token.
	 * @param array<string,mixed> $settings Courier settings.
	 * @return array<string,mixed>|WP_Error
	 */
	private function pathao_create_order( $payload, $access_token, $settings ) {
		$response = wp_remote_post(
			trailingslashit( $this->pathao_base_url( $settings ) ) . 'aladdin/api/v1/orders',
			array(
				'timeout' => 30,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'checkflow_pathao_order_failed', sprintf( __( 'Pathao booking request failed: %s', 'checkflow' ), $response->get_error_message() ) );
		}

		$body = $this->pathao_decode_response( $response );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'checkflow_pathao_order_rejected', $this->pathao_api_error_message( $response, $body, __( 'Pathao booking was rejected.', 'checkflow' ) ) );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * @param array<string,mixed>|mixed $body Response body.
	 * @param string                    $key Key to find.
	 * @return string
	 */
	private function pathao_response_value( $body, $key ) {
		if ( ! is_array( $body ) ) {
			return '';
		}
		if ( isset( $body[ $key ] ) && ! is_array( $body[ $key ] ) ) {
			return sanitize_text_field( (string) $body[ $key ] );
		}
		if ( isset( $body['data'] ) && is_array( $body['data'] ) && isset( $body['data'][ $key ] ) && ! is_array( $body['data'][ $key ] ) ) {
			return sanitize_text_field( (string) $body['data'][ $key ] );
		}
		return '';
	}

	/**
	 * @param array<string,mixed>|WP_Error $response HTTP response.
	 * @return array<string,mixed>
	 */
	private function pathao_decode_response( $response ) {
		$raw = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array( 'raw' => $raw );
	}

	/**
	 * @param array<string,mixed>|WP_Error $response HTTP response.
	 * @param array<string,mixed>          $body Decoded body.
	 * @param string                       $fallback Fallback message.
	 * @return string
	 */
	private function pathao_api_error_message( $response, $body, $fallback ) {
		$code = (int) wp_remote_retrieve_response_code( $response );
		foreach ( array( 'message', 'error', 'error_description' ) as $key ) {
			$value = $this->pathao_response_value( $body, $key );
			if ( '' !== $value ) {
				return sprintf( '%s (%s)', $value, $code );
			}
		}
		return sprintf( '%s (%s)', $fallback, $code );
	}

	/**
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function pathao_item_description( $order ) {
		$names = array();
		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
		}
		return sanitize_text_field( implode( ', ', array_slice( $names, 0, 4 ) ) );
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
	 * @return string
	 */
	public function get_admin_theme() {
		$theme = sanitize_key( (string) get_user_meta( get_current_user_id(), self::ADMIN_THEME_META, true ) );
		return in_array( $theme, array( 'dark', 'light' ), true ) ? $theme : 'dark';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_pixel_settings() {
		$defaults = array(
			'local_enabled'           => true,
			'meta_enabled'            => false,
			'meta_pixel_id'           => '',
			'debug_mode'              => false,
			'google_enabled'          => false,
			'google_measurement_id'   => '',
			'google_conversion_label' => '',
			'tiktok_enabled'          => false,
			'tiktok_pixel_id'         => '',
			'tiktok_api_token'        => '',
			'retention_days'          => 30,
		);
		foreach ( $this->get_pixel_event_names() as $event_name ) {
			$defaults[ 'event_' . sanitize_key( $event_name ) ] = true;
		}
		$saved = get_option( self::PIXEL_SETTINGS_OPTION, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		foreach ( array( 'local_enabled', 'meta_enabled', 'debug_mode', 'google_enabled', 'tiktok_enabled' ) as $key ) {
			$settings[ $key ] = (bool) $settings[ $key ];
		}
		foreach ( $this->get_pixel_event_names() as $event_name ) {
			$key = 'event_' . sanitize_key( $event_name );
			$settings[ $key ] = (bool) $settings[ $key ];
		}
		$settings['retention_days']          = max( 1, min( 365, absint( $settings['retention_days'] ) ) );
		$settings['meta_pixel_id']           = preg_replace( '/[^0-9]/', '', (string) $settings['meta_pixel_id'] );
		$settings['google_measurement_id']   = sanitize_text_field( (string) $settings['google_measurement_id'] );
		$settings['google_conversion_label'] = sanitize_text_field( (string) $settings['google_conversion_label'] );
		$settings['tiktok_pixel_id']         = sanitize_text_field( (string) $settings['tiktok_pixel_id'] );
		$settings['tiktok_api_token']        = sanitize_text_field( (string) $settings['tiktok_api_token'] );
		return $settings;
	}

	/**
	 * @return array<int,string>
	 */
	public function get_pixel_event_names() {
		return array( 'PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase' );
	}

	/**
	 * @param int $limit Number of rows.
	 * @return array<int,array<string,string>>
	 */
	public function get_recent_pixel_events( $limit = 8 ) {
		global $wpdb;

		$table = CheckFlow_Activator::event_log_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			CheckFlow_Activator::create_event_log_table();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_name, event_id, page_url, context, provider_state, created_at FROM {$table} ORDER BY id DESC LIMIT %d",
				max( 1, absint( $limit ) )
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			function ( $row ) {
				$context = json_decode( (string) $row['context'], true );
				return array(
					'event_name' => sanitize_text_field( (string) $row['event_name'] ),
					'event_id'   => sanitize_text_field( (string) $row['event_id'] ),
					'page_url'   => esc_url_raw( (string) $row['page_url'] ),
					'summary'    => $this->pixel_event_summary( is_array( $context ) ? $context : array() ),
					'context'    => wp_json_encode( is_array( $context ) ? $context : array() ),
					'created_at' => mysql2date( 'M j, H:i', (string) $row['created_at'] ),
				);
			},
			$rows
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_pixel_event_analytics() {
		global $wpdb;

		$table = CheckFlow_Activator::event_log_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			CheckFlow_Activator::create_event_log_table();
		}

		$count_rows = $wpdb->get_results( "SELECT event_name, COUNT(*) AS total FROM {$table} GROUP BY event_name", ARRAY_A );
		$counts     = array(
			'PageView'          => 0,
			'ViewContent'       => 0,
			'AddToCart'         => 0,
			'InitiateCheckout'  => 0,
			'Purchase'          => 0,
		);
		if ( is_array( $count_rows ) ) {
			foreach ( $count_rows as $row ) {
				$name = sanitize_text_field( (string) $row['event_name'] );
				if ( isset( $counts[ $name ] ) ) {
					$counts[ $name ] = absint( $row['total'] );
				}
			}
		}

		$total = array_sum( $counts );
		$max   = max( 1, max( $counts ) );

		$recent = $wpdb->get_results(
			"SELECT event_name, created_at FROM {$table} ORDER BY id DESC LIMIT 12",
			ARRAY_A
		);

		return array(
			'total'  => $total,
			'counts' => $counts,
			'max'    => $max,
			'recent' => is_array( $recent ) ? array_map(
				function ( $row ) {
					return array(
						'event_name' => sanitize_text_field( (string) $row['event_name'] ),
						'time'       => mysql2date( 'H:i', (string) $row['created_at'] ),
					);
				},
				array_reverse( $recent )
			) : array(),
		);
	}

	/**
	 * @param array<string,mixed> $context Event context.
	 * @return string
	 */
	private function pixel_event_summary( $context ) {
		if ( ! empty( $context['order_id'] ) ) {
			return sprintf( 'Order %s', sanitize_text_field( (string) $context['order_id'] ) );
		}
		if ( ! empty( $context['content_ids'] ) && is_array( $context['content_ids'] ) ) {
			return sprintf( '%d product(s)', count( $context['content_ids'] ) );
		}
		if ( ! empty( $context['num_items'] ) ) {
			return sprintf( '%d item(s)', absint( $context['num_items'] ) );
		}
		return __( 'Browser event', 'checkflow' );
	}

	/**
	 * @return string
	 */
	private function ensure_pixel_event_table() {
		global $wpdb;

		$table = CheckFlow_Activator::event_log_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			CheckFlow_Activator::create_event_log_table();
		}
		return $table;
	}

	/**
	 * @param string              $event_name Event name.
	 * @param string              $event_id Event ID.
	 * @param string              $page_url Page URL.
	 * @param array<string,mixed> $context Event context.
	 * @return bool
	 */
	private function insert_pixel_event( $event_name, $event_id, $page_url, $context ) {
		global $wpdb;

		$table    = $this->ensure_pixel_event_table();
		$settings = $this->get_pixel_settings();
		$state    = array(
			'checkflow' => array( 'enabled' => ! empty( $settings['local_enabled'] ), 'configured' => true ),
			'meta'      => array( 'enabled' => ! empty( $settings['meta_enabled'] ), 'configured' => ! empty( $settings['meta_pixel_id'] ) ),
			'google'    => array( 'enabled' => ! empty( $settings['google_enabled'] ), 'configured' => ! empty( $settings['google_measurement_id'] ) && ! empty( $settings['google_conversion_label'] ) ),
			'tiktok'    => array( 'enabled' => ! empty( $settings['tiktok_enabled'] ), 'configured' => ! empty( $settings['tiktok_pixel_id'] ) || ! empty( $settings['tiktok_api_token'] ) ),
		);

		return (bool) $wpdb->insert(
			$table,
			array(
				'event_name'     => $event_name,
				'event_id'       => substr( sanitize_text_field( $event_id ), 0, 100 ),
				'page_url'       => esc_url_raw( $page_url ),
				'context'        => wp_json_encode( $context ),
				'provider_state' => wp_json_encode( $state ),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * @param int $retention_days Days to keep.
	 * @return int
	 */
	private function prune_pixel_events( $retention_days ) {
		global $wpdb;

		$table = $this->ensure_pixel_event_table();
		$days  = max( 1, min( 365, absint( $retention_days ) ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
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
