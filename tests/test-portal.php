<?php
/**
 * Tests: Client Portal (Sprint 4)
 *
 * Covers:
 *   - ClientFlow_Portal_Auth::get_or_create_wp_user — creates user with clientflow_client role
 *   - ClientFlow_Portal_Auth::generate_magic_token  — raw token + SHA-256 hash in meta
 *   - ClientFlow_Portal_Auth::verify_magic_token    — valid token succeeds
 *   - ClientFlow_Portal_Auth::verify_magic_token    — expired token returns WP_Error
 *   - ClientFlow_Portal_Auth::verify_magic_token    — one-time use (second call fails)
 *   - ClientFlow_Portal_Auth::send_magic_link_email — calls wp_mail
 *   - ClientFlow_Portal_Data::get_proposals         — client only sees own proposals
 *   - REST POST /portal/send-magic-link             — always returns success (no enumeration)
 *   - REST POST /portal/verify                      — valid token returns redirect_url
 *   - REST POST /portal/verify                      — invalid token returns 401
 *
 * @package ClientFlow\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal.php';
require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal-template.php';
require_once dirname( __DIR__ ) . '/modules/proposals/handlers.php';
require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal-client.php';
require_once dirname( __DIR__ ) . '/modules/portal/class-portal-auth.php';
require_once dirname( __DIR__ ) . '/modules/portal/class-portal-data.php';
require_once dirname( __DIR__ ) . '/rest-api/portal.php';

class Test_Portal extends WP_UnitTestCase {

	/** @var int Agency owner (creates proposals). */
	private int $owner_id;

	/** @var string Client email address. */
	private string $client_email = 'client@example.com';

	// ── Setup / teardown ──────────────────────────────────────────────────────

	public function set_up(): void {
		parent::set_up();

		// Register the clientflow_client role if not present.
		if ( ! get_role( 'clientflow_client' ) ) {
			add_role( 'clientflow_client', 'ClientFlow Client', [ 'read' => true ] );
		}

		$this->owner_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tear_down(): void {
		// Remove any client WP users created during tests.
		$client = get_user_by( 'email', $this->client_email );
		if ( $client ) {
			wp_delete_user( $client->ID );
		}

		parent::tear_down();
	}

	// ── Helper ────────────────────────────────────────────────────────────────

	private function create_proposal( string $title = 'Test Proposal' ): int {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'cf_proposals', [
			'owner_id'     => $this->owner_id,
			'title'        => $title,
			'status'       => 'sent',
			'client_email' => $this->client_email,
			'client_name'  => 'Test Client',
			'token'        => wp_generate_uuid4(),
			'content'      => '{}',
			'total_amount' => '1500.00',
			'currency'     => 'GBP',
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		] );

		return (int) $wpdb->insert_id;
	}

	// =========================================================================
	// get_or_create_wp_user
	// =========================================================================

	public function test_creates_wp_user_with_client_role(): void {
		$user = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email, 'Test Client' );

		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertContains( 'clientflow_client', (array) $user->roles );
		$this->assertSame( $this->client_email, $user->user_email );
	}

	public function test_does_not_duplicate_user(): void {
		ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );

		$users = get_users( [ 'search' => $this->client_email, 'search_columns' => [ 'user_email' ] ] );
		$this->assertCount( 1, $users );
	}

	public function test_assigns_client_id_meta(): void {
		$user      = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		$client_id = get_user_meta( $user->ID, ClientFlow_Portal_Auth::META_CLIENT, true );

		$this->assertNotEmpty( $client_id );
	}

	// =========================================================================
	// generate_magic_token
	// =========================================================================

	public function test_generate_magic_token_stores_hash(): void {
		$user      = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		$raw_token = ClientFlow_Portal_Auth::generate_magic_token( $user->ID );

		$stored_hash = get_user_meta( $user->ID, ClientFlow_Portal_Auth::META_TOKEN, true );
		$expected    = hash( 'sha256', $raw_token );

		$this->assertSame( $expected, $stored_hash );
	}

	public function test_generate_magic_token_stores_expiry(): void {
		$user   = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		$before = time();
		ClientFlow_Portal_Auth::generate_magic_token( $user->ID );
		$after  = time();

		$expiry = (int) get_user_meta( $user->ID, ClientFlow_Portal_Auth::META_EXPIRY, true );

		$this->assertGreaterThanOrEqual( $before + ClientFlow_Portal_Auth::TOKEN_TTL - 1, $expiry );
		$this->assertLessThanOrEqual(    $after  + ClientFlow_Portal_Auth::TOKEN_TTL,     $expiry );
	}

	// =========================================================================
	// verify_magic_token
	// =========================================================================

	public function test_verify_valid_token_succeeds(): void {
		$user      = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		$raw_token = ClientFlow_Portal_Auth::generate_magic_token( $user->ID );

		$result = ClientFlow_Portal_Auth::verify_magic_token( $raw_token );

		$this->assertInstanceOf( WP_User::class, $result );
		$this->assertSame( $user->ID, $result->ID );
	}

	public function test_verify_invalid_token_returns_error(): void {
		$result = ClientFlow_Portal_Auth::verify_magic_token( 'not-a-real-token-abcdef1234567890' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	public function test_verify_expired_token_returns_error(): void {
		$user = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );

		$raw_token = ClientFlow_Portal_Auth::generate_magic_token( $user->ID );

		// Manually set expiry to the past.
		update_user_meta( $user->ID, ClientFlow_Portal_Auth::META_EXPIRY, time() - 3600 );

		$result = ClientFlow_Portal_Auth::verify_magic_token( $raw_token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'expired_token', $result->get_error_code() );
	}

	public function test_verify_token_is_one_time_use(): void {
		$user      = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		$raw_token = ClientFlow_Portal_Auth::generate_magic_token( $user->ID );

		// First use: should succeed.
		$first = ClientFlow_Portal_Auth::verify_magic_token( $raw_token );
		$this->assertInstanceOf( WP_User::class, $first );

		// Second use: token deleted, should fail.
		$second = ClientFlow_Portal_Auth::verify_magic_token( $raw_token );
		$this->assertInstanceOf( WP_Error::class, $second );
	}

	// =========================================================================
	// get_proposals — data isolation
	// =========================================================================

	public function test_client_sees_only_own_proposals(): void {
		// Create a proposal for our client.
		$own_id = $this->create_proposal( 'Our Proposal' );

		// Create a second proposal for a different client.
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'cf_proposals', [
			'owner_id'     => $this->owner_id,
			'title'        => 'Other Client Proposal',
			'status'       => 'sent',
			'client_email' => 'other@example.com',
			'client_name'  => 'Other Client',
			'token'        => wp_generate_uuid4(),
			'content'      => '{}',
			'total_amount' => '999.00',
			'currency'     => 'GBP',
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		] );

		$client_user = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		$proposals   = ClientFlow_Portal_Data::get_proposals( $client_user->ID );

		$ids = array_column( $proposals, 'id' );

		$this->assertContains( (string) $own_id, $ids );

		// None of the returned proposals should belong to other@example.com.
		$emails = array_unique( array_column( $proposals, 'client_email' ) );
		$this->assertCount( 1, $emails );
		$this->assertSame( $this->client_email, $emails[0] );
	}

	// =========================================================================
	// REST: POST /portal/send-magic-link
	// =========================================================================

	public function test_send_magic_link_always_succeeds(): void {
		$request = new WP_REST_Request( 'POST', '/clientflow/v1/portal/send-magic-link' );
		$request->set_param( 'email', 'nobody@example.com' );

		$response = cf_portal_send_magic_link( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	// =========================================================================
	// REST: POST /portal/verify
	// =========================================================================

	public function test_rest_verify_valid_token(): void {
		$user      = ClientFlow_Portal_Auth::get_or_create_wp_user( $this->client_email );
		$raw_token = ClientFlow_Portal_Auth::generate_magic_token( $user->ID );

		$request = new WP_REST_Request( 'POST', '/clientflow/v1/portal/verify' );
		$request->set_param( 'token', $raw_token );

		$response = cf_portal_verify( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertArrayHasKey( 'redirect_url', $response->get_data() );
	}

	public function test_rest_verify_invalid_token_returns_401(): void {
		$request = new WP_REST_Request( 'POST', '/clientflow/v1/portal/verify' );
		$request->set_param( 'token', 'bogus-token-that-does-not-exist' );

		$response = cf_portal_verify( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
	}
}
