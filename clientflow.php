<?php
/**
 * Plugin Name: ClientFlow
 * Plugin URI:  https://wpclientflow.io
 * Description: Professional proposal, payment, and client management for freelancers and agencies.
 * Version:     0.1.0
 * Author:      Codievolt
 * Author URI:  https://codievolt.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clientflow
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package ClientFlow
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

define( 'CLIENTFLOW_VERSION',    '0.1.0' );
define( 'CLIENTFLOW_DB_VERSION', '7' );
define( 'CLIENTFLOW_DIR',        plugin_dir_path( __FILE__ ) );
define( 'CLIENTFLOW_URL',        plugin_dir_url( __FILE__ ) );
define( 'CLIENTFLOW_BASENAME',   plugin_basename( __FILE__ ) );

// AI relay server URL — update this if you move hosting. Never exposed to agencies.
define( 'CLIENTFLOW_AI_RELAY_URL', 'https://clientflow.io' );

// ─────────────────────────────────────────────────────────────────────────────
// Autoloader
// ─────────────────────────────────────────────────────────────────────────────

/**
 * PSR-style autoloader for ClientFlow_* classes.
 *
 * Maps:
 *   ClientFlow_Entitlements → includes/class-entitlements.php
 *   ClientFlow_Db           → includes/class-db.php
 *   ClientFlow_Api          → includes/class-api.php
 *   ClientFlow_Auth         → includes/class-auth.php
 */
spl_autoload_register( static function ( string $class ): void {
	if ( ! str_starts_with( $class, 'ClientFlow_' ) ) {
		return;
	}

	// e.g. ClientFlow_Entitlements → entitlements
	$slug = strtolower( substr( $class, strlen( 'ClientFlow_' ) ) );
	$slug = str_replace( '_', '-', $slug );

	// Handlers class lives in a module directory without a class- prefix file.
	// ClientFlow_Proposal_Handlers → modules/proposals/handlers.php
	if ( str_ends_with( $slug, '-handlers' ) ) {
		$module = str_replace( '-handlers', '', $slug );
		$path   = CLIENTFLOW_DIR . "modules/{$module}/handlers.php";
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}

	$candidates = [
		CLIENTFLOW_DIR . "includes/class-{$slug}.php",
		// Module classes: e.g. ClientFlow_Proposal → modules/proposals/class-proposal.php
		// ClientFlow_Proposal_Template → modules/proposals/class-proposal-template.php
		CLIENTFLOW_DIR . "modules/" . strstr( $slug, '-', true ) . "/class-{$slug}.php",
	];

	foreach ( $candidates as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

// ─────────────────────────────────────────────────────────────────────────────
// Global helper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check whether a user can access a feature.
 *
 * This is the ONE function every module calls. It routes through the
 * single permission engine, ensuring no scattered plan checks.
 *
 * @param int    $user_id WordPress user ID.
 * @param string $feature Feature slug (e.g. 'use_ai', 'create_proposal').
 * @param array  $options Optional context (e.g. ['proposal_id' => 42]).
 *
 * @return bool|string Boolean for most features; string for portal tier.
 */
function cf_can_user( int $user_id, string $feature, array $options = [] ): bool|string {
	return ClientFlow_Entitlements::can_user( $user_id, $feature, $options );
}

/**
 * Build a branded HTML email body consistent with the magic-link email design.
 *
 * @param array $args {
 *   name          string  Client first/full name — used in "Hi {name},". Defaults to "there".
 *   body          string  Main content HTML (dropped inside a <td>; <p>, <strong>, <br> are fine).
 *   cta_label     string  Button label (optional).
 *   cta_url       string  Button href (optional).
 *   footer        string  Small footer note HTML (optional).
 *   business_name string  Defaults to the site name.
 * }
 * @return string Full HTML email document.
 */
function cf_email_html( array $args ): string {
	$business_name = esc_html( $args['business_name'] ?? get_option( 'blogname', 'ClientFlow' ) );
	$name          = esc_html( $args['name'] ?? '' );
	$greeting      = $name ? "Hi {$name}," : 'Hi there,';
	$body          = $args['body'] ?? '';

	$cta_html = '';
	if ( ! empty( $args['cta_label'] ) && ! empty( $args['cta_url'] ) ) {
		$label    = esc_html( $args['cta_label'] );
		$href     = esc_url( $args['cta_url'] );
		$cta_html = "
          <tr>
            <td style=\"padding-bottom:36px;text-align:center;\">
              <a href=\"{$href}\"
                 style=\"display:inline-block;padding:16px 40px;background:#6366F1;
                         color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;
                         border-radius:12px;letter-spacing:0.01em;\">
                {$label}
              </a>
            </td>
          </tr>";
	}

	$footer_html = '';
	if ( ! empty( $args['footer'] ) ) {
		$footer_html = "
          <tr>
            <td style=\"border-top:1px solid #F3F4F6;padding-top:28px;\">
              <p style=\"margin:0;font-size:13px;color:#9CA3AF;line-height:1.6;\">{$args['footer']}</p>
            </td>
          </tr>";
	}

	return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#F8F7F5;font-family:'DM Sans',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F8F7F5;padding:48px 16px;">
    <tr>
      <td align="center">
        <table width="520" cellpadding="0" cellspacing="0"
               style="background:#ffffff;border-radius:20px;padding:48px 44px;
                      box-shadow:0 2px 4px rgba(26,26,46,.04),0 12px 40px rgba(26,26,46,.09);">
          <tr>
            <td style="padding-bottom:32px;border-bottom:1px solid #F3F4F6;">
              <p style="margin:0;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;
                        color:#9CA3AF;font-weight:600;">{$business_name}</p>
            </td>
          </tr>
          <tr>
            <td style="padding-top:36px;padding-bottom:12px;">
              <h1 style="margin:0;font-size:28px;font-weight:700;color:#1A1A2E;
                         font-family:Georgia,serif;letter-spacing:-0.02em;">
                {$greeting}
              </h1>
            </td>
          </tr>
          <tr>
            <td style="padding-bottom:32px;">
              {$body}
            </td>
          </tr>
          {$cta_html}
          {$footer_html}
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Main plugin class — singleton bootstrap.
 */
final class ClientFlow {

	private static ?self $instance = null;

	/**
	 * Retrieve or create the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** @codeCoverageIgnore */
	private function __construct() {
		$this->register_hooks();
	}

	// ── Hooks ────────────────────────────────────────────────────────────────

	/**
	 * Register all WordPress hooks.
	 */
	private function register_hooks(): void {
		add_action( 'init',                    [ $this, 'load_textdomain' ] );
		add_action( 'admin_menu',              [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts',   [ $this, 'enqueue_admin_assets' ] );

		// Redirect to setup wizard on first admin load after fresh activation.
		add_action( 'admin_init', static function (): void {
			if ( get_transient( 'clientflow_redirect_to_setup' ) && current_user_can( 'manage_options' ) ) {
				delete_transient( 'clientflow_redirect_to_setup' );
				wp_safe_redirect( admin_url( 'admin.php?page=clientflow-setup' ) );
				exit;
			}
		} );

		// Run DB migrations automatically when the stored version is behind.
		add_action( 'admin_init', static function (): void {
			if ( (string) get_option( 'clientflow_db_version', '0' ) !== CLIENTFLOW_DB_VERSION ) {
				$schema = CLIENTFLOW_DIR . 'database/schema.php';
				if ( file_exists( $schema ) ) {
					require_once $schema;
					clientflow_create_tables();
				}
			}
		} );

		// Flush rewrite rules once whenever the plugin version changes (new routes deployed).
		add_action( 'admin_init', static function (): void {
			if ( get_option( 'clientflow_rewrite_version' ) !== CLIENTFLOW_VERSION ) {
				flush_rewrite_rules( false );
				update_option( 'clientflow_rewrite_version', CLIENTFLOW_VERSION );
			}
		} );

		// Include REST route files NOW (during plugins_loaded) so that each
		// file's own add_action('rest_api_init', ...) callback is registered
		// before rest_api_init fires. If the files were included inside a
		// rest_api_init callback, their inner add_action calls would be too
		// late — rest_api_init would already have fired and the routes would
		// never be registered.
		$this->load_rest_files();

		// Load client-facing proposal routing (rewrite rules + template_redirect).
		$routing = CLIENTFLOW_DIR . 'modules/proposals/client-routing.php';
		if ( file_exists( $routing ) ) {
			require_once $routing;
		}

		// Load portal routing (rewrite rules + template_redirect).
		$portal_routing = CLIENTFLOW_DIR . 'portal/routing.php';
		if ( file_exists( $portal_routing ) ) {
			require_once $portal_routing;
		}

		// Block WP admin access for clientflow_client role.
		add_action( 'admin_init', static function (): void {
			if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
				if ( ClientFlow_Portal_Auth::is_authenticated() ) {
					wp_safe_redirect( home_url( '/portal/dashboard' ) );
					exit;
				}
			}
		} );

		// Suppress admin bar for portal clients.
		add_filter( 'show_admin_bar', static function ( bool $show ): bool {
			if ( ClientFlow_Portal_Auth::is_authenticated() ) {
				return false;
			}
			return $show;
		} );

		// After WP login, send clients to the portal dashboard instead of WP admin.
		add_filter( 'login_redirect', static function ( string $redirect_to, string $_requested_redirect_to, $user ): string {
			if ( $user instanceof WP_User && in_array( 'clientflow_client', (array) $user->roles, true ) ) {
				return home_url( '/portal/dashboard' );
			}
			return $redirect_to;
		}, 10, 3 );

		// Hook: when a proposal is sent, provision/update the client's portal account.
		add_action( 'cf_proposal_sent', static function ( int $proposal_id, int $_owner_id ): void {
			global $wpdb;
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT c.email AS client_email, c.name AS client_name
					 FROM {$wpdb->prefix}clientflow_proposals p
					 JOIN {$wpdb->prefix}clientflow_clients c ON c.id = p.client_id
					 WHERE p.id = %d",
					$proposal_id
				),
				ARRAY_A
			);
			if ( $row && ! empty( $row['client_email'] ) ) {
				ClientFlow_Portal_Auth::get_or_create_wp_user(
					$row['client_email'],
					$row['client_name'] ?? null
				);
			}
		}, 10, 2 );

		// Hook: auto-create project when a proposal is accepted (Agency tier only).
		add_action( 'cf_proposal_accepted', static function ( int $proposal_id, int $owner_id ): void {
			if ( ! cf_can_user( $owner_id, 'use_projects' ) ) {
				return;
			}
			$base = CLIENTFLOW_DIR . 'modules/projects/';
			foreach ( [
				'class-project.php'   => 'ClientFlow_Project',
				'class-milestone.php' => 'ClientFlow_Milestone',
				'handlers.php'        => 'ClientFlow_Project_Handlers',
			] as $file => $class ) {
				if ( ! class_exists( $class ) && file_exists( $base . $file ) ) {
					require_once $base . $file;
				}
			}
			ClientFlow_Project_Handlers::create_from_accepted_proposal( $proposal_id, $owner_id );
		}, 10, 2 );

		// Hook: send portal invitation email when a proposal is accepted.
		// On Free plan: create the WP account silently but skip the email —
		// the owner can manually invite from the Clients page once they upgrade.
		add_action( 'cf_proposal_accepted', static function ( int $proposal_id, int $owner_id ): void {
			global $wpdb;
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT c.id AS client_id, c.email AS client_email, c.name AS client_name
					 FROM {$wpdb->prefix}clientflow_proposals p
					 JOIN {$wpdb->prefix}clientflow_clients c ON c.id = p.client_id
					 WHERE p.id = %d",
					$proposal_id
				),
				ARRAY_A
			);
			if ( ! $row || empty( $row['client_email'] ) ) {
				return;
			}
			// Always create the WP account so it is ready if the owner upgrades.
			$user = ClientFlow_Portal_Auth::get_or_create_wp_user(
				$row['client_email'],
				$row['client_name'] ?? null
			);
			if ( is_wp_error( $user ) ) {
				return;
			}
			// Only send the invite email when the owner has portal access.
			if ( ! cf_can_user( $owner_id, 'use_portal' ) ) {
				return;
			}
			$raw_token = ClientFlow_Portal_Auth::generate_magic_token( $user->ID );
			ClientFlow_Portal_Auth::send_magic_link_email( $user, $raw_token );
			$wpdb->update(
				$wpdb->prefix . 'clientflow_clients',
				[ 'portal_invited_at' => current_time( 'mysql' ) ],
				[ 'id' => (int) $row['client_id'] ]
			);
		}, 20, 2 );

		// Monthly usage reset — fires at start of each month.
		add_action( 'clientflow_monthly_reset', [ ClientFlow_Entitlements::class, 'reset_monthly_usage' ] );

		add_action( 'init', static function (): void {
			if ( ! wp_next_scheduled( 'clientflow_monthly_reset' ) ) {
				wp_schedule_event(
					(int) strtotime( 'first day of next month midnight' ),
					'monthly',
					'clientflow_monthly_reset'
				);
			}
		} );
	}

	// ── Textdomain ───────────────────────────────────────────────────────────

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'clientflow',
			false,
			CLIENTFLOW_DIR . 'languages'
		);
	}

	// ── REST API ─────────────────────────────────────────────────────────────

	/**
	 * Include REST route files during plugins_loaded.
	 *
	 * Each file registers its own add_action('rest_api_init', ...) callback.
	 * By including files here (not inside a rest_api_init callback) those
	 * inner callbacks are queued in time to fire when rest_api_init runs.
	 */
	public function load_rest_files(): void {
		$route_files = [
			CLIENTFLOW_DIR . 'rest-api/entitlements.php',
			CLIENTFLOW_DIR . 'rest-api/proposals.php',
			CLIENTFLOW_DIR . 'rest-api/client-proposals.php',
			CLIENTFLOW_DIR . 'rest-api/payments.php',
			CLIENTFLOW_DIR . 'rest-api/portal.php',
			CLIENTFLOW_DIR . 'rest-api/projects.php',
			CLIENTFLOW_DIR . 'rest-api/files.php',
			CLIENTFLOW_DIR . 'rest-api/approvals.php',
			CLIENTFLOW_DIR . 'rest-api/messages.php',
			CLIENTFLOW_DIR . 'rest-api/clients.php',
			CLIENTFLOW_DIR . 'rest-api/ai.php',
			CLIENTFLOW_DIR . 'rest-api/analytics.php',
			CLIENTFLOW_DIR . 'rest-api/onboarding.php',
		];

		foreach ( $route_files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	// ── Admin ─────────────────────────────────────────────────────────────────

	/**
	 * Register the top-level admin menu and sub-pages.
	 */
	public function register_admin_menu(): void {
		// SVG icon (indigo checkmark circle).
		$svg_icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
			. 'stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
		);

		add_menu_page(
			__( 'ClientFlow', 'clientflow' ),
			__( 'ClientFlow', 'clientflow' ),
			'manage_options',
			'clientflow',
			[ $this, 'render_plan_overview' ],
			$svg_icon,
			30
		);

		add_submenu_page(
			'clientflow',
			__( 'Plan & Usage', 'clientflow' ),
			__( 'Plan & Usage', 'clientflow' ),
			'manage_options',
			'clientflow',
			[ $this, 'render_plan_overview' ]
		);

		add_submenu_page(
			'clientflow',
			__( 'Proposals', 'clientflow' ),
			__( 'Proposals', 'clientflow' ),
			'manage_options',
			'clientflow-proposals',
			[ $this, 'render_proposals' ]
		);

		add_submenu_page(
			'clientflow',
			__( 'Clients', 'clientflow' ),
			__( 'Clients', 'clientflow' ),
			'manage_options',
			'clientflow-clients',
			[ $this, 'render_clients' ]
		);

		// Build Projects menu title with unread message badge if applicable.
		$projects_menu_title = __( 'Projects', 'clientflow' );
		$msg_class_file      = CLIENTFLOW_DIR . 'modules/messaging/class-message.php';
		if ( file_exists( $msg_class_file ) ) {
			if ( ! class_exists( 'ClientFlow_Message' ) ) {
				require_once $msg_class_file;
			}
			$unread_msgs = ClientFlow_Message::unread_count_admin( get_current_user_id() );
			if ( $unread_msgs > 0 ) {
				$projects_menu_title .= sprintf(
					' <span class="awaiting-mod count-%1$d"><span class="count">%1$d</span></span>',
					$unread_msgs
				);
			}
		}

		add_submenu_page(
			'clientflow',
			__( 'Projects', 'clientflow' ),
			$projects_menu_title,
			'manage_options',
			'clientflow-projects',
			[ $this, 'render_projects' ]
		);

		add_submenu_page(
			'clientflow',
			__( 'Analytics', 'clientflow' ),
			__( 'Analytics', 'clientflow' ),
			'manage_options',
			'clientflow-analytics',
			[ $this, 'render_analytics' ]
		);

		add_submenu_page(
			'clientflow',
			__( 'Settings', 'clientflow' ),
			__( 'Settings', 'clientflow' ),
			'manage_options',
			'clientflow-settings',
			[ $this, 'render_settings' ]
		);

		// Setup wizard — hidden from sidebar (null parent), accessible via redirect on activation.
		add_submenu_page(
			null,
			__( 'Setup', 'clientflow' ),
			__( 'Setup', 'clientflow' ),
			'manage_options',
			'clientflow-setup',
			[ $this, 'render_setup' ]
		);
	}

	/**
	 * Render the Plan & Usage admin page.
	 *
	 * Prepares variables and includes the view template.
	 */
	public function render_plan_overview(): void {
		$user_id   = get_current_user_id();
		$user_plan = ClientFlow_Entitlements::get_user_plan( $user_id );

		$usage_data = [
			'ai_requests'      => ClientFlow_Entitlements::get_monthly_usage( $user_id, 'use_ai' ),
			'ai_limit'         => ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' ),
			'proposals'        => ClientFlow_Entitlements::get_total_count( $user_id, 'create_proposal' ),
			'proposals_limit'  => ClientFlow_Entitlements::get_feature_limit( $user_id, 'create_proposal' ),
			'storage_mb'       => ClientFlow_Entitlements::get_storage_used( $user_id ),
			'storage_limit_mb' => 'agency' === $user_plan ? 1000 : 0,
			'team_seats'       => ClientFlow_Entitlements::get_team_seats_used( $user_id ),
			'team_limit'       => ClientFlow_Entitlements::get_team_limit( $user_id ),
		];

		$feature_access = [
			'create_proposal' => cf_can_user( $user_id, 'create_proposal' ),
			'use_payments'    => cf_can_user( $user_id, 'use_payments' ),
			'use_portal'      => cf_can_user( $user_id, 'use_portal' ),
			'use_projects'    => cf_can_user( $user_id, 'use_projects' ),
			'use_messaging'   => cf_can_user( $user_id, 'use_messaging' ),
			'use_files'       => cf_can_user( $user_id, 'use_files' ),
			'use_ai'          => cf_can_user( $user_id, 'use_ai' ),
			'team_access'     => cf_can_user( $user_id, 'team_access' ),
		];

		require CLIENTFLOW_DIR . 'admin/views/plan-overview.php';
	}

	/**
	 * Render the Proposals admin page.
	 *
	 * Outputs the React app mount point. All UI is handled by React.
	 */
	public function render_proposals(): void {
		require CLIENTFLOW_DIR . 'admin/views/proposals.php';
	}

	public function render_clients(): void {
		require CLIENTFLOW_DIR . 'admin/views/clients.php';
	}

	/**
	 * Render the Projects admin page.
	 */
	public function render_projects(): void {
		require CLIENTFLOW_DIR . 'admin/views/projects.php';
	}

	/**
	 * Render the Analytics admin page.
	 */
	public function render_analytics(): void {
		require CLIENTFLOW_DIR . 'admin/views/analytics.php';
	}

	/**
	 * Render the Settings admin page.
	 */
	public function render_settings(): void {
		require CLIENTFLOW_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the Setup wizard page.
	 */
	public function render_setup(): void {
		require CLIENTFLOW_DIR . 'admin/views/setup.php';
	}

	/**
	 * Enqueue admin scripts and styles on ClientFlow pages.
	 *
	 * Loads the compiled React app (build/index.js + build/index.css) only on
	 * the Proposals admin page. Provides window.cfData for React ↔ PHP comms.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'clientflow' ) ) {
			return;
		}

		$build_dir = CLIENTFLOW_DIR . 'build/';
		$build_url = CLIENTFLOW_URL . 'build/';
		$user_id   = get_current_user_id();
		$plan      = ClientFlow_Entitlements::get_user_plan( $user_id );

		$runtime_data = [
			'apiUrl'             => rest_url( 'clientflow/v1/' ),
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'userPlan'           => $plan,
			'planLimits'         => [
				'proposals' => ClientFlow_Entitlements::get_feature_limit( $user_id, 'create_proposal' ),
				'ai'        => ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' ),
			],
			'onboardingComplete' => (bool) get_option( 'clientflow_onboarding_complete' ),
		];

		if ( str_contains( $hook, 'clientflow-proposals' ) ) {
			$asset_file = $build_dir . 'index.asset.php';
			$asset      = file_exists( $asset_file )
				? require $asset_file
				: [ 'dependencies' => [ 'wp-element', 'wp-i18n' ], 'version' => CLIENTFLOW_VERSION ];

			if ( file_exists( $build_dir . 'index.css' ) ) {
				wp_enqueue_style( 'cf-admin', $build_url . 'index.css', [], $asset['version'] );
			}

			wp_enqueue_script( 'cf-admin', $build_url . 'index.js', $asset['dependencies'], $asset['version'], true );
			wp_localize_script( 'cf-admin', 'cfData', $runtime_data );

		} elseif ( str_contains( $hook, 'clientflow-projects' ) ) {
			$asset_file = $build_dir . 'projects.asset.php';
			$asset      = file_exists( $asset_file )
				? require $asset_file
				: [ 'dependencies' => [ 'wp-element', 'wp-i18n' ], 'version' => CLIENTFLOW_VERSION ];

			if ( file_exists( $build_dir . 'projects.css' ) ) {
				wp_enqueue_style( 'cf-projects', $build_url . 'projects.css', [], $asset['version'] );
			}

			wp_enqueue_script( 'cf-projects', $build_url . 'projects.js', $asset['dependencies'], $asset['version'], true );
			wp_localize_script( 'cf-projects', 'cfData', $runtime_data );

		} elseif ( str_contains( $hook, 'clientflow-analytics' ) ) {
			$asset_file = $build_dir . 'analytics.asset.php';
			$asset      = file_exists( $asset_file )
				? require $asset_file
				: [ 'dependencies' => [ 'wp-element', 'wp-i18n' ], 'version' => CLIENTFLOW_VERSION ];

			if ( file_exists( $build_dir . 'analytics.css' ) ) {
				wp_enqueue_style( 'cf-analytics', $build_url . 'analytics.css', [], $asset['version'] );
			}

			wp_enqueue_script( 'cf-analytics', $build_url . 'analytics.js', $asset['dependencies'], $asset['version'], true );
			wp_localize_script( 'cf-analytics', 'cfData', $runtime_data );

		} elseif ( str_contains( $hook, 'clientflow-clients' ) ) {
			$asset_file = $build_dir . 'clients.asset.php';
			$asset      = file_exists( $asset_file )
				? require $asset_file
				: [ 'dependencies' => [ 'wp-element', 'wp-i18n' ], 'version' => CLIENTFLOW_VERSION ];

			if ( file_exists( $build_dir . 'clients.css' ) ) {
				wp_enqueue_style( 'cf-clients', $build_url . 'clients.css', [], $asset['version'] );
			}

			wp_enqueue_script( 'cf-clients', $build_url . 'clients.js', $asset['dependencies'], $asset['version'], true );
			wp_localize_script( 'cf-clients', 'cfData', $runtime_data );

		} elseif ( str_contains( $hook, 'clientflow-setup' ) ) {
			$asset_file = $build_dir . 'setup.asset.php';
			$asset      = file_exists( $asset_file )
				? require $asset_file
				: [ 'dependencies' => [ 'wp-element', 'wp-i18n' ], 'version' => CLIENTFLOW_VERSION ];

			if ( file_exists( $build_dir . 'setup.css' ) ) {
				wp_enqueue_style( 'cf-setup', $build_url . 'setup.css', [], $asset['version'] );
			}

			wp_enqueue_script( 'cf-setup', $build_url . 'setup.js', $asset['dependencies'], $asset['version'], true );
			wp_localize_script( 'cf-setup', 'cfData', $runtime_data );
		}
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Activation / Deactivation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Plugin activation callback.
 *
 * Creates all database tables, stores version options, and schedules cron.
 */
function clientflow_activate(): void {
	require_once CLIENTFLOW_DIR . 'database/schema.php';
	clientflow_create_tables();

	add_option( 'clientflow_version',    CLIENTFLOW_VERSION );
	add_option( 'clientflow_db_version', CLIENTFLOW_DB_VERSION );

	if ( ! wp_next_scheduled( 'clientflow_monthly_reset' ) ) {
		wp_schedule_event(
			(int) strtotime( 'first day of next month midnight' ),
			'monthly',
			'clientflow_monthly_reset'
		);
	}

	// Register the clientflow_client role for portal users.
	add_role(
		'clientflow_client',
		__( 'ClientFlow Client', 'clientflow' ),
		[ 'read' => true ]
	);

	// Register proposal rewrite rules before flushing.
	add_rewrite_tag( '%cf_proposal_token%', '([a-zA-Z0-9\-]+)' );
	add_rewrite_rule(
		'^proposals/([a-zA-Z0-9\-]+)/?$',
		'index.php?cf_proposal_token=$matches[1]',
		'top'
	);

	// Register portal rewrite rules before flushing.
	add_rewrite_tag( '%cf_portal_page%', '([a-z]+)' );
	foreach ( [ 'login', 'verify', 'dashboard', 'proposals', 'payments', 'projects' ] as $portal_page ) {
		add_rewrite_rule(
			"^portal/{$portal_page}/?$",
			"index.php?cf_portal_page={$portal_page}",
			'top'
		);
	}
	add_rewrite_rule( '^portal/?$', 'index.php?cf_portal_page=login', 'top' );

	flush_rewrite_rules();

	// Queue redirect to setup wizard on first admin load after activation.
	if ( ! get_option( 'clientflow_onboarding_complete' ) ) {
		set_transient( 'clientflow_redirect_to_setup', true, 30 );
	}
}

/**
 * Plugin deactivation callback.
 *
 * Clears scheduled cron jobs. Does NOT drop database tables.
 */
function clientflow_deactivate(): void {
	wp_clear_scheduled_hook( 'clientflow_monthly_reset' );
	flush_rewrite_rules();
}

register_activation_hook( __FILE__,   'clientflow_activate' );
register_deactivation_hook( __FILE__, 'clientflow_deactivate' );

// ─────────────────────────────────────────────────────────────────────────────
// Initialise
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', static function (): void {
	ClientFlow::instance();
} );
