<?php
/**
 * Client Proposal Routing
 *
 * Registers a WordPress rewrite rule so that URLs like:
 *   https://yoursite.com/proposals/{token}
 * are served by the standalone ClientFlow client template — completely
 * bypassing the active theme.
 *
 * The token is a UUID4 generated when the proposal is created.
 *
 * Hooks registered:
 *   init              — add_rewrite_tag, add_rewrite_rule
 *   template_redirect — intercept matched requests and serve template
 *
 * NOTE: flush_rewrite_rules() is called on plugin activation (clientflow.php),
 * so the rule takes effect immediately after install.
 *
 * @package ClientFlow\Proposals
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Register rewrite tags + rules ────────────────────────────────────────────

add_action( 'init', static function (): void {
	// Primary token query var.
	add_rewrite_tag( '%cf_proposal_token%', '([a-zA-Z0-9\-]+)' );
	// Payment result: 'success' | 'cancel'
	add_rewrite_tag( '%cf_payment_result%', '(success|cancel)' );

	// /proposals/{token}/[/]  — proposal viewer.
	add_rewrite_rule(
		'^proposals/([a-zA-Z0-9\-]+)/?$',
		'index.php?cf_proposal_token=$matches[1]',
		'top'
	);

	// /proposals/{token}/success[/]  — payment success page.
	add_rewrite_rule(
		'^proposals/([a-zA-Z0-9\-]+)/success/?$',
		'index.php?cf_proposal_token=$matches[1]&cf_payment_result=success',
		'top'
	);

	// /proposals/{token}/cancel[/]  — payment cancelled page.
	add_rewrite_rule(
		'^proposals/([a-zA-Z0-9\-]+)/cancel/?$',
		'index.php?cf_proposal_token=$matches[1]&cf_payment_result=cancel',
		'top'
	);
}, 10 );

// ── Serve the standalone client template ──────────────────────────────────────

add_action( 'template_redirect', static function (): void {
	$token = get_query_var( 'cf_proposal_token' );

	if ( ! $token ) {
		return;
	}

	$template = CLIENTFLOW_DIR . 'client/template.php';

	if ( ! file_exists( $template ) ) {
		wp_die(
			esc_html__( 'Proposal viewer template not found.', 'clientflow' ),
			esc_html__( 'Error', 'clientflow' ),
			[ 'response' => 500 ]
		);
	}

	// Pass sanitised variables to the template.
	$cf_proposal_token  = sanitize_text_field( $token );
	$cf_payment_result  = sanitize_key( get_query_var( 'cf_payment_result', '' ) );
	// Stripe passes ?session_id=cs_xxx on the success redirect.
	$cf_session_id      = sanitize_text_field( $_GET['session_id'] ?? '' );

	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	include $template;
	exit;
}, 1 );
