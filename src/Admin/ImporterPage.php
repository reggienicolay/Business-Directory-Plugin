<?php
/**
 * CSV Importer admin page with AJAX Batch Processing
 *
 * Processes large CSV imports in batches to prevent timeout issues.
 * Shows real-time progress with pause/cancel capability.
 *
 * @package BusinessDirectory
 * @since 1.4.0
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImporterPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_bd_download_sample_csv', array( $this, 'handle_download_sample' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Initialize batch importer AJAX handlers.
		BatchImporter::init();
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'bd_business_page_bd-import-csv' !== $hook ) {
			return;
		}

		$plugin_url = plugins_url( '', dirname( __DIR__ ) ) . '/';
		$version    = defined( 'BD_VERSION' ) ? BD_VERSION : '1.4.0';

		// Batch importer styles.
		wp_enqueue_style(
			'bd-admin-importer',
			$plugin_url . 'assets/css/admin-importer.css',
			array(),
			$version
		);

		// Batch importer script.
		wp_enqueue_script(
			'bd-admin-importer',
			$plugin_url . 'assets/js/admin-importer.js',
			array( 'jquery' ),
			$version,
			true
		);

		// Localize script.
		wp_localize_script(
			'bd-admin-importer',
			'bdBatchImport',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_batch_import' ),
				'i18n'    => array(
					'uploading'     => __( 'Uploading and parsing CSV...', 'business-directory' ),
					'uploadError'   => __( 'Upload failed', 'business-directory' ),
					/* translators: %1$d is processed count, %2$d is total rows, %3$d is percentage */
					'processing'    => __( 'Processing %1$d of %2$d rows (%3$d%)', 'business-directory' ),
					/* translators: %d is the row number */
					'batchComplete' => __( 'Processed through row %d', 'business-directory' ),
					'imported'      => __( 'imported', 'business-directory' ),
					'updated'       => __( 'updated', 'business-directory' ),
					'skipped'       => __( 'skipped', 'business-directory' ),
					'importedLabel' => __( 'Imported', 'business-directory' ),
					'updatedLabel'  => __( 'Updated', 'business-directory' ),
					'skippedLabel'  => __( 'Skipped', 'business-directory' ),
					'batchError'    => __( 'Batch processing error', 'business-directory' ),
					'retrying'      => __( 'Connection issue, retrying...', 'business-directory' ),
					'paused'        => __( 'Import paused.', 'business-directory' ),
					'resuming'      => __( 'Resuming import...', 'business-directory' ),
					'cancelled'     => __( 'Import cancelled.', 'business-directory' ),
					'pause'         => __( 'Pause', 'business-directory' ),
					'resume'        => __( 'Resume', 'business-directory' ),
					'confirmCancel' => __( 'Are you sure you want to cancel? Progress will be lost.', 'business-directory' ),
					'leaveWarning'  => __( 'Import is in progress. Are you sure you want to leave?', 'business-directory' ),
					/* translators: %d is the number of additional errors */
					'moreErrors'    => __( '... and %d more errors.', 'business-directory' ),
					'previewNote'   => __( 'Preview Mode - No changes were made', 'business-directory' ),
					'wouldImport'   => __( 'Would Import', 'business-directory' ),
					'wouldUpdate'   => __( 'Would Update', 'business-directory' ),
					'wouldSkip'     => __( 'Would Skip', 'business-directory' ),
				),
			)
		);

		// Inline styles for page layout.
		wp_add_inline_style( 'bd-admin-importer', $this->get_inline_styles() );
	}

	/**
	 * Add submenu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Import CSV', 'business-directory' ),
			__( 'Import CSV', 'business-directory' ),
			'manage_options',
			'bd-import-csv',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the import page
	 */
	public function render_page() {
		?>
		<div class="wrap bd-import-wrap">
			<h1><?php esc_html_e( 'Import Businesses from CSV', 'business-directory' ); ?></h1>

			<?php $this->render_notices(); ?>

			<!-- Batch Import Form -->
			<div class="bd-import-section">
				<h2><?php esc_html_e( 'Upload CSV File', 'business-directory' ); ?></h2>

				<div class="bd-import-mode-info" style="background: #f0f6fc; border: 1px solid #c3d9ed; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
					<strong><?php esc_html_e( 'Batch Processing Enabled', 'business-directory' ); ?></strong>
					<p style="margin: 8px 0 0; font-size: 13px; color: #646970;">
						<?php esc_html_e( 'Large imports are processed in batches to prevent timeouts. You\'ll see real-time progress and can pause or cancel at any time.', 'business-directory' ); ?>
					</p>
				</div>

				<form id="bd-batch-import-form" method="post" enctype="multipart/form-data">
					<div class="bd-import-options" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin: 15px 0;">
						
						<!-- File Upload -->
						<div class="bd-import-option">
							<label for="csv_file"><?php esc_html_e( 'CSV File', 'business-directory' ); ?> <span style="color: #d63638; font-weight: bold;">*</span></label>
							<input type="file" name="csv_file" id="csv_file" accept=".csv" required />
							<p class="description"><?php esc_html_e( 'Select a CSV file to import.', 'business-directory' ); ?></p>
						</div>

						<!-- Import Mode -->
						<div class="bd-import-option">
							<label for="import_mode"><?php esc_html_e( 'Duplicate Handling', 'business-directory' ); ?></label>
							<select name="import_mode" id="import_mode" style="width: 100%;">
								<option value="skip"><?php esc_html_e( 'Skip duplicates', 'business-directory' ); ?></option>
								<option value="update"><?php esc_html_e( 'Update existing records', 'business-directory' ); ?></option>
								<option value="create"><?php esc_html_e( 'Always create new', 'business-directory' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'How to handle businesses that already exist.', 'business-directory' ); ?></p>
						</div>

						<!-- Match By -->
						<div class="bd-import-option">
							<label for="match_by"><?php esc_html_e( 'Match Duplicates By', 'business-directory' ); ?></label>
							<select name="match_by" id="match_by" style="width: 100%;">
								<option value="title"><?php esc_html_e( 'Business title', 'business-directory' ); ?></option>
								<option value="external_id"><?php esc_html_e( 'External ID', 'business-directory' ); ?></option>
								<option value="both"><?php esc_html_e( 'Both (ID first, then title)', 'business-directory' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'How to identify duplicate businesses.', 'business-directory' ); ?></p>
						</div>

						<!-- Create Terms -->
						<div class="bd-import-option">
							<label>
								<input type="checkbox" name="create_terms" value="1" checked />
								<?php esc_html_e( 'Create missing terms', 'business-directory' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Automatically create categories, areas, and tags if they don\'t exist.', 'business-directory' ); ?></p>
						</div>

						<!-- Download Images -->
						<div class="bd-import-option">
							<label>
								<input type="checkbox" name="download_images" value="1" />
								<?php esc_html_e( 'Download featured images', 'business-directory' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Download images from image_url column. Uses smaller batches.', 'business-directory' ); ?></p>
						</div>

						<!-- Geocoding -->
						<div class="bd-import-option">
							<label>
								<input type="checkbox" name="geocode" value="1" />
								<?php esc_html_e( 'Auto-fill coordinates from address', 'business-directory' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Use geocoding to get lat/lng when missing. Uses smaller batches.', 'business-directory' ); ?></p>
						</div>

						<!-- Dry Run -->
						<div class="bd-import-option">
							<label>
								<input type="checkbox" name="dry_run" value="1" />
								<?php esc_html_e( 'Preview only (dry run)', 'business-directory' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'See what will happen without making changes.', 'business-directory' ); ?></p>
						</div>
					</div>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Start Import', 'business-directory' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Progress Section (hidden by default) -->
			<div id="bd-import-progress" class="bd-import-progress bd-hidden">
				<h3><?php esc_html_e( 'Import Progress', 'business-directory' ); ?></h3>
				
				<div class="bd-progress-wrapper">
					<div id="bd-progress-bar" class="bd-progress-bar bd-processing" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
				</div>
				
				<p id="bd-progress-text" class="bd-progress-text"><?php esc_html_e( 'Starting...', 'business-directory' ); ?></p>
				
				<div class="bd-import-controls">
					<button type="button" id="bd-pause-import" class="button"><?php esc_html_e( 'Pause', 'business-directory' ); ?></button>
					<button type="button" id="bd-cancel-import" class="button"><?php esc_html_e( 'Cancel', 'business-directory' ); ?></button>
				</div>
				
				<div id="bd-status-log" class="bd-status-log"></div>
			</div>

			<!-- Results Section (hidden by default) -->
			<div id="bd-import-results" class="bd-hidden">
				<h3><?php esc_html_e( 'Import Results', 'business-directory' ); ?></h3>
				
				<div class="bd-import-stats">
					<div class="bd-stat bd-stat-imported">
						<span class="bd-stat-number">0</span>
						<span class="bd-stat-label"><?php esc_html_e( 'Imported', 'business-directory' ); ?></span>
					</div>
					<div class="bd-stat bd-stat-updated">
						<span class="bd-stat-number">0</span>
						<span class="bd-stat-label"><?php esc_html_e( 'Updated', 'business-directory' ); ?></span>
					</div>
					<div class="bd-stat bd-stat-skipped">
						<span class="bd-stat-number">0</span>
						<span class="bd-stat-label"><?php esc_html_e( 'Skipped', 'business-directory' ); ?></span>
					</div>
				</div>
				
				<div class="bd-errors-list bd-hidden"></div>
			</div>

			<!-- Column Reference -->
			<div class="bd-import-section" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0; border-radius: 4px;">
				<h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;"><?php esc_html_e( 'CSV Column Reference', 'business-directory' ); ?></h2>

				<div style="background: #f0f6fc; border: 1px solid #c3d9ed; padding: 15px; border-radius: 4px; margin: 15px 0;">
					<h4 style="margin-top: 0;"><?php esc_html_e( 'Required Column', 'business-directory' ); ?></h4>
					<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 5px; font-family: monospace; font-size: 12px;">
						<code style="background: #fff; padding: 3px 6px; border-radius: 3px; color: #d63638; font-weight: bold;">title</code>
					</div>
				</div>

				<div style="background: #f6f7f7; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin: 15px 0;">
					<h4 style="margin-top: 0;"><?php esc_html_e( 'Optional Columns', 'business-directory' ); ?></h4>
					<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 5px; font-family: monospace; font-size: 12px;">
						<?php
						$columns = array(
							'external_id',
							'description',
							'excerpt',
							'category',
							'area',
							'tags',
							'address',
							'city',
							'state',
							'zip',
							'country',
							'lat',
							'lng',
							'phone',
							'email',
							'website',
							'facebook',
							'instagram',
							'twitter',
							'linkedin',
							'youtube',
							'tiktok',
							'yelp',
							'price_level',
							'hours_monday',
							'hours_tuesday',
							'hours_wednesday',
							'hours_thursday',
							'hours_friday',
							'hours_saturday',
							'hours_sunday',
							'image_url',
							'year_established',
							'owner_name',
						);
						foreach ( $columns as $col ) :
							?>
							<code style="background: #fff; padding: 3px 6px; border-radius: 3px;"><?php echo esc_html( $col ); ?></code>
						<?php endforeach; ?>
					</div>
				</div>

				<p><strong><?php esc_html_e( 'Tips:', 'business-directory' ); ?></strong></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'Tags can be comma-separated: "wifi, parking, outdoor seating"', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Hours format: "9:00-17:00" or "9am-5pm" or "Closed"', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Price levels: "$", "$$", "$$$", "$$$$"', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Use external_id for reliable duplicate matching across imports', 'business-directory' ); ?></li>
				</ul>

				<p style="margin-top: 15px;">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bd_download_sample_csv' ), 'bd_download_sample' ) ); ?>" class="button">
						<?php esc_html_e( 'Download Sample CSV', 'business-directory' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render notices
	 */
	private function render_notices() {
		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Handle sample CSV download
	 */
	public function handle_download_sample() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bd_download_sample' ) ) {
			wp_die( esc_html__( 'Security check failed', 'business-directory' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'business-directory' ) );
		}

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="business-directory-sample.csv"' );

		$output = fopen( 'php://output', 'w' );

		fputcsv(
			$output,
			array(
				'title',
				'description',
				'category',
				'area',
				'tags',
				'address',
				'city',
				'state',
				'zip',
				'phone',
				'email',
				'website',
				'facebook',
				'instagram',
				'price_level',
				'hours_monday',
				'hours_tuesday',
				'hours_wednesday',
				'hours_thursday',
				'hours_friday',
				'hours_saturday',
				'hours_sunday',
				'external_id',
			)
		);

		fputcsv(
			$output,
			array(
				'Sample Coffee Shop',
				'A cozy neighborhood coffee shop with locally roasted beans.',
				'Restaurants',
				'Downtown',
				'coffee, wifi, outdoor seating',
				'123 Main Street',
				'Livermore',
				'CA',
				'94550',
				'(925) 555-0100',
				'hello@samplecoffee.com',
				'https://samplecoffee.com',
				'https://facebook.com/samplecoffee',
				'@samplecoffee',
				'$$',
				'7:00-18:00',
				'7:00-18:00',
				'7:00-18:00',
				'7:00-18:00',
				'7:00-20:00',
				'8:00-20:00',
				'8:00-16:00',
				'sample-001',
			)
		);

		fclose( $output );
		exit;
	}

	/**
	 * Get inline styles for the page
	 *
	 * @return string CSS styles.
	 */
	private function get_inline_styles() {
		return '
			.bd-import-wrap { max-width: 900px; }
			.bd-import-section h2 { 
				margin-top: 0; 
				padding-bottom: 10px;
				border-bottom: 1px solid #eee;
			}
		';
	}
}
