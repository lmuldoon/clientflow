<?php
/**
 * ClientFlow Client Proposal Template
 *
 * Standalone HTML page served when a client visits /proposals/{token}.
 * Completely bypasses the active WordPress theme — this is a self-contained
 * document viewer page.
 *
 * Variables injected by client-routing.php:
 *   $clientflow_proposal_token  string  Sanitised UUID token from the URL.
 *
 * @package ClientFlow
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- All file-scope variables in this template use the clientflow_ prefix.

// Determine whether this is a preview or a standard proposal URL.
$clientflow_preview_token  = $clientflow_preview_token  ?? '';
$clientflow_proposal_token = $clientflow_proposal_token ?? '';
$clientflow_is_preview        = '' !== $clientflow_preview_token;
$clientflow_active_token      = $clientflow_is_preview ? $clientflow_preview_token : $clientflow_proposal_token;

if ( empty( $clientflow_active_token ) ) {
	wp_die(
		esc_html__( 'Invalid proposal link.', 'clientflow' ),
		esc_html__( 'Not Found', 'clientflow' ),
		[ 'response' => 404 ]
	);
}

// Payment result only applies on live proposal URLs.
$clientflow_payment_result = $clientflow_is_preview ? '' : ( $clientflow_payment_result ?? '' );
$clientflow_session_id     = $clientflow_is_preview ? '' : ( $clientflow_session_id     ?? '' );

// ── Client email (for success page personalisation) ───────────────────────────
$clientflow_client_email = '';
if ( ! $clientflow_is_preview && ! empty( $clientflow_payment_result ) && class_exists( 'ClientFlow_Proposal_Client' ) ) {
	$_proposal_row = ClientFlow_Proposal_Client::get_by_token( $clientflow_proposal_token );
	if ( ! is_wp_error( $_proposal_row ) ) {
		$clientflow_client_email = $_proposal_row['client_email'] ?? '';
	}
}

// ── Business branding ─────────────────────────────────────────────────────────
$clientflow_business_name = get_bloginfo( 'name' );
$clientflow_business_logo = esc_url( get_option( 'clientflow_logo_url', '' ) );
$clientflow_brand_color   = sanitize_hex_color( get_option( 'clientflow_brand_color', '#6366F1' ) ) ?: '#6366F1';

// ── Asset URLs ────────────────────────────────────────────────────────────────
$clientflow_build_dir     = CLIENTFLOW_DIR . 'build/';
$clientflow_build_url     = CLIENTFLOW_URL . 'build/';
$clientflow_asset_file    = $clientflow_build_dir . 'client.asset.php';
$clientflow_asset         = file_exists( $clientflow_asset_file ) ? require $clientflow_asset_file : [ 'version' => CLIENTFLOW_VERSION ];
$clientflow_script_ver    = $clientflow_asset['version'] ?? CLIENTFLOW_VERSION;

$clientflow_script_url    = $clientflow_build_url . 'client.js';
$clientflow_style_url     = $clientflow_build_url . 'client.css';
$clientflow_has_css       = file_exists( $clientflow_build_dir . 'client.css' );

// The client bundle must exist.
if ( ! file_exists( $clientflow_build_dir . 'client.js' ) ) {
	wp_die(
		esc_html__( 'Proposal viewer assets not built. Please contact the site administrator.', 'clientflow' ),
		esc_html__( 'Configuration Error', 'clientflow' ),
		[ 'response' => 500 ]
	);
}

// ── Favicon ───────────────────────────────────────────────────────────────────
$clientflow_favicon_url = get_site_icon_url( 32 );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">

	<title><?php echo esc_html( $clientflow_business_name ); ?> &mdash; <?php esc_html_e( 'Proposal', 'clientflow' ); ?></title>

	<?php if ( $clientflow_favicon_url ) : ?>
		<link rel="icon" href="<?php echo esc_url( $clientflow_favicon_url ); ?>">
	<?php endif; ?>

	<?php if ( $clientflow_has_css ) : ?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone template, wp_enqueue_style() cannot be used outside WP head. ?>
		<link rel="stylesheet" href="<?php echo esc_url( $clientflow_style_url ); ?>?v=<?php echo esc_attr( $clientflow_script_ver ); ?>">
	<?php endif; ?>

	<!-- Minimal body reset to avoid flash of unstyled content before React mounts -->
	<style>
		*, *::before, *::after { box-sizing: border-box; }
		body {
			margin: 0;
			padding: 0;
			background: #F8F7F5;
			font-family: -apple-system, 'DM Sans', sans-serif;
			min-height: 100vh;
		}
		#cf-client-root {
			min-height: 100vh;
		}
		/* Loading indicator shown before React hydrates */
		.cf-preload {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			flex-direction: column;
			gap: 16px;
			color: #9CA3AF;
			font-size: 14px;
			font-family: -apple-system, sans-serif;
		}
		.cf-preload__spinner {
			width: 32px;
			height: 32px;
			border: 2.5px solid #E5E7EB;
			border-top-color: #6366F1;
			border-radius: 50%;
			animation: cf-pre-spin 0.8s linear infinite;
		}
		@keyframes cf-pre-spin { to { transform: rotate(360deg); } }
	</style>
</head>
<body>

	<div id="cf-client-root">
		<!-- Pre-hydration loading indicator -->
		<div class="cf-preload">
			<div class="cf-preload__spinner"></div>
			<span><?php esc_html_e( 'Loading your proposal&hellip;', 'clientflow' ); ?></span>
		</div>
	</div>

	<?php
	/*
	 * Load all dependencies declared by the asset manifest plus wp-element.
	 * The automatic JSX runtime requires 'react-jsx-runtime' to be present;
	 * older builds may instead need 'react' + 'wp-element'. The asset file
	 * is the authoritative source of what this bundle needs.
	 */
	$clientflow_script_deps = $clientflow_asset['dependencies'] ?? [];
	// Always ensure wp-element is available (provides wp.element.render).
	if ( ! in_array( 'wp-element', $clientflow_script_deps, true ) ) {
		$clientflow_script_deps[] = 'wp-element';
	}
	wp_print_scripts( $clientflow_script_deps );
	?>

	<script>
		window.cfClientData = {
			apiUrl:          <?php echo wp_json_encode( rest_url( 'clientflow/v1/' ) ); ?>,
			token:           <?php echo wp_json_encode( $clientflow_active_token ); ?>,
			businessName:    <?php echo wp_json_encode( $clientflow_business_name ); ?>,
			businessLogo:    <?php echo wp_json_encode( $clientflow_business_logo ); ?>,
			brandColor:      <?php echo wp_json_encode( $clientflow_brand_color ); ?>,
			nonce:           <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
			pageType:        <?php echo wp_json_encode( $clientflow_is_preview ? 'preview' : ( $clientflow_payment_result ?: 'proposal' ) ); ?>,
			sessionId:       <?php echo wp_json_encode( $clientflow_session_id ); ?>,
			clientEmail:     <?php echo wp_json_encode( $clientflow_client_email ); ?>,
			isPortalClient:  <?php echo wp_json_encode( class_exists( 'ClientFlow_Portal_Auth' ) && ClientFlow_Portal_Auth::is_authenticated() ); ?>,
			pluginLogoUrl:   <?php echo wp_json_encode( esc_url( CLIENTFLOW_URL . 'assets/images/logo.svg' ) ); ?>
		};
	</script>

	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone template, wp_enqueue_script() cannot be used outside WP head. ?>
	<script src="<?php echo esc_url( $clientflow_script_url ); ?>?v=<?php echo esc_attr( $clientflow_script_ver ); ?>" defer></script>

</body>
</html>
