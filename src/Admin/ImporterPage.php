<?php

/**
 * Enhanced CSV Importer admin page
 *
 * @package BusinessDirectory
 */


namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\Importer\CSV;

class ImporterPage {


	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_bd_import_csv', array( $this, 'handle_import' ) );
		add_action( 'admin_post_bd_download_sample_csv', array( $this, 'handle_download_sample' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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

		wp_add_inline_style(
			'wp-admin',
			'
			.bd-import-wrap { max-width: 900px; }
			.bd-import-section { 
				background: #fff; 
				border: 1px solid #c3c4c7; 
				padding: 20px; 
				margin: 20px 0;
				border-radius: 4px;
			}
			.bd-import-section h2 { 
				margin-top: 0; 
				padding-bottom: 10px;
				border-bottom: 1px solid #eee;
			}
			.bd-import-options { 
				display: grid; 
				grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
				gap: 20px; 
				margin: 15px 0;
			}
			.bd-import-option { 
				padding: 15px; 
				background: #f9f9f9; 
				border-radius: 4px;
				border: 1px solid #e5e5e5;
			}
			.bd-import-option label { 
				font-weight: 600; 
				display: block; 
				margin-bottom: 8px;
			}
			.bd-import-option p.description { 
				margin: 8px 0 0; 
				font-size: 12px;
				color: #666;
			}
			.bd-import-option select,
			.bd-import-option input[type="file"] {
				width: 100%;
			}
			.bd-columns-reference {
				background: #f0f6fc;
				border: 1px solid #c3d9ed;
				padding: 15px;
				border-radius: 4px;
				margin: 15px 0;
			}
			.bd-columns-reference h4 { margin-top: 0; }
			.bd-columns-list { 
				display: grid; 
				grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
				gap: 5px;
				font-family: monospace;
				font-size: 12px;
			}
			.bd-columns-list code {
				background: #fff;
				padding: 3px 6px;
				border-radius: 3px;
			}
			.bd-required { color: #d63638; font-weight: bold; }
			.bd-results-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
			.bd-results-table th, 
			.bd-results-table td { 
				padding: 10px; 
				text-align: left; 
				border-bottom: 1px solid #eee;
			}
			.bd-results-table th { background: #f5f5f5; }
			.bd-action-skip { color: #666; }
			.bd-action-update { color: #2271b1; }
			.bd-action-create { color: #00a32a; }
			.bd-preview-note {
				background: #fcf9e8;
				border-left: 4px solid #dba617;
				padding: 12px 15px;
				margin: 15px 0;
			}
			.bd-import-stats {
				display: flex;
				gap: 20px;
				flex-wrap: wrap;
				margin: 15px 0;
			}
			.bd-stat {
				padding: 15px 25px;
				background: #f0f0f0;
				border-radius: 4px;
				text-align: center;
			}
			.bd-stat-number {
				font-size: 28px;
				font-weight: 700;
				display: block;
			}
			.bd-stat-label {
				font-size: 12px;
				color: #666;
				text-transform: uppercase;
			}
			.bd-stat-imported .bd-stat-number { color: #00a32a; }
			.bd-stat-updated .bd-stat-number { color: #2271b1; }
			.bd-stat-skipped .bd-stat-number { color: #666; }
			.bd-errors-list {
				background: #fcf0f1;
				border: 1px solid #d63638;
				border-radius: 4px;
				padding: 15px;
				max-height: 200px;
				overflow-y: auto;
			}
			.bd-errors-list li { margin: 5px 0; font-size: 13px; }
			'
		);
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
		$results = $this->get_stored_results();
		?>
		<div class="wrap bd-import-wrap">
			<h1><?php esc_html_e( 'Import Businesses from CSV', 'business-directory' ); ?></h1>

			<?php $this->render_notices(); ?>
			<?php $this->render_results( $results ); ?>

			<div class="bd-import-section">
				<h2><?php esc_html_e( 'Upload CSV File', 'business-directory' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( 'bd_import_csv', 'bd_import_nonce' ); ?>
					<input type="hidden" name="action" value="bd_import_csv" />

					<div class="bd-import-options">
						<!-- File Upload -->
						<div class="bd-import-option">
							<label for="csv_file"><?php esc_html_e( 'CSV File', 'business-directory' ); ?> <span class="bd-required">*</span></label>
							<input type="file" name="csv_file" id="csv_file" accept=".csv" required />
							<p class="description"><?php esc_html_e( 'Select a CSV file to import.', 'business-directory' ); ?></p>
						</div>

						<!-- Import Mode -->
						<div class="bd-import-option">
							<label for="import_mode"><?php esc_html_e( 'Duplicate Handling', 'business-directory' ); ?></label>
							<select name="import_mode" id="import_mode">
								<option value="skip"><?php esc_html_e( 'Skip duplicates', 'business-directory' ); ?></option>
								<option value="update"><?php esc_html_e( 'Update existing records', 'business-directory' ); ?></option>
								<option value="create"><?php esc_html_e( 'Always create new', 'business-directory' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'How to handle businesses that already exist.', 'business-directory' ); ?></p>
						</div>

						<!-- Match By -->
						<div class="bd-import-option">
							<label for="match_by"><?php esc_html_e( 'Match Duplicates By', 'business-directory' ); ?></label>
							<select name="match_by" id="match_by">
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
							<p class="description"><?php esc_html_e( 'Download images from image_url column. May slow import.', 'business-directory' ); ?></p>
						</div>

						<!-- Geocoding -->
						<div class="bd-import-option">
							<label>
								<input type="checkbox" name="geocode" value="1" />
								<?php esc_html_e( 'Auto-fill coordinates from address', 'business-directory' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Use geocoding to get lat/lng when missing. Adds ~1 sec per row.', 'business-directory' ); ?></p>
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

					<?php submit_button( __( 'Import CSV', 'business-directory' ), 'primary', 'submit', true ); ?>
				</form>
			</div>

			<!-- Column Reference -->
			<div class="bd-import-section">
				<h2><?php esc_html_e( 'CSV Column Reference', 'business-directory' ); ?></h2>

				<div class="bd-columns-reference">
					<h4><?php esc_html_e( 'Required Column', 'business-directory' ); ?></h4>
					<div class="bd-columns-list">
						<code class="bd-required">title</code>
					</div>
				</div>

				<div class="bd-columns-reference" style="background: #f6f7f7; border-color: #ddd;">
					<h4><?php esc_html_e( 'Optional Columns', 'business-directory' ); ?></h4>
					<div class="bd-columns-list">
						<code>external_id</code>
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
						<code>linkedin</code>
						<code>youtube</code>
						<code>tiktok</code>
						<code>yelp</code>
						<code>price_level</code>
						<code>hours_monday</code>
						<code>hours_tuesday</code>
						<code>hours_wednesday</code>
						<code>hours_thursday</code>
						<code>hours_friday</code>
						<code>hours_saturday</code>
						<code>hours_sunday</code>
						<code>image_url</code>
						<code>year_established</code>
						<code>owner_name</code>
					</div>
				</div>

				<p><strong><?php esc_html_e( 'Tips:', 'business-directory' ); ?></strong></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'Tags can be comma-separated: "wifi, parking, outdoor seating"', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Hours format: "9:00-17:00" or "9am-5pm" or "Closed"', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Price levels: "$", "$$", "$$$", "$$$$"', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Use external_id for reliable duplicate matching across imports', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Geocoding: If lat/lng are empty, enable "Auto-fill coordinates" to look them up from address', 'business-directory' ); ?></li>
					<li><?php esc_html_e( 'Geocoding results are cached for 1 week to speed up future imports', 'business-directory' ); ?></li>
				</ul>

				<p style="margin-top: 15px;">
					<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=bd_download_sample_csv&_wpnonce=' . wp_create_nonce( 'bd_download_sample' ) ) ); ?>" class="button">
						<?php esc_html_e( 'Download Sample CSV', 'business-directory' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render admin notices
	 */
	private function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Render import results
	 *
	 * @param array|null $results Import results.
	 */
	private function render_results( $results ) {
		if ( ! $results ) {
			return;
		}

		$is_dry_run = ! empty( $results['preview'] );
		?>
		<div class="bd-import-section">
			<h2>
				<?php
				if ( $is_dry_run ) {
					esc_html_e( 'Preview Results (Dry Run)', 'business-directory' );
				} else {
					esc_html_e( 'Import Results', 'business-directory' );
				}
				?>
			</h2>

			<?php if ( $is_dry_run ) : ?>
				<div class="bd-preview-note">
					<strong><?php esc_html_e( 'This was a preview only.', 'business-directory' ); ?></strong>
					<?php esc_html_e( 'No changes were made. Uncheck "Preview only" and import again to apply changes.', 'business-directory' ); ?>
				</div>
			<?php endif; ?>

			<div class="bd-import-stats">
				<div class="bd-stat bd-stat-imported">
					<span class="bd-stat-number"><?php echo absint( $results['imported'] ); ?></span>
					<span class="bd-stat-label"><?php esc_html_e( 'Created', 'business-directory' ); ?></span>
				</div>
				<div class="bd-stat bd-stat-updated">
					<span class="bd-stat-number"><?php echo absint( $results['updated'] ); ?></span>
					<span class="bd-stat-label"><?php esc_html_e( 'Updated', 'business-directory' ); ?></span>
				</div>
				<div class="bd-stat bd-stat-skipped">
					<span class="bd-stat-number"><?php echo absint( $results['skipped'] ); ?></span>
					<span class="bd-stat-label"><?php esc_html_e( 'Skipped', 'business-directory' ); ?></span>
				</div>
			</div>

			<?php if ( $is_dry_run && ! empty( $results['preview'] ) ) : ?>
				<h4><?php esc_html_e( 'Preview Details', 'business-directory' ); ?></h4>
				<table class="bd-results-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Row', 'business-directory' ); ?></th>
							<th><?php esc_html_e( 'Title', 'business-directory' ); ?></th>
							<th><?php esc_html_e( 'Action', 'business-directory' ); ?></th>
							<th><?php esc_html_e( 'Existing ID', 'business-directory' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $results['preview'], 0, 50 ) as $item ) : ?>
							<tr>
								<td><?php echo absint( $item['row'] ); ?></td>
								<td><?php echo esc_html( $item['title'] ); ?></td>
								<td class="bd-action-<?php echo esc_attr( $item['action'] ); ?>">
									<?php
									switch ( $item['action'] ) {
										case 'skip':
											esc_html_e( 'Skip (duplicate)', 'business-directory' );
											break;
										case 'update':
											esc_html_e( 'Update existing', 'business-directory' );
											break;
										case 'create':
											esc_html_e( 'Create new', 'business-directory' );
											break;
									}
									?>
								</td>
								<td>
									<?php if ( $item['existing_id'] ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $item['existing_id'] ) ); ?>">
											#<?php echo absint( $item['existing_id'] ); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $results['preview'] ) > 50 ) : ?>
					<?php // translators: %d is the total number of rows in the preview. ?>
					<p><em><?php printf( esc_html__( 'Showing first 50 of %d rows.', 'business-directory' ), count( $results['preview'] ) ); ?></em></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $results['errors'] ) ) : ?>
				<h4><?php esc_html_e( 'Errors', 'business-directory' ); ?></h4>
				<div class="bd-errors-list">
					<ul>
						<?php foreach ( array_slice( $results['errors'], 0, 20 ) as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( count( $results['errors'] ) > 20 ) : ?>
						<?php // translators: %d is the number of additional errors not shown. ?>
						<p><em><?php printf( esc_html__( '... and %d more errors.', 'business-directory' ), count( $results['errors'] ) - 20 ); ?></em></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php

		// Clear stored results after displaying
		delete_transient( 'bd_import_results' );
	}

	/**
	 * Get stored import results
	 *
	 * @return array|null Results or null.
	 */
	private function get_stored_results() {
		return get_transient( 'bd_import_results' );
	}

	/**
	 * Handle CSV import form submission
	 */
	public function handle_import() {
		// Verify nonce
		if ( ! isset( $_POST['bd_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bd_import_nonce'] ) ), 'bd_import_csv' ) ) {
			wp_die( esc_html__( 'Security check failed', 'business-directory' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'business-directory' ) );
		}

		// Validate file upload
		if ( ! isset( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== $_FILES['csv_file']['error'] ) {
			$this->redirect_with_error( __( 'File upload failed. Please try again.', 'business-directory' ) );
			return;
		}

		// Get options from form
		$options = array(
			'create_terms'    => isset( $_POST['create_terms'] ),
			'dry_run'         => isset( $_POST['dry_run'] ),
			'import_mode'     => isset( $_POST['import_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['import_mode'] ) ) : 'skip',
			'match_by'        => isset( $_POST['match_by'] ) ? sanitize_text_field( wp_unslash( $_POST['match_by'] ) ) : 'title',
			'download_images' => isset( $_POST['download_images'] ),
			'geocode'         => isset( $_POST['geocode'] ),
		);

		// Validate import mode
		if ( ! in_array( $options['import_mode'], array( 'skip', 'update', 'create' ), true ) ) {
			$options['import_mode'] = 'skip';
		}

		// Validate match by
		if ( ! in_array( $options['match_by'], array( 'title', 'external_id', 'both' ), true ) ) {
			$options['match_by'] = 'title';
		}

		// Run the import
		$file    = sanitize_text_field( $_FILES['csv_file']['tmp_name'] );
		$results = CSV::import( $file, $options );

		if ( is_wp_error( $results ) ) {
			$this->redirect_with_error( $results->get_error_message() );
			return;
		}

		// Store results for display
		set_transient( 'bd_import_results', $results, 60 );

		// Redirect back to import page
		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'bd-import-csv' ),
				admin_url( 'edit.php?post_type=bd_business' )
			)
		);
		exit;
	}

	/**
	 * Handle sample CSV download
	 */
	public function handle_download_sample() {
		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bd_download_sample' ) ) {
			wp_die( esc_html__( 'Security check failed', 'business-directory' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'business-directory' ) );
		}

		$csv = CSV::get_sample_csv();

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="business-directory-sample.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $csv;
		exit;
	}

	/**
	 * Redirect with error message
	 *
	 * @param string $message Error message.
	 */
	private function redirect_with_error( $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'bd-import-csv',
					'error' => rawurlencode( $message ),
				),
				admin_url( 'edit.php?post_type=bd_business' )
			)
		);
		exit;
	}
}
