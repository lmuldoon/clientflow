<?php
/**
 * REST API: Client-Facing Proposal Endpoints
 *
 * Namespace: /wp-json/clientflow/v1/
 *
 * Routes:
 *   GET  /client/proposals/{token}         — fetch proposal by public token
 *   POST /client/proposals/{token}/view    — log a view event
 *   POST /client/proposals/{token}/accept  — client accepts proposal
 *   POST /client/proposals/{token}/decline — client declines proposal
 *
 * These routes require NO WordPress authentication.
 * The public token acts as the credential — treat it like a signed URL.
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', static function (): void {
	// Load class-proposal-client.php if not already loaded.
	$path = CLIENTFLOW_DIR . 'modules/proposals/class-proposal-client.php';
	if ( ! class_exists( 'ClientFlow_Proposal_Client' ) && file_exists( $path ) ) {
		require_once $path;
	}

	$ns     = 'clientflow/v1';
	$token  = '(?P<token>[a-zA-Z0-9\-]+)';

	// ── GET /client/proposals/{token} ────────────────────────────────────────
	register_rest_route( $ns, "/client/proposals/{$token}", [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_client_get_proposal',
		'permission_callback' => '__return_true', // Token is the credential.
		'args'                => [
			'token' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );

	// ── POST /client/proposals/{token}/view ──────────────────────────────────
	register_rest_route( $ns, "/client/proposals/{$token}/view", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_client_track_view',
		'permission_callback' => '__return_true',
		'args'                => [
			'token' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );

	// ── POST /client/proposals/{token}/accept ────────────────────────────────
	register_rest_route( $ns, "/client/proposals/{$token}/accept", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_client_accept_proposal',
		'permission_callback' => '__return_true',
		'args'                => [
			'token' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );

	// ── POST /client/proposals/{token}/decline ───────────────────────────────
	register_rest_route( $ns, "/client/proposals/{$token}/decline", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_client_decline_proposal',
		'permission_callback' => '__return_true',
		'args'                => [
			'token'  => [ 'type' => 'string', 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'reason' => [ 'type' => 'string', 'required' => false, 'default' => '',     'sanitize_callback' => 'sanitize_textarea_field' ],
		],
	] );

	// ── POST /client/proposals/{token}/request-change ────────────────────────
	register_rest_route( $ns, "/client/proposals/{$token}/request-change", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_client_request_change',
		'permission_callback' => '__return_true',
		'args'                => [
			'token' => [ 'type' => 'string', 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'note'  => [ 'type' => 'string', 'required' => false, 'default' => '',     'sanitize_callback' => 'sanitize_textarea_field' ],
		],
	] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Route handlers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * GET /clientflow/v1/client/proposals/{token}
 *
 * Returns the client-safe proposal data (no owner_id, no token field).
 */
function cf_rest_client_get_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$token  = (string) $request->get_param( 'token' );
	$result = ClientFlow_Proposal_Client::get_by_token( $token );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// Augment with payment status so the client view can hide the payment button
	// when a completed payment already exists for this proposal.
	$base       = CLIENTFLOW_DIR . 'modules/payments/class-payment.php';
	$has_paid   = false;
	if ( ! class_exists( 'ClientFlow_Payment' ) && file_exists( $base ) ) {
		require_once $base;
	}
	$remaining_balance = 0.0;
	if ( class_exists( 'ClientFlow_Payment' ) ) {
		$has_paid  = ClientFlow_Payment::has_completed_payment( (int) $result['id'] );
		$payments  = ClientFlow_Payment::get_for_proposal( (int) $result['id'] );
		$total_paid = array_reduce( $payments, static function ( float $carry, array $pm ): float {
			return $carry + ( 'completed' === $pm['status'] ? (float) $pm['amount'] : 0.0 );
		}, 0.0 );
		$remaining_balance = max( 0.0, (float) ( $result['total_amount'] ?? 0 ) - $total_paid );
	}
	$result['has_paid']          = $has_paid;
	$result['remaining_balance'] = $remaining_balance;

	// Prevent the payment button appearing when there is nothing to charge.
	if ( (float) ( $result['total_amount'] ?? 0 ) <= 0 ) {
		$result['payment_enabled'] = false;
	}

	return new WP_REST_Response( [ 'proposal' => $result ], 200 );
}

/**
 * POST /clientflow/v1/client/proposals/{token}/view
 *
 * Logs a view event and transitions sent → viewed on first open.
 * Returns 200 always (best-effort tracking — clients should not see errors here).
 */
function cf_rest_client_track_view( WP_REST_Request $request ): WP_REST_Response {
	$token      = (string) $request->get_param( 'token' );
	$ip         = sanitize_text_field( $_SERVER['REMOTE_ADDR']     ?? '' );
	$user_agent = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );

	ClientFlow_Proposal_Client::track_view( $token, $ip, $user_agent );

	return new WP_REST_Response( [ 'tracked' => true ], 200 );
}

/**
 * POST /clientflow/v1/client/proposals/{token}/accept
 *
 * Client accepts the proposal.
 */
function cf_rest_client_accept_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$token  = (string) $request->get_param( 'token' );
	$result = ClientFlow_Proposal_Client::accept( $token );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'proposal' => $result ], 200 );
}

/**
 * POST /clientflow/v1/client/proposals/{token}/decline
 *
 * Client declines the proposal.
 */
function cf_rest_client_decline_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$token  = (string) $request->get_param( 'token' );
	$reason = (string) $request->get_param( 'reason' );
	$result = ClientFlow_Proposal_Client::decline( $token, $reason );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'proposal' => $result ], 200 );
}

/**
 * POST /clientflow/v1/client/proposals/{token}/request-change
 *
 * Client requests changes — moves proposal to revision_requested status.
 */
function cf_rest_client_request_change( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$token  = (string) $request->get_param( 'token' );
	$note   = (string) $request->get_param( 'note' );
	$result = ClientFlow_Proposal_Client::request_change( $token, $note );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'proposal' => $result ], 200 );
}
