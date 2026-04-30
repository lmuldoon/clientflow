<?php
/**
 * Client-Facing Proposal Model
 *
 * Handles all database operations performed by the client (not the owner).
 * Authentication is token-based — no WordPress user session is required.
 *
 * Operations:
 *   - get_by_token()  — fetch a proposal by its public token (safe subset of fields)
 *   - track_view()    — log a view event; transition sent → viewed on first open
 *   - accept()        — transition to accepted, log event, notify owner
 *   - decline()       — transition to declined, log event
 *
 * @package ClientFlow\Proposals
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClientFlow_Proposal_Client
 */
class ClientFlow_Proposal_Client {

	/**
	 * Fields returned to the client. Excludes owner_id and internal fields.
	 *
	 * @var string[]
	 */
	private const CLIENT_FIELDS = [
		'id', 'title', 'content', 'status',
		'total_amount', 'currency', 'payment_enabled',
		'expiry_date', 'sent_at', 'viewed_at', 'accepted_at', 'declined_at', 'created_at',
	];

	// ── Read ──────────────────────────────────────────────────────────────────

	/**
	 * Get a proposal by its public token.
	 *
	 * Joins the clients table to include client_name for personalisation.
	 *
	 * @param string $token Public UUID token.
	 *
	 * @return array|WP_Error Sanitised proposal row, or WP_Error on failure.
	 */
	public static function get_by_token( string $token ): array|WP_Error {
		global $wpdb;

		if ( ! $token ) {
			return new WP_Error( 'invalid_token', __( 'Invalid proposal token.', 'clientflow' ), [ 'status' => 400 ] );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, c.name AS client_name, c.email AS client_email,
				        u.user_email AS owner_email
				 FROM {$wpdb->prefix}clientflow_proposals p
				 LEFT JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
				 LEFT JOIN {$wpdb->users} u ON p.owner_id = u.ID
				 WHERE p.token = %s AND p.deleted_at IS NULL
				 LIMIT 1",
				$token
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error(
				'proposal_not_found',
				__( 'Proposal not found.', 'clientflow' ),
				[ 'status' => 404 ]
			);
		}

		return self::prepare_client_row( $row );
	}

	// ── Track View ────────────────────────────────────────────────────────────

	/**
	 * Log a view event.
	 *
	 * Transitions status from 'sent' → 'viewed' on first open and stamps viewed_at.
	 * Subsequent views are still logged to clientflow_events but do not change status.
	 *
	 * @param string $token      Public proposal token.
	 * @param string $ip         Client IP address.
	 * @param string $user_agent Client user-agent string.
	 *
	 * @return true|WP_Error
	 */
	public static function track_view( string $token, string $ip = '', string $user_agent = '' ): true|WP_Error {
		global $wpdb;

		$proposal = self::get_by_token( $token );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		$now = current_time( 'mysql' );

		// First open: transition sent → viewed.
		if ( 'sent' === $proposal['status'] ) {
			$wpdb->update(
				$wpdb->prefix . 'clientflow_proposals',
				[
					'status'     => 'viewed',
					'viewed_at'  => $now,
					'updated_at' => $now,
				],
				[ 'id' => $proposal['id'] ],
				[ '%s', '%s', '%s' ],
				[ '%d' ]
			);
		}

		// Always log the raw view event.
		$wpdb->insert(
			$wpdb->prefix . 'clientflow_events',
			[
				'proposal_id' => $proposal['id'],
				'event_type'  => 'viewed',
				'user_ip'     => sanitize_text_field( substr( $ip, 0, 45 ) ),
				'user_agent'  => sanitize_text_field( substr( $user_agent, 0, 500 ) ),
				'timestamp'   => $now,
				'metadata'    => null,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return true;
	}

	// ── Accept ────────────────────────────────────────────────────────────────

	/**
	 * Accept a proposal.
	 *
	 * Only proposals in draft / sent / viewed state can be accepted.
	 * Logs an event and sends a notification email to the owner.
	 *
	 * @param string $token Public proposal token.
	 *
	 * @return array|WP_Error Updated proposal row, or WP_Error on failure.
	 */
	public static function accept( string $token ): array|WP_Error {
		global $wpdb;

		$proposal = self::get_by_token( $token );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		if ( ! in_array( $proposal['status'], [ 'draft', 'sent', 'viewed' ], true ) ) {
			return new WP_Error(
				'invalid_status',
				__( 'This proposal cannot be accepted in its current state.', 'clientflow' ),
				[ 'status' => 422 ]
			);
		}

		$now = current_time( 'mysql' );

		$wpdb->update(
			$wpdb->prefix . 'clientflow_proposals',
			[
				'status'      => 'accepted',
				'accepted_at' => $now,
				'updated_at'  => $now,
			],
			[ 'id' => $proposal['id'] ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		$wpdb->insert(
			$wpdb->prefix . 'clientflow_events',
			[
				'proposal_id' => $proposal['id'],
				'event_type'  => 'accepted',
				'user_ip'     => sanitize_text_field( substr( $_SERVER['REMOTE_ADDR'] ?? '', 0, 45 ) ),
				'user_agent'  => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500 ) ),
				'timestamp'   => $now,
				'metadata'    => null,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		self::notify_owner( $proposal['id'], 'accepted' );

		// Look up owner_id so modules can react to acceptance.
		$owner_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT owner_id FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d",
				$proposal['id']
			)
		);
		do_action( 'cf_proposal_accepted', $proposal['id'], $owner_id );

		return self::get_by_token( $token );
	}

	// ── Decline ───────────────────────────────────────────────────────────────

	/**
	 * Decline a proposal.
	 *
	 * Only proposals in draft / sent / viewed state can be declined.
	 * Logs an event and sends a notification email to the owner.
	 *
	 * @param string $token Public proposal token.
	 *
	 * @return array|WP_Error Updated proposal row, or WP_Error on failure.
	 */
	public static function decline( string $token, string $reason = '' ): array|WP_Error {
		global $wpdb;

		$proposal = self::get_by_token( $token );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		if ( ! in_array( $proposal['status'], [ 'draft', 'sent', 'viewed' ], true ) ) {
			return new WP_Error(
				'invalid_status',
				__( 'This proposal cannot be declined in its current state.', 'clientflow' ),
				[ 'status' => 422 ]
			);
		}

		$now    = current_time( 'mysql' );
		$reason = sanitize_textarea_field( $reason );

		// Always update status first — this must succeed regardless of whether
		// the decline_reason column exists (it may not exist on older installs
		// that haven't run the latest dbDelta migration yet).
		$wpdb->update(
			$wpdb->prefix . 'clientflow_proposals',
			[
				'status'      => 'declined',
				'declined_at' => $now,
				'updated_at'  => $now,
			],
			[ 'id' => $proposal['id'] ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		// Separately store the reason — silently skipped if column doesn't exist yet
		// (older installs that haven't run the latest dbDelta migration).
		if ( '' !== $reason ) {
			$col_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$wpdb->prefix}clientflow_proposals LIKE %s",
					'decline_reason'
				)
			);
			if ( $col_exists ) {
				$wpdb->update(
					$wpdb->prefix . 'clientflow_proposals',
					[ 'decline_reason' => $reason ],
					[ 'id' => $proposal['id'] ],
					[ '%s' ],
					[ '%d' ]
				);
			}
		}

		$wpdb->insert(
			$wpdb->prefix . 'clientflow_events',
			[
				'proposal_id' => $proposal['id'],
				'event_type'  => 'declined',
				'user_ip'     => sanitize_text_field( substr( $_SERVER['REMOTE_ADDR'] ?? '', 0, 45 ) ),
				'user_agent'  => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500 ) ),
				'timestamp'   => $now,
				'metadata'    => '' !== $reason ? wp_json_encode( [ 'reason' => $reason ] ) : null,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		self::notify_owner( $proposal['id'], 'declined' );

		return self::get_by_token( $token );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Strip internal fields and cast types for the client API response.
	 *
	 * @param array $row Raw DB row (may include owner_id, token, etc).
	 *
	 * @return array
	 */
	private static function prepare_client_row( array $row ): array {
		$out = [];

		foreach ( self::CLIENT_FIELDS as $field ) {
			$out[ $field ] = $row[ $field ] ?? null;
		}

		// Join fields.
		$out['client_name']    = $row['client_name']    ?? null;
		$out['client_email']   = $row['client_email']   ?? null;
		$out['owner_email']    = $row['owner_email']    ?? null;
		$out['decline_reason'] = $row['decline_reason'] ?? null;

		// Cast types.
		$out['id']              = (int) ( $out['id'] ?? 0 );
		$out['total_amount']    = $out['total_amount'] !== null ? (float) $out['total_amount'] : null;
		$out['payment_enabled'] = (bool) $out['payment_enabled'];

		// Decode JSON content block.
		if ( is_string( $out['content'] ) ) {
			$decoded        = json_decode( $out['content'], true );
			$out['content'] = is_array( $decoded ) ? $decoded : [];
		}

		return $out;
	}

	/**
	 * Send a notification email to the proposal owner when a client acts.
	 *
	 * Best-effort — failures are silent so they don't block the API response.
	 *
	 * @param int    $proposal_id
	 * @param string $event 'accepted' | 'declined'
	 */
	private static function notify_owner( int $proposal_id, string $event ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.title, p.owner_id,
				        c.name  AS client_name,
				        u.user_email AS owner_email
				 FROM {$wpdb->prefix}clientflow_proposals p
				 LEFT JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
				 LEFT JOIN {$wpdb->prefix}users             u ON p.owner_id   = u.ID
				 WHERE p.id = %d",
				$proposal_id
			),
			ARRAY_A
		);

		if ( ! $row || ! $row['owner_email'] ) {
			return;
		}

		$client = $row['client_name'] ?: __( 'A client', 'clientflow' );

		if ( 'accepted' === $event ) {
			$subject = sprintf( __( '🎉 Proposal Accepted: %s', 'clientflow' ), $row['title'] );
			$body    = sprintf(
				__( "%s has accepted your proposal \"%s\".\n\nLog in to view and follow up.", 'clientflow' ),
				$client,
				$row['title']
			);
		} else {
			$subject = sprintf( __( 'Proposal Declined: %s', 'clientflow' ), $row['title'] );
			$body    = sprintf(
				__( "%s has declined your proposal \"%s\".\n\nLog in to view details.", 'clientflow' ),
				$client,
				$row['title']
			);
		}

		wp_mail( $row['owner_email'], $subject, $body );
	}
}
