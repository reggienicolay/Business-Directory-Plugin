<?php
/**
 * Venue Syncer
 *
 * Automatically creates TEC Venues from Business Directory entries.
 * Handles both auto-sync on publish and bulk sync via admin.
 *
 * @package BusinessDirectory
 * @version 1.0.1
 */

namespace BD\Integrations\EventsCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VenueSyncer
 *
 * Syncs Business Directory entries to The Events Calendar venues.
 */
class VenueSyncer {

	/**
	 * Meta key for storing venue ID on business
	 */
	const VENUE_META_KEY = '_bd_synced_venue_id';

	/**
	 * Meta key for storing business ID on venue
	 */
	const BUSINESS_META_KEY = 'bd_linked_business';

	/**
	 * Meta key to mark venue as auto-created
	 */
	const AUTO_CREATED_KEY = '_bd_auto_created_venue';

	/**
	 * Initialize the syncer
	 */
	public static function init() {
		// Auto-create venue when business is published
		add_action( 'transition_post_status', array( __CLASS__, 'on_business_status_change' ), 10, 3 );

		// Update venue when business is updated
		add_action( 'save_post_bd_business', array( __CLASS__, 'on_business_save' ), 20, 3 );

		// Delete venue link when business is deleted
		add_action( 'before_delete_post', array( __CLASS__, 'on_business_delete' ) );

		// Add admin sync tools
		add_action( 'bd_settings_after_pages', array( __CLASS__, 'render_sync_settings' ) );
		add_action( 'admin_post_bd_sync_venues', array( __CLASS__, 'handle_bulk_sync' ) );
		add_action( 'admin_post_bd_sync_single_venue', array( __CLASS__, 'handle_single_sync' ) );

		// Add meta box to business edit screen showing linked venue
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_venue_meta_box' ) );

		// AJAX handlers for admin
		add_action( 'wp_ajax_bd_get_sync_status', array( __CLASS__, 'ajax_get_sync_status' ) );
	}

	/**
	 * Handle business status changes (for auto-creating venue on publish)
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_business_status_change( $new_status, $old_status, $post ) {
		// Only handle bd_business posts
		if ( 'bd_business' !== $post->post_type ) {
			return;
		}

		// Only create venue when transitioning TO publish
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Check if TEC is active
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			return;
		}

		// Check if venue already exists for this business
		$existing_venue = get_post_meta( $post->ID, self::VENUE_META_KEY, true );
		if ( $existing_venue && get_post( $existing_venue ) ) {
			return;
		}

		// Create the venue
		self::create_venue_for_business( $post->ID );
	}

	/**
	 * Handle business save (update linked venue if exists)
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public static function on_business_save( $post_id, $post, $update ) {
		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip if not published
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Check if TEC is active
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			return;
		}

		// Only update if venue was auto-created by us
		$venue_id = get_post_meta( $post_id, self::VENUE_META_KEY, true );
		if ( ! $venue_id ) {
			return;
		}

		$auto_created = get_post_meta( $venue_id, self::AUTO_CREATED_KEY, true );
		if ( ! $auto_created ) {
			return; // Don't update manually created venues
		}

		// Update the venue data
		self::update_venue_from_business( $venue_id, $post_id );
	}

	/**
	 * Handle business deletion
	 *
	 * @param int $post_id Post ID being deleted.
	 */
	public static function on_business_delete( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'bd_business' !== $post->post_type ) {
			return;
		}

		$venue_id = get_post_meta( $post_id, self::VENUE_META_KEY, true );
		if ( ! $venue_id ) {
			return;
		}

		// Remove the business link from venue (don't delete the venue)
		delete_post_meta( $venue_id, self::BUSINESS_META_KEY );

		// Optionally: Mark venue as orphaned
		update_post_meta( $venue_id, '_bd_orphaned', current_time( 'mysql' ) );
	}

	/**
	 * Create a TEC venue from a business
	 *
	 * @param int $business_id Business post ID.
	 * @return int|false Venue ID on success, false on failure.
	 */
	public static function create_venue_for_business( $business_id ) {
		$business_id = absint( $business_id );
		$business    = get_post( $business_id );

		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return false;
		}

		// Get business data
		$location = get_post_meta( $business_id, 'bd_location', true );
		$location = is_array( $location ) ? $location : array();
		$contact  = get_post_meta( $business_id, 'bd_contact', true );
		$contact  = is_array( $contact ) ? $contact : array();

		// Prepare venue data
		$venue_data = array(
			'post_title'  => $business->post_title,
			'post_status' => 'publish',
			'post_type'   => 'tribe_venue',
		);

		// Create the venue (pass true to get WP_Error on failure)
		$venue_id = wp_insert_post( $venue_data, true );

		if ( is_wp_error( $venue_id ) ) {
			// Log error for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BD VenueSyncer: Failed to create venue - ' . $venue_id->get_error_message() );
			}
			return false;
		}

		if ( ! $venue_id ) {
			return false;
		}

		// Set venue meta from business location
		self::set_venue_meta_from_business( $venue_id, $location, $contact );

		// Create two-way link
		update_post_meta( $business_id, self::VENUE_META_KEY, $venue_id );
		update_post_meta( $venue_id, self::BUSINESS_META_KEY, $business_id );
		update_post_meta( $venue_id, self::AUTO_CREATED_KEY, 1 );

		// Log the sync
		update_post_meta( $venue_id, '_bd_synced_at', current_time( 'mysql' ) );

		// Clear stats cache
		delete_transient( 'bd_venue_sync_stats' );

		/**
		 * Action fired after a venue is created from a business
		 *
		 * @param int $venue_id    The created venue ID.
		 * @param int $business_id The source business ID.
		 */
		do_action( 'bd_venue_created_from_business', $venue_id, $business_id );

		return $venue_id;
	}

	/**
	 * Update an existing venue from business data
	 *
	 * @param int $venue_id    Venue post ID.
	 * @param int $business_id Business post ID.
	 * @return bool Success.
	 */
	public static function update_venue_from_business( $venue_id, $business_id ) {
		$venue_id    = absint( $venue_id );
		$business_id = absint( $business_id );

		$business = get_post( $business_id );
		if ( ! $business ) {
			return false;
		}

		$venue = get_post( $venue_id );
		if ( ! $venue || 'tribe_venue' !== $venue->post_type ) {
			return false;
		}

		// Update venue title if changed
		if ( $venue->post_title !== $business->post_title ) {
			wp_update_post(
				array(
					'ID'         => $venue_id,
					'post_title' => $business->post_title,
				)
			);
		}

		// Get business data
		$location = get_post_meta( $business_id, 'bd_location', true );
		$location = is_array( $location ) ? $location : array();
		$contact  = get_post_meta( $business_id, 'bd_contact', true );
		$contact  = is_array( $contact ) ? $contact : array();

		// Update venue meta
		self::set_venue_meta_from_business( $venue_id, $location, $contact );

		// Update sync timestamp
		update_post_meta( $venue_id, '_bd_synced_at', current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Set TEC venue meta fields from business data
	 *
	 * @param int   $venue_id Venue post ID.
	 * @param array $location Business location array.
	 * @param array $contact  Business contact array.
	 */
	private static function set_venue_meta_from_business( $venue_id, $location, $contact ) {
		$venue_id = absint( $venue_id );

		if ( ! $venue_id ) {
			return;
		}

		// Ensure arrays
		$location = is_array( $location ) ? $location : array();
		$contact  = is_array( $contact ) ? $contact : array();

		// TEC Venue meta fields
		$venue_meta = array(
			'_VenueAddress' => $location['address'] ?? '',
			'_VenueCity'    => $location['city'] ?? '',
			'_VenueState'   => $location['state'] ?? '',
			'_VenueZip'     => $location['zip'] ?? '',
			'_VenueCountry' => 'United States', // Default for TriValley
			'_VenuePhone'   => $contact['phone'] ?? '',
			'_VenueURL'     => $contact['website'] ?? '',
		);

		// Handle coordinates if available
		if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
			$venue_meta['_VenueLat'] = $location['lat'];
			$venue_meta['_VenueLng'] = $location['lng'];
		}

		// Update all meta
		foreach ( $venue_meta as $key => $value ) {
			if ( ! empty( $value ) ) {
				update_post_meta( $venue_id, $key, $value );
			} else {
				delete_post_meta( $venue_id, $key );
			}
		}
	}

	/**
	 * Get sync statistics
	 *
	 * @return array Statistics array.
	 */
	public static function get_sync_stats() {
		// Check cache first
		$cached = get_transient( 'bd_venue_sync_stats' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Total published businesses (defensive casting)
		$counts           = wp_count_posts( 'bd_business' );
		$total_businesses = isset( $counts->publish ) ? (int) $counts->publish : 0;

		// Businesses with synced venues that still exist
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$synced_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.post_id) 
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.meta_value = p.ID
				WHERE pm.meta_key = %s 
				AND pm.meta_value != ''
				AND p.post_type = 'tribe_venue'
				AND p.post_status = 'publish'",
				self::VENUE_META_KEY
			)
		);

		// Businesses without venues
		$unsynced_count = max( 0, $total_businesses - $synced_count );

		// Total TEC venues
		$total_venues = 0;
		if ( class_exists( 'Tribe__Events__Main' ) ) {
			$venue_counts = wp_count_posts( 'tribe_venue' );
			$total_venues = isset( $venue_counts->publish ) ? (int) $venue_counts->publish : 0;
		}

		// Auto-created venues (by this system)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$auto_created_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.post_id) 
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = %s 
				AND pm.meta_value = '1'
				AND p.post_status = 'publish'",
				self::AUTO_CREATED_KEY
			)
		);

		$stats = array(
			'total_businesses'    => $total_businesses,
			'synced_businesses'   => $synced_count,
			'unsynced_businesses' => $unsynced_count,
			'total_venues'        => $total_venues,
			'auto_created_venues' => $auto_created_count,
		);

		// Cache for 5 minutes
		set_transient( 'bd_venue_sync_stats', $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Get businesses that need venue sync
	 *
	 * @param int $limit Number of businesses to return.
	 * @return array Array of business post objects.
	 */
	public static function get_unsynced_businesses( $limit = 50 ) {
		global $wpdb;

		// Get business IDs that need sync:
		// 1. No venue meta at all (NULL or empty)
		// 2. Venue meta exists but venue was deleted (stale reference)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$business_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				LEFT JOIN {$wpdb->posts} v ON pm.meta_value = v.ID AND v.post_type = 'tribe_venue' AND v.post_status = 'publish'
				WHERE p.post_type = 'bd_business' 
				AND p.post_status = 'publish'
				AND (pm.meta_value IS NULL OR pm.meta_value = '' OR v.ID IS NULL)
				LIMIT %d",
				self::VENUE_META_KEY,
				$limit
			)
		);

		// Also clear stale venue references so they don't block future syncs
		if ( ! empty( $business_ids ) ) {
			foreach ( $business_ids as $business_id ) {
				$venue_id = get_post_meta( $business_id, self::VENUE_META_KEY, true );
				if ( $venue_id && ! get_post( $venue_id ) ) {
					// Venue was deleted - clear the stale reference
					delete_post_meta( $business_id, self::VENUE_META_KEY );
				}
			}
		}

		if ( empty( $business_ids ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => 'bd_business',
				'post__in'       => $business_ids,
				'posts_per_page' => $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Bulk sync all unsynced businesses to venues
	 *
	 * @param int $batch_size Number to process per batch.
	 * @return array Results array.
	 */
	public static function bulk_sync( $batch_size = 50 ) {
		$businesses = self::get_unsynced_businesses( $batch_size );

		$results = array(
			'processed' => 0,
			'success'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ( $businesses as $business ) {
			++$results['processed'];

			$venue_id = self::create_venue_for_business( $business->ID );

			if ( $venue_id ) {
				++$results['success'];
			} else {
				++$results['failed'];
				$results['errors'][] = sprintf(
					/* translators: %s: business title */
					__( 'Failed to create venue for: %s', 'business-directory' ),
					$business->post_title
				);
			}
		}

		return $results;
	}

	/**
	 * Render sync settings section in admin
	 */
	public static function render_sync_settings() {
		// Only show if TEC is active
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			return;
		}

		// Security check
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats = self::get_sync_stats();
		?>
		<hr style="margin: 40px 0;">

		<h2><?php esc_html_e( 'Venue Sync', 'business-directory' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Automatically create Events Calendar venues from your business directory entries. This allows events to be linked to businesses without manual venue creation.', 'business-directory' ); ?>
		</p>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Status', 'business-directory' ); ?></th>
				<td>
					<div style="display: flex; gap: 30px; margin-bottom: 15px;">
						<div style="background: #f0f0f1; padding: 15px 20px; border-radius: 8px; text-align: center;">
							<div style="font-size: 24px; font-weight: 600; color: #1d2327;">
								<?php echo esc_html( $stats['synced_businesses'] ); ?>
							</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">
								<?php esc_html_e( 'Synced', 'business-directory' ); ?>
							</div>
						</div>
						<div style="background: <?php echo $stats['unsynced_businesses'] > 0 ? '#fcf0f1' : '#f0f0f1'; ?>; padding: 15px 20px; border-radius: 8px; text-align: center;">
							<div style="font-size: 24px; font-weight: 600; color: <?php echo $stats['unsynced_businesses'] > 0 ? '#d63638' : '#1d2327'; ?>;">
								<?php echo esc_html( $stats['unsynced_businesses'] ); ?>
							</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">
								<?php esc_html_e( 'Need Sync', 'business-directory' ); ?>
							</div>
						</div>
						<div style="background: #f0f0f1; padding: 15px 20px; border-radius: 8px; text-align: center;">
							<div style="font-size: 24px; font-weight: 600; color: #1d2327;">
								<?php echo esc_html( $stats['total_venues'] ); ?>
							</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">
								<?php esc_html_e( 'Total Venues', 'business-directory' ); ?>
							</div>
						</div>
					</div>

					<?php if ( $stats['unsynced_businesses'] > 0 ) : ?>
						<p style="margin-top: 15px;">
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bd_sync_venues' ), 'bd_sync_venues' ) ); ?>" class="button button-primary">
								<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
								<?php
								printf(
									/* translators: %d: number of businesses to sync */
									esc_html__( 'Sync %d Businesses to Venues', 'business-directory' ),
									$stats['unsynced_businesses']
								);
								?>
							</a>
						</p>
					<?php else : ?>
						<p style="color: #00a32a; margin-top: 10px;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'All businesses are synced to venues!', 'business-directory' ); ?>
						</p>
					<?php endif; ?>

					<p class="description" style="margin-top: 15px;">
						<strong><?php esc_html_e( 'Note:', 'business-directory' ); ?></strong>
						<?php esc_html_e( 'New businesses will automatically get venues created when published. This sync is only needed for existing businesses.', 'business-directory' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Handle bulk sync admin action
	 */
	public static function handle_bulk_sync() {
		// Verify nonce (using GET since we're using a link, not a form)
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bd_sync_venues' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'business-directory' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-directory' ) );
		}

		// Run sync
		$results = self::bulk_sync( 100 );

		// Clear stats cache
		delete_transient( 'bd_venue_sync_stats' );

		// Store results in transient for display
		set_transient( 'bd_venue_sync_results', $results, 60 );

		// Redirect back to settings
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'bd-settings',
					'sync_done'   => '1',
					'sync_count'  => $results['success'],
					'sync_failed' => $results['failed'],
				),
				admin_url( 'edit.php?post_type=bd_business' )
			)
		);
		exit;
	}

	/**
	 * Handle single business sync
	 */
	public static function handle_single_sync() {
		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bd_sync_single_venue' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'business-directory' ) );
		}

		$business_id = isset( $_GET['business_id'] ) ? absint( $_GET['business_id'] ) : 0;

		if ( ! $business_id ) {
			wp_die( esc_html__( 'Invalid business ID.', 'business-directory' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $business_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-directory' ) );
		}

		// Create or update venue
		$existing_venue = get_post_meta( $business_id, self::VENUE_META_KEY, true );

		if ( $existing_venue && get_post( $existing_venue ) ) {
			// Update existing
			self::update_venue_from_business( $existing_venue, $business_id );
			$message = 'updated';
		} else {
			// Create new
			$venue_id = self::create_venue_for_business( $business_id );
			$message  = $venue_id ? 'created' : 'failed';
		}

		// Clear stats cache
		delete_transient( 'bd_venue_sync_stats' );

		// Build redirect URL with fallback
		$redirect_url = get_edit_post_link( $business_id, 'url' );
		if ( ! $redirect_url ) {
			$redirect_url = admin_url( 'post.php?post=' . $business_id . '&action=edit' );
		}

		// Redirect back to edit screen
		wp_safe_redirect(
			add_query_arg(
				array(
					'venue_sync' => $message,
				),
				$redirect_url
			)
		);
		exit;
	}

	/**
	 * Add meta box to business edit screen
	 */
	public static function add_venue_meta_box() {
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			return;
		}

		add_meta_box(
			'bd_linked_venue',
			__( 'Linked Venue', 'business-directory' ),
			array( __CLASS__, 'render_venue_meta_box' ),
			'bd_business',
			'side',
			'default'
		);
	}

	/**
	 * Render the venue meta box
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_venue_meta_box( $post ) {
		$venue_id     = get_post_meta( $post->ID, self::VENUE_META_KEY, true );
		$venue        = $venue_id ? get_post( $venue_id ) : null;
		$auto_created = $venue_id ? get_post_meta( $venue_id, self::AUTO_CREATED_KEY, true ) : false;
		$synced_at    = $venue_id ? get_post_meta( $venue_id, '_bd_synced_at', true ) : '';
		?>
		<div class="bd-venue-link-box">
			<?php if ( $venue && 'publish' === $venue->post_status ) : ?>
				<p style="margin-bottom: 10px;">
					<strong style="color: #00a32a;">
						<span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
						<?php esc_html_e( 'Venue Linked', 'business-directory' ); ?>
					</strong>
				</p>
				<p>
					<a href="<?php echo esc_url( get_edit_post_link( $venue_id ) ); ?>" target="_blank">
						<?php echo esc_html( $venue->post_title ); ?>
					</a>
					<?php if ( $auto_created ) : ?>
						<br>
						<span style="color: #646970; font-size: 12px;">
							<?php esc_html_e( '(Auto-created)', 'business-directory' ); ?>
						</span>
					<?php endif; ?>
				</p>
				<?php if ( $synced_at ) : ?>
					<p style="color: #646970; font-size: 12px; margin-top: 8px;">
						<?php
						printf(
							/* translators: %s: sync date */
							esc_html__( 'Last synced: %s', 'business-directory' ),
							esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $synced_at ) ) )
						);
						?>
					</p>
				<?php endif; ?>
				<p style="margin-top: 10px;">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bd_sync_single_venue&business_id=' . $post->ID ), 'bd_sync_single_venue' ) ); ?>" class="button button-small">
						<span class="dashicons dashicons-update" style="margin-top: 3px; font-size: 14px;"></span>
						<?php esc_html_e( 'Re-sync Venue', 'business-directory' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p style="color: #646970;">
					<?php esc_html_e( 'No venue linked yet.', 'business-directory' ); ?>
				</p>
				<?php if ( 'publish' === $post->post_status ) : ?>
					<p style="margin-top: 10px;">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bd_sync_single_venue&business_id=' . $post->ID ), 'bd_sync_single_venue' ) ); ?>" class="button button-primary button-small">
							<span class="dashicons dashicons-plus-alt" style="margin-top: 3px; font-size: 14px;"></span>
							<?php esc_html_e( 'Create Venue', 'business-directory' ); ?>
						</a>
					</p>
				<?php else : ?>
					<p class="description" style="margin-top: 5px;">
						<?php esc_html_e( 'Venue will be created automatically when published.', 'business-directory' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get sync status
	 */
	public static function ajax_get_sync_status() {
		check_ajax_referer( 'bd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		wp_send_json_success( self::get_sync_stats() );
	}

	/**
	 * Check if a business has a synced venue
	 *
	 * @param int $business_id Business post ID.
	 * @return int|false Venue ID or false.
	 */
	public static function get_venue_for_business( $business_id ) {
		$business_id = absint( $business_id );

		if ( ! $business_id ) {
			return false;
		}

		$venue_id = get_post_meta( $business_id, self::VENUE_META_KEY, true );

		if ( ! $venue_id ) {
			return false;
		}

		$venue = get_post( $venue_id );
		if ( ! $venue || 'tribe_venue' !== $venue->post_type || 'publish' !== $venue->post_status ) {
			return false;
		}

		return (int) $venue_id;
	}

	/**
	 * Check if a venue was created from a business
	 *
	 * @param int $venue_id Venue post ID.
	 * @return int|false Business ID or false.
	 */
	public static function get_business_for_venue( $venue_id ) {
		$venue_id = absint( $venue_id );

		if ( ! $venue_id ) {
			return false;
		}

		$business_id = get_post_meta( $venue_id, self::BUSINESS_META_KEY, true );

		if ( ! $business_id ) {
			return false;
		}

		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type || 'publish' !== $business->post_status ) {
			return false;
		}

		return (int) $business_id;
	}
}
