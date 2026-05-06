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
			'checkflow',
			array( $this, 'render_page' ),
			'dashicons-cart',
			56
		);
	}

	/**
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_checkflow' !== $hook ) {
			return;
		}
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
			CHECKFLOW_VERSION
		);

		wp_register_script(
			'checkflow-admin',
			CHECKFLOW_URL . 'assets/admin.js',
			array( 'jquery' ),
			CHECKFLOW_VERSION,
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
				'adminPageBase' => admin_url( 'admin.php?page=checkflow' ),
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
		$hook = isset( $GLOBALS['pagenow'] ) ? sanitize_key( $GLOBALS['pagenow'] ) : '';
		if ( 'admin.php' === $hook && isset( $_GET['page'] ) && 'checkflow' === $_GET['page'] ) {
			return $cls . ' checkflow-admin-screen';
		}
		return $cls;
	}
}
