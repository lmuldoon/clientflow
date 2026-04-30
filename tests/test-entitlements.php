<?php
/**
 * ClientFlow Entitlements Test Suite
 *
 * Tests every path through ClientFlow_Entitlements::can_user().
 * Also tests log_usage(), reset_monthly_usage(), set_user_plan(),
 * check_rate_limit(), and get_feature_limit().
 *
 * Run with:
 *   ./vendor/bin/phpunit --testdox
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

/**
 * Class Test_Entitlements
 *
 * @group entitlements
 */
class Test_Entitlements extends WP_UnitTestCase {

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Create a user and ensure a user_meta row exists with the given plan.
	 *
	 * @param string $plan 'free' | 'pro' | 'agency'
	 *
	 * @return int WordPress user ID.
	 */
	private function make_user( string $plan = 'free' ): int {
		$user_id = self::factory()->user->create();
		ClientFlow_Entitlements::ensure_user_meta( $user_id );
		ClientFlow_Entitlements::set_user_plan( $user_id, $plan );

		return $user_id;
	}

	/**
	 * Insert N AI log rows directly for a user in the current month.
	 *
	 * @param int    $user_id
	 * @param int    $count
	 * @param string $month YYYY-MM (defaults to current month).
	 */
	private function log_ai_rows( int $user_id, int $count, string $month = '' ): void {
		global $wpdb;

		$month = $month ?: gmdate( 'Y-m' );

		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'clientflow_ai_usage_logs',
				[
					'user_id'   => $user_id,
					'action'    => 'improve',
					'month'     => $month,
					'timestamp' => current_time( 'mysql' ),
				],
				[ '%d', '%s', '%s', '%s' ]
			);
		}
	}

	// ─── Plan: Free ───────────────────────────────────────────────────────────

	/**
	 * @test
	 * Free user — plan defaults to 'free' (no row yet).
	 */
	public function test_new_user_defaults_to_free_plan(): void {
		$user_id = self::factory()->user->create();
		$this->assertSame( 'free', ClientFlow_Entitlements::get_user_plan( $user_id ) );
	}

	/**
	 * @test
	 * Free user — cannot use AI.
	 */
	public function test_free_user_cannot_use_ai(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) );
	}

	/**
	 * @test
	 * Free user — cannot collect payments.
	 */
	public function test_free_user_cannot_use_payments(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_payments' ) );
	}

	/**
	 * @test
	 * Free user — has no portal access.
	 */
	public function test_free_user_has_no_portal(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_portal' ) );
	}

	/**
	 * @test
	 * Free user — cannot access projects.
	 */
	public function test_free_user_cannot_use_projects(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_projects' ) );
	}

	/**
	 * @test
	 * Free user — cannot use messaging.
	 */
	public function test_free_user_cannot_use_messaging(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_messaging' ) );
	}

	/**
	 * @test
	 * Free user — cannot upload files.
	 */
	public function test_free_user_cannot_use_files(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_files' ) );
	}

	/**
	 * @test
	 * Free user — can create proposals (under 5 total).
	 */
	public function test_free_user_can_create_proposal_under_limit(): void {
		$user_id = $this->make_user( 'free' );
		// No proposals yet.
		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'create_proposal' ) );
	}

	/**
	 * @test
	 * Free user — blocked at proposal limit of 5.
	 */
	public function test_free_user_proposal_limit_blocks_at_five(): void {
		$user_id = $this->make_user( 'free' );

		// Simulate 5 proposals created.
		for ( $i = 0; $i < 5; $i++ ) {
			ClientFlow_Entitlements::log_usage( $user_id, 'create_proposal' );
		}

		// 6th should be denied.
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'create_proposal' ) );
	}

	/**
	 * @test
	 * Free user — exactly at limit (5) is blocked.
	 */
	public function test_free_user_at_exactly_five_proposals_is_blocked(): void {
		$user_id = $this->make_user( 'free' );

		for ( $i = 0; $i < 5; $i++ ) {
			ClientFlow_Entitlements::log_usage( $user_id, 'create_proposal' );
		}

		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'create_proposal' ) );
	}

	/**
	 * @test
	 * Free user — 4th proposal is still allowed.
	 */
	public function test_free_user_fourth_proposal_is_allowed(): void {
		$user_id = $this->make_user( 'free' );

		for ( $i = 0; $i < 4; $i++ ) {
			ClientFlow_Entitlements::log_usage( $user_id, 'create_proposal' );
		}

		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'create_proposal' ) );
	}

	// ─── Plan: Pro ────────────────────────────────────────────────────────────

	/**
	 * @test
	 * Pro user — can use AI.
	 */
	public function test_pro_user_can_use_ai(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) );
	}

	/**
	 * @test
	 * Pro user — can collect payments.
	 */
	public function test_pro_user_can_use_payments(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'use_payments' ) );
	}

	/**
	 * @test
	 * Pro user — has basic portal access (string 'basic').
	 */
	public function test_pro_user_has_basic_portal(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertSame( 'basic', ClientFlow_Entitlements::can_user( $user_id, 'use_portal' ) );
	}

	/**
	 * @test
	 * Pro user — cannot access projects.
	 */
	public function test_pro_user_cannot_use_projects(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_projects' ) );
	}

	/**
	 * @test
	 * Pro user — cannot use messaging.
	 */
	public function test_pro_user_cannot_use_messaging(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_messaging' ) );
	}

	/**
	 * @test
	 * Pro user — can create unlimited proposals.
	 */
	public function test_pro_user_can_create_unlimited_proposals(): void {
		$user_id = $this->make_user( 'pro' );

		// Even after 100 proposals, should still be allowed.
		for ( $i = 0; $i < 100; $i++ ) {
			ClientFlow_Entitlements::log_usage( $user_id, 'create_proposal' );
		}

		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'create_proposal' ) );
	}

	/**
	 * @test
	 * Pro user — blocked at monthly AI limit of 100.
	 */
	public function test_pro_user_blocked_at_ai_monthly_limit(): void {
		$user_id = $this->make_user( 'pro' );

		$this->log_ai_rows( $user_id, 100 );

		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) );
	}

	/**
	 * @test
	 * Pro user — still allowed at 99 AI requests.
	 */
	public function test_pro_user_allowed_at_99_ai_requests(): void {
		$user_id = $this->make_user( 'pro' );

		$this->log_ai_rows( $user_id, 99 );

		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) );
	}

	/**
	 * @test
	 * Pro user — AI limit is 100.
	 */
	public function test_pro_user_ai_limit_is_100(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertSame( 100, ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' ) );
	}

	// ─── Plan: Agency ─────────────────────────────────────────────────────────

	/**
	 * @test
	 * Agency user — can access projects.
	 */
	public function test_agency_user_can_use_projects(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'use_projects' ) );
	}

	/**
	 * @test
	 * Agency user — can use messaging.
	 */
	public function test_agency_user_can_use_messaging(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'use_messaging' ) );
	}

	/**
	 * @test
	 * Agency user — has full portal access (string 'full').
	 */
	public function test_agency_user_has_full_portal(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertSame( 'full', ClientFlow_Entitlements::can_user( $user_id, 'use_portal' ) );
	}

	/**
	 * @test
	 * Agency user — can upload files (within 1 GB).
	 */
	public function test_agency_user_can_use_files(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'use_files' ) );
	}

	/**
	 * @test
	 * Agency user — blocked when storage exceeds 1 GB.
	 */
	public function test_agency_user_blocked_when_storage_full(): void {
		global $wpdb;

		$user_id = $this->make_user( 'agency' );

		// Set storage_used_mb to exactly 1000 MB.
		$wpdb->update(
			$wpdb->prefix . 'clientflow_user_meta',
			[ 'storage_used_mb' => 1000 ],
			[ 'user_id' => $user_id ],
			[ '%d' ],
			[ '%d' ]
		);

		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_files' ) );
	}

	/**
	 * @test
	 * Agency user — AI limit is 500.
	 */
	public function test_agency_user_ai_limit_is_500(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertSame( 500, ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' ) );
	}

	/**
	 * @test
	 * Agency user — blocked at monthly AI limit of 500.
	 */
	public function test_agency_user_blocked_at_ai_monthly_limit(): void {
		$user_id = $this->make_user( 'agency' );

		$this->log_ai_rows( $user_id, 500 );

		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) );
	}

	/**
	 * @test
	 * Agency user — team limit is 5.
	 */
	public function test_agency_team_limit_is_five(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertSame( 5, ClientFlow_Entitlements::get_team_limit( $user_id ) );
	}

	// ─── Team Seats ───────────────────────────────────────────────────────────

	/**
	 * @test
	 * Free and Pro users — team seat limit is 1.
	 */
	public function test_free_and_pro_team_limit_is_one(): void {
		$free_id = $this->make_user( 'free' );
		$pro_id  = $this->make_user( 'pro' );

		$this->assertSame( 1, ClientFlow_Entitlements::get_team_limit( $free_id ) );
		$this->assertSame( 1, ClientFlow_Entitlements::get_team_limit( $pro_id ) );
	}

	/**
	 * @test
	 * Team access blocked when seats are full.
	 */
	public function test_team_access_blocked_when_seats_full(): void {
		global $wpdb;

		$user_id = $this->make_user( 'agency' );

		$wpdb->update(
			$wpdb->prefix . 'clientflow_user_meta',
			[ 'team_seats_used' => 5 ],
			[ 'user_id' => $user_id ],
			[ '%d' ],
			[ '%d' ]
		);

		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'team_access' ) );
	}

	// ─── Unknown Features ─────────────────────────────────────────────────────

	/**
	 * @test
	 * Unknown feature slug — returns false (deny by default).
	 */
	public function test_unknown_feature_returns_false(): void {
		$user_id = $this->make_user( 'agency' );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'nonexistent_feature_xyz' ) );
	}

	// ─── Usage Logging ────────────────────────────────────────────────────────

	/**
	 * @test
	 * log_usage increments proposal_created_total.
	 */
	public function test_log_usage_increments_proposal_total(): void {
		$user_id = $this->make_user( 'free' );

		ClientFlow_Entitlements::log_usage( $user_id, 'create_proposal' );
		ClientFlow_Entitlements::log_usage( $user_id, 'create_proposal' );

		$this->assertSame( 2, ClientFlow_Entitlements::get_total_count( $user_id, 'create_proposal' ) );
	}

	/**
	 * @test
	 * log_usage for AI inserts a row into ai_usage_logs.
	 */
	public function test_log_usage_ai_inserts_log_row(): void {
		global $wpdb;

		$user_id = $this->make_user( 'pro' );

		ClientFlow_Entitlements::log_usage( $user_id, 'use_ai', [
			'action'        => 'improve',
			'tokens_input'  => 50,
			'tokens_output' => 80,
			'cost_usd'      => 0.001,
		] );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_ai_usage_logs WHERE user_id = %d",
				$user_id
			)
		);

		$this->assertSame( 1, $count );
	}

	/**
	 * @test
	 * get_monthly_usage reflects log_usage calls.
	 */
	public function test_get_monthly_usage_reflects_log_calls(): void {
		$user_id = $this->make_user( 'pro' );

		ClientFlow_Entitlements::log_usage( $user_id, 'use_ai', [ 'action' => 'shorten' ] );
		ClientFlow_Entitlements::log_usage( $user_id, 'use_ai', [ 'action' => 'improve' ] );

		$this->assertSame( 2, ClientFlow_Entitlements::get_monthly_usage( $user_id, 'use_ai' ) );
	}

	// ─── Monthly Reset ────────────────────────────────────────────────────────

	/**
	 * @test
	 * reset_monthly_usage zeroes AI usage for all users.
	 */
	public function test_reset_monthly_usage_zeroes_ai_count(): void {
		global $wpdb;

		$user_id = $this->make_user( 'pro' );

		// Simulate mid-month usage in user_meta.
		$wpdb->update(
			$wpdb->prefix . 'clientflow_user_meta',
			[ 'ai_usage_count' => 42 ],
			[ 'user_id' => $user_id ],
			[ '%d' ],
			[ '%d' ]
		);

		ClientFlow_Entitlements::reset_monthly_usage();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ai_usage_count FROM {$wpdb->prefix}clientflow_user_meta WHERE user_id = %d",
				$user_id
			)
		);

		$this->assertSame( 0, $count );
	}

	/**
	 * @test
	 * After reset, a Pro user can use AI again.
	 */
	public function test_pro_user_can_use_ai_after_monthly_reset(): void {
		$user_id = $this->make_user( 'pro' );

		// Max out AI for this month.
		$this->log_ai_rows( $user_id, 100 );
		$this->assertFalse( ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) );

		// Trigger cron reset and clear log rows.
		ClientFlow_Entitlements::reset_monthly_usage();

		// Flush the ai_usage_logs for this user to simulate a new month.
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'clientflow_ai_usage_logs',
			[ 'user_id' => $user_id ],
			[ '%d' ]
		);

		$this->assertTrue( (bool) ClientFlow_Entitlements::can_user( $user_id, 'use_ai' ) );
	}

	// ─── Plan Management ──────────────────────────────────────────────────────

	/**
	 * @test
	 * set_user_plan upgrades a Free user to Pro.
	 */
	public function test_set_user_plan_upgrades_to_pro(): void {
		$user_id = $this->make_user( 'free' );

		ClientFlow_Entitlements::set_user_plan( $user_id, 'pro' );

		$this->assertSame( 'pro', ClientFlow_Entitlements::get_user_plan( $user_id ) );
	}

	/**
	 * @test
	 * set_user_plan rejects invalid plan slugs.
	 */
	public function test_set_user_plan_rejects_invalid_plan(): void {
		$user_id = $this->make_user( 'free' );

		$result = ClientFlow_Entitlements::set_user_plan( $user_id, 'enterprise' );

		$this->assertFalse( $result );
		$this->assertSame( 'free', ClientFlow_Entitlements::get_user_plan( $user_id ) );
	}

	// ─── Feature Limits ───────────────────────────────────────────────────────

	/**
	 * @test
	 * get_feature_limit returns null (unlimited) for Pro proposals.
	 */
	public function test_feature_limit_null_for_pro_proposals(): void {
		$user_id = $this->make_user( 'pro' );
		$this->assertNull( ClientFlow_Entitlements::get_feature_limit( $user_id, 'create_proposal' ) );
	}

	/**
	 * @test
	 * get_feature_limit returns 5 for Free proposals.
	 */
	public function test_feature_limit_five_for_free_proposals(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertSame( 5, ClientFlow_Entitlements::get_feature_limit( $user_id, 'create_proposal' ) );
	}

	/**
	 * @test
	 * get_feature_limit returns 0 for blocked features.
	 */
	public function test_feature_limit_zero_for_blocked_feature(): void {
		$user_id = $this->make_user( 'free' );
		$this->assertSame( 0, ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' ) );
	}

	// ─── Rate Limiting ────────────────────────────────────────────────────────

	/**
	 * @test
	 * check_rate_limit allows first request.
	 */
	public function test_rate_limit_allows_first_request(): void {
		$user_id = $this->make_user( 'pro' );

		// Clear any transient from prior test runs.
		delete_transient( "cf_rate_limit_{$user_id}" );

		$this->assertTrue( ClientFlow_Entitlements::check_rate_limit( $user_id ) );
	}

	/**
	 * @test
	 * check_rate_limit blocks second immediate request.
	 */
	public function test_rate_limit_blocks_immediate_second_request(): void {
		$user_id = $this->make_user( 'pro' );

		delete_transient( "cf_rate_limit_{$user_id}" );

		ClientFlow_Entitlements::check_rate_limit( $user_id ); // First — allowed.
		$result = ClientFlow_Entitlements::check_rate_limit( $user_id ); // Second — blocked.

		$this->assertFalse( $result );
	}
}
