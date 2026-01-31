<?php
/**
 * Export Admin Page
 *
 * Admin interface for exporting businesses to CSV.
 *
 * @package BusinessDirectory
 * @version 1.2.0
 */

namespace BD\Admin;

use BD\Exporter\CSV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExporterPage
 */
class ExporterPage {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 26 );
		add_action( 'admin_post_bd_export_csv', array( $this, 'handle_export' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bd_get_export_count', array( $this, 'ajax_get_export_count' ) );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Export CSV', 'business-directory' ),
			__( 'Export CSV', 'business-directory' ),
			'manage_options',
			'bd-export-csv',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'bd_business_page_bd-export-csv' !== $hook ) {
			return;
		}

		wp_register_style( 'bd-export-admin', false, array(), '1.0.0' );
		wp_enqueue_style( 'bd-export-admin' );
		wp_add_inline_style( 'bd-export-admin', $this->get_inline_styles() );

		// Register and enqueue a minimal script for localization.
		wp_register_script( 'bd-export-admin', '', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_script( 'bd-export-admin' );

		wp_localize_script(
			'bd-export-admin',
			'bdExport',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_export_nonce' ),
				'strings' => array(
					'counting'   => __( 'Counting...', 'business-directory' ),
					'businesses' => __( 'businesses', 'business-directory' ),
					'business'   => __( 'business', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Get inline CSS styles.
	 *
	 * @return string CSS styles.
	 */
	private function get_inline_styles() {
		return '
			.bd-export-wrap { max-width: 1000px; }
			.bd-export-section { 
				background: #fff; 
				padding: 20px 25px; 
				margin: 20px 0; 
				border: 1px solid #c3c4c7; 
				border-radius: 4px; 
			}
			.bd-export-section h2 { 
				margin-top: 0; 
				padding-bottom: 12px; 
				border-bottom: 1px solid #eee; 
			}
			.bd-export-options { 
				display: grid; 
				grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
				gap: 20px; 
				margin: 20px 0; 
			}
			.bd-export-option { margin-bottom: 15px; }
			.bd-export-option label { 
				display: block; 
				font-weight: 600; 
				margin-bottom: 5px; 
			}
			.bd-export-option select,
			.bd-export-option input[type="text"] { 
				width: 100%; 
				max-width: 300px; 
			}
			.bd-export-option .description { 
				color: #666; 
				font-size: 12px; 
				margin-top: 4px; 
			}
			.bd-checkbox-option { 
				display: flex; 
				align-items: center; 
				gap: 8px; 
				margin: 8px 0; 
			}
			.bd-checkbox-option input[type="checkbox"] { margin: 0; }
			.bd-export-preview { 
				background: #f0f6fc; 
				border: 1px solid #c3c4c7; 
				border-radius: 4px; 
				padding: 15px 20px; 
				margin: 20px 0; 
			}
			.bd-export-preview h3 { 
				margin: 0 0 10px; 
				font-size: 14px; 
				color: #1d2327; 
			}
			.bd-export-count { 
				font-size: 32px; 
				font-weight: 700; 
				color: #2271b1; 
			}
			.bd-export-count-label { 
				font-size: 14px; 
				color: #50575e; 
			}
			.bd-export-button { 
				display: inline-flex; 
				align-items: center; 
				gap: 8px; 
				padding: 10px 24px; 
				background: #722F37; 
				color: #fff; 
				border: none; 
				border-radius: 4px; 
				font-size: 14px; 
				font-weight: 600; 
				cursor: pointer; 
				text-decoration: none; 
			}
			.bd-export-button:hover { 
				background: #5a252c; 
				color: #fff; 
			}
			.bd-export-button:disabled { 
				background: #a7aaad; 
				cursor: not-allowed; 
			}
			.bd-export-button .dashicons { 
				font-size: 18px; 
				width: 18px; 
				height: 18px; 
			}
			.bd-format-info { 
				background: #f6f7f7; 
				border: 1px solid #ddd; 
				border-radius: 4px; 
				padding: 15px; 
				margin-top: 15px; 
			}
			.bd-format-info h4 { 
				margin: 0 0 10px; 
				font-size: 13px; 
			}
			.bd-format-info ul { 
				margin: 0; 
				padding-left: 20px; 
			}
			.bd-format-info li { 
				font-size: 12px; 
				color: #50575e; 
				margin: 4px 0; 
			}
			.bd-columns-list { 
				display: flex; 
				flex-wrap: wrap; 
				gap: 6px; 
				margin-top: 10px; 
			}
			.bd-columns-list code { 
				background: #fff; 
				padding: 2px 6px; 
				border-radius: 3px; 
				font-size: 11px; 
			}
			.bd-notice { 
				padding: 12px 16px; 
				border-radius: 4px; 
				margin-bottom: 16px; 
			}
			.bd-notice-info { 
				background: #f0f6fc; 
				border-left: 4px solid #72aee6; 
			}
		';
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		// Get categories and areas for filters.
		$categories = get_terms(
			array(
				'taxonomy'   => 'bd_category',
				'hide_empty' => false,
			)
		);

		$areas = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => false,
			)
		);

		// Get unique cities from locations table.
		$cities = $this->get_unique_cities();

		// Initial count (use 'publish' to match default dropdown selection).
		$total_count = CSV::get_export_count( array( 'status' => 'publish' ) );

		?>
		<div class="wrap bd-export-wrap">
			<h1><?php esc_html_e( 'Export Businesses to CSV', 'business-directory' ); ?></h1>

			<div class="bd-notice bd-notice-info">
				<strong><?php esc_html_e( 'Tip:', 'business-directory' ); ?></strong>
				<?php esc_html_e( 'The exported CSV is compatible with the Import feature. You can export, modify in a spreadsheet, and re-import to update businesses in bulk.', 'business-directory' ); ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bd-export-form">
				<?php wp_nonce_field( 'bd_export_csv', 'bd_export_nonce' ); ?>
				<input type="hidden" name="action" value="bd_export_csv">

				<div class="bd-export-section">
					<h2><?php esc_html_e( 'Filter Businesses', 'business-directory' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Select which businesses to include in the export. Leave filters empty to export all.', 'business-directory' ); ?></p>

					<div class="bd-export-options">
						<div class="bd-export-option">
							<label for="export_status"><?php esc_html_e( 'Status', 'business-directory' ); ?></label>
							<select name="status" id="export_status">
								<option value="any"><?php esc_html_e( 'All Statuses', 'business-directory' ); ?></option>
								<option value="publish" selected><?php esc_html_e( 'Published', 'business-directory' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'business-directory' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending Review', 'business-directory' ); ?></option>
							</select>
						</div>

						<div class="bd-export-option">
							<label for="export_category"><?php esc_html_e( 'Category', 'business-directory' ); ?></label>
							<select name="category" id="export_category">
								<option value=""><?php esc_html_e( 'All Categories', 'business-directory' ); ?></option>
								<?php if ( ! is_wp_error( $categories ) ) : ?>
									<?php foreach ( $categories as $category ) : ?>
										<option value="<?php echo esc_attr( $category->slug ); ?>">
											<?php echo esc_html( $category->name ); ?> (<?php echo esc_html( $category->count ); ?>)
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</div>

						<div class="bd-export-option">
							<label for="export_area"><?php esc_html_e( 'Area', 'business-directory' ); ?></label>
							<select name="area" id="export_area">
								<option value=""><?php esc_html_e( 'All Areas', 'business-directory' ); ?></option>
								<?php if ( ! is_wp_error( $areas ) ) : ?>
									<?php foreach ( $areas as $area ) : ?>
										<option value="<?php echo esc_attr( $area->slug ); ?>">
											<?php echo esc_html( $area->name ); ?> (<?php echo esc_html( $area->count ); ?>)
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</div>

						<div class="bd-export-option">
							<label for="export_city"><?php esc_html_e( 'City', 'business-directory' ); ?></label>
							<select name="city" id="export_city">
								<option value=""><?php esc_html_e( 'All Cities', 'business-directory' ); ?></option>
								<?php foreach ( $cities as $city ) : ?>
									<option value="<?php echo esc_attr( $city ); ?>">
										<?php echo esc_html( $city ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="bd-export-section">
					<h2><?php esc_html_e( 'Export Options', 'business-directory' ); ?></h2>

					<div class="bd-export-options">
						<div class="bd-export-option">
							<label for="export_format"><?php esc_html_e( 'Export Format', 'business-directory' ); ?></label>
							<select name="format" id="export_format">
								<?php foreach ( CSV::get_formats() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, 'standard' ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Choose how much data to include in the export.', 'business-directory' ); ?></p>
						</div>

						<div class="bd-export-option">
							<label for="export_filename"><?php esc_html_e( 'Filename (optional)', 'business-directory' ); ?></label>
							<input type="text" name="filename" id="export_filename" placeholder="businesses-export">
							<p class="description"><?php esc_html_e( 'Leave empty for auto-generated filename with date.', 'business-directory' ); ?></p>
						</div>
					</div>

					<div style="margin-top: 15px;">
						<label style="font-weight: 600; display: block; margin-bottom: 10px;"><?php esc_html_e( 'Include Additional Data', 'business-directory' ); ?></label>
						
						<div class="bd-checkbox-option">
							<input type="checkbox" name="include_reviews" id="include_reviews" value="1">
							<label for="include_reviews"><?php esc_html_e( 'Include review statistics (count & average rating)', 'business-directory' ); ?></label>
						</div>
						
						<div class="bd-checkbox-option">
							<input type="checkbox" name="include_claimed" id="include_claimed" value="1" checked>
							<label for="include_claimed"><?php esc_html_e( 'Include claim status', 'business-directory' ); ?></label>
						</div>
						
						<div class="bd-checkbox-option">
							<input type="checkbox" name="include_id" id="include_id" value="1" checked>
							<label for="include_id"><?php esc_html_e( 'Include WordPress post ID (helpful for re-imports)', 'business-directory' ); ?></label>
						</div>
					</div>

					<div class="bd-format-info" id="format-info-standard">
						<h4><?php esc_html_e( 'Standard Format Includes:', 'business-directory' ); ?></h4>
						<div class="bd-columns-list">
							<code>title</code>
							<code>description</code>
							<code>excerpt</code>
							<code>category</code>
							<code>area</code>
							<code>tags</code>
							<code>address</code>
							<code>city</code>
							<code>state</code>
							<code>zip</code>
							<code>country</code>
							<code>lat</code>
							<code>lng</code>
							<code>phone</code>
							<code>email</code>
							<code>website</code>
							<code>facebook</code>
							<code>instagram</code>
							<code>twitter</code>
							<code>price_level</code>
							<code>hours_*</code>
						</div>
					</div>
				</div>

				<div class="bd-export-preview">
					<h3><?php esc_html_e( 'Export Preview', 'business-directory' ); ?></h3>
					<span class="bd-export-count" id="export-count"><?php echo esc_html( $total_count ); ?></span>
					<span class="bd-export-count-label" id="export-count-label">
						<?php echo esc_html( _n( 'business will be exported', 'businesses will be exported', $total_count, 'business-directory' ) ); ?>
					</span>
				</div>

				<button type="submit" class="bd-export-button" id="export-button" <?php disabled( $total_count, 0 ); ?>>
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Download CSV', 'business-directory' ); ?>
				</button>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var $form = $('#bd-export-form');
			var $count = $('#export-count');
			var $countLabel = $('#export-count-label');
			var $button = $('#export-button');
			var countTimeout;

			// Update count when filters change.
			$form.find('select').on('change', function() {
				clearTimeout(countTimeout);
				$count.text(bdExport.strings.counting);
				
				countTimeout = setTimeout(function() {
					updateCount();
				}, 300);
			});

			function updateCount() {
				$.ajax({
					url: bdExport.ajaxUrl,
					type: 'POST',
					data: {
						action: 'bd_get_export_count',
						nonce: bdExport.nonce,
						status: $('#export_status').val(),
						category: $('#export_category').val(),
						area: $('#export_area').val(),
						city: $('#export_city').val()
					},
					success: function(response) {
						if (response.success) {
							var count = response.data.count;
							$count.text(count);
							$countLabel.text(count === 1 ? bdExport.strings.business + ' will be exported' : bdExport.strings.businesses + ' will be exported');
							$button.prop('disabled', count === 0);
						}
					}
				});
			}

			// Show format info based on selection.
			$('#export_format').on('change', function() {
				// Could expand this to show different info per format.
			});
		});
		</script>
		<?php
	}

	/**
	 * Get unique cities from locations table.
	 *
	 * @return array Array of unique city names.
	 */
	private function get_unique_cities() {
		global $wpdb;

		$table = $wpdb->prefix . 'bd_locations';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table;

		if ( ! $table_exists ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$cities = $wpdb->get_col(
			"SELECT DISTINCT city FROM {$table} WHERE city != '' AND city IS NOT NULL ORDER BY city ASC"
		);

		return $cities ?: array();
	}

	/**
	 * Handle export form submission.
	 */
	public function handle_export() {
		// Verify nonce.
		if ( ! isset( $_POST['bd_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bd_export_nonce'] ) ), 'bd_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed', 'business-directory' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'business-directory' ) );
		}

		// Build export arguments.
		$args = array(
			'status'          => isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'publish',
			'category'        => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'area'            => isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '',
			'city'            => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'format'          => isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'standard',
			'filename'        => isset( $_POST['filename'] ) ? sanitize_text_field( wp_unslash( $_POST['filename'] ) ) : '',
			'include_reviews' => ! empty( $_POST['include_reviews'] ),
			'include_claimed' => ! empty( $_POST['include_claimed'] ),
			'include_id'      => ! empty( $_POST['include_id'] ),
		);

		// Validate status against whitelist.
		$allowed_statuses = CSV::get_allowed_statuses();
		if ( ! in_array( $args['status'], $allowed_statuses, true ) ) {
			$args['status'] = 'publish';
		}

		// Validate format.
		$allowed_formats = CSV::get_allowed_formats();
		if ( ! in_array( $args['format'], $allowed_formats, true ) ) {
			$args['format'] = 'standard';
		}

		// Run the export (outputs CSV and exits).
		CSV::export( $args );
		exit;
	}

	/**
	 * AJAX handler for getting export count.
	 */
	public function ajax_get_export_count() {
		check_ajax_referer( 'bd_export_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'business-directory' ) ) );
		}

		$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'publish';

		// Validate status against whitelist.
		if ( ! in_array( $status, CSV::get_allowed_statuses(), true ) ) {
			$status = 'publish';
		}

		$args = array(
			'status'   => $status,
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'area'     => isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '',
			'city'     => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
		);

		$count = CSV::get_export_count( $args );

		wp_send_json_success( array( 'count' => $count ) );
	}
}
