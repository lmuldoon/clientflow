<?php
/**
 * ClientFlow Projects & Milestones Test Suite (Sprint 5)
 *
 * Tests for ClientFlow_Project, ClientFlow_Milestone, and
 * ClientFlow_Project_Handlers.
 *
 * Run with: ./vendor/bin/phpunit --testdox --filter Test_Projects
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/modules/projects/class-project.php';
require_once dirname( __DIR__ ) . '/modules/projects/class-milestone.php';
require_once dirname( __DIR__ ) . '/modules/projects/handlers.php';
require_once dirname( __DIR__ ) . '/modules/portal/class-portal-data.php';

/**
 * Class Test_Projects
 *
 * @group projects
 */
class Test_Projects extends WP_UnitTestCase {

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function make_user( string $plan = 'agency' ): int {
		$user_id = self::factory()->user->create();
		ClientFlow_Entitlements::ensure_user_meta( $user_id );
		ClientFlow_Entitlements::set_user_plan( $user_id, $plan );
		return $user_id;
	}

	private function make_client( int $owner_id, string $email = 'client@example.com' ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$wpdb->prefix . 'clientflow_clients',
			[
				'owner_id'   => $owner_id,
				'name'       => 'Test Client',
				'email'      => $email,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);
		return (int) $wpdb->insert_id;
	}

	private function make_proposal( int $owner_id, int $client_id, string $title = 'Test Proposal' ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$wpdb->prefix . 'clientflow_proposals',
			[
				'owner_id'    => $owner_id,
				'client_id'   => $client_id,
				'title'       => $title,
				'token'       => wp_generate_uuid4(),
				'status'      => 'accepted',
				'accepted_at' => $now,
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
		return (int) $wpdb->insert_id;
	}

	// ── Project tests ─────────────────────────────────────────────────────────

	/** Agency plan: cf_proposal_accepted creates a project automatically. */
	public function test_agency_creates_project_on_acceptance(): void {
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id );
		$proposal_id = $this->make_proposal( $owner_id, $client_id, 'Sprint 5 Proposal' );

		do_action( 'cf_proposal_accepted', $proposal_id, $owner_id );

		$project = ClientFlow_Project::get_by_proposal( $proposal_id );

		$this->assertFalse( is_wp_error( $project ), 'Project should be created for agency user' );
		$this->assertSame( $proposal_id, $project['proposal_id'] );
		$this->assertSame( $owner_id,    $project['owner_id'] );
		$this->assertSame( 'Sprint 5 Proposal', $project['name'] );
	}

	/** Free plan: cf_proposal_accepted does NOT create a project. */
	public function test_free_plan_skips_project_creation(): void {
		global $wpdb;
		$owner_id    = $this->make_user( 'free' );
		$client_id   = $this->make_client( $owner_id, 'free@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id );

		do_action( 'cf_proposal_accepted', $proposal_id, $owner_id );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_projects WHERE owner_id = %d",
				$owner_id
			)
		);

		$this->assertSame( 0, $count, 'Free plan should not trigger project creation' );
	}

	/** Firing cf_proposal_accepted twice creates only one project (idempotent). */
	public function test_project_creation_is_idempotent(): void {
		global $wpdb;
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id, 'idempotent@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id );

		do_action( 'cf_proposal_accepted', $proposal_id, $owner_id );
		do_action( 'cf_proposal_accepted', $proposal_id, $owner_id );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_projects WHERE proposal_id = %d",
				$proposal_id
			)
		);

		$this->assertSame( 1, $count, 'Only one project should exist per proposal' );
	}

	/** ClientFlow_Project::get() joins client and proposal data. */
	public function test_get_includes_joined_data(): void {
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id, 'joined@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id, 'Join Test' );

		$project_id = ClientFlow_Project::create( $owner_id, [
			'name'        => 'Joined Project',
			'proposal_id' => $proposal_id,
			'client_id'   => $client_id,
		] );

		$project = ClientFlow_Project::get( $project_id, $owner_id );

		$this->assertFalse( is_wp_error( $project ) );
		$this->assertSame( 'Test Client', $project['client_name'] );
		$this->assertSame( 'Join Test',   $project['proposal_title'] );
	}

	// ── Milestone / progress tests ────────────────────────────────────────────

	/** 0 milestones = 0% progress. */
	public function test_progress_zero_milestones(): void {
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id, 'zero@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id );

		$project_id = ClientFlow_Project::create( $owner_id, [
			'name'        => 'Empty',
			'proposal_id' => $proposal_id,
			'client_id'   => $client_id,
		] );

		$project = ClientFlow_Project::get( $project_id, $owner_id );
		$this->assertSame( 0, $project['progress_pct'] );
	}

	/** 2 of 4 milestones completed = 50%. */
	public function test_progress_partial_milestones(): void {
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id, 'partial@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id );

		$project_id = ClientFlow_Project::create( $owner_id, [
			'name'        => 'Partial',
			'proposal_id' => $proposal_id,
			'client_id'   => $client_id,
		] );

		$ids = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			$ids[] = ClientFlow_Milestone::create( $project_id, $owner_id, [ 'title' => "M$i" ] );
		}

		ClientFlow_Milestone::update( $ids[0], $owner_id, [ 'status' => 'completed' ] );
		ClientFlow_Milestone::update( $ids[1], $owner_id, [ 'status' => 'completed' ] );

		$project = ClientFlow_Project::get( $project_id, $owner_id );
		$this->assertSame( 4,  $project['milestone_total'] );
		$this->assertSame( 2,  $project['milestone_completed'] );
		$this->assertSame( 50, $project['progress_pct'] );
	}

	/** 1 of 1 milestones completed = 100%. */
	public function test_progress_all_completed(): void {
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id, 'full@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id );

		$project_id = ClientFlow_Project::create( $owner_id, [
			'name'        => 'Full',
			'proposal_id' => $proposal_id,
			'client_id'   => $client_id,
		] );

		$mid = ClientFlow_Milestone::create( $project_id, $owner_id, [ 'title' => 'Only' ] );
		ClientFlow_Milestone::update( $mid, $owner_id, [ 'status' => 'completed' ] );

		$project = ClientFlow_Project::get( $project_id, $owner_id );
		$this->assertSame( 100, $project['progress_pct'] );
	}

	/** Milestone reorder persists new sort_order. */
	public function test_milestone_reorder(): void {
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id, 'reorder@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id );

		$project_id = ClientFlow_Project::create( $owner_id, [
			'name'        => 'Reorder',
			'proposal_id' => $proposal_id,
			'client_id'   => $client_id,
		] );

		$a = ClientFlow_Milestone::create( $project_id, $owner_id, [ 'title' => 'A' ] );
		$b = ClientFlow_Milestone::create( $project_id, $owner_id, [ 'title' => 'B' ] );
		$c = ClientFlow_Milestone::create( $project_id, $owner_id, [ 'title' => 'C' ] );

		// Reverse: C, A, B.
		ClientFlow_Milestone::reorder( $project_id, $owner_id, [ $c, $a, $b ] );

		$list = ClientFlow_Milestone::list( $project_id, $owner_id );
		$this->assertSame( $c, $list[0]['id'] );
		$this->assertSame( $a, $list[1]['id'] );
		$this->assertSame( $b, $list[2]['id'] );
	}

	/** Deleting a project removes all its milestones. */
	public function test_project_delete_cascades_milestones(): void {
		global $wpdb;
		$owner_id    = $this->make_user( 'agency' );
		$client_id   = $this->make_client( $owner_id, 'cascade@example.com' );
		$proposal_id = $this->make_proposal( $owner_id, $client_id );

		$project_id = ClientFlow_Project::create( $owner_id, [
			'name'        => 'Cascade',
			'proposal_id' => $proposal_id,
			'client_id'   => $client_id,
		] );

		ClientFlow_Milestone::create( $project_id, $owner_id, [ 'title' => 'M1' ] );
		ClientFlow_Milestone::create( $project_id, $owner_id, [ 'title' => 'M2' ] );

		ClientFlow_Project::delete( $project_id, $owner_id );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}clientflow_milestones WHERE project_id = %d",
				$project_id
			)
		);
		$this->assertSame( 0, $count );
	}

	/** Portal data isolation: a client only sees their own projects. */
	public function test_portal_projects_isolation(): void {
		// Owner A + Client A.
		$owner_a    = $this->make_user( 'agency' );
		$client_a   = $this->make_client( $owner_a, 'client_a@example.com' );
		$proposal_a = $this->make_proposal( $owner_a, $client_a, 'Proposal A' );
		ClientFlow_Project::create( $owner_a, [
			'name'        => 'Project A',
			'proposal_id' => $proposal_a,
			'client_id'   => $client_a,
		] );

		// Owner B + Client B (different email).
		$owner_b    = $this->make_user( 'agency' );
		$client_b   = $this->make_client( $owner_b, 'client_b@example.com' );
		$proposal_b = $this->make_proposal( $owner_b, $client_b, 'Proposal B' );
		ClientFlow_Project::create( $owner_b, [
			'name'        => 'Project B',
			'proposal_id' => $proposal_b,
			'client_id'   => $client_b,
		] );

		// Portal user with client_a's email should only see Project A.
		$portal_user = self::factory()->user->create( [ 'user_email' => 'client_a@example.com' ] );
		$projects    = ClientFlow_Portal_Data::get_projects( $portal_user );

		$names = array_column( $projects, 'name' );
		$this->assertContains( 'Project A', $names, 'Should see own project' );
		$this->assertNotContains( 'Project B', $names, 'Should not see other client project' );
	}
}
