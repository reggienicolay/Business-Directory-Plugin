<?php
/**
 * Feature Shortcode - Embed businesses in posts/pages
 * Usage: [bd_feature id="123"], [bd_feature ids="45,23,89" layout="card" columns="3"]
 */
namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeatureShortcode {
	const CACHE_DURATION = 3600; // 1 hour

	public static function init() {
		add_shortcode( 'bd_feature', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => '',
				'ids'      => '',
				'layout'   => 'card',
				'columns'  => '3',
				'show'     => 'image,title,rating,excerpt,category,cta',
				'cta_text' => 'View Details',
				'source'   => '',
				'class'    => '',
			),
			$atts,
			'bd_feature'
		);

		$ids = array();
		if ( ! empty( $atts['id'] ) ) {
			$ids = array( absint( $atts['id'] ) );
		} elseif ( ! empty( $atts['ids'] ) ) {
			$ids = array_map( 'absint', explode( ',', $atts['ids'] ) );
		}
		$ids = array_filter( $ids );
		if ( empty( $ids ) ) {
			return '<!-- BD Feature: No valid IDs -->';
		}

		$show = array_map( 'trim', explode( ',', $atts['show'] ) );
		self::enqueue_assets();

		$businesses = self::get_businesses( $ids, $atts['source'] );
		if ( empty( $businesses ) ) {
			return '<!-- BD Feature: No businesses found -->';
		}

		switch ( $atts['layout'] ) {
			case 'list':
				return self::render_list( $businesses, $show, $atts );
			case 'inline':
				return self::render_inline( $businesses, $show, $atts );
			case 'mini':
				return self::render_mini( $businesses, $atts );
			default:
				return self::render_card( $businesses, $show, $atts );
		}
	}

	private static function get_businesses( $ids, $source = '' ) {
		if ( empty( $source ) || self::is_local( $source ) ) {
			return self::get_local( $ids );
		}
		return self::get_remote( $ids, $source );
	}

	private static function is_local( $source ) {
		$current = wp_parse_url( home_url(), PHP_URL_HOST );
		$src     = str_replace( array( 'http://', 'https://', 'www.' ), '', $source );
		return $current === $src || str_replace( 'www.', '', $current ) === $src;
	}

	private static function get_local( $ids ) {
		$businesses = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_status !== 'publish' || $post->post_type !== 'bd_business' ) {
				continue;
			}
			$location = get_post_meta( $id, 'bd_location', true );
			$city     = is_array( $location ) && ! empty( $location['city'] ) ? $location['city'] : '';
			$cats     = wp_get_post_terms( $id, 'bd_category', array( 'fields' => 'names' ) );
			$excerpt  = $post->post_excerpt ?: wp_trim_words( strip_shortcodes( $post->post_content ), 20, '...' );

			$businesses[] = array(
				'id'             => $id,
				'title'          => $post->post_title,
				'excerpt'        => $excerpt,
				'permalink'      => get_permalink( $id ),
				'featured_image' => array(
					'thumbnail' => get_the_post_thumbnail_url( $id, 'thumbnail' ),
					'medium'    => get_the_post_thumbnail_url( $id, 'medium' ),
				),
				'rating'         => floatval( get_post_meta( $id, 'bd_avg_rating', true ) ),
				'review_count'   => intval( get_post_meta( $id, 'bd_review_count', true ) ),
				'price_level'    => get_post_meta( $id, 'bd_price_level', true ),
				'categories'     => $cats,
				'city'           => $city,
			);
		}
		return $businesses;
	}

	private static function get_remote( $ids, $source ) {
		$source = rtrim( $source, '/' );
		if ( strpos( $source, 'http' ) !== 0 ) {
			$source = 'https://' . $source;
		}

		$cache_key = 'bd_feature_' . md5( $source . implode( ',', $ids ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( $source . '/wp-json/bd/v1/feature?ids=' . implode( ',', $ids ), array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $data['businesses'] ) ) {
			return array();
		}

		set_transient( $cache_key, $data['businesses'], self::CACHE_DURATION );
		return $data['businesses'];
	}

	private static function render_card( $businesses, $show, $atts ) {
		$cols  = max( 1, min( 4, absint( $atts['columns'] ) ) );
		$class = 'bd-feature-grid bd-feature-columns-' . $cols . ( $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '' );

		$html = '<div class="' . $class . '">';
		foreach ( $businesses as $b ) {
			$html .= '<div class="bd-feature-card">';
			if ( in_array( 'image', $show ) && ! empty( $b['featured_image']['medium'] ) ) {
				$html .= '<a href="' . esc_url( $b['permalink'] ) . '" class="bd-feature-image-link"><img src="' . esc_url( $b['featured_image']['medium'] ) . '" alt="' . esc_attr( $b['title'] ) . '" class="bd-feature-image" loading="lazy"></a>';
			}
			$html .= '<div class="bd-feature-content">';
			if ( in_array( 'title', $show ) ) {
				$html .= '<h3 class="bd-feature-title"><a href="' . esc_url( $b['permalink'] ) . '">' . esc_html( $b['title'] ) . '</a></h3>';
			}
			if ( in_array( 'rating', $show ) && ! empty( $b['rating'] ) ) {
				$html .= '<div class="bd-feature-rating">' . self::stars( $b['rating'] );
				if ( ! empty( $b['review_count'] ) ) {
					$html .= '<span class="bd-feature-review-count">(' . intval( $b['review_count'] ) . ')</span>';
				}
				$html .= '</div>';
			}
			if ( in_array( 'excerpt', $show ) && ! empty( $b['excerpt'] ) ) {
				$html .= '<p class="bd-feature-excerpt">' . esc_html( $b['excerpt'] ) . '</p>';
			}
			if ( in_array( 'category', $show ) ) {
				$meta = array();
				if ( ! empty( $b['price_level'] ) ) {
					$meta[] = esc_html( $b['price_level'] );
				}
				if ( ! empty( $b['categories'] ) ) {
					$meta[] = esc_html( $b['categories'][0] );
				}
				if ( ! empty( $b['city'] ) ) {
					$meta[] = esc_html( $b['city'] );
				}
				if ( $meta ) {
					$html .= '<div class="bd-feature-meta">' . implode( ' • ', $meta ) . '</div>';
				}
			}
			if ( in_array( 'cta', $show ) ) {
				$html .= '<a href="' . esc_url( $b['permalink'] ) . '" class="bd-feature-cta">' . esc_html( $atts['cta_text'] ) . ' →</a>';
			}
			$html .= '</div></div>';
		}
		return $html . '</div>';
	}

	private static function render_list( $businesses, $show, $atts ) {
		$html = '<div class="bd-feature-list' . ( $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '' ) . '">';
		foreach ( $businesses as $b ) {
			$html .= '<div class="bd-feature-list-item">';
			if ( in_array( 'image', $show ) && ! empty( $b['featured_image']['thumbnail'] ) ) {
				$html .= '<a href="' . esc_url( $b['permalink'] ) . '" class="bd-feature-list-thumb"><img src="' . esc_url( $b['featured_image']['thumbnail'] ) . '" alt="' . esc_attr( $b['title'] ) . '" loading="lazy"></a>';
			}
			$html .= '<div class="bd-feature-list-info">';
			if ( in_array( 'title', $show ) ) {
				$html .= '<h4 class="bd-feature-list-title"><a href="' . esc_url( $b['permalink'] ) . '">' . esc_html( $b['title'] ) . '</a></h4>';
			}
			if ( in_array( 'excerpt', $show ) && ! empty( $b['excerpt'] ) ) {
				$html .= '<p class="bd-feature-list-excerpt">' . esc_html( wp_trim_words( $b['excerpt'], 15, '...' ) ) . '</p>';
			}
			if ( in_array( 'category', $show ) && ! empty( $b['categories'] ) ) {
				$html .= '<span class="bd-feature-list-category">' . esc_html( $b['categories'][0] ) . '</span>';
			}
			$html .= '</div><div class="bd-feature-list-meta">';
			if ( in_array( 'rating', $show ) && ! empty( $b['rating'] ) ) {
				$html .= '<div class="bd-feature-list-rating">' . self::stars( $b['rating'] ) . '</div>';
			}
			if ( in_array( 'cta', $show ) ) {
				$html .= '<a href="' . esc_url( $b['permalink'] ) . '" class="bd-feature-list-cta">View →</a>';
			}
			$html .= '</div></div>';
		}
		return $html . '</div>';
	}

	private static function render_inline( $businesses, $show, $atts ) {
		$items = array();
		foreach ( $businesses as $b ) {
			$item = '<span class="bd-feature-inline"><a href="' . esc_url( $b['permalink'] ) . '" class="bd-feature-inline-link">' . esc_html( $b['title'] ) . '</a>';
			if ( in_array( 'rating', $show ) && ! empty( $b['rating'] ) ) {
				$item .= ' <span class="bd-feature-inline-rating">' . self::stars( $b['rating'], true ) . '</span>';
			}
			$items[] = $item . '</span>';
		}
		return implode( ', ', $items );
	}

	private static function render_mini( $businesses, $atts ) {
		$items = array();
		foreach ( $businesses as $b ) {
			$items[] = '<a href="' . esc_url( $b['permalink'] ) . '" class="bd-feature-mini">' . esc_html( $b['title'] ) . ' →</a>';
		}
		return '<span class="bd-feature-mini-list">' . implode( count( $businesses ) > 3 ? ' | ' : ', ', $items ) . '</span>';
	}

	private static function stars( $rating, $compact = false ) {
		$rating = floatval( $rating );
		if ( $compact ) {
			return '<span class="bd-stars-compact">' . number_format( $rating, 1 ) . ' ★</span>';
		}
		$full  = floor( $rating );
		$half  = ( $rating - $full ) >= 0.5 ? 1 : 0;
		$empty = 5 - $full - $half;
		return '<span class="bd-stars">' . str_repeat( '<span class="bd-star-filled">★</span>', $full ) . ( $half ? '<span class="bd-star-half">½</span>' : '' ) . str_repeat( '<span class="bd-star-empty">☆</span>', $empty ) . '</span>';
	}

	private static function enqueue_assets() {
		wp_enqueue_style( 'bd-feature-embed', BD_PLUGIN_URL . 'assets/css/feature-embed.css', array(), BD_VERSION );
	}
}

FeatureShortcode::init();
