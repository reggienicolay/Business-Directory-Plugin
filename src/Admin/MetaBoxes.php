<?php
namespace BD\Admin;

/**
 * Business metaboxes for custom fields
 */
class MetaBoxes {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_bd_business', array( $this, 'save_metabox' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts for media library
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		global $post_type;

		if ( 'bd_business' !== $post_type ) {
			return;
		}

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Register metaboxes
	 */
	public function add_metaboxes() {
		add_meta_box(
			'bd_business_details',
			__( 'Business Details', 'business-directory' ),
			array( $this, 'render_details_metabox' ),
			'bd_business',
			'normal',
			'high'
		);

		add_meta_box(
			'bd_business_photos',
			__( 'Photo Gallery', 'business-directory' ),
			array( $this, 'render_photos_metabox' ),
			'bd_business',
			'normal',
			'high'
		);

		add_meta_box(
			'bd_business_location',
			__( 'Location', 'business-directory' ),
			array( $this, 'render_location_metabox' ),
			'bd_business',
			'side',
			'default'
		);
	}

	/**
	 * Render business details metabox
	 */
	public function render_details_metabox( $post ) {
		wp_nonce_field( 'bd_business_details', 'bd_business_details_nonce' );

		// Read from bd_contact meta field.
		$contact = get_post_meta( $post->ID, 'bd_contact', true );
		if ( ! is_array( $contact ) ) {
			$contact = array();
		}

		$phone   = $contact['phone'] ?? '';
		$website = $contact['website'] ?? '';
		$email   = $contact['email'] ?? '';

		$price_level = get_post_meta( $post->ID, 'bd_price_level', true );
		$hours       = get_post_meta( $post->ID, 'bd_hours', true );
		$social      = get_post_meta( $post->ID, 'bd_social', true );

		if ( ! is_array( $hours ) ) {
			$hours = array();
		}
		if ( ! is_array( $social ) ) {
			$social = array();
		}

		?>
		<style>
			.bd-metabox-field { margin-bottom: 20px; }
			.bd-metabox-field label { display: block; font-weight: 600; margin-bottom: 5px; }
			.bd-metabox-field input[type="text"],
			.bd-metabox-field input[type="url"],
			.bd-metabox-field input[type="email"] { width: 100%; }
			.bd-hours-grid { display: grid; grid-template-columns: 120px 1fr 1fr; gap: 10px; margin-top: 10px; }
			.bd-hours-grid label { font-weight: normal; }
			.bd-social-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
		</style>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Phone Number', 'business-directory' ); ?></label>
			<input type="text" name="bd_phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="(555) 123-4567" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Website', 'business-directory' ); ?></label>
			<input type="url" name="bd_website" value="<?php echo esc_url( $website ); ?>" placeholder="https://example.com" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Email', 'business-directory' ); ?></label>
			<input type="email" name="bd_email" value="<?php echo esc_attr( $email ); ?>" placeholder="contact@example.com" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Price Level', 'business-directory' ); ?></label>
			<select name="bd_price_level">
				<option value=""><?php esc_html_e( 'Select...', 'business-directory' ); ?></option>
				<option value="$" <?php selected( $price_level, '$' ); ?>>$ (Budget)</option>
				<option value="$$" <?php selected( $price_level, '$$' ); ?>>$$ (Moderate)</option>
				<option value="$$$" <?php selected( $price_level, '$$$' ); ?>>$$$ (Expensive)</option>
				<option value="$$$$" <?php selected( $price_level, '$$$$' ); ?>>$$$$ (Luxury)</option>
			</select>
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Hours of Operation', 'business-directory' ); ?></label>
			<div class="bd-hours-grid">
				<strong><?php esc_html_e( 'Day', 'business-directory' ); ?></strong>
				<strong><?php esc_html_e( 'Open', 'business-directory' ); ?></strong>
				<strong><?php esc_html_e( 'Close', 'business-directory' ); ?></strong>
				<?php
				$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
				foreach ( $days as $day ) {
					$open  = isset( $hours[ $day ]['open'] ) ? $hours[ $day ]['open'] : '';
					$close = isset( $hours[ $day ]['close'] ) ? $hours[ $day ]['close'] : '';
					?>
					<label><?php echo esc_html( ucfirst( $day ) ); ?></label>
					<input type="time" name="bd_hours[<?php echo esc_attr( $day ); ?>][open]" value="<?php echo esc_attr( $open ); ?>" />
					<input type="time" name="bd_hours[<?php echo esc_attr( $day ); ?>][close]" value="<?php echo esc_attr( $close ); ?>" />
					<?php
				}
				?>
			</div>
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Social Media', 'business-directory' ); ?></label>
			<div class="bd-social-grid">
				<div>
					<label>Facebook</label>
					<input type="url" name="bd_social[facebook]" value="<?php echo esc_url( $social['facebook'] ?? '' ); ?>" placeholder="https://facebook.com/..." />
				</div>
				<div>
					<label>Instagram</label>
					<input type="url" name="bd_social[instagram]" value="<?php echo esc_url( $social['instagram'] ?? '' ); ?>" placeholder="https://instagram.com/..." />
				</div>
				<div>
					<label>Twitter/X</label>
					<input type="url" name="bd_social[twitter]" value="<?php echo esc_url( $social['twitter'] ?? '' ); ?>" placeholder="https://x.com/..." />
				</div>
				<div>
					<label>LinkedIn</label>
					<input type="url" name="bd_social[linkedin]" value="<?php echo esc_url( $social['linkedin'] ?? '' ); ?>" placeholder="https://linkedin.com/..." />
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render photo gallery metabox
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_photos_metabox( $post ) {
		// Get existing photos.
		$photo_ids   = get_post_meta( $post->ID, 'bd_photos', true );
		$featured_id = get_post_thumbnail_id( $post->ID );

		if ( ! is_array( $photo_ids ) ) {
			$photo_ids = array();
		}

		// Combine featured image with gallery (featured first).
		$all_photo_ids = array();
		if ( $featured_id ) {
			$all_photo_ids[] = $featured_id;
		}
		foreach ( $photo_ids as $photo_id ) {
			if ( $photo_id && ! in_array( $photo_id, $all_photo_ids, true ) ) {
				$all_photo_ids[] = $photo_id;
			}
		}

		?>
		<style>
			.bd-photo-gallery-wrap {
				padding: 10px 0;
			}
			.bd-photo-gallery-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
				gap: 12px;
				margin-bottom: 15px;
			}
			.bd-photo-gallery-item {
				position: relative;
				aspect-ratio: 1;
				border-radius: 8px;
				overflow: hidden;
				background: #f0f0f1;
				cursor: move;
				border: 2px solid transparent;
				transition: border-color 0.2s, transform 0.2s;
			}
			.bd-photo-gallery-item:hover {
				border-color: #2271b1;
			}
			.bd-photo-gallery-item.ui-sortable-helper {
				transform: scale(1.05);
				box-shadow: 0 4px 12px rgba(0,0,0,0.15);
			}
			.bd-photo-gallery-item.ui-sortable-placeholder {
				background: #e0e0e0;
				border: 2px dashed #999;
				visibility: visible !important;
			}
			.bd-photo-gallery-item img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			.bd-photo-gallery-item .bd-photo-remove {
				position: absolute;
				top: 4px;
				right: 4px;
				width: 24px;
				height: 24px;
				background: rgba(0,0,0,0.7);
				border: none;
				border-radius: 50%;
				color: #fff;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: center;
				opacity: 0;
				transition: opacity 0.2s;
			}
			.bd-photo-gallery-item:hover .bd-photo-remove {
				opacity: 1;
			}
			.bd-photo-gallery-item .bd-photo-remove:hover {
				background: #d63638;
			}
			.bd-photo-gallery-item .bd-photo-badge {
				position: absolute;
				bottom: 4px;
				left: 4px;
				background: #2271b1;
				color: #fff;
				font-size: 10px;
				font-weight: 600;
				padding: 2px 6px;
				border-radius: 3px;
				text-transform: uppercase;
			}
			.bd-photo-add-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 16px;
				background: #2271b1;
				color: #fff;
				border: none;
				border-radius: 4px;
				cursor: pointer;
				font-size: 13px;
			}
			.bd-photo-add-btn:hover {
				background: #135e96;
			}
			.bd-photo-gallery-help {
				color: #646970;
				font-size: 12px;
				margin-top: 10px;
			}
			.bd-photo-gallery-empty {
				text-align: center;
				padding: 40px 20px;
				background: #f6f7f7;
				border-radius: 8px;
				color: #646970;
				margin-bottom: 15px;
			}
			.bd-photo-gallery-empty .dashicons {
				font-size: 48px;
				width: 48px;
				height: 48px;
				color: #c3c4c7;
				margin-bottom: 10px;
			}
		</style>

		<div class="bd-photo-gallery-wrap">
			<div class="bd-photo-gallery-grid" id="bd-photo-gallery-grid">
				<?php if ( empty( $all_photo_ids ) ) : ?>
					<div class="bd-photo-gallery-empty" id="bd-photo-empty">
						<span class="dashicons dashicons-format-gallery"></span>
						<p><?php esc_html_e( 'No photos yet. Click "Add Photos" to upload.', 'business-directory' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $all_photo_ids as $index => $photo_id ) : ?>
						<?php
						$photo_url = wp_get_attachment_image_url( $photo_id, 'medium' );
						if ( ! $photo_url ) {
							continue;
						}
						?>
						<div class="bd-photo-gallery-item" data-id="<?php echo esc_attr( $photo_id ); ?>">
							<img src="<?php echo esc_url( $photo_url ); ?>" alt="">
							<input type="hidden" name="bd_gallery_photos[]" value="<?php echo esc_attr( $photo_id ); ?>">
							<?php if ( 0 === $index ) : ?>
								<span class="bd-photo-badge"><?php esc_html_e( 'Featured', 'business-directory' ); ?></span>
							<?php endif; ?>
							<button type="button" class="bd-photo-remove" title="<?php esc_attr_e( 'Remove photo', 'business-directory' ); ?>">
								<span class="dashicons dashicons-no-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
							</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<button type="button" class="bd-photo-add-btn" id="bd-photo-add-btn">
				<span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span>
				<?php esc_html_e( 'Add Photos', 'business-directory' ); ?>
			</button>

			<p class="bd-photo-gallery-help">
				<?php esc_html_e( 'Drag to reorder. The first photo will be set as the Featured Image. Maximum 10 photos.', 'business-directory' ); ?>
			</p>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var mediaFrame;
			var $grid = $('#bd-photo-gallery-grid');
			var $empty = $('#bd-photo-empty');
			var maxPhotos = 10;

			// Initialize sortable
			function initSortable() {
				$grid.sortable({
					items: '.bd-photo-gallery-item',
					cursor: 'move',
					opacity: 0.7,
					placeholder: 'bd-photo-gallery-item ui-sortable-placeholder',
					update: function() {
						updateBadges();
					}
				});
			}

			// Update featured badge (first item gets it)
			function updateBadges() {
				$grid.find('.bd-photo-badge').remove();
				var $first = $grid.find('.bd-photo-gallery-item').first();
				if ($first.length) {
					$first.append('<span class="bd-photo-badge"><?php echo esc_js( __( 'Featured', 'business-directory' ) ); ?></span>');
				}
			}

			// Check if we should show empty state
			function checkEmpty() {
				var count = $grid.find('.bd-photo-gallery-item').length;
				if (count === 0) {
					if (!$empty.length) {
						$grid.html('<div class="bd-photo-gallery-empty" id="bd-photo-empty"><span class="dashicons dashicons-format-gallery"></span><p><?php echo esc_js( __( 'No photos yet. Click "Add Photos" to upload.', 'business-directory' ) ); ?></p></div>');
						$empty = $('#bd-photo-empty');
					}
				} else {
					$empty.remove();
				}
			}

			// Add photo button click
			$('#bd-photo-add-btn').on('click', function(e) {
				e.preventDefault();

				var currentCount = $grid.find('.bd-photo-gallery-item').length;
				if (currentCount >= maxPhotos) {
					alert('<?php echo esc_js( __( 'Maximum 10 photos allowed.', 'business-directory' ) ); ?>');
					return;
				}

				if (mediaFrame) {
					mediaFrame.open();
					return;
				}

				mediaFrame = wp.media({
					title: '<?php echo esc_js( __( 'Select Business Photos', 'business-directory' ) ); ?>',
					button: {
						text: '<?php echo esc_js( __( 'Add to Gallery', 'business-directory' ) ); ?>'
					},
					multiple: true,
					library: {
						type: 'image'
					}
				});

				mediaFrame.on('select', function() {
					var selection = mediaFrame.state().get('selection');
					var currentCount = $grid.find('.bd-photo-gallery-item').length;

					// Remove empty state if present
					$empty.remove();

					selection.each(function(attachment) {
						if (currentCount >= maxPhotos) {
							return;
						}

						var id = attachment.id;
						var url = attachment.attributes.sizes.medium ? 
							attachment.attributes.sizes.medium.url : 
							attachment.attributes.url;

						// Check if already exists
						if ($grid.find('[data-id="' + id + '"]').length) {
							return;
						}

						var isFirst = currentCount === 0;
						var html = '<div class="bd-photo-gallery-item" data-id="' + id + '">' +
							'<img src="' + url + '" alt="">' +
							'<input type="hidden" name="bd_gallery_photos[]" value="' + id + '">' +
							(isFirst ? '<span class="bd-photo-badge"><?php echo esc_js( __( 'Featured', 'business-directory' ) ); ?></span>' : '') +
							'<button type="button" class="bd-photo-remove" title="<?php echo esc_js( __( 'Remove photo', 'business-directory' ) ); ?>">' +
							'<span class="dashicons dashicons-no-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>' +
							'</button>' +
							'</div>';

						$grid.append(html);
						currentCount++;
					});

					updateBadges();
					initSortable();
				});

				mediaFrame.open();
			});

			// Remove photo
			$grid.on('click', '.bd-photo-remove', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).closest('.bd-photo-gallery-item').remove();
				updateBadges();
				checkEmpty();
			});

			// Initialize
			if ($grid.find('.bd-photo-gallery-item').length) {
				initSortable();
			}
		});
		</script>
		<?php
	}

	/**
	 * Render location metabox
	 */
	public function render_location_metabox( $post ) {
		// Read from bd_location meta field instead of table.
		$location = get_post_meta( $post->ID, 'bd_location', true );

		if ( ! is_array( $location ) ) {
			$location = array();
		}

		$lat         = $location['lat'] ?? '';
		$lng         = $location['lng'] ?? '';
		$address     = $location['address'] ?? '';
		$city        = $location['city'] ?? '';
		$state       = $location['state'] ?? '';
		$postal_code = $location['zip'] ?? '';

		?>
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Address', 'business-directory' ); ?></label>
			<input type="text" name="bd_address" id="bd_address" value="<?php echo esc_attr( $address ); ?>" style="width:100%;" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'City', 'business-directory' ); ?></label>
			<input type="text" name="bd_city" id="bd_city" value="<?php echo esc_attr( $city ); ?>" style="width:100%;" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'State', 'business-directory' ); ?></label>
			<input type="text" name="bd_state" id="bd_state" value="<?php echo esc_attr( $state ); ?>" style="width:100%;" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Postal Code', 'business-directory' ); ?></label>
			<input type="text" name="bd_postal_code" id="bd_postal_code" value="<?php echo esc_attr( $postal_code ); ?>" style="width:100%;" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Latitude', 'business-directory' ); ?></label>
			<input type="text" name="bd_lat" id="bd_lat" value="<?php echo esc_attr( $lat ); ?>" style="width:100%;" />
		</div>
		
		<div class="bd-metabox-field">
			<label><?php esc_html_e( 'Longitude', 'business-directory' ); ?></label>
			<input type="text" name="bd_lng" id="bd_lng" value="<?php echo esc_attr( $lng ); ?>" style="width:100%;" />
		</div>
		
		<div id="bd-map" style="height: 300px; margin-top: 10px; border: 1px solid #ddd;"></div>
		<p class="description"><?php esc_html_e( 'Click on the map to set location, or enter coordinates manually.', 'business-directory' ); ?></p>
		<?php
	}

	/**
	 * Save metabox data
	 */
	public function save_metabox( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['bd_business_details_nonce'] ) || ! wp_verify_nonce( $_POST['bd_business_details_nonce'], 'bd_business_details' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save contact data in the SAME format as frontend submissions.
		$contact = array(
			'phone'   => isset( $_POST['bd_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_phone'] ) ) : '',
			'website' => isset( $_POST['bd_website'] ) ? esc_url_raw( wp_unslash( $_POST['bd_website'] ) ) : '',
			'email'   => isset( $_POST['bd_email'] ) ? sanitize_email( wp_unslash( $_POST['bd_email'] ) ) : '',
		);
		update_post_meta( $post_id, 'bd_contact', $contact );

		// Save location data in the SAME format as frontend submissions.
		$location = array(
			'address' => isset( $_POST['bd_address'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_address'] ) ) : '',
			'city'    => isset( $_POST['bd_city'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_city'] ) ) : '',
			'state'   => isset( $_POST['bd_state'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_state'] ) ) : '',
			'zip'     => isset( $_POST['bd_postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_postal_code'] ) ) : '',
			'lat'     => isset( $_POST['bd_lat'] ) ? floatval( $_POST['bd_lat'] ) : '',
			'lng'     => isset( $_POST['bd_lng'] ) ? floatval( $_POST['bd_lng'] ) : '',
		);
		update_post_meta( $post_id, 'bd_location', $location );

		// Save other fields.
		if ( isset( $_POST['bd_price_level'] ) ) {
			update_post_meta( $post_id, 'bd_price_level', sanitize_text_field( wp_unslash( $_POST['bd_price_level'] ) ) );
		}
		if ( isset( $_POST['bd_hours'] ) ) {
			// Sanitize hours array.
			$hours = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_hours = wp_unslash( $_POST['bd_hours'] );
			if ( is_array( $raw_hours ) ) {
				foreach ( $raw_hours as $day => $times ) {
					$hours[ sanitize_key( $day ) ] = array(
						'open'  => isset( $times['open'] ) ? sanitize_text_field( $times['open'] ) : '',
						'close' => isset( $times['close'] ) ? sanitize_text_field( $times['close'] ) : '',
					);
				}
			}
			update_post_meta( $post_id, 'bd_hours', $hours );
		}
		if ( isset( $_POST['bd_social'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$social = array_map( 'esc_url_raw', wp_unslash( $_POST['bd_social'] ) );
			update_post_meta( $post_id, 'bd_social', $social );
		}

		// Save photo gallery.
		if ( isset( $_POST['bd_gallery_photos'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$photo_ids = array_map( 'absint', wp_unslash( $_POST['bd_gallery_photos'] ) );
			$photo_ids = array_filter( $photo_ids ); // Remove zeros.
			$photo_ids = array_slice( $photo_ids, 0, 10 ); // Max 10 photos.

			if ( ! empty( $photo_ids ) ) {
				// First photo becomes featured image.
				set_post_thumbnail( $post_id, $photo_ids[0] );

				// Store all photos (excluding featured) in bd_photos.
				$gallery_photos = array_slice( $photo_ids, 1 );
				update_post_meta( $post_id, 'bd_photos', $gallery_photos );
			} else {
				// No photos - remove featured image and clear gallery.
				delete_post_thumbnail( $post_id );
				delete_post_meta( $post_id, 'bd_photos' );
			}
		}
	}
}
