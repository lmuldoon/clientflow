<?php
/**
 * REST API: Approval Endpoints
 *
 * Namespace: /wp-json/clientflow/v1/
 *
 * Admin routes (authenticated WordPress users):
 *   GET    /projects/{id}/approvals          — list approval requests for a project
 *   POST   /projects/{id}/approvals          — create an approval request
 *   DELETE /projects/{id}/approvals/{aid}    — delete an approval request
 *
 * Portal routes (authenticated portal / clientflow_client users):
 *   GET    /portal/projects/{id}/approvals         — list approvals (client)
 *   POST   /portal/approvals/{aid}/respond         — client approves or rejects
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', static function (): void {

	// Load class.
	$path = CLIENTFLOW_DIR . 'modules/approvals/class-approval.php';
	if ( ! class_exists( 'ClientFlow_Approval' ) && file_exists( $path ) ) {
		require_once $path;
	}

	if ( ! class_exists( 'ClientFlow_Portal_Auth' ) ) {
		$p = CLIENTFLOW_DIR . 'modules/portal/class-portal-auth.php';
		if ( file_exists( $p ) ) {
			require_once $p;
		}
	}

	$ns         = 'clientflow/v1';
	$proj_id    = '(?P<id>\d+)';
	$approv_id  = '(?P<aid>\d+)';

	// ── Admin: list ──────────────────────────────────────────────────────────
	register_rest_route( $ns, "/projects/{$proj_id}/approvals", [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_list_approvals',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );

	// ── Admin: create ────────────────────────────────────────────────────────
	register_rest_route( $ns, "/projects/{$proj_id}/approvals", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_create_approval',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'type'        => [ 'type' => 'string',  'required' => false, 'default' => 'other' ],
			'description' => [ 'type' => 'string',  'required' => false, 'default' => '',     'sanitize_callback' => 'sanitize_textarea_field' ],
		],
	] );

	// ── Admin: delete ────────────────────────────────────────────────────────
	register_rest_route( $ns, "/projects/{$proj_id}/approvals/{$approv_id}", [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => 'cf_rest_delete_approval',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'  => [ 'type' => 'integer', 'required' => true ],
			'aid' => [ 'type' => 'integer', 'required' => true ],
		],
	] );

	// ── Portal: list ─────────────────────────────────────────────────────────
	register_rest_route( $ns, "/portal/projects/{$proj_id}/approvals", [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_portal_rest_list_approvals',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );

	// ── Portal: respond ──────────────────────────────────────────────────────
	register_rest_route( $ns, "/portal/approvals/{$approv_id}/respond", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_portal_rest_respond_approval',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
		'args'                => [
			'aid'     => [ 'type' => 'integer', 'required' => true ],
			'status'  => [ 'type' => 'string',  'required' => true,  'enum' => [ 'approved', 'rejected' ] ],
			'comment' => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ],
		],
	] );
} );

// ── Admin handlers ────────────────────────────────────────────────────────────

function cf_rest_list_approvals( WP_REST_Request $request ): WP_REST_Response {
	$owner_id   = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );
	$approvals  = ClientFlow_Approval::list( $project_id, $owner_id );

	return new WP_REST_Response( [ 'approvals' => $approvals ], 200 );
}

function cf_rest_create_approval( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$owner_id   = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );

	$result = ClientFlow_Approval::create( $project_id, $owner_id, [
		'type'        => $request->get_param( 'type' ),
		'description' => $request->get_param( 'description' ),
	] );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$approvals = ClientFlow_Approval::list( $project_id, $owner_id );

	return new WP_REST_Response( [ 'approvals' => $approvals ], 201 );
}

function cf_rest_delete_approval( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$owner_id    = get_current_user_id();
	$approval_id = (int) $request->get_param( 'aid' );
	$result      = ClientFlow_Approval::delete( $approval_id, $owner_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'deleted' => true ], 200 );
}

// ── Portal handlers ───────────────────────────────────────────────────────────

function cf_portal_rest_list_approvals( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$client_id  = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );
	$result     = ClientFlow_Approval::get_for_client( $project_id, $client_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'approvals' => $result ], 200 );
}

function cf_portal_rest_respond_approval( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$client_id   = get_current_user_id();
	$approval_id = (int) $request->get_param( 'aid' );
	$status      = (string) $request->get_param( 'status' );
	$comment     = (string) $request->get_param( 'comment' );

	$result = ClientFlow_Approval::respond( $approval_id, $client_id, $status, $comment );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'approval' => $result ], 200 );
}
