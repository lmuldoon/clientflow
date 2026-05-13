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
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-scope variables use clientflow_ prefix; DB queries use trusted table constants.

if ( ! defined( 'ABSPATH' ) ) exit;

// Re-read the page from query var (routing.php already validated it).
$clientflow_portal_page = get_query_var( 'clientflow_portal_page', 'login' );

// Client data for authenticated pages.
$clientflow_client_data = null;
if ( ClientFlow_Portal_Auth::is_authenticated() ) {
	$clientflow_client_data = ClientFlow_Portal_Data::get_client( get_current_user_id() );
}

// Business identity.
$clientflow_business_name = get_option( 'blogname', '' );
$clientflow_business_logo = get_option( 'clientflow_logo_url', '' );
$clientflow_brand_color   = get_option( 'clientflow_brand_color', '#6366F1' );

// For the verify page, pass the raw token from the query string so the
// PortalVerify component can fire the verify API immediately on mount.
$clientflow_verify_token = '';
if ( 'verify' === $clientflow_portal_page ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing token, no state change occurs here.
	$clientflow_verify_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
}

// Determine if the admin owner has the agency plan (projects are agency-only).
$clientflow_has_projects = false;
if ( ClientFlow_Portal_Auth::is_authenticated() ) {
	global $wpdb;
	$clientflow_owner_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.owner_id
			 FROM {$wpdb->prefix}clientflow_proposals p
			 INNER JOIN {$wpdb->prefix}clientflow_clients c ON c.id = p.client_id
			 INNER JOIN {$wpdb->users} u ON u.user_email = c.email
			 WHERE u.ID = %d
			 LIMIT 1",
			get_current_user_id()
		)
	);
	if ( $clientflow_owner_id ) {
		$clientflow_has_projects = (bool) ClientFlow_Entitlements::can_user( $clientflow_owner_id, 'use_projects' );
	}
}

// Nonce for WP REST API calls.
$clientflow_nonce = wp_create_nonce( 'wp_rest' );

// Asset manifest.
$clientflow_asset_file = CLIENTFLOW_DIR . 'build/portal.asset.php';
$clientflow_asset      = file_exists( $clientflow_asset_file ) ? require $clientflow_asset_file : [ 'version' => CLIENTFLOW_VERSION, 'dependencies' => [] ];
$clientflow_ver        = $clientflow_asset['version'];
$clientflow_bundle_url = plugins_url( 'build/portal.js', CLIENTFLOW_DIR . 'clientflow.php' );

// Enqueue portal bundle with its dependencies so WordPress loads wp-element,
// react, react-jsx-runtime etc. before the bundle runs.
$clientflow_deps = array_unique( array_merge( $clientflow_asset['dependencies'], [ 'wp-element' ] ) );
wp_enqueue_script( 'cf-portal', $clientflow_bundle_url, $clientflow_deps, $clientflow_ver, true );

// Page title.
$clientflow_page_titles = [
	'login'     => 'Login',
	'verify'    => 'Verifying…',
	'dashboard' => 'Dashboard',
	'proposals' => 'Proposals',
	'projects'  => 'Projects',
	'payments'  => 'Payments',
];
$clientflow_page_title = $clientflow_page_titles[ $clientflow_portal_page ] ?? 'Portal';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $clientflow_page_title . ' — ' . $clientflow_business_name ); ?></title>
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
	'page'            => $clientflow_portal_page,
	'apiUrl'          => esc_url_raw( rest_url( 'clientflow/v1' ) ),
	'nonce'           => $clientflow_nonce,
	'isAuthenticated' => ClientFlow_Portal_Auth::is_authenticated(),
	'clientData'      => $clientflow_client_data,
	'businessName'    => $clientflow_business_name,
	'businessLogo'    => $clientflow_business_logo,
	'brandColor'      => $clientflow_brand_color,
	'verifyToken'     => $clientflow_verify_token,
	'pluginUrl'       => CLIENTFLOW_URL,
	'hasProjects'     => $clientflow_has_projects,
] ); ?>;
</script>

<?php wp_footer(); ?>
</body>
</html>
