<?php
/**
 * CheckFlow admin shell (pixel match to CheckFlow_Admin_Panel.html).
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$i18n           = CheckFlow_I18n::instance();
$locale         = $i18n->get_active_admin_locale();
$labels         = $i18n->locale_choices_labels();
$edit_locale    = isset( $_GET['cf_edit_lang'] ) ? sanitize_text_field( wp_unslash( $_GET['cf_edit_lang'] ) ) : $locale; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $edit_locale, CheckFlow_I18n::SUPPORTED_LOCALES, true ) ) {
	$edit_locale = $locale;
}

$order_amts = array(
	'en_US' => array( '$340', '$1,200', '$560', '$880', '$450', '$670', '$330' ),
	'bn_BD' => array( '৳৩৪০', '৳১,২০০', '৳৫৬০', '৳৮৮০', '৳৪৫০', '৳৬৭০', '৳৩৩০' ),
);
$amts       = isset( $order_amts[ $locale ] ) ? $order_amts[ $locale ] : $order_amts['en_US'];

$order_rows = array(
	array( '#1047', 'Rahim Molla', 'bkash', 'Pathao', 'paid' ),
	array( '#1046', 'Sumaiya Khatun', 'nagad', 'RedX', 'paid' ),
	array( '#1045', 'Karim Hossain', 'cod', 'Steadfast', 'pend' ),
	array( '#1044', 'Nasrin Akter', 'card', 'Pathao', 'paid' ),
	array( '#1043', 'Shafiqul Islam', 'bkash', 'RedX', 'paid' ),
	array( '#1042', 'Mehrin Akter', 'nagad', 'Pathao', 'fail' ),
	array( '#1041', 'Tarek Hossain', 'cod', 'Steadfast', 'paid' ),
);

$gw_map = array(
	'bkash' => 'gw.bkash',
	'nagad' => 'gw.nagad',
	'cod'   => 'gw.cod',
	'card'  => 'gw.card',
);
$st_map = array(
	'paid' => 'orders.st.paid',
	'pend' => 'orders.st.pending',
	'fail' => 'orders.st.fail',
);

$admin_instance = CheckFlow_Admin::instance();
$order_rows     = $admin_instance->get_recent_orders( 12 );
$order_metrics  = $admin_instance->get_order_metrics();
$dashboard_analytics = $admin_instance->get_dashboard_analytics( '7d' );
$courier_providers = $admin_instance->get_courier_providers();
$courier_settings  = $admin_instance->get_courier_settings();
$order_bump_settings = $admin_instance->get_order_bump_settings();
$order_bump_products = $admin_instance->get_order_bump_product_choices();
$order_bump_product  = ! empty( $order_bump_settings['product_id'] ) && function_exists( 'wc_get_product' ) ? wc_get_product( absint( $order_bump_settings['product_id'] ) ) : null;
$order_bump_product_label = $order_bump_product instanceof WC_Product ? sprintf( '#%1$d - %2$s', $order_bump_product->get_id(), $order_bump_product->get_name() ) : __( 'No product selected', 'checkflow' );
$order_bump_product_status = $order_bump_product instanceof WC_Product && $order_bump_product->is_purchasable() && $order_bump_product->is_in_stock() ? __( 'Product ready', 'checkflow' ) : __( 'Needs product', 'checkflow' );
$upsell_settings = $admin_instance->get_upsell_settings();
$upsell_products = $order_bump_products;
$upsell_offer_product = ! empty( $upsell_settings['offer_product_id'] ) && function_exists( 'wc_get_product' ) ? wc_get_product( absint( $upsell_settings['offer_product_id'] ) ) : null;
$upsell_offer_label = $upsell_offer_product instanceof WC_Product ? sprintf( '#%1$d - %2$s', $upsell_offer_product->get_id(), $upsell_offer_product->get_name() ) : __( 'No offer product selected', 'checkflow' );
$upsell_downsell_product = ! empty( $upsell_settings['downsell_product_id'] ) && function_exists( 'wc_get_product' ) ? wc_get_product( absint( $upsell_settings['downsell_product_id'] ) ) : null;
$upsell_downsell_label = $upsell_downsell_product instanceof WC_Product ? sprintf( '#%1$d - %2$s', $upsell_downsell_product->get_id(), $upsell_downsell_product->get_name() ) : '';
$pixel_settings    = $admin_instance->get_pixel_settings();
$pixel_events      = $admin_instance->get_recent_pixel_events( 8 );
$pixel_analytics   = $admin_instance->get_pixel_event_analytics();
$pixel_provider_status = array(
	'local'  => array(
		'label' => ! empty( $pixel_settings['local_enabled'] ) ? 'Ready' : 'Disabled',
		'items' => array(
			'WordPress local log table' => true,
			'Browser AJAX endpoint'     => true,
			! empty( $pixel_settings['local_enabled'] ) ? 'Local event capture on' : 'Local event capture off' => ! empty( $pixel_settings['local_enabled'] ),
		),
	),
	'meta'   => array(
		'label' => empty( $pixel_settings['meta_enabled'] ) ? 'Disabled' : ( ! empty( $pixel_settings['meta_pixel_id'] ) ? 'Ready' : 'Needs Pixel ID' ),
		'items' => array(
			! empty( $pixel_settings['meta_enabled'] ) ? 'Provider enabled' : 'Provider disabled' => ! empty( $pixel_settings['meta_enabled'] ),
			! empty( $pixel_settings['meta_pixel_id'] ) ? 'Pixel ID saved' : 'Pixel ID missing' => ! empty( $pixel_settings['meta_pixel_id'] ),
			! empty( $pixel_settings['meta_enabled'] ) && ! empty( $pixel_settings['meta_pixel_id'] ) ? 'Browser fire ready' : 'Browser fire paused' => ! empty( $pixel_settings['meta_enabled'] ) && ! empty( $pixel_settings['meta_pixel_id'] ),
		),
	),
	'google' => array(
		'label' => empty( $pixel_settings['google_enabled'] ) ? 'Disabled' : ( ( ! empty( $pixel_settings['google_measurement_id'] ) && ! empty( $pixel_settings['google_conversion_label'] ) ) ? 'Saved' : 'Needs IDs' ),
		'items' => array(
			! empty( $pixel_settings['google_enabled'] ) ? 'Provider enabled' : 'Provider disabled' => ! empty( $pixel_settings['google_enabled'] ),
			! empty( $pixel_settings['google_measurement_id'] ) ? 'Measurement ID saved' : 'Measurement ID missing' => ! empty( $pixel_settings['google_measurement_id'] ),
			! empty( $pixel_settings['google_conversion_label'] ) ? 'Conversion label saved' : 'Conversion label missing' => ! empty( $pixel_settings['google_conversion_label'] ),
		),
	),
	'tiktok' => array(
		'label' => empty( $pixel_settings['tiktok_enabled'] ) ? 'Disabled' : ( ( ! empty( $pixel_settings['tiktok_pixel_id'] ) || ! empty( $pixel_settings['tiktok_api_token'] ) ) ? 'Saved' : 'Needs ID/token' ),
		'items' => array(
			! empty( $pixel_settings['tiktok_enabled'] ) ? 'Provider enabled' : 'Provider disabled' => ! empty( $pixel_settings['tiktok_enabled'] ),
			! empty( $pixel_settings['tiktok_pixel_id'] ) ? 'Pixel ID saved' : 'Pixel ID missing' => ! empty( $pixel_settings['tiktok_pixel_id'] ),
			! empty( $pixel_settings['tiktok_api_token'] ) ? 'API token saved' : 'API token optional' => ! empty( $pixel_settings['tiktok_api_token'] ),
		),
	),
);

$str_keys       = $i18n->get_flat_keys_sorted();
$quick_settings = $admin_instance->get_quick_settings();
$admin_theme    = $admin_instance->get_admin_theme();
$checkout_templates = $admin_instance->get_checkout_templates();
$active_checkout_template = $admin_instance->get_checkout_template();
$field_rows     = class_exists( 'CheckFlow_Field_Editor' ) ? CheckFlow_Field_Editor::instance()->get_admin_rows() : array();
$field_groups   = array(
	'billing'  => array(),
	'shipping' => array(),
	'order'    => array(),
);
foreach ( $field_rows as $field ) {
	$group = isset( $field['group'] ) ? (string) $field['group'] : 'billing';
	if ( ! isset( $field_groups[ $group ] ) ) {
		$field_groups[ $group ] = array();
	}
	$field_groups[ $group ][] = $field;
}
$page_to_pane   = array(
	'checkflow-dashboard'    => 'dashboard',
	'checkflow-orders'       => 'orders',
	'checkflow-pixel'        => 'pixel',
	'checkflow-courier'      => 'courier',
	'checkflow-field-editor' => 'field_editor',
	'checkflow-templates'    => 'templates',
	'checkflow-order-bump'   => 'order_bump',
	'checkflow-upsell'       => 'upsell',
	'checkflow-bkash-nagad'  => 'bkash_nagad',
	'checkflow-settings'     => 'settings',
);
$current_page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'checkflow-dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_pane    = isset( $page_to_pane[ $current_page ] ) ? $page_to_pane[ $current_page ] : 'dashboard';
$pane_class     = static function ( $pane ) use ( $active_pane ) {
	return $pane === $active_pane ? ' on' : '';
};
$screen_class   = static function ( $pane ) use ( $active_pane ) {
	return $pane === $active_pane ? ' is-active' : '';
};
$toggle_class   = static function ( $key ) use ( $quick_settings ) {
	return ! empty( $quick_settings[ $key ] ) ? ' on' : '';
};
$screen_titles  = array(
	'dashboard'    => array( 'nav.dashboard', 'screen.dashboard.sub' ),
	'orders'       => array( 'nav.orders', 'screen.orders.sub' ),
	'pixel'        => array( 'nav.pixel', 'screen.pixel.sub' ),
	'courier'      => array( 'nav.courier', 'screen.courier.sub' ),
	'field_editor' => array( 'nav.field_editor', 'screen.field_editor.sub' ),
	'templates'    => array( 'nav.templates', 'screen.templates.sub' ),
	'order_bump'   => array( 'nav.order_bump', 'screen.order_bump.sub' ),
	'upsell'       => array( 'nav.upsell', 'screen.upsell.sub' ),
	'bkash_nagad'  => array( 'nav.bkash_nagad', 'screen.bkash_nagad.sub' ),
	'settings'     => array( 'nav.settings', 'screen.settings.sub' ),
);
$title_keys     = isset( $screen_titles[ $active_pane ] ) ? $screen_titles[ $active_pane ] : $screen_titles['dashboard'];
?>
<div id="checkflow-admin" class="checkflow-root<?php echo 'light' === $admin_theme ? ' is-light' : ''; ?>" data-admin-theme="<?php echo esc_attr( $admin_theme ); ?>">
	<aside class="sb">
		<div class="logo">
			<div class="logo-row">
				<div class="logo-ico">⚡</div>
				<div>
					<div class="logo-name">Check<em>Flow</em></div>
					<div class="logo-ver"><?php echo esc_html( checkflow_str( 'logo.version' ) ); ?></div>
				</div>
			</div>
		</div>

		<nav class="nav">
			<div class="nav-sec"><?php echo esc_html( checkflow_str( 'nav.sec_main' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'dashboard' ) ); ?>" data-screen="dashboard" role="button" tabindex="0"><span class="ni-ico">📊</span> <?php echo esc_html( checkflow_str( 'nav.dashboard' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'orders' ) ); ?>" data-screen="orders" role="button" tabindex="0"><span class="ni-ico">🛒</span> <?php echo esc_html( checkflow_str( 'nav.orders' ) ); ?> <span class="badge g"><?php echo esc_html( checkflow_str( 'nav.badge_orders' ) ); ?></span></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'pixel' ) ); ?>" data-screen="pixel" role="button" tabindex="0"><span class="ni-ico">📡</span> <?php echo esc_html( checkflow_str( 'nav.pixel' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'courier' ) ); ?>" data-screen="courier" role="button" tabindex="0"><span class="ni-ico">📦</span> <?php echo esc_html( checkflow_str( 'nav.courier' ) ); ?></div>

			<div class="nav-sec"><?php echo esc_html( checkflow_str( 'nav.sec_checkout' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'field_editor' ) ); ?>" data-screen="field_editor" role="button" tabindex="0"><span class="ni-ico">🧩</span> <?php echo esc_html( checkflow_str( 'nav.field_editor' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'templates' ) ); ?>" data-screen="templates" role="button" tabindex="0"><span class="ni-ico">🎨</span> <?php echo esc_html( checkflow_str( 'nav.templates' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'order_bump' ) ); ?>" data-screen="order_bump" role="button" tabindex="0"><span class="ni-ico">🎁</span> <?php echo esc_html( checkflow_str( 'nav.order_bump' ) ); ?> <span class="badge">!</span></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'upsell' ) ); ?>" data-screen="upsell" role="button" tabindex="0"><span class="ni-ico">🚀</span> <?php echo esc_html( checkflow_str( 'nav.upsell' ) ); ?></div>

			<div class="nav-sec"><?php echo esc_html( checkflow_str( 'nav.sec_payment' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'bkash_nagad' ) ); ?>" data-screen="bkash_nagad" role="button" tabindex="0"><span class="ni-ico">💳</span> <?php echo esc_html( checkflow_str( 'nav.bkash_nagad' ) ); ?></div>
			<div class="ni<?php echo esc_attr( $pane_class( 'settings' ) ); ?>" data-screen="settings" role="button" tabindex="0"><span class="ni-ico">⚙️</span> <?php echo esc_html( checkflow_str( 'nav.settings' ) ); ?></div>
		</nav>

		<div class="sb-foot">
			<div class="up-card">
				<p><strong><?php echo esc_html( checkflow_str( 'upgrade.title' ) ); ?></strong><?php echo esc_html( checkflow_str( 'upgrade.sub' ) ); ?></p>
				<button type="button" class="btn-up"><?php echo esc_html( checkflow_str( 'upgrade.btn' ) ); ?></button>
			</div>
		</div>
	</aside>

	<div class="main">
		<div class="topbar">
			<div class="tb-title" id="cf-ttl"><?php echo esc_html( checkflow_str( $title_keys[0] ) ); ?> <span><?php echo esc_html( checkflow_str( $title_keys[1] ) ); ?></span></div>
			<div class="tb-acts">
				<div class="cf-locale-select">
					<label for="cf-ui-locale"><?php echo esc_html( checkflow_str( 'lang.ui_label' ) ); ?></label>
					<select id="cf-ui-locale" class="cf-locale-switch" aria-label="<?php echo esc_attr( checkflow_str( 'lang.ui_label' ) ); ?>">
						<?php foreach ( $labels as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $locale, $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tabs-row">
					<div class="tab on" data-period="7d"><?php echo esc_html( checkflow_str( 'tab.7d' ) ); ?></div>
					<div class="tab" data-period="30d"><?php echo esc_html( checkflow_str( 'tab.30d' ) ); ?></div>
					<div class="tab" data-period="all"><?php echo esc_html( checkflow_str( 'tab.all' ) ); ?></div>
				</div>
				<button type="button" class="cf-theme-toggle" data-admin-theme-toggle aria-pressed="<?php echo 'light' === $admin_theme ? 'true' : 'false'; ?>" title="Toggle light theme">
					<span class="cf-theme-toggle-icon"><?php echo 'light' === $admin_theme ? '☀' : '☾'; ?></span>
					<span data-admin-theme-label><?php echo 'light' === $admin_theme ? 'Light' : 'Dark'; ?></span>
				</button>
				<div class="date-btn"><?php echo esc_html( checkflow_str( 'topbar.date_range' ) ); ?></div>
				<button type="button" class="btn-p"><?php echo esc_html( checkflow_str( 'topbar.new_bump' ) ); ?></button>
				<div class="avatar">R<div class="ndot"></div></div>
			</div>
		</div>

		<div class="cnt">
			<div class="cf-pane<?php echo esc_attr( $screen_class( 'dashboard' ) ); ?>" data-pane="dashboard">
				<div class="sg">
					<div class="sc bl">
						<div class="slbl"><?php echo esc_html( checkflow_str( 'stats.total_revenue' ) ); ?></div>
						<div class="sval bl"><?php echo esc_html( $dashboard_analytics['cards']['revenue']['value'] ); ?></div>
						<div class="sdlt <?php echo esc_attr( $dashboard_analytics['cards']['revenue']['delta_class'] ); ?>"><?php echo esc_html( $dashboard_analytics['cards']['revenue']['delta'] ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( $dashboard_analytics['cards']['revenue']['context'] ); ?></div>
					</div>
					<div class="sc gn">
						<div class="slbl"><?php echo esc_html( checkflow_str( 'stats.success_orders' ) ); ?></div>
						<div class="sval gn"><?php echo esc_html( $dashboard_analytics['cards']['orders']['value'] ); ?></div>
						<div class="sdlt <?php echo esc_attr( $dashboard_analytics['cards']['orders']['delta_class'] ); ?>"><?php echo esc_html( $dashboard_analytics['cards']['orders']['delta'] ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( $dashboard_analytics['cards']['orders']['context'] ); ?></div>
					</div>
					<div class="sc or">
						<div class="slbl"><?php echo esc_html( checkflow_str( 'stats.bump_revenue' ) ); ?></div>
						<div class="sval or"><?php echo esc_html( $dashboard_analytics['cards']['bump']['value'] ); ?></div>
						<div class="sdlt <?php echo esc_attr( $dashboard_analytics['cards']['bump']['delta_class'] ); ?>"><?php echo esc_html( $dashboard_analytics['cards']['bump']['delta'] ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( $dashboard_analytics['cards']['bump']['context'] ); ?></div>
					</div>
					<div class="sc pu">
						<div class="slbl"><?php echo esc_html( checkflow_str( 'stats.avg_order' ) ); ?></div>
						<div class="sval pu"><?php echo esc_html( $dashboard_analytics['cards']['average']['value'] ); ?></div>
						<div class="sdlt <?php echo esc_attr( $dashboard_analytics['cards']['average']['delta_class'] ); ?>"><?php echo esc_html( $dashboard_analytics['cards']['average']['delta'] ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( $dashboard_analytics['cards']['average']['context'] ); ?></div>
					</div>
				</div>

				<div class="cf-dashboard-source">
					<span>Real analytics</span>
					<strong>WooCommerce orders</strong>
					<em>+</em>
					<strong>CheckFlow local events</strong>
				</div>

				<div class="g2">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'funnel.title' ) ); ?></div><div class="pa"><?php echo esc_html( checkflow_str( 'funnel.details' ) ); ?></div></div>
						<div class="pb">
							<div class="funnel">
								<?php foreach ( $dashboard_analytics['funnel'] as $row ) : ?>
									<div class="fr">
										<div class="flbl"><?php echo esc_html( $row['label'] ); ?></div>
										<div class="fbw"><div class="fb <?php echo esc_attr( $row['class'] ); ?>" style="width:<?php echo esc_attr( (string) $row['width'] ); ?>%"><?php echo esc_html( sprintf( '%d people', absint( $row['value'] ) ) ); ?></div></div>
										<div class="fnum"><?php echo esc_html( (string) absint( $row['value'] ) ); ?></div>
										<div class="fdrop"><?php echo esc_html( (string) $row['drop'] ); ?></div>
									</div>
								<?php endforeach; ?>
							</div>
							<div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--bd)">
								<div style="font-size:12px;font-weight:700;color:var(--tx);margin-bottom:12px"><?php echo esc_html( checkflow_str( 'bump_perf.title' ) ); ?></div>
								<div class="blist">
									<div class="brow">
										<div class="bimg">👕</div>
										<div class="binf">
											<div class="bn"><?php echo esc_html( $dashboard_analytics['bump']['name'] ); ?></div>
											<div class="bm"><?php echo esc_html( $dashboard_analytics['bump']['product_id'] ? sprintf( '%d added - %s revenue', absint( $dashboard_analytics['bump']['quantity'] ), $dashboard_analytics['cards']['bump']['value'] ) : 'Configure a bump product to track revenue.' ); ?></div>
										</div>
										<div class="brt"><div class="bpct"><?php echo esc_html( $dashboard_analytics['cards']['bump']['delta'] ); ?></div><div class="brlbl"><?php echo esc_html( checkflow_str( 'bump.rate_lbl' ) ); ?></div></div>
									</div>
									<div class="brow">
										<div class="bimg">🎁</div>
										<div class="binf">
											<div class="bn">Checkout started</div>
											<div class="bm">From CheckFlow local InitiateCheckout events</div>
										</div>
										<div class="brt"><div class="bpct"><?php echo esc_html( (string) absint( $dashboard_analytics['funnel'][2]['value'] ) ); ?></div><div class="brlbl">Events</div></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'payment.title' ) ); ?></div><div class="pa"><?php echo esc_html( checkflow_str( 'payment.settings' ) ); ?></div></div>
						<div class="pb">
							<div class="plist">
								<?php foreach ( $dashboard_analytics['payment_mix'] as $payment_row ) : ?>
									<div class="pi">
										<div class="pdot" style="background:<?php echo esc_attr( $payment_row['color'] ); ?>"></div>
										<div class="pname"><?php echo esc_html( $payment_row['label'] ); ?></div>
										<div class="pbw"><div class="pbar" style="width:<?php echo esc_attr( (string) absint( $payment_row['percent'] ) ); ?>%;background:<?php echo esc_attr( $payment_row['color'] ); ?>"></div></div>
										<div class="ppct"><?php echo esc_html( absint( $payment_row['percent'] ) . '%' ); ?></div>
									</div>
								<?php endforeach; ?>
							</div>
							<div style="margin-top:16px;border-top:1px solid var(--bd);padding-top:14px">
								<div style="font-size:11px;color:var(--tx3);margin-bottom:8px"><?php echo esc_html( checkflow_str( 'payment.daily' ) ); ?></div>
								<div class="mct" id="cf-mc"></div>
								<div class="cx" id="cf-cx"></div>
							</div>
							<div style="margin-top:16px;border-top:1px solid var(--bd);padding-top:14px">
								<div style="font-size:12px;font-weight:700;color:var(--tx);margin-bottom:8px"><?php echo esc_html( checkflow_str( 'courier.summary_title' ) ); ?></div>
								<div class="cg">
									<div class="cc"><div class="ccn">Pathao</div><div class="cco"><?php echo esc_html( (string) absint( $dashboard_analytics['couriers']['pathao'] ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div>
									<div class="cc"><div class="ccn">RedX</div><div class="cco"><?php echo esc_html( (string) absint( $dashboard_analytics['couriers']['redx'] ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div>
									<div class="cc"><div class="ccn">Steadfast</div><div class="cco"><?php echo esc_html( (string) absint( $dashboard_analytics['couriers']['steadfast'] ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="g3">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'recent_orders.title' ) ); ?></div><div class="pa"><?php echo esc_html( checkflow_str( 'recent_orders.view_all' ) ); ?></div></div>
						<div class="pb" style="padding:0">
							<table class="ot">
								<thead>
									<tr>
										<th style="padding:12px 10px 10px"><?php echo esc_html( checkflow_str( 'orders.th_id' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_customer' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_payment' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_courier' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_amount' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_status' ) ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( array_slice( $order_rows, 0, 7 ) as $r ) : ?>
										<tr>
											<td><a class="oid" href="<?php echo esc_url( $r['edit_url'] ); ?>"><?php echo esc_html( $r['id'] ); ?></a></td>
											<td><span class="ocust"><?php echo esc_html( $r['customer'] ); ?></span></td>
											<td><span class="gtag <?php echo esc_attr( $r['payment_class'] ); ?>"><?php echo esc_html( $r['payment'] ); ?></span></td>
											<td><span class="ocourier<?php echo in_array( $r['courier_status'], array( 'draft_ready', 'booked' ), true ) ? ' is-ready' : ''; ?>"><?php echo esc_html( $r['courier'] ); ?></span></td>
											<td><span class="oamt"><?php echo esc_html( $r['amount'] ); ?></span></td>
											<td><span class="stag <?php echo esc_attr( $r['status_class'] ); ?>"><?php echo esc_html( $r['status'] ); ?></span></td>
										</tr>
									<?php endforeach; ?>
									<?php if ( empty( $order_rows ) ) : ?>
										<tr><td colspan="6" class="cf-empty-row">No WooCommerce orders found yet.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<div class="gcol">
						<div class="panel">
							<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'pixel.title' ) ); ?></div><div class="pa"><?php echo esc_html( checkflow_str( 'pixel.configure' ) ); ?></div></div>
							<div class="pb">
								<div class="pxl">
									<div class="pxi">
										<span style="font-size:18px">📘</span>
										<div><div class="pxn">Meta CAPI</div><div class="pxe"><?php echo esc_html( checkflow_str( 'pixel.meta_ev' ) ); ?></div></div>
										<div class="pxs ok"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.active' ) ); ?></div>
									</div>
									<div class="pxi">
										<span style="font-size:18px">🎯</span>
										<div><div class="pxn">Google Enhanced</div><div class="pxe"><?php echo esc_html( checkflow_str( 'pixel.google_ev' ) ); ?></div></div>
										<div class="pxs ok"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.active' ) ); ?></div>
									</div>
									<div class="pxi">
										<span style="font-size:18px">🎵</span>
										<div><div class="pxn">TikTok Events API</div><div class="pxe"><?php echo esc_html( checkflow_str( 'pixel.tiktok_no_key' ) ); ?></div></div>
										<div class="pxs warn"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.set_up' ) ); ?></div>
									</div>
								</div>
							</div>
						</div>
						<div class="panel">
							<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'quick_settings.title' ) ); ?></div></div>
							<div class="pb">
								<div class="slist">
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.direct_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.direct_desc' ) ); ?></div></div>
										<div class="tgl<?php echo esc_attr( $toggle_class( 'direct_checkout' ) ); ?>" data-setting="direct_checkout" role="switch" aria-checked="<?php echo ! empty( $quick_settings['direct_checkout'] ) ? 'true' : 'false'; ?>" tabindex="0"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.popup_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.popup_desc' ) ); ?></div></div>
										<div class="tgl<?php echo esc_attr( $toggle_class( 'popup_checkout' ) ); ?>" data-setting="popup_checkout" role="switch" aria-checked="<?php echo ! empty( $quick_settings['popup_checkout'] ) ? 'true' : 'false'; ?>" tabindex="0"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.slide_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.slide_desc' ) ); ?></div></div>
										<div class="tgl<?php echo esc_attr( $toggle_class( 'slide_checkout' ) ); ?>" data-setting="slide_checkout" role="switch" aria-checked="<?php echo ! empty( $quick_settings['slide_checkout'] ) ? 'true' : 'false'; ?>" tabindex="0"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.bump_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.bump_desc' ) ); ?></div></div>
										<div class="tgl<?php echo esc_attr( $toggle_class( 'order_bump' ) ); ?>" data-setting="order_bump" role="switch" aria-checked="<?php echo ! empty( $quick_settings['order_bump'] ) ? 'true' : 'false'; ?>" tabindex="0"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.timer_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.timer_desc' ) ); ?></div></div>
										<div class="tgl<?php echo esc_attr( $toggle_class( 'urgency_timer' ) ); ?>" data-setting="urgency_timer" role="switch" aria-checked="<?php echo ! empty( $quick_settings['urgency_timer'] ) ? 'true' : 'false'; ?>" tabindex="0"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.recaptcha_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.recaptcha_desc' ) ); ?></div></div>
										<div class="tgl<?php echo esc_attr( $toggle_class( 'recaptcha' ) ); ?>" data-setting="recaptcha" role="switch" aria-checked="<?php echo ! empty( $quick_settings['recaptcha'] ) ? 'true' : 'false'; ?>" tabindex="0"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.guest_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.guest_desc' ) ); ?></div></div>
										<div class="tgl<?php echo esc_attr( $toggle_class( 'guest_checkout' ) ); ?>" data-setting="guest_checkout" role="switch" aria-checked="<?php echo ! empty( $quick_settings['guest_checkout'] ) ? 'true' : 'false'; ?>" tabindex="0"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div><!-- dashboard -->

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'orders' ) ); ?>" data-pane="orders">
				<div class="cf-orders-metrics">
					<div class="cf-order-metric"><span>Total recent</span><strong><?php echo esc_html( $order_metrics['total_orders'] ); ?></strong><small>Last 50 orders</small></div>
					<div class="cf-order-metric"><span>Processing</span><strong><?php echo esc_html( $order_metrics['processing'] ); ?></strong><small>Paid or in progress</small></div>
					<div class="cf-order-metric"><span>Pending</span><strong><?php echo esc_html( $order_metrics['pending'] ); ?></strong><small>Needs attention</small></div>
					<div class="cf-order-metric"><span>Revenue</span><strong><?php echo esc_html( $order_metrics['revenue'] ); ?></strong><small>Completed/processing</small></div>
				</div>
				<div class="cf-orders-toolbar">
					<label class="cf-orders-search">
						<span>Search orders</span>
						<input type="search" class="cf-orders-search-input" placeholder="Order ID, customer, payment, courier, status" aria-label="Search orders" />
					</label>
					<div class="cf-orders-filter-group" aria-label="Filter orders by status">
						<button type="button" class="cf-order-filter is-active" data-order-filter="status" data-filter-value="all">All</button>
						<button type="button" class="cf-order-filter" data-order-filter="status" data-filter-value="paid">Processing</button>
						<button type="button" class="cf-order-filter" data-order-filter="status" data-filter-value="pend">Pending</button>
						<button type="button" class="cf-order-filter" data-order-filter="status" data-filter-value="fail">Other</button>
					</div>
					<div class="cf-orders-filter-group" aria-label="Filter orders by payment">
						<button type="button" class="cf-order-filter is-active" data-order-filter="payment" data-filter-value="all">All payments</button>
						<button type="button" class="cf-order-filter" data-order-filter="payment" data-filter-value="cod">COD</button>
						<button type="button" class="cf-order-filter" data-order-filter="payment" data-filter-value="mobile">bKash/Nagad</button>
						<button type="button" class="cf-order-filter" data-order-filter="payment" data-filter-value="card">Card/Other</button>
					</div>
					<div class="cf-orders-count"><strong data-orders-visible-count><?php echo esc_html( count( $order_rows ) ); ?></strong><span>shown</span></div>
				</div>
				<div class="cf-orders-bulkbar" data-orders-bulkbar hidden>
					<div><strong data-orders-selected-count>0</strong><span> selected</span></div>
					<button type="button" class="cf-btn-ghost" data-order-bulk-action="courier">Prepare courier</button>
					<button type="button" class="cf-btn-ghost" data-order-bulk-action="followup">Payment follow-up</button>
					<button type="button" class="cf-btn-ghost" data-order-bulk-action="export">Export selected</button>
					<button type="button" class="cf-btn-ghost" data-order-clear-selection>Clear</button>
				</div>
				<div class="g2 cf-orders-layout">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.orders' ) ); ?></div><div class="pa">WooCommerce sync</div></div>
						<div class="pb cf-orders-table-wrap">
							<table class="ot">
								<colgroup>
									<col class="cf-order-col-select" />
									<col class="cf-order-col-id" />
									<col class="cf-order-col-customer" />
									<col class="cf-order-col-payment" />
									<col class="cf-order-col-courier" />
									<col class="cf-order-col-amount" />
									<col class="cf-order-col-status" />
								</colgroup>
								<thead>
									<tr>
										<th class="cf-order-select-col"><input type="checkbox" data-order-select-all aria-label="Select all visible orders" /></th>
										<th style="padding:12px 10px 10px"><?php echo esc_html( checkflow_str( 'orders.th_id' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_customer' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_payment' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_courier' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_amount' ) ); ?></th>
										<th><?php echo esc_html( checkflow_str( 'orders.th_status' ) ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $order_rows as $r ) : ?>
										<?php
										$order_payment_filter = in_array( $r['payment_class'], array( 'bkash', 'nagad' ), true ) ? 'mobile' : ( 'gateway-card' === $r['payment_class'] ? 'card' : $r['payment_class'] );
										$order_search_text    = strtolower( implode( ' ', array( $r['id'], $r['customer'], $r['payment'], $r['courier'], $r['amount'], $r['status'], $r['date'] ) ) );
										$order_detail_json    = wp_json_encode(
											array(
												'orderId'  => $r['order_id'],
												'id'       => $r['id'],
												'customer' => $r['customer'],
												'email'    => $r['email'],
												'phone'    => $r['phone'],
												'address'  => $r['address'],
												'payment'  => $r['payment'],
												'paymentClass' => $r['payment_class'],
												'courier'  => $r['courier'],
												'courierProvider' => $r['courier_provider'],
												'courierStatus' => $r['courier_status'],
												'amount'   => $r['amount'],
												'status'   => $r['status'],
												'statusClass' => $r['status_class'],
												'statusKey' => $r['status_key'],
												'date'     => $r['date'],
												'items'    => $r['items'],
												'editUrl'  => $r['edit_url'],
											)
										);
										?>
										<tr data-order-row data-order-status="<?php echo esc_attr( $r['status_class'] ); ?>" data-order-payment="<?php echo esc_attr( $order_payment_filter ); ?>" data-order-search="<?php echo esc_attr( $order_search_text ); ?>" data-order-detail="<?php echo esc_attr( $order_detail_json ); ?>">
											<td class="cf-order-select-col"><input type="checkbox" data-order-select aria-label="<?php echo esc_attr( sprintf( 'Select order %s', $r['id'] ) ); ?>" /></td>
											<td><a class="oid" href="<?php echo esc_url( $r['edit_url'] ); ?>"><?php echo esc_html( $r['id'] ); ?></a></td>
											<td><span class="ocust"><?php echo esc_html( $r['customer'] ); ?></span></td>
											<td><span class="gtag <?php echo esc_attr( $r['payment_class'] ); ?>"><?php echo esc_html( $r['payment'] ); ?></span></td>
											<td><span class="ocourier<?php echo in_array( $r['courier_status'], array( 'draft_ready', 'booked' ), true ) ? ' is-ready' : ''; ?>"><?php echo esc_html( $r['courier'] ); ?></span></td>
											<td><span class="oamt"><?php echo esc_html( $r['amount'] ); ?></span></td>
											<td><span class="stag <?php echo esc_attr( $r['status_class'] ); ?>"><?php echo esc_html( $r['status'] ); ?></span></td>
										</tr>
									<?php endforeach; ?>
									<?php if ( empty( $order_rows ) ) : ?>
										<tr><td colspan="7" class="cf-empty-row">No WooCommerce orders found yet.</td></tr>
									<?php endif; ?>
									<tr class="cf-orders-no-results" hidden><td colspan="7" class="cf-empty-row">No matching orders found.</td></tr>
								</tbody>
							</table>
						</div>
					</div>
					<div class="gcol">
						<div class="panel"><div class="ph"><div class="pt">Order actions</div></div><div class="pb"><div class="cf-action-row"><span>Bulk courier booking</span><button type="button" class="btn-p" data-order-bulk-action="courier">Prepare</button></div><div class="cf-action-row"><span>Failed payment follow-up</span><button type="button" class="btn-p" data-order-bulk-action="followup">Review</button></div><div class="cf-action-row"><span>CSV export</span><button type="button" class="btn-p" data-order-bulk-action="export">Export</button></div></div></div>
						<div class="panel"><div class="ph"><div class="pt">Status mix</div></div><div class="pb"><div class="cf-mini-grid"><div class="cf-mini-card"><strong><?php echo esc_html( $order_metrics['processing'] ); ?></strong><span>Processing</span></div><div class="cf-mini-card"><strong><?php echo esc_html( $order_metrics['pending'] ); ?></strong><span>Pending</span></div><div class="cf-mini-card"><strong><?php echo esc_html( max( 0, absint( $order_metrics['total_orders'] ) - absint( $order_metrics['processing'] ) - absint( $order_metrics['pending'] ) ) ); ?></strong><span>Other</span></div></div></div></div>
					</div>
				</div>
				<div class="cf-order-drawer-backdrop" data-order-drawer-close hidden></div>
				<aside class="cf-order-drawer" aria-hidden="true" aria-label="Order details">
					<div class="cf-order-drawer-head">
						<div><span>Order details</span><strong data-order-detail-id>Order</strong></div>
						<button type="button" class="cf-order-drawer-close" data-order-drawer-close aria-label="Close order details">×</button>
					</div>
					<div class="cf-order-drawer-body">
						<div class="cf-order-detail-status"><span data-order-detail-status>Status</span><strong data-order-detail-amount>0</strong></div>
						<div class="cf-order-activity" data-order-activity hidden></div>
						<div class="cf-order-detail-section"><small>Customer</small><strong data-order-detail-customer></strong><span data-order-detail-email></span><span data-order-detail-phone></span></div>
						<div class="cf-order-detail-section"><small>Address</small><p data-order-detail-address></p></div>
						<div class="cf-order-detail-grid"><div><small>Payment</small><strong data-order-detail-payment></strong></div><div><small>Courier</small><strong data-order-detail-courier></strong></div><div><small>Date</small><strong data-order-detail-date></strong></div></div>
						<div class="cf-pathao-review" data-pathao-review hidden></div>
						<div class="cf-order-detail-section"><small>Items</small><div class="cf-order-detail-items" data-order-detail-items></div></div>
						<div class="cf-order-workflow">
							<div class="cf-order-workflow-head"><small>Status workflow</small><span>Real WooCommerce update</span></div>
							<div class="cf-order-status-actions">
								<button type="button" class="cf-order-status-action" data-order-status-draft="processing">Mark processing</button>
								<button type="button" class="cf-order-status-action" data-order-status-draft="completed">Mark completed</button>
								<button type="button" class="cf-order-status-action danger" data-order-status-draft="cancelled">Cancel order</button>
							</div>
							<div class="cf-order-confirm" data-order-status-confirm hidden>
								<span data-order-status-confirm-text></span>
								<div>
									<button type="button" class="cf-btn-ghost" data-order-status-cancel>Cancel</button>
									<button type="button" class="btn-p" data-order-status-confirm-btn>Update status</button>
								</div>
							</div>
						</div>
						<div class="cf-order-note-box">
							<div class="cf-order-workflow-head"><small>Order note</small><span>Saves to WooCommerce</span></div>
							<select data-order-note-type aria-label="Order note type">
								<option value="internal">Internal note</option>
								<option value="customer">Customer note</option>
							</select>
							<textarea data-order-note-text placeholder="Write a quick note for this order..."></textarea>
							<div class="cf-order-note-actions">
								<button type="button" class="cf-btn-ghost" data-order-note-clear>Clear</button>
								<button type="button" class="btn-p" data-order-note-draft>Save note</button>
							</div>
							<div class="cf-order-note-preview" data-order-note-preview hidden></div>
						</div>
					</div>
					<div class="cf-order-drawer-actions">
						<a class="btn-p" data-order-detail-edit href="#">View Woo order</a>
						<button type="button" class="cf-btn-ghost" data-copy-order-phone>Copy phone</button>
						<button type="button" class="cf-btn-ghost" data-copy-order-address>Copy address</button>
						<button type="button" class="cf-btn-ghost" data-order-single-action="courier">Prepare courier</button>
						<button type="button" class="cf-btn-ghost" data-review-pathao-booking>Review Pathao booking</button>
						<button type="button" class="btn-p" data-book-pathao-order>Book Pathao live</button>
					</div>
				</aside>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'pixel' ) ); ?>" data-pane="pixel">
				<div class="g2">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.pixel' ) ); ?></div><div class="pa">Local Event Log</div></div>
						<div class="pb">
							<div class="cf-pixel-provider-grid">
								<div class="cf-pixel-card is-local is-open">
									<div class="cf-pixel-card-head" data-pixel-provider-toggle role="button" tabindex="0"><div><strong>CheckFlow Local</strong><span>Own first-party event log inside WordPress.</span></div><em><?php echo esc_html( $pixel_provider_status['local']['label'] ); ?></em></div>
									<label class="cf-field-switch"><span>Log local events</span><input type="checkbox" data-pixel-setting="local_enabled" <?php checked( ! empty( $pixel_settings['local_enabled'] ) ); ?> /></label>
									<p>Stores PageView, ViewContent, AddToCart, InitiateCheckout, and Purchase with event IDs for future CAPI dedupe.</p>
									<div class="cf-pixel-readiness"><?php foreach ( $pixel_provider_status['local']['items'] as $item => $ready ) : ?><span class="<?php echo $ready ? 'is-ready' : 'is-missing'; ?>"><?php echo esc_html( $item ); ?></span><?php endforeach; ?></div>
								</div>
								<div class="cf-pixel-card">
									<div class="cf-pixel-card-head" data-pixel-provider-toggle role="button" tabindex="0"><div><strong>Meta Pixel</strong><span>Browser pixel foundation. Real CAPI test later.</span></div><em><?php echo esc_html( $pixel_provider_status['meta']['label'] ); ?></em></div>
									<div class="cf-pixel-card-fields">
										<label class="cf-field-switch"><span>Enable Meta Pixel</span><input type="checkbox" data-pixel-setting="meta_enabled" <?php checked( ! empty( $pixel_settings['meta_enabled'] ) ); ?> /></label>
										<label><span>Pixel ID</span><input type="text" inputmode="numeric" data-pixel-setting="meta_pixel_id" value="<?php echo esc_attr( $pixel_settings['meta_pixel_id'] ); ?>" placeholder="123456789012345" /></label>
										<label class="cf-field-switch"><span>Debug console log</span><input type="checkbox" data-pixel-setting="debug_mode" <?php checked( ! empty( $pixel_settings['debug_mode'] ) ); ?> /></label>
									</div>
									<div class="cf-pixel-readiness"><?php foreach ( $pixel_provider_status['meta']['items'] as $item => $ready ) : ?><span class="<?php echo $ready ? 'is-ready' : 'is-missing'; ?>"><?php echo esc_html( $item ); ?></span><?php endforeach; ?></div>
									<p class="cf-pixel-help">Paste the numeric Meta Pixel ID from Events Manager. CAPI/server dedupe will use CheckFlow event IDs in a later pass.</p>
								</div>
								<div class="cf-pixel-card">
									<div class="cf-pixel-card-head" data-pixel-provider-toggle role="button" tabindex="0"><div><strong>Google Ads / GA4</strong><span>Save IDs now; event firing comes in final real test pass.</span></div><em><?php echo esc_html( $pixel_provider_status['google']['label'] ); ?></em></div>
									<div class="cf-pixel-card-fields">
										<label class="cf-field-switch"><span>Enable Google</span><input type="checkbox" data-pixel-setting="google_enabled" <?php checked( ! empty( $pixel_settings['google_enabled'] ) ); ?> /></label>
										<label><span>Measurement / Conversion ID</span><input type="text" data-pixel-setting="google_measurement_id" value="<?php echo esc_attr( $pixel_settings['google_measurement_id'] ); ?>" placeholder="G-XXXX or AW-XXXX" /></label>
										<label><span>Conversion label</span><input type="text" data-pixel-setting="google_conversion_label" value="<?php echo esc_attr( $pixel_settings['google_conversion_label'] ); ?>" placeholder="Purchase label" /></label>
									</div>
									<div class="cf-pixel-readiness"><?php foreach ( $pixel_provider_status['google']['items'] as $item => $ready ) : ?><span class="<?php echo $ready ? 'is-ready' : 'is-missing'; ?>"><?php echo esc_html( $item ); ?></span><?php endforeach; ?></div>
									<p class="cf-pixel-help">Use GA4 Measurement ID or Google Ads Conversion ID. The label is required for purchase conversion mapping.</p>
								</div>
								<div class="cf-pixel-card">
									<div class="cf-pixel-card-head" data-pixel-provider-toggle role="button" tabindex="0"><div><strong>TikTok Events</strong><span>Pixel/API placeholders ready. Live firing later.</span></div><em><?php echo esc_html( $pixel_provider_status['tiktok']['label'] ); ?></em></div>
									<div class="cf-pixel-card-fields">
										<label class="cf-field-switch"><span>Enable TikTok</span><input type="checkbox" data-pixel-setting="tiktok_enabled" <?php checked( ! empty( $pixel_settings['tiktok_enabled'] ) ); ?> /></label>
										<label><span>Pixel ID</span><input type="text" data-pixel-setting="tiktok_pixel_id" value="<?php echo esc_attr( $pixel_settings['tiktok_pixel_id'] ); ?>" placeholder="TikTok Pixel ID" /></label>
										<label><span>API token</span><input type="password" data-pixel-setting="tiktok_api_token" value="<?php echo esc_attr( $pixel_settings['tiktok_api_token'] ); ?>" placeholder="Saved locally for future API pass" /></label>
									</div>
									<div class="cf-pixel-readiness"><?php foreach ( $pixel_provider_status['tiktok']['items'] as $item => $ready ) : ?><span class="<?php echo $ready ? 'is-ready' : 'is-missing'; ?>"><?php echo esc_html( $item ); ?></span><?php endforeach; ?></div>
									<p class="cf-pixel-help">Pixel ID covers browser setup. API token is saved for the future Events API pass.</p>
								</div>
							</div>
							<div class="cf-pixel-actions">
								<div class="cf-pixel-save-row" data-pixel-save-status>Local browser events are ready. External real validation will run after all features are complete.</div>
								<button type="button" class="btn-p" data-save-pixel-settings>Save tracking settings</button>
							</div>
							<div class="cf-pixel-visuals">
								<div class="cf-pixel-visuals-head">
									<div><strong>Tracking insights</strong><span>Quick conversion signal from local events.</span></div>
									<button type="button" data-pixel-insights-toggle>Show chart</button>
								</div>
								<div class="cf-pixel-kpis">
									<div><span>Total events</span><strong><?php echo esc_html( (string) $pixel_analytics['total'] ); ?></strong></div>
									<div><span>Add to cart</span><strong><?php echo esc_html( (string) $pixel_analytics['counts']['AddToCart'] ); ?></strong></div>
									<div><span>Checkout</span><strong><?php echo esc_html( (string) $pixel_analytics['counts']['InitiateCheckout'] ); ?></strong></div>
									<div><span>Purchase</span><strong><?php echo esc_html( (string) $pixel_analytics['counts']['Purchase'] ); ?></strong></div>
								</div>
								<div class="cf-pixel-chart" aria-label="Local event breakdown">
									<?php foreach ( $pixel_analytics['counts'] as $event_name => $total ) : ?>
										<?php $width = max( 4, round( ( absint( $total ) / max( 1, absint( $pixel_analytics['max'] ) ) ) * 100 ) ); ?>
										<?php $percent = $pixel_analytics['total'] ? round( ( absint( $total ) / absint( $pixel_analytics['total'] ) ) * 100 ) : 0; ?>
										<button type="button" class="cf-pixel-chart-row" data-pixel-filter="<?php echo esc_attr( $event_name ); ?>" data-chart-label="<?php echo esc_attr( $event_name . ': ' . $total . ' events, ' . $percent . '%' ); ?>">
											<span><?php echo esc_html( $event_name ); ?><small><?php echo esc_html( (string) $percent ); ?>%</small></span>
											<i><b style="width: <?php echo esc_attr( (string) $width ); ?>%"></b></i>
											<em><?php echo esc_html( (string) $total ); ?></em>
										</button>
									<?php endforeach; ?>
								</div>
								<div class="cf-pixel-timeline">
									<?php if ( empty( $pixel_analytics['recent'] ) ) : ?>
										<span>No activity yet</span>
									<?php else : ?>
										<?php foreach ( $pixel_analytics['recent'] as $activity ) : ?>
											<button type="button" data-pixel-filter="<?php echo esc_attr( $activity['event_name'] ); ?>"><strong><?php echo esc_html( substr( $activity['event_name'], 0, 1 ) ); ?></strong><span><?php echo esc_html( $activity['time'] ); ?></span></button>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
								<div class="cf-pixel-chart-status" data-pixel-chart-status>Showing all recent local events.</div>
							</div>
							<div class="cf-pixel-advanced">
								<button type="button" class="cf-pixel-advanced-toggle" data-pixel-advanced-toggle>
									<span>Advanced tracking settings</span>
									<em>Event controls, retention, export, test</em>
								</button>
								<div class="cf-pixel-advanced-body">
									<div class="cf-pixel-event-controls">
										<?php foreach ( $admin_instance->get_pixel_event_names() as $event_name ) : ?>
											<?php $event_key = 'event_' . sanitize_key( $event_name ); ?>
											<label class="cf-field-switch">
												<span><?php echo esc_html( $event_name ); ?></span>
												<input type="checkbox" data-pixel-setting="<?php echo esc_attr( $event_key ); ?>" <?php checked( ! empty( $pixel_settings[ $event_key ] ) ); ?> />
											</label>
										<?php endforeach; ?>
									</div>
									<div class="cf-pixel-advanced-grid">
										<label><span>Retention days</span><input type="number" min="1" max="365" data-pixel-setting="retention_days" value="<?php echo esc_attr( (string) $pixel_settings['retention_days'] ); ?>" /></label>
										<label><span>Test event</span><select data-pixel-test-event><?php foreach ( $admin_instance->get_pixel_event_names() as $event_name ) : ?><option value="<?php echo esc_attr( $event_name ); ?>"><?php echo esc_html( $event_name ); ?></option><?php endforeach; ?></select></label>
										<button type="button" class="cf-btn-ghost" data-test-pixel-event>Send test event</button>
										<button type="button" class="cf-btn-ghost" data-export-pixel-log>Export CSV</button>
										<button type="button" class="cf-btn-ghost" data-clear-pixel-log="expired">Clear expired</button>
										<button type="button" class="cf-btn-danger" data-clear-pixel-log="all">Clear all logs</button>
									</div>
									<div class="cf-pixel-advanced-status" data-pixel-advanced-status>Changes apply after saving tracking settings.</div>
								</div>
							</div>
							<div class="cf-pixel-settings">
								<label class="cf-field-switch"><span>Enable Meta Pixel</span><input type="checkbox" data-pixel-setting="meta_enabled" <?php checked( ! empty( $pixel_settings['meta_enabled'] ) ); ?> /></label>
								<label><span>Meta Pixel ID</span><input type="text" inputmode="numeric" data-pixel-setting="meta_pixel_id" value="<?php echo esc_attr( $pixel_settings['meta_pixel_id'] ); ?>" placeholder="123456789012345" /></label>
								<label class="cf-field-switch"><span>Debug console log</span><input type="checkbox" data-pixel-setting="debug_mode" <?php checked( ! empty( $pixel_settings['debug_mode'] ) ); ?> /></label>
								<button type="button" class="btn-p" data-save-pixel-settings>Save pixel settings</button>
							</div>
							<div class="cf-pixel-save-row" data-pixel-save-status>Meta browser events: PageView, ViewContent, AddToCart, InitiateCheckout, Purchase.</div>
							<div class="pxl">
								<div class="pxi"><span style="font-size:18px">📘</span><div><div class="pxn">Meta CAPI</div><div class="pxe">Purchase, InitiateCheckout, AddPaymentInfo</div></div><div class="pxs ok"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.active' ) ); ?></div></div>
								<div class="pxi"><span style="font-size:18px">🎯</span><div><div class="pxn">Google Enhanced</div><div class="pxe">Conversion ID + Label pending real settings</div></div><div class="pxs ok"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.active' ) ); ?></div></div>
								<div class="pxi"><span style="font-size:18px">🎵</span><div><div class="pxn">TikTok Events API</div><div class="pxe"><?php echo esc_html( checkflow_str( 'pixel.tiktok_no_key' ) ); ?></div></div><div class="pxs warn"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.set_up' ) ); ?></div></div>
							</div>
						</div>
					</div>
					<div class="panel">
						<div class="ph"><div class="pt">CheckFlow Event Log</div><div class="pa"><button type="button" class="cf-pixel-filter-clear" data-pixel-filter="all">All events</button></div></div>
						<div class="pb">
							<?php if ( empty( $pixel_events ) ) : ?>
								<div class="cf-pixel-event-empty"><strong>No local events yet</strong><span>Visit product, shop, checkout, or order received pages to populate this log.</span></div>
							<?php else : ?>
								<div class="cf-module-list cf-pixel-event-list">
									<?php foreach ( $pixel_events as $event ) : ?>
										<button type="button" data-pixel-event-row data-event-name="<?php echo esc_attr( $event['event_name'] ); ?>" data-event-id="<?php echo esc_attr( $event['event_id'] ); ?>" data-event-summary="<?php echo esc_attr( $event['summary'] ); ?>" data-event-url="<?php echo esc_url( $event['page_url'] ); ?>" data-event-context="<?php echo esc_attr( $event['context'] ); ?>">
											<strong><?php echo esc_html( $event['event_name'] ); ?></strong>
											<span><?php echo esc_html( $event['summary'] . ' - ' . $event['created_at'] ); ?></span>
										</button>
									<?php endforeach; ?>
								</div>
								<div class="cf-pixel-filter-empty" data-pixel-filter-empty hidden>No events found for this filter yet.</div>
								<div class="cf-pixel-detail" data-pixel-detail>
									<strong>Select an event</strong>
									<span>Click any event row or chart bar to inspect context.</span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'courier' ) ); ?>" data-pane="courier">
				<div class="g2">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'courier.summary_title' ) ); ?></div><div class="pa">Sandbox ready</div></div>
						<div class="pb">
							<div class="cf-courier-providers">
								<?php foreach ( $courier_providers as $provider_key => $provider ) : ?>
									<?php
									$enabled_key = $provider_key . '_enabled';
									$mode_key    = $provider_key . '_mode';
									$token_key   = $provider_key . '_token';
									$is_default  = $provider_key === $courier_settings['default_provider'];
									?>
									<div class="cf-courier-provider<?php echo $is_default ? ' is-default' : ''; ?>" data-courier-provider-card="<?php echo esc_attr( $provider_key ); ?>">
										<div class="cf-courier-provider-head">
											<div><strong><?php echo esc_html( $provider['label'] ); ?></strong><span><?php echo $is_default ? 'Default provider' : 'Courier provider'; ?></span></div>
											<label><input type="radio" name="cf_courier_default" value="<?php echo esc_attr( $provider_key ); ?>" data-courier-default <?php checked( $is_default ); ?> /> Default</label>
										</div>
										<div class="cf-courier-provider-controls">
											<label><span>Enabled</span><input type="checkbox" data-courier-setting="<?php echo esc_attr( $enabled_key ); ?>" <?php checked( ! empty( $courier_settings[ $enabled_key ] ) ); ?> /></label>
											<label><span>Mode</span><select data-courier-setting="<?php echo esc_attr( $mode_key ); ?>"><option value="sandbox" <?php selected( $courier_settings[ $mode_key ], 'sandbox' ); ?>>Sandbox</option><option value="live" <?php selected( $courier_settings[ $mode_key ], 'live' ); ?>>Live</option></select></label>
										</div>
										<?php if ( 'pathao' !== $provider_key ) : ?>
											<label class="cf-courier-token"><span>API token / key</span><input type="password" data-courier-setting="<?php echo esc_attr( $token_key ); ?>" value="<?php echo esc_attr( $courier_settings[ $token_key ] ); ?>" placeholder="Future API token" /></label>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="cf-pathao-settings">
								<div class="cf-pathao-settings-head"><div><strong>Pathao API setup</strong><span>Review payload first, then book live from an order drawer.</span></div><span>OAuth + live booking</span></div>
								<div class="cf-pathao-foundation">
									<label><span>Base URL</span><input type="text" data-courier-setting="pathao_base_url" value="<?php echo esc_attr( $courier_settings['pathao_base_url'] ); ?>" placeholder="Auto by mode if blank" /></label>
									<label><span>Client ID</span><input type="text" data-courier-setting="pathao_client_id" value="<?php echo esc_attr( $courier_settings['pathao_client_id'] ); ?>" /></label>
									<label><span>Client secret</span><input type="password" data-courier-setting="pathao_client_secret" value="<?php echo esc_attr( $courier_settings['pathao_client_secret'] ); ?>" /></label>
									<label><span>Username / email</span><input type="text" data-courier-setting="pathao_username" value="<?php echo esc_attr( $courier_settings['pathao_username'] ); ?>" /></label>
									<label><span>Password</span><input type="password" data-courier-setting="pathao_password" value="<?php echo esc_attr( $courier_settings['pathao_password'] ); ?>" /></label>
									<label><span>Store ID</span><input type="number" min="1" data-courier-setting="pathao_store_id" value="<?php echo esc_attr( $courier_settings['pathao_store_id'] ); ?>" /></label>
									<label><span>Delivery type</span><input type="number" min="1" data-courier-setting="pathao_delivery_type" value="<?php echo esc_attr( $courier_settings['pathao_delivery_type'] ); ?>" /></label>
									<label><span>Item type</span><input type="number" min="1" data-courier-setting="pathao_item_type" value="<?php echo esc_attr( $courier_settings['pathao_item_type'] ); ?>" /></label>
									<label><span>Default weight</span><input type="number" min="0.1" step="0.1" data-courier-setting="pathao_item_weight" value="<?php echo esc_attr( $courier_settings['pathao_item_weight'] ); ?>" /></label>
								</div>
							</div>
							<div class="cf-courier-save-row">
								<span data-courier-save-status>Provider setup is stored locally. Live Pathao booking runs only from the order drawer button.</span>
								<button type="button" class="btn-p" data-save-courier-settings>Save courier settings</button>
							</div>
						</div>
					</div>
					<div class="panel">
						<div class="ph"><div class="pt">Booking workflow</div></div>
						<div class="pb">
							<div class="cf-action-row"><span>Order drawer prepare action</span><strong>Creates courier draft meta</strong></div>
							<div class="cf-action-row"><span>Auto book after processing</span><div class="tgl" role="switch" aria-checked="false" tabindex="0"></div></div>
							<div class="cf-action-row"><span>COD reconciliation</span><div class="tgl" role="switch" aria-checked="false" tabindex="0"></div></div>
							<div class="cf-action-row"><span>Live API booking</span><strong>Pathao enabled</strong></div>
						</div>
					</div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'field_editor' ) ); ?>" data-pane="field_editor">
				<div class="panel">
					<div class="ph"><div class="pt">Checkout field editor</div><div class="pa">WooCommerce safe</div></div>
					<div class="pb">
						<div class="cf-field-toolbar">
							<p>Manage checkout fields with friendly controls. Drag the handle or use arrows to reorder, then save to apply changes on checkout.</p>
							<div class="cf-field-actions">
								<input type="search" class="cf-field-search" placeholder="Search fields" aria-label="Search checkout fields" />
								<button type="button" class="cf-btn-ghost cf-reset-active-fields">Reset tab</button>
								<span class="cf-field-save-state" aria-live="polite">Saved</span>
								<button type="button" class="cf-btn-ghost cf-export-fields">Export setup</button>
								<button type="button" class="cf-btn-ghost cf-import-fields">Import setup</button>
								<input type="file" class="cf-import-fields-file" accept="application/json,.json" hidden />
								<button type="button" class="btn-p cf-save-fields">Save fields</button>
								<button type="button" class="cf-btn-ghost cf-reset-fields">Reset</button>
							</div>
						</div>
						<div class="cf-field-presets-head">
							<div>
								<strong>Field templates</strong>
								<span>Apply a smart checkout setup, review it, then save when it looks right.</span>
							</div>
							<div class="cf-field-preset-status" data-field-preset-status aria-live="polite">No template selected</div>
						</div>
						<div class="cf-field-presets" aria-label="Checkout field presets">
							<div class="cf-preset-card cf-preset-card--cod">
								<div class="cf-preset-icon">COD</div>
								<div>
									<strong>Bangladesh COD</strong>
									<span>Phone-first local delivery flow with address essentials.</span>
								</div>
								<button type="button" class="cf-btn-ghost" data-field-preset="bangladesh_cod">Apply</button>
							</div>
							<div class="cf-preset-card cf-preset-card--minimal">
								<div class="cf-preset-icon">MIN</div>
								<div>
									<strong>Minimal Checkout</strong>
									<span>Lean checkout with only high-signal customer fields.</span>
								</div>
								<button type="button" class="cf-btn-ghost" data-field-preset="minimal">Apply</button>
							</div>
							<div class="cf-preset-card cf-preset-card--digital">
								<div class="cf-preset-icon">DIG</div>
								<div>
									<strong>Digital Product</strong>
									<span>Email-led checkout for files, courses, and services.</span>
								</div>
								<button type="button" class="cf-btn-ghost" data-field-preset="digital">Apply</button>
							</div>
							<div class="cf-preset-card cf-preset-card--b2b">
								<div class="cf-preset-icon">B2B</div>
								<div>
									<strong>Business / B2B</strong>
									<span>Company, billing notes, and procurement-friendly fields.</span>
								</div>
								<button type="button" class="cf-btn-ghost" data-field-preset="b2b">Apply</button>
							</div>
						</div>
						<div class="cf-field-create">
							<div>
								<strong>Add custom field</strong>
								<span>Text, select, checkbox, date, or textarea</span>
							</div>
							<input type="text" class="cf-new-field-label" placeholder="Field label" />
							<select class="cf-new-field-type" aria-label="Field type">
								<option value="text">Text</option>
								<option value="textarea">Textarea</option>
								<option value="select">Select</option>
								<option value="checkbox">Checkbox</option>
								<option value="date">Date</option>
							</select>
							<select class="cf-new-field-group" aria-label="Field group">
								<option value="billing">Billing</option>
								<option value="shipping">Shipping</option>
								<option value="order">Order notes</option>
							</select>
							<input type="text" class="cf-new-field-options" placeholder="Select options, comma separated" />
							<button type="button" class="cf-btn-ghost cf-add-custom-field">Add field</button>
						</div>
						<div class="cf-field-tabs" role="tablist" aria-label="Checkout field groups">
							<button type="button" class="is-active" data-field-group-tab="billing">Billing</button>
							<button type="button" data-field-group-tab="shipping">Shipping</button>
							<button type="button" data-field-group-tab="order">Order notes</button>
						</div>
						<div class="cf-field-panels">
							<?php foreach ( $field_groups as $group_name => $group_rows ) : ?>
								<section class="cf-field-panel<?php echo 'billing' === $group_name ? ' is-active' : ''; ?>" data-field-group-panel="<?php echo esc_attr( $group_name ); ?>">
									<div class="cf-field-panel-head">
										<div>
											<strong><?php echo esc_html( ucfirst( $group_name ) ); ?> fields</strong>
											<span><?php echo esc_html( count( $group_rows ) . ' editable checkout fields' ); ?></span>
										</div>
									</div>
									<div class="cf-field-list" data-field-list="<?php echo esc_attr( $group_name ); ?>">
										<?php foreach ( $group_rows as $field ) : ?>
										<?php
										$key       = isset( $field['key'] ) ? (string) $field['key'] : '';
										$locked    = ! empty( $field['protected'] );
										$enabled   = ! empty( $field['enabled'] ) || $locked;
										$required  = ! empty( $field['required'] );
										$group     = isset( $field['group'] ) ? (string) $field['group'] : '';
										$label     = isset( $field['label'] ) ? (string) $field['label'] : $key;
										$type      = isset( $field['type'] ) ? (string) $field['type'] : 'text';
										$priority  = isset( $field['priority'] ) ? absint( $field['priority'] ) : 10;
										$placeholder = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
										$help      = isset( $field['help'] ) ? (string) $field['help'] : '';
										$width     = isset( $field['width'] ) ? (string) $field['width'] : 'default';
										$default_value = isset( $field['default_value'] ) ? (string) $field['default_value'] : '';
										$validation = isset( $field['validation'] ) ? (string) $field['validation'] : 'none';
										$min       = isset( $field['min'] ) ? (string) $field['min'] : '';
										$max       = isset( $field['max'] ) ? (string) $field['max'] : '';
										$min_length = isset( $field['min_length'] ) ? absint( $field['min_length'] ) : 0;
										$max_length = isset( $field['max_length'] ) ? absint( $field['max_length'] ) : 0;
										$required_message = isset( $field['required_message'] ) ? (string) $field['required_message'] : '';
										$validation_message = isset( $field['validation_message'] ) ? (string) $field['validation_message'] : '';
										$condition = isset( $field['condition'] ) && is_array( $field['condition'] ) ? $field['condition'] : array();
										$condition_enabled = ! empty( $condition['enabled'] );
										$condition_action = isset( $condition['action'] ) ? (string) $condition['action'] : 'show';
										$condition_source = isset( $condition['source'] ) ? (string) $condition['source'] : 'payment_method';
										$condition_operator = isset( $condition['operator'] ) ? (string) $condition['operator'] : 'equals';
										$condition_value = isset( $condition['value'] ) ? (string) $condition['value'] : '';
										$condition_field = isset( $condition['field'] ) ? (string) $condition['field'] : '';
										$default   = CheckFlow_Field_Editor::instance()->get_default_field_for_admin( $key );
										?>
											<div class="cf-field-row" data-field-key="<?php echo esc_attr( $key ); ?>" data-field-group="<?php echo esc_attr( $group ); ?>" data-field-type="<?php echo esc_attr( $type ); ?>" data-field-custom="<?php echo ! empty( $field['custom'] ) ? '1' : '0'; ?>" data-field-options="<?php echo esc_attr( wp_json_encode( isset( $field['options'] ) && is_array( $field['options'] ) ? array_values( $field['options'] ) : array() ) ); ?>" data-protected="<?php echo $locked ? '1' : '0'; ?>" data-default-label="<?php echo esc_attr( isset( $default['label'] ) ? (string) $default['label'] : $label ); ?>" data-default-priority="<?php echo esc_attr( isset( $default['priority'] ) ? (string) absint( $default['priority'] ) : (string) $priority ); ?>" data-default-enabled="<?php echo ! empty( $default['enabled'] ) || $locked ? '1' : '0'; ?>" data-default-required="<?php echo ! empty( $default['required'] ) ? '1' : '0'; ?>" data-default-placeholder="<?php echo esc_attr( isset( $default['placeholder'] ) ? (string) $default['placeholder'] : '' ); ?>" data-default-help="<?php echo esc_attr( isset( $default['help'] ) ? (string) $default['help'] : '' ); ?>" data-default-width="<?php echo esc_attr( isset( $default['width'] ) ? (string) $default['width'] : 'default' ); ?>" data-default-value="<?php echo esc_attr( isset( $default['default_value'] ) ? (string) $default['default_value'] : '' ); ?>" data-default-validation="<?php echo esc_attr( isset( $default['validation'] ) ? (string) $default['validation'] : 'none' ); ?>" data-default-min="<?php echo esc_attr( isset( $default['min'] ) ? (string) $default['min'] : '' ); ?>" data-default-max="<?php echo esc_attr( isset( $default['max'] ) ? (string) $default['max'] : '' ); ?>" data-default-min-length="<?php echo esc_attr( isset( $default['min_length'] ) ? (string) absint( $default['min_length'] ) : '0' ); ?>" data-default-max-length="<?php echo esc_attr( isset( $default['max_length'] ) ? (string) absint( $default['max_length'] ) : '0' ); ?>" data-default-required-message="<?php echo esc_attr( isset( $default['required_message'] ) ? (string) $default['required_message'] : '' ); ?>" data-default-validation-message="<?php echo esc_attr( isset( $default['validation_message'] ) ? (string) $default['validation_message'] : '' ); ?>" data-default-condition-enabled="0" data-default-condition-action="show" data-default-condition-source="payment_method" data-default-condition-operator="equals" data-default-condition-value="" data-default-condition-field="">
												<div class="cf-field-move" aria-label="Reorder field">
													<button type="button" class="cf-field-drag" data-field-drag aria-label="Drag to reorder">☰</button>
													<button type="button" data-field-move="up" aria-label="Move up">↑</button>
													<button type="button" data-field-move="down" aria-label="Move down">↓</button>
												</div>
												<div class="cf-field-main">
													<div class="cf-field-title">
														<strong><?php echo esc_html( $label ); ?></strong>
														<span><?php echo esc_html( $key ); ?></span>
														<div class="cf-field-badges">
															<em><?php echo esc_html( ucfirst( $type ) ); ?></em>
															<?php echo $locked ? '<em>Locked</em>' : ''; ?>
															<?php echo ! empty( $field['custom'] ) ? '<em>Custom</em>' : ''; ?>
														</div>
													</div>
													<label>
														<span>Checkout label</span>
														<input type="text" class="cf-field-label" value="<?php echo esc_attr( $label ); ?>" />
													</label>
												</div>
												<div class="cf-field-controls">
													<label class="cf-field-mini">
														<span>Order</span>
														<input type="number" class="cf-field-priority" min="1" max="999" value="<?php echo esc_attr( (string) $priority ); ?>" />
													</label>
													<label class="cf-field-switch">
														<input type="checkbox" class="cf-field-enabled" <?php checked( $enabled ); ?> <?php disabled( $locked ); ?> />
														<span>Show</span>
													</label>
													<label class="cf-field-switch">
														<input type="checkbox" class="cf-field-required" <?php checked( $required ); ?> />
														<span>Required</span>
													</label>
													<button type="button" class="cf-field-settings" data-field-settings aria-expanded="false">Settings</button>
													<?php if ( ! empty( $field['custom'] ) ) : ?>
														<button type="button" class="cf-field-delete" data-field-delete aria-label="Delete custom field">Delete</button>
													<?php endif; ?>
												</div>
												<div class="cf-field-advanced" hidden>
													<label>
														<span>Placeholder</span>
														<input type="text" class="cf-field-placeholder" value="<?php echo esc_attr( $placeholder ); ?>" placeholder="Shown inside the input" />
													</label>
													<label>
														<span>Help text</span>
														<input type="text" class="cf-field-help" value="<?php echo esc_attr( $help ); ?>" placeholder="Small note below the field" />
													</label>
													<label>
														<span>Width</span>
														<select class="cf-field-width">
															<option value="default" <?php selected( $width, 'default' ); ?>>Default</option>
															<option value="full" <?php selected( $width, 'full' ); ?>>Full width</option>
															<option value="first" <?php selected( $width, 'first' ); ?>>Half - left</option>
															<option value="last" <?php selected( $width, 'last' ); ?>>Half - right</option>
														</select>
													</label>
													<label>
														<span>Default value</span>
														<input type="text" class="cf-field-default-value" value="<?php echo esc_attr( $default_value ); ?>" placeholder="Optional prefilled value" />
													</label>
													<label>
														<span>Validation</span>
														<select class="cf-field-validation">
															<option value="none" <?php selected( $validation, 'none' ); ?>>None</option>
															<option value="email" <?php selected( $validation, 'email' ); ?>>Email</option>
															<option value="phone" <?php selected( $validation, 'phone' ); ?>>Phone</option>
															<option value="number" <?php selected( $validation, 'number' ); ?>>Number</option>
															<option value="text" <?php selected( $validation, 'text' ); ?>>Letters only</option>
														</select>
													</label>
													<label>
														<span>Number min/max</span>
														<div class="cf-field-pair">
															<input type="number" class="cf-field-min" value="<?php echo esc_attr( $min ); ?>" placeholder="Min" />
															<input type="number" class="cf-field-max" value="<?php echo esc_attr( $max ); ?>" placeholder="Max" />
														</div>
													</label>
													<label>
														<span>Length min/max</span>
														<div class="cf-field-pair">
															<input type="number" class="cf-field-min-length" min="0" value="<?php echo esc_attr( (string) $min_length ); ?>" placeholder="Min" />
															<input type="number" class="cf-field-max-length" min="0" value="<?php echo esc_attr( (string) $max_length ); ?>" placeholder="Max" />
														</div>
													</label>
													<label>
														<span>Required message</span>
														<input type="text" class="cf-field-required-message" value="<?php echo esc_attr( $required_message ); ?>" placeholder="Custom required error" />
													</label>
													<label>
														<span>Validation message</span>
														<input type="text" class="cf-field-validation-message" value="<?php echo esc_attr( $validation_message ); ?>" placeholder="Custom invalid error" />
													</label>
													<div class="cf-field-condition">
														<label class="cf-field-switch">
															<input type="checkbox" class="cf-field-condition-enabled" <?php checked( $condition_enabled ); ?> />
															<span>Conditional</span>
														</label>
														<label>
															<span>Action</span>
															<select class="cf-field-condition-action">
																<option value="show" <?php selected( $condition_action, 'show' ); ?>>Show when</option>
																<option value="hide" <?php selected( $condition_action, 'hide' ); ?>>Hide when</option>
															</select>
														</label>
														<label>
															<span>Source</span>
															<select class="cf-field-condition-source">
																<option value="payment_method" <?php selected( $condition_source, 'payment_method' ); ?>>Payment method</option>
																<option value="billing_country" <?php selected( $condition_source, 'billing_country' ); ?>>Billing country</option>
																<option value="shipping_country" <?php selected( $condition_source, 'shipping_country' ); ?>>Shipping country</option>
																<option value="field" <?php selected( $condition_source, 'field' ); ?>>Another field</option>
																<option value="cart_total" <?php selected( $condition_source, 'cart_total' ); ?>>Cart total</option>
																<option value="product_id" <?php selected( $condition_source, 'product_id' ); ?>>Product ID in cart</option>
																<option value="category_id" <?php selected( $condition_source, 'category_id' ); ?>>Category ID in cart</option>
															</select>
														</label>
														<label>
															<span>Operator</span>
															<select class="cf-field-condition-operator">
																<option value="equals" <?php selected( $condition_operator, 'equals' ); ?>>Equals</option>
																<option value="not_equals" <?php selected( $condition_operator, 'not_equals' ); ?>>Not equals</option>
																<option value="contains" <?php selected( $condition_operator, 'contains' ); ?>>Contains</option>
																<option value="greater_equal" <?php selected( $condition_operator, 'greater_equal' ); ?>>Greater or equal</option>
																<option value="less_equal" <?php selected( $condition_operator, 'less_equal' ); ?>>Less or equal</option>
																<option value="checked" <?php selected( $condition_operator, 'checked' ); ?>>Checked</option>
																<option value="not_checked" <?php selected( $condition_operator, 'not_checked' ); ?>>Not checked</option>
															</select>
														</label>
														<label>
															<span>Field key</span>
															<input type="text" class="cf-field-condition-field" value="<?php echo esc_attr( $condition_field ); ?>" placeholder="For another field" />
														</label>
														<label>
															<span>Value</span>
															<input type="text" class="cf-field-condition-value" value="<?php echo esc_attr( $condition_value ); ?>" placeholder="cod, BD, 100, product/category ID" />
														</label>
													</div>
													<div class="cf-field-preview" aria-hidden="true">
														<span>Preview</span>
														<strong><?php echo esc_html( $label ); ?></strong>
														<em><?php echo esc_html( $placeholder ? $placeholder : 'Customer input' ); ?></em>
														<small><?php echo esc_html( $help ? $help : 'No help text' ); ?></small>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								</section>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'templates' ) ); ?>" data-pane="templates">
				<div class="panel cf-template-panel">
					<div class="ph">
						<div>
							<div class="pt">Checkout templates</div>
							<div class="cf-panel-sub">Choose a live checkout look. WooCommerce payment and order flow stay native.</div>
						</div>
						<div class="cf-template-current" data-template-current>
							<?php echo esc_html( isset( $checkout_templates[ $active_checkout_template ] ) ? $checkout_templates[ $active_checkout_template ]['name'] : 'Default One Page' ); ?>
						</div>
					</div>
					<div class="pb">
						<div class="cf-template-grid">
							<?php foreach ( $checkout_templates as $key => $tpl ) : ?>
								<?php $is_active_template = $key === $active_checkout_template; ?>
								<div class="cf-template-card<?php echo $is_active_template ? ' is-active' : ''; ?>" data-checkout-template="<?php echo esc_attr( $key ); ?>" data-template-name="<?php echo esc_attr( $tpl['name'] ); ?>" data-template-description="<?php echo esc_attr( $tpl['description'] ); ?>" data-template-tag="<?php echo esc_attr( $tpl['tag'] ); ?>" data-template-field-preset="<?php echo esc_attr( isset( $tpl['field_preset'] ) ? $tpl['field_preset'] : '' ); ?>" data-template-field-preset-label="<?php echo esc_attr( isset( $tpl['field_preset_label'] ) ? $tpl['field_preset_label'] : '' ); ?>">
									<div class="cf-template-thumb cf-template-thumb--<?php echo esc_attr( $key ); ?>" aria-hidden="true">
										<span></span><span></span><span></span>
									</div>
									<div class="cf-template-body">
										<div class="cf-template-name"><?php echo esc_html( $tpl['name'] ); ?></div>
										<div class="cf-template-desc"><?php echo esc_html( $tpl['description'] ); ?></div>
									</div>
									<div class="cf-template-foot">
										<span><?php echo esc_html( $tpl['tag'] ); ?></span>
										<button type="button" class="<?php echo $is_active_template ? 'cf-btn-ghost' : 'btn-p'; ?>" data-save-checkout-template="<?php echo esc_attr( $key ); ?>">
											<?php echo $is_active_template ? esc_html__( 'Active', 'checkflow' ) : esc_html__( 'Use template', 'checkflow' ); ?>
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="cf-template-compare" data-template-compare>
						<div class="cf-template-compare-card">
							<span>Current live template</span>
							<strong data-template-compare-current><?php echo esc_html( isset( $checkout_templates[ $active_checkout_template ] ) ? $checkout_templates[ $active_checkout_template ]['name'] : 'Default One Page' ); ?></strong>
							<small data-template-compare-current-copy><?php echo esc_html( isset( $checkout_templates[ $active_checkout_template ] ) ? $checkout_templates[ $active_checkout_template ]['description'] : '' ); ?></small>
						</div>
						<div class="cf-template-compare-card is-selected">
							<span>Preview selected</span>
							<strong data-template-compare-selected><?php echo esc_html( isset( $checkout_templates[ $active_checkout_template ] ) ? $checkout_templates[ $active_checkout_template ]['name'] : 'Default One Page' ); ?></strong>
							<small data-template-compare-selected-copy>Hover a template to compare its layout, field pairing, and visual focus.</small>
						</div>
						<div class="cf-template-compare-list">
							<strong>What changes</strong>
							<ul data-template-compare-points>
								<li>Visual theme and checkout spacing</li>
								<li>Order summary emphasis and trust modules</li>
								<li>Suggested matching field preset</li>
							</ul>
						</div>
					</div>
					<div class="cf-template-pairing" data-template-pairing hidden>
						<div>
							<strong data-template-pairing-title>Matching field preset available</strong>
							<span data-template-pairing-copy>Apply the paired field preset to align the checkout form with this template.</span>
						</div>
						<div class="cf-template-pairing-actions">
							<label class="cf-template-auto-pair">
								<input type="checkbox" data-template-auto-pair />
								<span>Auto-apply after template select</span>
							</label>
							<button type="button" class="btn-p" data-apply-template-field-preset>Apply matching fields</button>
						</div>
					</div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'order_bump' ) ); ?>" data-pane="order_bump">
				<div class="cf-bump-layout">
					<div class="panel">
						<div class="ph"><div class="pt">Order Bump offer</div><div class="pa">Rules ready</div></div>
						<div class="pb">
							<div class="cf-bump-status-strip">
								<div><span>Current product</span><strong data-bump-product-label><?php echo esc_html( $order_bump_product_label ); ?></strong></div>
								<em data-bump-product-status><?php echo esc_html( $order_bump_product_status ); ?></em>
							</div>
							<div class="cf-bump-form">
								<label class="cf-field-switch"><span>Enable offer</span><input type="checkbox" data-bump-setting="enabled" <?php checked( ! empty( $order_bump_settings['enabled'] ) ); ?> /></label>
								<label><span>Bump product</span><select data-bump-setting="product_id" data-bump-product-select><option value="0">Select a product</option>
									<?php if ( $order_bump_product instanceof WC_Product ) : ?>
										<option value="<?php echo esc_attr( (string) $order_bump_product->get_id() ); ?>" selected><?php echo esc_html( $order_bump_product_label ); ?></option>
									<?php endif; ?>
									<?php foreach ( $order_bump_products as $choice ) : ?>
										<?php if ( $order_bump_product instanceof WC_Product && absint( $choice['id'] ) === absint( $order_bump_product->get_id() ) ) { continue; } ?>
										<option value="<?php echo esc_attr( $choice['id'] ); ?>"><?php echo esc_html( $choice['label'] ); ?></option>
									<?php endforeach; ?>
								</select></label>
								<label><span>Offer title</span><input type="text" data-bump-setting="title" value="<?php echo esc_attr( $order_bump_settings['title'] ); ?>" /></label>
								<label><span>Offer description</span><input type="text" data-bump-setting="description" value="<?php echo esc_attr( $order_bump_settings['description'] ); ?>" /></label>
								<label><span>Badge</span><input type="text" data-bump-setting="badge" value="<?php echo esc_attr( $order_bump_settings['badge'] ); ?>" /></label>
								<label><span>Placement</span><select data-bump-setting="placement"><option value="after_summary" <?php selected( $order_bump_settings['placement'], 'after_summary' ); ?>>After order summary</option><option value="before_payment" <?php selected( $order_bump_settings['placement'], 'before_payment' ); ?>>Before payment</option><option value="after_payment" <?php selected( $order_bump_settings['placement'], 'after_payment' ); ?>>After payment</option></select></label>
							</div>
							<div class="cf-bump-preview">
								<span><?php echo esc_html( $order_bump_settings['badge'] ); ?></span>
								<strong><?php echo esc_html( $order_bump_settings['title'] ); ?></strong>
								<em><?php echo esc_html( $order_bump_settings['description'] ); ?></em>
								<small data-bump-preview-meta>One-click add-on card preview. Frontend updates after save and checkout refresh.</small>
							</div>
						</div>
					</div>
					<div class="panel">
						<div class="ph"><div class="pt">Display rules</div><div class="pa">Smart targeting</div></div>
						<div class="pb">
							<div class="cf-bump-rules-grid">
								<label><span>Minimum cart total</span><input type="number" step="0.01" min="0" data-bump-setting="min_total" value="<?php echo esc_attr( $order_bump_settings['min_total'] ); ?>" placeholder="No minimum" /></label>
								<label><span>Maximum cart total</span><input type="number" step="0.01" min="0" data-bump-setting="max_total" value="<?php echo esc_attr( $order_bump_settings['max_total'] ); ?>" placeholder="No maximum" /></label>
								<label><span>Require products</span><input type="text" data-bump-setting="include_products" value="<?php echo esc_attr( $order_bump_settings['include_products'] ); ?>" placeholder="IDs: 12,34" /></label>
								<label><span>Exclude products</span><input type="text" data-bump-setting="exclude_products" value="<?php echo esc_attr( $order_bump_settings['exclude_products'] ); ?>" placeholder="IDs: 56,78" /></label>
								<label><span>Require categories</span><input type="text" data-bump-setting="include_categories" value="<?php echo esc_attr( $order_bump_settings['include_categories'] ); ?>" placeholder="Category IDs" /></label>
								<label><span>Countries</span><input type="text" data-bump-setting="countries" value="<?php echo esc_attr( $order_bump_settings['countries'] ); ?>" placeholder="BD,US" /></label>
								<label><span>Payment methods</span><input type="text" data-bump-setting="payment_methods" value="<?php echo esc_attr( $order_bump_settings['payment_methods'] ); ?>" placeholder="cod,bacs" /></label>
								<label><span>Customer</span><select data-bump-setting="customer_rule"><option value="all" <?php selected( $order_bump_settings['customer_rule'], 'all' ); ?>>All customers</option><option value="guest" <?php selected( $order_bump_settings['customer_rule'], 'guest' ); ?>>Guest only</option><option value="logged_in" <?php selected( $order_bump_settings['customer_rule'], 'logged_in' ); ?>>Logged-in only</option></select></label>
							</div>
							<div class="cf-bump-rule-summary" data-bump-rule-summary></div>
							<div class="cf-bump-qa-list">
								<strong>Lock checklist</strong>
								<span data-bump-check-product>Product selected</span>
								<span data-bump-check-copy>Offer copy ready</span>
								<span data-bump-check-placement>Placement selected</span>
								<span data-bump-check-rules>Rules reviewed</span>
							</div>
							<div class="cf-bump-save-row">
								<div data-bump-save-status>Rules save to WordPress and apply on checkout refresh.</div>
								<button type="button" class="btn-p" data-save-order-bump>Save bump</button>
							</div>
						</div>
					</div>
				</div>
				<div class="g2 cf-hidden-legacy-bump" hidden>
					<div class="panel"><div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'bump_perf.title' ) ); ?></div><div class="pa">New bump</div></div><div class="pb"><div class="blist"><div class="brow"><div class="bimg">👕</div><div class="binf"><div class="bn"><?php echo esc_html( checkflow_str( 'bump.b1_name' ) ); ?></div><div class="bm"><?php echo esc_html( checkflow_str( 'bump.b1_meta' ) ); ?></div></div><div class="brt"><div class="bpct">38%</div><div class="brlbl"><?php echo esc_html( checkflow_str( 'bump.rate_lbl' ) ); ?></div></div></div><div class="brow"><div class="bimg">🎁</div><div class="binf"><div class="bn"><?php echo esc_html( checkflow_str( 'bump.b2_name' ) ); ?></div><div class="bm"><?php echo esc_html( checkflow_str( 'bump.b2_meta' ) ); ?></div></div><div class="brt"><div class="bpct">29%</div><div class="brlbl"><?php echo esc_html( checkflow_str( 'bump.rate_lbl' ) ); ?></div></div></div></div></div></div>
					<div class="panel"><div class="ph"><div class="pt">Rules</div></div><div class="pb"><div class="cf-module-list"><div><strong>Cart total</strong><span>Show when total is over $50</span></div><div><strong>Product match</strong><span>Show for apparel category</span></div><div><strong>Customer type</strong><span>First-time buyer only</span></div></div></div></div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'upsell' ) ); ?>" data-pane="upsell">
				<div class="cf-upsell-layout">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.upsell' ) ); ?></div><div class="pa">Rules UI foundation</div></div>
						<div class="pb">
							<div class="cf-bump-status-strip cf-upsell-status-strip">
								<div>
									<span>Current offer</span>
									<strong data-upsell-product-label><?php echo esc_html( $upsell_offer_label ); ?></strong>
								</div>
								<em data-upsell-product-status><?php echo $upsell_offer_product instanceof WC_Product ? esc_html__( 'Offer selected', 'checkflow' ) : esc_html__( 'Needs offer', 'checkflow' ); ?></em>
							</div>
							<div class="cf-upsell-safe-note">
								<strong>Foundation mode</strong>
								<span>Rules save now. Customer-facing upsell screens will be wired in the next execution pass without hijacking WooCommerce payment submission.</span>
							</div>
							<div class="cf-upsell-flow-tabs" data-upsell-flow-tabs>
								<button type="button" data-upsell-flow="pre_purchase" class="<?php echo 'pre_purchase' === $upsell_settings['flow_type'] ? 'is-active' : ''; ?>">Pre-purchase</button>
								<button type="button" data-upsell-flow="post_purchase" class="<?php echo 'post_purchase' === $upsell_settings['flow_type'] ? 'is-active' : ''; ?>">Post-purchase</button>
							</div>
							<input type="hidden" data-upsell-setting="flow_type" value="<?php echo esc_attr( $upsell_settings['flow_type'] ); ?>" />
							<div class="cf-bump-form cf-upsell-form">
								<label class="cf-field-switch"><span>Enable funnel</span><input type="checkbox" data-upsell-setting="enabled" <?php checked( $upsell_settings['enabled'] ); ?> /></label>
								<label><span>Offer product</span>
									<select data-upsell-setting="offer_product_id" data-upsell-product-select>
										<option value="0"><?php esc_html_e( 'Select offer product', 'checkflow' ); ?></option>
										<?php $upsell_offer_seen = false; ?>
										<?php foreach ( $upsell_products as $product_choice ) : ?>
											<?php $upsell_offer_seen = $upsell_offer_seen || absint( $product_choice['id'] ) === absint( $upsell_settings['offer_product_id'] ); ?>
											<option value="<?php echo esc_attr( $product_choice['id'] ); ?>" <?php selected( absint( $product_choice['id'] ), absint( $upsell_settings['offer_product_id'] ) ); ?>><?php echo esc_html( $product_choice['label'] ); ?></option>
										<?php endforeach; ?>
										<?php if ( $upsell_offer_product instanceof WC_Product && ! $upsell_offer_seen ) : ?>
											<option value="<?php echo esc_attr( (string) $upsell_offer_product->get_id() ); ?>" selected><?php echo esc_html( $upsell_offer_label ); ?></option>
										<?php endif; ?>
									</select>
								</label>
								<label><span>Downsell product</span>
									<select data-upsell-setting="downsell_product_id">
										<option value="0"><?php esc_html_e( 'Optional downsell product', 'checkflow' ); ?></option>
										<?php $upsell_downsell_seen = false; ?>
										<?php foreach ( $upsell_products as $product_choice ) : ?>
											<?php $upsell_downsell_seen = $upsell_downsell_seen || absint( $product_choice['id'] ) === absint( $upsell_settings['downsell_product_id'] ); ?>
											<option value="<?php echo esc_attr( $product_choice['id'] ); ?>" <?php selected( absint( $product_choice['id'] ), absint( $upsell_settings['downsell_product_id'] ) ); ?>><?php echo esc_html( $product_choice['label'] ); ?></option>
										<?php endforeach; ?>
										<?php if ( $upsell_downsell_product instanceof WC_Product && ! $upsell_downsell_seen ) : ?>
											<option value="<?php echo esc_attr( (string) $upsell_downsell_product->get_id() ); ?>" selected><?php echo esc_html( $upsell_downsell_label ); ?></option>
										<?php endif; ?>
									</select>
								</label>
								<label><span>Offer title</span><input type="text" data-upsell-setting="title" value="<?php echo esc_attr( $upsell_settings['title'] ); ?>" /></label>
								<label><span>Offer description</span><input type="text" data-upsell-setting="description" value="<?php echo esc_attr( $upsell_settings['description'] ); ?>" /></label>
								<label><span>Discount type</span>
									<select data-upsell-setting="discount_type">
										<option value="none" <?php selected( $upsell_settings['discount_type'], 'none' ); ?>>No discount</option>
										<option value="percent" <?php selected( $upsell_settings['discount_type'], 'percent' ); ?>>Percent off</option>
										<option value="fixed" <?php selected( $upsell_settings['discount_type'], 'fixed' ); ?>>Fixed amount</option>
									</select>
								</label>
								<label><span>Discount value</span><input type="text" data-upsell-setting="discount_value" value="<?php echo esc_attr( $upsell_settings['discount_value'] ); ?>" placeholder="10" /></label>
							</div>
							<div class="cf-bump-preview cf-upsell-preview">
								<span data-upsell-preview-mode><?php echo 'post_purchase' === $upsell_settings['flow_type'] ? esc_html__( 'Post-purchase', 'checkflow' ) : esc_html__( 'Pre-purchase', 'checkflow' ); ?></span>
								<strong><?php echo esc_html( $upsell_settings['title'] ); ?></strong>
								<em><?php echo esc_html( $upsell_settings['description'] ); ?></em>
								<small data-upsell-preview-meta></small>
							</div>
						</div>
					</div>
					<div class="panel">
						<div class="ph"><div class="pt">Trigger rules</div><div class="pa">Safe targeting</div></div>
						<div class="pb">
							<div class="cf-bump-rules-grid cf-upsell-rules-grid">
								<label><span>Minimum cart total</span><input type="text" data-upsell-setting="trigger_min_total" value="<?php echo esc_attr( $upsell_settings['trigger_min_total'] ); ?>" placeholder="No minimum" /></label>
								<label><span>Maximum cart total</span><input type="text" data-upsell-setting="trigger_max_total" value="<?php echo esc_attr( $upsell_settings['trigger_max_total'] ); ?>" placeholder="No maximum" /></label>
								<label><span>Require products</span><input type="text" data-upsell-setting="trigger_products" value="<?php echo esc_attr( $upsell_settings['trigger_products'] ); ?>" placeholder="IDs: 12,34" /></label>
								<label><span>Require categories</span><input type="text" data-upsell-setting="trigger_categories" value="<?php echo esc_attr( $upsell_settings['trigger_categories'] ); ?>" placeholder="Category IDs" /></label>
								<label><span>Countries</span><input type="text" data-upsell-setting="countries" value="<?php echo esc_attr( $upsell_settings['countries'] ); ?>" placeholder="BD,US" /></label>
								<label><span>Payment methods</span><input type="text" data-upsell-setting="payment_methods" value="<?php echo esc_attr( $upsell_settings['payment_methods'] ); ?>" placeholder="cod,bacs" /></label>
								<label><span>Customer</span>
									<select data-upsell-setting="customer_rule">
										<option value="all" <?php selected( $upsell_settings['customer_rule'], 'all' ); ?>>All customers</option>
										<option value="guest" <?php selected( $upsell_settings['customer_rule'], 'guest' ); ?>>Guests only</option>
										<option value="logged_in" <?php selected( $upsell_settings['customer_rule'], 'logged_in' ); ?>>Logged-in only</option>
										<option value="first_time" <?php selected( $upsell_settings['customer_rule'], 'first_time' ); ?>>First-time buyer</option>
										<option value="returning" <?php selected( $upsell_settings['customer_rule'], 'returning' ); ?>>Returning customer</option>
									</select>
								</label>
								<label><span>Display timing</span>
									<select data-upsell-setting="display_timing">
										<option value="before_payment" <?php selected( $upsell_settings['display_timing'], 'before_payment' ); ?>>Before payment</option>
										<option value="after_checkout" <?php selected( $upsell_settings['display_timing'], 'after_checkout' ); ?>>After checkout click</option>
										<option value="order_received" <?php selected( $upsell_settings['display_timing'], 'order_received' ); ?>>Order received</option>
									</select>
								</label>
							</div>
							<div class="cf-bump-rule-summary cf-upsell-rule-summary" data-upsell-rule-summary></div>
							<div class="cf-bump-qa-list cf-upsell-qa-list">
								<strong>Build checklist</strong>
								<span data-upsell-check-product>Offer product selected</span>
								<span data-upsell-check-copy>Offer copy ready</span>
								<span data-upsell-check-flow>Flow selected</span>
								<span data-upsell-check-rules>Rules reviewed</span>
							</div>
							<div class="cf-bump-save-row">
								<div class="cf-upsell-save-status" data-upsell-save-status>Settings save now; customer-facing upsell execution comes in the next implementation pass.</div>
								<button type="button" class="btn-p" data-save-upsell>Save funnel</button>
							</div>
						</div>
					</div>
					<div class="panel cf-upsell-roadmap">
						<div class="ph"><div class="pt">Workflow preview</div><div class="pa">No payment hijack</div></div>
						<div class="pb">
							<div class="cf-module-list">
								<div><strong>1. Trigger check</strong><span>Cart, product, category, country, payment, customer rules.</span></div>
								<div><strong>2. Offer screen</strong><span>Pre-purchase inline or post-purchase interstitial in a later pass.</span></div>
								<div><strong>3. Downsell fallback</strong><span>Optional secondary offer if the first offer is skipped.</span></div>
								<div><strong>4. Return safely</strong><span>Always return to WooCommerce checkout/order-received flow.</span></div>
							</div>
						</div>
					</div>
					<div class="panel">
						<div class="ph"><div class="pt">Performance foundation</div><div class="pa">Future data</div></div>
						<div class="pb"><div class="cf-mini-grid"><div class="cf-mini-card"><strong>0</strong><span>Shown</span></div><div class="cf-mini-card"><strong>0</strong><span>Accepted</span></div><div class="cf-mini-card"><strong>0%</strong><span>Take rate</span></div></div></div>
					</div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'bkash_nagad' ) ); ?>" data-pane="bkash_nagad">
				<div class="g2">
					<div class="panel"><div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.bkash_nagad' ) ); ?></div><div class="pa">Sandbox</div></div><div class="pb"><div class="cf-module-list"><div><strong>bKash</strong><span>App key, secret, merchant number</span></div><div><strong>Nagad</strong><span>Merchant ID, RSA keys, callback URL</span></div><div><strong>Rocket</strong><span>Merchant settings via gateway module</span></div><div><strong>SSLCOMMERZ</strong><span>Store ID, password, IPN</span></div></div></div></div>
					<div class="panel"><div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'payment.title' ) ); ?></div></div><div class="pb"><div class="plist"><div class="pi"><div class="pdot" style="background:#ff4081"></div><div class="pname"><?php echo esc_html( checkflow_str( 'payment.bkash' ) ); ?></div><div class="pbw"><div class="pbar" style="width:48%;background:#ff4081"></div></div><div class="ppct"><?php echo esc_html( checkflow_str( 'payment.pct48' ) ); ?></div></div><div class="pi"><div class="pdot" style="background:var(--or)"></div><div class="pname"><?php echo esc_html( checkflow_str( 'payment.nagad' ) ); ?></div><div class="pbw"><div class="pbar" style="width:22%;background:var(--or)"></div></div><div class="ppct"><?php echo esc_html( checkflow_str( 'payment.pct22' ) ); ?></div></div><div class="pi"><div class="pdot" style="background:var(--pr)"></div><div class="pname"><?php echo esc_html( checkflow_str( 'payment.card' ) ); ?></div><div class="pbw"><div class="pbar" style="width:12%;background:var(--pr)"></div></div><div class="ppct"><?php echo esc_html( checkflow_str( 'payment.pct12' ) ); ?></div></div></div></div></div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'settings' ) ); ?>" data-pane="settings">
				<div class="panel">
					<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.settings' ) ); ?></div></div>
					<div class="pb">
						<p style="color:var(--tx2);font-size:12px;margin-bottom:12px"><?php echo esc_html( checkflow_str( 'settings.strings_help' ) ); ?></p>
						<div style="margin-bottom:10px;display:flex;align-items:center;gap:10px;color:var(--tx);font-size:13px">
							<label for="cf-edit-locale" style="font-weight:700"><?php echo esc_html( checkflow_str( 'settings.strings_locale' ) ); ?></label>
							<select id="cf-edit-locale" class="cf-str-locale-picker">
								<?php foreach ( $labels as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $edit_locale, $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="btn-p cf-save-strings-btn" id="cf-save-overrides"><?php echo esc_html( checkflow_str( 'settings.strings_save' ) ); ?></button>
						</div>
						<div class="cf-string-editor">
							<div class="cf-string-row"><header><?php echo esc_html( checkflow_str( 'settings.strings_heading' ) ); ?></header></div>
							<?php foreach ( $str_keys as $key ) : ?>
								<?php
								$row = $i18n->get_bundle_and_override_row( $key, $edit_locale );
								$b   = isset( $row['bundle'] ) ? $row['bundle'] : '';
								$o   = isset( $row['override'] ) ? $row['override'] : '';
								?>
								<div class="cf-string-row">
									<div class="cf-string-key"><?php echo esc_html( $key ); ?></div>
									<div>
										<small><?php echo esc_html( checkflow_str( 'settings.strings_default' ) ); ?></small>
										<textarea readonly class=""><?php echo esc_textarea( $b ); ?></textarea>
									</div>
									<div>
										<small><?php echo esc_html( checkflow_str( 'settings.strings_custom' ) ); ?></small>
										<textarea class="cf-str-custom" data-key="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $o ); ?></textarea>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				</div>

		</div>

	</div><!-- .main -->

</div><!-- #checkflow-admin -->
