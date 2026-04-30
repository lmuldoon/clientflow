<?php
/**
 * REST API: Onboarding endpoints.
 *
 * GET  /clientflow/v1/onboarding/status  → current step + saved brand data
 * POST /clientflow/v1/onboarding/save    → persist step data to wp_options
 * POST /clientflow/v1/onboarding/complete → mark wizard done
 *
 * @package ClientFlow
 */

declare( strict_types=1 );

add_action( 'rest_api_init', static function (): void {

	$ns = 'clientflow/v1';

	// GET /onboarding/status
	register_rest_route( $ns, '/onboarding/status', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'cf_onboarding_status',
		'permission_callback' => 'cf_rest_require_auth',
	] );

	// POST /onboarding/save
	register_rest_route( $ns, '/onboarding/save', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_onboarding_save',
		'permission_callback' => 'cf_rest_require_auth',
		'args'                => [
			'step'                   => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 4 ],
			'license_key'            => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'stripe_pk'              => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'stripe_sk'              => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'stripe_webhook_secret'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'business_name'          => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'brand_color'            => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_hex_color' ],
			'logo_url'               => [ 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ],
		],
	] );

	// POST /onboarding/complete
	register_rest_route( $ns, '/onboarding/complete', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'cf_onboarding_complete',
		'permission_callback' => 'cf_rest_require_auth',
	] );

} );

/**
 * Return current onboarding state.
 */
function cf_onboarding_status( WP_REST_Request $request ): WP_REST_Response {
	return new WP_REST_Response( [
		'complete'      => (bool) get_option( 'clientflow_onboarding_complete' ),
		'step'          => (int) get_option( 'clientflow_onboarding_step', 0 ),
		'saved'         => [
			'license_key'   => get_option( 'clientflow_license_key', '' ),
			'stripe_pk'     => get_option( 'clientflow_stripe_publishable_key', '' ),
			'business_name' => get_option( 'clientflow_business_name', '' ),
			'brand_color'   => get_option( 'clientflow_brand_color', '#6366f1' ),
			'logo_url'      => get_option( 'clientflow_logo_url', '' ),
		],
	], 200 );
}

/**
 * Save one step's worth of settings and advance the stored step pointer.
 */
function cf_onboarding_save( WP_REST_Request $request ): WP_REST_Response {
	$map = [
		'license_key'           => 'clientflow_license_key',
		'stripe_pk'             => 'clientflow_stripe_publishable_key',
		'stripe_sk'             => 'clientflow_stripe_secret_key',
		'stripe_webhook_secret' => 'clientflow_stripe_webhook_secret',
		'business_name'         => 'clientflow_business_name',
		'brand_color'           => 'clientflow_brand_color',
		'logo_url'              => 'clientflow_logo_url',
	];

	foreach ( $map as $param => $option ) {
		$value = $request->get_param( $param );
		if ( null !== $value && '' !== $value ) {
			update_option( $option, $value );
		}
	}

	$step = $request->get_param( 'step' );
	if ( null !== $step ) {
		$current = (int) get_option( 'clientflow_onboarding_step', 0 );
		if ( (int) $step >= $current ) {
			update_option( 'clientflow_onboarding_step', (int) $step );
		}
	}

	return new WP_REST_Response( [
		'success' => true,
		'step'    => (int) get_option( 'clientflow_onboarding_step', 0 ),
	], 200 );
}

/**
 * Mark onboarding as complete.
 */
function cf_onboarding_complete( WP_REST_Request $request ): WP_REST_Response {
	update_option( 'clientflow_onboarding_complete', gmdate( 'c' ) );

	return new WP_REST_Response( [ 'success' => true ], 200 );
}
