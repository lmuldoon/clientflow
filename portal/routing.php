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

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Register query vars ───────────────────────────────────────────────────────

add_filter( 'query_vars', function( array $vars ): array {
	$vars[] = 'cf_portal_page';
	return $vars;
} );

// ── Rewrite rules ─────────────────────────────────────────────────────────────

add_action( 'init', 'cf_add_portal_rewrite_rules' );

function cf_add_portal_rewrite_rules(): void {
	// /portal → redirect to /portal/login (handled in template_redirect below).
	add_rewrite_rule(
		'^portal/?$',
		'index.php?cf_portal_page=login',
		'top'
	);

	add_rewrite_rule(
		'^portal/login/?$',
		'index.php?cf_portal_page=login',
		'top'
	);

	add_rewrite_rule(
		'^portal/verify/?$',
		'index.php?cf_portal_page=verify',
		'top'
	);

	add_rewrite_rule(
		'^portal/dashboard/?$',
		'index.php?cf_portal_page=dashboard',
		'top'
	);

	add_rewrite_rule(
		'^portal/proposals/?$',
		'index.php?cf_portal_page=proposals',
		'top'
	);

	add_rewrite_rule(
		'^portal/payments/?$',
		'index.php?cf_portal_page=payments',
		'top'
	);

	add_rewrite_rule(
		'^portal/projects/?$',
		'index.php?cf_portal_page=projects',
		'top'
	);

	add_rewrite_rule(
		'^portal/logout/?$',
		'index.php?cf_portal_page=logout',
		'top'
	);
}

// ── Template redirect ─────────────────────────────────────────────────────────

add_action( 'template_redirect', 'cf_portal_template_redirect' );

function cf_portal_template_redirect(): void {
	$page = get_query_var( 'cf_portal_page' );

	if ( ! $page ) {
		return;
	}

	$authenticated_pages = [ 'dashboard', 'proposals', 'payments', 'projects' ];
	$public_pages        = [ 'login', 'verify' ];

	// /portal/logout — clear session and redirect to login.
	if ( 'logout' === $page ) {
		wp_logout();
		wp_safe_redirect( home_url( '/portal/login' ) );
		exit;
	}

	if ( in_array( $page, $authenticated_pages, true ) ) {
		// Auth gate: unauthenticated clients → login.
		if ( ! ClientFlow_Portal_Auth::is_authenticated() ) {
			wp_safe_redirect( home_url( '/portal/login' ) );
			exit;
		}
	} elseif ( in_array( $page, $public_pages, true ) ) {
		// Already logged-in clients hitting /login → dashboard.
		if ( 'login' === $page && ClientFlow_Portal_Auth::is_authenticated() ) {
			wp_safe_redirect( home_url( '/portal/dashboard' ) );
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
