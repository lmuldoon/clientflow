<?php
/**
 * Proposal Model
 *
 * Handles all CRUD operations for the clientflow_proposals table.
 * Every write operation checks entitlements before acting.
 *
 * @package ClientFlow\Proposals
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClientFlow_Proposal
 */
class ClientFlow_Proposal {

	// ── Schema ────────────────────────────────────────────────────────────────

	/**
	 * Valid proposal status values.
	 *
	 * @var string[]
	 */
	public const STATUSES = [ 'draft', 'sent', 'viewed', 'accepted', 'declined', 'expired', 'completed' ];

	/**
	 * Table name (without $wpdb->prefix — use self::table() in queries).
	 *
	 * @var string
	 */
	private const TABLE = 'clientflow_proposals';

	/**
	 * Return the full prefixed table name.
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	// ── Create ────────────────────────────────────────────────────────────────

	/**
	 * Create a new proposal.
	 *
	 * Enforces the free-tier 5-proposal limit via cf_can_user().
	 * Increments the usage counter after a successful insert.
	 *
	 * @param int   $owner_id WordPress user ID of the creator.
	 * @param array $data     Proposal fields (see $defaults).
	 *
	 * @return int|WP_Error New proposal ID, or WP_Error on failure.
	 */
	public static function create( int $owner_id, array $data ): int|WP_Error {
		global $wpdb;

		// ── Entitlement check ────────────────────────────────────────────────
		if ( ! cf_can_user( $owner_id, 'create_proposal' ) ) {
			return new WP_Error(
				'proposal_limit_reached',
				__( 'You have reached your proposal limit. Upgrade to Pro for unlimited proposals.', 'clientflow' ),
				[ 'status' => 403 ]
			);
		}

		$now      = current_time( 'mysql' );
		$defaults = [
			'owner_id'        => $owner_id,
			'client_id'       => null,
			'title'           => '',
			'content'         => null,
			'token'           => self::generate_token(),
			'status'          => 'draft',
			'total_amount'    => null,
			'currency'        => 'GBP',
			'payment_enabled' => 0,
			'expiry_date'     => null,
			'template_id'     => null,
			'created_at'      => $now,
			'updated_at'      => $now,
		];

		$row = array_merge( $defaults, array_intersect_key( $data, $defaults ) );

		// Always regenerate token — never allow caller to set it.
		$row['token'] = self::generate_token();

		// Enable payment if owner has Pro/Agency.
		if ( cf_can_user( $owner_id, 'use_payments' ) ) {
			$row['payment_enabled'] = 1;
		}

		$inserted = $wpdb->insert( self::table(), $row );

		if ( false === $inserted ) {
			return new WP_Error(
				'db_insert_failed',
				__( 'Failed to create proposal.', 'clientflow' ),
				[ 'status' => 500 ]
			);
		}

		$id = (int) $wpdb->insert_id;

		// ── Log usage ────────────────────────────────────────────────────────
		ClientFlow_Entitlements::log_usage( $owner_id, 'create_proposal' );

		return $id;
	}

	// ── Read ──────────────────────────────────────────────────────────────────

	/**
	 * Get a single proposal by ID.
	 *
	 * @param int $id
	 * @param int $owner_id Used to scope ownership (non-admins see own only).
	 *
	 * @return array|WP_Error
	 */
	public static function get( int $id, int $owner_id = 0 ): array|WP_Error {
		global $wpdb;

		$t = self::table();

		$sql = $owner_id
			? $wpdb->prepare(
				"SELECT p.*, c.name AS client_name, c.email AS client_email,
				        c.company AS client_company, c.phone AS client_phone
				 FROM $t p
				 LEFT JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
				 WHERE p.id = %d AND p.owner_id = %d AND p.deleted_at IS NULL",
				$id,
				$owner_id
			)
			: $wpdb->prepare(
				"SELECT p.*, c.name AS client_name, c.email AS client_email,
				        c.company AS client_company, c.phone AS client_phone
				 FROM $t p
				 LEFT JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
				 WHERE p.id = %d AND p.deleted_at IS NULL",
				$id
			);

		$row = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! $row ) {
			return new WP_Error(
				'proposal_not_found',
				__( 'Proposal not found.', 'clientflow' ),
				[ 'status' => 404 ]
			);
		}

		return self::prepare_row( $row );
	}

	/**
	 * List proposals for a user with optional filters.
	 *
	 * @param int   $owner_id
	 * @param array $args {
	 *     Optional query args.
	 *
	 *     @type string $status   Filter by status.
	 *     @type string $search   Search title/client name.
	 *     @type int    $page     Page number (1-based).
	 *     @type int    $per_page Results per page (max 100).
	 *     @type string $orderby  Column to sort by.
	 *     @type string $order    'ASC' or 'DESC'.
	 * }
	 *
	 * @return array { proposals: [], total: int, pages: int }
	 */
	public static function list( int $owner_id, array $args = [] ): array {
		global $wpdb;

		$status   = $args['status']   ?? '';
		$search   = $args['search']   ?? '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$orderby  = in_array( $args['orderby'] ?? 'created_at', [ 'created_at', 'updated_at', 'title', 'status', 'total_amount' ], true )
			? $args['orderby']
			: 'created_at';
		$order    = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		$offset   = ( $page - 1 ) * $per_page;
		$t        = self::table();

		// Build WHERE — all column refs must use the 'p.' alias because the
		// main SELECT joins clientflow_clients (which also has owner_id).
		$where = [ $wpdb->prepare( "p.owner_id = %d", $owner_id ), "p.deleted_at IS NULL" ];

		if ( $status && in_array( $status, self::STATUSES, true ) ) {
			$where[] = $wpdb->prepare( "p.status = %s", $status );
		}

		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = $wpdb->prepare( "(p.title LIKE %s OR p.client_id IN (SELECT id FROM {$wpdb->prefix}clientflow_clients WHERE name LIKE %s))", $like, $like );
		}

		$where_sql = implode( ' AND ', $where );

		// Total count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t p WHERE $where_sql" );

		// Rows.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT p.*, c.name AS client_name, c.email AS client_email, c.company AS client_company
			 FROM $t p
			 LEFT JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
			 WHERE $where_sql
			 ORDER BY p.$orderby $order
			 LIMIT $per_page OFFSET $offset",
			ARRAY_A
		);

		return [
			'proposals' => array_map( [ __CLASS__, 'prepare_row' ], $rows ?: [] ),
			'total'     => $total,
			'pages'     => (int) ceil( $total / $per_page ),
			'page'      => $page,
			'per_page'  => $per_page,
		];
	}

	// ── Update ────────────────────────────────────────────────────────────────

	/**
	 * Update a proposal.
	 *
	 * Sent/viewed/accepted/declined proposals may still have some fields updated
	 * (e.g. expiry_date), but their status cannot be rolled back.
	 *
	 * @param int   $id       Proposal ID.
	 * @param int   $owner_id Ownership check.
	 * @param array $data     Fields to update.
	 *
	 * @return true|WP_Error
	 */
	public static function update( int $id, int $owner_id, array $data ): true|WP_Error {
		global $wpdb;

		$allowed = [
			'title', 'content', 'total_amount', 'currency',
			'expiry_date', 'client_id', 'status', 'template_id',
		];

		$update = array_intersect_key( $data, array_flip( $allowed ) );

		if ( empty( $update ) ) {
			return new WP_Error( 'no_data', __( 'No valid fields to update.', 'clientflow' ), [ 'status' => 400 ] );
		}

		// Auto-reset declined proposals to draft when content is being edited,
		// unless the caller is explicitly setting a different status.
		if ( ! isset( $update['status'] ) ) {
			$current_status = $wpdb->get_var(
				$wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE id = %d AND owner_id = %d', $id, $owner_id )
			);
			if ( 'declined' === $current_status ) {
				$update['status'] = 'draft';
			}
		}

		$update['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			self::table(),
			$update,
			[ 'id' => $id, 'owner_id' => $owner_id ]
		);

		if ( false === $result ) {
			return new WP_Error( 'db_update_failed', __( 'Failed to update proposal.', 'clientflow' ), [ 'status' => 500 ] );
		}

		if ( 0 === $result ) {
			// Either not found or no change — verify existence.
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::table() . " WHERE id = %d AND owner_id = %d", $id, $owner_id ) );
			if ( ! $exists ) {
				return new WP_Error( 'proposal_not_found', __( 'Proposal not found.', 'clientflow' ), [ 'status' => 404 ] );
			}
		}

		return true;
	}

	// ── Send ──────────────────────────────────────────────────────────────────

	/**
	 * Mark a proposal as sent and record the sent timestamp.
	 *
	 * @param int    $id       Proposal ID.
	 * @param int    $owner_id
	 * @param string $client_email Email to send to (for notification in Sprint 3).
	 *
	 * @return true|WP_Error
	 */
	public static function send( int $id, int $owner_id, string $client_email = '' ): true|WP_Error {
		global $wpdb;

		$proposal = self::get( $id, $owner_id );

		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		if ( 'draft' !== $proposal['status'] ) {
			return new WP_Error(
				'invalid_status',
				__( 'Only draft proposals can be sent.', 'clientflow' ),
				[ 'status' => 422 ]
			);
		}

		$now = current_time( 'mysql' );

		$wpdb->update(
			self::table(),
			[
				'status'     => 'sent',
				'sent_at'    => $now,
				'updated_at' => $now,
			],
			[ 'id' => $id, 'owner_id' => $owner_id ]
		);

		// Log view event.
		self::log_event( $id, 'sent' );

		// Allow modules (e.g. portal) to react to a proposal being sent.
		do_action( 'cf_proposal_sent', $id, $owner_id );

		return true;
	}

	// ── Delete ────────────────────────────────────────────────────────────────

	/**
	 * Delete a proposal.
	 *
	 * Only draft or declined proposals can be deleted.
	 *
	 * @param int $id
	 * @param int $owner_id
	 *
	 * @return true|WP_Error
	 */
	public static function delete( int $id, int $owner_id ): true|WP_Error {
		global $wpdb;

		$proposal = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status FROM " . self::table() . " WHERE id = %d AND owner_id = %d AND deleted_at IS NULL",
				$id,
				$owner_id
			)
		);

		if ( ! $proposal ) {
			return new WP_Error( 'proposal_not_found', __( 'Proposal not found.', 'clientflow' ), [ 'status' => 404 ] );
		}

		if ( 'accepted' === $proposal->status ) {
			return new WP_Error(
				'proposal_accepted',
				__( 'This proposal has an active project. Delete the project first if you want to remove it.', 'clientflow' ),
				[ 'status' => 422 ]
			);
		}

		// Soft-delete: stamp deleted_at so the row is preserved for analytics and project references.
		$wpdb->update(
			self::table(),
			[ 'deleted_at' => current_time( 'mysql' ) ],
			[ 'id' => $id, 'owner_id' => $owner_id ],
			[ '%s' ],
			[ '%d', '%d' ]
		);

		// Decrement the monthly plan-limit counter only — not the lifetime analytics total.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}clientflow_user_meta
				 SET proposal_count_month = GREATEST(0, proposal_count_month - 1),
				     updated_at           = %s
				 WHERE user_id = %d",
				current_time( 'mysql' ),
				$owner_id
			)
		);

		return true;
	}

	// ── Duplicate ─────────────────────────────────────────────────────────────

	/**
	 * Duplicate an existing proposal.
	 *
	 * Creates a new draft copy with "Copy of " prefix on the title.
	 * Entitlement check applies (free users still limited to 5 total).
	 *
	 * @param int $id
	 * @param int $owner_id
	 *
	 * @return int|WP_Error New proposal ID.
	 */
	public static function duplicate( int $id, int $owner_id ): int|WP_Error {
		$source = self::get( $id, $owner_id );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$new_data = [
			'title'        => __( 'Copy of ', 'clientflow' ) . $source['title'],
			'content'      => is_array( $source['content'] ) ? wp_json_encode( $source['content'] ) : $source['content'],
			'total_amount' => $source['total_amount'],
			'currency'     => $source['currency'],
			'expiry_date'  => null, // Reset expiry on duplicate.
			'client_id'    => $source['client_id'],
		];

		return self::create( $owner_id, $new_data );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Generate a cryptographically random public token for a proposal.
	 *
	 * Used in the client-facing URL: /proposals/{token}
	 * Format: UUID v4 via wp_generate_uuid4().
	 *
	 * @return string
	 */
	private static function generate_token(): string {
		return wp_generate_uuid4();
	}

	/**
	 * Prepare a raw database row for the API response.
	 *
	 * Casts types and decodes JSON content.
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public static function prepare_row( array $row ): array {
		$row['id']              = (int) $row['id'];
		$row['owner_id']        = (int) $row['owner_id'];
		$row['client_id']       = $row['client_id'] ? (int) $row['client_id'] : null;
		$row['total_amount']    = $row['total_amount'] !== null ? (float) $row['total_amount'] : null;
		$row['payment_enabled'] = (bool) $row['payment_enabled'];
		$row['decline_reason']  = $row['decline_reason'] ?? null;

		// Normalise expiry_date: the column is DATETIME but the date picker
		// needs exactly YYYY-MM-DD. Strip the time component if present.
		if ( ! empty( $row['expiry_date'] ) ) {
			$row['expiry_date'] = substr( $row['expiry_date'], 0, 10 );
		}

		// Decode JSON content block.
		if ( is_string( $row['content'] ) ) {
			$decoded = json_decode( $row['content'], true );
			$row['content'] = is_array( $decoded ) ? $decoded : [];
		}

		return $row;
	}

	/**
	 * Log an event to clientflow_events.
	 *
	 * @param int    $proposal_id
	 * @param string $event_type
	 * @param array  $metadata
	 */
	private static function log_event( int $proposal_id, string $event_type, array $metadata = [] ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'clientflow_events',
			[
				'proposal_id' => $proposal_id,
				'event_type'  => $event_type,
				'user_ip'     => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
				'user_agent'  => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
				'timestamp'   => current_time( 'mysql' ),
				'metadata'    => $metadata ? wp_json_encode( $metadata ) : null,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}
}
