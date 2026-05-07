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
			array( 'jquery' ),
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
}
