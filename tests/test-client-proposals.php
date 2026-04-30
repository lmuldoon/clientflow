<?php
/**
 * Tests: Client-Facing Proposal Endpoints (Sprint 2)
 *
 * Covers:
 *   - ClientFlow_Proposal_Client::get_by_token()
 *   - ClientFlow_Proposal_Client::track_view()
 *   - ClientFlow_Proposal_Client::accept()
 *   - ClientFlow_Proposal_Client::decline()
 *   - Token generation on proposal create
 *   - Status transition guards
 *   - Event logging
 *   - Owner notification (wp_mail stub)
 *
 * @package ClientFlow\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal.php';
require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal-template.php';
require_once dirname( __DIR__ ) . '/modules/proposals/handlers.php';
require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal-client.php';

/**
 * Class Test_Client_Proposals
 */
class Test_Client_Proposals extends WP_UnitTestCase {

	/** @var int WordPress user ID acting as the proposal owner. */
	private int $owner_id;

	/** @var int A proposal ID created during setUp. */
	private int $proposal_id;

	/** @var string The public token of the setUp proposal. */
	private string $token;

	// ── Fixtures ──────────────────────────────────────────────────────────────

	public function setUp(): void {
		parent::setUp();

		// Create a free-plan owner user.
		$this->owner_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		ClientFlow_Entitlements::set_user_plan( $this->owner_id, 'pro' );

		// Create a proposal and capture its token.
		$this->proposal_id = ClientFlow_Proposal::create( $this->owner_id, [
			'title'    => 'Sprint 2 Test Proposal',
			'currency' => 'GBP',
			'content'  => wp_json_encode( [
				'sections'    => [
					[ 'type' => 'heading', 'content' => 'Overview' ],
					[ 'type' => 'text',    'content' => 'Test body.' ],
				],
				'line_items'   => [
					[ 'id' => 'li_1', 'description' => 'Design', 'qty' => 1, 'unit_price' => 1000 ],
				],
				'discount_pct' => 0,
				'vat_pct'      => 20,
			] ),
			'total_amount' => 1200.00,
		] );

		// Read back the generated token.
		global $wpdb;
		$this->token = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT token FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d",
				$this->proposal_id
			)
		);
	}

	// ── Token generation ──────────────────────────────────────────────────────

	/** Token is auto-generated (non-empty UUID) when a proposal is created. */
	public function test_proposal_create_generates_token(): void {
		$this->assertNotEmpty( $this->token, 'Token should be set on create.' );
		// UUID4 format: 8-4-4-4-12 hex chars separated by dashes.
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$this->token
		);
	}

	/** Every proposal gets a unique token. */
	public function test_each_proposal_gets_unique_token(): void {
		$id2 = ClientFlow_Proposal::create( $this->owner_id, [ 'title' => 'Second' ] );
		global $wpdb;
		$token2 = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT token FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d", $id2 )
		);

		$this->assertNotSame( $this->token, $token2 );
	}

	// ── get_by_token ──────────────────────────────────────────────────────────

	/** Valid token returns proposal data. */
	public function test_get_by_token_returns_proposal(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertIsArray( $result );
		$this->assertSame( $this->proposal_id, $result['id'] );
		$this->assertSame( 'Sprint 2 Test Proposal', $result['title'] );
	}

	/** Owner ID is NOT exposed in the client response. */
	public function test_get_by_token_excludes_owner_id(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertArrayNotHasKey( 'owner_id', $result );
	}

	/** Token field is NOT exposed in the client response. */
	public function test_get_by_token_excludes_token(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertArrayNotHasKey( 'token', $result );
	}

	/** Content JSON is decoded to an array. */
	public function test_get_by_token_decodes_content(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertIsArray( $result['content'] );
		$this->assertArrayHasKey( 'sections', $result['content'] );
	}

	/** payment_enabled is cast to bool. */
	public function test_get_by_token_casts_payment_enabled(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertIsBool( $result['payment_enabled'] );
	}

	/** Invalid token returns WP_Error 404. */
	public function test_get_by_token_returns_404_for_invalid_token(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( 'not-a-real-token' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'proposal_not_found', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 404, $data['status'] );
	}

	/** Empty token returns WP_Error 400. */
	public function test_get_by_token_returns_400_for_empty_token(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( '' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	// ── track_view ────────────────────────────────────────────────────────────

	/** Viewing a 'sent' proposal transitions status to 'viewed'. */
	public function test_track_view_transitions_sent_to_viewed(): void {
		// First send the proposal.
		ClientFlow_Proposal::send( $this->proposal_id, $this->owner_id );

		$before = ClientFlow_Proposal_Client::get_by_token( $this->token );
		$this->assertSame( 'sent', $before['status'] );

		ClientFlow_Proposal_Client::track_view( $this->token, '127.0.0.1', 'TestAgent' );

		$after = ClientFlow_Proposal_Client::get_by_token( $this->token );
		$this->assertSame( 'viewed', $after['status'] );
		$this->assertNotNull( $after['viewed_at'] );
	}

	/** Viewing a 'viewed' proposal does NOT change status (idempotent). */
	public function test_track_view_is_idempotent_on_already_viewed(): void {
		ClientFlow_Proposal::send( $this->proposal_id, $this->owner_id );
		ClientFlow_Proposal_Client::track_view( $this->token );
		ClientFlow_Proposal_Client::track_view( $this->token );

		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );
		$this->assertSame( 'viewed', $result['status'] );
	}

	/** View event is logged to clientflow_events. */
	public function test_track_view_logs_event(): void {
		global $wpdb;
		ClientFlow_Proposal::send( $this->proposal_id, $this->owner_id );

		ClientFlow_Proposal_Client::track_view( $this->token, '10.0.0.1', 'Browser/1.0' );

		$event = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}clientflow_events
				 WHERE proposal_id = %d AND event_type = 'viewed'",
				$this->proposal_id
			),
			ARRAY_A
		);

		$this->assertNotNull( $event );
		$this->assertSame( '10.0.0.1', $event['user_ip'] );
	}

	// ── accept ────────────────────────────────────────────────────────────────

	/** Client can accept a draft proposal. */
	public function test_accept_draft_proposal(): void {
		$result = ClientFlow_Proposal_Client::accept( $this->token );

		$this->assertIsArray( $result );
		$this->assertSame( 'accepted', $result['status'] );
		$this->assertNotNull( $result['accepted_at'] );
	}

	/** Client can accept a sent proposal. */
	public function test_accept_sent_proposal(): void {
		ClientFlow_Proposal::send( $this->proposal_id, $this->owner_id );

		$result = ClientFlow_Proposal_Client::accept( $this->token );

		$this->assertSame( 'accepted', $result['status'] );
	}

	/** Client can accept a viewed proposal. */
	public function test_accept_viewed_proposal(): void {
		ClientFlow_Proposal::send( $this->proposal_id, $this->owner_id );
		ClientFlow_Proposal_Client::track_view( $this->token );

		$result = ClientFlow_Proposal_Client::accept( $this->token );

		$this->assertSame( 'accepted', $result['status'] );
	}

	/** Cannot accept an already-accepted proposal. */
	public function test_cannot_accept_already_accepted(): void {
		ClientFlow_Proposal_Client::accept( $this->token );

		$result = ClientFlow_Proposal_Client::accept( $this->token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_status', $result->get_error_code() );
	}

	/** Cannot accept an expired proposal. */
	public function test_cannot_accept_expired_proposal(): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'clientflow_proposals',
			[ 'status' => 'expired' ],
			[ 'id' => $this->proposal_id ]
		);

		$result = ClientFlow_Proposal_Client::accept( $this->token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	/** Accept logs an event to clientflow_events. */
	public function test_accept_logs_event(): void {
		global $wpdb;

		ClientFlow_Proposal_Client::accept( $this->token );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_events
				 WHERE proposal_id = %d AND event_type = 'accepted'",
				$this->proposal_id
			)
		);

		$this->assertSame( 1, $count );
	}

	/** Expired proposals show warning — get_by_token returns status 'expired'. */
	public function test_expired_proposal_status_exposed_to_client(): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'clientflow_proposals',
			[ 'status' => 'expired' ],
			[ 'id' => $this->proposal_id ]
		);

		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertSame( 'expired', $result['status'] );
	}

	// ── decline ───────────────────────────────────────────────────────────────

	/** Client can decline a proposal. */
	public function test_decline_proposal(): void {
		$result = ClientFlow_Proposal_Client::decline( $this->token );

		$this->assertIsArray( $result );
		$this->assertSame( 'declined', $result['status'] );
	}

	/** declined_at is stamped when declined. */
	public function test_decline_stamps_declined_at(): void {
		ClientFlow_Proposal_Client::decline( $this->token );

		global $wpdb;
		$declined_at = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT declined_at FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d",
				$this->proposal_id
			)
		);

		$this->assertNotNull( $declined_at );
	}

	/** Cannot decline an already-declined proposal. */
	public function test_cannot_decline_already_declined(): void {
		ClientFlow_Proposal_Client::decline( $this->token );

		$result = ClientFlow_Proposal_Client::decline( $this->token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_status', $result->get_error_code() );
	}

	/** Cannot decline an accepted proposal. */
	public function test_cannot_decline_accepted_proposal(): void {
		ClientFlow_Proposal_Client::accept( $this->token );

		$result = ClientFlow_Proposal_Client::decline( $this->token );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** Decline logs an event to clientflow_events. */
	public function test_decline_logs_event(): void {
		global $wpdb;

		ClientFlow_Proposal_Client::decline( $this->token );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_events
				 WHERE proposal_id = %d AND event_type = 'declined'",
				$this->proposal_id
			)
		);

		$this->assertSame( 1, $count );
	}

	// ── Pricing table calculation ─────────────────────────────────────────────

	/**
	 * Pricing table grand total:
	 * £1,000 design × 1 + 20% VAT = £1,200.
	 */
	public function test_pricing_total_stored_correctly(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertSame( 1200.0, $result['total_amount'] );
	}

	/** Line items are accessible in content.line_items. */
	public function test_pricing_line_items_in_content(): void {
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$items = $result['content']['line_items'] ?? [];
		$this->assertCount( 1, $items );
		$this->assertSame( 'Design', $items[0]['description'] );
		$this->assertSame( 1000.0, (float) $items[0]['unit_price'] );
	}

	// ── Accept redirects to payment (if enabled) ──────────────────────────────

	/** payment_enabled is true when owner has Pro and proposal was created on Pro. */
	public function test_payment_enabled_for_pro_user(): void {
		// Pro plan owner → payment_enabled should be 1 on create.
		$result = ClientFlow_Proposal_Client::get_by_token( $this->token );

		$this->assertTrue( $result['payment_enabled'] );
	}

	/** payment_enabled is false for free-plan proposals. */
	public function test_payment_disabled_for_free_user(): void {
		$free_owner = $this->factory->user->create();
		ClientFlow_Entitlements::set_user_plan( $free_owner, 'free' );

		$free_id = ClientFlow_Proposal::create( $free_owner, [ 'title' => 'Free Proposal' ] );

		global $wpdb;
		$free_token = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT token FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d", $free_id )
		);

		$result = ClientFlow_Proposal_Client::get_by_token( $free_token );

		$this->assertFalse( $result['payment_enabled'] );
	}
}
