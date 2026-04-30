<?php
/**
 * Tests: Payment Integration (Sprint 3)
 *
 * Covers:
 *   - ClientFlow_Payment::create / get_by_session_id / mark_complete / mark_failed
 *   - ClientFlow_Stripe::verify_webhook_signature
 *   - ClientFlow_Stripe::is_configured / get_mode
 *   - REST POST /payments/create-session (token auth, amount calculation, status guards)
 *   - REST GET  /payments/status
 *   - REST POST /payments/webhook (signature verification, event processing)
 *   - cf_handle_checkout_complete: proposal status transition + event log
 *   - Deposit percentage calculation
 *
 * Stripe HTTP calls are intercepted via the 'pre_http_request' filter to avoid
 * real network requests in the test environment.
 *
 * @package ClientFlow\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal.php';
require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal-template.php';
require_once dirname( __DIR__ ) . '/modules/proposals/handlers.php';
require_once dirname( __DIR__ ) . '/modules/proposals/class-proposal-client.php';
require_once dirname( __DIR__ ) . '/modules/payments/class-stripe.php';
require_once dirname( __DIR__ ) . '/modules/payments/class-payment.php';
require_once dirname( __DIR__ ) . '/rest-api/payments.php';

/**
 * Class Test_Payments
 */
class Test_Payments extends WP_UnitTestCase {

	/** @var int Proposal owner user ID. */
	private int $owner_id;

	/** @var int A proposal with payment enabled. */
	private int $proposal_id;

	/** @var string The proposal's public token. */
	private string $token;

	/** @var string Fake Stripe session ID. */
	private string $session_id = 'cs_test_abc123def456';

	/** @var callable|null HTTP intercept filter callback. */
	private static $http_mock = null;

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Register a single-use HTTP mock for the next Stripe request.
	 *
	 * @param array $response_body Decoded JSON to return.
	 * @param int   $status_code   HTTP status (default 200).
	 */
	private function mock_stripe( array $response_body, int $status_code = 200 ): void {
		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) use ( $response_body, $status_code ) {
			if ( str_contains( $url, 'api.stripe.com' ) ) {
				return [
					'response' => [ 'code' => $status_code, 'message' => 'OK' ],
					'body'     => wp_json_encode( $response_body ),
					'headers'  => [],
					'cookies'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );
	}

	/**
	 * Remove all pre_http_request mocks added during a test.
	 */
	private function clear_http_mocks(): void {
		remove_all_filters( 'pre_http_request' );
	}

	// ── Fixtures ──────────────────────────────────────────────────────────────

	public function setUp(): void {
		parent::setUp();

		// Pro plan owner — payment_enabled will be set on create.
		$this->owner_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		ClientFlow_Entitlements::set_user_plan( $this->owner_id, 'pro' );

		// Configure Stripe keys so is_configured() returns true.
		update_option( 'clientflow_stripe_secret_key',      'sk_test_dummy_key_for_tests' );
		update_option( 'clientflow_stripe_publishable_key', 'pk_test_dummy_pub_key' );
		update_option( 'clientflow_stripe_webhook_secret',  'whsec_test_secret' );

		// Create a proposal.
		$this->proposal_id = ClientFlow_Proposal::create( $this->owner_id, [
			'title'        => 'Sprint 3 Test Proposal',
			'currency'     => 'GBP',
			'total_amount' => 2000.00,
			'content'      => wp_json_encode( [
				'sections'    => [],
				'line_items'  => [ [ 'id' => 'li_1', 'description' => 'Design', 'qty' => 1, 'unit_price' => 2000 ] ],
				'deposit_pct' => 50,
				'vat_pct'     => 0,
			] ),
		] );

		// Capture the token.
		global $wpdb;
		$this->token = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT token FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d",
				$this->proposal_id
			)
		);
	}

	public function tearDown(): void {
		$this->clear_http_mocks();
		delete_option( 'clientflow_stripe_secret_key' );
		delete_option( 'clientflow_stripe_publishable_key' );
		delete_option( 'clientflow_stripe_webhook_secret' );
		parent::tearDown();
	}

	// ── ClientFlow_Stripe ─────────────────────────────────────────────────────

	/** is_configured returns true when secret key is set. */
	public function test_stripe_is_configured(): void {
		$this->assertTrue( ClientFlow_Stripe::is_configured() );
	}

	/** is_configured returns false when no key is set. */
	public function test_stripe_not_configured_without_key(): void {
		delete_option( 'clientflow_stripe_secret_key' );
		$this->assertFalse( ClientFlow_Stripe::is_configured() );
	}

	/** Test mode detected from sk_test_ prefix. */
	public function test_stripe_get_mode_test(): void {
		$this->assertSame( 'test', ClientFlow_Stripe::get_mode() );
	}

	/** Live mode detected from sk_live_ prefix. */
	public function test_stripe_get_mode_live(): void {
		update_option( 'clientflow_stripe_secret_key', 'sk_live_livekey123' );
		$this->assertSame( 'live', ClientFlow_Stripe::get_mode() );
	}

	// ── Webhook signature verification ────────────────────────────────────────

	/** Valid signature is accepted. */
	public function test_webhook_signature_valid(): void {
		$secret    = 'whsec_test_secret_for_sig_test';
		$timestamp = (string) time();
		$payload   = '{"type":"checkout.session.completed"}';
		$signed    = $timestamp . '.' . $payload;
		$sig       = hash_hmac( 'sha256', $signed, $secret );
		$header    = "t={$timestamp},v1={$sig}";

		$this->assertTrue(
			ClientFlow_Stripe::verify_webhook_signature( $payload, $header, $secret )
		);
	}

	/** Invalid signature is rejected. */
	public function test_webhook_signature_invalid(): void {
		$this->assertFalse(
			ClientFlow_Stripe::verify_webhook_signature(
				'{"type":"checkout.session.completed"}',
				't=1234567890,v1=invalidsignature',
				'whsec_test_secret_for_sig_test'
			)
		);
	}

	/** Stale signature (>5 min) is rejected. */
	public function test_webhook_signature_stale_rejected(): void {
		$secret    = 'whsec_test';
		$timestamp = (string) ( time() - 400 ); // 400 seconds ago — stale.
		$payload   = '{}';
		$signed    = $timestamp . '.' . $payload;
		$sig       = hash_hmac( 'sha256', $signed, $secret );
		$header    = "t={$timestamp},v1={$sig}";

		$this->assertFalse(
			ClientFlow_Stripe::verify_webhook_signature( $payload, $header, $secret )
		);
	}

	/** Empty signature header is rejected. */
	public function test_webhook_signature_empty_rejected(): void {
		$this->assertFalse(
			ClientFlow_Stripe::verify_webhook_signature( '{}', '', 'whsec_test' )
		);
	}

	// ── ClientFlow_Payment (model) ────────────────────────────────────────────

	/** Create returns a positive integer ID. */
	public function test_payment_create_returns_id(): void {
		$id = ClientFlow_Payment::create( $this->proposal_id, $this->owner_id, [
			'amount'     => 1000.00,
			'currency'   => 'GBP',
			'deposit_pct' => 50,
			'session_id' => 'cs_test_create_01',
		] );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/** get_by_session_id retrieves the correct record. */
	public function test_payment_get_by_session_id(): void {
		ClientFlow_Payment::create( $this->proposal_id, $this->owner_id, [
			'amount'     => 500.00,
			'currency'   => 'GBP',
			'deposit_pct' => 25,
			'session_id' => 'cs_test_get_01',
		] );

		$payment = ClientFlow_Payment::get_by_session_id( 'cs_test_get_01' );

		$this->assertIsArray( $payment );
		$this->assertSame( 500.0, $payment['amount'] );
		$this->assertSame( 'GBP', $payment['currency'] );
		$this->assertSame( 25, $payment['deposit_pct'] );
		$this->assertSame( 'pending', $payment['status'] );
	}

	/** get_by_session_id returns WP_Error for unknown session. */
	public function test_payment_get_by_session_id_not_found(): void {
		$result = ClientFlow_Payment::get_by_session_id( 'cs_not_real' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	/** mark_complete transitions status and sets payment_intent_id. */
	public function test_payment_mark_complete(): void {
		ClientFlow_Payment::create( $this->proposal_id, $this->owner_id, [
			'amount'     => 2000.00,
			'currency'   => 'GBP',
			'deposit_pct' => 100,
			'session_id' => 'cs_test_complete_01',
		] );

		$result = ClientFlow_Payment::mark_complete(
			'cs_test_complete_01',
			'pi_test_paymentintent',
			'cus_test_customer'
		);

		$this->assertTrue( $result );

		$payment = ClientFlow_Payment::get_by_session_id( 'cs_test_complete_01' );
		$this->assertSame( 'completed', $payment['status'] );
		$this->assertSame( 'pi_test_paymentintent', $payment['stripe_payment_intent_id'] );
		$this->assertSame( 'cus_test_customer', $payment['stripe_customer_id'] );
		$this->assertNotNull( $payment['completed_at'] );
	}

	/** mark_failed transitions status to failed. */
	public function test_payment_mark_failed(): void {
		ClientFlow_Payment::create( $this->proposal_id, $this->owner_id, [
			'amount'     => 2000.00,
			'currency'   => 'GBP',
			'deposit_pct' => 100,
			'session_id' => 'cs_test_fail_01',
		] );

		ClientFlow_Payment::mark_failed( 'cs_test_fail_01' );

		$payment = ClientFlow_Payment::get_by_session_id( 'cs_test_fail_01' );
		$this->assertSame( 'failed', $payment['status'] );
	}

	/** has_completed_payment returns false before completion. */
	public function test_has_completed_payment_false_before_completion(): void {
		$this->assertFalse( ClientFlow_Payment::has_completed_payment( $this->proposal_id ) );
	}

	/** has_completed_payment returns true after mark_complete. */
	public function test_has_completed_payment_true_after_completion(): void {
		ClientFlow_Payment::create( $this->proposal_id, $this->owner_id, [
			'amount'     => 2000.00,
			'currency'   => 'GBP',
			'deposit_pct' => 100,
			'session_id' => 'cs_test_hcp_01',
		] );

		ClientFlow_Payment::mark_complete( 'cs_test_hcp_01', 'pi_hcp', null );

		$this->assertTrue( ClientFlow_Payment::has_completed_payment( $this->proposal_id ) );
	}

	// ── Deposit percentage calculation ────────────────────────────────────────

	/**
	 * 50% deposit on £2,000 = £1,000 (200000 pence) sent to Stripe.
	 *
	 * We verify this by inspecting what the REST handler would pass to Stripe
	 * by intercepting the HTTP call.
	 */
	public function test_deposit_amount_calculation(): void {
		$captured_body = null;

		add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( &$captured_body ) {
			if ( str_contains( $url, 'api.stripe.com' ) ) {
				parse_str( $args['body'], $parsed );
				$captured_body = $parsed;
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => wp_json_encode( [
						'id'  => 'cs_test_deposit_01',
						'url' => 'https://checkout.stripe.com/pay/cs_test_deposit_01',
					] ),
					'headers'  => [],
					'cookies'  => [],
				];
			}
			return $preempt;
		}, 10, 3 );

		// Fire the REST handler.
		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/create-session' );
		$request->set_param( 'token', $this->token );
		cf_rest_payment_create_session( $request );

		$this->clear_http_mocks();

		// With deposit_pct=50 and total=2000, unit_amount should be 100000 pence.
		$unit_amount = $captured_body['line_items'][0]['price_data']['unit_amount'] ?? null;
		$this->assertSame( '100000', (string) $unit_amount );
	}

	// ── REST: POST /payments/create-session ───────────────────────────────────

	/** Valid token + mocked Stripe returns checkout_url. */
	public function test_create_session_success(): void {
		$this->mock_stripe( [
			'id'  => $this->session_id,
			'url' => 'https://checkout.stripe.com/pay/' . $this->session_id,
		] );

		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/create-session' );
		$request->set_param( 'token', $this->token );

		$response = cf_rest_payment_create_session( $request );

		$this->clear_http_mocks();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'checkout_url', $data );
		$this->assertArrayHasKey( 'session_id', $data );
		$this->assertSame( $this->session_id, $data['session_id'] );
	}

	/** Invalid token returns 404. */
	public function test_create_session_invalid_token(): void {
		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/create-session' );
		$request->set_param( 'token', 'not-a-real-token-xxxx' );

		$result = cf_rest_payment_create_session( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	/** Payment not enabled on proposal returns 403. */
	public function test_create_session_payment_not_enabled(): void {
		// Create a free-plan user — payment won't be enabled.
		$free_id = $this->factory->user->create();
		ClientFlow_Entitlements::set_user_plan( $free_id, 'free' );
		$free_proposal_id = ClientFlow_Proposal::create( $free_id, [ 'title' => 'Free', 'total_amount' => 100 ] );

		global $wpdb;
		$free_token = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT token FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d", $free_proposal_id )
		);

		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/create-session' );
		$request->set_param( 'token', $free_token );

		$result = cf_rest_payment_create_session( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/** Expired proposal cannot initiate payment (422). */
	public function test_create_session_expired_proposal(): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'clientflow_proposals',
			[ 'status' => 'expired' ],
			[ 'id' => $this->proposal_id ]
		);

		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/create-session' );
		$request->set_param( 'token', $this->token );

		$result = cf_rest_payment_create_session( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	/** Stripe not configured returns 503. */
	public function test_create_session_stripe_not_configured(): void {
		delete_option( 'clientflow_stripe_secret_key' );

		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/create-session' );
		$request->set_param( 'token', $this->token );

		$result = cf_rest_payment_create_session( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 503, $result->get_error_data()['status'] );
	}

	// ── REST: GET /payments/status ────────────────────────────────────────────

	/** Returns correct status for known session. */
	public function test_payment_status_from_db(): void {
		ClientFlow_Payment::create( $this->proposal_id, $this->owner_id, [
			'amount'     => 1000.00,
			'currency'   => 'GBP',
			'deposit_pct' => 50,
			'session_id' => 'cs_test_status_01',
		] );

		ClientFlow_Payment::mark_complete( 'cs_test_status_01', 'pi_status', null );

		$request = new WP_REST_Request( 'GET', '/clientflow/v1/payments/status' );
		$request->set_param( 'session_id', 'cs_test_status_01' );

		$response = cf_rest_payment_status( $request );
		$data     = $response->get_data();

		$this->assertSame( 'completed', $data['status'] );
		$this->assertSame( 1000.0, $data['amount'] );
	}

	// ── REST: POST /payments/webhook ──────────────────────────────────────────

	/** checkout.session.completed marks payment complete and logs event. */
	public function test_webhook_checkout_completed(): void {
		global $wpdb;

		// Create a pending payment record.
		ClientFlow_Payment::create( $this->proposal_id, $this->owner_id, [
			'amount'     => 1000.00,
			'currency'   => 'GBP',
			'deposit_pct' => 50,
			'session_id' => 'cs_test_wh_01',
		] );

		$event = [
			'type' => 'checkout.session.completed',
			'data' => [
				'object' => [
					'id'             => 'cs_test_wh_01',
					'payment_intent' => 'pi_wh_test',
					'customer'       => 'cus_wh_test',
					'amount_total'   => 100000,
					'currency'       => 'gbp',
					'metadata'       => [
						'proposal_id' => (string) $this->proposal_id,
						'token'       => $this->token,
						'deposit_pct' => '50',
					],
					'payment_status' => 'paid',
				],
			],
		];

		$payload    = wp_json_encode( $event );
		$secret     = 'whsec_test_secret';
		$timestamp  = (string) time();
		$signed     = $timestamp . '.' . $payload;
		$sig        = hash_hmac( 'sha256', $signed, $secret );
		$sig_header = "t={$timestamp},v1={$sig}";

		update_option( 'clientflow_stripe_webhook_secret', $secret );

		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/webhook' );
		$request->set_body( $payload );
		$request->add_header( 'stripe-signature', $sig_header );

		$response = cf_rest_payment_webhook( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		// Payment should now be completed.
		$payment = ClientFlow_Payment::get_by_session_id( 'cs_test_wh_01' );
		$this->assertSame( 'completed', $payment['status'] );
		$this->assertSame( 'pi_wh_test', $payment['stripe_payment_intent_id'] );

		// Event should be logged.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_events
				 WHERE proposal_id = %d AND event_type = 'payment_completed'",
				$this->proposal_id
			)
		);
		$this->assertSame( 1, $count );
	}

	/** Webhook with invalid signature returns 400. */
	public function test_webhook_invalid_signature(): void {
		$request = new WP_REST_Request( 'POST', '/clientflow/v1/payments/webhook' );
		$request->set_body( '{"type":"checkout.session.completed","data":{"object":{}}}' );
		$request->add_header( 'stripe-signature', 't=999,v1=invalidsig' );

		$result = cf_rest_payment_webhook( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/** checkout.session.completed transitions draft proposal to accepted. */
	public function test_webhook_transitions_proposal_to_accepted(): void {
		global $wpdb;

		// Proposal is still draft.
		$status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d", $this->proposal_id )
		);
		$this->assertSame( 'draft', $status );

		cf_handle_checkout_complete( [
			'id'             => 'cs_test_transition_01',
			'payment_intent' => 'pi_transition',
			'customer'       => null,
			'amount_total'   => 200000,
			'currency'       => 'gbp',
			'metadata'       => [
				'proposal_id' => (string) $this->proposal_id,
				'token'       => $this->token,
				'deposit_pct' => '100',
			],
		] );

		$new_status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d", $this->proposal_id )
		);
		$this->assertSame( 'accepted', $new_status );
	}

	/** Already-accepted proposals are NOT downgraded by the webhook. */
	public function test_webhook_does_not_overwrite_accepted(): void {
		global $wpdb;

		// Manually set to accepted.
		$wpdb->update(
			$wpdb->prefix . 'clientflow_proposals',
			[ 'status' => 'accepted' ],
			[ 'id' => $this->proposal_id ]
		);

		cf_handle_checkout_complete( [
			'id'             => 'cs_test_noop_01',
			'payment_intent' => 'pi_noop',
			'customer'       => null,
			'amount_total'   => 200000,
			'currency'       => 'gbp',
			'metadata'       => [
				'proposal_id' => (string) $this->proposal_id,
				'token'       => $this->token,
			],
		] );

		$status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$wpdb->prefix}clientflow_proposals WHERE id = %d", $this->proposal_id )
		);
		$this->assertSame( 'accepted', $status );
	}
}
