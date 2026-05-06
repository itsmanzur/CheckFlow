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

$str_keys = $i18n->get_flat_keys_sorted();
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
			<div class="ni on" data-screen="dashboard" role="button" tabindex="0"><span class="ni-ico">📊</span> <?php echo esc_html( checkflow_str( 'nav.dashboard' ) ); ?></div>
			<div class="ni" data-screen="orders" role="button" tabindex="0"><span class="ni-ico">🛒</span> <?php echo esc_html( checkflow_str( 'nav.orders' ) ); ?> <span class="badge g"><?php echo esc_html( checkflow_str( 'nav.badge_orders' ) ); ?></span></div>
			<div class="ni" data-screen="pixel" role="button" tabindex="0"><span class="ni-ico">📡</span> <?php echo esc_html( checkflow_str( 'nav.pixel' ) ); ?></div>
			<div class="ni" data-screen="courier" role="button" tabindex="0"><span class="ni-ico">📦</span> <?php echo esc_html( checkflow_str( 'nav.courier' ) ); ?></div>

			<div class="nav-sec"><?php echo esc_html( checkflow_str( 'nav.sec_checkout' ) ); ?></div>
			<div class="ni" data-screen="field_editor" role="button" tabindex="0"><span class="ni-ico">🧩</span> <?php echo esc_html( checkflow_str( 'nav.field_editor' ) ); ?></div>
			<div class="ni" data-screen="templates" role="button" tabindex="0"><span class="ni-ico">🎨</span> <?php echo esc_html( checkflow_str( 'nav.templates' ) ); ?></div>
			<div class="ni" data-screen="order_bump" role="button" tabindex="0"><span class="ni-ico">🎁</span> <?php echo esc_html( checkflow_str( 'nav.order_bump' ) ); ?> <span class="badge">!</span></div>
			<div class="ni" data-screen="upsell" role="button" tabindex="0"><span class="ni-ico">🚀</span> <?php echo esc_html( checkflow_str( 'nav.upsell' ) ); ?></div>

			<div class="nav-sec"><?php echo esc_html( checkflow_str( 'nav.sec_payment' ) ); ?></div>
			<div class="ni" data-screen="bkash_nagad" role="button" tabindex="0"><span class="ni-ico">💳</span> <?php echo esc_html( checkflow_str( 'nav.bkash_nagad' ) ); ?></div>
			<div class="ni" data-screen="settings" role="button" tabindex="0"><span class="ni-ico">⚙️</span> <?php echo esc_html( checkflow_str( 'nav.settings' ) ); ?></div>
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
			<div class="tb-title" id="cf-ttl"><?php echo esc_html( checkflow_str( 'nav.dashboard' ) ); ?> <span><?php echo esc_html( checkflow_str( 'screen.dashboard.sub' ) ); ?></span></div>
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
					<div class="tab on"><?php echo esc_html( checkflow_str( 'tab.7d' ) ); ?></div>
					<div class="tab"><?php echo esc_html( checkflow_str( 'tab.30d' ) ); ?></div>
					<div class="tab"><?php echo esc_html( checkflow_str( 'tab.all' ) ); ?></div>
				</div>
				<div class="date-btn"><?php echo esc_html( checkflow_str( 'topbar.date_range' ) ); ?></div>
				<button type="button" class="btn-p"><?php echo esc_html( checkflow_str( 'topbar.new_bump' ) ); ?></button>
				<div class="avatar">R<div class="ndot"></div></div>
			</div>
		</div>

		<div class="cnt">
			<div class="cf-pane is-active" data-pane="dashboard">
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
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.popup_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.popup_desc' ) ); ?></div></div>
										<div class="tgl on"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.bump_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.bump_desc' ) ); ?></div></div>
										<div class="tgl on"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.timer_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.timer_desc' ) ); ?></div></div>
										<div class="tgl"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.recaptcha_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.recaptcha_desc' ) ); ?></div></div>
										<div class="tgl on"></div>
									</div>
									<div class="srow">
										<div class="sinf"><div class="sn"><?php echo esc_html( checkflow_str( 'qs.guest_title' ) ); ?></div><div class="sd"><?php echo esc_html( checkflow_str( 'qs.guest_desc' ) ); ?></div></div>
										<div class="tgl on"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div><!-- dashboard -->

			<?php
			$stub_screens = array( 'orders', 'pixel', 'courier', 'field_editor', 'templates', 'order_bump', 'upsell', 'bkash_nagad' );
			foreach ( $stub_screens as $sid ) :
				?>
				<div class="cf-pane" data-pane="<?php echo esc_attr( $sid ); ?>">
					<div class="panel">
						<div class="pb">
							<div class="cf-stub">
								<h2><?php echo esc_html( checkflow_str( 'stub.title' ) ); ?></h2>
								<p><?php echo esc_html( checkflow_str( 'stub.body' ) ); ?></p>
								<button type="button" class="btn-p cf-stub-dash"><?php echo esc_html( checkflow_str( 'stub.back_dashboard' ) ); ?></button>
							</div>
						</div>
					</div>
				</div>
				<?php
			endforeach;
			?>

			<div class="cf-pane" data-pane="settings">
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
