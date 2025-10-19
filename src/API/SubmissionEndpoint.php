<?php
namespace BD\API;

class SubmissionEndpoint {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'bd/v1',
			'/submit-business',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_business' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function submit_business( $request ) {
		try {
			$params = $request->get_params();

			// Validate required fields
			if ( empty( $params['title'] ) || empty( $params['description'] ) ) {
				return new \WP_Error(
					'missing_required',
					'Business name and description are required',
					array( 'status' => 400 )
				);
			}

			// Create the business post
			$post_data = array(
				'post_title'   => sanitize_text_field( $params['title'] ),
				'post_content' => wp_kses_post( $params['description'] ),
				'post_type'    => 'bd_business',
				'post_status'  => 'pending', // Requires admin approval
				'post_author'  => get_current_user_id() ?: 0,
			);

			$business_id = wp_insert_post( $post_data );

			if ( is_wp_error( $business_id ) ) {
				return new \WP_Error(
					'creation_failed',
					'Failed to create business listing',
					array( 'status' => 500 )
				);
			}

			// Save category
			if ( ! empty( $params['category'] ) ) {
				wp_set_object_terms( $business_id, intval( $params['category'] ), 'bd_category' );
			}

			// Save area
			if ( ! empty( $params['area'] ) ) {
				wp_set_object_terms( $business_id, intval( $params['area'] ), 'bd_area' );
			}

			// Save price level
			if ( ! empty( $params['price_level'] ) ) {
				update_post_meta( $business_id, 'bd_price_level', sanitize_text_field( $params['price_level'] ) );
			}

			// Save location data
			if ( ! empty( $params['address'] ) ) {
				$location = array(
					'address' => sanitize_text_field( $params['address'] ),
					'city'    => sanitize_text_field( $params['city'] ?? '' ),
					'state'   => sanitize_text_field( $params['state'] ?? '' ),
					'zip'     => sanitize_text_field( $params['zip'] ?? '' ),
				);

				// Try to geocode the address
				require_once plugin_dir_path( __DIR__ ) . 'Search/Geocoder.php';
				$full_address = implode(
					', ',
					array_filter(
						array(
							$location['address'],
							$location['city'],
							$location['state'],
							$location['zip'],
						)
					)
				);

				$geocoded = \BusinessDirectory\Search\Geocoder::geocode( $full_address );
				if ( $geocoded ) {
					$location['lat'] = $geocoded['lat'];
					$location['lng'] = $geocoded['lng'];
				}

				update_post_meta( $business_id, 'bd_location', $location );
			}

			// Save contact info
			$contact = array();
			if ( ! empty( $params['phone'] ) ) {
				$contact['phone'] = sanitize_text_field( $params['phone'] );
			}
			if ( ! empty( $params['website'] ) ) {
				$contact['website'] = esc_url_raw( $params['website'] );
			}
			if ( ! empty( $params['email'] ) ) {
				$contact['email'] = sanitize_email( $params['email'] );
			}
			if ( ! empty( $contact ) ) {
				update_post_meta( $business_id, 'bd_contact', $contact );
			}

			// Save hours
			if ( ! empty( $params['hours'] ) ) {
				$hours_data = is_string( $params['hours'] ) ? json_decode( $params['hours'], true ) : $params['hours'];
				if ( is_array( $hours_data ) ) {
					update_post_meta( $business_id, 'bd_hours', $hours_data );
				}
			}

			// Handle photo uploads
			if ( ! empty( $_FILES['photos'] ) ) {
				$this->handle_photo_uploads( $business_id, $_FILES['photos'] );
			}

			// Handle video uploads
			if ( ! empty( $_FILES['videos'] ) ) {
				$this->handle_video_uploads( $business_id, $_FILES['videos'] );
			}

			// Save submitter info
			if ( ! empty( $params['submitter_name'] ) || ! empty( $params['submitter_email'] ) ) {
				update_post_meta(
					$business_id,
					'bd_submitter',
					array(
						'name'  => sanitize_text_field( $params['submitter_name'] ?? '' ),
						'email' => sanitize_email( $params['submitter_email'] ?? '' ),
					)
				);
			}

			// Send notification email to admin
			$this->send_admin_notification( $business_id, $params );

			return array(
				'success'     => true,
				'message'     => 'Thank you! Your business has been submitted and is pending approval. We\'ll notify you once it\'s live.',
				'business_id' => $business_id,
			);

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'submission_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	private function handle_photo_uploads( $business_id, $files ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$photo_ids = array();

		// Handle multiple files
		if ( is_array( $files['name'] ) ) {
			$file_count = count( $files['name'] );
			for ( $i = 0; $i < $file_count && $i < 10; $i++ ) {
				if ( $files['error'][ $i ] === UPLOAD_ERR_OK ) {
					$file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ],
					);

					$attachment_id = media_handle_sideload( $file, $business_id );
					if ( ! is_wp_error( $attachment_id ) ) {
						$photo_ids[] = $attachment_id;

						// Set first photo as featured image
						if ( $i === 0 ) {
							set_post_thumbnail( $business_id, $attachment_id );
						}
					}
				}
			}
		}

		if ( ! empty( $photo_ids ) ) {
			update_post_meta( $business_id, 'bd_photos', $photo_ids );
		}
	}

	private function handle_video_uploads( $business_id, $files ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$video_ids = array();

		// Handle multiple files
		if ( is_array( $files['name'] ) ) {
			$file_count = count( $files['name'] );
			for ( $i = 0; $i < $file_count && $i < 3; $i++ ) {
				if ( $files['error'][ $i ] === UPLOAD_ERR_OK ) {
					$file = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ],
					);

					$attachment_id = media_handle_sideload( $file, $business_id );
					if ( ! is_wp_error( $attachment_id ) ) {
						$video_ids[] = $attachment_id;
					}
				}
			}
		}

		if ( ! empty( $video_ids ) ) {
			update_post_meta( $business_id, 'bd_videos', $video_ids );
		}
	}

	private function send_admin_notification( $business_id, $params ) {
		$admin_email   = get_option( 'admin_email' );
		$business_name = get_the_title( $business_id );

		$subject = sprintf(
			'[%s] New Business Submission: %s',
			get_bloginfo( 'name' ),
			$business_name
		);

		$message = sprintf(
			"A new business has been submitted and is pending approval.\n\n" .
			"Business Name: %s\n" .
			"Category: %s\n" .
			"Location: %s, %s\n" .
			"Submitted by: %s (%s)\n\n" .
			"Review and approve: %s\n",
			$business_name,
			$params['category'] ?? 'N/A',
			$params['city'] ?? '',
			$params['state'] ?? '',
			$params['submitter_name'] ?? 'Anonymous',
			$params['submitter_email'] ?? 'N/A',
			admin_url( 'post.php?post=' . $business_id . '&action=edit' )
		);

		wp_mail( $admin_email, $subject, $message );
	}
}

// Initialize
new SubmissionEndpoint();
