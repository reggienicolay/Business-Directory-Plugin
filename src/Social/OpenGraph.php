<?php
/**
 * Open Graph Meta Tags
 *
 * Generates Open Graph meta tags for rich social media previews.
 * Supports businesses, lists, reviews, and badges.
 *
 * @package BusinessDirectory
 */

namespace BD\Social;

use BD\Lists\ListManager;

class OpenGraph {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_meta_tags' ), 5 );
	}

	/**
	 * Output Open Graph meta tags.
	 */
	public function output_meta_tags() {
		$tags = array();

		// Check for list page first (query param or shortcode context).
		if ( $this->is_list_page() ) {
			$list = $this->get_current_list();
			if ( $list ) {
				$tags = $this->generate_list_tags( $list );
			}
		} elseif ( is_singular( 'bd_business' ) ) {
			// Business pages.
			$post = get_queried_object();
			if ( $post ) {
				$tags = $this->generate_business_tags( $post );
			}
		}

		if ( ! empty( $tags ) ) {
			$this->render_tags( $tags );
		}
	}

	/**
	 * Check if current page is a list display page.
	 *
	 * @return bool True if list page.
	 */
	private function is_list_page() {
		// Check for list query param.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['list'] ) || isset( $_GET['bd_list'] ) ) {
			return true;
		}

		// Check if page has bd_list shortcode with slug attribute.
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'bd_list' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the current list being viewed.
	 *
	 * @return array|null List data or null.
	 */
	private function get_current_list() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug = isset( $_GET['list'] ) ? sanitize_text_field( wp_unslash( $_GET['list'] ) ) : '';
		if ( ! $slug ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$slug = isset( $_GET['bd_list'] ) ? sanitize_text_field( wp_unslash( $_GET['bd_list'] ) ) : '';
		}

		if ( $slug ) {
			if ( is_numeric( $slug ) ) {
				return ListManager::get_list( (int) $slug );
			}
			return ListManager::get_list_by_slug( $slug );
		}

		return null;
	}

	/**
	 * Generate OG tags for a list.
	 *
	 * @param array $list List data.
	 * @return array Tags.
	 */
	public function generate_list_tags( $list ) {
		$tags = array();

		// Basic OG tags.
		$tags['og:type']      = 'article';
		$tags['og:url']       = ListManager::get_list_url( $list );
		$tags['og:title']     = $this->get_list_title( $list );
		$tags['og:site_name'] = get_bloginfo( 'name' );

		// Description.
		$tags['og:description'] = $this->get_list_description( $list );

		// Image - use dynamic list image generator.
		$image_url = $this->get_list_share_image_url( $list['id'] );
		if ( $image_url ) {
			$tags['og:image']        = $image_url;
			$tags['og:image:width']  = 1200;
			$tags['og:image:height'] = 630;
			$tags['og:image:alt']    = $list['title'];
		}

		// Twitter Card.
		$tags['twitter:card']        = 'summary_large_image';
		$tags['twitter:title']       = $tags['og:title'];
		$tags['twitter:description'] = $tags['og:description'];
		if ( $image_url ) {
			$tags['twitter:image'] = $image_url;
		}

		// Author info.
		if ( ! empty( $list['user_id'] ) ) {
			$author = get_userdata( $list['user_id'] );
			if ( $author ) {
				$tags['article:author'] = $author->display_name;
			}
		}

		// Timestamps.
		if ( ! empty( $list['created_at'] ) ) {
			$tags['article:published_time'] = gmdate( 'c', strtotime( $list['created_at'] ) );
		}
		if ( ! empty( $list['updated_at'] ) ) {
			$tags['article:modified_time'] = gmdate( 'c', strtotime( $list['updated_at'] ) );
		}

		// Facebook App ID.
		$fb_app_id = get_option( 'bd_facebook_app_id', '' );
		if ( $fb_app_id ) {
			$tags['fb:app_id'] = $fb_app_id;
		}

		return array_filter( $tags );
	}

	/**
	 * Get optimized title for list sharing.
	 *
	 * @param array $list List data.
	 * @return string Title.
	 */
	private function get_list_title( $list ) {
		$title = $list['title'];

		// Add item count.
		$item_count = $list['item_count'] ?? 0;
		if ( $item_count > 0 ) {
			$title .= sprintf(
				' (%d %s)',
				$item_count,
				_n( 'place', 'places', $item_count, 'business-directory' )
			);
		}

		// Add site name context.
		$title .= ' · ' . get_bloginfo( 'name' );

		return $title;
	}

	/**
	 * Get description for list sharing.
	 *
	 * @param array $list List data.
	 * @return string Description.
	 */
	private function get_list_description( $list ) {
		// Use list description if available.
		if ( ! empty( $list['description'] ) ) {
			return wp_trim_words( $list['description'], 25 );
		}

		// Build description from list content.
		$item_count = $list['item_count'] ?? 0;
		$author     = '';

		if ( ! empty( $list['user_id'] ) ) {
			$user = get_userdata( $list['user_id'] );
			if ( $user ) {
				$author = $user->display_name;
			}
		}

		if ( $author ) {
			return sprintf(
				// translators: %1$d is item count, %2$s is author name.
				_n(
					'A curated list of %1$d local business by %2$s',
					'A curated list of %1$d local businesses by %2$s',
					$item_count,
					'business-directory'
				),
				$item_count,
				$author
			);
		}

		return sprintf(
			// translators: %d is item count.
			_n(
				'Discover %d amazing local business',
				'Discover %d amazing local businesses',
				$item_count,
				'business-directory'
			),
			$item_count
		);
	}

	/**
	 * Get list share image URL.
	 *
	 * @param int $list_id List ID.
	 * @return string Image URL.
	 */
	private function get_list_share_image_url( $list_id ) {
		// Check if cached image exists.
		$upload_dir = wp_upload_dir();
		$image_path = $upload_dir['basedir'] . '/bd-share-images/list-' . $list_id . '.png';

		if ( file_exists( $image_path ) ) {
			// Check if cache is still valid (24 hours).
			$modified = filemtime( $image_path );
			if ( time() - $modified < DAY_IN_SECONDS ) {
				return $upload_dir['baseurl'] . '/bd-share-images/list-' . $list_id . '.png';
			}
		}

		// Return URL to dynamic generator.
		return add_query_arg(
			array(
				'bd_share_image' => 1,
				'list_id'        => $list_id,
			),
			home_url()
		);
	}

	/**
	 * Generate OG tags for a business.
	 *
	 * @param \WP_Post $post Business post.
	 * @return array Tags.
	 */
	private function generate_business_tags( $post ) {
		$tags = array();

		// Basic OG tags.
		$tags['og:type']      = 'business.business';
		$tags['og:url']       = get_permalink( $post->ID );
		$tags['og:title']     = $this->get_business_title( $post );
		$tags['og:site_name'] = get_bloginfo( 'name' );

		// Description.
		$tags['og:description'] = $this->get_business_description( $post );

		// Image.
		$image = $this->get_share_image( $post );
		if ( $image ) {
			$tags['og:image']        = $image['url'];
			$tags['og:image:width']  = $image['width'] ?? 1200;
			$tags['og:image:height'] = $image['height'] ?? 630;
			$tags['og:image:alt']    = $post->post_title;
		}

		// Business-specific tags.
		$location = get_post_meta( $post->ID, 'bd_location', true );
		if ( is_array( $location ) ) {
			if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
				$tags['place:location:latitude']  = $location['lat'];
				$tags['place:location:longitude'] = $location['lng'];
			}
			if ( ! empty( $location['address'] ) ) {
				$tags['business:contact_data:street_address'] = $location['address'];
			}
			if ( ! empty( $location['city'] ) ) {
				$tags['business:contact_data:locality'] = $location['city'];
			}
			if ( ! empty( $location['state'] ) ) {
				$tags['business:contact_data:region'] = $location['state'];
			}
			if ( ! empty( $location['zip'] ) ) {
				$tags['business:contact_data:postal_code'] = $location['zip'];
			}
			if ( ! empty( $location['country'] ) ) {
				$tags['business:contact_data:country_name'] = $location['country'];
			}
		}

		// Contact info.
		$contact = get_post_meta( $post->ID, 'bd_contact', true );
		if ( is_array( $contact ) ) {
			if ( ! empty( $contact['phone'] ) ) {
				$tags['business:contact_data:phone_number'] = $contact['phone'];
			}
			if ( ! empty( $contact['website'] ) ) {
				$tags['business:contact_data:website'] = $contact['website'];
			}
		}

		// Rating.
		$rating       = get_post_meta( $post->ID, 'bd_rating_avg', true );
		$review_count = get_post_meta( $post->ID, 'bd_review_count', true );
		if ( $rating ) {
			// Schema.org for Google.
			$tags['rating']      = $rating;
			$tags['ratingCount'] = $review_count ? $review_count : 0;
		}

		// Facebook-specific.
		$fb_app_id = get_option( 'bd_facebook_app_id', '' );
		if ( $fb_app_id ) {
			$tags['fb:app_id'] = $fb_app_id;
		}

		// LinkedIn-specific.
		$tags['linkedin:owner'] = get_option( 'bd_linkedin_company_id', '' );

		return array_filter( $tags );
	}

	/**
	 * Get optimized title for sharing.
	 *
	 * @param \WP_Post $post Business post.
	 * @return string Title.
	 */
	private function get_business_title( $post ) {
		$title = $post->post_title;

		// Add rating if available.
		$rating = get_post_meta( $post->ID, 'bd_rating_avg', true );
		if ( $rating ) {
			$title .= ' ⭐ ' . number_format( (float) $rating, 1 );
		}

		// Add category.
		$categories = wp_get_post_terms( $post->ID, 'bd_category', array( 'fields' => 'names' ) );
		if ( ! empty( $categories ) ) {
			$title .= ' · ' . $categories[0];
		}

		// Add area.
		$areas = wp_get_post_terms( $post->ID, 'bd_area', array( 'fields' => 'names' ) );
		if ( ! empty( $areas ) ) {
			$title .= ' · ' . $areas[0];
		}

		return $title;
	}

	/**
	 * Get description for sharing.
	 *
	 * @param \WP_Post $post Business post.
	 * @return string Description.
	 */
	private function get_business_description( $post ) {
		// Use excerpt if available.
		if ( $post->post_excerpt ) {
			return wp_trim_words( $post->post_excerpt, 30 );
		}

		// Use content.
		if ( $post->post_content ) {
			return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		}

		// Build from meta.
		$parts = array();

		$review_count = get_post_meta( $post->ID, 'bd_review_count', true );
		if ( $review_count ) {
			$parts[] = sprintf(
				// translators: %d is number of reviews.
				_n( '%d review', '%d reviews', $review_count, 'business-directory' ),
				$review_count
			);
		}

		$location = get_post_meta( $post->ID, 'bd_location', true );
		if ( is_array( $location ) ) {
			$address_parts = array_filter(
				array(
					$location['address'] ?? '',
					$location['city'] ?? '',
					$location['state'] ?? '',
				)
			);
			if ( $address_parts ) {
				$parts[] = implode( ', ', $address_parts );
			}
		}

		$contact = get_post_meta( $post->ID, 'bd_contact', true );
		if ( is_array( $contact ) && ! empty( $contact['phone'] ) ) {
			$parts[] = $contact['phone'];
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Get share image URL.
	 *
	 * @param \WP_Post $post Business post.
	 * @return array|null Image data or null.
	 */
	private function get_share_image( $post ) {
		// Check for dynamic share image.
		$dynamic_image = $this->get_dynamic_share_image_url( $post->ID );
		if ( $dynamic_image ) {
			return array(
				'url'    => $dynamic_image,
				'width'  => 1200,
				'height' => 630,
			);
		}

		// Fall back to featured image.
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$image = wp_get_attachment_image_src( $thumbnail_id, 'large' );
			if ( $image ) {
				return array(
					'url'    => $image[0],
					'width'  => $image[1],
					'height' => $image[2],
				);
			}
		}

		// Fall back to site default.
		$default_image = get_option( 'bd_default_share_image', '' );
		if ( $default_image ) {
			return array(
				'url'    => $default_image,
				'width'  => 1200,
				'height' => 630,
			);
		}

		return null;
	}

	/**
	 * Get dynamic share image URL.
	 *
	 * @param int $business_id Business ID.
	 * @return string|null Image URL or null.
	 */
	private function get_dynamic_share_image_url( $business_id ) {
		// Check if dynamic image exists.
		$upload_dir = wp_upload_dir();
		$image_path = $upload_dir['basedir'] . '/bd-share-images/' . $business_id . '.png';

		if ( file_exists( $image_path ) ) {
			return $upload_dir['baseurl'] . '/bd-share-images/' . $business_id . '.png';
		}

		// Return URL to dynamic generator.
		return add_query_arg(
			array(
				'bd_share_image' => 1,
				'business_id'    => $business_id,
			),
			home_url()
		);
	}

	/**
	 * Render meta tags.
	 *
	 * @param array $tags Tags array.
	 */
	private function render_tags( $tags ) {
		echo "\n<!-- Business Directory Open Graph Tags -->\n";

		foreach ( $tags as $property => $content ) {
			if ( empty( $content ) ) {
				continue;
			}

			// Determine if it's a name or property attribute.
			$attr = strpos( $property, ':' ) !== false ? 'property' : 'name';

			printf(
				'<meta %s="%s" content="%s" />' . "\n",
				esc_attr( $attr ),
				esc_attr( $property ),
				esc_attr( $content )
			);
		}

		echo "<!-- /Business Directory Open Graph Tags -->\n\n";
	}

	/**
	 * Generate OG tags for a review (used in share URLs).
	 *
	 * @param array $review Review data.
	 * @return array Tags.
	 */
	public static function generate_review_tags( $review ) {
		$business = get_post( $review['business_id'] );
		if ( ! $business ) {
			return array();
		}

		$stars = str_repeat( '⭐', (int) $review['rating'] );

		return array(
			'og:type'        => 'article',
			'og:title'       => sprintf(
				// translators: %1$s is stars, %2$s is business name.
				__( '%1$s Review of %2$s', 'business-directory' ),
				$stars,
				$business->post_title
			),
			'og:description' => wp_trim_words( $review['content'], 30 ),
			'og:url'         => get_permalink( $business->ID ) . '#review-' . $review['id'],
			'og:site_name'   => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Generate OG tags for a badge achievement.
	 *
	 * @param string $badge_key Badge key.
	 * @param array  $badge Badge data.
	 * @param int    $user_id User ID.
	 * @return array Tags.
	 */
	public static function generate_badge_tags( $badge_key, $badge, $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		return array(
			'og:type'        => 'article',
			'og:title'       => sprintf(
				// translators: %1$s is user name, %2$s is badge name, %3$s is site name.
				__( '%1$s earned the "%2$s" badge on %3$s!', 'business-directory' ),
				$user->display_name,
				$badge['name'],
				get_bloginfo( 'name' )
			),
			'og:description' => $badge['description'] ?? '',
			'og:site_name'   => get_bloginfo( 'name' ),
		);
	}
}
