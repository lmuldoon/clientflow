<?php
/**
 * Approval Model
 *
 * Handles approval request creation and client responses.
 * Approvals are project-scoped and agency-tier gated.
 *
 * @package ClientFlow\Approvals
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClientFlow_Approval
 */
class ClientFlow_Approval {

	private const TABLE = 'clientflow_approvals';

	/** Valid approval types. */
	public const TYPES = [ 'design', 'content', 'deliverable', 'other' ];

	/** Valid response statuses. */
	public const RESPONSE_STATUSES = [ 'approved', 'rejected' ];

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	// ── Create ────────────────────────────────────────────────────────────────

	/**
	 * Create a new approval request.
	 *
	 * @param int   $project_id
	 * @param int   $owner_id
	 * @param array $data { type, description }
	 *
	 * @return int|WP_Error New approval ID.
	 */
	public static function create( int $project_id, int $owner_id, array $data ): int|WP_Error {
		global $wpdb;

		$project = self::get_project( $project_id, $owner_id );
		if ( is_wp_error( $project ) ) {
			return $project;
		}

		$type = sanitize_text_field( $data['type'] ?? 'other' );
		if ( ! in_array( $type, self::TYPES, true ) ) {
			$type = 'other';
		}

		$description = sanitize_textarea_field( $data['description'] ?? '' );

		$now = current_time( 'mysql' );

		$wpdb->insert(
			self::table(),
			[
				'project_id'   => $project_id,
				'type'         => $type,
				'description'  => $description ?: null,
				'status'       => 'pending',
				'requested_by' => $owner_id,
				'created_at'   => $now,
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to create approval request.', 'clientflow' ), [ 'status' => 500 ] );
		}

		$id = (int) $wpdb->insert_id;

		// Notify client.
		self::notify_client( $id );

		return $id;
	}

	// ── Read ──────────────────────────────────────────────────────────────────

	/**
	 * List approvals for a project (admin).
	 *
	 * @param int $project_id
	 * @param int $owner_id
	 *
	 * @return array
	 */
	public static function list( int $project_id, int $owner_id ): array {
		global $wpdb;

		$project = self::get_project( $project_id, $owner_id );
		if ( is_wp_error( $project ) ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE project_id = %d ORDER BY created_at DESC",
				$project_id
			),
			ARRAY_A
		);

		return array_map( [ __CLASS__, 'prepare_row' ], $rows ?: [] );
	}

	/**
	 * Get a single approval (admin).
	 *
	 * @param int $id
	 * @param int $owner_id
	 *
	 * @return array|WP_Error
	 */
	public static function get( int $id, int $owner_id ): array|WP_Error {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.* FROM " . self::table() . " a
				 INNER JOIN {$wpdb->prefix}clientflow_projects p ON a.project_id = p.id
				 WHERE a.id = %d AND p.owner_id = %d",
				$id,
				$owner_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'approval_not_found', __( 'Approval not found.', 'clientflow' ), [ 'status' => 404 ] );
		}

		return self::prepare_row( $row );
	}

	/**
	 * List approvals for a project — client portal access.
	 *
	 * @param int $project_id
	 * @param int $client_wp_user_id
	 *
	 * @return array|WP_Error
	 */
	public static function get_for_client( int $project_id, int $client_wp_user_id ): array|WP_Error {
		global $wpdb;

		if ( ! self::client_owns_project( $project_id, $client_wp_user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Access denied.', 'clientflow' ), [ 'status' => 403 ] );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE project_id = %d ORDER BY created_at DESC",
				$project_id
			),
			ARRAY_A
		);

		return array_map( [ __CLASS__, 'prepare_row' ], $rows ?: [] );
	}

	// ── Respond ───────────────────────────────────────────────────────────────

	/**
	 * Client responds to an approval request.
	 *
	 * @param int    $id                  Approval ID.
	 * @param int    $client_wp_user_id   WP user ID of the responding client.
	 * @param string $status              'approved' or 'rejected'.
	 * @param string $comment             Optional comment from client.
	 *
	 * @return array|WP_Error Updated approval row.
	 */
	public static function respond( int $id, int $client_wp_user_id, string $status, string $comment = '' ): array|WP_Error {
		global $wpdb;

		if ( ! in_array( $status, self::RESPONSE_STATUSES, true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid response status.', 'clientflow' ), [ 'status' => 400 ] );
		}

		// Load approval and verify access.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table() . " WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'approval_not_found', __( 'Approval not found.', 'clientflow' ), [ 'status' => 404 ] );
		}

		if ( ! self::client_owns_project( (int) $row['project_id'], $client_wp_user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Access denied.', 'clientflow' ), [ 'status' => 403 ] );
		}

		if ( 'pending' !== $row['status'] ) {
			return new WP_Error(
				'already_responded',
				__( 'This approval has already been responded to.', 'clientflow' ),
				[ 'status' => 422 ]
			);
		}

		$now     = current_time( 'mysql' );
		$comment = sanitize_textarea_field( $comment );

		$update = [
			'status'         => $status,
			'approved_by'    => $client_wp_user_id,
			'responded_at'   => $now,
		];

		if ( '' !== $comment ) {
			$update['client_comment'] = $comment;
		}

		$wpdb->update(
			self::table(),
			$update,
			[ 'id' => $id ]
		);

		// Notify the owner.
		self::notify_owner( $id, $status );

		$updated = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ),
			ARRAY_A
		);

		return self::prepare_row( $updated );
	}

	// ── Delete ────────────────────────────────────────────────────────────────

	/**
	 * Delete an approval request (admin only).
	 *
	 * @param int $id
	 * @param int $owner_id
	 *
	 * @return true|WP_Error
	 */
	public static function delete( int $id, int $owner_id ): true|WP_Error {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE a FROM " . self::table() . " a
				 INNER JOIN {$wpdb->prefix}clientflow_projects p ON a.project_id = p.id
				 WHERE a.id = %d AND p.owner_id = %d",
				$id,
				$owner_id
			)
		);

		if ( ! $result ) {
			return new WP_Error( 'approval_not_found', __( 'Approval not found.', 'clientflow' ), [ 'status' => 404 ] );
		}

		return true;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Prepare a raw DB row for API responses.
	 */
	public static function prepare_row( array $row ): array {
		$row['id']           = (int) $row['id'];
		$row['project_id']   = (int) $row['project_id'];
		$row['requested_by'] = $row['requested_by'] ? (int) $row['requested_by'] : null;
		$row['approved_by']  = $row['approved_by']  ? (int) $row['approved_by']  : null;

		return $row;
	}

	/**
	 * Verify a project belongs to the given owner.
	 */
	private static function get_project( int $project_id, int $owner_id ): array|WP_Error {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}clientflow_projects WHERE id = %d AND owner_id = %d",
				$project_id,
				$owner_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'project_not_found', __( 'Project not found.', 'clientflow' ), [ 'status' => 404 ] );
		}

		return $row;
	}

	/**
	 * Check whether a WP user is the client assigned to a project.
	 */
	private static function client_owns_project( int $project_id, int $client_wp_user_id ): bool {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_projects p
				 INNER JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
				 WHERE p.id = %d AND c.wp_user_id = %d",
				$project_id,
				$client_wp_user_id
			)
		);

		return $count > 0;
	}

	/**
	 * Send notification email to the project owner when client responds.
	 */
	private static function notify_owner( int $approval_id, string $status ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.type, a.description, p.name AS project_name,
				        u.user_email AS owner_email,
				        c.name AS client_name
				 FROM " . self::table() . " a
				 INNER JOIN {$wpdb->prefix}clientflow_projects p ON a.project_id = p.id
				 INNER JOIN {$wpdb->users} u ON p.owner_id = u.ID
				 LEFT  JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
				 WHERE a.id = %d",
				$approval_id
			),
			ARRAY_A
		);

		if ( ! $row || ! $row['owner_email'] ) {
			return;
		}

		$client  = $row['client_name'] ?: __( 'Your client', 'clientflow' );
		$label   = 'approved' === $status ? __( 'approved', 'clientflow' ) : __( 'requested changes on', 'clientflow' );
		$subject = sprintf( __( 'Approval %s: %s — %s', 'clientflow' ), ucfirst( $status ), $row['type'], $row['project_name'] );
		$body    = sprintf(
			__( "%s has %s the approval request for \"%s\" on project \"%s\".\n\nLog in to view details.", 'clientflow' ),
			$client,
			$label,
			$row['description'] ?: $row['type'],
			$row['project_name']
		);

		wp_mail( $row['owner_email'], $subject, $body );
	}

	/**
	 * Notify the assigned client that a new approval request was created.
	 */
	private static function notify_client( int $approval_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.type, a.description, p.name AS project_name,
				        c.email AS client_email, c.name AS client_name
				 FROM " . self::table() . " a
				 INNER JOIN {$wpdb->prefix}clientflow_projects p ON a.project_id = p.id
				 LEFT  JOIN {$wpdb->prefix}clientflow_clients c ON p.client_id = c.id
				 WHERE a.id = %d",
				$approval_id
			),
			ARRAY_A
		);

		if ( ! $row || ! $row['client_email'] ) {
			return;
		}

		$project_name = esc_html( $row['project_name'] );
		$type_label   = esc_html( ucfirst( $row['type'] ) );
		$description  = $row['description'] ? '<p style="margin:12px 0 0;font-size:14px;color:#6B7280;font-style:italic;">' . esc_html( $row['description'] ) . '</p>' : '';

		$subject  = sprintf( __( 'Action Required: Please review — %s', 'clientflow' ), $row['project_name'] );
		$body_html = "
			<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
				A new approval has been requested on your project <strong style=\"color:#1A1A2E;\">{$project_name}</strong>.
			</p>
			<div style=\"margin:20px 0;padding:16px 20px;background:#F8F7FF;border-radius:10px;border-left:3px solid #6366F1;\">
				<p style=\"margin:0;font-size:13px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#6366F1;\">{$type_label}</p>
				{$description}
			</div>
			<p style=\"margin:0;font-size:16px;color:#6B7280;line-height:1.65;\">
				Please log in to your portal to review and respond.
			</p>";

		$message = cf_email_html( [
			'name'      => $row['client_name'] ?: '',
			'body'      => $body_html,
			'cta_label' => __( 'Review & Respond', 'clientflow' ),
			'cta_url'   => home_url( '/portal/' ),
		] );

		wp_mail( $row['client_email'], $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
}
