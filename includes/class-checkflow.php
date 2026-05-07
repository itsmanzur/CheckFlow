<?php
/**
 * Main plugin orchestrator.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CHECKFLOW_PATH . 'includes/Frontend/class-assets.php';
require_once CHECKFLOW_PATH . 'includes/Frontend/class-ajax.php';
require_once CHECKFLOW_PATH . 'includes/Frontend/class-checkout.php';
require_once CHECKFLOW_PATH . 'includes/Frontend/class-field-editor.php';

final class CheckFlow {

	/** @var self|null */
	private static $instance = null;

	/** @var CheckFlow_Loader */
	private $loader;

	private function __construct() {
		$this->loader = new CheckFlow_Loader();
	}

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
	 * Wire module hooks and run loader.
	 */
	public function run() {
		$i18n  = CheckFlow_I18n::instance();
		$admin = CheckFlow_Admin::instance();
		$assets = CheckFlow_Frontend_Assets::instance();
		$ajax   = CheckFlow_Frontend_Ajax::instance();
		$checkout = CheckFlow_Frontend_Checkout::instance();
		$field_editor = CheckFlow_Field_Editor::instance();

		$this->loader->add_action( 'init', $i18n, 'load_textdomain' );
		$this->loader->add_action( 'wp_ajax_checkflow_set_admin_locale', $i18n, 'ajax_set_admin_locale' );
		$this->loader->add_action( 'wp_ajax_checkflow_save_string_overrides', $i18n, 'ajax_save_string_overrides' );

		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
		$this->loader->add_filter( 'admin_body_class', $admin, 'body_class' );
		$this->loader->add_action( 'wp_ajax_checkflow_toggle_setting', $admin, 'ajax_toggle_setting' );
		$this->loader->add_action( 'wp_ajax_checkflow_get_stats', $admin, 'ajax_get_stats' );
		$this->loader->add_action( 'wp_ajax_checkflow_save_checkout_fields', $field_editor, 'ajax_save_fields' );
		$this->loader->add_action( 'wp_ajax_checkflow_reset_checkout_fields', $field_editor, 'ajax_reset_fields' );

		$this->loader->add_action( 'admin_init', $this, 'maybe_show_wc_notice' );
		$this->loader->add_action( 'admin_notices', $this, 'render_wc_notice' );

		$this->loader->add_action( 'wp_enqueue_scripts', $assets, 'enqueue' );

		$this->loader->add_action( 'wp_ajax_checkflow_update_order_review', $ajax, 'update_order_review' );
		$this->loader->add_action( 'wp_ajax_nopriv_checkflow_update_order_review', $ajax, 'update_order_review' );
		$this->loader->add_action( 'wp_ajax_checkflow_apply_coupon', $ajax, 'apply_coupon' );
		$this->loader->add_action( 'wp_ajax_nopriv_checkflow_apply_coupon', $ajax, 'apply_coupon' );
		$this->loader->add_action( 'wp_ajax_checkflow_remove_coupon', $ajax, 'remove_coupon' );
		$this->loader->add_action( 'wp_ajax_nopriv_checkflow_remove_coupon', $ajax, 'remove_coupon' );
		$this->loader->add_action( 'wp_ajax_checkflow_validate_field', $ajax, 'validate_field' );
		$this->loader->add_action( 'wp_ajax_nopriv_checkflow_validate_field', $ajax, 'validate_field' );
		$this->loader->add_action( 'wp_ajax_checkflow_get_shipping_methods', $ajax, 'get_shipping_methods' );
		$this->loader->add_action( 'wp_ajax_nopriv_checkflow_get_shipping_methods', $ajax, 'get_shipping_methods' );
		$this->loader->add_action( 'wp_ajax_checkflow_add_order_bump', $ajax, 'add_order_bump' );
		$this->loader->add_action( 'wp_ajax_nopriv_checkflow_add_order_bump', $ajax, 'add_order_bump' );
		$this->loader->add_action( 'wp_ajax_checkflow_place_order', $ajax, 'place_order' );
		$this->loader->add_action( 'wp_ajax_nopriv_checkflow_place_order', $ajax, 'place_order' );

		$this->loader->add_filter( 'woocommerce_checkout_cart_item_quantity', $checkout, 'render_checkout_quantity', 10, 3 );
		$this->loader->add_filter( 'woocommerce_checkout_fields', $field_editor, 'apply_checkout_fields', 20 );
		$this->loader->add_filter( 'woocommerce_add_to_cart_redirect', $checkout, 'maybe_direct_checkout_redirect', 20 );
		$this->loader->add_filter( 'option_woocommerce_enable_guest_checkout', $checkout, 'filter_guest_checkout_option', 20 );
		$this->loader->add_filter( 'woocommerce_checkout_registration_required', $checkout, 'filter_checkout_registration_required', 20 );
		$this->loader->add_action( 'woocommerce_after_checkout_validation', $checkout, 'validate_recaptcha', 20, 2 );
		$this->loader->add_action( 'woocommerce_before_checkout_form', $checkout, 'render_checkout_shell_intro', 5 );
		// Block checkout skips classic template hooks; prepend intro after blocks/shortcodes render.
		$this->loader->add_filter( 'the_content', $checkout, 'prepend_shell_intro_block_checkout', 12 );
		$this->loader->add_action( 'wp_footer', $checkout, 'render_direct_checkout_script' );
		$this->loader->add_action( 'wp_footer', $checkout, 'render_quick_setting_modules' );
		$this->loader->add_action( 'wp_footer', $checkout, 'render_recaptcha_script' );
		$this->loader->add_action( 'wp_footer', $checkout, 'render_trust_badges' );

		$this->loader->run();
	}

	/**
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Register a transient-backed notice flag.
	 */
	public function maybe_show_wc_notice() {
		if ( self::is_woocommerce_active() ) {
			delete_transient( 'checkflow_wc_notice' );
			return;
		}
		set_transient( 'checkflow_wc_notice', 1, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Render admin notice when WooCommerce is inactive.
	 */
	public function render_wc_notice() {
		if ( ! get_transient( 'checkflow_wc_notice' ) ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'CheckFlow requires WooCommerce to be active. Core checkout features are disabled until WooCommerce is activated.', 'checkflow' );
		echo '</p></div>';
	}
}
