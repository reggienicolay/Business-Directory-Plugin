<?php
namespace BD\Admin;

/**
 * CSV Importer admin page
 */
class ImporterPage {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_bd_import_csv', array( $this, 'handle_import' ) );
	}

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

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'Import Businesses from CSV', 'business-directory' ); ?></h1>
			
			<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'bd_import_csv', 'bd_import_nonce' ); ?>
				<input type="hidden" name="action" value="bd_import_csv" />
				
				<table class="form-table">
					<tr>
						<th><label for="csv_file"><?php _e( 'CSV File', 'business-directory' ); ?></label></th>
						<td>
							<input type="file" name="csv_file" id="csv_file" accept=".csv" required />
							<p class="description">
								<?php _e( 'Required columns: title, lat, lng', 'business-directory' ); ?><br>
								<?php _e( 'Optional: description, category, area, address, city, phone, website', 'business-directory' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="create_terms"><?php _e( 'Create Terms', 'business-directory' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" name="create_terms" id="create_terms" value="1" checked />
								<?php _e( 'Automatically create categories/areas if they don\'t exist', 'business-directory' ); ?>
							</label>
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Import', 'business-directory' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_import() {
		if ( ! isset( $_POST['bd_import_nonce'] ) || ! wp_verify_nonce( $_POST['bd_import_nonce'], 'bd_import_csv' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_redirect(
				add_query_arg(
					array(
						'page'  => 'bd-import-csv',
						'error' => 'upload',
					),
					admin_url( 'edit.php?post_type=bd_business' )
				)
			);
			exit;
		}

		$file    = $_FILES['csv_file']['tmp_name'];
		$options = array(
			'create_terms' => isset( $_POST['create_terms'] ),
		);

		$results = \BD\Importer\CSV::import( $file, $options );

		if ( is_wp_error( $results ) ) {
			wp_redirect(
				add_query_arg(
					array(
						'page'  => 'bd-import-csv',
						'error' => urlencode( $results->get_error_message() ),
					),
					admin_url( 'edit.php?post_type=bd_business' )
				)
			);
			exit;
		}

		wp_redirect(
			add_query_arg(
				array(
					'page'     => 'bd-import-csv',
					'imported' => $results['imported'],
					'skipped'  => $results['skipped'],
				),
				admin_url( 'edit.php?post_type=bd_business' )
			)
		);
		exit;
	}
}
