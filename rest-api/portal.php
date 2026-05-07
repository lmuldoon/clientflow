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

	// ── Authenticated: set password (first-time forced setup) ────────────────
	register_rest_route( $ns, '/portal/set-password', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_portal_set_password',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
		'args'                => [
			'password' => [
				'required'          => true,
				'sanitize_callback' => static fn( $v ) => (string) $v,
			],
		],
	] );

	// ── Authenticated: single payment receipt ───────────────────────────────
	register_rest_route( $ns, '/portal/receipt/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_portal_get_receipt',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
		'args'                => [
			'id' => [ 'type' => 'integer', 'required' => true ],
		],
	] );

	// ── Public: password login ───────────────────────────────────────────────
	register_rest_route( $ns, '/portal/login', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_portal_password_login',
		'permission_callback' => '__return_true',
		'args'                => [
			'email'    => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => static fn( $v ) => is_email( $v ),
			],
			'password' => [
				'required'          => true,
				'sanitize_callback' => static fn( $v ) => (string) $v,
			],
		],
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

	// First-time login: force password setup before entering the portal.
	$redirect = ClientFlow_Portal_Auth::has_set_password( $result->ID )
		? home_url( '/clientflow/dashboard' )
		: home_url( '/clientflow/set-password' );

	return new WP_REST_Response( [
		'success'      => true,
		'redirect_url' => $redirect,
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

	// For any payment that is still pending, check Stripe directly and write-through
	// if it has been paid. This covers cases where the webhook didn't fire (e.g. local dev).
	if ( ClientFlow_Stripe::is_configured() ) {
		$resolved = false;
		foreach ( $payments as $pm ) {
			if ( in_array( $pm['status'], [ 'pending', 'processing' ], true ) && ! empty( $pm['stripe_session_id'] ) ) {
				$stripe_session = ClientFlow_Stripe::retrieve_session( $pm['stripe_session_id'] );
				if ( ! is_wp_error( $stripe_session ) && 'paid' === ( $stripe_session['payment_status'] ?? '' ) ) {
					cf_handle_checkout_complete( $stripe_session );
					$resolved = true;
				}
			}
		}
		// Re-fetch if any records were updated.
		if ( $resolved ) {
			$payments = ClientFlow_Portal_Data::get_payments( get_current_user_id() );
		}
	}

	return new WP_REST_Response( $payments, 200 );
}

/**
 * POST /portal/logout
 */
function cf_portal_logout(): WP_REST_Response {
	wp_logout();

	return new WP_REST_Response( [
		'success'      => true,
		'redirect_url' => home_url( '/clientflow/login' ),
	], 200 );
}

/**
 * Validate a portal password against all requirements.
 * Returns an array of failed rule keys (empty = valid).
 */
function cf_validate_portal_password( string $password ): array {
	$errors = [];
	if ( strlen( $password ) < 8 )                     $errors[] = 'min_length';
	if ( ! preg_match( '/[A-Z]/', $password ) )        $errors[] = 'uppercase';
	if ( ! preg_match( '/[a-z]/', $password ) )        $errors[] = 'lowercase';
	if ( ! preg_match( '/[0-9]/', $password ) )        $errors[] = 'number';
	if ( ! preg_match( '/[^A-Za-z0-9]/', $password ) ) $errors[] = 'special';
	return $errors;
}

/**
 * POST /portal/set-password
 */
function cf_portal_set_password( WP_REST_Request $request ): WP_REST_Response {
	$user_id  = get_current_user_id();
	$password = $request->get_param( 'password' );

	// If the client has already set a password, require verification of the current one.
	if ( ClientFlow_Portal_Auth::has_set_password( $user_id ) ) {
		$current = (string) $request->get_param( 'current_password' );
		$user    = get_user_by( 'ID', $user_id );
		if ( ! $user || ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Current password is incorrect.', 'clientflow' ),
				'errors'  => [ 'current_password' ],
			], 401 );
		}
	}

	$errors = cf_validate_portal_password( $password );

	if ( $errors ) {
		return new WP_REST_Response( [
			'success' => false,
			'errors'  => $errors,
			'message' => __( 'Password does not meet the requirements.', 'clientflow' ),
		], 422 );
	}

	wp_set_password( $password, $user_id );
	ClientFlow_Portal_Auth::mark_password_set( $user_id );
	// wp_set_password() destroys all sessions — re-issue the auth cookie.
	wp_set_auth_cookie( $user_id, true );

	return new WP_REST_Response( [
		'success'      => true,
		'redirect_url' => home_url( '/clientflow/dashboard' ),
	], 200 );
}

/**
 * POST /portal/login
 */
function cf_portal_password_login( WP_REST_Request $request ): WP_REST_Response {
	$email    = $request->get_param( 'email' );
	$password = $request->get_param( 'password' );
	$user     = get_user_by( 'email', $email );

	// Generic error — never reveal whether the email exists.
	$invalid = new WP_REST_Response( [
		'success' => false,
		'message' => __( 'Invalid email or password.', 'clientflow' ),
	], 401 );

	if ( ! $user || ! in_array( 'clientflow_client', (array) $user->roles, true ) ) {
		return $invalid;
	}

	// Only allow password login after the client has set one.
	if ( ! ClientFlow_Portal_Auth::has_set_password( $user->ID ) ) {
		return $invalid;
	}

	if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
		return $invalid;
	}

	wp_set_auth_cookie( $user->ID, true );
	wp_set_current_user( $user->ID );

	return new WP_REST_Response( [
		'success'      => true,
		'redirect_url' => home_url( '/clientflow/dashboard' ),
	], 200 );
}

/**
 * GET /portal/receipt/{id}
 *
 * Returns full receipt data for a single completed payment.
 * Scoped to the authenticated client — returns 404 if the payment
 * doesn't belong to them.
 */
function cf_portal_get_receipt( WP_REST_Request $request ): WP_REST_Response {
	global $wpdb;

	$payment_id = (int) $request->get_param( 'id' );
	$user       = wp_get_current_user();

	$pm = $wpdb->prefix . 'clientflow_payments';
	$pt = $wpdb->prefix . 'clientflow_proposals';
	$ct = $wpdb->prefix . 'clientflow_clients';

	$payment = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT pay.id, pay.proposal_id, pay.amount, pay.currency, pay.deposit_pct,
			        pay.stripe_payment_intent_id, pay.completed_at, pay.created_at,
			        pr.title AS proposal_title, pr.token AS proposal_token, pr.total_amount
			 FROM   {$pm} AS pay
			 JOIN   {$pt} AS pr ON pr.id  = pay.proposal_id
			 JOIN   {$ct} AS c  ON c.id   = pr.client_id
			 WHERE  pay.id = %d
			   AND  c.wp_user_id = %d
			   AND  pay.status = 'completed'",
			$payment_id,
			$user->ID
		),
		ARRAY_A
	);

	if ( ! $payment ) {
		return new WP_REST_Response( [ 'success' => false ], 404 );
	}

	// Determine payment type label.
	$prior_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$pm}
			 WHERE  proposal_id = %d
			   AND  status = 'completed'
			   AND  created_at < %s",
			(int) $payment['proposal_id'],
			$payment['created_at']
		)
	);

	if ( (int) $payment['deposit_pct'] < 100 ) {
		$payment_type = 'Deposit';
	} elseif ( $prior_count > 0 ) {
		$payment_type = 'Remaining balance';
	} else {
		$payment_type = 'Full payment';
	}

	return new WP_REST_Response( [
		'success'       => true,
		'payment'       => $payment,
		'payment_type'  => $payment_type,
		'client_name'   => $user->display_name,
		'client_email'  => $user->user_email,
		'business_name' => get_option( 'clientflow_business_name' ) ?: get_option( 'blogname' ),
		'business_logo' => get_option( 'clientflow_business_logo' ),
	], 200 );
}
