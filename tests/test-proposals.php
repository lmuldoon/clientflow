<?php
/**
 * ClientFlow Proposal Test Suite
 *
 * Tests for ClientFlow_Proposal, ClientFlow_Proposal_Template,
 * and ClientFlow_Proposal_Handlers.
 *
 * Run with: ./vendor/bin/phpunit --testdox --filter Test_Proposals
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

/**
 * Class Test_Proposals
 *
 * @group proposals
 */
class Test_Proposals extends WP_UnitTestCase {

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Create a WordPress user and set up a clientflow_user_meta row.
	 *
	 * @param string $plan 'free' | 'pro' | 'agency'
	 *
	 * @return int
	 */
	private function make_user( string $plan = 'free' ): int {
		$user_id = self::factory()->user->create();
		ClientFlow_Entitlements::ensure_user_meta( $user_id );
		ClientFlow_Entitlements::set_user_plan( $user_id, $plan );
		return $user_id;
	}

	/**
	 * Build a minimal valid wizard payload.
	 *
	 * @param array $overrides
	 *
	 * @return array
	 */
	private function wizard_payload( array $overrides = [] ): array {
		return array_merge( [
			'template_id'  => 'web-design',
			'title'        => 'Test Proposal',
			'currency'     => 'GBP',
			'client_name'  => 'Jane Client',
			'client_email' => 'jane@example.com',
			'line_items'   => [
				[ 'id' => 'li_1', 'description' => 'Web Design', 'qty' => 1, 'unit_price' => 1500 ],
			],
			'discount_pct' => 0,
			'vat_pct'      => 20,
		], $overrides );
	}

	// ─── Template Access ──────────────────────────────────────────────────────

	/**
	 * @test
	 * All 4 templates are defined.
	 */
	public function test_four_templates_defined(): void {
		$this->assertCount( 4, ClientFlow_Proposal_Template::all() );
	}

	/**
	 * @test
	 * Free plan gets 3 accessible templates (web-design, retainer, blank).
	 */
	public function test_free_plan_gets_three_templates(): void {
		$templates = ClientFlow_Proposal_Template::for_plan( 'free' );
		$this->assertCount( 3, $templates );
		$ids = array_column( $templates, 'id' );
		$this->assertNotContains( 'marketing', $ids );
	}

	/**
	 * @test
	 * Pro plan gets all 4 templates.
	 */
	public function test_pro_plan_gets_all_templates(): void {
		$this->assertCount( 4, ClientFlow_Proposal_Template::for_plan( 'pro' ) );
	}

	/**
	 * @test
	 * Agency plan gets all 4 templates.
	 */
	public function test_agency_plan_gets_all_templates(): void {
		$this->assertCount( 4, ClientFlow_Proposal_Template::for_plan( 'agency' ) );
	}

	/**
	 * @test
	 * Free user cannot access the marketing template.
	 */
	public function test_free_user_cannot_access_marketing_template(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertFalse( ClientFlow_Proposal_Template::user_can_access( $user_id, 'marketing' ) );
	}

	/**
	 * @test
	 * Pro user can access the marketing template.
	 */
	public function test_pro_user_can_access_marketing_template(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertTrue( ClientFlow_Proposal_Template::user_can_access( $user_id, 'marketing' ) );
	}

	/**
	 * @test
	 * Unknown template ID returns false.
	 */
	public function test_unknown_template_returns_false(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertFalse( ClientFlow_Proposal_Template::user_can_access( $user_id, 'nonexistent-template' ) );
	}

	/**
	 * @test
	 * default_content returns valid JSON with the correct template_id.
	 */
	public function test_default_content_returns_valid_json(): void {
		$json    = ClientFlow_Proposal_Template::default_content( 'web-design' );
		$decoded = json_decode( $json, true );

		$this->assertIsArray( $decoded );
		$this->assertSame( 'web-design', $decoded['template_id'] );
		$this->assertArrayHasKey( 'sections', $decoded );
	}

	// ─── Proposal CRUD ────────────────────────────────────────────────────────

	/**
	 * @test
	 * Free user can create a proposal.
	 */
	public function test_free_user_can_create_proposal(): void {
		$user_id = $this->make_user( 'free' );

		$id = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Test' ] );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * @test
	 * Free user is blocked after 5 proposals.
	 */
	public function test_free_user_blocked_after_five_proposals(): void {
		$user_id = $this->make_user( 'free' );

		for ( $i = 0; $i < 5; $i++ ) {
			ClientFlow_Proposal::create( $user_id, [ 'title' => "Proposal $i" ] );
		}

		$result = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Proposal 6' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'proposal_limit_reached', $result->get_error_code() );
	}

	/**
	 * @test
	 * Pro user can create unlimited proposals (past 5).
	 */
	public function test_pro_user_can_create_unlimited_proposals(): void {
		$user_id = $this->make_user( 'pro' );

		for ( $i = 0; $i < 6; $i++ ) {
			$id = ClientFlow_Proposal::create( $user_id, [ 'title' => "Proposal $i" ] );
			$this->assertIsInt( $id );
		}
	}

	/**
	 * @test
	 * Created proposal has status 'draft'.
	 */
	public function test_created_proposal_is_draft(): void {
		$user_id = $this->make_user( 'free' );
		$id      = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Draft Test' ] );

		$proposal = ClientFlow_Proposal::get( $id, $user_id );

		$this->assertIsArray( $proposal );
		$this->assertSame( 'draft', $proposal['status'] );
	}

	/**
	 * @test
	 * get() returns WP_Error for non-existent proposal.
	 */
	public function test_get_returns_error_for_nonexistent(): void {
		$user_id = $this->make_user( 'free' );
		$result  = ClientFlow_Proposal::get( 99999, $user_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'proposal_not_found', $result->get_error_code() );
	}

	/**
	 * @test
	 * get() respects ownership — user B cannot get user A's proposal.
	 */
	public function test_get_enforces_ownership(): void {
		$user_a = $this->make_user( 'pro' );
		$user_b = $this->make_user( 'pro' );

		$id = ClientFlow_Proposal::create( $user_a, [ 'title' => 'Private' ] );

		$result = ClientFlow_Proposal::get( $id, $user_b );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'proposal_not_found', $result->get_error_code() );
	}

	/**
	 * @test
	 * update() changes the title.
	 */
	public function test_update_changes_title(): void {
		$user_id = $this->make_user( 'pro' );
		$id      = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Original' ] );

		ClientFlow_Proposal::update( $id, $user_id, [ 'title' => 'Updated' ] );

		$proposal = ClientFlow_Proposal::get( $id, $user_id );
		$this->assertSame( 'Updated', $proposal['title'] );
	}

	/**
	 * @test
	 * delete() removes the proposal.
	 */
	public function test_delete_removes_proposal(): void {
		$user_id = $this->make_user( 'pro' );
		$id      = ClientFlow_Proposal::create( $user_id, [ 'title' => 'To Delete' ] );

		$result = ClientFlow_Proposal::delete( $id, $user_id );
		$this->assertTrue( $result );

		$fetch = ClientFlow_Proposal::get( $id, $user_id );
		$this->assertInstanceOf( WP_Error::class, $fetch );
	}

	/**
	 * @test
	 * duplicate() creates a new draft with "Copy of" prefix.
	 */
	public function test_duplicate_creates_copy(): void {
		$user_id = $this->make_user( 'pro' );
		$id      = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Original' ] );

		$new_id = ClientFlow_Proposal::duplicate( $id, $user_id );

		$this->assertIsInt( $new_id );
		$this->assertNotEquals( $id, $new_id );

		$copy = ClientFlow_Proposal::get( $new_id, $user_id );
		$this->assertStringContainsString( 'Original', $copy['title'] );
		$this->assertSame( 'draft', $copy['status'] );
	}

	/**
	 * @test
	 * list() returns proposals for the owner only.
	 */
	public function test_list_returns_owner_proposals_only(): void {
		$user_a = $this->make_user( 'pro' );
		$user_b = $this->make_user( 'pro' );

		ClientFlow_Proposal::create( $user_a, [ 'title' => 'A1' ] );
		ClientFlow_Proposal::create( $user_a, [ 'title' => 'A2' ] );
		ClientFlow_Proposal::create( $user_b, [ 'title' => 'B1' ] );

		$result = ClientFlow_Proposal::list( $user_a );

		$this->assertSame( 2, $result['total'] );
	}

	/**
	 * @test
	 * list() filters by status.
	 */
	public function test_list_filters_by_status(): void {
		$user_id = $this->make_user( 'pro' );

		ClientFlow_Proposal::create( $user_id, [ 'title' => 'Draft 1' ] );
		$sent_id = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Sent 1' ] );

		ClientFlow_Proposal::update( $sent_id, $user_id, [ 'status' => 'sent' ] );

		$result = ClientFlow_Proposal::list( $user_id, [ 'status' => 'draft' ] );
		$this->assertSame( 1, $result['total'] );

		$result2 = ClientFlow_Proposal::list( $user_id, [ 'status' => 'sent' ] );
		$this->assertSame( 1, $result2['total'] );
	}

	// ─── Status Flow ──────────────────────────────────────────────────────────

	/**
	 * @test
	 * send() transitions status from draft to sent.
	 */
	public function test_send_transitions_draft_to_sent(): void {
		$user_id = $this->make_user( 'pro' );
		$id      = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Ready to Send' ] );

		ClientFlow_Proposal::send( $id, $user_id );

		$proposal = ClientFlow_Proposal::get( $id, $user_id );
		$this->assertSame( 'sent', $proposal['status'] );
		$this->assertNotEmpty( $proposal['sent_at'] );
	}

	/**
	 * @test
	 * send() returns WP_Error when proposal is not in draft status.
	 */
	public function test_send_fails_if_not_draft(): void {
		$user_id = $this->make_user( 'pro' );
		$id      = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Already Sent' ] );

		ClientFlow_Proposal::send( $id, $user_id );

		// Try to send again.
		$result = ClientFlow_Proposal::send( $id, $user_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_status', $result->get_error_code() );
	}

	/**
	 * @test
	 * update() can transition status to accepted.
	 */
	public function test_status_can_transition_to_accepted(): void {
		$user_id = $this->make_user( 'pro' );
		$id      = ClientFlow_Proposal::create( $user_id, [ 'title' => 'Accepted' ] );

		ClientFlow_Proposal::update( $id, $user_id, [ 'status' => 'accepted' ] );

		$proposal = ClientFlow_Proposal::get( $id, $user_id );
		$this->assertSame( 'accepted', $proposal['status'] );
	}

	// ─── Wizard Handler ───────────────────────────────────────────────────────

	/**
	 * @test
	 * create_from_wizard() creates proposal with correct total.
	 */
	public function test_wizard_creates_proposal_with_correct_total(): void {
		$user_id = $this->make_user( 'pro' );
		$payload = $this->wizard_payload();

		$proposal = ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload );

		$this->assertIsArray( $proposal );
		// 1500 + 20% VAT = 1800.
		$this->assertEquals( 1800.00, $proposal['total_amount'] );
	}

	/**
	 * @test
	 * create_from_wizard() creates a client record.
	 */
	public function test_wizard_creates_client_record(): void {
		global $wpdb;

		$user_id = $this->make_user( 'pro' );
		$payload = $this->wizard_payload();

		ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload );

		$client = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}clientflow_clients WHERE email = %s AND owner_id = %d",
				'jane@example.com',
				$user_id
			)
		);

		$this->assertNotNull( $client );
		$this->assertSame( 'Jane Client', $client->name );
	}

	/**
	 * @test
	 * create_from_wizard() reuses existing client by email.
	 */
	public function test_wizard_reuses_existing_client(): void {
		global $wpdb;

		$user_id = $this->make_user( 'pro' );
		$payload = $this->wizard_payload();

		ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload );
		ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload ); // Same email.

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_clients WHERE email = %s AND owner_id = %d",
				'jane@example.com',
				$user_id
			)
		);

		$this->assertSame( 1, $count ); // Only one client record.
	}

	/**
	 * @test
	 * create_from_wizard() blocks free user from using marketing template.
	 */
	public function test_wizard_blocks_free_user_from_marketing_template(): void {
		$user_id = $this->make_user( 'free' );
		$payload = $this->wizard_payload( [ 'template_id' => 'marketing' ] );

		$result = ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'template_locked', $result->get_error_code() );
	}

	/**
	 * @test
	 * Proposal total is 0 when no line items are provided.
	 */
	public function test_wizard_total_zero_with_no_line_items(): void {
		$user_id = $this->make_user( 'pro' );
		$payload = $this->wizard_payload( [ 'line_items' => [], 'vat_pct' => 0 ] );

		$proposal = ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload );

		$this->assertIsArray( $proposal );
		$this->assertEquals( 0.0, $proposal['total_amount'] );
	}

	/**
	 * @test
	 * Discount reduces the total correctly.
	 */
	public function test_wizard_discount_reduces_total(): void {
		$user_id = $this->make_user( 'pro' );
		$payload = $this->wizard_payload( [
			'line_items'   => [ [ 'id' => 'li_1', 'description' => 'Design', 'qty' => 1, 'unit_price' => 1000 ] ],
			'discount_pct' => 10,
			'vat_pct'      => 0,
		] );

		$proposal = ClientFlow_Proposal_Handlers::create_from_wizard( $user_id, $payload );

		$this->assertEquals( 900.00, $proposal['total_amount'] );
	}
}
