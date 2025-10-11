<?php
namespace BD\Admin;

class Settings {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=bd_business',
            __('Settings', 'business-directory'),
            __('Settings', 'business-directory'),
            'manage_options',
            'bd-settings',
            [$this, 'render_page']
        );
    }
    
    public function register_settings() {
        register_setting('bd_settings', 'bd_turnstile_site_key');
        register_setting('bd_settings', 'bd_turnstile_secret_key');
        register_setting('bd_settings', 'bd_notification_emails');
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Business Directory Settings', 'business-directory'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('bd_settings'); ?>
                
                <h2><?php _e('Cloudflare Turnstile', 'business-directory'); ?></h2>
                <p><?php _e('Get free keys at: <a href="https://dash.cloudflare.com/sign-up/turnstile" target="_blank">Cloudflare Turnstile</a>', 'business-directory'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th><label for="bd_turnstile_site_key"><?php _e('Site Key', 'business-directory'); ?></label></th>
                        <td>
                            <input type="text" id="bd_turnstile_site_key" name="bd_turnstile_site_key" value="<?php echo esc_attr(get_option('bd_turnstile_site_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bd_turnstile_secret_key"><?php _e('Secret Key', 'business-directory'); ?></label></th>
                        <td>
                            <input type="text" id="bd_turnstile_secret_key" name="bd_turnstile_secret_key" value="<?php echo esc_attr(get_option('bd_turnstile_secret_key')); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Notifications', 'business-directory'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><label for="bd_notification_emails"><?php _e('Email Addresses', 'business-directory'); ?></label></th>
                        <td>
                            <input type="text" id="bd_notification_emails" name="bd_notification_emails" value="<?php echo esc_attr(get_option('bd_notification_emails', get_option('admin_email'))); ?>" class="regular-text" />
                            <p class="description"><?php _e('Comma-separated list of emails to receive notifications', 'business-directory'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
