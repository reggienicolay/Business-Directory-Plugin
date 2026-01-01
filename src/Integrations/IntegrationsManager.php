<?php
/**
 * Integrations Manager
 *
 * Auto-detects compatible plugins and loads integrations.
 * Adds Integrations settings section to main Settings page.
 *
 * @package BusinessDirectory
 */

namespace BD\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IntegrationsManager
 */
class IntegrationsManager {

	/**
	 * Available integrations and their detection callbacks
	 *
	 * @var array
	 */
	private static $integrations = array();

	/**
	 * Initialize the integrations manager
	 */
	public static function init() {
		self::register_integrations();
		self::load_active_integrations();

		// Add settings section to main settings page
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'bd_settings_after_pages', array( __CLASS__, 'render_settings_section' ) );
	}

	/**
	 * Register available integrations
	 */
	private static function register_integrations() {
		self::$integrations = array(
			'events_calendar' => array(
				'name'        => __( 'The Events Calendar', 'business-directory' ),
				'description' => __( 'Link businesses to events, venues, and organizers. Display events on business pages.', 'business-directory' ),
				'detect'      => array( __CLASS__, 'detect_events_calendar' ),
				'loader'      => BD_PLUGIN_DIR . 'src/Integrations/EventsCalendar/EventsCalendarIntegration.php',
				'class'       => 'BD\\Integrations\\EventsCalendar\\EventsCalendarIntegration',
				'option'      => 'bd_integration_events_calendar',
				'settings'    => array(
					'show_events_on_business' => true,
					'show_business_on_events' => true,
					'enable_city_events_api'  => true,
				),
			),
		);
	}

	/**
	 * Detect if The Events Calendar is active
	 *
	 * @return bool
	 */
	public static function detect_events_calendar() {
		return class_exists( 'Tribe__Events__Main' );
	}

	/**
	 * Load active integrations
	 */
	private static function load_active_integrations() {
		foreach ( self::$integrations as $key => $integration ) {
			// Check if plugin is detected
			$detected = call_user_func( $integration['detect'] );

			if ( ! $detected ) {
				continue;
			}

			// Check if integration is enabled (default: true if detected)
			$enabled = get_option( $integration['option'], 'auto' );

			// 'auto' means enabled when detected, 'disabled' means explicitly disabled
			if ( 'disabled' === $enabled ) {
				continue;
			}

			// Load the integration
			if ( file_exists( $integration['loader'] ) ) {
				require_once $integration['loader'];

				if ( class_exists( $integration['class'] ) ) {
					call_user_func( array( $integration['class'], 'init' ) );
				}
			}
		}
	}

	/**
	 * Register integration settings
	 */
	public static function register_settings() {
		foreach ( self::$integrations as $key => $integration ) {
			register_setting( 'bd_settings', $integration['option'] );

			// Register sub-settings if any
			if ( ! empty( $integration['settings'] ) ) {
				foreach ( $integration['settings'] as $setting => $default ) {
					register_setting( 'bd_settings', $integration['option'] . '_' . $setting );
				}
			}
		}
	}

	/**
	 * Render integrations settings section
	 * Called via action hook from Settings.php
	 */
	public static function render_settings_section() {
		?>
		<hr style="margin: 40px 0;">

		<h2><?php esc_html_e( 'Integrations', 'business-directory' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Connect Business Directory with other plugins. Integrations are auto-detected and enabled automatically.', 'business-directory' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<?php foreach ( self::$integrations as $key => $integration ) : ?>
				<?php
				$detected = call_user_func( $integration['detect'] );
				$status   = get_option( $integration['option'], 'auto' );
				$enabled  = $detected && 'disabled' !== $status;
				?>
				<tr>
					<th scope="row">
						<?php echo esc_html( $integration['name'] ); ?>
					</th>
					<td>
						<?php if ( $detected ) : ?>
							<span class="bd-integration-status bd-integration-detected" style="color: #00a32a; font-weight: 600;">
								✓ <?php esc_html_e( 'Detected', 'business-directory' ); ?>
							</span>

							<label style="margin-left: 20px;">
								<input type="checkbox" 
									name="<?php echo esc_attr( $integration['option'] ); ?>" 
									value="auto"
									<?php checked( 'disabled' !== $status ); ?>>
								<?php esc_html_e( 'Enable Integration', 'business-directory' ); ?>
							</label>

							<?php if ( $enabled && ! empty( $integration['settings'] ) ) : ?>
								<div class="bd-integration-settings" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
									<strong style="display: block; margin-bottom: 10px;">
										<?php esc_html_e( 'Integration Settings:', 'business-directory' ); ?>
									</strong>

									<?php if ( 'events_calendar' === $key ) : ?>
										<label style="display: block; margin-bottom: 8px;">
											<input type="hidden" 
												name="<?php echo esc_attr( $integration['option'] ); ?>_show_events_on_business" 
												value="0">
											<input type="checkbox" 
												name="<?php echo esc_attr( $integration['option'] ); ?>_show_events_on_business" 
												value="1"
												<?php checked( get_option( $integration['option'] . '_show_events_on_business', 1 ) ); ?>>
											<?php esc_html_e( 'Show upcoming events on business pages', 'business-directory' ); ?>
										</label>

										<label style="display: block; margin-bottom: 8px;">
											<input type="hidden" 
												name="<?php echo esc_attr( $integration['option'] ); ?>_show_business_on_events" 
												value="0">
											<input type="checkbox" 
												name="<?php echo esc_attr( $integration['option'] ); ?>_show_business_on_events" 
												value="1"
												<?php checked( get_option( $integration['option'] . '_show_business_on_events', 1 ) ); ?>>
											<?php esc_html_e( 'Show linked business card on event pages', 'business-directory' ); ?>
										</label>

										<label style="display: block; margin-bottom: 8px;">
											<input type="hidden" 
												name="<?php echo esc_attr( $integration['option'] ); ?>_enable_city_events_api" 
												value="0">
											<input type="checkbox" 
												name="<?php echo esc_attr( $integration['option'] ); ?>_enable_city_events_api" 
												value="1"
												<?php checked( get_option( $integration['option'] . '_enable_city_events_api', 1 ) ); ?>>
											<?php esc_html_e( 'Enable REST API for city sites to fetch events', 'business-directory' ); ?>
										</label>
									<?php endif; ?>
								</div>
							<?php endif; ?>

						<?php else : ?>
							<span class="bd-integration-status bd-integration-not-detected" style="color: #666;">
								○ <?php esc_html_e( 'Not Detected', 'business-directory' ); ?>
							</span>
							<p class="description" style="margin-top: 5px;">
								<?php echo esc_html( $integration['description'] ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Check if a specific integration is active
	 *
	 * @param string $key Integration key.
	 * @return bool
	 */
	public static function is_active( $key ) {
		if ( ! isset( self::$integrations[ $key ] ) ) {
			return false;
		}

		$integration = self::$integrations[ $key ];
		$detected    = call_user_func( $integration['detect'] );
		$status      = get_option( $integration['option'], 'auto' );

		return $detected && 'disabled' !== $status;
	}

	/**
	 * Get integration setting value
	 *
	 * @param string $key     Integration key.
	 * @param string $setting Setting name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_setting( $key, $setting, $default = null ) {
		if ( ! isset( self::$integrations[ $key ] ) ) {
			return $default;
		}

		$integration = self::$integrations[ $key ];
		$option_name = $integration['option'] . '_' . $setting;

		// Get default from integration config if not specified
		if ( null === $default && isset( $integration['settings'][ $setting ] ) ) {
			$default = $integration['settings'][ $setting ];
		}

		return get_option( $option_name, $default );
	}
}
