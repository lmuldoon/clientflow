<?php
/**
 * REST API: Entitlements Endpoints
 *
 * Namespace: /wp-json/clientflow/v1/
 *
 * Routes registered here:
 *   GET  /user/plan           — current user's plan + limits
 *   POST /user/can            — check feature access (React uses this to gate UI)
 *   GET  /user/usage          — current user's usage stats
 *   POST /user/log-usage      — log a feature usage event
 *   GET  /admin/usage-report  — aggregate AI cost report (admin only)
 *
 * All authenticated routes require a logged-in WordPress user.
 * The React admin app uses the WP nonce (X-WP-Nonce header).
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Route Registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', static function (): void {

	$ns = 'clientflow/v1';

	// ── GET /user/plan ────────────────────────────────────────────────────────
	register_rest_route( $ns, '/user/plan', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_get_user_plan',
		'permission_callback' => 'cf_rest_require_auth',
	] );

	// ── POST /user/can ────────────────────────────────────────────────────────
	register_rest_route( $ns, '/user/can', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_check_feature',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'feature' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'cf_rest_is_valid_feature',
				'description'       => 'Feature slug to check (e.g. "use_ai", "create_proposal").',
			],
			'options' => [
				'required'    => false,
				'type'        => 'object',
				'default'     => [],
				'description' => 'Optional context object passed to the entitlements engine.',
			],
		],
	] );

	// ── GET /user/usage ───────────────────────────────────────────────────────
	register_rest_route( $ns, '/user/usage', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_get_usage',
		'permission_callback' => 'cf_rest_require_auth',
	] );

	// ── POST /user/log-usage ──────────────────────────────────────────────────
	register_rest_route( $ns, '/user/log-usage', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_log_usage',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'feature' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'cf_rest_is_valid_feature',
			],
			'meta' => [
				'required'    => false,
				'type'        => 'object',
				'default'     => [],
				'description' => 'Extra metadata (proposal_id, action, tokens_input, tokens_output, cost_usd).',
			],
		],
	] );

	// ── GET /admin/usage-report ───────────────────────────────────────────────
	register_rest_route( $ns, '/admin/usage-report', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_usage_report',
		'permission_callback' => static function (): bool {
			return current_user_can( 'manage_options' );
		},
		'args'                => [
			'month' => [
				'required'          => false,
				'type'              => 'string',
				'default'           => gmdate( 'Y-m' ),
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => 'YYYY-MM month to report on. Defaults to current month.',
			],
		],
	] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Permission Callbacks
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Require an authenticated WordPress user.
 *
 * @return true|WP_Error
 */
function cf_rest_require_auth(): true|WP_Error {
	if ( ! is_user_logged_in() ) {
		return new WP_Error(
			'rest_not_logged_in',
			__( 'You must be logged in to use ClientFlow.', 'clientflow' ),
			[ 'status' => 401 ]
		);
	}

	return true;
}

/**
 * Validate that a feature slug is known to the entitlements system.
 *
 * @param string $feature
 *
 * @return bool
 */
function cf_rest_is_valid_feature( string $feature ): bool {
	static $known = [
		'create_proposal',
		'use_ai',
		'use_payments',
		'use_portal',
		'use_projects',
		'use_messaging',
		'use_files',
		'team_access',
	];

	return in_array( $feature, $known, true );
}

// ─────────────────────────────────────────────────────────────────────────────
// Route Handlers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * GET /clientflow/v1/user/plan
 *
 * Returns the current user's plan and per-feature limits.
 * Used by the React admin app to render the plan badge and feature gates.
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response
 */
function cf_rest_get_user_plan( WP_REST_Request $request ): WP_REST_Response {
	$user_id = get_current_user_id();
	$plan    = ClientFlow_Entitlements::get_user_plan( $user_id );

	return new WP_REST_Response(
		[
			'user_id' => $user_id,
			'plan'    => $plan,
			'limits'  => [
				'proposals'  => ClientFlow_Entitlements::get_feature_limit( $user_id, 'create_proposal' ),
				'ai_monthly' => ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' ),
				'team_seats' => ClientFlow_Entitlements::get_team_limit( $user_id ),
				'storage_mb' => 'agency' === $plan ? 1000 : 0,
			],
		],
		200
	);
}

/**
 * POST /clientflow/v1/user/can
 *
 * Check whether the current user may access a named feature.
 * The React frontend calls this to conditionally show/hide UI elements.
 *
 * Request body: { "feature": "use_ai", "options": {} }
 *
 * Response:
 * {
 *   "feature": "use_ai",
 *   "allowed": true,
 *   "tier": null,          // or "basic" / "full" for use_portal
 *   "plan": "pro"
 * }
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response
 */
function cf_rest_check_feature( WP_REST_Request $request ): WP_REST_Response {
	$user_id = get_current_user_id();
	$feature = (string) $request->get_param( 'feature' );
	$options = (array)  ( $request->get_param( 'options' ) ?? [] );

	$result = ClientFlow_Entitlements::can_user( $user_id, $feature, $options );

	return new WP_REST_Response(
		[
			'feature' => $feature,
			'allowed' => (bool) $result,
			'tier'    => is_string( $result ) ? $result : null,
			'plan'    => ClientFlow_Entitlements::get_user_plan( $user_id ),
		],
		200
	);
}

/**
 * GET /clientflow/v1/user/usage
 *
 * Returns the current user's live usage statistics and limits.
 * Drives the Plan & Usage admin dashboard.
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response
 */
function cf_rest_get_usage( WP_REST_Request $request ): WP_REST_Response {
	$user_id = get_current_user_id();

	return new WP_REST_Response(
		[
			'user_id' => $user_id,
			'plan'    => ClientFlow_Entitlements::get_user_plan( $user_id ),
			'usage'   => [
				'ai_requests_month' => ClientFlow_Entitlements::get_monthly_usage( $user_id, 'use_ai' ),
				'proposals_total'   => ClientFlow_Entitlements::get_total_count( $user_id, 'create_proposal' ),
				'proposals_month'   => ClientFlow_Entitlements::get_monthly_usage( $user_id, 'create_proposal' ),
				'storage_used_mb'   => ClientFlow_Entitlements::get_storage_used( $user_id ),
				'team_seats_used'   => ClientFlow_Entitlements::get_team_seats_used( $user_id ),
			],
			'limits'  => [
				'ai_monthly'  => ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' ),
				'proposals'   => ClientFlow_Entitlements::get_feature_limit( $user_id, 'create_proposal' ),
				'team_seats'  => ClientFlow_Entitlements::get_team_limit( $user_id ),
				'storage_mb'  => 1000,
			],
		],
		200
	);
}

/**
 * POST /clientflow/v1/user/log-usage
 *
 * Log a feature usage event for the current user.
 * Applies rate limiting and hard-stop limit checks for AI requests.
 *
 * Request body:
 * {
 *   "feature": "use_ai",
 *   "meta": {
 *     "proposal_id": 42,
 *     "action": "improve",
 *     "tokens_input": 120,
 *     "tokens_output": 80,
 *     "cost_usd": 0.012
 *   }
 * }
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response|WP_Error
 */
function cf_rest_log_usage( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$feature = (string) $request->get_param( 'feature' );
	$meta    = (array)  ( $request->get_param( 'meta' ) ?? [] );

	// AI-specific pre-flight checks.
	if ( 'use_ai' === $feature ) {
		// 1. Rate limit: max 1 request per 3 seconds.
		if ( ! ClientFlow_Entitlements::check_rate_limit( $user_id ) ) {
			return new WP_Error(
				'rate_limited',
				__( 'Please wait before making another request.', 'clientflow' ),
				[ 'status' => 429 ]
			);
		}

		// 2. Monthly limit hard stop.
		if ( ! ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) ) {
			return new WP_Error(
				'limit_exceeded',
				__( 'Monthly AI limit reached. Upgrade to Agency or wait until next month.', 'clientflow' ),
				[ 'status' => 429 ]
			);
		}
	}

	if ( ! ClientFlow_Entitlements::log_usage( $user_id, $feature, $meta ) ) {
		return new WP_Error(
			'log_failed',
			__( 'Usage could not be logged.', 'clientflow' ),
			[ 'status' => 500 ]
		);
	}

	return new WP_REST_Response( [ 'logged' => true ], 200 );
}

/**
 * GET /clientflow/v1/admin/usage-report
 *
 * Admin-only aggregate AI usage and cost report.
 * Answers operational questions like "how much did AI cost last month?"
 *
 * Query params:
 *   month (string, YYYY-MM) — defaults to current month
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response
 */
function cf_rest_usage_report( WP_REST_Request $request ): WP_REST_Response {
	global $wpdb;

	$month = (string) $request->get_param( 'month' );

	// Validate YYYY-MM format.
	if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$month = gmdate( 'Y-m' );
	}

	$total_cost = (float) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(SUM(cost_usd), 0)
			 FROM {$wpdb->prefix}clientflow_ai_usage_logs
			 WHERE month = %s",
			$month
		)
	);

	$total_requests = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$wpdb->prefix}clientflow_ai_usage_logs
			 WHERE month = %s",
			$month
		)
	);

	// Per-user breakdown (sorted by cost desc).
	$per_user = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
			     u.user_login,
			     um.plan,
			     COALESCE(SUM(al.cost_usd), 0) AS total_cost,
			     COUNT(al.id)                   AS total_requests
			 FROM {$wpdb->prefix}clientflow_ai_usage_logs al
			 JOIN {$wpdb->prefix}clientflow_user_meta um ON al.user_id = um.user_id
			 JOIN {$wpdb->users}                     u  ON um.user_id  = u.ID
			 WHERE al.month = %s
			 GROUP BY al.user_id
			 ORDER BY total_cost DESC",
			$month
		),
		ARRAY_A
	);

	// Budget alert flag: warn if monthly AI cost exceeds £100.
	$budget_alert = $total_cost > 100.00;

	return new WP_REST_Response(
		[
			'month'          => $month,
			'total_cost_usd' => round( $total_cost, 4 ),
			'total_requests' => $total_requests,
			'budget_alert'   => $budget_alert,
			'per_user'       => $per_user ?? [],
		],
		200
	);
}
