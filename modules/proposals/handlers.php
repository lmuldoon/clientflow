<?php
/**
 * Proposal Business Logic Handlers
 *
 * Orchestrates higher-level operations that span multiple models:
 *   - create_from_wizard()   — creates proposal + client in one call
 *   - send_to_client()       — marks sent + triggers notification email
 *   - process_line_items()   — validates and stores pricing data
 *   - expire_overdue()       — cron job to auto-expire old proposals
 *
 * These are called by REST route callbacks, not accessed directly.
 *
 * @package ClientFlow\Proposals
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClientFlow_Proposal_Handlers
 */
class ClientFlow_Proposal_Handlers {

	// ── Wizard Create ─────────────────────────────────────────────────────────

	/**
	 * Create a proposal from the 5-step wizard payload.
	 *
	 * Handles:
	 *   1. Template access check.
	 *   2. Client record lookup or creation.
	 *   3. Line-item calculation to determine total_amount.
	 *   4. Proposal insertion via ClientFlow_Proposal::create().
	 *
	 * @param int   $owner_id WordPress user ID of the creator.
	 * @param array $payload  Validated wizard data (from REST request body).
	 *
	 * @return array|WP_Error The created proposal row, or WP_Error on failure.
	 */
	public static function create_from_wizard( int $owner_id, array $payload ): array|WP_Error {
		// ── Template access ──────────────────────────────────────────────────
		$template_id = sanitize_key( $payload['template_id'] ?? 'blank' );

		if ( ! ClientFlow_Proposal_Template::user_can_access( $owner_id, $template_id ) ) {
			return new WP_Error(
				'template_locked',
				__( 'This template requires a Pro plan.', 'clientflow' ),
				[ 'status' => 403 ]
			);
		}

		// ── Client record ────────────────────────────────────────────────────
		$client_id = self::resolve_client(
			$owner_id,
			sanitize_text_field( $payload['client_name']    ?? '' ),
			sanitize_email(      $payload['client_email']   ?? '' ),
			sanitize_text_field( $payload['client_company'] ?? '' ),
			sanitize_text_field( $payload['client_phone']   ?? '' )
		);

		if ( is_wp_error( $client_id ) ) {
			return $client_id;
		}

		// ── Line items → total ───────────────────────────────────────────────
		$line_items   = is_array( $payload['line_items'] ?? null ) ? $payload['line_items'] : [];
		$discount_pct = (float) ( $payload['discount_pct'] ?? 0 );
		$vat_pct      = (float) ( $payload['vat_pct']      ?? 0 );
		$total_amount = self::calculate_total( $line_items, $discount_pct, $vat_pct );

		// ── Build content block ──────────────────────────────────────────────
		// Merge template default sections with wizard pricing data.
		$default_content = json_decode( ClientFlow_Proposal_Template::default_content( $template_id ), true ) ?: [];
		$content         = array_merge( $default_content, [
			'line_items'   => self::sanitize_line_items( $line_items ),
			'discount_pct' => $discount_pct,
			'vat_pct'      => $vat_pct,
		] );

		// ── Expiry date ──────────────────────────────────────────────────────
		$expiry_date = ! empty( $payload['expiry_date'] )
			? sanitize_text_field( $payload['expiry_date'] )
			: gmdate( 'Y-m-d', strtotime( '+30 days' ) );

		// ── Create proposal ──────────────────────────────────────────────────
		$proposal_id = ClientFlow_Proposal::create( $owner_id, [
			'client_id'      => $client_id,
			'title'          => sanitize_text_field( $payload['title'] ?? __( 'Untitled Proposal', 'clientflow' ) ),
			'content'        => wp_json_encode( $content ),
			'total_amount'   => $total_amount,
			'currency'       => strtoupper( sanitize_text_field( $payload['currency'] ?? 'GBP' ) ),
			'expiry_date'    => $expiry_date,
			'template_id'    => $template_id,
		] );

		if ( is_wp_error( $proposal_id ) ) {
			return $proposal_id;
		}

		// ── Return full row ──────────────────────────────────────────────────
		return ClientFlow_Proposal::get( $proposal_id, $owner_id );
	}

	// ── Wizard Update ─────────────────────────────────────────────────────────

	/**
	 * Update an existing proposal from the wizard payload.
	 *
	 * Mirrors create_from_wizard() but calls update() instead of create(),
	 * so usage counters are NOT incremented again.
	 *
	 * @param int   $id       Proposal ID to update.
	 * @param int   $owner_id Ownership check.
	 * @param array $payload  Wizard form data.
	 *
	 * @return array|WP_Error Updated proposal row, or WP_Error.
	 */
	public static function update_from_wizard( int $id, int $owner_id, array $payload ): array|WP_Error {
		global $wpdb;

		// ── Client record ────────────────────────────────────────────────────
		$client_id = self::resolve_client(
			$owner_id,
			sanitize_text_field( $payload['client_name']    ?? '' ),
			sanitize_email(      $payload['client_email']   ?? '' ),
			sanitize_text_field( $payload['client_company'] ?? '' ),
			sanitize_text_field( $payload['client_phone']   ?? '' )
		);

		if ( is_wp_error( $client_id ) ) {
			return $client_id;
		}

		// If the client already exists and details may have changed, update them.
		if ( $client_id && ! empty( $payload['client_name'] ) ) {
			$wpdb->update(
				$wpdb->prefix . 'clientflow_clients',
				[
					'name'       => sanitize_text_field( $payload['client_name']    ?? '' ),
					'company'    => sanitize_text_field( $payload['client_company'] ?? '' ),
					'phone'      => sanitize_text_field( $payload['client_phone']   ?? '' ),
					'updated_at' => current_time( 'mysql' ),
				],
				[ 'id' => $client_id, 'owner_id' => $owner_id ]
			);
		}

		// ── Line items → total ───────────────────────────────────────────────
		$line_items   = is_array( $payload['line_items'] ?? null ) ? $payload['line_items'] : [];
		$discount_pct = (float) ( $payload['discount_pct'] ?? 0 );
		$vat_pct      = (float) ( $payload['vat_pct']      ?? 0 );
		$total_amount = self::calculate_total( $line_items, $discount_pct, $vat_pct );

		// ── Build content block ──────────────────────────────────────────────
		$template_id     = sanitize_key( $payload['template_id'] ?? 'blank' );
		$default_content = json_decode( ClientFlow_Proposal_Template::default_content( $template_id ), true ) ?: [];
		$content         = array_merge( $default_content, [
			'template_id'     => $template_id,
			'line_items'      => self::sanitize_line_items( $line_items ),
			'discount_pct'    => $discount_pct,
			'vat_pct'         => $vat_pct,
			'deposit_pct'     => (int) ( $payload['deposit_pct']    ?? 0 ),
			'require_deposit' => ! empty( $payload['require_deposit'] ),
		] );

		// ── Update proposal ──────────────────────────────────────────────────
		$result = ClientFlow_Proposal::update( $id, $owner_id, [
			'client_id'    => $client_id,
			'title'        => sanitize_text_field( $payload['title'] ?? __( 'Untitled Proposal', 'clientflow' ) ),
			'content'      => wp_json_encode( $content ),
			'total_amount' => $total_amount,
			'currency'     => strtoupper( sanitize_text_field( $payload['currency'] ?? 'GBP' ) ),
			'expiry_date'  => sanitize_text_field( $payload['expiry_date'] ?? '' ) ?: null,
			'template_id'  => $template_id,
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return ClientFlow_Proposal::get( $id, $owner_id );
	}

	// ── Send to Client ────────────────────────────────────────────────────────

	/**
	 * Send a proposal to a client: mark as sent + email notification.
	 *
	 * @param int    $proposal_id
	 * @param int    $owner_id
	 * @param string $client_email Override email (falls back to stored client email).
	 *
	 * @return true|WP_Error
	 */
	public static function send_to_client( int $proposal_id, int $owner_id, string $client_email = '' ): true|WP_Error {
		$result = ClientFlow_Proposal::send( $proposal_id, $owner_id, $client_email );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Fetch proposal + client data for notification email.
		$proposal = ClientFlow_Proposal::get( $proposal_id, $owner_id );

		if ( is_wp_error( $proposal ) ) {
			return true; // Sent successfully; notification is best-effort.
		}

		$recipient = $client_email ?: ( $proposal['client_email'] ?? '' );

		if ( $recipient ) {
			self::send_proposal_email( $proposal, $recipient );
		}

		return true;
	}

	// ── Expire Overdue ────────────────────────────────────────────────────────

	/**
	 * Auto-expire proposals whose expiry_date has passed.
	 *
	 * Intended to run as a daily WP-Cron job.
	 *
	 * @return int Number of proposals expired.
	 */
	public static function expire_overdue(): int {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}clientflow_proposals
				 SET status = 'expired', updated_at = %s
				 WHERE status IN ('draft','sent','viewed')
				   AND expiry_date IS NOT NULL
				   AND expiry_date < %s",
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);

		return (int) $result;
	}

	// ── Private Helpers ───────────────────────────────────────────────────────

	/**
	 * Resolve or create a client record.
	 *
	 * If a client with the same email already exists for this owner,
	 * returns their ID. Otherwise creates a new record.
	 *
	 * Returns null (not an error) if both name and email are empty —
	 * proposals without clients are allowed.
	 *
	 * @param int    $owner_id
	 * @param string $name
	 * @param string $email
	 * @param string $company
	 * @param string $phone
	 *
	 * @return int|null|WP_Error Client ID, null, or WP_Error.
	 */
	private static function resolve_client( int $owner_id, string $name, string $email, string $company, string $phone ): int|null|WP_Error {
		global $wpdb;

		if ( ! $name && ! $email ) {
			return null;
		}

		// Look for existing client by email.
		if ( $email ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}clientflow_clients
					 WHERE owner_id = %d AND email = %s
					 LIMIT 1",
					$owner_id,
					$email
				)
			);

			if ( $existing_id ) {
				return (int) $existing_id;
			}
		}

		// Create new client.
		$now = current_time( 'mysql' );

		$wpdb->insert(
			$wpdb->prefix . 'clientflow_clients',
			[
				'owner_id'   => $owner_id,
				'name'       => $name,
				'email'      => $email,
				'company'    => $company,
				'phone'      => $phone,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'client_create_failed', __( 'Failed to create client record.', 'clientflow' ), [ 'status' => 500 ] );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Calculate proposal grand total from line items.
	 *
	 * @param array $line_items  Array of { qty, unit_price }.
	 * @param float $discount_pct
	 * @param float $vat_pct
	 *
	 * @return float
	 */
	private static function calculate_total( array $line_items, float $discount_pct, float $vat_pct ): float {
		$subtotal     = 0.0;

		foreach ( $line_items as $item ) {
			$qty        = max( 0, (float) ( $item['qty']        ?? 0 ) );
			$unit_price = max( 0, (float) ( $item['unit_price'] ?? 0 ) );
			$subtotal  += $qty * $unit_price;
		}

		$discount_amt = $subtotal * ( $discount_pct / 100 );
		$after_disc   = $subtotal - $discount_amt;
		$vat_amt      = $after_disc * ( $vat_pct / 100 );

		return round( $after_disc + $vat_amt, 2 );
	}

	/**
	 * Sanitize and normalise line items array.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	private static function sanitize_line_items( array $items ): array {
		$clean = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean[] = [
				'id'          => sanitize_key( $item['id'] ?? uniqid( 'li_', true ) ),
				'description' => sanitize_text_field( $item['description'] ?? '' ),
				'qty'         => max( 0, (float) ( $item['qty'] ?? 1 ) ),
				'unit_price'  => max( 0, (float) ( $item['unit_price'] ?? 0 ) ),
			];
		}

		return $clean;
	}

	/**
	 * Send the proposal notification email to the client.
	 *
	 * @param array  $proposal
	 * @param string $recipient
	 *
	 * @return void
	 */
	private static function send_proposal_email( array $proposal, string $recipient ): void {
		$owner        = get_user_by( 'ID', $proposal['owner_id'] );
		$from         = $owner ? $owner->display_name : get_bloginfo( 'name' );
		$proposal_url = esc_url( get_site_url() . '/proposals/' . ( $proposal['token'] ?? $proposal['id'] ) );
		$title        = esc_html( $proposal['title'] );
		$from_esc     = esc_html( $from );
		$expiry       = esc_html( $proposal['expiry_date'] ?? __( 'the specified date', 'clientflow' ) );

		$subject   = sprintf( __( 'You have received a proposal: %s', 'clientflow' ), $proposal['title'] );
		$body_html = "
			<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
				<strong style=\"color:#1A1A2E;\">{$from_esc}</strong> has sent you a new proposal.
			</p>
			<div style=\"margin:20px 0;padding:16px 20px;background:#F8F7FF;border-radius:10px;border-left:3px solid #6366F1;\">
				<p style=\"margin:0;font-size:16px;font-weight:600;color:#1A1A2E;\">{$title}</p>
				<p style=\"margin:6px 0 0;font-size:13px;color:#9CA3AF;\">Expires {$expiry}</p>
			</div>
			<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
				Click below to review the proposal and accept or decline.
			</p>";

		$message = cf_email_html( [
			'name'      => $proposal['client_name'] ?? '',
			'body'      => $body_html,
			'cta_label' => __( 'View Proposal', 'clientflow' ),
			'cta_url'   => $proposal_url,
		] );

		wp_mail(
			$recipient,
			$subject,
			$message,
			[
				"From: {$from} <" . get_option( 'admin_email' ) . '>',
				'Content-Type: text/html; charset=UTF-8',
			]
		);
	}
}

// ─── Register daily expiry cron ───────────────────────────────────────────────
add_action( 'clientflow_expire_proposals', [ 'ClientFlow_Proposal_Handlers', 'expire_overdue' ] );

add_action( 'init', static function (): void {
	if ( ! wp_next_scheduled( 'clientflow_expire_proposals' ) ) {
		wp_schedule_event( time(), 'daily', 'clientflow_expire_proposals' );
	}
} );
