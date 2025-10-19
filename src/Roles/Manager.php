<?php
namespace BD\Roles;

class Manager {

	public static function create() {
		// Existing Directory Manager role
		add_role(
			'bd_manager',
			__( 'Directory Manager', 'business-directory' ),
			array(
				'read'          => true,
				'edit_posts'    => true,
				'delete_posts'  => true,
				'publish_posts' => true,
				'upload_files'  => true,
			)
		);

		$manager_role = get_role( 'bd_manager' );
		if ( $manager_role ) {
			$manager_role->add_cap( 'bd_manage_businesses' );
			$manager_role->add_cap( 'bd_moderate_reviews' );
			$manager_role->add_cap( 'bd_approve_submissions' );
			$manager_role->add_cap( 'bd_manage_claims' );
		}

		// NEW: Business Owner role (limited permissions)
		add_role(
			'business_owner',
			__( 'Business Owner', 'business-directory' ),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);

		$owner_role = get_role( 'business_owner' );
		if ( $owner_role ) {
			// Can edit ONLY their own business
			$owner_role->add_cap( 'bd_edit_own_business' );
			$owner_role->add_cap( 'bd_upload_to_own_business' );
			$owner_role->add_cap( 'bd_view_own_analytics' );
			$owner_role->add_cap( 'bd_respond_to_reviews' );
		}

		// Admin gets all capabilities
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'bd_manage_businesses' );
			$admin->add_cap( 'bd_moderate_reviews' );
			$admin->add_cap( 'bd_approve_submissions' );
			$admin->add_cap( 'bd_manage_claims' );
			$admin->add_cap( 'bd_edit_any_business' );
		}
	}

	public static function remove() {
		remove_role( 'bd_manager' );
		remove_role( 'business_owner' );
	}
}
