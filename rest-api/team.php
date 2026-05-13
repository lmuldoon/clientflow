<?php
/**
 * Team REST API
 *
 * GET    /clientflow/v1/team/members        — list team members for the current owner
 * POST   /clientflow/v1/team/invite         — invite a new team member
 * DELETE /clientflow/v1/team/members/{id}   — remove a team member
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table queries; all table variables use ->prefix with trusted constants, not user input.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', static function (): void {
	$ns = 'clientflow/v1';

	register_rest_route( $ns, '/team/members', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'clientflow_rest_team_list',
		'permission_callback' => 'clientflow_rest_require_auth',
	] );

	register_rest_route( $ns, '/team/invite', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'clientflow_rest_team_invite',
		'permission_callback' => 'clientflow_rest_require_auth',
		'args'                => [
			'email' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
			'name'  => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
			'role'  => [
				'type'              => 'string',
				'enum'              => [ 'admin', 'editor', 'viewer' ],
				'default'           => 'editor',
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	] );

	register_rest_route( $ns, '/team/members/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => 'clientflow_rest_team_remove',
		'permission_callback' => 'clientflow_rest_require_auth',
		'args'                => [
			'id' => [ 'type' => 'integer', 'required' => true ],
		],
	] );
} );

/**
 * List all team members for the current owner.
 */
function clientflow_rest_team_list( WP_REST_Request $request ): WP_REST_Response {
	$owner_id = clientflow_get_owner_id( get_current_user_id() );

	$handlers = CLIENTFLOW_DIR . 'modules/team/handlers.php';
	if ( ! function_exists( 'clientflow_team_get_members' ) && file_exists( $handlers ) ) {
		require_once $handlers;
	}

	$seats_used  = ClientFlow_Entitlements::get_team_seats_used( $owner_id );
	$seats_limit = ClientFlow_Entitlements::get_team_limit( $owner_id );

	return new WP_REST_Response( [
		'members'     => clientflow_team_get_members( $owner_id ),
		'seats_used'  => $seats_used,
		'seats_limit' => $seats_limit,
	], 200 );
}

/**
 * Invite a new team member.
 */
function clientflow_rest_team_invite( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$owner_id = clientflow_get_owner_id( get_current_user_id() );

	if ( ! clientflow_rest_rate_limit( 'team_invite', $owner_id, 10 ) ) {
		return new WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'clientflow' ), [ 'status' => 429 ] );
	}

	$handlers = CLIENTFLOW_DIR . 'modules/team/handlers.php';
	if ( ! function_exists( 'clientflow_team_invite_member' ) && file_exists( $handlers ) ) {
		require_once $handlers;
	}

	$result = clientflow_team_invite_member(
		$owner_id,
		(string) $request->get_param( 'email' ),
		(string) $request->get_param( 'name' ),
		(string) $request->get_param( 'role' )
	);

	if ( ! $result['success'] ) {
		$status = isset( $result['upgrade_required'] ) ? 402 : 400;
		return new WP_REST_Response( $result, $status );
	}

	return new WP_REST_Response( $result, 200 );
}

/**
 * Remove a team member.
 */
function clientflow_rest_team_remove( WP_REST_Request $request ): WP_REST_Response {
	$owner_id = clientflow_get_owner_id( get_current_user_id() );

	$handlers = CLIENTFLOW_DIR . 'modules/team/handlers.php';
	if ( ! function_exists( 'clientflow_team_remove_member' ) && file_exists( $handlers ) ) {
		require_once $handlers;
	}

	$row_id  = (int) $request->get_param( 'id' );
	$removed = clientflow_team_remove_member( $owner_id, $row_id );

	if ( ! $removed ) {
		return new WP_REST_Response( [ 'success' => false, 'error' => 'not_found' ], 404 );
	}

	return new WP_REST_Response( [ 'success' => true ], 200 );
}
