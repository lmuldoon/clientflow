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
		$fields = [
			'clientflow_license_key'            => 'sanitize_text_field',
			'clientflow_stripe_publishable_key' => 'sanitize_text_field',
			'clientflow_stripe_secret_key'      => 'sanitize_text_field',
			'clientflow_stripe_webhook_secret'  => 'sanitize_text_field',
		];

		foreach ( $fields as $option => $sanitizer ) {
			$value = isset( $_POST[ $option ] ) ? call_user_func( $sanitizer, wp_unslash( $_POST[ $option ] ) ) : '';
			update_option( $option, $value );
		}

		// Plan override (dev/testing only).
		if ( ! empty( $_POST['cf_dev_plan'] ) ) {
			$new_plan = sanitize_text_field( wp_unslash( $_POST['cf_dev_plan'] ) );
			$old_plan = ClientFlow_Entitlements::get_user_plan( get_current_user_id() );
			ClientFlow_Entitlements::set_user_plan( get_current_user_id(), $new_plan, $old_plan );
		}

		$saved = true;
	} else {
		$errors[] = __( 'Security check failed. Please try again.', 'clientflow' );
	}
}

// ── Current values ────────────────────────────────────────────────────────────

$current_plan = ClientFlow_Entitlements::get_user_plan( get_current_user_id() );
$license_key  = get_option( 'clientflow_license_key', '' );
$pub_key      = get_option( 'clientflow_stripe_publishable_key', '' );
$secret_key   = get_option( 'clientflow_stripe_secret_key', '' );
$webhook_sec  = get_option( 'clientflow_stripe_webhook_secret', '' );

$stripe_mode   = str_starts_with( $secret_key, 'sk_live_' ) ? 'live' : ( $secret_key ? 'test' : '' );
$webhook_url   = rest_url( 'clientflow/v1/payments/webhook' );

$plan_colors = [ 'free' => 'cf-badge--none', 'pro' => 'cf-badge--test', 'agency' => 'cf-badge--live' ];
$plan_color  = $plan_colors[ $current_plan ] ?? 'cf-badge--none';
?>
<div class="wrap">
<style>
/* ── Settings page styles ──────────────────────────────────────────── */
.cf-settings-wrap {
	max-width: 740px;
	padding: 32px 28px 64px;
	font-family: 'Archivo', -apple-system, 'Segoe UI', sans-serif;
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
	font-size: 26px;
	font-weight: 800;
	color: #1A1A2E;
	margin: 0 0 4px;
	line-height: 1.2;
	letter-spacing: -0.5px;
}
.cf-settings-hero__sub {
	font-size: 13.5px;
	color: #6B7280;
	margin: 0;
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
</style>

<div class="cf-settings-wrap">

	<!-- Hero -->
	<div class="cf-settings-hero">
		<div class="cf-settings-hero__icon">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<circle cx="12" cy="12" r="3"/>
				<path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
			</svg>
		</div>
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

		<!-- ── Licence card ─────────────────────────────────────────────────── -->
		<div class="cf-card">
			<p class="cf-card__title">
				<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
				</svg>
				<?php esc_html_e( 'Licence', 'clientflow' ); ?>
				<?php if ( $license_key ) : ?>
					<span class="cf-badge <?php echo esc_attr( $plan_color ); ?>">
						<?php echo esc_html( ucfirst( $current_plan ) ); ?>
					</span>
				<?php else : ?>
					<span class="cf-badge cf-badge--none"><?php esc_html_e( 'Not configured', 'clientflow' ); ?></span>
				<?php endif; ?>
			</p>
			<p class="cf-card__desc">
				<?php esc_html_e( 'Your ClientFlow licence key unlocks Pro or Agency features and authenticates AI requests on your behalf. Enter the key from your purchase confirmation email.', 'clientflow' ); ?>
			</p>

			<div class="cf-field">
				<label class="cf-label" for="cf-license-key">
					<?php esc_html_e( 'Licence Key', 'clientflow' ); ?>
				</label>
				<input
					type="password"
					id="cf-license-key"
					name="clientflow_license_key"
					class="cf-input"
					value="<?php echo esc_attr( $license_key ); ?>"
					placeholder="sk_…"
					autocomplete="new-password"
					spellcheck="false"
				>
				<p class="cf-help">
					<?php esc_html_e( 'Find your licence key in your Freemius account under Licences, or in your purchase confirmation email.', 'clientflow' ); ?>
				</p>
			</div>

			<div class="cf-test-row">
				<button
					type="button"
					id="cf-license-test-btn"
					class="cf-copy-btn"
					onclick="cfTestLicence()"
				><?php esc_html_e( 'Test Licence', 'clientflow' ); ?></button>
				<span id="cf-license-test-result" class="cf-test-result"></span>
			</div>
		</div>

		<!-- ── Stripe API Keys card ──────────────────────────────────────────── -->
		<div class="cf-card">
			<p class="cf-card__title">
				<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
				</svg>
				<?php esc_html_e( 'Stripe API Keys', 'clientflow' ); ?>
				<?php if ( $stripe_mode ) : ?>
					<span class="cf-badge cf-badge--<?php echo esc_attr( $stripe_mode ); ?>">
						<?php echo esc_html( ucfirst( $stripe_mode ) ); ?> <?php esc_html_e( 'mode', 'clientflow' ); ?>
					</span>
				<?php else : ?>
					<span class="cf-badge cf-badge--none"><?php esc_html_e( 'Not configured', 'clientflow' ); ?></span>
				<?php endif; ?>
			</p>
			<p class="cf-card__desc">
				<?php esc_html_e( 'Find these in your Stripe Dashboard under Developers → API Keys.', 'clientflow' ); ?>
			</p>

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
					value="<?php echo esc_attr( $secret_key ); ?>"
					placeholder="sk_test_…"
					autocomplete="new-password"
					spellcheck="false"
				>
				<p class="cf-help"><?php esc_html_e( 'Never share your secret key. It is stored encrypted in your database.', 'clientflow' ); ?></p>
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
						onclick="navigator.clipboard.writeText(document.getElementById('cf-webhook-url').value).then(function(){this.textContent='Copied!';}.bind(this))"
					><?php esc_html_e( 'Copy', 'clientflow' ); ?></button>
				</div>
			</div>

			<div class="cf-divider"></div>

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

		<!-- ── Developer plan override ───────────────────────────────────────── -->
		<div class="cf-card" style="border:1.5px dashed #D1D5DB;background:#FAFAF8;">
			<p class="cf-card__title" style="color:#6B7280;">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
					<path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
				</svg>
				<?php esc_html_e( 'Developer: Plan Override', 'clientflow' ); ?>
				<span class="cf-badge cf-badge--none" style="font-size:10px;margin-left:4px;">TESTING ONLY</span>
			</p>
			<p class="cf-card__desc">
				<?php esc_html_e( 'Override your plan to test gated features (payments, portal, AI). This only affects your own account. Set back to Free when done.', 'clientflow' ); ?>
			</p>
			<div class="cf-field" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<label class="cf-label" for="cf-dev-plan" style="margin:0;white-space:nowrap;">
					<?php esc_html_e( 'Your current plan:', 'clientflow' ); ?>
				</label>
				<select id="cf-dev-plan" name="cf_dev_plan" class="cf-plan-select">
					<?php foreach ( [ 'free' => 'Free', 'pro' => 'Pro', 'agency' => 'Agency' ] as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_plan, $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="cf-badge <?php echo esc_attr( $plan_color ); ?>">
					<?php echo esc_html( ucfirst( $current_plan ) ); ?> <?php esc_html_e( 'active', 'clientflow' ); ?>
				</span>
			</div>
		</div>

		<button type="submit" class="cf-btn-save">
			<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
			<?php esc_html_e( 'Save Settings', 'clientflow' ); ?>
		</button>

	</form>
</div>
</div>
<script>
function cfTestLicence() {
	var btn    = document.getElementById( 'cf-license-test-btn' );
	var result = document.getElementById( 'cf-license-test-result' );
	btn.disabled    = true;
	btn.textContent = 'Testing…';
	result.textContent = '';
	result.style.color = '';

	fetch( '<?php echo esc_js( rest_url( 'clientflow/v1/ai/test-connection' ) ); ?>', {
		method:  'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce':   '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
		},
	} )
	.then( function( r ) { return r.json(); } )
	.then( function( data ) {
		btn.disabled    = false;
		btn.textContent = 'Test Licence';
		if ( data.success ) {
			result.textContent = '✓ ' + data.message;
			result.style.color = '#065F46';
		} else {
			result.textContent = '✗ ' + data.message;
			result.style.color = '#991B1B';
		}
	} )
	.catch( function() {
		btn.disabled    = false;
		btn.textContent = 'Test Licence';
		result.textContent = '✗ Request failed. Check browser console.';
		result.style.color = '#991B1B';
	} );
}
</script>
