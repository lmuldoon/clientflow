<?php
/**
 * Standalone portal page template.
 *
 * Renders a bare HTML page (no theme) that boots the portal React bundle.
 * Injects window.cfPortalData with everything the JS needs.
 *
 * Variables available from portal/routing.php:
 *   $page — one of 'login' | 'verify' | 'dashboard' | 'proposals' | 'payments'
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) exit;

// Re-read the page from query var (routing.php already validated it).
$cf_portal_page = get_query_var( 'cf_portal_page', 'login' );

// Client data for authenticated pages.
$cf_client_data = null;
if ( ClientFlow_Portal_Auth::is_authenticated() ) {
	$cf_client_data = ClientFlow_Portal_Data::get_client( get_current_user_id() );
}

// Business identity.
$cf_business_name = get_option( 'blogname', '' );
$cf_business_logo = get_option( 'clientflow_business_logo', '' );

// For the verify page, pass the raw token from the query string so the
// PortalVerify component can fire the verify API immediately on mount.
$cf_verify_token = '';
if ( 'verify' === $cf_portal_page ) {
	$cf_verify_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
}

// Nonce for WP REST API calls.
$cf_nonce = wp_create_nonce( 'wp_rest' );

// Asset manifest.
$asset_file = CLIENTFLOW_DIR . 'build/portal.asset.php';
$asset      = file_exists( $asset_file ) ? require $asset_file : [ 'version' => CLIENTFLOW_VERSION, 'dependencies' => [] ];
$ver        = $asset['version'];
$bundle_url = plugins_url( 'build/portal.js', CLIENTFLOW_DIR . 'clientflow.php' );

// Enqueue portal bundle with its dependencies so WordPress loads wp-element,
// react, react-jsx-runtime etc. before the bundle runs.
$deps = array_unique( array_merge( $asset['dependencies'], [ 'wp-element' ] ) );
wp_enqueue_script( 'cf-portal', $bundle_url, $deps, $ver, true );

// Page title.
$page_titles = [
	'login'     => 'Login',
	'verify'    => 'Verifying…',
	'dashboard' => 'Dashboard',
	'proposals' => 'Proposals',
	'projects'  => 'Projects',
	'payments'  => 'Payments',
];
$page_title = $page_titles[ $cf_portal_page ] ?? 'Portal';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $page_title . ' — ' . $cf_business_name ); ?></title>
<meta name="robots" content="noindex, nofollow">
<?php wp_head(); ?>
<style>
/* Minimal reset before the bundle injects its own styles */
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; background: #F8F7F5; }
#cf-portal-root { min-height: 100vh; }
</style>
</head>
<body>
<div id="cf-portal-root"></div>

<script>
window.cfPortalData = <?php echo wp_json_encode( [
	'page'            => $cf_portal_page,
	'apiUrl'          => esc_url_raw( rest_url( 'clientflow/v1' ) ),
	'nonce'           => $cf_nonce,
	'isAuthenticated' => ClientFlow_Portal_Auth::is_authenticated(),
	'clientData'      => $cf_client_data,
	'businessName'    => $cf_business_name,
	'businessLogo'    => $cf_business_logo,
	'verifyToken'     => $cf_verify_token,
] ); ?>;
</script>

<?php wp_footer(); ?>
</body>
</html>
