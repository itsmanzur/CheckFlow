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
	 * @param string $key Quick setting key.
	 * @return bool
	 */
	private function quick_setting_enabled( $key ) {
		$settings = CheckFlow_Admin::instance()->get_quick_settings();
		return ! empty( $settings[ $key ] );
	}

	/**
	 * @return int
	 */
	private function get_order_bump_product_id() {
		$product_id = absint( get_option( 'checkflow_order_bump_product_id', 0 ) );
		return absint( apply_filters( 'checkflow_order_bump_product_id', $product_id ) );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function cart_has_product( $product_id ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( absint( $cart_item['product_id'] ) === absint( $product_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return WC_Product|null
	 */
	private function get_order_bump_product() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product_id = $this->get_order_bump_product_id();
		if ( ! $product_id ) {
			return null;
		}
		if ( $this->cart_has_product( $product_id ) ) {
			return null;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return null;
		}
		return $product;
	}

	/**
	 * @return string
	 */
	private function get_recaptcha_site_key() {
		return (string) apply_filters( 'checkflow_recaptcha_site_key', get_option( 'checkflow_recaptcha_site_key', '' ) );
	}

	/**
	 * @return string
	 */
	private function get_recaptcha_secret_key() {
		return (string) apply_filters( 'checkflow_recaptcha_secret_key', get_option( 'checkflow_recaptcha_secret_key', '' ) );
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
	 * Send shoppers straight to checkout after a non-AJAX add-to-cart action.
	 *
	 * @param string $url WooCommerce redirect URL.
	 * @return string
	 */
	public function maybe_direct_checkout_redirect( $url ) {
		if ( is_admin() || wp_doing_ajax() ) {
			return $url;
		}
		if ( ! function_exists( 'wc_get_checkout_url' ) ) {
			return $url;
		}

		$settings = CheckFlow_Admin::instance()->get_quick_settings();
		if ( empty( $settings['direct_checkout'] ) ) {
			return $url;
		}

		return wc_get_checkout_url();
	}

	/**
	 * Support AJAX add-to-cart buttons by redirecting after WooCommerce confirms cart add.
	 */
	public function render_direct_checkout_script() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( ! function_exists( 'wc_get_checkout_url' ) ) {
			return;
		}

		$settings = CheckFlow_Admin::instance()->get_quick_settings();
		if ( empty( $settings['direct_checkout'] ) || ! empty( $settings['popup_checkout'] ) || ! empty( $settings['slide_checkout'] ) ) {
			return;
		}

		$checkout_url = wc_get_checkout_url();
		?>
		<script>
			(function () {
				if (!window.jQuery) {
					return;
				}
				window.jQuery(document.body).on("added_to_cart", function () {
					window.location.href = <?php echo wp_json_encode( $checkout_url ); ?>;
				});
			})();
		</script>
		<?php
	}

	/**
	 * Map Guest Checkout quick setting to WooCommerce's native option read.
	 *
	 * @param string $value Stored WooCommerce yes/no value.
	 * @return string
	 */
	public function filter_guest_checkout_option( $value ) {
		return $this->quick_setting_enabled( 'guest_checkout' ) ? 'yes' : 'no';
	}

	/**
	 * Keep classic checkout registration requirement aligned with Guest Checkout.
	 *
	 * @param bool $required Whether registration is required.
	 * @return bool
	 */
	public function filter_checkout_registration_required( $required ) {
		return $this->quick_setting_enabled( 'guest_checkout' ) ? false : true;
	}

	/**
	 * Render checkout modules controlled by quick settings.
	 */
	public function render_quick_setting_modules() {
		if ( ! $this->trust_badges_context_ok() ) {
			return;
		}

		$has_timer = $this->quick_setting_enabled( 'urgency_timer' );
		$bump      = $this->quick_setting_enabled( 'order_bump' ) ? $this->get_order_bump_product() : null;
		if ( ! $has_timer && ! $bump ) {
			return;
		}

		echo '<div class="checkflow-checkout-modules" data-checkflow-modules="1">';
		if ( $has_timer ) {
			echo '<div class="checkflow-urgency-timer" data-checkflow-countdown-seconds="900">';
			echo '<span>' . esc_html__( 'Your checkout session is reserved', 'checkflow' ) . '</span>';
			echo '<strong data-checkflow-countdown>15:00</strong>';
			echo '</div>';
		}
		if ( $bump ) {
			echo '<div class="checkflow-order-bump-module" data-checkflow-bump-product="' . esc_attr( (string) $bump->get_id() ) . '">';
			echo '<label>';
			echo '<input type="checkbox" class="checkflow-order-bump-checkbox" />';
			echo '<span><strong>' . esc_html( $bump->get_name() ) . '</strong><em>' . wp_kses_post( $bump->get_price_html() ) . '</em></span>';
			echo '</label>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render reCAPTCHA v3 integration when keys are configured.
	 */
	public function render_recaptcha_script() {
		if ( ! $this->trust_badges_context_ok() || ! $this->quick_setting_enabled( 'recaptcha' ) ) {
			return;
		}
		$site_key = $this->get_recaptcha_site_key();
		if ( '' === $site_key ) {
			return;
		}
		wp_enqueue_script(
			'google-recaptcha',
			'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ),
			array(),
			null,
			true
		);
		?>
		<script>
			(function () {
				var siteKey = <?php echo wp_json_encode( $site_key ); ?>;
				function attachToken() {
					if (!window.grecaptcha || !document.querySelector("form.checkout")) {
						return;
					}
					window.grecaptcha.ready(function () {
						window.grecaptcha.execute(siteKey, { action: "checkout" }).then(function (token) {
							var form = document.querySelector("form.checkout");
							if (!form) return;
							var input = form.querySelector('input[name="checkflow_recaptcha_token"]');
							if (!input) {
								input = document.createElement("input");
								input.type = "hidden";
								input.name = "checkflow_recaptcha_token";
								form.appendChild(input);
							}
							input.value = token;
						});
					});
				}
				document.addEventListener("DOMContentLoaded", attachToken);
				document.addEventListener("submit", attachToken, true);
			})();
		</script>
		<?php
	}

	/**
	 * Validate reCAPTCHA token for classic checkout when keys exist.
	 *
	 * @param array    $data Checkout data.
	 * @param WP_Error $errors Checkout errors.
	 */
	public function validate_recaptcha( $data, $errors ) {
		if ( ! $this->quick_setting_enabled( 'recaptcha' ) ) {
			return;
		}
		$secret = $this->get_recaptcha_secret_key();
		if ( '' === $secret ) {
			return;
		}
		$token = isset( $_POST['checkflow_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['checkflow_recaptcha_token'] ) ) : '';
		if ( '' === $token ) {
			$errors->add( 'checkflow_recaptcha', __( 'Security verification failed. Please refresh and try again.', 'checkflow' ) );
			return;
		}
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 5,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			$errors->add( 'checkflow_recaptcha', __( 'Security verification is unavailable. Please try again.', 'checkflow' ) );
			return;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) || ( isset( $body['score'] ) && (float) $body['score'] < 0.4 ) ) {
			$errors->add( 'checkflow_recaptcha', __( 'Security verification failed. Please try again.', 'checkflow' ) );
		}
	}

	/**
	 * Trust badges markup. Render once in the footer and move beside the order summary with JS.
	 *
	 * @return string
	 */
	public function get_trust_badges_markup() {
		if ( ! $this->trust_badges_context_ok() ) {
			return '';
		}
		ob_start();
		echo '<div class="checkflow-trust-badges" role="presentation">';
		echo '<span class="badge"><span class="checkflow-badge-icon is-lock" aria-hidden="true"></span>' . esc_html__( 'SSL Secure', 'checkflow' ) . '</span>';
		echo '<span class="badge"><span class="checkflow-badge-icon is-card" aria-hidden="true"></span>' . esc_html__( 'Trusted Payment', 'checkflow' ) . '</span>';
		echo '<span class="badge"><span class="checkflow-badge-icon is-truck" aria-hidden="true"></span>' . esc_html__( 'Fast Delivery', 'checkflow' ) . '</span>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Render a single trust badge source for classic and block checkout.
	 */
	public function render_trust_badges() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_html__ in get_trust_badges_markup.
		echo $this->get_trust_badges_markup();
	}
}
