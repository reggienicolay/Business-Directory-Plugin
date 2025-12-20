<?php
/**
 * Feature Picker - TinyMCE button and modal for selecting businesses
 *
 * @package BusinessDirectory
 */

namespace BD\Admin;

class FeaturePicker {

	/**
	 * Initialize picker
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'media_buttons', array( __CLASS__, 'add_media_button' ), 15 );
		add_action( 'admin_footer', array( __CLASS__, 'render_picker_modal' ) );
	}

	/**
	 * Enqueue picker assets
	 */
	public static function enqueue_assets( $hook ) {
		// Only load on post edit screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'bd-feature-picker',
			BD_PLUGIN_URL . 'assets/css/feature-picker.css',
			array(),
			BD_VERSION
		);

		wp_enqueue_script(
			'bd-feature-picker',
			BD_PLUGIN_URL . 'assets/js/feature-picker.js',
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		// Get source URL from settings
		$source_url = FeatureSettings::get_source_url();
		$api_base   = empty( $source_url ) ? rest_url( 'bd/v1/' ) : 'https://' . $source_url . '/wp-json/bd/v1/';

		wp_localize_script(
			'bd-feature-picker',
			'bdFeaturePicker',
			array(
				'apiUrl'    => $api_base,
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'sourceUrl' => $source_url,
				'isLocal'   => empty( $source_url ),
			)
		);
	}

	/**
	 * Add media button
	 */
	public static function add_media_button() {
		?>
		<button type="button" id="bd-feature-picker-btn" class="button bd-feature-picker-btn">
			<span class="dashicons dashicons-store" style="vertical-align: text-bottom;"></span>
			Add Business
		</button>
		<?php
	}

	/**
	 * Render picker modal
	 */
	public static function render_picker_modal() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}
		?>
		<div id="bd-feature-picker-modal" class="bd-picker-modal" style="display: none;">
			<div class="bd-picker-backdrop"></div>
			<div class="bd-picker-container">
				<div class="bd-picker-header">
					<h2>Insert Featured Businesses</h2>
					<button type="button" class="bd-picker-close">&times;</button>
				</div>

				<div class="bd-picker-body">
					<!-- Search & Filter -->
					<div class="bd-picker-search-bar">
						<input type="text" id="bd-picker-search" placeholder="Search businesses..." class="bd-picker-input">
						<select id="bd-picker-category" class="bd-picker-select">
							<option value="">All Categories</option>
						</select>
						<button type="button" id="bd-picker-search-btn" class="button">Search</button>
					</div>

					<!-- Results -->
					<div class="bd-picker-results-container">
						<div id="bd-picker-results" class="bd-picker-results">
							<p class="bd-picker-loading">Loading businesses...</p>
						</div>
					</div>

					<!-- Selected -->
					<div class="bd-picker-selected-section">
						<h3>Selected Businesses <span id="bd-picker-selected-count">(0)</span></h3>
						<div id="bd-picker-selected" class="bd-picker-selected">
							<p class="bd-picker-empty">No businesses selected. Click on businesses above to add them.</p>
						</div>
						<div class="bd-picker-reorder-hint" style="display: none;">
							<small>Drag to reorder</small>
						</div>
					</div>

					<!-- Options -->
					<div class="bd-picker-options">
						<div class="bd-picker-option">
							<label for="bd-picker-layout">Layout:</label>
							<select id="bd-picker-layout" class="bd-picker-select">
								<option value="card">Card Grid</option>
								<option value="list">Compact List</option>
								<option value="inline">Inline</option>
								<option value="mini">Mini Links</option>
							</select>
						</div>

						<div class="bd-picker-option bd-picker-columns-option">
							<label for="bd-picker-columns">Columns:</label>
							<select id="bd-picker-columns" class="bd-picker-select">
								<option value="2">2 Columns</option>
								<option value="3" selected>3 Columns</option>
								<option value="4">4 Columns</option>
							</select>
						</div>

						<div class="bd-picker-option">
							<label for="bd-picker-cta">CTA Text:</label>
							<input type="text" id="bd-picker-cta" class="bd-picker-input-sm" value="View Details" placeholder="View Details">
						</div>
					</div>

					<!-- Preview -->
					<div class="bd-picker-preview">
						<label>Shortcode Preview:</label>
						<code id="bd-picker-shortcode-preview">[bd_feature ids="" layout="card"]</code>
					</div>
				</div>

				<div class="bd-picker-footer">
					<button type="button" class="button bd-picker-cancel">Cancel</button>
					<button type="button" class="button button-primary bd-picker-insert" disabled>Insert Shortcode</button>
				</div>
			</div>
		</div>
		<?php
	}
}
