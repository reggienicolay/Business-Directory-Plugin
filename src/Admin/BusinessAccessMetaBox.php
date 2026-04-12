<?php
/**
 * Business Access Meta Box + Row Action
 *
 * Renders a "Business Access" side meta box on the bd_business edit screen
 * listing everyone with approved access to the business, plus a "Grant
 * Access" button that opens the shared modal. Also adds a "Grant Access" row
 * action to the businesses list table so admins can launch the modal from
 * either place.
 *
 * Asset enqueue for the shared grant-access modal is centralised here —
 * BusinessAccessMetaBox::enqueue_modal_assets() is called from this class
 * on admin screens and from GrantAccessToolbar on the frontend, so the modal
 * JS/CSS is loaded exactly once per request with consistent localisation.
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
 * Class BusinessAccessMetaBox
 */
class BusinessAccessMetaBox {

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the "Business Access" meta box on the bd_business edit screen.
	 *
	 * Side position so it sits below Publish/Categories and doesn't push the
	 * big content meta boxes around.
	 */
	public function register_meta_box() {
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		add_meta_box(
			'bd_business_access',
			__( 'Business Access', 'business-directory' ),
			array( $this, 'render_meta_box' ),
			'bd_business',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box contents.
	 *
	 * Lists current authorized users with a revoke link for each, plus a
	 * "Grant Access" button that triggers the shared modal (handled in
	 * grant-access-modal.js). The list is rendered server-side from the
	 * claims table so the meta box works even if JavaScript fails to load.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		$users      = ClaimRequestsTable::get_authorized_users( $post->ID );
		$primary_id = (int) get_post_meta( $post->ID, 'bd_claimed_by', true );

		echo '<div class="bd-business-access-metabox" data-business-id="' . esc_attr( (string) $post->ID ) . '">';

		if ( empty( $users ) ) {
			echo '<p class="bd-business-access-empty">' . esc_html__( 'No users have been granted access to this business yet.', 'business-directory' ) . '</p>';
		} else {
			echo '<ul class="bd-business-access-list">';
			foreach ( $users as $row ) {
				self::render_user_row( $row, $primary_id );
			}
			echo '</ul>';
		}

		echo '<p class="bd-business-access-actions">';
		printf(
			'<button type="button" class="button button-primary bd-grant-access-trigger" data-business-id="%d">%s</button>',
			(int) $post->ID,
			esc_html__( '+ Grant Access', 'business-directory' )
		);
		echo '</p>';

		echo '<p class="description">' . esc_html__( 'Grant a business owner or marketing contact direct access to edit this listing, bypassing the claim form.', 'business-directory' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render a single row in the authorized users list.
	 *
	 * Kept as a helper so the toolbar / row-action / modal can reuse it later
	 * when re-rendering the list after grant/revoke without round-tripping.
	 *
	 * @param array $row        Row from ClaimRequestsTable::get_authorized_users().
	 * @param int   $primary_id User ID stored in bd_claimed_by meta.
	 * @return void
	 */
	private static function render_user_row( $row, $primary_id ) {
		$user_id       = (int) ( $row['user_id'] ?? 0 );
		$display_name  = $row['display_name'] ?: $row['claimant_name'] ?: $row['claimant_email'];
		$email         = $row['user_email'] ?: $row['claimant_email'];
		$relationship  = $row['relationship'] ?: 'owner';
		$is_primary    = ( $user_id === $primary_id );
		$user_missing  = empty( $row['user_login'] );

		echo '<li class="bd-business-access-item" data-claim-id="' . esc_attr( (string) (int) $row['id'] ) . '" data-user-id="' . esc_attr( (string) $user_id ) . '">';
		echo '<div class="bd-business-access-item__main">';
		echo '<strong class="bd-business-access-item__name">' . esc_html( $display_name ) . '</strong>';
		if ( $is_primary ) {
			echo ' <span class="bd-business-access-badge bd-business-access-badge--primary" title="' . esc_attr__( 'Primary owner', 'business-directory' ) . '">' . esc_html__( 'Primary', 'business-directory' ) . '</span>';
		}
		echo ' <span class="bd-business-access-badge bd-business-access-badge--' . esc_attr( $relationship ) . '">' . esc_html( ucfirst( $relationship ) ) . '</span>';
		echo '<br><span class="bd-business-access-item__email">' . esc_html( $email ) . '</span>';
		if ( $user_missing ) {
			echo '<br><span class="bd-business-access-item__warning">' . esc_html__( '⚠ User account no longer exists', 'business-directory' ) . '</span>';
		}
		echo '</div>';
		echo '<div class="bd-business-access-item__actions">';
		printf(
			'<button type="button" class="button-link bd-business-access-revoke" data-claim-id="%d" aria-label="%s">%s</button>',
			(int) $row['id'],
			esc_attr__( 'Revoke access', 'business-directory' ),
			esc_html__( 'Revoke', 'business-directory' )
		);
		echo '</div>';
		echo '</li>';
	}

	/**
	 * Add "Grant Access" to the row actions on the bd_business list table.
	 *
	 * The href is `#` — the shared modal script attaches a click handler to
	 * any `.bd-grant-access-trigger` element and reads `data-business-id`.
	 *
	 * @param array    $actions Existing row actions.
	 * @param \WP_Post $post    Current post.
	 * @return array
	 */
	public function add_row_action( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || 'bd_business' !== $post->post_type ) {
			return $actions;
		}
		if ( ! self::current_user_can_manage() ) {
			return $actions;
		}

		$label = esc_html__( 'Grant Access', 'business-directory' );

		$actions['bd_grant_access'] = sprintf(
			'<a href="#" class="bd-grant-access-trigger" data-business-id="%d">%s</a>',
			(int) $post->ID,
			$label
		);

		return $actions;
	}

	/**
	 * Enqueue modal assets on bd_business admin screens.
	 *
	 * Runs on the edit screen (post.php / post-new.php) AND on the list
	 * table (edit.php) so the row action can trigger the modal.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'bd_business' !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}
		if ( ! self::current_user_can_manage() ) {
			return;
		}

		self::enqueue_modal_assets();
	}

	/**
	 * Enqueue the shared grant-access modal JS + CSS.
	 *
	 * Public static so GrantAccessToolbar can call it from the frontend and
	 * we guarantee a single, consistent asset bundle.
	 *
	 * @return void
	 */
	public static function enqueue_modal_assets() {
		$handle = 'bd-grant-access-modal';

		// Idempotent — wp_enqueue_style/script are no-ops if already enqueued.
		wp_enqueue_style(
			$handle,
			BD_PLUGIN_URL . 'assets/css/grant-access-modal.css',
			array(),
			BD_VERSION
		);

		wp_enqueue_script(
			$handle,
			BD_PLUGIN_URL . 'assets/js/grant-access-modal.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			BD_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'bdGrantAccess',
			array(
				'restRoot' => esc_url_raw( rest_url( 'bd/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'title'          => __( 'Grant Business Access', 'business-directory' ),
					'subtitle'       => __( 'Give a business owner or marketing contact direct access without the public claim form.', 'business-directory' ),
					'email'          => __( 'Email', 'business-directory' ),
					'emailHelp'      => __( 'Required. We will look up or create a user account for this email.', 'business-directory' ),
					'fullName'       => __( 'Full name', 'business-directory' ),
					'fullNameHelp'   => __( 'Used only when creating a new account.', 'business-directory' ),
					'phone'          => __( 'Phone (optional)', 'business-directory' ),
					'role'           => __( 'Role', 'business-directory' ),
					'owner'          => __( 'Owner', 'business-directory' ),
					'manager'        => __( 'Manager', 'business-directory' ),
					'staff'          => __( 'Staff', 'business-directory' ),
					'note'           => __( 'Internal note (optional)', 'business-directory' ),
					'noteHelp'       => __( 'Visible to directory managers only. Example: "Met at Farmers Market".', 'business-directory' ),
					'sendWelcome'    => __( 'Email the user their login details', 'business-directory' ),
					'submit'         => __( 'Grant Access', 'business-directory' ),
					'cancel'         => __( 'Cancel', 'business-directory' ),
					'submitting'     => __( 'Granting access…', 'business-directory' ),
					'revoking'       => __( 'Revoking…', 'business-directory' ),
					'successNew'     => __( '✅ Access granted. Welcome email sent.', 'business-directory' ),
					'successExist'   => __( '✅ Access granted.', 'business-directory' ),
					'successAlready' => __( 'ℹ️ This user already has access.', 'business-directory' ),
					'successRevoked' => __( '✅ Access revoked.', 'business-directory' ),
					'currentAccess'  => __( 'People with access', 'business-directory' ),
					'noAccess'       => __( 'No users have access yet.', 'business-directory' ),
					'revoke'         => __( 'Revoke', 'business-directory' ),
					'revokeConfirm'  => __( 'Remove this user\'s access to this business?', 'business-directory' ),
					'primary'        => __( 'Primary', 'business-directory' ),
					'ownerExists'    => __( 'This business already has an owner. This person will be added as an additional owner — the existing primary owner will not be changed.', 'business-directory' ),
					'errorGeneric'   => __( 'Something went wrong. Please try again.', 'business-directory' ),
					'errorEmail'     => __( 'Please enter a valid email address.', 'business-directory' ),
					'loading'        => __( 'Loading…', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Capability check for grant/revoke actions.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( 'bd_manage_claims' ) || current_user_can( 'manage_options' );
	}
}
