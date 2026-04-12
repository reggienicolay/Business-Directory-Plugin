<?php
/**
 * In-Field Grant Access Service
 *
 * Lets a directory manager (e.g. Nicole meeting a business owner in person)
 * grant a known owner or marketing contact edit access to a business listing
 * without making them fill out the public [bd_claim_form] and wait for admin
 * approval. Reuses the same storage and hook surface as a normal approved
 * claim so downstream plugins (BD SEO, newsletter, email signatures) react
 * identically.
 *
 * @package BusinessDirectory
 * @subpackage Admin
 * @since 0.1.8
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\DB\ClaimRequestsTable;

/**
 * Class GrantAccess
 *
 * Single code path shared by all three Phase 2 UIs (edit-screen meta box,
 * businesses list row action, frontend admin bar). Safe to call directly from
 * PHP if a future workflow needs to bypass REST.
 */
class GrantAccess {

	/**
	 * Allowed relationship values (stored on the claim row).
	 */
	const ALLOWED_RELATIONSHIPS = array( 'owner', 'manager', 'staff', 'other' );

	/**
	 * Grant a user access to manage a business.
	 *
	 * @param array $args {
	 *     @type int    $business_id   Required. bd_business post ID.
	 *     @type string $email         Required. Email of the person being granted access.
	 *     @type string $name          Optional. Full name (used only when creating a new user).
	 *     @type string $phone         Optional. Contact phone.
	 *     @type string $relationship  Optional. owner|manager|staff|other (default: owner).
	 *     @type string $note          Optional. Internal admin note stored in admin_notes.
	 *     @type bool   $send_welcome  Optional. Email the user their login. Default true.
	 *     @type int    $granted_by    Optional. Acting admin user ID. Defaults to current user.
	 * }
	 * @return array|\WP_Error Success payload with user_id, claim_id, created_user flag, or WP_Error.
	 */
	public static function grant( array $args ) {
		// --- 1. Validate input ---------------------------------------------------
		$business_id = absint( $args['business_id'] ?? 0 );
		$email       = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
		$name        = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		$phone       = isset( $args['phone'] ) ? sanitize_text_field( $args['phone'] ) : '';
		$relationship = isset( $args['relationship'] ) ? sanitize_text_field( $args['relationship'] ) : 'owner';
		$note        = isset( $args['note'] ) ? sanitize_textarea_field( $args['note'] ) : '';
		$send_welcome = isset( $args['send_welcome'] ) ? (bool) $args['send_welcome'] : true;
		$granted_by  = absint( $args['granted_by'] ?? get_current_user_id() );

		if ( ! $business_id ) {
			return new \WP_Error( 'bd_grant_invalid_business', __( 'Business ID is required.', 'business-directory' ), array( 'status' => 400 ) );
		}

		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return new \WP_Error( 'bd_grant_invalid_business', __( 'Business not found.', 'business-directory' ), array( 'status' => 404 ) );
		}

		if ( ! $email || ! is_email( $email ) ) {
			return new \WP_Error( 'bd_grant_invalid_email', __( 'A valid email address is required.', 'business-directory' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $relationship, self::ALLOWED_RELATIONSHIPS, true ) ) {
			$relationship = 'owner';
		}

		if ( ! $granted_by ) {
			return new \WP_Error( 'bd_grant_no_admin', __( 'Grant must be performed by a logged-in admin.', 'business-directory' ), array( 'status' => 403 ) );
		}

		// --- 2. Early dedupe for existing users -------------------------------------
		// Look up the target by email. If they already exist AND already have an
		// approved claim for this business, return success without touching roles
		// or creating anything — this makes repeated calls idempotent and avoids
		// pointless add_role() side-effects on re-grants.
		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			$already = ClaimRequestsTable::get_approved_for_user( $business_id, (int) $existing_user->ID );
			if ( $already ) {
				return array(
					'success'      => true,
					'already'      => true,
					'business_id'  => $business_id,
					'user_id'      => (int) $existing_user->ID,
					'claim_id'     => (int) $already['id'],
					'created_user' => false,
					'relationship' => $already['relationship'] ?? 'owner',
					'message'      => __( 'User already has approved access to this business.', 'business-directory' ),
				);
			}
		}

		// --- 3. Find or create the target user --------------------------------------
		$created_user = false;
		$password     = '';

		if ( $existing_user ) {
			$user_id = (int) $existing_user->ID;
		} else {
			$username = self::make_unique_username( $email );
			$password = wp_generate_password( 16, true );

			$user_id = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name ?: $email,
					'first_name'   => $name,
				)
			);

			$new_user = new \WP_User( $user_id );
			$new_user->set_role( 'business_owner' );

			$created_user = true;
		}

		// --- 4. Insert the approved claim row (audit trail) -------------------------
		// Do this BEFORE granting roles to existing users so that a DB failure
		// doesn't leave an existing user with an elevated role and no claim row.
		$granter      = get_userdata( $granted_by );
		$granter_name = $granter ? $granter->display_name : sprintf( 'user #%d', $granted_by );
		$admin_notes  = sprintf(
			/* translators: 1: admin display name, 2: optional note */
			__( 'Granted in-field by %1$s%2$s', 'business-directory' ),
			$granter_name,
			$note ? ': ' . $note : ''
		);

		$claim_id = ClaimRequestsTable::insert_granted(
			array(
				'business_id'    => $business_id,
				'user_id'        => $user_id,
				'claimant_name'  => $name ?: ( $existing_user ? $existing_user->display_name : $email ),
				'claimant_email' => $email,
				'claimant_phone' => $phone,
				'relationship'   => $relationship,
				'admin_notes'    => $admin_notes,
				'reviewed_by'    => $granted_by,
			)
		);

		if ( ! $claim_id ) {
			// Rollback: if we just created the user for this grant, delete the
			// orphan account so the next attempt starts clean. wp_delete_user is
			// loaded from wp-admin/includes/user.php on most admin contexts; the
			// require_once is cheap and idempotent.
			if ( $created_user ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user_id );
			}
			return new \WP_Error( 'bd_grant_db_failed', __( 'Failed to record grant in claims table.', 'business-directory' ), array( 'status' => 500 ) );
		}

		// --- 5. Ensure role for existing users (only after claim row is saved) ------
		if ( ! $created_user ) {
			$user = new \WP_User( $user_id );

			// Don't demote admins / directory managers — they already have access.
			$keep_existing = $user->has_cap( 'manage_options' ) || $user->has_cap( 'bd_manage_claims' );
			if ( ! $keep_existing && ! in_array( 'business_owner', (array) $user->roles, true ) ) {
				$user->add_role( 'business_owner' );
			}
		}

		// --- 6. Update business post meta -------------------------------------------
		// bd_claimed_by is "primary owner" for backward compat with immersive
		// template, DuplicateMerger, CSV export, and the public claim form's
		// already-claimed gate. Only set it when granting an OWNER role AND
		// there is no existing primary owner — secondary grants (marketing
		// person) must NOT overwrite the owner's primary flag.
		$existing_primary = get_post_meta( $business_id, 'bd_claimed_by', true );
		if ( 'owner' === $relationship && empty( $existing_primary ) ) {
			update_post_meta( $business_id, 'bd_claimed_by', $user_id );
			update_post_meta( $business_id, 'bd_claim_status', 'claimed' );
			update_post_meta( $business_id, 'bd_claimed_date', current_time( 'mysql' ) );
		}

		// --- 7. Send welcome email (new users only, by default) --------------------
		if ( $send_welcome && $created_user ) {
			self::send_welcome_email( $user_id, $business_id, $email, $password, $granter_name );
		}

		// --- 8. Fire hooks for companion plugins ------------------------------------
		/**
		 * Fires when a claim is approved (including in-field grants).
		 *
		 * @param int $claim_id    Claim row ID.
		 * @param int $business_id Business post ID.
		 * @param int $user_id     Granted user ID.
		 */
		do_action( 'bd_claim_approved', $claim_id, $business_id, $user_id );

		/**
		 * Fires specifically when an in-field grant happens (not a form claim).
		 *
		 * Companion plugins that want to differentiate the two flows should
		 * hook this instead of `bd_claim_approved`.
		 *
		 * @param int    $claim_id     Claim row ID.
		 * @param int    $business_id  Business post ID.
		 * @param int    $user_id      Granted user ID.
		 * @param string $relationship owner|manager|staff|other.
		 * @param int    $granted_by   Admin user ID who performed the grant.
		 */
		do_action( 'bd_access_granted', $claim_id, $business_id, $user_id, $relationship, $granted_by );

		return array(
			'success'      => true,
			'already'      => false,
			'business_id'  => $business_id,
			'user_id'      => $user_id,
			'claim_id'     => (int) $claim_id,
			'created_user' => $created_user,
			'relationship' => $relationship,
			'message'      => $created_user
				? __( 'New user created and granted access.', 'business-directory' )
				: __( 'Existing user granted access.', 'business-directory' ),
		);
	}

	/**
	 * Revoke a previously granted access row.
	 *
	 * Flips the claim row status to `revoked` and, if the revoked user was
	 * the "primary" owner (stored in the `bd_claimed_by` post meta), promotes
	 * another remaining approved owner to primary. If no other owner remains,
	 * clears all three primary-owner meta keys so the public claim form
	 * becomes available again.
	 *
	 * Does NOT remove the `business_owner` WP role — the user may still be
	 * the owner of OTHER businesses in the directory, and we don't want to
	 * break their access to those. The authorization layer
	 * (EditListing::user_can_edit) checks the claims table row state, so
	 * flipping status=revoked is sufficient to cut off this business.
	 *
	 * @param array $args {
	 *     @type int    $claim_id   Required. Row ID from wp_bd_claim_requests.
	 *     @type int    $revoked_by Optional. Acting admin. Defaults to current user.
	 *     @type string $note       Optional. Reason appended to admin_notes.
	 * }
	 * @return array|\WP_Error Success payload (business_id, user_id, claim_id, new_primary_user_id|null) or WP_Error.
	 */
	public static function revoke( array $args ) {
		$claim_id   = absint( $args['claim_id'] ?? 0 );
		$revoked_by = absint( $args['revoked_by'] ?? get_current_user_id() );
		$note       = isset( $args['note'] ) ? sanitize_textarea_field( $args['note'] ) : '';

		if ( ! $claim_id ) {
			return new \WP_Error( 'bd_revoke_missing_id', __( 'Claim ID is required.', 'business-directory' ), array( 'status' => 400 ) );
		}

		if ( ! $revoked_by ) {
			return new \WP_Error( 'bd_revoke_no_admin', __( 'Revoke must be performed by a logged-in admin.', 'business-directory' ), array( 'status' => 403 ) );
		}

		$claim = ClaimRequestsTable::get( $claim_id );
		if ( ! $claim ) {
			return new \WP_Error( 'bd_revoke_not_found', __( 'Claim not found.', 'business-directory' ), array( 'status' => 404 ) );
		}

		if ( 'approved' !== $claim['status'] ) {
			return new \WP_Error( 'bd_revoke_not_approved', __( 'Only approved claims can be revoked.', 'business-directory' ), array( 'status' => 409 ) );
		}

		$business_id = (int) $claim['business_id'];
		$user_id     = (int) $claim['user_id'];

		$ok = ClaimRequestsTable::revoke( $claim_id, $revoked_by, $note );
		if ( ! $ok ) {
			return new \WP_Error( 'bd_revoke_db_failed', __( 'Failed to revoke access in the database.', 'business-directory' ), array( 'status' => 500 ) );
		}

		// --- Primary-owner cleanup ----------------------------------------------
		// If the revoked user was the "primary" owner stored in bd_claimed_by,
		// try to promote another remaining owner. If none, clear all primary
		// flags so the public claim form becomes available again.
		$new_primary       = null;
		$current_primary   = (int) get_post_meta( $business_id, 'bd_claimed_by', true );
		$was_primary_owner = ( $current_primary === $user_id );

		if ( $was_primary_owner ) {
			$remaining = ClaimRequestsTable::get_authorized_users( $business_id );

			// Prefer another row with relationship='owner'; fall back to any remaining row.
			$promoted = null;
			foreach ( $remaining as $row ) {
				if ( 'owner' === ( $row['relationship'] ?? '' ) ) {
					$promoted = $row;
					break;
				}
			}
			if ( ! $promoted && ! empty( $remaining ) ) {
				$promoted = $remaining[0];
			}

			if ( $promoted ) {
				$new_primary = (int) $promoted['user_id'];
				update_post_meta( $business_id, 'bd_claimed_by', $new_primary );
				update_post_meta( $business_id, 'bd_claimed_date', current_time( 'mysql' ) );
				// bd_claim_status stays 'claimed' — business is still owned.
			} else {
				delete_post_meta( $business_id, 'bd_claimed_by' );
				delete_post_meta( $business_id, 'bd_claim_status' );
				delete_post_meta( $business_id, 'bd_claimed_date' );
			}
		}

		/**
		 * Fires when an approved claim is revoked.
		 *
		 * @param int      $claim_id       Revoked claim row ID.
		 * @param int      $business_id    Business post ID.
		 * @param int      $user_id        User whose access was revoked.
		 * @param int      $revoked_by     Admin user ID who performed the revoke.
		 * @param int|null $new_primary_id New primary owner user ID, null if none.
		 */
		do_action( 'bd_access_revoked', $claim_id, $business_id, $user_id, $revoked_by, $new_primary );

		return array(
			'success'          => true,
			'business_id'      => $business_id,
			'user_id'          => $user_id,
			'claim_id'         => $claim_id,
			'was_primary'      => $was_primary_owner,
			'new_primary_id'   => $new_primary,
			'message'          => __( 'Access revoked.', 'business-directory' ),
		);
	}

	/**
	 * Derive a unique WP username from an email address.
	 *
	 * @param string $email Email.
	 * @return string Unique username.
	 */
	private static function make_unique_username( $email ) {
		$base = sanitize_user( $email, true );
		if ( empty( $base ) ) {
			$base = 'user_' . wp_generate_password( 6, false );
		}

		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . '_' . $suffix;
			++$suffix;
			if ( $suffix > 999 ) {
				$username = $base . '_' . wp_generate_password( 6, false );
				break;
			}
		}
		return $username;
	}

	/**
	 * Send the grant welcome email to a newly created user.
	 *
	 * Kept separate from ClaimController::notify_claimant_approved() so the
	 * wording can reflect an in-field grant ("A team member has given you
	 * access") instead of a reviewed form submission ("Your claim has been
	 * approved"). Uses wp_mail() with plain text to match the rest of the
	 * plugin's notification style.
	 *
	 * @param int    $user_id      New user ID.
	 * @param int    $business_id  Business post ID.
	 * @param string $email        Recipient email.
	 * @param string $password     Temporary password.
	 * @param string $granter_name Display name of the admin who granted access.
	 * @return void
	 */
	private static function send_welcome_email( $user_id, $business_id, $email, $password, $granter_name ) {
		$site_name     = get_bloginfo( 'name' );
		$business_name = get_the_title( $business_id );
		$login_url     = wp_login_url();

		$subject = sprintf(
			/* translators: 1: site name, 2: business name */
			__( '[%1$s] You have been granted access to manage %2$s', 'business-directory' ),
			$site_name,
			$business_name
		);

		/* translators: 1: granter name, 2: business name, 3: site name, 4: email, 5: password, 6: login URL, 7: site name */
		$template = __( "Hello,\n\n%1\$s has granted you access to manage %2\$s on %3\$s.\n\nLogin Details:\nEmail: %4\$s\nTemporary password: %5\$s\nLogin URL: %6\$s\n\nNext steps:\n1. Log in using the link above\n2. Change your password from your account settings\n3. Review your business listing, add photos, update hours\n\nWelcome aboard!\n%7\$s Team", 'business-directory' );

		$message = sprintf(
			$template,
			$granter_name,
			$business_name,
			$site_name,
			$email,
			$password,
			$login_url,
			$site_name
		);

		wp_mail( $email, $subject, $message );
	}
}
