<?php
/**
 * Frontend checkout controller placeholder (Phase 1 target).
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Frontend_Checkout {

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
	 * Whether shell intro may show on this request.
	 *
	 * @return bool
	 */
	private function should_show_shell_intro() {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return false;
		}
		return true;
	}

	/**
	 * Trust badges may render on checkout AJAX fragments (classic); do not exclude `wp_doing_ajax`.
	 *
	 * @return bool
	 */
	private function trust_badges_context_ok() {
		if ( is_admin() ) {
			return false;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return false;
		}
		return true;
	}

	/**
	 * Markup for shell intro (classic + block checkout).
	 *
	 * @return string
	 */
	public function get_shell_intro_markup() {
		if ( ! $this->should_show_shell_intro() ) {
			return '';
		}
		ob_start();
		echo '<div class="checkflow-shell-intro">';
		echo '<h2>' . esc_html__( 'CheckFlow Checkout', 'checkflow' ) . '</h2>';
		echo '<p>' . esc_html__( 'Fast one-page checkout with live order updates.', 'checkflow' ) . '</p>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Render shell heading before classic checkout form.
	 */
	public function render_checkout_shell_intro() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup built with escaped strings in get_shell_intro_markup.
		echo $this->get_shell_intro_markup();
	}

	/**
	 * Prepend intro for WooCommerce **Block** checkout (no woocommerce_before_checkout_form).
	 *
	 * @param string $content Post content HTML (after do_blocks / do_shortcode).
	 * @return string
	 */
	public function prepend_shell_intro_block_checkout( $content ) {
		if ( ! $this->should_show_shell_intro() ) {
			return $content;
		}
		// Block checkout markup (WooCommerce Blocks).
		if ( false === strpos( $content, 'wc-block-checkout' ) && false === strpos( $content, 'wp-block-woocommerce-checkout' ) ) {
			return $content;
		}
		static $did = false;
		if ( $did ) {
			return $content;
		}
		$did = true;

		return $this->get_shell_intro_markup() . $content;
	}

	/**
	 * Replace static quantity text with input for AJAX quantity updates.
	 *
	 * @param string $product_quantity Original quantity html.
	 * @param array  $cart_item Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function render_checkout_quantity( $product_quantity, $cart_item, $cart_item_key ) {
		if ( ! isset( $cart_item['quantity'] ) ) {
			return $product_quantity;
		}
		$qty = max( 1, absint( $cart_item['quantity'] ) );
		return sprintf(
			'<input type="number" class="checkflow-qty-input" min="1" step="1" value="%1$d" data-item-key="%2$s" aria-label="%3$s" />',
			$qty,
			esc_attr( $cart_item_key ),
			esc_attr__( 'Quantity', 'checkflow' )
		);
	}

	/**
	 * Trust badges markup (classic: after `#place_order`; Blocks: below order summary).
	 *
	 * @return string
	 */
	public function get_trust_badges_markup() {
		if ( ! $this->trust_badges_context_ok() ) {
			return '';
		}
		ob_start();
		echo '<div class="checkflow-trust-badges" role="presentation">';
		echo '<span class="badge">' . esc_html__( 'SSL Secure', 'checkflow' ) . '</span>';
		echo '<span class="badge">' . esc_html__( 'Trusted Payment', 'checkflow' ) . '</span>';
		echo '<span class="badge">' . esc_html__( 'Fast Delivery', 'checkflow' ) . '</span>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Classic checkout: hooks after `#place_order` in order review.
	 */
	public function render_trust_badges() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_html__ in get_trust_badges_markup.
		echo $this->get_trust_badges_markup();
	}
}
