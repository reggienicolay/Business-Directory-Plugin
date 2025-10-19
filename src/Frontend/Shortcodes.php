<?php
namespace BD\Frontend;

/**
 * Frontend shortcodes
 */
class Shortcodes {

	public function __construct() {
		add_shortcode( 'bd_directory', array( $this, 'directory_shortcode' ) );
	}

	public function directory_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'     => 'map',
				'category' => '',
				'per_page' => 20,
			),
			$atts,
			'bd_directory'
		);

		// Enqueue assets
		wp_enqueue_style( 'bd-frontend' );
		wp_enqueue_script( 'bd-frontend' );

		ob_start();
		?>
		<div class="bd-directory" data-view="<?php echo esc_attr( $atts['view'] ); ?>">
			<div class="bd-directory-header">
				<h2><?php _e( 'Business Directory', 'business-directory' ); ?></h2>
			</div>
			
			<div class="bd-directory-content">
				<?php echo do_shortcode( '[business_filters]' ); ?>
				
				<main class="bd-results">
					<div class="bd-view-toggle">
						<button class="bd-view-btn active" data-view="map"><?php _e( 'Map', 'business-directory' ); ?></button>
						<button class="bd-view-btn" data-view="list"><?php _e( 'List', 'business-directory' ); ?></button>
					</div>
					
					<div id="bd-map-container" class="bd-map-view">
						<div id="bd-map" style="height: 600px;"></div>
					</div>
					
					<div id="bd-list-container" class="bd-list-view" style="display:none;">
						<p><?php _e( 'Loading businesses...', 'business-directory' ); ?></p>
					</div>
				</main>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
