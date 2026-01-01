<?php
/**
 * Share Buttons Component
 *
 * Renders social share buttons throughout the directory.
 *
 * @package BusinessDirectory
 */


namespace BD\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class ShareButtons {

	/**
	 * Supported platforms (no Twitter/X per requirements).
	 *
	 * @var array
	 */
	const PLATFORMS = array(
		'facebook'  => array(
			'name'  => 'Facebook',
			'icon'  => 'fab fa-facebook-f',
			'color' => '#1877F2',
		),
		'linkedin'  => array(
			'name'  => 'LinkedIn',
			'icon'  => 'fab fa-linkedin-in',
			'color' => '#0A66C2',
		),
		'nextdoor'  => array(
			'name'  => 'Nextdoor',
			'icon'  => 'fas fa-home',
			'color' => '#00B636',
		),
		'email'     => array(
			'name'  => 'Email',
			'icon'  => 'fas fa-envelope',
			'color' => '#1a3a4a',
		),
		'copy_link' => array(
			'name'  => 'Copy Link',
			'icon'  => 'fas fa-link',
			'color' => '#5d7a8c',
		),
	);

	/**
	 * Points awarded for sharing actions.
	 *
	 * @var array
	 */
	const SHARE_POINTS = array(
		'share_business' => 5,
		'share_review'   => 10,
		'share_badge'    => 15,
		'share_profile'  => 5,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add share buttons to business detail pages.
		add_action( 'bd_after_business_header', array( $this, 'render_business_share_buttons' ), 20 );

		// Add share buttons after review submission.
		add_action( 'bd_review_approved', array( $this, 'add_review_share_prompt' ), 10, 2 );

		// Add share buttons to badge earn notifications.
		add_action( 'bd_badge_earned', array( $this, 'add_badge_share_prompt' ), 10, 3 );

		// Shortcode for manual placement.
		add_shortcode( 'bd_share_buttons', array( $this, 'shortcode_share_buttons' ) );

		// Filter to append share buttons to business content.
		add_filter( 'the_content', array( $this, 'append_share_buttons_to_business' ), 25 );
	}

	/**
	 * Render share buttons for a business.
	 *
	 * @param int|null $business_id Business ID.
	 * @return string HTML output.
	 */
	public static function render_business_buttons( $business_id = null ) {
		if ( ! $business_id ) {
			$business_id = get_the_ID();
		}

		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return '';
		}

		$share_url   = get_permalink( $business_id );
		$share_title = $business->post_title;

		// Get rating for share text.
		$rating = get_post_meta( $business_id, 'bd_rating_avg', true );
		$rating = $rating ? number_format( (float) $rating, 1 ) : '';

		// Get category.
		$categories = wp_get_post_terms( $business_id, 'bd_category', array( 'fields' => 'names' ) );
		$category   = ! empty( $categories ) ? $categories[0] : '';

		// Get area.
		$areas = wp_get_post_terms( $business_id, 'bd_area', array( 'fields' => 'names' ) );
		$area  = ! empty( $areas ) ? $areas[0] : '';

		// Build share text.
		$share_text = sprintf(
			// translators: %1$s is business name, %2$s is category, %3$s is area.
			__( 'Check out %1$s', 'business-directory' ),
			$share_title
		);

		if ( $rating ) {
			$share_text .= ' ⭐ ' . $rating;
		}
		if ( $category ) {
			$share_text .= ' · ' . $category;
		}
		if ( $area ) {
			$share_text .= ' · ' . $area;
		}

		return self::render_buttons(
			array(
				'url'         => $share_url,
				'title'       => $share_title,
				'text'        => $share_text,
				'type'        => 'business',
				'object_id'   => $business_id,
				'image'       => get_the_post_thumbnail_url( $business_id, 'large' ),
				'style'       => 'horizontal',
				'show_counts' => true,
			)
		);
	}

	/**
	 * Render share buttons for a review.
	 *
	 * @param array $review Review data.
	 * @return string HTML output.
	 */
	public static function render_review_buttons( $review ) {
		$business    = get_post( $review['business_id'] );
		$share_url   = get_permalink( $review['business_id'] ) . '#review-' . $review['id'];
		$share_title = sprintf(
			// translators: %1$s is rating, %2$s is business name.
			__( 'My %1$s-star review of %2$s', 'business-directory' ),
			$review['rating'],
			$business->post_title
		);

		$stars      = str_repeat( '⭐', (int) $review['rating'] );
		$share_text = $stars . ' ' . wp_trim_words( $review['content'], 20 );

		return self::render_buttons(
			array(
				'url'         => $share_url,
				'title'       => $share_title,
				'text'        => $share_text,
				'type'        => 'review',
				'object_id'   => $review['id'],
				'style'       => 'compact',
				'show_counts' => false,
			)
		);
	}

	/**
	 * Render share buttons for a badge achievement.
	 *
	 * @param string $badge_key Badge key.
	 * @param array  $badge Badge data.
	 * @param int    $user_id User ID.
	 * @return string HTML output.
	 */
	public static function render_badge_buttons( $badge_key, $badge, $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		// Profile URL or site URL.
		$profile_page = get_option( 'bd_profile_page_id' );
		$share_url    = $profile_page
			? add_query_arg( 'user', $user_id, get_permalink( $profile_page ) )
			: home_url();

		$share_title = sprintf(
			// translators: %1$s is badge name, %2$s is site name.
			__( 'I earned the "%1$s" badge on %2$s!', 'business-directory' ),
			$badge['name'],
			get_bloginfo( 'name' )
		);

		$share_text = $badge['icon'] . ' ' . $share_title . ' ' . $badge['description'];

		return self::render_buttons(
			array(
				'url'         => $share_url,
				'title'       => $share_title,
				'text'        => $share_text,
				'type'        => 'badge',
				'object_id'   => $badge_key,
				'style'       => 'compact',
				'show_counts' => false,
				'badge_data'  => array(
					'key'   => $badge_key,
					'name'  => $badge['name'],
					'icon'  => $badge['icon'],
					'color' => $badge['color'] ?? '#7a9eb8',
				),
			)
		);
	}

	/**
	 * Render the share buttons HTML.
	 *
	 * @param array $args Arguments.
	 * @return string HTML output.
	 */
	public static function render_buttons( $args = array() ) {
		$defaults = array(
			'url'         => '',
			'title'       => '',
			'text'        => '',
			'type'        => 'business',
			'object_id'   => 0,
			'image'       => '',
			'style'       => 'horizontal',
			'show_counts' => true,
			'platforms'   => array( 'facebook', 'linkedin', 'nextdoor', 'email', 'copy_link' ),
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['url'] ) ) {
			return '';
		}

		$encoded_url   = rawurlencode( $args['url'] );
		$encoded_title = rawurlencode( $args['title'] );
		$encoded_text  = rawurlencode( $args['text'] );

		ob_start();
		?>
		<div class="bd-share-buttons bd-share-<?php echo esc_attr( $args['style'] ); ?>"
			data-share-type="<?php echo esc_attr( $args['type'] ); ?>"
			data-object-id="<?php echo esc_attr( $args['object_id'] ); ?>"
			data-share-url="<?php echo esc_attr( $args['url'] ); ?>">
			
			<span class="bd-share-label"><?php esc_html_e( 'Share:', 'business-directory' ); ?></span>
			
			<div class="bd-share-buttons-list">
				<?php foreach ( $args['platforms'] as $platform ) : ?>
					<?php
					if ( ! isset( self::PLATFORMS[ $platform ] ) ) {
						continue;
					}
					?>
					<?php $p = self::PLATFORMS[ $platform ]; ?>
					
					<?php
					// Build share URL for each platform.
					$share_href = '#';
					switch ( $platform ) {
						case 'facebook':
							$share_href = 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url . '&quote=' . $encoded_text;
							break;
						case 'linkedin':
							$share_href = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encoded_url;
							break;
						case 'nextdoor':
							// Nextdoor doesn't have a direct share URL, use copy link.
							$share_href = '#copy';
							break;
						case 'email':
							$share_href = 'mailto:?subject=' . $encoded_title . '&body=' . $encoded_text . '%0A%0A' . $encoded_url;
							break;
						case 'copy_link':
							$share_href = '#copy';
							break;
					}
					?>
					
					<a href="<?php echo esc_url( $share_href ); ?>"
						class="bd-share-btn bd-share-<?php echo esc_attr( $platform ); ?>"
						data-platform="<?php echo esc_attr( $platform ); ?>"
						data-share-url="<?php echo esc_attr( $args['url'] ); ?>"
						data-share-text="<?php echo esc_attr( $args['text'] ); ?>"
						data-share-title="<?php echo esc_attr( $args['title'] ); ?>"
						title="<?php echo esc_attr( $p['name'] ); ?>"
						style="--btn-color: <?php echo esc_attr( $p['color'] ); ?>"
						<?php if ( 'email' !== $platform && 'copy_link' !== $platform ) : ?>
						target="_blank" rel="noopener noreferrer"
						<?php endif; ?>>
						<i class="<?php echo esc_attr( $p['icon'] ); ?>"></i>
						<span class="bd-share-btn-text"><?php echo esc_html( $p['name'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( $args['show_counts'] && is_user_logged_in() ) : ?>
				<span class="bd-share-points-hint">
					<?php
					$points = self::SHARE_POINTS[ 'share_' . $args['type'] ] ?? 5;
					printf(
						// translators: %d is number of points.
						esc_html__( '+%d points', 'business-directory' ),
						$points
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		
		<?php
		// Toast notification container (only output once).
		static $toast_rendered = false;
		if ( ! $toast_rendered ) :
			$toast_rendered = true;
			?>
			<div id="bd-share-toast" class="bd-share-toast" aria-live="polite"></div>
		<?php endif; ?>
		
		<?php
		return ob_get_clean();
	}

	/**
	 * Append share buttons to business content.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function append_share_buttons_to_business( $content ) {
		// Only on single business pages.
		if ( ! is_singular( 'bd_business' ) ) {
			return $content;
		}

		// Don't add twice.
		if ( strpos( $content, 'bd-share-buttons' ) !== false ) {
			return $content;
		}

		$share_html = self::render_business_buttons( get_the_ID() );

		return $content . $share_html;
	}

	/**
	 * Render business share buttons hook.
	 */
	public function render_business_share_buttons() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::render_business_buttons();
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function shortcode_share_buttons( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'        => 'business',
				'id'          => 0,
				'url'         => '',
				'title'       => '',
				'text'        => '',
				'style'       => 'horizontal',
				'show_counts' => 'yes',
			),
			$atts,
			'bd_share_buttons'
		);

		if ( 'business' === $atts['type'] ) {
			$business_id = $atts['id'] ? (int) $atts['id'] : get_the_ID();
			return self::render_business_buttons( $business_id );
		}

		// Custom URL sharing.
		return self::render_buttons(
			array(
				'url'         => $atts['url'] ? $atts['url'] : get_permalink(),
				'title'       => $atts['title'] ? $atts['title'] : get_the_title(),
				'text'        => $atts['text'] ? $atts['text'] : '',
				'type'        => $atts['type'],
				'object_id'   => $atts['id'],
				'style'       => $atts['style'],
				'show_counts' => 'yes' === $atts['show_counts'],
			)
		);
	}

	/**
	 * Add review share prompt (called after review approval).
	 *
	 * @param int        $review_id Review ID.
	 * @param array|null $review    Review data (optional, will be fetched if not provided).
	 */
	public function add_review_share_prompt( $review_id, $review = null ) {
		// Fetch review data if not provided.
		if ( null === $review ) {
			global $wpdb;
			$table = $wpdb->prefix . 'bd_reviews';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$review = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $review_id ),
				ARRAY_A
			);
			if ( ! $review ) {
				return;
			}
		}

		// This stores the prompt data for display via JS.
		if ( ! isset( $review['user_id'] ) || ! $review['user_id'] ) {
			return;
		}

		// Store transient for the user to show share prompt.
		set_transient(
			'bd_review_share_prompt_' . $review['user_id'],
			array(
				'review_id'   => $review_id,
				'business_id' => $review['business_id'],
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Add badge share prompt.
	 *
	 * @param string $badge_key Badge key.
	 * @param int    $user_id User ID.
	 * @param array  $badge Badge data.
	 */
	public function add_badge_share_prompt( $badge_key, $user_id, $badge ) {
		// Store transient for the user to show share prompt.
		set_transient(
			'bd_badge_share_prompt_' . $user_id,
			array(
				'badge_key' => $badge_key,
				'badge'     => $badge,
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Get share count for an object.
	 *
	 * @param string $type Object type.
	 * @param int    $object_id Object ID.
	 * @return int Share count.
	 */
	public static function get_share_count( $type, $object_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_share_tracking';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
		if ( ! $exists ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE share_type = %s AND object_id = %d",
				$type,
				$object_id
			)
		);

		return (int) $count;
	}
}
