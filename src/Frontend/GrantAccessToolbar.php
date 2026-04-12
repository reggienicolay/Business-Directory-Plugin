<?php
/**
 * Frontend Admin Bar — Grant Access Node
 *
 * Adds a "🔑 Grant Access" node to the WordPress admin bar on single
 * bd_business pages for directory managers. Tapping the node opens the
 * shared grant-access modal, so a manager (e.g. Nicole meeting a business
 * owner in person) can authorise them straight from the business's public
 * page on her phone — no wp-admin login required.
 *
 * Asset loading delegates to BusinessAccessMetaBox::enqueue_modal_assets()
 * so the admin and frontend entry points share one bundle.
 *
 * @package BusinessDirectory
 * @subpackage Frontend
 * @since 0.1.8
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Admin\BusinessAccessMetaBox;

/**
 * Class GrantAccessToolbar
 */
class GrantAccessToolbar {

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		// Priority 100 keeps us below WP's built-in nodes.
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Add the Grant Access node to the admin bar on single bd_business pages.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar.
	 */
	public function add_node( $wp_admin_bar ) {
		if ( ! $this->should_show() ) {
			return;
		}

		$business_id = (int) get_queried_object_id();
		if ( ! $business_id ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'bd-grant-access',
				'title'  => '🔑 ' . esc_html__( 'Grant Access', 'business-directory' ),
				'href'   => '#bd-grant-access-' . $business_id,
				'parent' => 'top-secondary',
				'meta'   => array(
					'class' => 'bd-grant-access-toolbar bd-grant-access-trigger',
					'title' => esc_attr__( 'Grant a business owner access to manage this listing', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Enqueue modal assets on single bd_business frontend pages.
	 *
	 * Only loads when the admin bar node will actually render.
	 */
	public function enqueue_frontend_assets() {
		if ( ! $this->should_show() ) {
			return;
		}

		$business_id = (int) get_queried_object_id();
		if ( ! $business_id ) {
			return;
		}

		BusinessAccessMetaBox::enqueue_modal_assets();

		// Stamp the current business ID into a tiny inline script so the
		// toolbar trigger knows which business to target when clicked. This
		// supplements the data-business-id attributes used on admin screens.
		wp_add_inline_script(
			'bd-grant-access-modal',
			sprintf(
				'window.bdGrantAccess = window.bdGrantAccess || {}; window.bdGrantAccess.currentBusinessId = %d;',
				$business_id
			),
			'before'
		);
	}

	/**
	 * Should the admin bar node be shown on the current request?
	 *
	 * @return bool
	 */
	private function should_show() {
		if ( is_admin() ) {
			return false;
		}
		if ( ! is_admin_bar_showing() ) {
			return false;
		}
		if ( ! is_singular( 'bd_business' ) ) {
			return false;
		}
		return BusinessAccessMetaBox::current_user_can_manage();
	}
}
