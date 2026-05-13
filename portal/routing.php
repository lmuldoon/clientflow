<?php
/**
 * Portal URL routing.
 *
 * Registers rewrite rules for:
 *   /portal                → login (redirect)
 *   /portal/login          → PortalLogin component
 *   /portal/verify         → PortalVerify component  (token in query string)
 *   /portal/dashboard      → PortalDashboard (auth-gated)
 *   /portal/proposals      → PortalProposals (auth-gated)
 *   /portal/payments       → PortalPayments  (auth-gated)
 *
 * All portal pages bypass the active theme and render portal/template.php
 * as a standalone HTML page.
 */

declare( strict_types = 1 );
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table queries; all table variables use ->prefix with trusted constants, not user input.

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Register query vars ───────────────────────────────────────────────────────

add_filter( 'query_vars', function( array $vars ): array {
	$vars[] = 'clientflow_portal_page';
	return $vars;
} );

// ── Rewrite rules ─────────────────────────────────────────────────────────────

add_action( 'init', 'clientflow_add_portal_rewrite_rules' );

function clientflow_add_portal_rewrite_rules(): void {
	// /portal → redirect to /portal/login (handled in template_redirect below).
	add_rewrite_rule(
		'^clientflow/?$',
		'index.php?clientflow_portal_page=login',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/login/?$',
		'index.php?clientflow_portal_page=login',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/verify/?$',
		'index.php?clientflow_portal_page=verify',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/dashboard/?$',
		'index.php?clientflow_portal_page=dashboard',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/proposals/?$',
		'index.php?clientflow_portal_page=proposals',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/payments/?$',
		'index.php?clientflow_portal_page=payments',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/projects/?$',
		'index.php?clientflow_portal_page=projects',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/set-password/?$',
		'index.php?clientflow_portal_page=set-password',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/receipt/?$',
		'index.php?clientflow_portal_page=receipt',
		'top'
	);

	add_rewrite_rule(
		'^clientflow/logout/?$',
		'index.php?clientflow_portal_page=logout',
		'top'
	);
}

// ── Template redirect ─────────────────────────────────────────────────────────

add_action( 'template_redirect', 'clientflow_portal_template_redirect' );

function clientflow_portal_template_redirect(): void {
	$page = get_query_var( 'clientflow_portal_page' );

	if ( ! $page ) {
		return;
	}

	$authenticated_pages = [ 'dashboard', 'proposals', 'payments', 'projects', 'receipt' ];
	$public_pages        = [ 'login', 'verify' ];

	// /portal/set-password — auth required; bypass if password already set.
	if ( 'set-password' === $page ) {
		if ( ! ClientFlow_Portal_Auth::is_authenticated() ) {
			wp_safe_redirect( home_url( '/clientflow/login' ) );
			exit;
		}
		if ( ClientFlow_Portal_Auth::has_set_password( get_current_user_id() ) ) {
			wp_safe_redirect( home_url( '/clientflow/dashboard' ) );
			exit;
		}
		require CLIENTFLOW_DIR . 'portal/template.php';
		exit;
	}

	// /portal/logout — clear session and redirect to login.
	if ( 'logout' === $page ) {
		wp_logout();
		wp_safe_redirect( home_url( '/clientflow/login' ) );
		exit;
	}

	if ( in_array( $page, $authenticated_pages, true ) ) {
		// Auth gate: unauthenticated clients → login.
		if ( ! ClientFlow_Portal_Auth::is_authenticated() ) {
			wp_safe_redirect( home_url( '/clientflow/login' ) );
			exit;
		}
	} elseif ( in_array( $page, $public_pages, true ) ) {
		// Already logged-in clients hitting /login → dashboard.
		if ( 'login' === $page && ClientFlow_Portal_Auth::is_authenticated() ) {
			wp_safe_redirect( home_url( '/clientflow/dashboard' ) );
			exit;
		}
	} else {
		// Unknown portal page → 404.
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return;
	}

	// Render standalone portal page (bypasses theme).
	require CLIENTFLOW_DIR . 'portal/template.php';
	exit;
}
