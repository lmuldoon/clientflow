<?php
/**
 * ClientFlow Database Schema
 *
 * Creates all 10 ClientFlow tables via dbDelta() on plugin activation.
 * Safe to call multiple times — dbDelta only applies diffs.
 *
 * Tables:
 *   1.  clientflow_user_meta       — per-user plan, usage, billing
 *   2.  clientflow_ai_usage_logs   — AI request audit trail
 *   3.  clientflow_clients         — client records
 *   4.  clientflow_proposals       — proposal data
 *   5.  clientflow_projects        — post-acceptance project tracking
 *   6.  clientflow_milestones      — project milestone steps (Agency)
 *   7.  clientflow_payments        — Stripe payment records
 *   8.  clientflow_messages        — threaded messaging (Agency)
 *   9.  clientflow_files           — file uploads per project (Agency)
 *   10. clientflow_approvals       — approval workflows (Agency)
 *   11. clientflow_events          — analytics event log
 *
 * @package ClientFlow
 * @since   0.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create or upgrade all ClientFlow database tables.
 *
 * Uses the WordPress dbDelta() function so it is safe to run on every
 * plugin activation — it only modifies tables when the definition changes.
 *
 * @return void
 */
function clientflow_create_tables(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// ────────────────────────────────────────────────────────────────────────
	// Table 1: clientflow_user_meta
	// Foundation for entitlements — single source of truth per user.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_user_meta (
		id INT NOT NULL AUTO_INCREMENT,
		user_id INT NOT NULL,
		plan ENUM('free','pro','agency') NOT NULL DEFAULT 'free',

		ai_usage_count INT NOT NULL DEFAULT 0,
		ai_usage_month VARCHAR(7) DEFAULT NULL,

		proposals_created_total INT NOT NULL DEFAULT 0,
		proposal_count_month INT NOT NULL DEFAULT 0,

		billing_cycle_start DATE DEFAULT NULL,
		billing_cycle_end DATE DEFAULT NULL,
		stripe_customer_id VARCHAR(255) DEFAULT NULL,

		team_seats_used INT NOT NULL DEFAULT 1,
		storage_used_mb INT NOT NULL DEFAULT 0,

		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		UNIQUE KEY user_id (user_id),
		KEY plan (plan),
		KEY ai_usage_month (ai_usage_month),
		KEY billing_cycle_start (billing_cycle_start)
	) $charset_collate;" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 2: clientflow_ai_usage_logs
	// Audit trail for every AI call — enables cost tracking and analytics.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_ai_usage_logs (
		id INT NOT NULL AUTO_INCREMENT,
		user_id INT NOT NULL,
		proposal_id INT DEFAULT NULL,
		action VARCHAR(50) DEFAULT NULL,

		tokens_input INT DEFAULT NULL,
		tokens_output INT DEFAULT NULL,
		cost_usd DECIMAL(10,4) DEFAULT NULL,

		timestamp DATETIME DEFAULT NULL,
		month VARCHAR(7) DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY user_month (user_id, month),
		KEY timestamp (timestamp),
		KEY proposal_id (proposal_id),
		KEY action (action)
	) $charset_collate;" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 3: clientflow_clients
	// Freelancer's/agency's client records.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_clients (
		id INT NOT NULL AUTO_INCREMENT,
		owner_id INT NOT NULL,
		wp_user_id INT DEFAULT NULL,

		name VARCHAR(255) NOT NULL,
		email VARCHAR(255) DEFAULT NULL,
		company VARCHAR(255) DEFAULT NULL,
		phone VARCHAR(20) DEFAULT NULL,

		portal_invited_at DATETIME DEFAULT NULL,

		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY owner_id (owner_id),
		KEY email (email),
		KEY created_at (created_at)
	) $charset_collate;" );

	// Ensure portal_invited_at exists on existing installations.
	$wpdb->query( "ALTER TABLE {$wpdb->prefix}clientflow_clients ADD COLUMN IF NOT EXISTS portal_invited_at DATETIME DEFAULT NULL" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 4: clientflow_proposals
	// Core proposal data. content is a JSON block structure.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_proposals (
		id INT NOT NULL AUTO_INCREMENT,
		owner_id INT NOT NULL,
		client_id INT DEFAULT NULL,

		title VARCHAR(255) NOT NULL,
		content LONGTEXT DEFAULT NULL,

		token VARCHAR(64) NOT NULL DEFAULT '',

		status ENUM('draft','sent','viewed','accepted','declined','expired','completed') NOT NULL DEFAULT 'draft',

		total_amount DECIMAL(12,2) DEFAULT NULL,
		currency VARCHAR(3) NOT NULL DEFAULT 'GBP',

		payment_enabled TINYINT(1) NOT NULL DEFAULT 0,

		expiry_date DATETIME DEFAULT NULL,
		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,
		sent_at DATETIME DEFAULT NULL,
		viewed_at DATETIME DEFAULT NULL,
		accepted_at DATETIME DEFAULT NULL,
		declined_at DATETIME DEFAULT NULL,
		decline_reason TEXT DEFAULT NULL,
		deleted_at DATETIME DEFAULT NULL,

		template_id VARCHAR(50) DEFAULT NULL,

		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		KEY owner_id (owner_id),
		KEY client_id (client_id),
		KEY status (status),
		KEY created_at (created_at),
		KEY template_id (template_id)
	) $charset_collate;" );

	// Ensure 'completed' status and deleted_at exist on existing installations.
	$wpdb->query(
		"ALTER TABLE {$wpdb->prefix}clientflow_proposals
		 MODIFY COLUMN status ENUM('draft','sent','viewed','accepted','declined','expired','completed') NOT NULL DEFAULT 'draft'"
	);
	$wpdb->query( "ALTER TABLE {$wpdb->prefix}clientflow_proposals ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT NULL" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 5: clientflow_projects
	// Auto-created when a proposal is accepted (Agency tier).
	// Links proposal → delivery work.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_projects (
		id INT NOT NULL AUTO_INCREMENT,
		owner_id INT NOT NULL,
		client_id INT NOT NULL,
		proposal_id INT NOT NULL,

		name VARCHAR(255) NOT NULL,
		description TEXT DEFAULT NULL,

		status ENUM('active','on-hold','completed') NOT NULL DEFAULT 'active',

		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,
		completed_at DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY owner_id (owner_id),
		KEY client_id (client_id),
		KEY proposal_id (proposal_id),
		KEY status (status)
	) $charset_collate;" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 6: clientflow_milestones
	// Step-level milestones within a project. Agency tier.
	// sort_order controls display sequence (drag-to-reorder).
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_milestones (
		id           INT NOT NULL AUTO_INCREMENT,
		project_id   INT NOT NULL,
		owner_id     INT NOT NULL,

		title        VARCHAR(255) NOT NULL DEFAULT '',
		description  TEXT DEFAULT NULL,

		status       ENUM('pending','submitted','in-progress','completed') NOT NULL DEFAULT 'pending',

		due_date     DATE DEFAULT NULL,
		completed_at DATETIME DEFAULT NULL,

		sort_order   SMALLINT NOT NULL DEFAULT 0,
		created_at   DATETIME DEFAULT NULL,
		updated_at   DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY project_id (project_id),
		KEY owner_id (owner_id),
		KEY status (status)
	) $charset_collate;" );

	// Ensure the 'submitted' status exists for existing installations (dbDelta won't modify ENUMs).
	$wpdb->query(
		"ALTER TABLE {$wpdb->prefix}clientflow_milestones
		 MODIFY COLUMN status ENUM('pending','submitted','in-progress','completed') NOT NULL DEFAULT 'pending'"
	);

	// ────────────────────────────────────────────────────────────────────────
	// Table 7: clientflow_payments
	// Stripe payment records. Pro + Agency tiers.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_payments (
		id INT NOT NULL AUTO_INCREMENT,
		proposal_id INT NOT NULL,
		client_id INT DEFAULT NULL,
		owner_id INT NOT NULL,

		amount DECIMAL(12,2) NOT NULL,
		currency VARCHAR(3) NOT NULL DEFAULT 'GBP',
		deposit_pct TINYINT UNSIGNED NOT NULL DEFAULT 100,

		stripe_session_id VARCHAR(255) DEFAULT NULL,
		stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
		stripe_customer_id VARCHAR(255) DEFAULT NULL,

		status ENUM('pending','processing','completed','failed','refunded') NOT NULL DEFAULT 'pending',

		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,
		completed_at DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		UNIQUE KEY stripe_session_id (stripe_session_id),
		KEY proposal_id (proposal_id),
		KEY owner_id (owner_id),
		KEY status (status)
	) $charset_collate;" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 8: clientflow_messages
	// Threaded messaging between agency/freelancer and client. Agency only.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_messages (
		id INT NOT NULL AUTO_INCREMENT,
		project_id INT NOT NULL,
		sender_id INT NOT NULL,
		sender_type ENUM('admin','client') NOT NULL,

		message TEXT NOT NULL,

		read_at DATETIME DEFAULT NULL,
		created_at DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY project_id (project_id),
		KEY sender_id (sender_id),
		KEY created_at (created_at)
	) $charset_collate;" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 9: clientflow_files
	// File uploads per project. Agency only. 1 GB storage limit per account.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_files (
		id INT NOT NULL AUTO_INCREMENT,
		project_id INT NOT NULL,
		uploaded_by INT NOT NULL,

		file_name VARCHAR(255) NOT NULL,
		file_url VARCHAR(500) NOT NULL,
		file_size_kb INT DEFAULT NULL,
		file_mime VARCHAR(50) DEFAULT NULL,

		created_at DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY project_id (project_id),
		KEY uploaded_by (uploaded_by)
	) $charset_collate;" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 10: clientflow_approvals
	// Approval workflows for designs/deliverables. Agency only.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_approvals (
		id INT NOT NULL AUTO_INCREMENT,
		project_id INT NOT NULL,
		type VARCHAR(50) DEFAULT NULL,

		description TEXT DEFAULT NULL,
		status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',

		requested_by INT DEFAULT NULL,
		approved_by INT DEFAULT NULL,
		client_comment TEXT DEFAULT NULL,

		created_at DATETIME DEFAULT NULL,
		responded_at DATETIME DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY project_id (project_id),
		KEY status (status)
	) $charset_collate;" );

	// ────────────────────────────────────────────────────────────────────────
	// Table 11: clientflow_events
	// Analytics event log. All tiers. metadata column is JSON.
	// ────────────────────────────────────────────────────────────────────────
	dbDelta( "CREATE TABLE {$wpdb->prefix}clientflow_events (
		id INT NOT NULL AUTO_INCREMENT,
		proposal_id INT DEFAULT NULL,
		event_type VARCHAR(50) DEFAULT NULL,

		user_ip VARCHAR(45) DEFAULT NULL,
		user_agent TEXT DEFAULT NULL,

		timestamp DATETIME DEFAULT NULL,
		metadata JSON DEFAULT NULL,

		PRIMARY KEY  (id),
		KEY proposal_id (proposal_id),
		KEY event_type (event_type),
		KEY timestamp (timestamp)
	) $charset_collate;" );

	update_option( 'clientflow_db_version', defined( 'CLIENTFLOW_DB_VERSION' ) ? CLIENTFLOW_DB_VERSION : '1' );
}
