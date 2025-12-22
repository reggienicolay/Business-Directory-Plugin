<?php
/**
 * Widget Endpoint
 *
 * REST API endpoint for serving embeddable widget.
 *
 * @package BusinessDirectory
 */

namespace BD\BusinessTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WidgetEndpoint
 */
class WidgetEndpoint {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Widget JavaScript file.
		register_rest_route(
			'bd/v1',
			'/widget/embed.js',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'serve_widget_script' ),
				'permission_callback' => '__return_true',
			)
		);

		// Widget data endpoint.
		register_rest_route(
			'bd/v1',
			'/widget/data/(?P<business_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_widget_data' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'business_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Track widget click.
		register_rest_route(
			'bd/v1',
			'/widget/click',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_click' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'business_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'action'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Serve widget JavaScript.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function serve_widget_script( $request ) {
		$js = $this->get_widget_js();

		$response = new \WP_REST_Response( $js );
		$response->header( 'Content-Type', 'application/javascript; charset=utf-8' );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		// CORS headers - allow from anywhere (domain check happens in data endpoint).
		$response->header( 'Access-Control-Allow-Origin', '*' );

		return $response;
	}

	/**
	 * Get widget data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_widget_data( $request ) {
		$business_id = $request->get_param( 'business_id' );
		$reviews     = $request->get_param( 'reviews' ) ?: 5;

		// Check domain whitelist.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$domain  = '';

		if ( $referer ) {
			$parsed = wp_parse_url( $referer );
			$domain = $parsed['host'] ?? '';
		}

		if ( ! WidgetGenerator::is_domain_allowed( $business_id, $domain ) ) {
			return new \WP_REST_Response(
				array(
					'error'   => 'domain_not_allowed',
					'message' => __( 'This widget is not authorized for this domain.', 'business-directory' ),
				),
				403
			);
		}

		$data = WidgetGenerator::get_widget_data( $business_id, $reviews );

		$response = new \WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'public, max-age=3600' );
		$response->header( 'Access-Control-Allow-Origin', '*' );

		return $response;
	}

	/**
	 * Track widget click.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function track_click( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_widget_clicks';

		$business_id = $request->get_param( 'business_id' );
		$action      = $request->get_param( 'action' );

		// Get referer domain.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$domain  = '';
		if ( $referer ) {
			$parsed = wp_parse_url( $referer );
			$domain = $parsed['host'] ?? '';
		}

		// Get IP.
		$ip = $this->get_client_ip();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'business_id' => $business_id,
				'action'      => $action,
				'domain'      => $domain,
				'ip_address'  => $ip,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Get widget JavaScript code.
	 *
	 * @return string JavaScript code.
	 */
	private function get_widget_js() {
		$api_url   = rest_url( 'bd/v1/widget/' );
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// Brand colors.
		$colors = array(
			'primary'    => '#1a3a4a',
			'secondary'  => '#7a9eb8',
			'accent'     => '#1e4258',
			'light'      => '#a8c4d4',
			'text'       => '#1a1a1a',
			'text_light' => '#5d7a8c',
			'star'       => '#f59e0b',
			'white'      => '#ffffff',
			'light_bg'   => '#f0f5f8',
		);

		$js = <<<JAVASCRIPT
/**
 * LoveTriValley Review Widget
 * Embeddable review widget for business owners
 */
(function() {
	'use strict';

	const API_URL = '{$api_url}';
	const SITE_NAME = '{$site_name}';
	const SITE_URL = '{$site_url}';
	const COLORS = {
		primary: '{$colors['primary']}',
		secondary: '{$colors['secondary']}',
		accent: '{$colors['accent']}',
		light: '{$colors['light']}',
		text: '{$colors['text']}',
		textLight: '{$colors['text_light']}',
		star: '{$colors['star']}',
		white: '{$colors['white']}',
		lightBg: '{$colors['light_bg']}'
	};

	// Find all widget scripts
	const scripts = document.querySelectorAll('script[data-business]');
	
	scripts.forEach(function(script) {
		const businessId = script.getAttribute('data-business');
		const style = script.getAttribute('data-style') || 'compact';
		const theme = script.getAttribute('data-theme') || 'light';
		const reviewCount = parseInt(script.getAttribute('data-reviews') || '5');
		
		// Find container
		const containerId = 'ltv-widget-' + businessId;
		let container = document.getElementById(containerId);
		
		if (!container) {
			container = document.createElement('div');
			container.id = containerId;
			script.parentNode.insertBefore(container, script);
		}
		
		// Load widget data
		loadWidget(container, businessId, style, theme, reviewCount);
	});

	function loadWidget(container, businessId, style, theme, reviewCount) {
		// Show loading state
		container.innerHTML = '<div class="ltv-loading">Loading reviews...</div>';
		container.className = 'ltv-widget-container';
		
		// Fetch data
		fetch(API_URL + 'data/' + businessId + '?reviews=' + reviewCount)
			.then(function(response) {
				if (!response.ok) {
					return response.json().then(function(data) {
						throw new Error(data.message || 'Failed to load widget');
					});
				}
				return response.json();
			})
			.then(function(data) {
				renderWidget(container, data, style, theme);
			})
			.catch(function(error) {
				container.innerHTML = '<div class="ltv-error">' + error.message + '</div>';
			});
	}

	function renderWidget(container, data, style, theme) {
		const isDark = theme === 'dark';
		const bgColor = isDark ? COLORS.primary : COLORS.white;
		const textColor = isDark ? COLORS.white : COLORS.text;
		const borderColor = isDark ? COLORS.accent : COLORS.light;
		
		let html = '';
		
		switch(style) {
			case 'compact':
				html = renderCompact(data, isDark);
				break;
			case 'carousel':
				html = renderCarousel(data, isDark);
				break;
			case 'list':
				html = renderList(data, isDark);
				break;
			default:
				html = renderCompact(data, isDark);
		}
		
		container.innerHTML = html;
		
		// Inject styles
		injectStyles();
		
		// Initialize carousel if needed
		if (style === 'carousel') {
			initCarousel(container);
		}
		
		// Track clicks
		container.querySelectorAll('a').forEach(function(link) {
			link.addEventListener('click', function() {
				trackClick(data.business.id, link.classList.contains('ltv-review-btn') ? 'review' : 'view');
			});
		});
	}

	function renderCompact(data, isDark) {
		const stars = '★'.repeat(Math.round(data.rating.average)) + '☆'.repeat(5 - Math.round(data.rating.average));
		const themeClass = isDark ? 'ltv-dark' : 'ltv-light';
		
		return '<div class="ltv-widget ltv-compact ' + themeClass + '">' +
			'<div class="ltv-rating">' +
				'<span class="ltv-stars">' + stars + '</span>' +
				'<span class="ltv-rating-num">' + data.rating.average + '</span>' +
				'<span class="ltv-rating-count">(' + data.rating.count + ' reviews)</span>' +
			'</div>' +
			'<a href="' + data.review_url + '" class="ltv-review-btn" target="_blank">Write a Review</a>' +
			'<div class="ltv-branding">' +
				'<a href="' + data.site_url + '" target="_blank">📍 ' + data.site_name + '</a>' +
			'</div>' +
		'</div>';
	}

	function renderCarousel(data, isDark) {
		const themeClass = isDark ? 'ltv-dark' : 'ltv-light';
		let slidesHtml = '';
		
		data.reviews.forEach(function(review, index) {
			const stars = '★'.repeat(review.rating);
			slidesHtml += '<div class="ltv-slide' + (index === 0 ? ' ltv-active' : '') + '">' +
				'<div class="ltv-review-content">"' + review.content + '"</div>' +
				'<div class="ltv-review-meta">' +
					'<span class="ltv-stars">' + stars + '</span>' +
					' — ' + review.author +
				'</div>' +
			'</div>';
		});
		
		let dotsHtml = '';
		data.reviews.forEach(function(_, index) {
			dotsHtml += '<span class="ltv-dot' + (index === 0 ? ' ltv-active' : '') + '" data-index="' + index + '"></span>';
		});
		
		return '<div class="ltv-widget ltv-carousel ' + themeClass + '">' +
			'<div class="ltv-slides">' + slidesHtml + '</div>' +
			'<div class="ltv-carousel-nav">' +
				'<button class="ltv-prev">‹</button>' +
				'<div class="ltv-dots">' + dotsHtml + '</div>' +
				'<button class="ltv-next">›</button>' +
			'</div>' +
			'<div class="ltv-carousel-footer">' +
				'<a href="' + data.review_url + '" class="ltv-review-btn" target="_blank">Write a Review</a>' +
			'</div>' +
			'<div class="ltv-branding">' +
				'<a href="' + data.site_url + '" target="_blank">📍 ' + data.site_name + '</a>' +
			'</div>' +
		'</div>';
	}

	function renderList(data, isDark) {
		const themeClass = isDark ? 'ltv-dark' : 'ltv-light';
		const avgStars = '★'.repeat(Math.round(data.rating.average));
		
		let reviewsHtml = '';
		data.reviews.forEach(function(review) {
			const stars = '★'.repeat(review.rating);
			reviewsHtml += '<div class="ltv-review-item">' +
				'<div class="ltv-review-header">' +
					'<span class="ltv-stars">' + stars + '</span>' +
					'<span class="ltv-review-date">' + review.date + '</span>' +
				'</div>' +
				'<div class="ltv-review-text">"' + review.content + '"</div>' +
				'<div class="ltv-review-author">— ' + review.author + '</div>' +
			'</div>';
		});
		
		return '<div class="ltv-widget ltv-list ' + themeClass + '">' +
			'<div class="ltv-header">' +
				'<div class="ltv-business-name">' + data.business.name + '</div>' +
				'<div class="ltv-header-rating">' +
					'<span class="ltv-stars">' + avgStars + '</span>' +
					' ' + data.rating.average + ' · ' + data.rating.count + ' reviews' +
				'</div>' +
			'</div>' +
			'<div class="ltv-reviews-list">' + reviewsHtml + '</div>' +
			'<div class="ltv-footer">' +
				'<a href="' + data.review_url + '" class="ltv-review-btn" target="_blank">Write a Review</a>' +
				'<a href="' + data.business.url + '" class="ltv-view-all" target="_blank">See All Reviews →</a>' +
			'</div>' +
			'<div class="ltv-branding">' +
				'<a href="' + data.site_url + '" target="_blank">📍 ' + data.site_name + '</a>' +
			'</div>' +
		'</div>';
	}

	function initCarousel(container) {
		const slides = container.querySelectorAll('.ltv-slide');
		const dots = container.querySelectorAll('.ltv-dot');
		const prevBtn = container.querySelector('.ltv-prev');
		const nextBtn = container.querySelector('.ltv-next');
		let currentIndex = 0;
		
		function showSlide(index) {
			slides.forEach(function(slide, i) {
				slide.classList.toggle('ltv-active', i === index);
			});
			dots.forEach(function(dot, i) {
				dot.classList.toggle('ltv-active', i === index);
			});
			currentIndex = index;
		}
		
		if (prevBtn) {
			prevBtn.addEventListener('click', function() {
				showSlide((currentIndex - 1 + slides.length) % slides.length);
			});
		}
		
		if (nextBtn) {
			nextBtn.addEventListener('click', function() {
				showSlide((currentIndex + 1) % slides.length);
			});
		}
		
		dots.forEach(function(dot, index) {
			dot.addEventListener('click', function() {
				showSlide(index);
			});
		});
		
		// Auto-rotate
		setInterval(function() {
			showSlide((currentIndex + 1) % slides.length);
		}, 5000);
	}

	function trackClick(businessId, action) {
		fetch(API_URL + 'click', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ business_id: businessId, action: action })
		}).catch(function() {});
	}

	function injectStyles() {
		if (document.getElementById('ltv-widget-styles')) return;
		
		const css = \`
			.ltv-widget-container { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
			.ltv-widget { border-radius: 12px; padding: 20px; box-sizing: border-box; }
			.ltv-light { background: ${COLORS.white}; color: ${COLORS.text}; border: 1px solid ${COLORS.light}; }
			.ltv-dark { background: ${COLORS.primary}; color: ${COLORS.white}; border: 1px solid ${COLORS.accent}; }
			.ltv-stars { color: ${COLORS.star}; letter-spacing: 2px; }
			.ltv-rating { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
			.ltv-rating-num { font-weight: 700; font-size: 18px; }
			.ltv-rating-count { opacity: 0.7; font-size: 14px; }
			.ltv-review-btn { display: inline-block; background: ${COLORS.primary}; color: ${COLORS.white}; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; transition: background 0.2s; }
			.ltv-review-btn:hover { background: ${COLORS.accent}; }
			.ltv-dark .ltv-review-btn { background: ${COLORS.secondary}; color: ${COLORS.primary}; }
			.ltv-dark .ltv-review-btn:hover { background: ${COLORS.light}; }
			.ltv-branding { margin-top: 12px; font-size: 12px; opacity: 0.7; }
			.ltv-branding a { color: inherit; text-decoration: none; }
			.ltv-branding a:hover { text-decoration: underline; }
			
			/* Compact */
			.ltv-compact { text-align: center; max-width: 280px; }
			
			/* Carousel */
			.ltv-carousel { max-width: 400px; }
			.ltv-slides { position: relative; min-height: 120px; }
			.ltv-slide { display: none; }
			.ltv-slide.ltv-active { display: block; }
			.ltv-review-content { font-style: italic; margin-bottom: 8px; line-height: 1.5; }
			.ltv-review-meta { font-size: 14px; opacity: 0.8; }
			.ltv-carousel-nav { display: flex; align-items: center; justify-content: center; gap: 12px; margin: 16px 0; }
			.ltv-prev, .ltv-next { background: none; border: none; font-size: 24px; cursor: pointer; padding: 4px 8px; opacity: 0.6; }
			.ltv-prev:hover, .ltv-next:hover { opacity: 1; }
			.ltv-dark .ltv-prev, .ltv-dark .ltv-next { color: ${COLORS.white}; }
			.ltv-dots { display: flex; gap: 6px; }
			.ltv-dot { width: 8px; height: 8px; border-radius: 50%; background: ${COLORS.light}; cursor: pointer; }
			.ltv-dot.ltv-active { background: ${COLORS.secondary}; }
			.ltv-carousel-footer { text-align: center; }
			
			/* List */
			.ltv-list { max-width: 500px; }
			.ltv-header { border-bottom: 1px solid ${COLORS.light}; padding-bottom: 12px; margin-bottom: 16px; }
			.ltv-dark .ltv-header { border-color: ${COLORS.accent}; }
			.ltv-business-name { font-weight: 700; font-size: 18px; margin-bottom: 4px; }
			.ltv-header-rating { font-size: 14px; opacity: 0.8; }
			.ltv-reviews-list { max-height: 300px; overflow-y: auto; }
			.ltv-review-item { padding: 12px 0; border-bottom: 1px solid ${COLORS.lightBg}; }
			.ltv-dark .ltv-review-item { border-color: ${COLORS.accent}; }
			.ltv-review-item:last-child { border-bottom: none; }
			.ltv-review-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
			.ltv-review-date { font-size: 12px; opacity: 0.6; }
			.ltv-review-text { font-style: italic; line-height: 1.5; margin-bottom: 8px; }
			.ltv-review-author { font-size: 13px; opacity: 0.7; }
			.ltv-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid ${COLORS.light}; }
			.ltv-dark .ltv-footer { border-color: ${COLORS.accent}; }
			.ltv-view-all { font-size: 14px; color: ${COLORS.secondary}; text-decoration: none; }
			.ltv-view-all:hover { text-decoration: underline; }
			
			/* Loading/Error */
			.ltv-loading, .ltv-error { padding: 20px; text-align: center; font-size: 14px; color: ${COLORS.textLight}; }
			.ltv-error { color: #d63638; }
		\`;
		
		const style = document.createElement('style');
		style.id = 'ltv-widget-styles';
		style.textContent = css;
		document.head.appendChild(style);
	}
})();
JAVASCRIPT;

		return $js;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return '0.0.0.0';
	}
}
