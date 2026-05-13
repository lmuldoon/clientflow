<?php
/**
 * Admin View: ClientFlow Settings
 *
 * General settings page: licence key, Stripe, developer overrides.
 * Handles saving via direct option updates (nonce-verified POST).
 *
 * @package ClientFlow
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Insufficient permissions.', 'clientflow' ) );
}

// ── Save handler ──────────────────────────────────────────────────────────────

$saved  = false;
$errors = [];

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['cf_settings_nonce'] ) ) {
	if ( wp_verify_nonce( $_POST['cf_settings_nonce'], 'cf_save_settings' ) ) {
		// Branding options: available to all plans.
		$fields = [
			'clientflow_business_name' => 'sanitize_text_field',
			'clientflow_from_name'     => 'sanitize_text_field',
			'clientflow_from_email'    => 'sanitize_email',
			'clientflow_brand_color'   => 'sanitize_hex_color',
			'clientflow_logo_url'      => 'esc_url_raw',
		];

		foreach ( $fields as $option => $sanitizer ) {
			$value = isset( $_POST[ $option ] ) ? call_user_func( $sanitizer, wp_unslash( $_POST[ $option ] ) ) : '';
			update_option( $option, $value );
		}

		// Stripe options: paid plans only — do not overwrite on free to avoid clearing stored keys.
		$_save_owner_id = cf_get_owner_id( get_current_user_id() );
		if ( cf_can_user( $_save_owner_id, 'use_payments' ) ) {
			update_option( 'clientflow_stripe_publishable_key', sanitize_text_field( wp_unslash( $_POST['clientflow_stripe_publishable_key'] ?? '' ) ) );
			update_option( 'clientflow_stripe_secret_key',      sanitize_text_field( wp_unslash( $_POST['clientflow_stripe_secret_key'] ?? '' ) ) );
			update_option( 'clientflow_stripe_webhook_secret',  sanitize_text_field( wp_unslash( $_POST['clientflow_stripe_webhook_secret'] ?? '' ) ) );
		}

		// Testimonial options: paid plans only.
		if ( cf_can_user( $_save_owner_id, 'use_testimonials' ) ) {
			update_option( 'clientflow_testimonial_body',      sanitize_textarea_field( wp_unslash( $_POST['clientflow_testimonial_body'] ?? '' ) ) );
			update_option( 'clientflow_testimonial_url',       esc_url_raw( wp_unslash( $_POST['clientflow_testimonial_url'] ?? '' ) ) );
			update_option( 'clientflow_testimonial_cta_label', sanitize_text_field( wp_unslash( $_POST['clientflow_testimonial_cta_label'] ?? '' ) ) );
			update_option( 'clientflow_testimonial_enabled',   ! empty( $_POST['clientflow_testimonial_enabled'] ) ? '1' : '' );
		} else {
			update_option( 'clientflow_testimonial_enabled', '' );
		}

		$saved = true;
	} else {
		$errors[] = __( 'Security check failed. Please try again.', 'clientflow' );
	}
}

// ── Current values ────────────────────────────────────────────────────────────

$pub_key      = get_option( 'clientflow_stripe_publishable_key', '' );
$secret_key   = get_option( 'clientflow_stripe_secret_key', '' );
$webhook_sec  = get_option( 'clientflow_stripe_webhook_secret', '' );

$stripe_mode   = str_starts_with( $secret_key, 'sk_live_' ) ? 'live' : ( $secret_key ? 'test' : '' );
$webhook_url   = rest_url( 'clientflow/v1/payments/webhook' );

$business_name = get_option( 'clientflow_business_name', '' );
$from_name     = get_option( 'clientflow_from_name', '' );
$from_email    = get_option( 'clientflow_from_email', '' );
$brand_color   = get_option( 'clientflow_brand_color', '#6366f1' );
$logo_url      = get_option( 'clientflow_logo_url', '' );

$testimonial_enabled    = get_option( 'clientflow_testimonial_enabled', '' );
$testimonial_body       = get_option( 'clientflow_testimonial_body', '' );
$testimonial_review_url = get_option( 'clientflow_testimonial_url', '' );
$testimonial_cta_label  = get_option( 'clientflow_testimonial_cta_label', '' );

$cf_owner_id        = cf_get_owner_id( get_current_user_id() );
$cf_payments_locked = ! cf_can_user( $cf_owner_id, 'use_payments' );
$cf_is_free         = ! cf_can_user( $cf_owner_id, 'use_testimonials' );

?>
<div>
<style>
/* ── Settings page styles ──────────────────────────────────────────── */
.cf-settings-wrap {
	max-width: 1100px;
	padding: 32px 28px 64px;
	font-family: 'Archivo', -apple-system, 'Segoe UI', sans-serif;
}
.cf-settings-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	align-items: start;
	margin-bottom: 20px;
}
.cf-settings-grid .cf-card--full {
	grid-column: 1 / -1;
}
.cf-settings-grid .cf-card {
	margin-bottom: 0;
}
@media (max-width: 900px) {
	.cf-settings-grid { grid-template-columns: 1fr; }
}
.cf-settings-hero {
	display: flex;
	align-items: flex-start;
	gap: 20px;
	margin-bottom: 36px;
}
.cf-settings-hero__icon {
	width: 52px;
	height: 52px;
	background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
	border-radius: 14px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	box-shadow: 0 4px 12px rgba(99,102,241,.25);
}
.cf-settings-hero__title {
	font-family: var(--cf-font);
  font-size: 28px; 
  font-weight: 800; 
	color: var(--cf-navy);
  letter-spacing: -.5px; 
  margin: 0; 
  line-height: 1;
}
.cf-settings-hero__sub {
	font-size: 14px;
	color: #94A3B8;
	margin: 6px 0 0;
	line-height: 1.5;
}
.cf-card {
	background: #fff;
	border: 1px solid #E5E7EB;
	border-radius: 16px;
	padding: 28px 32px;
	margin-bottom: 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.cf-card__title {
	font-size: 15px;
	font-weight: 700;
	color: #1A1A2E;
	margin: 0 0 4px;
	display: flex;
	align-items: center;
	gap: 10px;
}
.cf-card__title svg { flex-shrink: 0; }
.cf-card__desc {
	font-size: 13px;
	color: #6B7280;
	margin: 0 0 24px;
	line-height: 1.6;
}
.cf-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	padding: 3px 9px;
	border-radius: 100px;
}
.cf-badge--test { background: #FEF3C7; color: #92400E; }
.cf-badge--live { background: #ECFDF5; color: #065F46; }
.cf-badge--none { background: #F3F4F6; color: #6B7280; }
.cf-field { margin-bottom: 22px; }
.cf-field:last-child { margin-bottom: 0; }
.cf-label {
	display: block;
	font-size: 13px;
	font-weight: 600;
	color: #374151;
	margin-bottom: 7px;
}
.cf-label span {
	font-weight: 400;
	color: #9CA3AF;
	font-size: 12px;
	margin-left: 4px;
}
.cf-input {
	width: 100%;
	height: 42px;
	border: 1.5px solid #D1D5DB;
	border-radius: 9px;
	padding: 0 14px;
	font-size: 13px;
	font-family: 'DM Mono', 'Courier New', monospace;
	color: #1A1A2E;
	background: #FAFAFA;
	transition: border-color .15s, box-shadow .15s;
	outline: none;
	letter-spacing: 0.03em;
	box-sizing: border-box;
}
.cf-input:focus {
	border-color: #6366F1;
	box-shadow: 0 0 0 3px rgba(99,102,241,.12);
	background: #fff;
}
.cf-input::placeholder { color: #9CA3AF; font-family: -apple-system, sans-serif; letter-spacing: 0; }
.cf-webhook-row { display: flex; gap: 8px; }
.cf-webhook-row .cf-input { flex: 1; color: #6B7280; background: #F9FAFB; cursor: default; }
.cf-copy-btn {
	height: 42px;
	padding: 0 16px;
	background: #F3F4F6;
	border: 1.5px solid #D1D5DB;
	border-radius: 9px;
	font-size: 13px;
	font-weight: 600;
	color: #374151;
	cursor: pointer;
	transition: background .15s;
	white-space: nowrap;
	font-family: inherit;
}
.cf-copy-btn:hover { background: #E5E7EB; }
.cf-notice {
	padding: 12px 18px;
	border-radius: 9px;
	font-size: 13px;
	font-weight: 500;
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 20px;
}
.cf-notice--success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.cf-notice--error   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
.cf-btn-save {
	display: inline-flex;
	align-items: center;
	gap: 9px;
	height: 44px;
	padding: 0 26px;
	background: #6366F1;
	color: #fff;
	border: none;
	border-radius: 10px;
	font-size: 14px;
	font-weight: 600;
	font-family: inherit;
	cursor: pointer;
	transition: background .15s, box-shadow .15s, transform .1s;
	box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
.cf-btn-save:hover { background: #4F46E5; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(99,102,241,.4); }
.cf-btn-save:active { transform: none; }
.cf-plan-select {
	height: 42px;
	border: 1.5px solid #D1D5DB;
	border-radius: 9px;
	padding: 0 14px;
	font-size: 13px;
	font-weight: 600;
	color: #1A1A2E;
	background: #fff;
	cursor: pointer;
	outline: none;
	transition: border-color .15s;
}
.cf-plan-select:focus { border-color: #6366F1; }
.cf-help {
	font-size: 12px;
	color: #9CA3AF;
	margin-top: 6px;
	line-height: 1.5;
}
.cf-help a { color: #6366F1; text-decoration: none; }
.cf-help a:hover { text-decoration: underline; }
.cf-hint { font-size: 12px; color: #9CA3AF; margin-top: 6px; line-height: 1.5; }
.cf-divider { height: 1px; background: #F3F4F6; margin: 24px 0; }
.cf-test-row {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-top: 4px;
}
.cf-test-result {
	font-size: 13px;
	font-weight: 600;
}
/* ── Branding card ──────────────────────────────────────────────────── */
.cf-color-row {
	display: flex;
	align-items: center;
	gap: 10px;
}
#cf-brand-color-picker {
	-webkit-appearance: none;
	appearance: none;
	width: 42px;
	height: 42px;
	border: 1.5px solid #D1D5DB;
	border-radius: 9px;
	padding: 3px;
	background: #FAFAFA;
	cursor: pointer;
	flex-shrink: 0;
	transition: border-color .15s, box-shadow .15s;
}
#cf-brand-color-picker::-webkit-color-swatch-wrapper { padding: 0; border-radius: 6px; overflow: hidden; }
#cf-brand-color-picker::-webkit-color-swatch         { border: none; border-radius: 6px; }
#cf-brand-color-picker::-moz-color-swatch            { border: none; border-radius: 6px; }
#cf-brand-color-picker:focus {
	border-color: #6366F1;
	box-shadow: 0 0 0 3px rgba(99,102,241,.12);
	outline: none;
}
#cf-brand-color-hex {
	width: 110px;
	flex-shrink: 0;
	font-family: 'DM Mono', 'Courier New', monospace;
	letter-spacing: 0.05em;
}
.cf-logo-preview-wrap {
	margin-top: 10px;
	padding: 10px 14px;
	background: #F9FAFB;
	border: 1.5px dashed #D1D5DB;
	border-radius: 9px;
	display: inline-flex;
	align-items: center;
	gap: 10px;
	width: 100%;
}
.cf-logo-preview-label {
	font-size: 11px;
	font-weight: 600;
	color: #9CA3AF;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}
#cf-logo-preview {
	max-height: 48px;
	max-width: 180px;
	display: block;
}
</style>

<div class="cf-settings-wrap">

	<!-- Hero -->
	<div class="cf-settings-hero">
		<div>
			<h1 class="cf-settings-hero__title"><?php esc_html_e( 'Settings', 'clientflow' ); ?></h1>
			<p class="cf-settings-hero__sub">
				<?php esc_html_e( 'Configure your ClientFlow licence and payment settings.', 'clientflow' ); ?>
			</p>
		</div>
	</div>

	<?php foreach ( $errors as $err ) : ?>
		<div class="cf-notice cf-notice--error">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			<?php echo esc_html( $err ); ?>
		</div>
	<?php endforeach; ?>

	<?php if ( $saved ) : ?>
		<div class="cf-notice cf-notice--success">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
			<?php esc_html_e( 'Settings saved.', 'clientflow' ); ?>
		</div>
	<?php endif; ?>

	<form method="POST" action="">
		<?php wp_nonce_field( 'cf_save_settings', 'cf_settings_nonce' ); ?>

		<div class="cf-settings-grid">

			<!-- ── Branding card ─────────────────────────────────────────────────── -->
			<div class="cf-card">
				<p class="cf-card__title">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="13.5" cy="6.5" r=".5" fill="#6366F1"/><circle cx="17.5" cy="10.5" r=".5" fill="#6366F1"/><circle cx="8.5" cy="7.5" r=".5" fill="#6366F1"/><circle cx="6.5" cy="12.5" r=".5" fill="#6366F1"/>
						<path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10c.555 0 1.1-.05 1.629-.145a1 1 0 00.571-1.67 1.002 1.002 0 01.148-1.37c.35-.29.83-.4 1.28-.28l1.95.52a1 1 0 001.23-.97V17c0-2.76-2.24-5-5-5h-1c-.55 0-1-.45-1-1 0-.55.45-1 1-1 .55 0 1-.45 1-1 0-.55-.45-1-1-1z"/>
					</svg>
					<?php esc_html_e( 'Branding', 'clientflow' ); ?>
				</p>
				<p class="cf-card__desc">
					<?php esc_html_e( 'Customise the name, colour, and logo shown on client-facing proposals and the client portal.', 'clientflow' ); ?>
				</p>

				<div class="cf-field">
					<label class="cf-label" for="cf-business-name">
						<?php esc_html_e( 'Business Name', 'clientflow' ); ?>
					</label>
					<input
						type="text"
						id="cf-business-name"
						name="clientflow_business_name"
						class="cf-input"
						value="<?php echo esc_attr( $business_name ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Acme Studio', 'clientflow' ); ?>"
						autocomplete="organization"
						spellcheck="false"
					>
					<p class="cf-help"><?php esc_html_e( 'Used in email footers and the client portal header.', 'clientflow' ); ?></p>
				</div>

				<div class="cf-divider"></div>

				<div class="cf-field">
					<label class="cf-label" for="cf-from-name">
						<?php esc_html_e( 'Sender Name', 'clientflow' ); ?>
					</label>
					<input
						type="text"
						id="cf-from-name"
						name="clientflow_from_name"
						class="cf-input"
						value="<?php echo esc_attr( $from_name ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Acme Studio', 'clientflow' ); ?>"
						autocomplete="off"
						spellcheck="false"
					>
					<p class="cf-help"><?php esc_html_e( 'The display name clients see in their inbox — usually your agency or business name.', 'clientflow' ); ?></p>
				</div>

				<div class="cf-field">
					<label class="cf-label" for="cf-from-email">
						<?php esc_html_e( 'Sender Email', 'clientflow' ); ?>
					</label>
					<input
						type="email"
						id="cf-from-email"
						name="clientflow_from_email"
						class="cf-input"
						value="<?php echo esc_attr( $from_email ); ?>"
						placeholder="<?php esc_attr_e( 'hello@youragency.com', 'clientflow' ); ?>"
						autocomplete="email"
						spellcheck="false"
					>
					<p class="cf-help"><?php esc_html_e( 'The address all ClientFlow emails are sent from. Must be an address you control.', 'clientflow' ); ?></p>
				</div>

				<div class="cf-divider"></div>

				<div class="cf-field">
					<label class="cf-label" for="cf-brand-color-picker">
						<?php esc_html_e( 'Brand Colour', 'clientflow' ); ?>
					</label>
					<div class="cf-color-row">
						<input
							type="color"
							id="cf-brand-color-picker"
							name="clientflow_brand_color"
							value="<?php echo esc_attr( $brand_color ); ?>"
						>
						<input
							type="text"
							id="cf-brand-color-hex"
							class="cf-input"
							value="<?php echo esc_attr( $brand_color ); ?>"
							placeholder="#6366f1"
							maxlength="7"
							spellcheck="false"
							autocomplete="off"
						>
					</div>
					<p class="cf-help"><?php esc_html_e( 'Applied to proposal buttons and portal accents.', 'clientflow' ); ?></p>
				</div>

				<div class="cf-field">
					<label class="cf-label" for="cf-logo-url-input">
						<?php esc_html_e( 'Logo URL', 'clientflow' ); ?>
					</label>
					<input
						type="url"
						id="cf-logo-url-input"
						name="clientflow_logo_url"
						class="cf-input"
						value="<?php echo esc_attr( $logo_url ); ?>"
						placeholder="https://…/logo.png"
						autocomplete="off"
						spellcheck="false"
					>
					<div class="cf-logo-preview-wrap" id="cf-logo-preview-wrap" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
						<span class="cf-logo-preview-label"><?php esc_html_e( 'Preview', 'clientflow' ); ?></span>
						<img id="cf-logo-preview" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Logo preview', 'clientflow' ); ?>">
					</div>
					<p class="cf-help"><?php esc_html_e( 'Displayed in proposal headers and portal. Use a PNG or SVG with transparent background. Max 180×48px recommended.', 'clientflow' ); ?></p>
				</div>
			</div>

			<!-- ── Stripe cards (stacked in one grid column) ────────────────────── -->
			<div style="display:flex;flex-direction:column;gap:20px;">

			<!-- ── Stripe API Keys card ──────────────────────────────────────────── -->
			<div class="cf-card">
				<p class="cf-card__title">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
					</svg>
					<?php esc_html_e( 'Stripe API Keys', 'clientflow' ); ?>
					<?php if ( ! $cf_payments_locked && $stripe_mode ) : ?>
						<span class="cf-badge cf-badge--<?php echo esc_attr( $stripe_mode ); ?>">
							<?php echo esc_html( ucfirst( $stripe_mode ) ); ?> <?php esc_html_e( 'mode', 'clientflow' ); ?>
						</span>
					<?php elseif ( ! $cf_payments_locked ) : ?>
						<span class="cf-badge cf-badge--none"><?php esc_html_e( 'Not configured', 'clientflow' ); ?></span>
					<?php endif; ?>
				</p>
				<p class="cf-card__desc">
					<?php esc_html_e( 'Find these in your Stripe Dashboard under Developers → API Keys.', 'clientflow' ); ?>
				</p>

				<div style="position:relative;">

					<?php if ( $cf_payments_locked ) : ?>
					<div style="position:absolute;inset:0;z-index:10;background:rgba(255,255,255,0.85);backdrop-filter:blur(3px);border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;text-align:center;padding:20px;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
							<path d="M7 11V7a5 5 0 0110 0v4"/>
						</svg>
						<p style="margin:0;font-size:13px;font-weight:600;color:#1A1A2E;"><?php esc_html_e( 'Available on Pro &amp; Agency plans', 'clientflow' ); ?></p>
						<a href="https://clientflow.io/pricing" target="_blank" rel="noopener" style="display:inline-block;padding:7px 18px;background:#6366F1;color:#fff;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;"><?php esc_html_e( 'Upgrade', 'clientflow' ); ?></a>
					</div>
					<?php endif; ?>

					<div class="cf-field">
						<label class="cf-label" for="cf-pub-key">
							<?php esc_html_e( 'Publishable Key', 'clientflow' ); ?>
							<span><?php esc_html_e( '(pk_test_… or pk_live_…)', 'clientflow' ); ?></span>
						</label>
						<input
							type="text"
							id="cf-pub-key"
							name="clientflow_stripe_publishable_key"
							class="cf-input"
							<?php disabled( $cf_payments_locked, true ); ?>
							value="<?php echo esc_attr( $pub_key ); ?>"
							placeholder="pk_test_…"
							autocomplete="off"
							spellcheck="false"
						>
					</div>

					<div class="cf-field">
						<label class="cf-label" for="cf-secret-key">
							<?php esc_html_e( 'Secret Key', 'clientflow' ); ?>
							<span><?php esc_html_e( '(sk_test_… or sk_live_…)', 'clientflow' ); ?></span>
						</label>
						<input
							type="password"
							id="cf-secret-key"
							name="clientflow_stripe_secret_key"
							class="cf-input"
							<?php disabled( $cf_payments_locked, true ); ?>
							value="<?php echo esc_attr( $secret_key ); ?>"
							placeholder="sk_test_…"
							autocomplete="new-password"
							spellcheck="false"
						>
						<p class="cf-help"><?php esc_html_e( 'Never share your secret key. It is stored encrypted in your database.', 'clientflow' ); ?></p>
					</div>

				</div>
			</div>

			<!-- ── Webhook card ──────────────────────────────────────────────────── -->
			<div class="cf-card">
				<p class="cf-card__title">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.8 10.72a19.79 19.79 0 01-3.07-8.67A2 2 0 012.71 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 9.67a16 16 0 006.29 6.29l1.03-1.04a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
					</svg>
					<?php esc_html_e( 'Stripe Webhook', 'clientflow' ); ?>
				</p>
				<p class="cf-card__desc">
					<?php
					printf(
						esc_html__( 'Add this URL in your Stripe Dashboard under Developers → Webhooks. Listen for the %s event.', 'clientflow' ),
						'<code>checkout.session.completed</code>'
					);
					?>
				</p>

				<div style="position:relative;">

					<?php if ( $cf_payments_locked ) : ?>
					<div style="position:absolute;inset:0;z-index:10;background:rgba(255,255,255,0.85);backdrop-filter:blur(3px);border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;text-align:center;padding:20px;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
							<path d="M7 11V7a5 5 0 0110 0v4"/>
						</svg>
						<p style="margin:0;font-size:13px;font-weight:600;color:#1A1A2E;"><?php esc_html_e( 'Available on Pro &amp; Agency plans', 'clientflow' ); ?></p>
						<a href="https://clientflow.io/pricing" target="_blank" rel="noopener" style="display:inline-block;padding:7px 18px;background:#6366F1;color:#fff;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;"><?php esc_html_e( 'Upgrade', 'clientflow' ); ?></a>
					</div>
					<?php endif; ?>

					<div class="cf-field">
						<label class="cf-label" for="cf-webhook-url"><?php esc_html_e( 'Webhook Endpoint URL', 'clientflow' ); ?></label>
						<div class="cf-webhook-row">
							<input
								type="text"
								id="cf-webhook-url"
								class="cf-input"
								value="<?php echo esc_url( $webhook_url ); ?>"
								readonly
							>
							<button
								type="button"
								class="cf-copy-btn"
								<?php disabled( $cf_payments_locked, true ); ?>
								onclick="navigator.clipboard.writeText(document.getElementById('cf-webhook-url').value).then(function(){this.textContent='Copied!';}.bind(this))"
							><?php esc_html_e( 'Copy', 'clientflow' ); ?></button>
						</div>
					</div>

					<div class="cf-field">
						<label class="cf-label" for="cf-webhook-secret">
							<?php esc_html_e( 'Signing Secret', 'clientflow' ); ?>
							<span><?php esc_html_e( '(whsec_…)', 'clientflow' ); ?></span>
						</label>
						<input
							type="password"
							id="cf-webhook-secret"
							name="clientflow_stripe_webhook_secret"
							class="cf-input"
							<?php disabled( $cf_payments_locked, true ); ?>
							value="<?php echo esc_attr( $webhook_sec ); ?>"
							placeholder="whsec_…"
							autocomplete="new-password"
							spellcheck="false"
						>
						<p class="cf-help">
							<?php esc_html_e( 'Found in your webhook\'s settings page on the Stripe Dashboard. Used to verify events are genuinely from Stripe.', 'clientflow' ); ?>
						</p>
					</div>

				</div>
			</div>

			</div><!-- /.stripe-column -->

			<!-- ── Testimonial Emails card ──────────────────────────────────────── -->
			<div class="cf-card">
				<p class="cf-card__title">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
					</svg>
					<?php esc_html_e( 'Testimonial Emails', 'clientflow' ); ?>
				</p>
				<p class="cf-card__desc">
					<?php esc_html_e( 'When enabled, clients will receive a review request email once their final payment clears. Tick the box below to turn this on. Available on Pro and Agency plans.', 'clientflow' ); ?>
				</p>

				<div style="position:relative;">

					<?php if ( $cf_is_free ) : ?>
					<div style="position:absolute;inset:0;z-index:10;background:rgba(255,255,255,0.85);backdrop-filter:blur(3px);border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;text-align:center;padding:20px;">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
							<path d="M7 11V7a5 5 0 0110 0v4"/>
						</svg>
						<p style="margin:0;font-size:13px;font-weight:600;color:#1A1A2E;"><?php esc_html_e( 'Available on Pro &amp; Agency plans', 'clientflow' ); ?></p>
						<a href="https://clientflow.io/pricing" target="_blank" rel="noopener" style="display:inline-block;padding:7px 18px;background:#6366F1;color:#fff;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;"><?php esc_html_e( 'Upgrade', 'clientflow' ); ?></a>
					</div>
					<?php endif; ?>

					<div class="cf-field" style="display:flex;align-items:center;gap:10px;">
						<input
							type="checkbox"
							id="cf-testimonial-enabled"
							name="clientflow_testimonial_enabled"
							value="1"
							<?php checked( $testimonial_enabled, '1' ); ?>
							<?php disabled( $cf_is_free, true ); ?>
							style="width:18px;height:18px;cursor:pointer;flex-shrink:0;"
						>
						<label for="cf-testimonial-enabled" style="margin:0;font-size:13px;font-weight:500;color:#374151;cursor:pointer;">
							<?php esc_html_e( 'Send testimonial request email after final payment', 'clientflow' ); ?>
						</label>
					</div>

					<div class="cf-divider"></div>

					<div class="cf-field">
						<label class="cf-label" for="cf-testimonial-body">
							<?php esc_html_e( 'Email body copy', 'clientflow' ); ?>
						</label>
						<textarea
							id="cf-testimonial-body"
							name="clientflow_testimonial_body"
							class="cf-input"
							rows="4"
							<?php disabled( $cf_is_free, true ); ?>
							style="height:auto;padding:12px 14px;font-family:-apple-system,sans-serif;letter-spacing:0;resize:vertical;"
							placeholder="<?php esc_attr_e( "It was a pleasure working with you. If you have a moment, we\xe2\x80\x99d love to hear your feedback \xe2\x80\x94 it helps us improve and helps others find us.", 'clientflow' ); ?>"
						><?php echo esc_textarea( $testimonial_body ); ?></textarea>
						<p class="cf-hint"><?php esc_html_e( 'Plain text. Leave blank to use the default message.', 'clientflow' ); ?></p>
					</div>

					<div class="cf-field">
						<label class="cf-label" for="cf-testimonial-url">
							<?php esc_html_e( 'Review / testimonial URL', 'clientflow' ); ?>
							<span><?php esc_html_e( '(optional)', 'clientflow' ); ?></span>
						</label>
						<input
							type="url"
							id="cf-testimonial-url"
							name="clientflow_testimonial_url"
							class="cf-input"
							<?php disabled( $cf_is_free, true ); ?>
							value="<?php echo esc_url( $testimonial_review_url ); ?>"
							placeholder="https://g.page/r/your-google-review-link"
							autocomplete="off"
							spellcheck="false"
						>
						<p class="cf-hint"><?php esc_html_e( 'Google Reviews, Trustpilot, Clutch, or any custom form. Leave blank to omit the button.', 'clientflow' ); ?></p>
					</div>

					<div class="cf-field">
						<label class="cf-label" for="cf-testimonial-cta-label">
							<?php esc_html_e( 'Button label', 'clientflow' ); ?>
							<span><?php esc_html_e( '(optional)', 'clientflow' ); ?></span>
						</label>
						<input
							type="text"
							id="cf-testimonial-cta-label"
							name="clientflow_testimonial_cta_label"
							class="cf-input"
							<?php disabled( $cf_is_free, true ); ?>
							value="<?php echo esc_attr( $testimonial_cta_label ); ?>"
							placeholder="<?php esc_attr_e( 'Leave a Review', 'clientflow' ); ?>"
							spellcheck="false"
						>
						<p class="cf-hint"><?php esc_html_e( 'Text shown on the review button. Defaults to "Leave a Review".', 'clientflow' ); ?></p>
					</div>

				</div>
			</div>

		</div><!-- /.cf-settings-grid -->

		<button type="submit" class="cf-btn-save">
			<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
			<?php esc_html_e( 'Save Settings', 'clientflow' ); ?>
		</button>

	</form>
</div>
</div>
<script>
(function () {
	var picker   = document.getElementById( 'cf-brand-color-picker' );
	var hexInput = document.getElementById( 'cf-brand-color-hex' );
	if ( picker && hexInput ) {
		picker.addEventListener( 'input', function () { hexInput.value = picker.value; } );
		hexInput.addEventListener( 'input', function () {
			if ( /^#[0-9A-Fa-f]{6}$/.test( hexInput.value.trim() ) ) {
				picker.value = hexInput.value.trim();
			}
		} );
		hexInput.addEventListener( 'blur', function () {
			if ( ! /^#[0-9A-Fa-f]{6}$/.test( hexInput.value.trim() ) ) {
				hexInput.value = picker.value;
			}
		} );
	}
	var logoInput   = document.getElementById( 'cf-logo-url-input' );
	var logoPreview = document.getElementById( 'cf-logo-preview' );
	var logoWrap    = document.getElementById( 'cf-logo-preview-wrap' );
	if ( logoInput && logoPreview && logoWrap ) {
		logoInput.addEventListener( 'input', function () {
			var url = logoInput.value.trim();
			if ( url ) {
				logoPreview.src        = url;
				logoWrap.style.display = 'inline-flex';
			} else {
				logoWrap.style.display = 'none';
				logoPreview.src        = '';
			}
		} );
	}
}());

</script>
