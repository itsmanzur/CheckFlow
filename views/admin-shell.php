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

$str_keys       = $i18n->get_flat_keys_sorted();
$quick_settings = CheckFlow_Admin::instance()->get_quick_settings();
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
<div id="checkflow-admin" class="checkflow-root">
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
						<div class="sval bl"><?php echo esc_html( checkflow_str( 'stats.rev_value' ) ); ?></div>
						<div class="sdlt up"><?php echo esc_html( checkflow_str( 'stats.rev_delta' ) ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( checkflow_str( 'stats.vs_7d' ) ); ?></div>
					</div>
					<div class="sc gn">
						<div class="slbl"><?php echo esc_html( checkflow_str( 'stats.success_orders' ) ); ?></div>
						<div class="sval gn"><?php echo esc_html( checkflow_str( 'stats.orders_value' ) ); ?></div>
						<div class="sdlt up"><?php echo esc_html( checkflow_str( 'stats.orders_delta' ) ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( checkflow_str( 'stats.conv_line' ) ); ?></div>
					</div>
					<div class="sc or">
						<div class="slbl"><?php echo esc_html( checkflow_str( 'stats.bump_revenue' ) ); ?></div>
						<div class="sval or"><?php echo esc_html( checkflow_str( 'stats.bump_value' ) ); ?></div>
						<div class="sdlt up"><?php echo esc_html( checkflow_str( 'stats.bump_delta' ) ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( checkflow_str( 'stats.take_line' ) ); ?></div>
					</div>
					<div class="sc pu">
						<div class="slbl"><?php echo esc_html( checkflow_str( 'stats.avg_order' ) ); ?></div>
						<div class="sval pu"><?php echo esc_html( checkflow_str( 'stats.avg_value' ) ); ?></div>
						<div class="sdlt dn"><?php echo esc_html( checkflow_str( 'stats.avg_delta' ) ); ?></div>
						<div class="ssub" style="margin-top:5px"><?php echo esc_html( checkflow_str( 'stats.avg_target_line' ) ); ?></div>
					</div>
				</div>

				<div class="g2">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'funnel.title' ) ); ?></div><div class="pa"><?php echo esc_html( checkflow_str( 'funnel.details' ) ); ?></div></div>
						<div class="pb">
							<div class="funnel">
								<div class="fr">
									<div class="flbl"><?php echo esc_html( checkflow_str( 'funnel.page_views' ) ); ?></div>
									<div class="fbw"><div class="fb bl" style="width:100%"><?php echo esc_html( checkflow_str( 'funnel.f1_txt' ) ); ?></div></div>
									<div class="fnum"><?php echo esc_html( checkflow_str( 'funnel.f1_num' ) ); ?></div>
									<div class="fdrop"></div>
								</div>
								<div class="fr">
									<div class="flbl"><?php echo esc_html( checkflow_str( 'funnel.form_start' ) ); ?></div>
									<div class="fbw"><div class="fb tl" style="width:80%"><?php echo esc_html( checkflow_str( 'funnel.f2_txt' ) ); ?></div></div>
									<div class="fnum"><?php echo esc_html( checkflow_str( 'funnel.f2_num' ) ); ?></div>
									<div class="fdrop" style="color:var(--rd)"><?php echo esc_html( checkflow_str( 'funnel.drop20' ) ); ?></div>
								</div>
								<div class="fr">
									<div class="flbl"><?php echo esc_html( checkflow_str( 'funnel.payment_select' ) ); ?></div>
									<div class="fbw"><div class="fb or" style="width:71%"><?php echo esc_html( checkflow_str( 'funnel.f3_txt' ) ); ?></div></div>
									<div class="fnum"><?php echo esc_html( checkflow_str( 'funnel.f3_num' ) ); ?></div>
									<div class="fdrop" style="color:var(--or)"><?php echo esc_html( checkflow_str( 'funnel.drop11' ) ); ?></div>
								</div>
								<div class="fr">
									<div class="flbl"><?php echo esc_html( checkflow_str( 'funnel.complete' ) ); ?></div>
									<div class="fbw"><div class="fb gn" style="width:68%"><?php echo esc_html( checkflow_str( 'funnel.f4_txt' ) ); ?></div></div>
									<div class="fnum"><?php echo esc_html( checkflow_str( 'funnel.f4_num' ) ); ?></div>
									<div class="fdrop" style="color:var(--gn)"><?php echo esc_html( checkflow_str( 'funnel.pct68' ) ); ?></div>
								</div>
							</div>
							<div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--bd)">
								<div style="font-size:12px;font-weight:700;color:var(--tx);margin-bottom:12px"><?php echo esc_html( checkflow_str( 'bump_perf.title' ) ); ?></div>
								<div class="blist">
									<div class="brow">
										<div class="bimg">👕</div>
										<div class="binf">
											<div class="bn"><?php echo esc_html( checkflow_str( 'bump.b1_name' ) ); ?></div>
											<div class="bm"><?php echo esc_html( checkflow_str( 'bump.b1_meta' ) ); ?></div>
										</div>
										<div class="brt"><div class="bpct">38%</div><div class="brlbl"><?php echo esc_html( checkflow_str( 'bump.rate_lbl' ) ); ?></div></div>
									</div>
									<div class="brow">
										<div class="bimg">🎁</div>
										<div class="binf">
											<div class="bn"><?php echo esc_html( checkflow_str( 'bump.b2_name' ) ); ?></div>
											<div class="bm"><?php echo esc_html( checkflow_str( 'bump.b2_meta' ) ); ?></div>
										</div>
										<div class="brt"><div class="bpct">29%</div><div class="brlbl"><?php echo esc_html( checkflow_str( 'bump.rate_lbl' ) ); ?></div></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'payment.title' ) ); ?></div><div class="pa"><?php echo esc_html( checkflow_str( 'payment.settings' ) ); ?></div></div>
						<div class="pb">
							<div class="plist">
								<div class="pi">
									<div class="pdot" style="background:#ff4081"></div>
									<div class="pname"><?php echo esc_html( checkflow_str( 'payment.bkash' ) ); ?></div>
									<div class="pbw"><div class="pbar" style="width:48%;background:#ff4081"></div></div>
									<div class="ppct"><?php echo esc_html( checkflow_str( 'payment.pct48' ) ); ?></div>
								</div>
								<div class="pi">
									<div class="pdot" style="background:var(--or)"></div>
									<div class="pname"><?php echo esc_html( checkflow_str( 'payment.nagad' ) ); ?></div>
									<div class="pbw"><div class="pbar" style="width:22%;background:var(--or)"></div></div>
									<div class="ppct"><?php echo esc_html( checkflow_str( 'payment.pct22' ) ); ?></div>
								</div>
								<div class="pi">
									<div class="pdot" style="background:var(--gn)"></div>
									<div class="pname"><?php echo esc_html( checkflow_str( 'payment.cod' ) ); ?></div>
									<div class="pbw"><div class="pbar" style="width:18%;background:var(--gn)"></div></div>
									<div class="ppct"><?php echo esc_html( checkflow_str( 'payment.pct18' ) ); ?></div>
								</div>
								<div class="pi">
									<div class="pdot" style="background:var(--pr)"></div>
									<div class="pname"><?php echo esc_html( checkflow_str( 'payment.card' ) ); ?></div>
									<div class="pbw"><div class="pbar" style="width:12%;background:var(--pr)"></div></div>
									<div class="ppct"><?php echo esc_html( checkflow_str( 'payment.pct12' ) ); ?></div>
								</div>
							</div>
							<div style="margin-top:16px;border-top:1px solid var(--bd);padding-top:14px">
								<div style="font-size:11px;color:var(--tx3);margin-bottom:8px"><?php echo esc_html( checkflow_str( 'payment.daily' ) ); ?></div>
								<div class="mct" id="cf-mc"></div>
								<div class="cx" id="cf-cx"></div>
							</div>
							<div style="margin-top:16px;border-top:1px solid var(--bd);padding-top:14px">
								<div style="font-size:12px;font-weight:700;color:var(--tx);margin-bottom:8px"><?php echo esc_html( checkflow_str( 'courier.summary_title' ) ); ?></div>
								<div class="cg">
									<div class="cc"><div class="ccn">Pathao</div><div class="cco"><?php echo esc_html( checkflow_str( 'courier.pathao' ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div>
									<div class="cc"><div class="ccn">RedX</div><div class="cco"><?php echo esc_html( checkflow_str( 'courier.redx' ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div>
									<div class="cc"><div class="ccn">Steadfast</div><div class="cco"><?php echo esc_html( checkflow_str( 'courier.steadfast' ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div>
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
									<?php foreach ( $order_rows as $i => $r ) : ?>
										<tr>
											<td><span class="oid"><?php echo esc_html( $r[0] ); ?></span></td>
											<td><span class="ocust"><?php echo esc_html( $r[1] ); ?></span></td>
											<td><span class="gtag <?php echo esc_attr( $r[2] ); ?>"><?php echo esc_html( checkflow_str( $gw_map[ $r[2] ] ) ); ?></span></td>
											<td style="color:var(--tx3);font-size:12px"><?php echo esc_html( $r[3] ); ?></td>
											<td><span class="oamt"><?php echo isset( $amts[ $i ] ) ? esc_html( $amts[ $i ] ) : ''; ?></span></td>
											<td><span class="stag <?php echo esc_attr( 'paid' === $r[4] ? 'paid' : ( 'pend' === $r[4] ? 'pend' : 'fail' ) ); ?>"><?php echo esc_html( checkflow_str( $st_map[ $r[4] ] ) ); ?></span></td>
										</tr>
									<?php endforeach; ?>
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
				<div class="g2">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.orders' ) ); ?></div><div class="pa">WooCommerce sync</div></div>
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
									<?php foreach ( $order_rows as $i => $r ) : ?>
										<tr>
											<td><span class="oid"><?php echo esc_html( $r[0] ); ?></span></td>
											<td><span class="ocust"><?php echo esc_html( $r[1] ); ?></span></td>
											<td><span class="gtag <?php echo esc_attr( $r[2] ); ?>"><?php echo esc_html( checkflow_str( $gw_map[ $r[2] ] ) ); ?></span></td>
											<td style="color:var(--tx3);font-size:12px"><?php echo esc_html( $r[3] ); ?></td>
											<td><span class="oamt"><?php echo isset( $amts[ $i ] ) ? esc_html( $amts[ $i ] ) : ''; ?></span></td>
											<td><span class="stag <?php echo esc_attr( 'paid' === $r[4] ? 'paid' : ( 'pend' === $r[4] ? 'pend' : 'fail' ) ); ?>"><?php echo esc_html( checkflow_str( $st_map[ $r[4] ] ) ); ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
					<div class="gcol">
						<div class="panel"><div class="ph"><div class="pt">Order actions</div></div><div class="pb"><div class="cf-action-row"><span>Bulk courier booking</span><button type="button" class="btn-p">Prepare</button></div><div class="cf-action-row"><span>Failed payment follow-up</span><button type="button" class="btn-p">Review</button></div><div class="cf-action-row"><span>CSV export</span><button type="button" class="btn-p">Export</button></div></div></div>
						<div class="panel"><div class="ph"><div class="pt">Status mix</div></div><div class="pb"><div class="cf-mini-grid"><div class="cf-mini-card"><strong>5</strong><span>Paid</span></div><div class="cf-mini-card"><strong>1</strong><span>Pending</span></div><div class="cf-mini-card"><strong>1</strong><span>Failed</span></div></div></div></div>
					</div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'pixel' ) ); ?>" data-pane="pixel">
				<div class="g2">
					<div class="panel">
						<div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.pixel' ) ); ?></div><div class="pa">Test Event</div></div>
						<div class="pb">
							<div class="pxl">
								<div class="pxi"><span style="font-size:18px">📘</span><div><div class="pxn">Meta CAPI</div><div class="pxe">Purchase, InitiateCheckout, AddPaymentInfo</div></div><div class="pxs ok"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.active' ) ); ?></div></div>
								<div class="pxi"><span style="font-size:18px">🎯</span><div><div class="pxn">Google Enhanced</div><div class="pxe">Conversion ID + Label pending real settings</div></div><div class="pxs ok"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.active' ) ); ?></div></div>
								<div class="pxi"><span style="font-size:18px">🎵</span><div><div class="pxn">TikTok Events API</div><div class="pxe"><?php echo esc_html( checkflow_str( 'pixel.tiktok_no_key' ) ); ?></div></div><div class="pxs warn"><div class="pulse"></div><?php echo esc_html( checkflow_str( 'pixel.set_up' ) ); ?></div></div>
							</div>
						</div>
					</div>
					<div class="panel"><div class="ph"><div class="pt">Event queue</div><div class="pa">Retry failed</div></div><div class="pb"><div class="cf-module-list"><div><strong>Purchase</strong><span>checkflow_1047 · sent</span></div><div><strong>InitiateCheckout</strong><span>session_a82 · sent</span></div><div><strong>AddPaymentInfo</strong><span>session_a82 · pending</span></div><div><strong>Purchase</strong><span>checkflow_1042 · failed</span></div></div></div></div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'courier' ) ); ?>" data-pane="courier">
				<div class="g2">
					<div class="panel"><div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'courier.summary_title' ) ); ?></div><div class="pa">Configure</div></div><div class="pb"><div class="cg"><div class="cc"><div class="ccn">Pathao</div><div class="cco"><?php echo esc_html( checkflow_str( 'courier.pathao' ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div><div class="cc"><div class="ccn">RedX</div><div class="cco"><?php echo esc_html( checkflow_str( 'courier.redx' ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div><div class="cc"><div class="ccn">SteadFast</div><div class="cco"><?php echo esc_html( checkflow_str( 'courier.steadfast' ) ); ?></div><div class="ccl"><?php echo esc_html( checkflow_str( 'courier.orders' ) ); ?></div></div></div></div></div>
					<div class="panel"><div class="ph"><div class="pt">Booking workflow</div></div><div class="pb"><div class="cf-action-row"><span>Auto book after processing</span><div class="tgl on" role="switch" aria-checked="true" tabindex="0"></div></div><div class="cf-action-row"><span>COD reconciliation</span><div class="tgl" role="switch" aria-checked="false" tabindex="0"></div></div><div class="cf-action-row"><span>Fallback courier suggestion</span><div class="tgl on" role="switch" aria-checked="true" tabindex="0"></div></div></div></div>
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
								<button type="button" class="btn-p cf-save-fields">Save fields</button>
								<button type="button" class="cf-btn-ghost cf-reset-fields">Reset</button>
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
										$default   = CheckFlow_Field_Editor::instance()->get_default_field_for_admin( $key );
										?>
											<div class="cf-field-row" data-field-key="<?php echo esc_attr( $key ); ?>" data-field-group="<?php echo esc_attr( $group ); ?>" data-field-type="<?php echo esc_attr( $type ); ?>" data-field-custom="<?php echo ! empty( $field['custom'] ) ? '1' : '0'; ?>" data-field-options="<?php echo esc_attr( wp_json_encode( isset( $field['options'] ) && is_array( $field['options'] ) ? array_values( $field['options'] ) : array() ) ); ?>" data-protected="<?php echo $locked ? '1' : '0'; ?>" data-default-label="<?php echo esc_attr( isset( $default['label'] ) ? (string) $default['label'] : $label ); ?>" data-default-priority="<?php echo esc_attr( isset( $default['priority'] ) ? (string) absint( $default['priority'] ) : (string) $priority ); ?>" data-default-enabled="<?php echo ! empty( $default['enabled'] ) || $locked ? '1' : '0'; ?>" data-default-required="<?php echo ! empty( $default['required'] ) ? '1' : '0'; ?>" data-default-placeholder="<?php echo esc_attr( isset( $default['placeholder'] ) ? (string) $default['placeholder'] : '' ); ?>" data-default-help="<?php echo esc_attr( isset( $default['help'] ) ? (string) $default['help'] : '' ); ?>" data-default-width="<?php echo esc_attr( isset( $default['width'] ) ? (string) $default['width'] : 'default' ); ?>" data-default-value="<?php echo esc_attr( isset( $default['default_value'] ) ? (string) $default['default_value'] : '' ); ?>">
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
				<div class="cf-template-grid">
					<?php foreach ( array( 'Default One Page', 'Bangladesh COD', 'Mobile Banking', 'Minimal Digital', 'Premium Split' ) as $i => $tpl ) : ?>
						<div class="panel"><div class="pb"><div class="cf-template-thumb"><span><?php echo esc_html( (string) ( $i + 1 ) ); ?></span></div><div class="cf-template-name"><?php echo esc_html( $tpl ); ?></div><div class="cf-template-meta"><?php echo 0 === $i ? 'Active' : 'Pro template'; ?></div></div></div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'order_bump' ) ); ?>" data-pane="order_bump">
				<div class="g2">
					<div class="panel"><div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'bump_perf.title' ) ); ?></div><div class="pa">New bump</div></div><div class="pb"><div class="blist"><div class="brow"><div class="bimg">👕</div><div class="binf"><div class="bn"><?php echo esc_html( checkflow_str( 'bump.b1_name' ) ); ?></div><div class="bm"><?php echo esc_html( checkflow_str( 'bump.b1_meta' ) ); ?></div></div><div class="brt"><div class="bpct">38%</div><div class="brlbl"><?php echo esc_html( checkflow_str( 'bump.rate_lbl' ) ); ?></div></div></div><div class="brow"><div class="bimg">🎁</div><div class="binf"><div class="bn"><?php echo esc_html( checkflow_str( 'bump.b2_name' ) ); ?></div><div class="bm"><?php echo esc_html( checkflow_str( 'bump.b2_meta' ) ); ?></div></div><div class="brt"><div class="bpct">29%</div><div class="brlbl"><?php echo esc_html( checkflow_str( 'bump.rate_lbl' ) ); ?></div></div></div></div></div></div>
					<div class="panel"><div class="ph"><div class="pt">Rules</div></div><div class="pb"><div class="cf-module-list"><div><strong>Cart total</strong><span>Show when total is over $50</span></div><div><strong>Product match</strong><span>Show for apparel category</span></div><div><strong>Customer type</strong><span>First-time buyer only</span></div></div></div></div>
				</div>
			</div>

			<div class="cf-pane<?php echo esc_attr( $screen_class( 'upsell' ) ); ?>" data-pane="upsell">
				<div class="g2">
					<div class="panel"><div class="ph"><div class="pt"><?php echo esc_html( checkflow_str( 'nav.upsell' ) ); ?></div><div class="pa">Create funnel</div></div><div class="pb"><div class="cf-module-list"><div><strong>Step 1</strong><span>Post-payment premium add-on</span></div><div><strong>Step 2</strong><span>Downsell if customer skips</span></div><div><strong>Thank-you</strong><span>Return to WooCommerce order received</span></div></div></div></div>
					<div class="panel"><div class="ph"><div class="pt">Performance</div></div><div class="pb"><div class="cf-mini-grid"><div class="cf-mini-card"><strong>124</strong><span>Shown</span></div><div class="cf-mini-card"><strong>31</strong><span>Accepted</span></div><div class="cf-mini-card"><strong>25%</strong><span>Take rate</span></div></div></div></div>
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
