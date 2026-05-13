<?php
/**
 * Webhooks REST API
 *
 * GET    /clientflow/v1/webhooks          — list webhooks for owner (with last 3 log entries)
 * POST   /clientflow/v1/webhooks          — create a webhook
 * PATCH  /clientflow/v1/webhooks/{id}     — update url / events / enabled
 * DELETE /clientflow/v1/webhooks/{id}     — delete webhook + its logs
 * POST   /clientflow/v1/webhooks/{id}/test — send a test ping immediately
 *
 * All routes require a logged-in WordPress session (clientflow_rest_require_auth).
 * Write operations are additionally gated to Pro/Agency plan (use_webhooks).
 *
 * @package ClientFlow\Webhooks
 * @since   0.1.2
 */

declare( strict_types=1 );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- All table variables use $wpdb->prefix with hardcoded slugs, not user input.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ────────────────────────────────────────────────────────────────

const CF_WEBHOOK_EVENTS = [
	'proposal.sent',
	'proposal.accepted',
	'proposal.declined',
	'proposal.revision_requested',
	'payment.completed',
	'project.created',
	'project.completed',
];

// ── Route registration ────────────────────────────────────────────────────────

add_action( 'rest_api_init', static function (): void {
	$ns = 'clientflow/v1';

	register_rest_route( $ns, '/webhooks', [
		[
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'clientflow_rest_list_webhooks',
			'permission_callback' => 'clientflow_rest_require_auth',
		],
		[
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'clientflow_rest_create_webhook',
			'permission_callback' => 'clientflow_rest_require_auth',
			'args'                => clientflow_webhook_args(),
		],
	] );

	register_rest_route( $ns, '/webhooks/(?P<id>\d+)', [
		[
			'methods'             => 'PATCH',
			'callback'            => 'clientflow_rest_update_webhook',
			'permission_callback' => 'clientflow_rest_require_auth',
			'args'                => array_merge(
				[ 'id' => [ 'type' => 'integer', 'required' => true ] ],
				clientflow_webhook_args( false )
			),
		],
		[
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => 'clientflow_rest_delete_webhook',
			'permission_callback' => 'clientflow_rest_require_auth',
			'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
		],
	] );

	register_rest_route( $ns, '/webhooks/(?P<id>\d+)/test', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'clientflow_rest_test_webhook',
		'permission_callback' => 'clientflow_rest_require_auth',
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );
} );

// ── Shared arg definitions ────────────────────────────────────────────────────

function clientflow_webhook_args( bool $required = true ): array {
	return [
		'url'     => [
			'type'              => 'string',
			'required'          => $required,
			'sanitize_callback' => 'esc_url_raw',
		],
		'events'  => [
			'type'     => 'array',
			'required' => $required,
			'items'    => [ 'type' => 'string' ],
		],
		'enabled' => [
			'type'     => 'boolean',
			'required' => false,
		],
	];
}

// ── Handlers ──────────────────────────────────────────────────────────────────

/**
 * GET /webhooks
 */
function clientflow_rest_list_webhooks( WP_REST_Request $request ): WP_REST_Response {
	global $wpdb;

	$owner_id = clientflow_get_owner_id( get_current_user_id() );
	$wt       = $wpdb->prefix . 'clientflow_webhooks';
	$lt       = $wpdb->prefix . 'clientflow_webhook_logs';

	$webhooks = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wt} WHERE owner_id = %d ORDER BY created_at DESC",
			$owner_id
		),
		ARRAY_A
	);

	$webhooks = array_map( static function ( array $wh ) use ( $wpdb, $lt ): array {
		$wh['id']      = (int) $wh['id'];
		$wh['enabled'] = (bool) $wh['enabled'];
		$wh['events']  = json_decode( $wh['events'], true ) ?? [];
		$wh['logs']    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event, response_code, success, delivered_at
				 FROM {$lt} WHERE webhook_id = %d ORDER BY delivered_at DESC LIMIT 3",
				$wh['id']
			),
			ARRAY_A
		);
		foreach ( $wh['logs'] as &$log ) {
			$log['response_code'] = $log['response_code'] !== null ? (int) $log['response_code'] : null;
			$log['success']       = (bool) $log['success'];
		}
		unset( $log );
		return $wh;
	}, $webhooks ?: [] );

	return new WP_REST_Response( [ 'webhooks' => $webhooks ], 200 );
}

/**
 * POST /webhooks
 */
function clientflow_rest_create_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	global $wpdb;

	$owner_id = clientflow_get_owner_id( get_current_user_id() );

	if ( ! clientflow_rest_rate_limit( 'webhooks_write', $owner_id, 30 ) ) {
		return new WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'clientflow' ), [ 'status' => 429 ] );
	}

	if ( ! clientflow_can_user( $owner_id, 'use_webhooks' ) ) {
		return new WP_Error( 'plan_required', __( 'Upgrade to Pro or Agency to use webhooks.', 'clientflow' ), [ 'status' => 403 ] );
	}

	$url    = (string) $request->get_param( 'url' );
	$events = clientflow_sanitize_webhook_events( (array) $request->get_param( 'events' ) );

	if ( empty( $url ) ) {
		return new WP_Error( 'invalid_url', __( 'A valid URL is required.', 'clientflow' ), [ 'status' => 422 ] );
	}

	if ( empty( $events ) ) {
		return new WP_Error( 'no_events', __( 'Select at least one event to subscribe to.', 'clientflow' ), [ 'status' => 422 ] );
	}

	$secret = bin2hex( random_bytes( 32 ) );
	$now    = current_time( 'mysql' );

	$wpdb->insert(
		$wpdb->prefix . 'clientflow_webhooks',
		[
			'owner_id'   => $owner_id,
			'url'        => $url,
			'events'     => wp_json_encode( $events ),
			'secret'     => $secret,
			'enabled'    => 1,
			'created_at' => $now,
			'updated_at' => $now,
		],
		[ '%d', '%s', '%s', '%s', '%d', '%s', '%s' ]
	);

	$id = (int) $wpdb->insert_id;

	return new WP_REST_Response( [
		'webhook' => [
			'id'         => $id,
			'owner_id'   => $owner_id,
			'url'        => $url,
			'events'     => $events,
			'secret'     => $secret,
			'enabled'    => true,
			'created_at' => $now,
			'updated_at' => $now,
			'logs'       => [],
		],
	], 201 );
}

/**
 * PATCH /webhooks/{id}
 */
function clientflow_rest_update_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	global $wpdb;

	$owner_id = clientflow_get_owner_id( get_current_user_id() );

	if ( ! clientflow_rest_rate_limit( 'webhooks_write', $owner_id, 30 ) ) {
		return new WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'clientflow' ), [ 'status' => 429 ] );
	}

	if ( ! clientflow_can_user( $owner_id, 'use_webhooks' ) ) {
		return new WP_Error( 'plan_required', __( 'Upgrade to Pro or Agency to use webhooks.', 'clientflow' ), [ 'status' => 403 ] );
	}

	$id = (int) $request->get_param( 'id' );

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}clientflow_webhooks WHERE id = %d AND owner_id = %d",
			$id,
			$owner_id
		)
	);

	if ( ! $exists ) {
		return new WP_Error( 'not_found', __( 'Webhook not found.', 'clientflow' ), [ 'status' => 404 ] );
	}

	$update = [ 'updated_at' => current_time( 'mysql' ) ];

	if ( null !== $request->get_param( 'url' ) ) {
		$url = (string) $request->get_param( 'url' );
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', __( 'A valid URL is required.', 'clientflow' ), [ 'status' => 422 ] );
		}
		$update['url'] = $url;
	}

	if ( null !== $request->get_param( 'events' ) ) {
		$events = clientflow_sanitize_webhook_events( (array) $request->get_param( 'events' ) );
		if ( empty( $events ) ) {
			return new WP_Error( 'no_events', __( 'Select at least one event.', 'clientflow' ), [ 'status' => 422 ] );
		}
		$update['events'] = wp_json_encode( $events );
	}

	if ( null !== $request->get_param( 'enabled' ) ) {
		$update['enabled'] = (bool) $request->get_param( 'enabled' ) ? 1 : 0;
	}

	$wpdb->update(
		$wpdb->prefix . 'clientflow_webhooks',
		$update,
		[ 'id' => $id, 'owner_id' => $owner_id ]
	);

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}clientflow_webhooks WHERE id = %d",
			$id
		),
		ARRAY_A
	);

	$row['id']      = (int) $row['id'];
	$row['enabled'] = (bool) $row['enabled'];
	$row['events']  = json_decode( $row['events'], true ) ?? [];

	return new WP_REST_Response( [ 'webhook' => $row ], 200 );
}

/**
 * DELETE /webhooks/{id}
 */
function clientflow_rest_delete_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	global $wpdb;

	$owner_id = clientflow_get_owner_id( get_current_user_id() );
	$id       = (int) $request->get_param( 'id' );

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}clientflow_webhooks WHERE id = %d AND owner_id = %d",
			$id,
			$owner_id
		)
	);

	if ( ! $exists ) {
		return new WP_Error( 'not_found', __( 'Webhook not found.', 'clientflow' ), [ 'status' => 404 ] );
	}

	$wpdb->delete( $wpdb->prefix . 'clientflow_webhook_logs', [ 'webhook_id' => $id ], [ '%d' ] );
	$wpdb->delete( $wpdb->prefix . 'clientflow_webhooks', [ 'id' => $id, 'owner_id' => $owner_id ], [ '%d', '%d' ] );

	return new WP_REST_Response( [ 'deleted' => true ], 200 );
}

/**
 * POST /webhooks/{id}/test
 */
function clientflow_rest_test_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	global $wpdb;

	$owner_id = clientflow_get_owner_id( get_current_user_id() );

	if ( ! clientflow_rest_rate_limit( 'webhooks_write', $owner_id, 30 ) ) {
		return new WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'clientflow' ), [ 'status' => 429 ] );
	}

	$id = (int) $request->get_param( 'id' );

	$wh = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, url, secret FROM {$wpdb->prefix}clientflow_webhooks WHERE id = %d AND owner_id = %d",
			$id,
			$owner_id
		),
		ARRAY_A
	);

	if ( ! $wh ) {
		return new WP_Error( 'not_found', __( 'Webhook not found.', 'clientflow' ), [ 'status' => 404 ] );
	}

	$payload = (string) wp_json_encode( [
		'event'     => 'test',
		'timestamp' => gmdate( 'c' ),
		'data'      => [ 'message' => 'This is a test ping from ClientFlow.' ],
	] );

	$sig      = 'sha256=' . hash_hmac( 'sha256', $payload, $wh['secret'] );
	$response = wp_remote_post( $wh['url'], [
		'body'    => $payload,
		'headers' => [
			'Content-Type'           => 'application/json',
			'X-ClientFlow-Event'     => 'test',
			'X-ClientFlow-Signature' => $sig,
		],
		'timeout' => 5,
	] );

	$code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$success = ( $code >= 200 && $code < 300 );

	$wpdb->insert(
		$wpdb->prefix . 'clientflow_webhook_logs',
		[
			'webhook_id'    => (int) $wh['id'],
			'event'         => 'test',
			'response_code' => $code,
			'success'       => $success ? 1 : 0,
			'delivered_at'  => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%d', '%d', '%s' ]
	);

	/* translators: %d is the HTTP response status code */
	$clientflow_success_msg = sprintf( __( 'Test delivered successfully (HTTP %d).', 'clientflow' ), $code );
	/* translators: %d is the HTTP response status code */
	$clientflow_failure_msg = sprintf( __( 'Delivery failed (HTTP %d). Check the URL and try again.', 'clientflow' ), $code );
	$clientflow_webhook_msg = $success ? $clientflow_success_msg : $clientflow_failure_msg;

	return new WP_REST_Response( [
		'success'       => $success,
		'response_code' => $code,
		'message'       => $clientflow_webhook_msg,
	], 200 );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function clientflow_sanitize_webhook_events( array $raw ): array {
	return array_values( array_filter(
		array_map( 'sanitize_text_field', $raw ),
		static fn( string $e ): bool => in_array( $e, CF_WEBHOOK_EVENTS, true )
	) );
}
