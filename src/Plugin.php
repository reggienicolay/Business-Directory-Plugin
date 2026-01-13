<?php
/**
 * Main Plugin Class
 *
 * Singleton pattern implementation for the Business Directory plugin.
 * Handles core initialization, hooks, and component loading.
 *
 * @package BusinessDirectory
 * @since 0.1.0
 */

namespace BD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin singleton class.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Initialize plugin components.
	 *
	 * Note: Gamification components (GamificationHooks, BadgeAdmin, ProfileShortcode)
	 * are loaded via includes/gamification-loader.php to avoid duplicates.
	 */
	private function init_components() {
		// Admin components.
		if ( is_admin() ) {
			new Admin\MetaBoxes();
			new Admin\ImporterPage();
			new Admin\Settings();
			new Moderation\ReviewsQueue();
			new Admin\ClaimsQueue();
			Admin\FeaturedAdmin::init();
			// BadgeAdmin is loaded by gamification-loader.php
		}

		// Frontend components.
		new Frontend\Shortcodes();
		Frontend\QuickFilterDisplay::init();
		new Forms\BusinessSubmission();
		new Forms\ReviewSubmission();
		new Forms\ClaimRequest();
	}

	/**
	 * Register custom post types.
	 */
	public function register_post_types() {
		PostTypes\Business::register();
	}

	/**
	 * Register custom taxonomies.
	 */
	public function register_taxonomies() {
		Taxonomies\Category::register();
		Taxonomies\Area::register();
		Taxonomies\Tag::register();
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'business-directory',
			false,
			dirname( BD_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_assets( $hook ) {
		$screen = get_current_screen();

		if ( $screen && $screen->post_type === 'bd_business' ) {
			wp_enqueue_style(
				'bd-admin',
				BD_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				BD_VERSION
			);

			wp_enqueue_script(
				'bd-admin',
				BD_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				BD_VERSION,
				true
			);

			if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
				wp_enqueue_script(
					'bd-admin-map',
					BD_PLUGIN_URL . 'assets/js/admin-map.js',
					array( 'jquery' ),
					BD_VERSION,
					true
				);
			}
		}
	}

	/**
	 * Register frontend assets.
	 *
	 * Note: These are registered, not enqueued. Shortcodes and templates
	 * should enqueue these when needed using wp_enqueue_style/script.
	 */
	public function frontend_assets() {
		wp_register_style(
			'bd-frontend',
			BD_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			BD_VERSION
		);

		wp_register_script(
			'bd-frontend',
			BD_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			BD_VERSION,
			true
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		REST\BusinessesController::register();
		REST\SubmitBusinessController::register();
		REST\SubmitReviewController::register();
		REST\ClaimController::register();
	}
}
