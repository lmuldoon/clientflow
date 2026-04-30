<?php
/**
 * REST API: Proposals Endpoints
 *
 * Namespace: /wp-json/clientflow/v1/
 *
 * Routes:
 *   POST   /proposals/create      — create proposal from wizard payload
 *   GET    /proposals             — list user's proposals (with filters)
 *   GET    /proposals/{id}        — get single proposal
 *   POST   /proposals/{id}/update — update proposal fields
 *   POST   /proposals/{id}/send   — send proposal to client
 *   POST   /proposals/{id}/duplicate — duplicate proposal
 *   DELETE /proposals/{id}        — delete proposal
 *   GET    /proposals/templates   — list available templates for user's plan
 *
 * All routes require authentication.
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load proposal classes if not already autoloaded.
add_action( 'rest_api_init', static function (): void {
	// Load each module class independently — a single shared condition was
	// short-circuiting after the first two classes loaded, leaving handlers.php
	// never required and ClientFlow_Proposal_Handlers undefined.
	$base_dir    = CLIENTFLOW_DIR . 'modules/proposals/';
	$module_files = [
		'class-proposal-template.php' => 'ClientFlow_Proposal_Template',
		'class-proposal.php'          => 'ClientFlow_Proposal',
		'handlers.php'                => 'ClientFlow_Proposal_Handlers',
	];
	foreach ( $module_files as $file => $class ) {
		if ( ! class_exists( $class ) ) {
			$path = $base_dir . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	$ns = 'clientflow/v1';

	// ── GET /proposals/templates ──────────────────────────────────────────────
	register_rest_route( $ns, '/proposals/templates', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_list_templates',
		'permission_callback' => 'cf_rest_require_auth',
	] );

	// ── POST /proposals/create ────────────────────────────────────────────────
	register_rest_route( $ns, '/proposals/create', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_create_proposal',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => cf_proposal_create_args(),
	] );

	// ── GET /proposals ────────────────────────────────────────────────────────
	register_rest_route( $ns, '/proposals', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_list_proposals',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'status'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'enum'              => array_merge( [ '' ], ClientFlow_Proposal::STATUSES ),
				'default'           => '',
			],
			'search'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
			'orderby'  => [ 'type' => 'string', 'default' => 'created_at', 'sanitize_callback' => 'sanitize_key' ],
			'order'    => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
		],
	] );

	// ── GET /proposals/{id} ───────────────────────────────────────────────────
	register_rest_route( $ns, '/proposals/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_rest_get_proposal',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );

	// ── POST /proposals/{id}/update ───────────────────────────────────────────
	register_rest_route( $ns, '/proposals/(?P<id>\d+)/update', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_update_proposal',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => array_merge(
			[ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			cf_proposal_update_args()
		),
	] );

	// ── POST /proposals/{id}/send ─────────────────────────────────────────────
	register_rest_route( $ns, '/proposals/(?P<id>\d+)/send', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_send_proposal',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'id'           => [ 'type' => 'integer', 'required' => true ],
			'client_email' => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_email',
			],
		],
	] );

	// ── POST /proposals/{id}/update-wizard ───────────────────────────────────
	register_rest_route( $ns, '/proposals/(?P<id>\d+)/update-wizard', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_update_wizard_proposal',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => array_merge(
			[ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			cf_proposal_create_args()
		),
	] );

	// ── POST /proposals/{id}/duplicate ────────────────────────────────────────
	register_rest_route( $ns, '/proposals/(?P<id>\d+)/duplicate', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_rest_duplicate_proposal',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );

	// ── DELETE /proposals/{id} ────────────────────────────────────────────────
	register_rest_route( $ns, '/proposals/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => 'cf_rest_delete_proposal',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Argument definitions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Args for POST /proposals/create.
 */
function cf_proposal_create_args(): array {
	return [
		'template_id'     => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'sanitize_key' ],
		'title'           => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
		'currency'        => [ 'type' => 'string',  'required' => false, 'default' => 'GBP', 'sanitize_callback' => 'sanitize_text_field' ],
		'expiry_date'     => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'deposit_pct'     => [ 'type' => 'number',  'required' => false, 'default' => 0 ],
		'require_deposit' => [ 'type' => 'boolean', 'required' => false, 'default' => false ],
		'client_name'     => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'client_email'    => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_email' ],
		'client_company'  => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'client_phone'    => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'line_items'      => [ 'type' => 'array',   'required' => false, 'default' => [] ],
		'discount_pct'    => [ 'type' => 'number',  'required' => false, 'default' => 0 ],
		'vat_pct'         => [ 'type' => 'number',  'required' => false, 'default' => 0 ],
	];
}

/**
 * Args for POST /proposals/{id}/update.
 */
function cf_proposal_update_args(): array {
	return [
		'title'        => [ 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'content'      => [ 'type' => 'string', 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
		'status'       => [ 'type' => 'string', 'required' => false, 'enum' => ClientFlow_Proposal::STATUSES ],
		'currency'     => [ 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'expiry_date'  => [ 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'total_amount' => [ 'type' => 'number', 'required' => false ],
		'client_id'    => [ 'type' => 'integer','required' => false ],
	];
}

// ─────────────────────────────────────────────────────────────────────────────
// Route handlers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * GET /clientflow/v1/proposals/templates
 *
 * Returns templates available for the current user's plan.
 */
function cf_rest_list_templates( WP_REST_Request $request ): WP_REST_Response {
	$user_id  = get_current_user_id();
	$plan     = ClientFlow_Entitlements::get_user_plan( $user_id );
	$all      = ClientFlow_Proposal_Template::all();

	// Annotate each template with whether it's accessible.
	$templates = array_map( static function ( array $tpl ) use ( $plan ) {
		$tpl['locked'] = ( 'pro' === $tpl['tier'] && 'free' === $plan );
		unset( $tpl['sections'] ); // Don't expose full content in listing.
		return $tpl;
	}, $all );

	return new WP_REST_Response( [ 'templates' => array_values( $templates ) ], 200 );
}

/**
 * POST /clientflow/v1/proposals/create
 *
 * Create a proposal from the wizard payload.
 */
function cf_rest_create_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$payload = $request->get_params();

	$result = ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'proposal' => $result ], 201 );
}

/**
 * GET /clientflow/v1/proposals
 *
 * List the current user's proposals.
 */
function cf_rest_list_proposals( WP_REST_Request $request ): WP_REST_Response {
	$user_id = get_current_user_id();

	$result = ClientFlow_Proposal::list( $user_id, [
		'status'   => $request->get_param( 'status' ),
		'search'   => $request->get_param( 'search' ),
		'page'     => (int) $request->get_param( 'page' ),
		'per_page' => (int) $request->get_param( 'per_page' ),
		'orderby'  => $request->get_param( 'orderby' ),
		'order'    => $request->get_param( 'order' ),
	] );

	return new WP_REST_Response( $result, 200 );
}

/**
 * GET /clientflow/v1/proposals/{id}
 *
 * Get a single proposal.
 */
function cf_rest_get_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );

	$result = ClientFlow_Proposal::get( $id, $user_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'proposal' => $result ], 200 );
}

/**
 * POST /clientflow/v1/proposals/{id}/update
 *
 * Update an existing proposal.
 */
function cf_rest_update_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );

	$data = array_filter(
		$request->get_params(),
		static fn( $k ) => in_array( $k, [ 'title', 'content', 'status', 'currency', 'expiry_date', 'total_amount', 'client_id' ], true ),
		ARRAY_FILTER_USE_KEY
	);

	$result = ClientFlow_Proposal::update( $id, $user_id, $data );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$proposal = ClientFlow_Proposal::get( $id, $user_id );

	if ( is_wp_error( $proposal ) ) {
		// Fallback: return minimal shape so the caller can still handle it.
		return new WP_REST_Response( [ 'updated' => true, 'id' => $id ], 200 );
	}

	return new WP_REST_Response( [ 'proposal' => $proposal ], 200 );
}

/**
 * POST /clientflow/v1/proposals/{id}/send
 *
 * Mark a proposal as sent and email the client.
 */
function cf_rest_send_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id      = get_current_user_id();
	$id           = (int) $request->get_param( 'id' );
	$client_email = (string) ( $request->get_param( 'client_email' ) ?? '' );

	$result = ClientFlow_Proposal_Handlers::send_to_client( $id, $user_id, $client_email );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'sent' => true, 'id' => $id ], 200 );
}

/**
 * POST /clientflow/v1/proposals/{id}/update-wizard
 *
 * Update an existing proposal using the full wizard payload.
 */
function cf_rest_update_wizard_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );
	$payload = $request->get_params();

	$result = ClientFlow_Proposal_Handlers::update_from_wizard( $id, $user_id, $payload );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'proposal' => $result ], 200 );
}

/**
 * POST /clientflow/v1/proposals/{id}/duplicate
 *
 * Duplicate a proposal.
 */
function cf_rest_duplicate_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );

	$new_id = ClientFlow_Proposal::duplicate( $id, $user_id );

	if ( is_wp_error( $new_id ) ) {
		return $new_id;
	}

	$proposal = ClientFlow_Proposal::get( $new_id, $user_id );

	if ( is_wp_error( $proposal ) ) {
		return new WP_REST_Response( [ 'duplicated' => true, 'id' => $new_id ], 201 );
	}

	return new WP_REST_Response( [ 'proposal' => $proposal ], 201 );
}

/**
 * DELETE /clientflow/v1/proposals/{id}
 *
 * Delete a proposal.
 */
function cf_rest_delete_proposal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$user_id = get_current_user_id();
	$id      = (int) $request->get_param( 'id' );

	$result = ClientFlow_Proposal::delete( $id, $user_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
}
