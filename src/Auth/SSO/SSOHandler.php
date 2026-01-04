<?php

/**
 * SSO Handler
 *
 * Orchestrates cross-domain single sign-on for multisite with domain mapping.
 * Uses redirect chain for reliable authentication across all sites.
 *
 * @package BusinessDirectory
 * @subpackage Auth\SSO
 * @version 2.1.2
 */

namespace BD\Auth\SSO;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class SSOHandler
 */
class SSOHandler
{

	/**
	 * Query parameter for SSO token
	 */
	const TOKEN_PARAM = 'bd_sso_token';

	/**
	 * Query parameter for SSO action
	 */
	const ACTION_PARAM = 'bd_sso_action';

	/**
	 * User ID captured before logout (for SSO logout sync)
	 *
	 * @var int
	 */
	private static $logout_user_id = 0;

	/**
	 * Initialize SSO handler
	 */
	public static function init()
	{
		if (! is_multisite()) {
			return;
		}

		// Handle incoming SSO sync requests early.
		add_action('init', array(__CLASS__, 'handle_sso_request'), 1);

		// Hook into successful login for non-AJAX logins (redirect chain).
		add_action('wp_login', array(__CLASS__, 'trigger_sso_sync'), 99, 2);

		// Add SSO data to AJAX login response.
		add_filter('bd_auth_login_response', array(__CLASS__, 'add_sso_to_response'), 10, 2);

		// Capture user ID BEFORE logout.
		add_action('clear_auth_cookie', array(__CLASS__, 'capture_logout_user_id'));

		// Hook into logout.
		add_action('wp_logout', array(__CLASS__, 'trigger_sso_logout'), 1);

		// Enqueue SSO script.
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_sso_script'));
		add_action('wp_enqueue_scripts', array(__CLASS__, 'localize_sso_data'), 20);
	}

	/**
	 * URL-safe base64 encode
	 *
	 * Standard base64 uses +/ which get URL-decoded to spaces, corrupting tokens.
	 * This uses -_ instead and strips padding.
	 *
	 * @param string $data Data to encode.
	 * @return string URL-safe base64 encoded string.
	 */
	private static function base64_url_encode($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * URL-safe base64 decode
	 *
	 * @param string $data URL-safe base64 encoded string.
	 * @return string|false Decoded data or false on failure.
	 */
	private static function base64_url_decode($data)
	{
		$data    = strtr($data, '-_', '+/');
		$padding = strlen($data) % 4;
		if ($padding) {
			$data .= str_repeat('=', 4 - $padding);
		}
		return base64_decode($data, true);
	}

	/**
	 * Get all network site URLs for SSO sync
	 *
	 * @param int $exclude_site_id Site ID to exclude from list.
	 * @return array Array of site data.
	 */
	public static function get_network_sites($exclude_site_id = 0)
	{
		static $cache = array();

		$cache_key = 'sites_' . $exclude_site_id;
		if (isset($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$sites = array();

		if (! is_multisite()) {
			return $sites;
		}

		$network_sites = get_sites(
			array(
				'number'   => 100,
				'public'   => 1,
				'archived' => 0,
				'deleted'  => 0,
			)
		);

		foreach ($network_sites as $site) {
			if ($exclude_site_id && (int) $site->blog_id === (int) $exclude_site_id) {
				continue;
			}

			$site_url = get_home_url($site->blog_id);

			$sites[$site->blog_id] = array(
				'id'     => (int) $site->blog_id,
				'url'    => $site_url,
				'domain' => $site->domain,
				'name'   => get_blog_option($site->blog_id, 'blogname'),
			);
		}

		$cache[$cache_key] = $sites;
		return $sites;
	}

	/**
	 * Check if a URL belongs to our network
	 *
	 * @param string $url URL to check.
	 * @return bool True if URL is in network.
	 */
	public static function is_network_url($url)
	{
		if (empty($url)) {
			return false;
		}

		$parsed = wp_parse_url($url);
		if (empty($parsed['host'])) {
			return false;
		}

		$sites = self::get_network_sites();
		foreach ($sites as $site) {
			if ($site['domain'] === $parsed['host']) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the hub (main) site URL
	 *
	 * @return string Main site URL.
	 */
	public static function get_hub_url()
	{
		return get_home_url(get_main_site_id());
	}

	/**
	 * Check if current site is the hub
	 *
	 * @return bool True if current site is main site.
	 */
	public static function is_hub_site()
	{
		return get_current_blog_id() === get_main_site_id();
	}

	/**
	 * Handle incoming SSO sync request
	 */
	public static function handle_sso_request()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (! isset($_GET[self::TOKEN_PARAM])) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field(wp_unslash($_GET[self::TOKEN_PARAM]));
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset($_GET[self::ACTION_PARAM])
			? sanitize_key($_GET[self::ACTION_PARAM])
			: 'sync';

		switch ($action) {
			case 'sync':
				self::process_sync_request($token);
				break;

			case 'logout':
				self::process_logout_request($token);
				break;
		}
	}

	/**
	 * Add SSO redirect URL to AJAX login response
	 *
	 * Returns a redirect URL to start the SSO chain for cross-domain auth.
	 *
	 * @param array    $response Response data.
	 * @param \WP_User $user     Logged in user.
	 * @return array Modified response.
	 */
	public static function add_sso_to_response($response, $user)
	{
		$current_site = get_current_blog_id();
		$sites = self::get_network_sites($current_site);

		if (empty($sites)) {
			return $response;
		}

		// Get the return URL from the response or use home_url.
		$return_url = ! empty($response['redirect']) ? $response['redirect'] : home_url();

		// Generate redirect chain URL.
		$site_urls = array_column($sites, 'url');
		$first_url = array_shift($site_urls);

		$token = TokenManager::generate_token($user->ID, $current_site, $return_url);

		if ($token) {
			$sites_encoded = ! empty($site_urls) ? self::base64_url_encode(wp_json_encode($site_urls)) : '';

			$sync_url = add_query_arg(
				array(
					self::TOKEN_PARAM  => $token,
					self::ACTION_PARAM => 'sync',
					'bd_sso_next'      => ! empty($site_urls) ? 1 : 0,
					'bd_sso_sites'     => $sites_encoded,
					'bd_sso_return'    => rawurlencode($return_url),
				),
				trailingslashit($first_url)
			);

			$response['sso_redirect'] = $sync_url;
		}

		return $response;
	}

	/**
	 * Process SSO sync (login) request - redirect chain mode
	 *
	 * Each site in the chain validates the token, sets the auth cookie,
	 * generates a new token, and redirects to the next site.
	 *
	 * @param string $token SSO token.
	 */
	private static function process_sync_request($token)
	{
		$token_data = TokenManager::validate_token($token);

		if (! $token_data) {
			wp_safe_redirect(home_url());
			exit;
		}

		$user_id    = $token_data['user_id'];
		$return_url = ! empty($token_data['return_url']) ? $token_data['return_url'] : home_url();

		// Log the user in on this site.
		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, true);

		// Check for chain continuation.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$next_site = isset($_GET['bd_sso_next']) ? absint($_GET['bd_sso_next']) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sites_json = isset($_GET['bd_sso_sites'])
			? sanitize_text_field(wp_unslash($_GET['bd_sso_sites']))
			: '';

		if ($next_site && $sites_json) {
			$decoded = self::base64_url_decode($sites_json);
			$sites   = $decoded ? json_decode($decoded, true) : null;

			if (is_array($sites) && ! empty($sites)) {
				$new_token = TokenManager::generate_token($user_id, $token_data['origin_site'], $return_url);

				if ($new_token) {
					$next_site_url = array_shift($sites);

					if (self::is_network_url($next_site_url)) {
						$remaining = ! empty($sites) ? self::base64_url_encode(wp_json_encode(array_values($sites))) : '';

						$sync_url = add_query_arg(
							array(
								self::TOKEN_PARAM  => $new_token,
								self::ACTION_PARAM => 'sync',
								'bd_sso_next'      => $remaining ? 1 : 0,
								'bd_sso_sites'     => $remaining,
								'bd_sso_return'    => rawurlencode($return_url),
							),
							trailingslashit($next_site_url)
						);

						wp_redirect($sync_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
						exit;
					}
				}
			}
		}

		// Final redirect - chain complete.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$final_return = isset($_GET['bd_sso_return'])
			? esc_url_raw(rawurldecode(wp_unslash($_GET['bd_sso_return'])))
			: $return_url;

		if (! self::is_network_url($final_return)) {
			$final_return = home_url();
		}

		wp_redirect($final_return); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Process SSO logout request
	 *
	 * Clears auth cookies directly instead of calling wp_logout() to avoid
	 * triggering redirect hooks that would break the chain.
	 *
	 * @param string $token SSO token.
	 */
	private static function process_logout_request($token)
	{
		$token_data = TokenManager::validate_token($token);

		if (! $token_data) {
			wp_safe_redirect(home_url());
			exit;
		}

		// Clear auth cookies directly instead of wp_logout() to avoid redirect hooks.
		wp_clear_auth_cookie();
		wp_set_current_user(0);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$return_url = isset($_GET['bd_sso_return'])
			? esc_url_raw(rawurldecode(wp_unslash($_GET['bd_sso_return'])))
			: home_url();

		// Check for chain continuation.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$next_site = isset($_GET['bd_sso_next']) ? absint($_GET['bd_sso_next']) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sites_json = isset($_GET['bd_sso_sites'])
			? sanitize_text_field(wp_unslash($_GET['bd_sso_sites']))
			: '';

		if ($next_site && $sites_json) {
			$decoded = self::base64_url_decode($sites_json);
			$sites   = $decoded ? json_decode($decoded, true) : null;

			if (is_array($sites) && ! empty($sites)) {
				$new_token = TokenManager::generate_token($token_data['user_id'], $token_data['origin_site']);

				if ($new_token) {
					$next_site_url = array_shift($sites);

					if (self::is_network_url($next_site_url)) {
						$remaining = ! empty($sites) ? self::base64_url_encode(wp_json_encode(array_values($sites))) : '';

						$logout_url = add_query_arg(
							array(
								self::TOKEN_PARAM  => $new_token,
								self::ACTION_PARAM => 'logout',
								'bd_sso_next'      => $remaining ? 1 : 0,
								'bd_sso_sites'     => $remaining,
								'bd_sso_return'    => rawurlencode($return_url),
							),
							trailingslashit($next_site_url)
						);

						wp_redirect($logout_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
						exit;
					}
				}
			}
		}

		// Final redirect - chain complete.
		if (! self::is_network_url($return_url)) {
			$return_url = home_url();
		}

		wp_redirect($return_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Trigger SSO sync after successful non-AJAX login
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public static function trigger_sso_sync($user_login, $user)
	{
		if (wp_doing_ajax()) {
			return;
		}

		if (! apply_filters('bd_sso_enabled', true)) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$return_url = isset($_REQUEST['redirect_to'])
			? esc_url_raw(wp_unslash($_REQUEST['redirect_to']))
			: home_url();

		self::start_sync_chain($user->ID, $return_url);
	}

	/**
	 * Start the SSO sync chain (redirect mode)
	 *
	 * @param int    $user_id    User ID.
	 * @param string $return_url URL to return to after sync.
	 */
	public static function start_sync_chain($user_id, $return_url = '')
	{
		$current_site = get_current_blog_id();
		$sites        = self::get_network_sites($current_site);

		if (empty($sites)) {
			return;
		}

		$site_urls = array_column($sites, 'url');
		$first_url = array_shift($site_urls);

		$token = TokenManager::generate_token($user_id, $current_site, $return_url);

		if (! $token) {
			return;
		}

		$sites_encoded = ! empty($site_urls) ? self::base64_url_encode(wp_json_encode($site_urls)) : '';

		$sync_url = add_query_arg(
			array(
				self::TOKEN_PARAM  => $token,
				self::ACTION_PARAM => 'sync',
				'bd_sso_next'      => ! empty($site_urls) ? 1 : 0,
				'bd_sso_sites'     => $sites_encoded,
				'bd_sso_return'    => rawurlencode($return_url ?: home_url()),
			),
			trailingslashit($first_url)
		);

		wp_redirect($sync_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Capture user ID before logout
	 *
	 * Called on clear_auth_cookie hook to capture the user ID before
	 * the auth cookies are cleared.
	 */
	public static function capture_logout_user_id()
	{
		self::$logout_user_id = get_current_user_id();
	}

	/**
	 * Trigger SSO logout across all sites
	 *
	 * Initiates a redirect chain to log the user out of all network sites.
	 */
	public static function trigger_sso_logout()
	{
		// Don't trigger if we're already in a logout chain.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_GET[self::TOKEN_PARAM]) && isset($_GET[self::ACTION_PARAM]) && 'logout' === $_GET[self::ACTION_PARAM]) {
			return;
		}

		if (wp_doing_ajax()) {
			return;
		}

		$user_id = self::$logout_user_id;

		if (! $user_id) {
			return;
		}

		if (! apply_filters('bd_sso_logout_enabled', true)) {
			return;
		}

		$current_site = get_current_blog_id();
		$sites = self::get_network_sites($current_site);

		if (empty($sites)) {
			return;
		}

		$site_urls = array_column($sites, 'url');
		$first_url = array_shift($site_urls);

		$token = TokenManager::generate_token($user_id, $current_site);

		if (! $token) {
			return;
		}

		$sites_encoded = ! empty($site_urls) ? self::base64_url_encode(wp_json_encode($site_urls)) : '';

		$logout_url = add_query_arg(
			array(
				self::TOKEN_PARAM  => $token,
				self::ACTION_PARAM => 'logout',
				'bd_sso_next'      => ! empty($site_urls) ? 1 : 0,
				'bd_sso_sites'     => $sites_encoded,
				'bd_sso_return'    => rawurlencode(home_url()),
			),
			trailingslashit($first_url)
		);

		wp_redirect($logout_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Enqueue SSO JavaScript
	 */
	public static function enqueue_sso_script()
	{
		if (is_admin()) {
			return;
		}

		wp_enqueue_script(
			'bd-sso',
			BD_PLUGIN_URL . 'assets/js/sso.js',
			array('jquery'),
			BD_VERSION,
			true
		);
	}

	/**
	 * Localize SSO data
	 */
	public static function localize_sso_data()
	{
		if (is_admin()) {
			return;
		}

		wp_localize_script(
			'bd-sso',
			'bdSSO',
			array(
				'enabled'     => is_multisite(),
				'isHub'       => self::is_hub_site(),
				'hubUrl'      => self::get_hub_url(),
				'currentSite' => get_current_blog_id(),
				'isLoggedIn'  => is_user_logged_in(),
				'tokenParam'  => self::TOKEN_PARAM,
				'actionParam' => self::ACTION_PARAM,
			)
		);
	}
}
