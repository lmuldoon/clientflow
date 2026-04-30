<?php
/**
 * ClientFlow Client Proposal Template
 *
 * Standalone HTML page served when a client visits /proposals/{token}.
 * Completely bypasses the active WordPress theme — this is a self-contained
 * document viewer page.
 *
 * Variables injected by client-routing.php:
 *   $cf_proposal_token  string  Sanitised UUID token from the URL.
 *
 * @package ClientFlow
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure we have a valid token from the routing layer.
if ( empty( $cf_proposal_token ) ) {
	wp_die(
		esc_html__( 'Invalid proposal link.', 'clientflow' ),
		esc_html__( 'Not Found', 'clientflow' ),
		[ 'response' => 404 ]
	);
}

// Payment result page ('success' | 'cancel' | '').
$cf_payment_result = $cf_payment_result ?? '';
$cf_session_id     = $cf_session_id     ?? '';

// ── Client email (for success page personalisation) ───────────────────────────
$client_email = '';
if ( ! empty( $cf_payment_result ) && class_exists( 'ClientFlow_Proposal_Client' ) ) {
	$_proposal_row = ClientFlow_Proposal_Client::get_by_token( $cf_proposal_token );
	if ( ! is_wp_error( $_proposal_row ) ) {
		$client_email = $_proposal_row['client_email'] ?? '';
	}
}

// ── Business branding ─────────────────────────────────────────────────────────
$business_name = get_bloginfo( 'name' );
$business_logo = '';

// Use the site's custom logo if set (wp_get_attachment_image_src returns array|false).
$logo_id = get_theme_mod( 'custom_logo' );
if ( $logo_id ) {
	$logo_src    = wp_get_attachment_image_src( $logo_id, 'medium' );
	$business_logo = $logo_src ? esc_url( $logo_src[0] ) : '';
}

// ── Asset URLs ────────────────────────────────────────────────────────────────
$build_dir     = CLIENTFLOW_DIR . 'build/';
$build_url     = CLIENTFLOW_URL . 'build/';
$asset_file    = $build_dir . 'client.asset.php';
$asset         = file_exists( $asset_file ) ? require $asset_file : [ 'version' => CLIENTFLOW_VERSION ];
$script_ver    = $asset['version'] ?? CLIENTFLOW_VERSION;

$script_url    = $build_url . 'client.js';
$style_url     = $build_url . 'client.css';
$has_css       = file_exists( $build_dir . 'client.css' );

// The client bundle must exist.
if ( ! file_exists( $build_dir . 'client.js' ) ) {
	wp_die(
		esc_html__( 'Proposal viewer assets not built. Please contact the site administrator.', 'clientflow' ),
		esc_html__( 'Configuration Error', 'clientflow' ),
		[ 'response' => 500 ]
	);
}

// ── Favicon ───────────────────────────────────────────────────────────────────
$favicon_url = get_site_icon_url( 32 );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">

	<title><?php echo esc_html( $business_name ); ?> &mdash; <?php esc_html_e( 'Proposal', 'clientflow' ); ?></title>

	<?php if ( $favicon_url ) : ?>
		<link rel="icon" href="<?php echo esc_url( $favicon_url ); ?>">
	<?php endif; ?>

	<?php if ( $has_css ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( $style_url ); ?>?v=<?php echo esc_attr( $script_ver ); ?>">
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
	$script_deps = $asset['dependencies'] ?? [];
	// Always ensure wp-element is available (provides wp.element.render).
	if ( ! in_array( 'wp-element', $script_deps, true ) ) {
		$script_deps[] = 'wp-element';
	}
	wp_print_scripts( $script_deps );
	?>

	<script>
		window.cfClientData = {
			apiUrl:        <?php echo wp_json_encode( rest_url( 'clientflow/v1/' ) ); ?>,
			token:         <?php echo wp_json_encode( $cf_proposal_token ); ?>,
			businessName:  <?php echo wp_json_encode( $business_name ); ?>,
			businessLogo:  <?php echo wp_json_encode( $business_logo ); ?>,
			nonce:         <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
			pageType:      <?php echo wp_json_encode( $cf_payment_result ?: 'proposal' ); ?>,
			sessionId:     <?php echo wp_json_encode( $cf_session_id ); ?>,
			clientEmail:   <?php echo wp_json_encode( $client_email ); ?>
		};
	</script>

	<script src="<?php echo esc_url( $script_url ); ?>?v=<?php echo esc_attr( $script_ver ); ?>" defer></script>

</body>
</html>
