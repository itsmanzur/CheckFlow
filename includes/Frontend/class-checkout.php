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
	 * Detect checkout pages even when WooCommerce conditionals are late or theme-filtered.
	 *
	 * @return bool
	 */
	private function is_checkout_request() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'is_page' ) && is_page( 'checkout' ) ) {
			return true;
		}

		$post = get_post();
		if ( $post && isset( $post->post_content ) ) {
			$content = (string) $post->post_content;
			return false !== strpos( $content, '[woocommerce_checkout]' ) || false !== strpos( $content, 'wp:woocommerce/checkout' );
		}

		return false;
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
		if ( ! $this->is_checkout_request() || is_order_received_page() ) {
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
		if ( ! $this->is_checkout_request() || is_order_received_page() ) {
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
	 * Add checkout template classes to the frontend checkout page.
	 *
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public function body_class( $classes ) {
		if ( is_admin() || ! $this->is_checkout_request() || is_order_received_page() ) {
			return $classes;
		}

		$template  = CheckFlow_Admin::instance()->get_checkout_template();
		$classes[] = 'checkflow-checkout-template';
		$classes[] = 'checkflow-template-' . sanitize_html_class( $template );
		return array_values( array_unique( $classes ) );
	}

	/**
	 * @return int
	 */
	private function get_order_bump_product_id() {
		$settings = CheckFlow_Admin::instance()->get_order_bump_settings();
		$product_id = absint( $settings['product_id'] );
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
		$settings = CheckFlow_Admin::instance()->get_order_bump_settings();
		if ( empty( $settings['enabled'] ) || ! $this->order_bump_rules_match( $settings, false ) ) {
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
	 * @param string $slot Offer slot: main or downsell.
	 * @return WC_Product|null
	 */
	private function get_upsell_product( $slot = 'main' ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$settings = CheckFlow_Admin::instance()->get_upsell_settings();
		if ( empty( $settings['enabled'] ) ) {
			return null;
		}
		$product_id = 'downsell' === $slot ? absint( $settings['downsell_product_id'] ) : absint( $settings['offer_product_id'] );
		if ( ! $product_id || $this->cart_has_product( $product_id ) ) {
			return null;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return null;
		}
		return $product;
	}

	/**
	 * @param array<string,mixed> $settings Upsell settings.
	 * @param WC_Product          $product Upsell product.
	 * @param string              $slot Offer slot.
	 * @param bool                $hidden Whether to render hidden.
	 * @param bool                $post_purchase Whether this is an order-received offer.
	 * @return string
	 */
	private function get_upsell_offer_markup( $settings, $product, $slot = 'main', $hidden = false, $post_purchase = false ) {
		$title = '' !== $settings['title'] ? $settings['title'] : $product->get_name();
		if ( 'downsell' === $slot ) {
			$title = sprintf(
				/* translators: %s: upsell title. */
				__( 'Last chance: %s', 'checkflow' ),
				$title
			);
		}
		$description = '' !== $settings['description'] ? $settings['description'] : __( 'Add this matched offer to your order.', 'checkflow' );
		$country_rules = $this->csv_to_strings( $settings['countries'] );
		$payment_rules = $this->csv_to_strings( $settings['payment_methods'] );
		$price_html = $this->get_upsell_price_html( $product, $settings );
		$classes = array( 'checkflow-upsell-module' );
		if ( 'downsell' === $slot ) {
			$classes[] = 'is-downsell';
		}
		if ( $post_purchase ) {
			$classes[] = 'is-post-purchase';
		}

		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-checkflow-upsell-product="' . esc_attr( (string) $product->get_id() ) . '" data-checkflow-upsell-slot="' . esc_attr( $slot ) . '" data-checkflow-upsell-countries="' . esc_attr( implode( ',', $country_rules ) ) . '" data-checkflow-upsell-payments="' . esc_attr( implode( ',', $payment_rules ) ) . '"';
		if ( $hidden ) {
			echo ' hidden data-checkflow-upsell-manual-hidden="1"';
		}
		if ( $post_purchase ) {
			echo ' data-checkflow-post-purchase="1"';
		}
		echo '>';
		echo '<div class="checkflow-upsell-media">' . wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ) . '</div>';
		echo '<div class="checkflow-upsell-copy">';
		echo '<span class="checkflow-upsell-badge">' . esc_html( 'downsell' === $slot ? __( 'Alternative offer', 'checkflow' ) : __( 'Special offer', 'checkflow' ) ) . '</span>';
		echo '<strong>' . esc_html( $title ) . '</strong>';
		echo '<small>' . esc_html( $description ) . '</small>';
		echo '<em>' . wp_kses_post( $price_html ) . '</em>';
		echo '</div>';
		echo '<div class="checkflow-upsell-actions">';
		echo '<button type="button" class="checkflow-upsell-accept">' . esc_html__( 'Add offer', 'checkflow' ) . '</button>';
		echo '<button type="button" class="checkflow-upsell-skip">' . esc_html__( 'No thanks', 'checkflow' ) . '</button>';
		echo '</div>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Build a clear offer price preview when an upsell discount is configured.
	 *
	 * @param WC_Product          $product Product.
	 * @param array<string,mixed> $settings Upsell settings.
	 * @return string
	 */
	private function get_upsell_price_html( $product, $settings ) {
		$base_price = (float) $product->get_price();
		if ( $base_price <= 0 || empty( $settings['discount_type'] ) || 'none' === $settings['discount_type'] || '' === (string) $settings['discount_value'] ) {
			return $product->get_price_html();
		}

		$discounted = $this->discounted_upsell_price( $base_price, (string) $settings['discount_type'], (float) $settings['discount_value'] );
		if ( $discounted >= $base_price ) {
			return $product->get_price_html();
		}

		return sprintf(
			'<del>%1$s</del> <ins>%2$s</ins>',
			wc_price( $base_price ),
			wc_price( $discounted )
		);
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
	 * Apply accepted upsell item discounts without creating coupons or touching payment submission.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function apply_upsell_cart_discounts( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! $cart || ! is_callable( array( $cart, 'get_cart' ) ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['checkflow_upsell'] ) || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}
			$original = isset( $cart_item['checkflow_upsell_original_price'] ) ? (float) $cart_item['checkflow_upsell_original_price'] : (float) $cart_item['data']->get_regular_price();
			if ( $original <= 0 ) {
				$original = (float) $cart_item['data']->get_price();
			}
			$type  = isset( $cart_item['checkflow_upsell_discount_type'] ) ? (string) $cart_item['checkflow_upsell_discount_type'] : 'none';
			$value = isset( $cart_item['checkflow_upsell_discount_value'] ) ? (float) $cart_item['checkflow_upsell_discount_value'] : 0;
			$price = $this->discounted_upsell_price( $original, $type, $value );
			$cart_item['data']->set_price( $price );
		}
	}

	/**
	 * Preserve upsell attribution and discount details on the WooCommerce order line item.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string,mixed>   $values Cart item values.
	 * @param WC_Order              $order Order.
	 */
	public function save_upsell_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['checkflow_upsell'] ) || ! is_object( $item ) ) {
			return;
		}
		$item->add_meta_data( '_checkflow_upsell', 'yes', true );
		$item->add_meta_data( '_checkflow_upsell_slot', isset( $values['checkflow_upsell_slot'] ) ? sanitize_key( (string) $values['checkflow_upsell_slot'] ) : 'main', true );
		if ( ! empty( $values['checkflow_upsell_discount_type'] ) && 'none' !== $values['checkflow_upsell_discount_type'] ) {
			$item->add_meta_data( '_checkflow_upsell_discount_type', sanitize_key( (string) $values['checkflow_upsell_discount_type'] ), true );
			$item->add_meta_data( '_checkflow_upsell_discount_value', wc_format_decimal( (string) $values['checkflow_upsell_discount_value'] ), true );
			$item->add_meta_data( '_checkflow_upsell_original_price', wc_format_decimal( (string) $values['checkflow_upsell_original_price'] ), true );
		}
	}

	/**
	 * @param array<string,mixed> $settings Upsell settings.
	 * @param bool                $check_dynamic Whether to check payment/country session state.
	 * @return bool
	 */
	private function upsell_rules_match( $settings, $check_dynamic = true ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

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

		if ( $check_dynamic ) {
			$countries = $this->csv_to_strings( $settings['countries'] );
			if ( $countries && ! in_array( $this->checkout_country(), $countries, true ) ) {
				return false;
			}
			$payment_methods = $this->csv_to_strings( $settings['payment_methods'] );
			if ( $payment_methods && ! in_array( $this->chosen_payment_method(), $payment_methods, true ) ) {
				return false;
			}
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
	 * @param array<string,mixed> $settings Order bump settings.
	 * @param bool                $check_dynamic Whether to check payment/country session state.
	 * @return bool
	 */
	private function order_bump_rules_match( $settings, $check_dynamic = true ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

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

		if ( $check_dynamic ) {
			$countries = $this->csv_to_strings( $settings['countries'] );
			if ( $countries && ! in_array( $this->checkout_country(), $countries, true ) ) {
				return false;
			}
			$payment_methods = $this->csv_to_strings( $settings['payment_methods'] );
			if ( $payment_methods && ! in_array( $this->chosen_payment_method(), $payment_methods, true ) ) {
				return false;
			}
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
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $ids;
		}
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
		if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
			return '';
		}
		$country = WC()->customer->get_shipping_country();
		if ( '' === $country ) {
			$country = WC()->customer->get_billing_country();
		}
		return strtolower( (string) $country );
	}

	/**
	 * @return string
	 */
	private function chosen_payment_method() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return sanitize_key( (string) WC()->session->get( 'chosen_payment_method', '' ) );
		}
		return '';
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

		$settings  = CheckFlow_Admin::instance()->get_order_bump_settings();
		$upsell_settings = CheckFlow_Admin::instance()->get_upsell_settings();
		$has_timer = $this->quick_setting_enabled( 'urgency_timer' );
		$bump      = ! empty( $settings['enabled'] ) ? $this->get_order_bump_product() : null;
		$upsell    = ! empty( $upsell_settings['enabled'] ) && 'pre_purchase' === $upsell_settings['flow_type'] && $this->upsell_rules_match( $upsell_settings, false ) ? $this->get_upsell_product( 'main' ) : null;
		$downsell  = $upsell ? $this->get_upsell_product( 'downsell' ) : null;
		if ( ! $has_timer && ! $bump && ! $upsell ) {
			return;
		}

		echo '<div class="checkflow-checkout-modules" data-checkflow-modules="1" data-checkflow-placement="' . esc_attr( $settings['placement'] ) . '">';
		if ( $has_timer ) {
			echo '<div class="checkflow-urgency-timer" data-checkflow-countdown-seconds="900">';
			echo '<span>' . esc_html__( 'Your checkout session is reserved', 'checkflow' ) . '</span>';
			echo '<strong data-checkflow-countdown>15:00</strong>';
			echo '</div>';
		}
		if ( $bump ) {
			$title = '' !== $settings['title'] ? $settings['title'] : $bump->get_name();
			$description = '' !== $settings['description'] ? $settings['description'] : __( 'One click add-on for this order.', 'checkflow' );
			$country_rules = $this->csv_to_strings( $settings['countries'] );
			$payment_rules = $this->csv_to_strings( $settings['payment_methods'] );
			echo '<div class="checkflow-order-bump-module" data-checkflow-bump-product="' . esc_attr( (string) $bump->get_id() ) . '" data-checkflow-bump-countries="' . esc_attr( implode( ',', $country_rules ) ) . '" data-checkflow-bump-payments="' . esc_attr( implode( ',', $payment_rules ) ) . '">';
			if ( '' !== $settings['badge'] ) {
				echo '<div class="checkflow-order-bump-badge">' . esc_html( $settings['badge'] ) . '</div>';
			}
			echo '<label>';
			echo '<input type="checkbox" class="checkflow-order-bump-checkbox" />';
			echo '<span><strong>' . esc_html( $title ) . '</strong><small>' . esc_html( $description ) . '</small><em>' . wp_kses_post( $bump->get_price_html() ) . '</em></span>';
			echo '</label>';
			echo '</div>';
		}
		if ( $upsell ) {
			echo $this->get_upsell_offer_markup( $upsell_settings, $upsell, 'main' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $downsell ) {
				echo $this->get_upsell_offer_markup( $upsell_settings, $downsell, 'downsell', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</div>';
	}

	/**
	 * Render a safe post-purchase upsell slot on the order-received page.
	 */
	public function render_order_received_upsell() {
		if ( is_admin() || wp_doing_ajax() || ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$settings = CheckFlow_Admin::instance()->get_upsell_settings();
		if ( empty( $settings['enabled'] ) || 'post_purchase' !== $settings['flow_type'] ) {
			return;
		}

		$product = $this->get_upsell_product( 'main' );
		if ( ! $product ) {
			return;
		}

		echo '<div class="checkflow-post-purchase-upsell">';
		echo '<h3>' . esc_html__( 'Before you go', 'checkflow' ) . '</h3>';
		echo '<p>' . esc_html__( 'Add this offer to a new checkout. Your completed order stays unchanged.', 'checkflow' ) . '</p>';
		echo $this->get_upsell_offer_markup( $settings, $product, 'main', false, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
