<?php
/**
 * Feature Settings - Admin settings for cross-site source URL
 *
 * @package BusinessDirectory
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeatureSettings {

	/**
	 * Initialize settings
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_section' ) );
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		// Register the source URL setting
		register_setting(
			'bd_feature_settings',
			'bd_feature_source_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_source_url' ),
				'default'           => '',
			)
		);

		// Register local features setting (for multisite)
		register_setting(
			'bd_feature_settings',
			'bd_enable_local_features',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_local_features' ),
				'default'           => null,
			)
		);

		// Add settings section
		add_settings_section(
			'bd_feature_section',
			'Feature Embed Settings',
			array( __CLASS__, 'render_section_description' ),
			'bd-feature-settings'
		);

		// Source URL field
		add_settings_field(
			'bd_feature_source_url',
			'Directory Source URL',
			array( __CLASS__, 'render_source_url_field' ),
			'bd-feature-settings',
			'bd_feature_section'
		);

		// Local features field (only show in multisite)
		if ( is_multisite() ) {
			add_settings_field(
				'bd_enable_local_features',
				'Local Directory Features',
				array( __CLASS__, 'render_local_features_field' ),
				'bd-feature-settings',
				'bd_feature_section'
			);
		}
	}

	/**
	 * Sanitize the local features checkbox value.
	 * Ensures we always get a boolean, even when checkbox is unchecked.
	 *
	 * @param mixed $value The submitted value.
	 * @return bool
	 */
	public static function sanitize_local_features( $value ) {
		return (bool) $value;
	}

	/**
	 * Add settings page
	 */
	public static function add_settings_section() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			'Feature Embed Settings',
			'Feature Embed',
			'manage_options',
			'bd-feature-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Check if local directory features are enabled on this site.
	 * Used by Installer to determine if tables should be created.
	 *
	 * @return bool
	 */
	public static function is_local_features_enabled() {
		// Single site always has local features
		if ( ! is_multisite() ) {
			return true;
		}

		// Use a sentinel value to detect if option was ever saved
		$setting = get_option( 'bd_enable_local_features', 'not_set' );

		// If never explicitly set, use default behavior
		if ( 'not_set' === $setting ) {
			return is_main_site();
		}

		// Return the explicit setting (works for both true and false)
		return (bool) $setting;
	}

	/**
	 * Sanitize source URL
	 */
	public static function sanitize_source_url( $value ) {
		$value = trim( $value );

		if ( empty( $value ) ) {
			return '';
		}

		// Remove protocol if present
		$value = preg_replace( '#^https?://#', '', $value );

		// Remove trailing slash
		$value = rtrim( $value, '/' );

		return sanitize_text_field( $value );
	}

	/**
	 * Render section description
	 */
	public static function render_section_description() {
		echo '<p>Configure the source site for embedding businesses from your directory network.</p>';
		echo '<p>If this site <strong>IS</strong> the main directory site (e.g., LoveTrivalley.com), leave this empty.</p>';
		echo '<p>If this site pulls businesses from another site, enter that site\'s URL below.</p>';
	}

	/**
	 * Render local features checkbox field
	 */
	public static function render_local_features_field() {
		$enabled    = self::is_local_features_enabled();
		$is_main    = is_main_site();
		$source_url = get_option( 'bd_feature_source_url', '' );
		?>
		<!-- Hidden field ensures "0" is submitted when checkbox is unchecked -->
		<input type="hidden" name="bd_enable_local_features" value="0">
		<label>
			<input 
				type="checkbox" 
				name="bd_enable_local_features" 
				id="bd_enable_local_features" 
				value="1"
				<?php checked( $enabled ); ?>
			>
			Enable local directory database on this site
		</label>
		<p class="description">
			When enabled, this site will have its own database tables for reviews, gamification, user profiles, and claims.<br>
			<?php if ( $is_main ) : ?>
				<strong>This is the main network site</strong> - local features are typically enabled here.
			<?php elseif ( ! empty( $source_url ) ) : ?>
				<strong>This site pulls from <?php echo esc_html( $source_url ); ?></strong> - local features are typically disabled.
			<?php else : ?>
				Enable this if you want this site to function as an independent directory hub.
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render source URL field
	 */
	public static function render_source_url_field() {
		$value = get_option( 'bd_feature_source_url', '' );
		?>
		<input 
			type="text" 
			name="bd_feature_source_url" 
			id="bd_feature_source_url" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text"
			placeholder="e.g., lovetrivalley.com"
		>
		<p class="description">
			Enter the domain of the main directory site (without https://).<br>
			Leave empty if this site has the business directory installed locally.
		</p>

		<?php if ( ! empty( $value ) ) : ?>
			<p style="margin-top: 10px;">
				<button type="button" class="button" id="bd-test-connection">Test Connection</button>
				<span id="bd-test-result" style="margin-left: 10px;"></span>
			</p>
			<script>
			document.getElementById('bd-test-connection').addEventListener('click', function() {
				var resultEl = document.getElementById('bd-test-result');
				resultEl.textContent = 'Testing...';
				resultEl.style.color = '#666';
				
				var sourceUrl = document.getElementById('bd_feature_source_url').value.trim();
				if (!sourceUrl) {
					resultEl.textContent = '❌ Please enter a source URL';
					resultEl.style.color = 'red';
					return;
				}
				
				var apiUrl = 'https://' + sourceUrl + '/wp-json/bd/v1/feature?ids=1';
				
				fetch(apiUrl)
					.then(function(response) {
						if (response.ok) {
							resultEl.textContent = '✅ Connection successful!';
							resultEl.style.color = 'green';
						} else {
							resultEl.textContent = '❌ API returned error: ' + response.status;
							resultEl.style.color = 'red';
						}
					})
					.catch(function(error) {
						resultEl.textContent = '❌ Connection failed: ' + error.message;
						resultEl.style.color = 'red';
					});
			});
			</script>
		<?php endif; ?>

		<div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
			<strong>Network Sites:</strong>
			<ul style="margin: 10px 0 0 20px;">
				<li><strong>LoveTrivalley.com</strong> - Main directory (leave source empty)</li>
				<li><strong>LoveLivermore.com</strong> - Set source to: <code>lovetrivalley.com</code></li>
				<li><strong>LovePleasanton.com</strong> - Set source to: <code>lovetrivalley.com</code></li>
				<li><strong>LoveDublin.com</strong> - Set source to: <code>lovetrivalley.com</code></li>
				<li><strong>LoveSanRamon.com</strong> - Set source to: <code>lovetrivalley.com</code></li>
				<li><strong>LoveDanville.com</strong> - Set source to: <code>lovetrivalley.com</code></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'bd_feature_settings' );
				do_settings_sections( 'bd-feature-settings' );
				submit_button( 'Save Settings' );
				?>
			</form>

			<hr style="margin: 30px 0;">

			<h2>Shortcode Usage</h2>
			<table class="widefat" style="max-width: 800px;">
				<thead>
					<tr>
						<th>Shortcode</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>[bd_feature id="123"]</code></td>
						<td>Single business</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45,23,89"]</code></td>
						<td>Multiple businesses in order specified</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45,23" layout="card"]</code></td>
						<td>Card layout (default)</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45,23" layout="list"]</code></td>
						<td>Compact list layout</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45,23" layout="inline"]</code></td>
						<td>Inline within text</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45,23" layout="mini"]</code></td>
						<td>Minimal link-only style</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45,23" columns="2"]</code></td>
						<td>2 columns (card layout)</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45" cta_text="Learn More"]</code></td>
						<td>Custom CTA button text</td>
					</tr>
					<tr>
						<td><code>[bd_feature ids="45" show="title,rating,cta"]</code></td>
						<td>Control what displays</td>
					</tr>
				</tbody>
			</table>

			<h3 style="margin-top: 20px;">Show Options</h3>
			<p>Use the <code>show</code> attribute with comma-separated values:</p>
			<ul style="margin-left: 20px;">
				<li><code>image</code> - Featured image</li>
				<li><code>title</code> - Business name (linked)</li>
				<li><code>rating</code> - Star rating</li>
				<li><code>excerpt</code> - Short description</li>
				<li><code>category</code> - Category & price level</li>
				<li><code>cta</code> - Call-to-action button</li>
			</ul>
			<p>Default: <code>show="image,title,rating,excerpt,category,cta"</code></p>
		</div>
		<?php
	}

	/**
	 * Get configured source URL (for use by shortcode)
	 */
	public static function get_source_url() {
		return get_option( 'bd_feature_source_url', '' );
	}
}
