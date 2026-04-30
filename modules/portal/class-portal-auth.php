<?php
/**
 * Portal authentication: magic link generation and verification.
 *
 * Flow:
 *   1. Client submits email → send_magic_link_email()
 *   2. Raw 32-byte token in email URL → stored as SHA-256 hash in user meta
 *   3. Client clicks link → verify_magic_token() hashes incoming token,
 *      finds matching user meta, checks expiry, deletes meta (one-time use),
 *      then calls wp_set_auth_cookie()
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) exit;

class ClientFlow_Portal_Auth {

	/** User meta key storing the hashed token. */
	const META_TOKEN  = '_cf_portal_token_hash';

	/** User meta key storing the expiry timestamp. */
	const META_EXPIRY = '_cf_portal_token_expiry';

	/** User meta key linking WP user → client row. */
	const META_CLIENT = '_cf_client_id';

	/** Token lifetime in seconds (24 hours). */
	const TOKEN_TTL = 86400;

	// -------------------------------------------------------------------------
	// User management
	// -------------------------------------------------------------------------

	/**
	 * Find an existing WP user by email that has the clientflow_client role.
	 *
	 * @param  string $email
	 * @return WP_User|null
	 */
	public static function find_client_by_email( string $email ): ?WP_User {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return null;
		}

		if ( ! in_array( 'clientflow_client', (array) $user->roles, true ) ) {
			return null;
		}

		return $user;
	}

	/**
	 * Get or create a WP user for the given email address.
	 *
	 * Created users receive the `clientflow_client` role and a generated
	 * `_cf_client_id` meta value so the portal can filter data to their records.
	 *
	 * @param  string      $email
	 * @param  string|null $name   Display name hint (e.g. from proposal data).
	 * @return WP_User|WP_Error
	 */
	public static function get_or_create_wp_user( string $email, ?string $name = null ) {
		global $wpdb;

		$existing = get_user_by( 'email', $email );

		if ( $existing ) {
			// Ensure the role is set even for pre-existing users.
			if ( ! in_array( 'clientflow_client', (array) $existing->roles, true ) ) {
				$existing->add_role( 'clientflow_client' );
			}

			// Back-fill client ID if missing.
			if ( ! get_user_meta( $existing->ID, self::META_CLIENT, true ) ) {
				update_user_meta( $existing->ID, self::META_CLIENT, wp_generate_uuid4() );
			}

			// Link WP user back to clientflow_clients row if not already set.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}clientflow_clients SET wp_user_id = %d
					 WHERE email = %s AND ( wp_user_id IS NULL OR wp_user_id = 0 )",
					$existing->ID,
					$email
				)
			);

			return $existing;
		}

		// Create a new minimal WP user.
		$username = self::unique_username_from_email( $email );

		$user_id = wp_insert_user( [
			'user_login'   => $username,
			'user_email'   => $email,
			'display_name' => $name ?: $username,
			'role'         => 'clientflow_client',
			// Random password — client never uses a password, only magic links.
			'user_pass'    => wp_generate_password( 48, true, true ),
		] );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Assign a stable client ID.
		update_user_meta( $user_id, self::META_CLIENT, wp_generate_uuid4() );

		// Link the new WP user to their clientflow_clients row.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}clientflow_clients SET wp_user_id = %d
				 WHERE email = %s AND ( wp_user_id IS NULL OR wp_user_id = 0 )",
				$user_id,
				$email
			)
		);

		return get_user_by( 'ID', $user_id );
	}

	// -------------------------------------------------------------------------
	// Magic token
	// -------------------------------------------------------------------------

	/**
	 * Generate a magic login token for the given WP user.
	 *
	 * Stores `hash('sha256', $raw_token)` + expiry in user meta and returns
	 * the raw token (to be embedded in the email URL).
	 *
	 * @param  int $user_id
	 * @return string Raw token.
	 */
	public static function generate_magic_token( int $user_id ): string {
		$raw_token = wp_generate_password( 32, true, false );
		$hash      = hash( 'sha256', $raw_token );
		$expiry    = time() + self::TOKEN_TTL;

		update_user_meta( $user_id, self::META_TOKEN,  $hash );
		update_user_meta( $user_id, self::META_EXPIRY, $expiry );

		return $raw_token;
	}

	/**
	 * Verify a magic token received from the URL.
	 *
	 * On success: deletes the token meta (one-time use), sets the WP auth
	 * cookie, and returns the WP_User.
	 *
	 * On failure: returns a WP_Error.
	 *
	 * @param  string $raw_token  Token from query string.
	 * @return WP_User|WP_Error
	 */
	public static function verify_magic_token( string $raw_token ) {
		if ( strlen( $raw_token ) < 10 ) {
			return new WP_Error( 'invalid_token', 'Invalid token.' );
		}

		$hash = hash( 'sha256', $raw_token );

		// Find the user whose stored hash matches.
		$users = get_users( [
			'meta_key'   => self::META_TOKEN,
			'meta_value' => $hash,
			'number'     => 1,
			'fields'     => 'all',
		] );

		if ( empty( $users ) ) {
			return new WP_Error( 'invalid_token', 'This login link is invalid.' );
		}

		$user = $users[0];

		// Check expiry.
		$expiry = (int) get_user_meta( $user->ID, self::META_EXPIRY, true );

		if ( time() > $expiry ) {
			// Clean up expired token.
			delete_user_meta( $user->ID, self::META_TOKEN );
			delete_user_meta( $user->ID, self::META_EXPIRY );

			return new WP_Error( 'expired_token', 'This login link has expired. Please request a new one.' );
		}

		// One-time use: delete before setting cookie.
		delete_user_meta( $user->ID, self::META_TOKEN );
		delete_user_meta( $user->ID, self::META_EXPIRY );

		// Authenticate the user.
		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );

		// Ensure clientflow_clients.wp_user_id is linked to this WP user.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}clientflow_clients SET wp_user_id = %d
				 WHERE email = %s AND ( wp_user_id IS NULL OR wp_user_id = 0 )",
				$user->ID,
				$user->user_email
			)
		);

		return $user;
	}

	// -------------------------------------------------------------------------
	// Auth checks
	// -------------------------------------------------------------------------

	/**
	 * Whether the current WP user is an authenticated portal client.
	 *
	 * @return bool
	 */
	public static function is_authenticated(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		return in_array( 'clientflow_client', (array) $user->roles, true );
	}

	/**
	 * Return the `_cf_client_id` of the currently-logged-in client.
	 *
	 * @return string|null
	 */
	public static function get_current_client_id(): ?string {
		if ( ! self::is_authenticated() ) {
			return null;
		}

		$id = get_user_meta( get_current_user_id(), self::META_CLIENT, true );

		return $id ?: null;
	}

	/**
	 * REST permission callback for portal-authenticated endpoints.
	 *
	 * @return bool|WP_Error
	 */
	public static function rest_permission() {
		if ( ! self::is_authenticated() ) {
			return new WP_Error(
				'cf_portal_unauthorized',
				'Authentication required.',
				[ 'status' => 401 ]
			);
		}

		// Backfill wp_user_id on clientflow_clients if not yet set.
		// Handles users who logged in before this link was stored.
		global $wpdb;
		$user = wp_get_current_user();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}clientflow_clients SET wp_user_id = %d
				 WHERE email = %s AND ( wp_user_id IS NULL OR wp_user_id = 0 )",
				$user->ID,
				$user->user_email
			)
		);

		return true;
	}

	// -------------------------------------------------------------------------
	// Email
	// -------------------------------------------------------------------------

	/**
	 * Send a magic login link email to the given WP user.
	 *
	 * @param  WP_User $user
	 * @param  string  $raw_token
	 * @return bool
	 */
	public static function send_magic_link_email( WP_User $user, string $raw_token ): bool {
		$business_name = get_option( 'blogname', 'ClientFlow' );
		$portal_url    = home_url( '/portal/verify?token=' . rawurlencode( $raw_token ) );
		$subject       = sprintf( 'Your login link for %s', $business_name );

		$message = self::build_email_html( $user, $portal_url, $business_name );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		return wp_mail( $user->user_email, $subject, $message, $headers );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Generate a unique WP username derived from an email address.
	 *
	 * @param  string $email
	 * @return string
	 */
	private static function unique_username_from_email( string $email ): string {
		$base     = sanitize_user( strstr( $email, '@', true ), true );
		$base     = $base ?: 'client';
		$username = $base;
		$counter  = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			$counter++;
		}

		return $username;
	}

	/**
	 * Build the HTML body for the magic link email.
	 *
	 * @param  WP_User $user
	 * @param  string  $portal_url
	 * @param  string  $business_name
	 * @return string
	 */
	private static function build_email_html(
		WP_User $user,
		string  $portal_url,
		string  $business_name
	): string {
		$name          = esc_html( $user->display_name );
		$escaped_url   = esc_url( $portal_url );
		$escaped_biz   = esc_html( $business_name );
		$expiry_hours  = (int) ( self::TOKEN_TTL / 3600 );

		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your login link</title>
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
                        color:#9CA3AF;font-weight:600;">{$escaped_biz}</p>
            </td>
          </tr>
          <tr>
            <td style="padding-top:36px;padding-bottom:12px;">
              <h1 style="margin:0;font-size:28px;font-weight:700;color:#1A1A2E;
                         font-family:Georgia,serif;letter-spacing:-0.02em;">
                Hi {$name},
              </h1>
            </td>
          </tr>
          <tr>
            <td style="padding-bottom:32px;">
              <p style="margin:0;font-size:16px;color:#6B7280;line-height:1.65;">
                Click the button below to log in to your client portal.
                No password needed.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding-bottom:36px;text-align:center;">
              <a href="{$escaped_url}"
                 style="display:inline-block;padding:16px 40px;background:#6366F1;
                        color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;
                        border-radius:12px;letter-spacing:0.01em;">
                Log In to Your Portal
              </a>
            </td>
          </tr>
          <tr>
            <td style="border-top:1px solid #F3F4F6;padding-top:28px;">
              <p style="margin:0;font-size:13px;color:#9CA3AF;line-height:1.6;">
                This link expires in {$expiry_hours} hours and can only be used once.<br>
                If you did not request this, you can safely ignore this email.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding-top:16px;">
              <p style="margin:0;font-size:12px;color:#C0C0C8;">
                Having trouble with the button?
                Copy and paste this URL into your browser:<br>
                <a href="{$escaped_url}" style="color:#6366F1;word-break:break-all;">{$escaped_url}</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
	}
}
