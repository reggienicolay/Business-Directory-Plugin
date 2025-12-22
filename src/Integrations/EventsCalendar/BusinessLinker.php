<?php
/**
 * Business Linker
 *
 * Adds meta boxes to Events, Venues, and Organizers
 * to link them with businesses from the directory.
 *
 * Updated for Gutenberg/Block Editor compatibility.
 *
 * @package BusinessDirectory
 */

namespace BD\Integrations\EventsCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BusinessLinker
 */
class BusinessLinker {

	/**
	 * Initialize the linker
	 */
	public static function init() {
		// Register meta for REST API (required for block editor)
		add_action( 'init', array( __CLASS__, 'register_meta' ) );

		// Add meta boxes (for classic editor fallback)
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		// Save meta (classic editor)
		add_action( 'save_post_tribe_events', array( __CLASS__, 'save_event_meta' ) );
		add_action( 'save_post_tribe_venue', array( __CLASS__, 'save_venue_meta' ) );
		add_action( 'save_post_tribe_organizer', array( __CLASS__, 'save_organizer_meta' ) );

		// Auto-link on venue/organizer creation
		add_action( 'save_post_tribe_venue', array( __CLASS__, 'maybe_auto_link_venue' ), 20, 2 );
		add_action( 'save_post_tribe_organizer', array( __CLASS__, 'maybe_auto_link_organizer' ), 20, 2 );

		// AJAX search for businesses
		add_action( 'wp_ajax_bd_search_businesses', array( __CLASS__, 'ajax_search_businesses' ) );

		// Enqueue admin assets - for both classic and block editor
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );

		// Add column to events list
		add_filter( 'manage_tribe_events_posts_columns', array( __CLASS__, 'add_business_column' ) );
		add_action( 'manage_tribe_events_posts_custom_column', array( __CLASS__, 'render_business_column' ), 10, 2 );

		// Add sidebar panel for block editor
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'add_block_editor_sidebar' ) );
	}

	/**
	 * Register meta fields for REST API access (required for Gutenberg)
	 */
	public static function register_meta() {
		$post_types = array( 'tribe_events', 'tribe_venue', 'tribe_organizer' );

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'bd_linked_business',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Add Gutenberg sidebar panel
	 */
	public static function add_block_editor_sidebar() {
		global $post_type;

		if ( ! in_array( $post_type, array( 'tribe_events', 'tribe_venue', 'tribe_organizer' ), true ) ) {
			return;
		}

		// Get all businesses for the dropdown
		$businesses = get_posts(
			array(
				'post_type'      => 'bd_business',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$business_options = array(
			array(
				'value' => 0,
				'label' => '— Select a Business —',
			),
		);

		foreach ( $businesses as $business ) {
			$location = get_post_meta( $business->ID, 'bd_location', true );
			$city     = is_array( $location ) ? ( $location['city'] ?? '' ) : '';
			$label    = $business->post_title;
			if ( $city ) {
				$label .= ' (' . $city . ')';
			}

			$business_options[] = array(
				'value' => $business->ID,
				'label' => $label,
			);
		}

		wp_add_inline_script(
			'bd-business-linker-block',
			'window.bdBusinessOptions = ' . wp_json_encode( $business_options ) . ';',
			'before'
		);
	}

	/**
	 * Enqueue block editor assets
	 */
	public static function enqueue_block_editor_assets() {
		global $post_type;

		if ( ! in_array( $post_type, array( 'tribe_events', 'tribe_venue', 'tribe_organizer' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'bd-business-linker-block',
			BD_PLUGIN_URL . 'assets/js/integrations/business-linker-block.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose' ),
			BD_VERSION,
			true
		);

		wp_enqueue_style(
			'bd-business-linker-block',
			BD_PLUGIN_URL . 'assets/css/integrations/business-linker-block.css',
			array(),
			BD_VERSION
		);
	}

	/**
	 * Add meta boxes to Events, Venues, and Organizers (classic editor)
	 */
	public static function add_meta_boxes() {
		// Event meta box
		add_meta_box(
			'bd_event_business_link',
			__( 'Linked Business', 'business-directory' ),
			array( __CLASS__, 'render_event_meta_box' ),
			'tribe_events',
			'side',
			'default'
		);

		// Venue meta box
		add_meta_box(
			'bd_venue_business_link',
			__( 'Linked Business', 'business-directory' ),
			array( __CLASS__, 'render_venue_meta_box' ),
			'tribe_venue',
			'side',
			'default'
		);

		// Organizer meta box
		add_meta_box(
			'bd_organizer_business_link',
			__( 'Linked Business', 'business-directory' ),
			array( __CLASS__, 'render_organizer_meta_box' ),
			'tribe_organizer',
			'side',
			'default'
		);
	}

	/**
	 * Render event meta box
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_event_meta_box( $post ) {
		wp_nonce_field( 'bd_event_business_link', 'bd_event_business_link_nonce' );

		$business_id = get_post_meta( $post->ID, 'bd_linked_business', true );

		// Check for inherited business from venue
		$venue_id          = get_post_meta( $post->ID, '_EventVenueID', true );
		$venue_business_id = $venue_id ? get_post_meta( $venue_id, 'bd_linked_business', true ) : null;

		self::render_business_dropdown( 'bd_event_business_id', $business_id );

		if ( $venue_business_id && ! $business_id ) {
			$venue_business = get_post( $venue_business_id );
			if ( $venue_business ) {
				?>
				<p class="description" style="margin-top: 10px;">
					<em><?php esc_html_e( 'Inherits from venue:', 'business-directory' ); ?></em>
					<br>
					<a href="<?php echo esc_url( get_permalink( $venue_business_id ) ); ?>" target="_blank">
						<?php echo esc_html( $venue_business->post_title ); ?>
					</a>
				</p>
				<?php
			}
		}
	}

	/**
	 * Render venue meta box
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_venue_meta_box( $post ) {
		wp_nonce_field( 'bd_venue_business_link', 'bd_venue_business_link_nonce' );

		$business_id = get_post_meta( $post->ID, 'bd_linked_business', true );

		self::render_business_dropdown( 'bd_venue_business_id', $business_id );

		?>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Events at this venue will automatically link to this business.', 'business-directory' ); ?>
		</p>
		<?php
	}

	/**
	 * Render organizer meta box
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_organizer_meta_box( $post ) {
		wp_nonce_field( 'bd_organizer_business_link', 'bd_organizer_business_link_nonce' );

		$business_id = get_post_meta( $post->ID, 'bd_linked_business', true );

		self::render_business_dropdown( 'bd_organizer_business_id', $business_id );

		?>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Events by this organizer can link to this business.', 'business-directory' ); ?>
		</p>
		<?php
	}

	/**
	 * Render a simple dropdown selector for businesses
	 *
	 * @param string   $field_name  Field name.
	 * @param int|null $selected_id Currently selected business ID.
	 */
	private static function render_business_dropdown( $field_name, $selected_id ) {
		$businesses = get_posts(
			array(
				'post_type'      => 'bd_business',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<select name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name ); ?>" style="width: 100%; max-width: 100%;">
			<option value=""><?php esc_html_e( '— Select a Business —', 'business-directory' ); ?></option>
			<?php foreach ( $businesses as $business ) : ?>
				<?php
				$location = get_post_meta( $business->ID, 'bd_location', true );
				$city     = is_array( $location ) ? ( $location['city'] ?? '' ) : '';
				$label    = $business->post_title;
				if ( $city ) {
					$label .= ' (' . $city . ')';
				}
				?>
				<option value="<?php echo esc_attr( $business->ID ); ?>" <?php selected( $selected_id, $business->ID ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php if ( $selected_id ) : ?>
			<?php $selected_business = get_post( $selected_id ); ?>
			<?php if ( $selected_business ) : ?>
				<p style="margin-top: 8px;">
					<a href="<?php echo esc_url( get_permalink( $selected_id ) ); ?>" target="_blank" style="font-size: 12px;">
						<?php esc_html_e( 'View business →', 'business-directory' ); ?>
					</a>
				</p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save event meta
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_event_meta( $post_id ) {
		if ( ! isset( $_POST['bd_event_business_link_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['bd_event_business_link_nonce'], 'bd_event_business_link' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$business_id = isset( $_POST['bd_event_business_id'] ) ? absint( $_POST['bd_event_business_id'] ) : 0;

		if ( $business_id ) {
			update_post_meta( $post_id, 'bd_linked_business', $business_id );
		} else {
			delete_post_meta( $post_id, 'bd_linked_business' );
		}
	}

	/**
	 * Save venue meta
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_venue_meta( $post_id ) {
		if ( ! isset( $_POST['bd_venue_business_link_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['bd_venue_business_link_nonce'], 'bd_venue_business_link' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$business_id = isset( $_POST['bd_venue_business_id'] ) ? absint( $_POST['bd_venue_business_id'] ) : 0;

		if ( $business_id ) {
			update_post_meta( $post_id, 'bd_linked_business', $business_id );
		} else {
			delete_post_meta( $post_id, 'bd_linked_business' );
		}
	}

	/**
	 * Save organizer meta
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_organizer_meta( $post_id ) {
		if ( ! isset( $_POST['bd_organizer_business_link_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['bd_organizer_business_link_nonce'], 'bd_organizer_business_link' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$business_id = isset( $_POST['bd_organizer_business_id'] ) ? absint( $_POST['bd_organizer_business_id'] ) : 0;

		if ( $business_id ) {
			update_post_meta( $post_id, 'bd_linked_business', $business_id );
		} else {
			delete_post_meta( $post_id, 'bd_linked_business' );
		}
	}

	/**
	 * Try to auto-link venue to business on creation
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function maybe_auto_link_venue( $post_id, $post ) {
		// Skip if already linked
		if ( get_post_meta( $post_id, 'bd_linked_business', true ) ) {
			return;
		}

		// Skip if this is a manual save (nonce present)
		if ( isset( $_POST['bd_venue_business_link_nonce'] ) ) {
			return;
		}

		$venue_name    = $post->post_title;
		$venue_address = get_post_meta( $post_id, '_VenueAddress', true );

		$business = self::find_matching_business( $venue_name, $venue_address );

		if ( $business ) {
			update_post_meta( $post_id, 'bd_linked_business', $business->ID );
			update_post_meta( $post_id, 'bd_auto_linked', 1 );
		}
	}

	/**
	 * Try to auto-link organizer to business on creation
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function maybe_auto_link_organizer( $post_id, $post ) {
		// Skip if already linked
		if ( get_post_meta( $post_id, 'bd_linked_business', true ) ) {
			return;
		}

		// Skip if this is a manual save (nonce present)
		if ( isset( $_POST['bd_organizer_business_link_nonce'] ) ) {
			return;
		}

		$organizer_name = $post->post_title;

		$business = self::find_matching_business( $organizer_name );

		if ( $business ) {
			update_post_meta( $post_id, 'bd_linked_business', $business->ID );
			update_post_meta( $post_id, 'bd_auto_linked', 1 );
		}
	}

	/**
	 * Find a matching business by name and optionally address
	 *
	 * @param string $name    Name to match.
	 * @param string $address Optional address to match.
	 * @return WP_Post|null
	 */
	private static function find_matching_business( $name, $address = '' ) {
		// First try exact name match
		$businesses = get_posts(
			array(
				'post_type'      => 'bd_business',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'title'          => $name,
			)
		);

		if ( ! empty( $businesses ) ) {
			return $businesses[0];
		}

		// Try partial name match
		$businesses = get_posts(
			array(
				'post_type'      => 'bd_business',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				's'              => $name,
			)
		);

		if ( empty( $businesses ) ) {
			return null;
		}

		// If we have an address, try to match
		if ( $address ) {
			foreach ( $businesses as $business ) {
				$location = get_post_meta( $business->ID, 'bd_location', true );
				if ( is_array( $location ) && ! empty( $location['address'] ) ) {
					// Simple similarity check
					similar_text( strtolower( $address ), strtolower( $location['address'] ), $percent );
					if ( $percent > 70 ) {
						return $business;
					}
				}
			}
		}

		// Return first result if name is very similar
		similar_text( strtolower( $name ), strtolower( $businesses[0]->post_title ), $percent );
		if ( $percent > 85 ) {
			return $businesses[0];
		}

		return null;
	}

	/**
	 * AJAX handler for business search
	 */
	public static function ajax_search_businesses() {
		check_ajax_referer( 'bd_business_search', 'nonce' );

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}

		$businesses = get_posts(
			array(
				'post_type'      => 'bd_business',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				's'              => $search,
			)
		);

		$results = array();

		foreach ( $businesses as $business ) {
			$location   = get_post_meta( $business->ID, 'bd_location', true );
			$city       = is_array( $location ) ? ( $location['city'] ?? '' ) : '';
			$categories = get_the_terms( $business->ID, 'business_category' );
			$cat_name   = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0]->name : '';

			$results[] = array(
				'id'       => $business->ID,
				'name'     => $business->post_title,
				'city'     => $city,
				'category' => $cat_name,
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Enqueue admin assets (classic editor)
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		global $post_type;

		// Only load on event, venue, organizer edit screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! in_array( $post_type, array( 'tribe_events', 'tribe_venue', 'tribe_organizer' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'bd-business-linker',
			BD_PLUGIN_URL . 'assets/js/integrations/business-linker.js',
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		wp_localize_script(
			'bd-business-linker',
			'bdBusinessLinker',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_business_search' ),
			)
		);
	}

	/**
	 * Add business column to events list
	 *
	 * @param array $columns Columns array.
	 * @return array
	 */
	public static function add_business_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['bd_linked_business'] = __( 'Linked Business', 'business-directory' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render business column content
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_business_column( $column, $post_id ) {
		if ( 'bd_linked_business' !== $column ) {
			return;
		}

		$business_id = EventsCalendarIntegration::get_business_for_event( $post_id );

		if ( $business_id ) {
			$business = get_post( $business_id );
			if ( $business ) {
				printf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_post_link( $business_id ) ),
					esc_html( $business->post_title )
				);

				// Show if inherited from venue
				$direct_link = get_post_meta( $post_id, 'bd_linked_business', true );
				if ( ! $direct_link ) {
					echo ' <span style="color: #666; font-size: 11px;">(via venue)</span>';
				}
			}
		} else {
			echo '<span style="color: #999;">—</span>';
		}
	}
}
