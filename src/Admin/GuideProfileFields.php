<?php
/**
 * Guide Profile Fields
 *
 * Adds cover photo selector and other Guide fields to WordPress user profile.
 *
 * @package BusinessDirectory
 * @subpackage Admin
 * @version 1.0.0
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GuideProfileFields {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		// Add fields to user profile
		add_action( 'show_user_profile', array( __CLASS__, 'render_guide_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_guide_fields' ) );

		// Save fields
		add_action( 'personal_options_update', array( __CLASS__, 'save_guide_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_guide_fields' ) );

		// Enqueue admin styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
	}

	/**
	 * Enqueue admin styles for profile page
	 */
	public static function enqueue_admin_styles( $hook ) {
		if ( 'user-edit.php' !== $hook && 'profile.php' !== $hook ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', self::get_inline_styles() );
	}

	/**
	 * Get inline styles for the cover selector
	 */
	private static function get_inline_styles() {
		return '
			.bd-guide-fields {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				padding: 20px;
				margin: 20px 0;
			}
			.bd-guide-fields h2 {
				margin-top: 0;
				padding-bottom: 12px;
				border-bottom: 2px solid #c9a227;
				color: #1a3a4a;
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.bd-guide-fields h2::before {
				content: "⭐";
			}
			.bd-cover-options {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
				gap: 16px;
				margin-top: 12px;
			}
			.bd-cover-option {
				position: relative;
				cursor: pointer;
				border-radius: 8px;
				overflow: hidden;
				border: 3px solid transparent;
				transition: all 0.2s ease;
			}
			.bd-cover-option:hover {
				border-color: #c9a227;
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.15);
			}
			.bd-cover-option.selected {
				border-color: #c9a227;
				box-shadow: 0 0 0 2px rgba(201, 162, 39, 0.3);
			}
			.bd-cover-option input {
				position: absolute;
				opacity: 0;
				pointer-events: none;
			}
			.bd-cover-option img {
				width: 100%;
				height: 80px;
				object-fit: cover;
				display: block;
			}
			.bd-cover-option-label {
				padding: 8px 12px;
				background: #f6f7f7;
				font-size: 12px;
				font-weight: 500;
				color: #50575e;
				text-transform: capitalize;
			}
			.bd-cover-option.selected .bd-cover-option-label {
				background: #1a3a4a;
				color: #fff;
			}
			.bd-cover-option-check {
				position: absolute;
				top: 8px;
				right: 8px;
				width: 24px;
				height: 24px;
				background: #c9a227;
				border-radius: 50%;
				display: none;
				align-items: center;
				justify-content: center;
				color: #fff;
				font-size: 14px;
				box-shadow: 0 2px 4px rgba(0,0,0,0.2);
			}
			.bd-cover-option.selected .bd-cover-option-check {
				display: flex;
			}
			.bd-cover-default-option {
				background: linear-gradient(135deg, #133453 0%, #0f2530 100%);
				height: 80px;
				display: flex;
				align-items: center;
				justify-content: center;
				color: rgba(255,255,255,0.5);
				font-size: 12px;
			}
			.bd-no-covers-notice {
				padding: 20px;
				background: #fff8e5;
				border-left: 4px solid #c9a227;
				color: #6b5900;
			}
			.bd-no-covers-notice code {
				background: rgba(0,0,0,0.05);
				padding: 2px 6px;
				border-radius: 3px;
			}
		';
	}

	/**
	 * Render Guide fields on user profile
	 */
	public static function render_guide_fields( $user ) {
		// Check if user is a guide
		$is_guide = get_user_meta( $user->ID, 'bd_is_guide', true );
		
		// Get current cover selection
		$current_cover = get_user_meta( $user->ID, 'bd_cover_photo', true );
		
		// Get available covers
		$covers = self::get_available_covers();
		
		?>
		<div class="bd-guide-fields">
			<h2><?php esc_html_e( 'Community Guide Settings', 'business-directory' ); ?></h2>

			<?php if ( ! $is_guide ) : ?>
				<p style="color: #666; font-style: italic;">
					<?php esc_html_e( 'This user is not currently a Community Guide. Cover photo options are available for Guides only.', 'business-directory' ); ?>
				</p>
			<?php else : ?>

				<table class="form-table">
					<tr>
						<th>
							<label><?php esc_html_e( 'Profile Cover Photo', 'business-directory' ); ?></label>
						</th>
						<td>
							<?php if ( empty( $covers ) ) : ?>
								<div class="bd-no-covers-notice">
									<strong><?php esc_html_e( 'No cover images found.', 'business-directory' ); ?></strong><br>
									<?php 
									printf(
										/* translators: %s: folder path */
										esc_html__( 'Add .jpg images to: %s', 'business-directory' ),
										'<code>/wp-content/plugins/business-directory/assets/images/covers/</code>'
									); 
									?>
								</div>
							<?php else : ?>
								<p class="description" style="margin-bottom: 12px;">
									<?php esc_html_e( 'Select a cover photo for this Guide\'s profile page.', 'business-directory' ); ?>
								</p>
								<div class="bd-cover-options">
									<!-- Default (no cover) option -->
									<label class="bd-cover-option <?php echo empty( $current_cover ) ? 'selected' : ''; ?>">
										<input type="radio" name="bd_cover_photo" value="" <?php checked( $current_cover, '' ); ?>>
										<div class="bd-cover-default-option">
											<?php esc_html_e( 'Default Gradient', 'business-directory' ); ?>
										</div>
										<div class="bd-cover-option-label"><?php esc_html_e( 'None (Default)', 'business-directory' ); ?></div>
										<span class="bd-cover-option-check">✓</span>
									</label>

									<?php foreach ( $covers as $cover ) : ?>
										<label class="bd-cover-option <?php echo ( $current_cover === $cover['key'] ) ? 'selected' : ''; ?>">
											<input type="radio" name="bd_cover_photo" value="<?php echo esc_attr( $cover['key'] ); ?>" <?php checked( $current_cover, $cover['key'] ); ?>>
											<img src="<?php echo esc_url( $cover['url'] ); ?>" alt="<?php echo esc_attr( $cover['label'] ); ?>">
											<div class="bd-cover-option-label"><?php echo esc_html( $cover['label'] ); ?></div>
											<span class="bd-cover-option-check">✓</span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<script>
				jQuery(document).ready(function($) {
					$('.bd-cover-option input').on('change', function() {
						$('.bd-cover-option').removeClass('selected');
						$(this).closest('.bd-cover-option').addClass('selected');
					});
				});
				</script>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save Guide fields
	 */
	public static function save_guide_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Verify this is a guide before saving cover
		$is_guide = get_user_meta( $user_id, 'bd_is_guide', true );
		if ( ! $is_guide ) {
			return;
		}

		// Save cover photo
		if ( isset( $_POST['bd_cover_photo'] ) ) {
			$cover = sanitize_text_field( wp_unslash( $_POST['bd_cover_photo'] ) );
			
			// Validate cover exists (or is empty for default)
			if ( empty( $cover ) || self::cover_exists( $cover ) ) {
				update_user_meta( $user_id, 'bd_cover_photo', $cover );
			}
		}
	}

	/**
	 * Get available cover images from folder
	 */
	public static function get_available_covers() {
		$covers = array();
		$covers_dir = BD_PLUGIN_DIR . 'assets/images/covers/';
		$covers_url = BD_PLUGIN_URL . 'assets/images/covers/';

		if ( ! is_dir( $covers_dir ) ) {
			return $covers;
		}

		$files = scandir( $covers_dir );
		
		foreach ( $files as $file ) {
			// Only process jpg/jpeg files
			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg' ), true ) ) {
				continue;
			}

			$key = pathinfo( $file, PATHINFO_FILENAME );
			$label = self::format_cover_label( $key );

			$covers[] = array(
				'key'   => $key,
				'file'  => $file,
				'url'   => $covers_url . $file,
				'label' => $label,
			);
		}

		// Sort alphabetically by label
		usort( $covers, function( $a, $b ) {
			return strcmp( $a['label'], $b['label'] );
		});

		return $covers;
	}

	/**
	 * Check if a cover image exists
	 */
	public static function cover_exists( $key ) {
		$covers_dir = BD_PLUGIN_DIR . 'assets/images/covers/';
		return file_exists( $covers_dir . $key . '.jpg' ) || file_exists( $covers_dir . $key . '.jpeg' );
	}

	/**
	 * Format cover filename as readable label
	 * e.g., "vineyard-hills" becomes "Vineyard Hills"
	 */
	private static function format_cover_label( $key ) {
		$label = str_replace( array( '-', '_' ), ' ', $key );
		return ucwords( $label );
	}
}
