<?php
/**
 * REST API endpoints for the client portal.
 *
 * Public endpoints (no auth required):
 *   POST /clientflow/v1/portal/send-magic-link
 *   POST /clientflow/v1/portal/verify
 *
 * Authenticated endpoints (clientflow_client role):
 *   GET  /clientflow/v1/portal/me
 *   GET  /clientflow/v1/portal/proposals
 *   GET  /clientflow/v1/portal/payments
 *   POST /clientflow/v1/portal/logout
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'cf_register_portal_routes' );

function cf_register_portal_routes(): void {
	$ns = 'clientflow/v1';

	// ── Public: request a magic link ─────────────────────────────────────────
	register_rest_route( $ns, '/portal/send-magic-link', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_portal_send_magic_link',
		'permission_callback' => '__return_true',
		'args'                => [
			'email' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => fn( $v ) => is_email( $v ),
			],
		],
	] );

	// ── Public: verify token & set auth cookie ───────────────────────────────
	register_rest_route( $ns, '/portal/verify', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_portal_verify',
		'permission_callback' => '__return_true',
		'args'                => [
			'token' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	] );

	// ── Authenticated: current client profile ────────────────────────────────
	register_rest_route( $ns, '/portal/me', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_portal_me',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
	] );

	// ── Authenticated: client's proposals ───────────────────────────────────
	register_rest_route( $ns, '/portal/proposals', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_portal_proposals',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
	] );

	// ── Authenticated: client's payments ────────────────────────────────────
	register_rest_route( $ns, '/portal/payments', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_portal_payments',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
	] );

	// ── Authenticated: logout ────────────────────────────────────────────────
	register_rest_route( $ns, '/portal/logout', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_portal_logout',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
	] );
}

// =============================================================================
// Handlers
// =============================================================================

/**
 * POST /portal/send-magic-link
 *
 * Accepts: { email }
 * Always returns a generic success response to prevent email enumeration.
 */
function cf_portal_send_magic_link( WP_REST_Request $request ): WP_REST_Response {
	$email = $request->get_param( 'email' );

	// We get-or-create silently; if the email isn't associated with any
	// proposals we still return success (don't leak whether an account exists).
	$user = ClientFlow_Portal_Auth::get_or_create_wp_user( $email );

	if ( ! is_wp_error( $user ) ) {
		$raw_token = ClientFlow_Portal_Auth::generate_magic_token( $user->ID );
		ClientFlow_Portal_Auth::send_magic_link_email( $user, $raw_token );
	}

	return new WP_REST_Response( [
		'success' => true,
		'message' => 'If an account exists for that email, a login link has been sent.',
	], 200 );
}

/**
 * POST /portal/verify
 *
 * Accepts: { token }
 * On success: sets WP auth cookie + returns redirect URL.
 */
function cf_portal_verify( WP_REST_Request $request ): WP_REST_Response {
	$token = $request->get_param( 'token' );

	$result = ClientFlow_Portal_Auth::verify_magic_token( $token );

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response( [
			'success' => false,
			'message' => $result->get_error_message(),
			'code'    => $result->get_error_code(),
		], 401 );
	}

	return new WP_REST_Response( [
		'success'      => true,
		'redirect_url' => home_url( '/portal/dashboard' ),
	], 200 );
}

/**
 * GET /portal/me
 */
function cf_portal_me(): WP_REST_Response {
	$client = ClientFlow_Portal_Data::get_client( get_current_user_id() );

	return new WP_REST_Response( $client, 200 );
}

/**
 * GET /portal/proposals
 */
function cf_portal_proposals(): WP_REST_Response {
	$proposals = ClientFlow_Portal_Data::get_proposals( get_current_user_id() );

	return new WP_REST_Response( $proposals, 200 );
}

/**
 * GET /portal/payments
 */
function cf_portal_payments(): WP_REST_Response {
	$payments = ClientFlow_Portal_Data::get_payments( get_current_user_id() );

	return new WP_REST_Response( $payments, 200 );
}

/**
 * POST /portal/logout
 */
function cf_portal_logout(): WP_REST_Response {
	wp_logout();

	return new WP_REST_Response( [
		'success'      => true,
		'redirect_url' => home_url( '/portal/login' ),
	], 200 );
}
