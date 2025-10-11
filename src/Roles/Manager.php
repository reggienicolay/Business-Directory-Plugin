<?php
namespace BD\Roles;

class Manager {
    
    public static function create() {
        add_role(
            'bd_manager',
            __('Directory Manager', 'business-directory'),
            [
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'publish_posts' => true,
                'upload_files' => true,
            ]
        );
        
        $role = get_role('bd_manager');
        if ($role) {
            $role->add_cap('bd_manage_businesses');
            $role->add_cap('bd_moderate_reviews');
            $role->add_cap('bd_approve_submissions');
        }
        
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('bd_manage_businesses');
            $admin->add_cap('bd_moderate_reviews');
            $admin->add_cap('bd_approve_submissions');
        }
    }
    
    public static function remove() {
        remove_role('bd_manager');
    }
}
