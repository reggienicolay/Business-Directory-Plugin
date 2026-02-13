<?php
/**
 * Batch CSV Importer
 *
 * Handles AJAX-based batch processing for large CSV imports.
 * Prevents timeouts by processing records in chunks via separate HTTP requests.
 *
 * @package BusinessDirectory
 * @since 1.4.0
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Importer\CSV;

/**
 * Class BatchImporter
 *
 * Manages the batch import process:
 * 1. Upload & parse CSV → store in transient
 * 2. Process N rows per AJAX call
 * 3. Track progress and aggregate results
 */
class BatchImporter {

	/**
	 * Default batch size (rows per AJAX request)
	 */
	const DEFAULT_BATCH_SIZE = 25;

	/**
	 * Transient expiry (1 hour)
	 */
	const TRANSIENT_EXPIRY = HOUR_IN_SECONDS;

	/**
	 * Maximum errors to store (prevents memory issues)
	 */
	const MAX_STORED_ERRORS = 100;

	/**
	 * Initialize AJAX handlers
	 */
	public static function init() {
		add_action( 'wp_ajax_bd_batch_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'wp_ajax_bd_batch_process', array( __CLASS__, 'handle_batch' ) );
		add_action( 'wp_ajax_bd_batch_cleanup', array( __CLASS__, 'handle_cleanup' ) );
	}

	/**
	 * Handle CSV upload and initialization
	 *
	 * Parses CSV, stores data in transient, returns row count
	 */
	public static function handle_upload() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_batch_import', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'business-directory' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'business-directory' ) ) );
		}

		// Validate file upload.
		if ( ! isset( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== $_FILES['csv_file']['error'] ) {
			$error_message = isset( $_FILES['csv_file'] )
				? self::get_upload_error_message( $_FILES['csv_file']['error'] )
				: __( 'No file uploaded.', 'business-directory' );
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		// Validate file type.
		$filename  = sanitize_file_name( $_FILES['csv_file']['name'] );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'csv' !== $extension ) {
			wp_send_json_error( array( 'message' => __( 'Please upload a CSV file.', 'business-directory' ) ) );
		}

		// Get import options from form.
		$options = array(
			'create_terms'    => ! empty( $_POST['create_terms'] ),
			'dry_run'         => ! empty( $_POST['dry_run'] ),
			'import_mode'     => isset( $_POST['import_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['import_mode'] ) ) : 'skip',
			'match_by'        => isset( $_POST['match_by'] ) ? sanitize_text_field( wp_unslash( $_POST['match_by'] ) ) : 'title',
			'download_images' => ! empty( $_POST['download_images'] ),
			'geocode'         => ! empty( $_POST['geocode'] ),
		);

		// Validate options.
		if ( ! in_array( $options['import_mode'], array( 'skip', 'update', 'create' ), true ) ) {
			$options['import_mode'] = 'skip';
		}
		if ( ! in_array( $options['match_by'], array( 'title', 'external_id', 'both' ), true ) ) {
			$options['match_by'] = 'title';
		}

		// Parse CSV file.
		// Note: tmp_name is system-controlled, not user input - use directly.
		$file_path = $_FILES['csv_file']['tmp_name'];
		$parsed    = self::parse_csv( $file_path );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
		}

		// Generate unique import ID.
		$import_id = wp_generate_uuid4();

		// Store parsed data in transient.
		$import_data = array(
			'headers'    => $parsed['headers'],
			'rows'       => $parsed['rows'],
			'options'    => $options,
			'total'      => count( $parsed['rows'] ),
			'processed'  => 0,
			'results'    => array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'errors'   => array(),
			),
			'started_at' => current_time( 'mysql' ),
		);

		set_transient( 'bd_batch_import_' . $import_id, $import_data, self::TRANSIENT_EXPIRY );

		// Calculate recommended batch size based on options.
		$batch_size = self::DEFAULT_BATCH_SIZE;
		if ( $options['geocode'] || $options['download_images'] ) {
			// Slower operations need smaller batches.
			$batch_size = 10;
		}

		wp_send_json_success(
			array(
				'import_id'  => $import_id,
				'total'      => $import_data['total'],
				'batch_size' => $batch_size,
				'message'    => sprintf(
					/* translators: %d is the number of rows found in the CSV */
					__( 'Found %d rows to process.', 'business-directory' ),
					$import_data['total']
				),
			)
		);
	}

	/**
	 * Handle batch processing
	 *
	 * Processes a chunk of rows and returns progress
	 */
	public static function handle_batch() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_batch_import', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'business-directory' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'business-directory' ) ) );
		}

		// Get parameters.
		$import_id  = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';
		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : self::DEFAULT_BATCH_SIZE;

		if ( empty( $import_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import ID.', 'business-directory' ) ) );
		}

		// Retrieve import data.
		$import_data = get_transient( 'bd_batch_import_' . $import_id );

		if ( ! $import_data ) {
			wp_send_json_error(
				array(
					'message' => __( 'Import session expired. Please start over.', 'business-directory' ),
					'expired' => true,
				)
			);
		}

		// Calculate which rows to process.
		$start_index = $import_data['processed'];
		$end_index   = min( $start_index + $batch_size, $import_data['total'] );

		// Process the batch.
		$batch_results = self::process_rows(
			array_slice( $import_data['rows'], $start_index, $batch_size ),
			$import_data['headers'],
			$import_data['options'],
			$start_index + 2 // Row numbers (accounting for header row).
		);

		// Update progress.
		$import_data['processed']            = $end_index;
		$import_data['results']['imported'] += $batch_results['imported'];
		$import_data['results']['updated']  += $batch_results['updated'];
		$import_data['results']['skipped']  += $batch_results['skipped'];

		// Merge errors but cap to prevent memory issues.
		$import_data['results']['errors'] = array_merge(
			$import_data['results']['errors'],
			$batch_results['errors']
		);
		if ( count( $import_data['results']['errors'] ) > self::MAX_STORED_ERRORS ) {
			$import_data['results']['errors'] = array_slice( $import_data['results']['errors'], 0, self::MAX_STORED_ERRORS );
			$import_data['results']['errors_truncated'] = true;
		}

		// Save updated state.
		set_transient( 'bd_batch_import_' . $import_id, $import_data, self::TRANSIENT_EXPIRY );

		// Check if complete.
		$is_complete = $import_data['processed'] >= $import_data['total'];
		$is_dry_run  = ! empty( $import_data['options']['dry_run'] );

		$response = array(
			'processed'  => $import_data['processed'],
			'total'      => $import_data['total'],
			'percentage' => $import_data['total'] > 0 ? round( ( $import_data['processed'] / $import_data['total'] ) * 100 ) : 0,
			'results'    => $import_data['results'],
			'complete'   => $is_complete,
			'dry_run'    => $is_dry_run,
			'batch'      => array(
				'imported' => $batch_results['imported'],
				'updated'  => $batch_results['updated'],
				'skipped'  => $batch_results['skipped'],
				'errors'   => $batch_results['errors'],
			),
		);

		if ( $is_complete ) {
			if ( $is_dry_run ) {
				$response['message'] = sprintf(
					/* translators: %1$d would be imported, %2$d would be updated, %3$d would be skipped */
					__( 'Preview complete! %1$d would be imported, %2$d would be updated, %3$d would be skipped. No changes were made.', 'business-directory' ),
					$import_data['results']['imported'],
					$import_data['results']['updated'],
					$import_data['results']['skipped']
				);
			} else {
				$response['message'] = sprintf(
					/* translators: %1$d imported, %2$d updated, %3$d skipped */
					__( 'Import complete! %1$d imported, %2$d updated, %3$d skipped.', 'business-directory' ),
					$import_data['results']['imported'],
					$import_data['results']['updated'],
					$import_data['results']['skipped']
				);
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Handle cleanup after import
	 */
	public static function handle_cleanup() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bd_batch_import', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'business-directory' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'business-directory' ) ) );
		}

		$import_id = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';

		if ( ! empty( $import_id ) ) {
			delete_transient( 'bd_batch_import_' . $import_id );
		}

		wp_send_json_success( array( 'message' => __( 'Cleanup complete.', 'business-directory' ) ) );
	}

	/**
	 * Parse CSV file into headers and rows
	 *
	 * @param string $file_path Path to CSV file.
	 * @return array|\WP_Error Parsed data or error.
	 */
	private static function parse_csv( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found.', 'business-directory' ) );
		}

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Could not open CSV file.', 'business-directory' ) );
		}

		// Read header row.
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return new \WP_Error( 'invalid_csv', __( 'CSV has no header row.', 'business-directory' ) );
		}

		// Normalize headers.
		$headers = array_map(
			function ( $h ) {
				return strtolower( trim( $h ) );
			},
			$headers
		);

		// Validate required columns.
		if ( ! in_array( 'title', $headers, true ) ) {
			fclose( $handle );
			return new \WP_Error( 'missing_title', __( 'CSV must have a "title" column.', 'business-directory' ) );
		}

		// Read all rows.
		$rows = array();
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			// Skip completely empty rows.
			if ( empty( array_filter( $data ) ) ) {
				continue;
			}

			// Normalize row to match header count.
			if ( count( $data ) !== count( $headers ) ) {
				$data = array_pad( $data, count( $headers ), '' );
				$data = array_slice( $data, 0, count( $headers ) );
			}

			$rows[] = $data;
		}

		fclose( $handle );

		if ( empty( $rows ) ) {
			return new \WP_Error( 'empty_csv', __( 'CSV has no data rows.', 'business-directory' ) );
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Process a batch of rows
	 *
	 * @param array $rows           Rows to process.
	 * @param array $headers        Column headers.
	 * @param array $options        Import options.
	 * @param int   $start_row_num  Starting row number for error messages.
	 * @return array Results.
	 */
	private static function process_rows( $rows, $headers, $options, $start_row_num ) {
		$results = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		$row_number = $start_row_num;

		foreach ( $rows as $row_data ) {
			// Combine headers with data.
			$row = array_combine( $headers, $row_data );

			// Validate required fields.
			if ( empty( $row['title'] ) ) {
				++$results['skipped'];
				$results['errors'][] = sprintf(
					/* translators: %d is the row number */
					__( 'Row %d: Missing required field (title)', 'business-directory' ),
					$row_number
				);
				++$row_number;
				continue;
			}

			// Process via CSV class methods.
			$result = CSV::process_single_row( $row, $options );

			if ( is_wp_error( $result ) ) {
				$results['errors'][] = sprintf(
					/* translators: %1$d is row number, %2$s is error message */
					__( 'Row %1$d: %2$s', 'business-directory' ),
					$row_number,
					$result->get_error_message()
				);
				++$results['skipped'];
			} elseif ( 'skipped' === $result['action'] ) {
				++$results['skipped'];
			} elseif ( 'updated' === $result['action'] ) {
				++$results['updated'];
			} else {
				++$results['imported'];
			}

			++$row_number;
		}

		return $results;
	}

	/**
	 * Get human-readable upload error message
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string Error message.
	 */
	private static function get_upload_error_message( $error_code ) {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'business-directory' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'business-directory' ),
			UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'business-directory' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'business-directory' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Server missing temporary folder.', 'business-directory' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'business-directory' ),
			UPLOAD_ERR_EXTENSION  => __( 'File upload blocked by extension.', 'business-directory' ),
		);

		return isset( $messages[ $error_code ] )
			? $messages[ $error_code ]
			: __( 'Unknown upload error.', 'business-directory' );
	}
}
