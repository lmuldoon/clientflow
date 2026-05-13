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
	add_rewrite_tag( '%clientflow_proposal_token%', '([a-zA-Z0-9\-]+)' );
	// Payment result: 'success' | 'cancel'
	add_rewrite_tag( '%clientflow_payment_result%', '(success|cancel)' );
	// Preview token — registered before the generic proposal rule so it matches first.
	add_rewrite_tag( '%clientflow_preview_token%', '([a-zA-Z0-9\-]+)' );

	// /proposals/preview/{token}[/]  — internal preview viewer (read-only).
	add_rewrite_rule(
		'^proposals/preview/([a-zA-Z0-9\-]+)/?$',
		'index.php?clientflow_preview_token=$matches[1]',
		'top'
	);

	// /proposals/{token}/[/]  — proposal viewer.
	add_rewrite_rule(
		'^proposals/([a-zA-Z0-9\-]+)/?$',
		'index.php?clientflow_proposal_token=$matches[1]',
		'top'
	);

	// /proposals/{token}/success[/]  — payment success page.
	add_rewrite_rule(
		'^proposals/([a-zA-Z0-9\-]+)/success/?$',
		'index.php?clientflow_proposal_token=$matches[1]&clientflow_payment_result=success',
		'top'
	);

	// /proposals/{token}/cancel[/]  — payment cancelled page.
	add_rewrite_rule(
		'^proposals/([a-zA-Z0-9\-]+)/cancel/?$',
		'index.php?clientflow_proposal_token=$matches[1]&clientflow_payment_result=cancel',
		'top'
	);
}, 10 );

// ── Serve the standalone client template ──────────────────────────────────────

add_action( 'template_redirect', static function (): void {
	$template = CLIENTFLOW_DIR . 'client/template.php';

	// ── Preview URL: /proposals/preview/{token} ──────────────────────────────
	$preview_token = get_query_var( 'clientflow_preview_token' );

	if ( $preview_token ) {
		if ( ! file_exists( $template ) ) {
			wp_die(
				esc_html__( 'Proposal viewer template not found.', 'clientflow' ),
				esc_html__( 'Error', 'clientflow' ),
				[ 'response' => 500 ]
			);
		}

		$clientflow_preview_token = sanitize_text_field( $preview_token );

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		include $template;
		exit;
	}

	// ── Standard proposal URL: /proposals/{token} ────────────────────────────
	$token = get_query_var( 'clientflow_proposal_token' );

	if ( ! $token ) {
		return;
	}

	if ( ! file_exists( $template ) ) {
		wp_die(
			esc_html__( 'Proposal viewer template not found.', 'clientflow' ),
			esc_html__( 'Error', 'clientflow' ),
			[ 'response' => 500 ]
		);
	}

	// Pass sanitised variables to the template.
	$clientflow_proposal_token  = sanitize_text_field( $token );
	$clientflow_payment_result  = sanitize_key( get_query_var( 'clientflow_payment_result', '' ) );
	// Stripe passes ?session_id=cs_xxx on the success redirect.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only Stripe redirect parameter, no state change occurs here.
	$clientflow_session_id      = sanitize_text_field( wp_unslash( $_GET['session_id'] ?? '' ) );

	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	include $template;
	exit;
}, 1 );
