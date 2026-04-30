<?php
/**
 * REST API: Project & Milestone Endpoints
 *
 * Namespace: /wp-json/clientflow/v1/
 *
 * Admin routes (authenticated WordPress users):
 *   GET    /projects                             — list projects
 *   GET    /projects/{id}                        — get project + milestones
 *   POST   /projects/{id}/update                 — update project fields
 *   DELETE /projects/{id}                        — delete project
 *   POST   /projects/{id}/milestones             — create milestone
 *   POST   /projects/{id}/milestones/{mid}/update — update milestone
 *   DELETE /projects/{id}/milestones/{mid}       — delete milestone
 *   POST   /projects/{id}/milestones/reorder     — reorder milestones
 *
 * Portal routes (authenticated portal / clientflow_client users):
 *   GET    /portal/projects                      — client's projects
 *   GET    /portal/projects/{id}                 — single project + milestones
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', static function (): void {

	// Explicitly require module files — autoloader cannot resolve these class names
	// because strstr('project', '-', true) and strstr('milestone', '-', true) both
	// return false (no hyphen), so the autoloader path resolution fails.
	$base = CLIENTFLOW_DIR . 'modules/projects/';
	foreach ( [
		'class-project.php'   => 'ClientFlow_Project',
		'class-milestone.php' => 'ClientFlow_Milestone',
		'handlers.php'        => 'ClientFlow_Project_Handlers',
	] as $file => $class ) {
		if ( ! class_exists( $class ) && file_exists( $base . $file ) ) {
			require_once $base . $file;
		}
	}

	// Also ensure Portal_Data is available (for portal routes).
	if ( ! class_exists( 'ClientFlow_Portal_Data' ) ) {
		$p = CLIENTFLOW_DIR . 'modules/portal/class-portal-data.php';
		if ( file_exists( $p ) ) require_once $p;
	}
	if ( ! class_exists( 'ClientFlow_Portal_Auth' ) ) {
		$p = CLIENTFLOW_DIR . 'modules/portal/class-portal-auth.php';
		if ( file_exists( $p ) ) require_once $p;
	}

	$ns = 'clientflow/v1';

	// ── GET /projects ─────────────────────────────────────────────────────────
	register_rest_route( $ns, '/projects', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_list_projects',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'status'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ],
			'search'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
			'orderby'  => [ 'type' => 'string', 'default' => 'created_at', 'sanitize_callback' => 'sanitize_key' ],
			'order'    => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
		],
	] );

	// ── GET /projects/{id} ────────────────────────────────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_get_project',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );

	// ── POST /projects/{id}/update ────────────────────────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)/update', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_update_project',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'name'        => [ 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
			'description' => [ 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
			'status'      => [ 'type' => 'string', 'required' => false, 'enum' => [ 'active', 'on-hold', 'completed' ] ],
		],
	] );

	// ── DELETE /projects/{id} ─────────────────────────────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => 'cf_rest_delete_project',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );

	// ── POST /projects/{id}/milestones ────────────────────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)/milestones', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_create_milestone',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'title'       => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'description' => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
			'due_date'    => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );

	// ── POST /projects/{id}/milestones/{mid}/update ───────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)/milestones/(?P<mid>\d+)/update', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_update_milestone',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'mid'         => [ 'type' => 'integer', 'required' => true ],
			'title'       => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
			'description' => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
			'status'      => [ 'type' => 'string',  'required' => false, 'enum' => ClientFlow_Milestone::STATUSES ],
			'due_date'    => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );

	// ── DELETE /projects/{id}/milestones/{mid} ────────────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)/milestones/(?P<mid>\d+)', [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => 'cf_rest_delete_milestone',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'  => [ 'type' => 'integer', 'required' => true ],
			'mid' => [ 'type' => 'integer', 'required' => true ],
		],
	] );

	// ── POST /projects/{id}/milestones/reorder ────────────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)/milestones/reorder', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_reorder_milestones',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'ordered_ids' => [ 'type' => 'array',   'required' => true ],
		],
	] );

	// ── POST /projects/{id}/milestones/{mid}/submit ───────────────────────────
	register_rest_route( $ns, '/projects/(?P<id>\d+)/milestones/(?P<mid>\d+)/submit', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_submit_milestone',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'  => [ 'type' => 'integer', 'required' => true ],
			'mid' => [ 'type' => 'integer', 'required' => true ],
		],
	] );

	// ── Portal: GET /portal/projects ──────────────────────────────────────────
	register_rest_route( $ns, '/portal/projects', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_portal_rest_list_projects',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
	] );

	// ── Portal: GET /portal/projects/{id} ─────────────────────────────────────
	register_rest_route( $ns, '/portal/projects/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_portal_rest_get_project',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );

	// ── Portal: POST /portal/projects/{id}/milestones/{mid}/approve ───────────
	register_rest_route( $ns, '/portal/projects/(?P<id>\d+)/milestones/(?P<mid>\d+)/approve', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_portal_rest_approve_milestone',
		'permission_callback' => [ 'ClientFlow_Portal_Auth', 'rest_permission' ],
		'args'                => [
			'id'  => [ 'type' => 'integer', 'required' => true ],
			'mid' => [ 'type' => 'integer', 'required' => true ],
		],
	] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Admin handlers
// ─────────────────────────────────────────────────────────────────────────────

function cf_rest_list_projects( WP_REST_Request $request ): WP_REST_Response {
	$user_id = get_current_user_id();
	$result  = ClientFlow_Project::list( $user_id, [
		'status'   => $request->get_param( 'status' ),
		'search'   => $request->get_param( 'search' ),
		'page'     => (int) $request->get_param( 'page' ),
		'per_page' => (int) $request->get_param( 'per_page' ),
		'orderby'  => $request->get_param( 'orderby' ),
		'order'    => $request->get_param( 'order' ),
	] );
	return new WP_REST_Response( $result, 200 );
}

function cf_rest_get_project( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );
	$result  = ClientFlow_Project::get( $id, $user_id );
	if ( is_wp_error( $result ) ) return $result;
	return new WP_REST_Response( [ 'project' => $result ], 200 );
}

function cf_rest_update_project( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	global $wpdb;

	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );
	$data    = array_filter(
		$request->get_params(),
		static fn( $k ) => in_array( $k, [ 'name', 'description', 'status' ], true ),
		ARRAY_FILTER_USE_KEY
	);

	// Gate: cannot mark completed until all milestones are done.
	if ( isset( $data['status'] ) && 'completed' === $data['status'] ) {
		if ( ! ClientFlow_Milestone::all_completed( $id ) ) {
			return new WP_Error(
				'milestones_incomplete',
				__( 'All milestones must be marked complete before the project can be closed.', 'clientflow' ),
				[ 'status' => 422 ]
			);
		}
	}

	$result = ClientFlow_Project::update( $id, $user_id, $data );
	if ( is_wp_error( $result ) ) return $result;

	$project = ClientFlow_Project::get( $id, $user_id );

	// On project completion: stamp the proposal as completed and email the client.
	if ( isset( $data['status'] ) && 'completed' === $data['status'] && ! is_wp_error( $project ) ) {
		if ( ! empty( $project['proposal_id'] ) ) {
			$wpdb->update(
				$wpdb->prefix . 'clientflow_proposals',
				[ 'status' => 'completed' ],
				[ 'id' => (int) $project['proposal_id'], 'owner_id' => $user_id ],
				[ '%s' ],
				[ '%d', '%d' ]
			);
		}
		cf_send_project_completion_email( $project );
	}

	return new WP_REST_Response( [ 'project' => $project ], 200 );
}

function cf_rest_delete_project( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );
	$result  = ClientFlow_Project::delete( $id, $user_id );
	if ( is_wp_error( $result ) ) return $result;
	return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
}

function cf_rest_create_milestone( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id    = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );

	// Ownership check.
	$project = ClientFlow_Project::get( $project_id, $user_id );
	if ( is_wp_error( $project ) ) return $project;

	$mid = ClientFlow_Milestone::create( $project_id, $user_id, [
		'title'       => $request->get_param( 'title' ),
		'description' => $request->get_param( 'description' ) ?? '',
		'due_date'    => $request->get_param( 'due_date' )    ?? '',
	] );

	if ( is_wp_error( $mid ) ) return $mid;

	$project = ClientFlow_Project::get( $project_id, $user_id );
	return new WP_REST_Response( [ 'project' => $project ], 201 );
}

function cf_rest_update_milestone( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id    = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );
	$mid        = (int) $request->get_param( 'mid' );
	$data       = array_filter(
		$request->get_params(),
		static fn( $k ) => in_array( $k, [ 'title', 'description', 'status', 'due_date' ], true ),
		ARRAY_FILTER_USE_KEY
	);

	// Block admin from marking a milestone complete until the client has approved it.
	if ( isset( $data['status'] ) && 'completed' === $data['status'] ) {
		global $wpdb;
		$current_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}clientflow_milestones WHERE id = %d AND owner_id = %d",
				$mid,
				$user_id
			)
		);
		if ( in_array( $current_status, [ 'pending', 'submitted' ], true ) ) {
			return new WP_Error(
				'awaiting_client_approval',
				__( 'This milestone must be approved by the client before it can be marked complete.', 'clientflow' ),
				[ 'status' => 422 ]
			);
		}
	}

	$result = ClientFlow_Milestone::update( $mid, $user_id, $data );
	if ( is_wp_error( $result ) ) return $result;

	$project = ClientFlow_Project::get( $project_id, $user_id );

	// Email client when a milestone is marked complete.
	if ( isset( $data['status'] ) && 'completed' === $data['status'] && ! is_wp_error( $project ) ) {
		$milestone_title = '';
		foreach ( $project['milestones'] ?? [] as $m ) {
			if ( (int) $m['id'] === $mid ) {
				$milestone_title = $m['title'];
				break;
			}
		}
		cf_send_milestone_complete_email( $project, $milestone_title );
	}

	return new WP_REST_Response( [ 'project' => $project ], 200 );
}

function cf_rest_delete_milestone( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id    = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );
	$mid        = (int) $request->get_param( 'mid' );
	$result     = ClientFlow_Milestone::delete( $mid, $user_id );
	if ( is_wp_error( $result ) ) return $result;
	$project = ClientFlow_Project::get( $project_id, $user_id );
	return new WP_REST_Response( [ 'project' => $project ], 200 );
}

function cf_rest_reorder_milestones( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id    = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );
	$ids        = array_map( 'intval', (array) $request->get_param( 'ordered_ids' ) );
	$result     = ClientFlow_Milestone::reorder( $project_id, $user_id, $ids );
	if ( is_wp_error( $result ) ) return $result;
	$project = ClientFlow_Project::get( $project_id, $user_id );
	return new WP_REST_Response( [ 'project' => $project ], 200 );
}

function cf_rest_submit_milestone( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id    = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );
	$mid        = (int) $request->get_param( 'mid' );

	$project = ClientFlow_Project::get( $project_id, $user_id );
	if ( is_wp_error( $project ) ) return $project;

	$result = ClientFlow_Milestone::submit( $mid, $user_id );
	if ( is_wp_error( $result ) ) return $result;

	$project = ClientFlow_Project::get( $project_id, $user_id );
	return new WP_REST_Response( [ 'project' => $project ], 200 );
}

// ─────────────────────────────────────────────────────────────────────────────
// Portal handlers
// ─────────────────────────────────────────────────────────────────────────────

function cf_portal_rest_list_projects( WP_REST_Request $request ): WP_REST_Response {
	$user_id  = get_current_user_id();
	$projects = ClientFlow_Portal_Data::get_projects( $user_id );
	return new WP_REST_Response( [ 'projects' => $projects ], 200 );
}

function cf_portal_rest_get_project( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );
	$project = ClientFlow_Portal_Data::get_project( $user_id, $id );
	if ( is_wp_error( $project ) ) return $project;
	return new WP_REST_Response( [ 'project' => $project ], 200 );
}

function cf_portal_rest_approve_milestone( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id    = get_current_user_id();
	$project_id = (int) $request->get_param( 'id' );
	$mid        = (int) $request->get_param( 'mid' );

	// Ownership check — ensure this project belongs to the current portal user.
	$project = ClientFlow_Portal_Data::get_project( $user_id, $project_id );
	if ( is_wp_error( $project ) ) return $project;

	$result = ClientFlow_Milestone::approve( $mid, $project_id );
	if ( is_wp_error( $result ) ) return $result;

	// Reload so the response has the updated milestone status.
	$project = ClientFlow_Portal_Data::get_project( $user_id, $project_id );
	return new WP_REST_Response( [ 'project' => $project ], 200 );
}

// ─────────────────────────────────────────────────────────────────────────────
// Email helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fetch the client email address for a project row.
 *
 * @param array $project Project row (must contain client_id or proposal_id).
 * @return string Client email, or empty string if not resolvable.
 */
function cf_project_client_email( array $project ): string {
	return cf_project_client_data( $project )['email'];
}

function cf_project_client_data( array $project ): array {
	global $wpdb;

	$client_id = (int) ( $project['client_id'] ?? 0 );
	if ( ! $client_id ) return [ 'email' => '', 'name' => '' ];

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT email, name FROM {$wpdb->prefix}clientflow_clients WHERE id = %d",
			$client_id
		),
		ARRAY_A
	);

	return [
		'email' => (string) ( $row['email'] ?? '' ),
		'name'  => (string) ( $row['name']  ?? '' ),
	];
}

/**
 * Email the client that a milestone has been marked complete.
 *
 * @param array  $project        Full project row (from ClientFlow_Project::get).
 * @param string $milestone_title
 */
function cf_send_milestone_complete_email( array $project, string $milestone_title ): void {
	$client = cf_project_client_data( $project );
	if ( ! $client['email'] ) return;

	$project_name   = esc_html( $project['name'] ?? '' );
	$milestone_esc  = esc_html( $milestone_title );
	$subject        = sprintf( 'Milestone Complete: %s', $milestone_title );

	$body_html = "
		<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
			Great news — a milestone has been completed on your project
			<strong style=\"color:#1A1A2E;\">{$project_name}</strong>.
		</p>
		<div style=\"margin:20px 0;padding:16px 20px;background:#F0FDF4;border-radius:10px;border-left:3px solid #10B981;\">
			<p style=\"margin:0;font-size:13px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#10B981;\">Completed</p>
			<p style=\"margin:6px 0 0;font-size:16px;font-weight:600;color:#1A1A2E;\">{$milestone_esc}</p>
		</div>
		<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
			Log in to your portal to see the latest progress on your project.
		</p>";

	$message = cf_email_html( [
		'name'      => $client['name'],
		'body'      => $body_html,
		'cta_label' => 'View Project',
		'cta_url'   => home_url( '/portal/' ),
	] );

	wp_mail( $client['email'], $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/**
 * Email the client that their project has been completed.
 *
 * @param array $project Full project row (from ClientFlow_Project::get).
 */
function cf_send_project_completion_email( array $project ): void {
	$client = cf_project_client_data( $project );
	if ( ! $client['email'] ) return;

	global $wpdb;

	$proposal_id  = (int) ( $project['proposal_id'] ?? 0 );
	$total_amount = 0.00;
	$paid_amount  = 0.00;

	if ( $proposal_id ) {
		$total_amount = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT total_amount FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d",
				$proposal_id
			)
		);
		$paid_amount = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}clientflow_payments WHERE proposal_id = %d AND status = 'completed'",
				$proposal_id
			)
		);
	}

	$remaining    = max( 0.00, $total_amount - $paid_amount );
	$project_name = esc_html( $project['name'] ?? '' );

	$payment_block = '';
	if ( $remaining > 0 ) {
		$amount_fmt    = esc_html( number_format( $remaining, 2 ) );
		$payment_block = "
		<div style=\"margin:20px 0;padding:16px 20px;background:#FFFBEB;border-radius:10px;border-left:3px solid #F59E0B;\">
			<p style=\"margin:0;font-size:13px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#F59E0B;\">Payment Due</p>
			<p style=\"margin:6px 0 0;font-size:20px;font-weight:700;color:#1A1A2E;\">&pound;{$amount_fmt}</p>
			<p style=\"margin:6px 0 0;font-size:13px;color:#9CA3AF;\">Please log in to your portal to arrange payment.</p>
		</div>";
	}

	$subject   = sprintf( '%s is Complete', $project['name'] ?? '' );
	$body_html = "
		<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
			Your project <strong style=\"color:#1A1A2E;\">{$project_name}</strong> has been completed.
			Thank you for working with us.
		</p>
		{$payment_block}
		<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
			Log in to your portal to view the final summary.
		</p>";

	$message = cf_email_html( [
		'name'      => $client['name'],
		'body'      => $body_html,
		'cta_label' => 'View Project',
		'cta_url'   => home_url( '/portal/' ),
	] );

	wp_mail( $client['email'], $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
}
