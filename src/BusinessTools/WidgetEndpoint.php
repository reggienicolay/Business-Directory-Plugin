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
	exit; }

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

		// Build JS constants with PHP values, then use nowdoc for the rest (avoids PHP 8.2 ${} deprecation).
		$js_header  = "/**\n * LoveTriValley Review Widget\n * Embeddable review widget for business owners\n */\n(function() {\n\t'use strict';\n\n";
		$js_header .= "\tvar API_URL = '" . esc_js( $api_url ) . "';\n";
		$js_header .= "\tvar SITE_NAME = '" . esc_js( $site_name ) . "';\n";
		$js_header .= "\tvar SITE_URL = '" . esc_js( $site_url ) . "';\n";
		$js_header .= "\tvar COLORS = {\n";
		$js_header .= "\t\tprimary: '" . esc_js( $colors['primary'] ) . "',\n";
		$js_header .= "\t\tsecondary: '" . esc_js( $colors['secondary'] ) . "',\n";
		$js_header .= "\t\taccent: '" . esc_js( $colors['accent'] ) . "',\n";
		$js_header .= "\t\tlight: '" . esc_js( $colors['light'] ) . "',\n";
		$js_header .= "\t\ttext: '" . esc_js( $colors['text'] ) . "',\n";
		$js_header .= "\t\ttextLight: '" . esc_js( $colors['text_light'] ) . "',\n";
		$js_header .= "\t\tstar: '" . esc_js( $colors['star'] ) . "',\n";
		$js_header .= "\t\twhite: '" . esc_js( $colors['white'] ) . "',\n";
		$js_header .= "\t\tlightBg: '" . esc_js( $colors['light_bg'] ) . "'\n";
		$js_header .= "\t};\n";
		$js_header .= "\tvar AVATAR_COLORS = ['#1a3a4a','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#10b981','#ec4899','#06b6d4'];\n";

		$js_body = <<<'JAVASCRIPT'

	var STAR_PATH = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';

	function renderStars(rating, size) {
		size = size || 16;
		var html = '<span style="display:inline-flex;gap:2px;vertical-align:middle">';
		for (var i = 1; i <= 5; i++) {
			var fill;
			if (i <= Math.floor(rating)) {
				fill = COLORS.star;
			} else if (i === Math.ceil(rating) && rating % 1 > 0) {
				var pct = Math.round((rating % 1) * 100);
				fill = 'url(#ltv-partial-' + i + ')';
				html += '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24"><defs><linearGradient id="ltv-partial-' + i + '"><stop offset="' + pct + '%" stop-color="' + COLORS.star + '"/><stop offset="' + pct + '%" stop-color="#e2e8f0"/></linearGradient></defs><path d="' + STAR_PATH + '" fill="' + fill + '"/></svg>';
				continue;
			} else {
				fill = '#e2e8f0';
			}
			html += '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24"><path d="' + STAR_PATH + '" fill="' + fill + '"/></svg>';
		}
		html += '</span>';
		return html;
	}

	// Find all widget scripts on the page.
	var scripts = document.querySelectorAll('script[data-business]');

	scripts.forEach(function(script) {
		var businessId = script.getAttribute('data-business');
		var style = script.getAttribute('data-style') || 'compact';
		var theme = script.getAttribute('data-theme') || 'light';
		var reviewCount = parseInt(script.getAttribute('data-reviews') || '5', 10);
		var showBreakdown = script.getAttribute('data-breakdown') === '1';

		// Find or create container.
		var containerId = 'ltv-widget-' + businessId;
		var container = document.getElementById(containerId);

		if (!container) {
			container = document.createElement('div');
			container.id = containerId;
			script.parentNode.insertBefore(container, script);
		}

		loadWidget(container, businessId, style, theme, reviewCount, showBreakdown);
	});

	function loadWidget(container, businessId, style, theme, reviewCount, showBreakdown) {
		container.innerHTML = '<div style="padding:24px;text-align:center;color:' + COLORS.textLight + ';font-family:\'Source Sans 3\',system-ui,-apple-system,sans-serif;font-size:14px">Loading reviews...</div>';
		container.className = 'ltv-widget-container';

		fetch(API_URL + 'data/' + businessId + '?reviews=' + reviewCount)
			.then(function(response) {
				if (!response.ok) {
					return response.json().then(function(d) {
						throw new Error(d.message || 'Failed to load widget');
					});
				}
				return response.json();
			})
			.then(function(data) {
				renderWidget(container, data, style, theme, showBreakdown);
			})
			.catch(function(error) {
				container.innerHTML = '<div style="padding:20px;text-align:center;font-size:14px;color:#d63638;font-family:system-ui,sans-serif">' + error.message + '</div>';
			});
	}

	function renderWidget(container, data, style, theme, showBreakdown) {
		var isDark = theme === 'dark';
		var html = '';

		switch(style) {
			case 'compact':
				html = renderCompact(data, isDark);
				break;
			case 'carousel':
				html = renderCarousel(data, isDark);
				break;
			case 'list':
				html = renderList(data, isDark, showBreakdown);
				break;
			default:
				html = renderCompact(data, isDark);
		}

		container.innerHTML = html;
		injectStyles();

		if (style === 'carousel') {
			initCarousel(container);
		}

		// Track clicks.
		container.querySelectorAll('a').forEach(function(link) {
			link.addEventListener('click', function() {
				trackClick(data.business.id, link.classList.contains('ltv-review-btn') ? 'review' : 'view');
			});
		});

		// Responsive sizing via ResizeObserver.
		if (typeof ResizeObserver !== 'undefined') {
			var resizeObs = new ResizeObserver(function(entries) {
				var w = entries[0].contentRect.width;
				container.classList.toggle('ltv-narrow', w < 280);
				container.classList.toggle('ltv-wide', w >= 400);
			});
			resizeObs.observe(container);
			// Store for cleanup
			container._ltvResizeObs = resizeObs;
		}
	}

	function renderCompact(data, isDark) {
		var bg = isDark ? COLORS.primary : COLORS.white;
		var text = isDark ? COLORS.white : COLORS.text;
		var border = isDark ? COLORS.accent : '#e2e8f0';
		var mutedText = isDark ? COLORS.light : COLORS.textLight;
		var btnBg = isDark ? 'linear-gradient(135deg, #f59e0b, #d97706)' : 'linear-gradient(135deg, #1a3a4a, #1e4258)';
		var btnText = COLORS.white;

		return '<div style="max-width:300px;border-radius:16px;background:' + bg + ';border:1px solid ' + border + ';box-shadow:0 4px 24px rgba(0,0,0,' + (isDark?'0.3':'0.08') + ');padding:24px;font-family:\'Source Sans 3\',system-ui,-apple-system,sans-serif">' +
			'<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">' +
				'<span style="font-size:32px;font-weight:800;color:' + text + ';line-height:1">' + (data.rating.average || '\u2014') + '</span>' +
				'<div>' + renderStars(data.rating.average) +
				'<div style="font-size:12px;color:' + mutedText + ';margin-top:2px">' + data.rating.count + ' reviews</div></div>' +
			'</div>' +
			'<a href="' + data.review_url + '" target="_blank" rel="noopener" class="ltv-review-btn" style="display:block;text-align:center;padding:10px 24px;border-radius:24px;background:' + btnBg + ';color:' + btnText + ';text-decoration:none;font-size:14px;font-weight:600;transition:opacity 0.2s">Write a Review</a>' +
			'<div style="text-align:center;margin-top:14px;opacity:0.4;transition:opacity 0.2s"><a href="' + data.site_url + '" target="_blank" rel="noopener" style="font-size:11px;color:' + mutedText + ';text-decoration:none;display:inline-flex;align-items:center;gap:4px">' +
				'<svg width="10" height="10" viewBox="0 0 24 24" fill="' + mutedText + '"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>' +
				data.site_name + '</a></div>' +
		'</div>';
	}

	function renderCarousel(data, isDark) {
		if (!data.reviews || data.reviews.length === 0) {
			return renderCompact(data, isDark); // Fallback to compact
		}
		var bg = isDark ? COLORS.primary : COLORS.white;
		var text = isDark ? COLORS.white : COLORS.text;
		var border = isDark ? COLORS.accent : '#e2e8f0';
		var mutedText = isDark ? COLORS.light : COLORS.textLight;
		var cardBg = isDark ? COLORS.accent : COLORS.lightBg;
		var btnBg = isDark ? 'linear-gradient(135deg, #f59e0b, #d97706)' : 'linear-gradient(135deg, #1a3a4a, #1e4258)';

		var slidesHtml = '';
		data.reviews.forEach(function(review, index) {
			var avatarColor = AVATAR_COLORS[review.color_index || 0];
			var isActive = index === 0;
			var slideStyle = isActive
				? 'opacity:1;transform:translateX(0);position:relative;transition:opacity 0.4s ease,transform 0.4s ease'
				: 'opacity:0;transform:translateX(20px);position:absolute;top:0;left:0;right:0;pointer-events:none;transition:opacity 0.4s ease,transform 0.4s ease';

			slidesHtml += '<div class="ltv-slide" data-index="' + index + '" style="' + slideStyle + ';padding:0">' +
				'<div style="background:' + cardBg + ';border-radius:12px;border-left:4px solid #f59e0b;padding:20px">' +
					'<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">' +
						'<div style="width:36px;height:36px;border-radius:50%;background:' + avatarColor + ';display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0">' + (review.initial || 'A') + '</div>' +
						'<div><div style="font-weight:600;color:' + text + ';font-size:14px">' + review.author + '</div>' +
						'<div style="font-size:11px;color:' + mutedText + '">' + review.date + '</div></div>' +
					'</div>' +
					'<div style="margin-bottom:8px">' + renderStars(review.rating) + '</div>' +
					'<div style="font-size:14px;line-height:1.6;color:' + text + '">\u201c' + review.content + '\u201d</div>' +
				'</div>' +
			'</div>';
		});

		var dotsHtml = '';
		data.reviews.forEach(function(_, index) {
			var dotStyle = index === 0
				? 'width:10px;height:10px;border-radius:50%;background:' + COLORS.star + ';cursor:pointer;transition:all 0.3s'
				: 'width:10px;height:10px;border-radius:50%;background:transparent;border:2px solid ' + (isDark ? COLORS.light : '#cbd5e1') + ';cursor:pointer;transition:all 0.3s;box-sizing:border-box';
			dotsHtml += '<span class="ltv-dot" data-index="' + index + '" style="' + dotStyle + '"></span>';
		});

		return '<div style="max-width:420px;border-radius:16px;background:' + bg + ';border:1px solid ' + border + ';box-shadow:0 4px 24px rgba(0,0,0,' + (isDark?'0.3':'0.08') + ');padding:24px;font-family:\'Source Sans 3\',system-ui,-apple-system,sans-serif">' +
			'<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">' +
				'<span style="font-size:28px;font-weight:800;color:' + text + ';line-height:1">' + (data.rating.average || '\u2014') + '</span>' +
				'<div>' + renderStars(data.rating.average) +
				'<div style="font-size:12px;color:' + mutedText + ';margin-top:2px">' + data.rating.count + ' reviews</div></div>' +
			'</div>' +
			'<div class="ltv-slides" style="position:relative;min-height:140px;overflow:hidden">' + slidesHtml + '</div>' +
			'<div style="display:flex;align-items:center;justify-content:center;gap:16px;margin:20px 0 16px">' +
				'<button class="ltv-prev" style="background:none;border:none;font-size:20px;cursor:pointer;color:' + mutedText + ';padding:4px 8px;opacity:0.6;transition:opacity 0.2s">\u2039</button>' +
				'<div class="ltv-dots" style="display:flex;gap:8px;align-items:center">' + dotsHtml + '</div>' +
				'<button class="ltv-next" style="background:none;border:none;font-size:20px;cursor:pointer;color:' + mutedText + ';padding:4px 8px;opacity:0.6;transition:opacity 0.2s">\u203a</button>' +
			'</div>' +
			'<a href="' + data.review_url + '" target="_blank" rel="noopener" class="ltv-review-btn" style="display:block;text-align:center;padding:10px 24px;border-radius:24px;background:' + btnBg + ';color:#fff;text-decoration:none;font-size:14px;font-weight:600;transition:opacity 0.2s">Write a Review</a>' +
			'<div style="text-align:center;margin-top:14px;opacity:0.4;transition:opacity 0.2s"><a href="' + data.site_url + '" target="_blank" rel="noopener" style="font-size:11px;color:' + mutedText + ';text-decoration:none;display:inline-flex;align-items:center;gap:4px">' +
				'<svg width="10" height="10" viewBox="0 0 24 24" fill="' + mutedText + '"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>' +
				data.site_name + '</a></div>' +
		'</div>';
	}

	function renderList(data, isDark, showBreakdown) {
		if (!data.reviews || data.reviews.length === 0) {
			return renderCompact(data, isDark); // Fallback to compact
		}
		var bg = isDark ? COLORS.primary : COLORS.white;
		var text = isDark ? COLORS.white : COLORS.text;
		var border = isDark ? COLORS.accent : '#e2e8f0';
		var mutedText = isDark ? COLORS.light : COLORS.textLight;
		var cardBg = isDark ? COLORS.accent : COLORS.lightBg;
		var btnBg = isDark ? 'linear-gradient(135deg, #f59e0b, #d97706)' : 'linear-gradient(135deg, #1a3a4a, #1e4258)';

		// Header with business name and rating.
		var html = '<div style="max-width:520px;border-radius:16px;background:' + bg + ';border:1px solid ' + border + ';box-shadow:0 4px 24px rgba(0,0,0,' + (isDark?'0.3':'0.08') + ');padding:28px;font-family:\'Source Sans 3\',system-ui,-apple-system,sans-serif">';
		html += '<div style="margin-bottom:20px">';
		html += '<div style="font-weight:700;font-size:20px;color:' + text + ';margin-bottom:6px">' + data.business.name + '</div>';
		html += '<div style="display:flex;align-items:center;gap:8px">' + renderStars(data.rating.average, 18) +
			'<span style="font-weight:700;font-size:16px;color:' + text + '">' + (data.rating.average || '\u2014') + '</span>' +
			'<span style="font-size:13px;color:' + mutedText + '">' + data.rating.count + ' reviews</span></div>';
		html += '</div>';

		// Optional rating breakdown.
		if (showBreakdown && data.distribution) {
			var maxCnt = Math.max(data.distribution['5'] || 0, data.distribution['4'] || 0, data.distribution['3'] || 0, data.distribution['2'] || 0, data.distribution['1'] || 0, 1);
			html += '<div style="margin-bottom:20px;padding:16px;background:' + cardBg + ';border-radius:12px">';
			for (var s = 5; s >= 1; s--) {
				var cnt = data.distribution[String(s)] || 0;
				var barPct = Math.round((cnt / maxCnt) * 100);
				html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:' + (s > 1 ? '6' : '0') + 'px;font-size:13px">' +
					'<span style="width:20px;text-align:right;color:' + mutedText + ';font-weight:600">' + s + '\u2605</span>' +
					'<div style="flex:1;height:8px;background:' + (isDark ? 'rgba(255,255,255,0.1)' : '#e2e8f0') + ';border-radius:4px;overflow:hidden">' +
						'<div style="height:100%;width:' + barPct + '%;background:' + COLORS.star + ';border-radius:4px;transition:width 0.6s ease"></div>' +
					'</div>' +
					'<span style="width:24px;text-align:right;color:' + mutedText + ';font-size:12px">' + cnt + '</span>' +
				'</div>';
			}
			html += '</div>';
		}

		// Review cards.
		html += '<div class="ltv-reviews-list" style="max-height:400px;overflow-y:auto">';
		data.reviews.forEach(function(review) {
			var avatarColor = AVATAR_COLORS[review.color_index || 0];
			html += '<div class="ltv-review-card" style="background:' + cardBg + ';border-radius:12px;padding:16px;margin-bottom:12px;transition:transform 0.2s ease,box-shadow 0.2s ease">' +
				'<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">' +
					'<div style="width:36px;height:36px;border-radius:50%;background:' + avatarColor + ';display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0">' + (review.initial || 'A') + '</div>' +
					'<div style="flex:1"><div style="font-weight:600;color:' + text + ';font-size:14px">' + review.author + '</div>' +
					'<div style="font-size:11px;color:' + mutedText + '">' + review.date + '</div></div>' +
					renderStars(review.rating, 14) +
				'</div>' +
				'<div style="font-size:14px;line-height:1.6;color:' + text + '">\u201c' + review.content + '\u201d</div>' +
			'</div>';
		});
		html += '</div>';

		// Footer with action buttons.
		html += '<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-top:20px;padding-top:20px;border-top:1px solid ' + border + '">' +
			'<a href="' + data.review_url + '" target="_blank" rel="noopener" class="ltv-review-btn" style="display:inline-block;padding:10px 24px;border-radius:24px;background:' + btnBg + ';color:#fff;text-decoration:none;font-size:14px;font-weight:600;transition:opacity 0.2s">Write a Review</a>' +
			'<a href="' + data.business.url + '" target="_blank" rel="noopener" style="font-size:13px;color:' + (isDark ? COLORS.light : COLORS.secondary) + ';text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:opacity 0.2s">See all on ' + data.site_name + ' \u2192</a>' +
		'</div>';

		// Branding.
		html += '<div style="text-align:center;margin-top:14px;opacity:0.4;transition:opacity 0.2s"><a href="' + data.site_url + '" target="_blank" rel="noopener" style="font-size:11px;color:' + mutedText + ';text-decoration:none;display:inline-flex;align-items:center;gap:4px">' +
			'<svg width="10" height="10" viewBox="0 0 24 24" fill="' + mutedText + '"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>' +
			data.site_name + '</a></div>';

		html += '</div>';
		return html;
	}

	function initCarousel(container) {
		var slides = container.querySelectorAll('.ltv-slide');
		var dots = container.querySelectorAll('.ltv-dot');
		var prevBtn = container.querySelector('.ltv-prev');
		var nextBtn = container.querySelector('.ltv-next');
		var currentIndex = 0;
		var autoTimer = null;
		var paused = false;

		function transitionSlide(index) {
			slides.forEach(function(slide, i) {
				if (i === index) {
					slide.style.opacity = '1';
					slide.style.transform = 'translateX(0)';
					slide.style.position = 'relative';
					slide.style.pointerEvents = 'auto';
				} else {
					slide.style.opacity = '0';
					slide.style.transform = 'translateX(20px)';
					slide.style.position = 'absolute';
					slide.style.pointerEvents = 'none';
				}
			});
			dots.forEach(function(dot, i) {
				if (i === index) {
					dot.style.background = COLORS.star;
					dot.style.border = 'none';
				} else {
					dot.style.background = 'transparent';
					dot.style.border = '2px solid #cbd5e1';
				}
			});
			currentIndex = index;
		}

		function startAuto() {
			stopAuto();
			autoTimer = setInterval(function() {
				if (!paused) {
					transitionSlide((currentIndex + 1) % slides.length);
				}
			}, 5000);
		}

		function stopAuto() {
			if (autoTimer) {
				clearInterval(autoTimer);
				autoTimer = null;
			}
		}

		// Pause on hover, resume on leave.
		var slidesContainer = container.querySelector('.ltv-slides');
		if (slidesContainer) {
			slidesContainer.addEventListener('mouseenter', function() { paused = true; });
			slidesContainer.addEventListener('mouseleave', function() { paused = false; });
		}

		if (prevBtn) {
			prevBtn.addEventListener('click', function() {
				transitionSlide((currentIndex - 1 + slides.length) % slides.length);
				startAuto();
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener('click', function() {
				transitionSlide((currentIndex + 1) % slides.length);
				startAuto();
			});
		}

		dots.forEach(function(dot, index) {
			dot.addEventListener('click', function() {
				transitionSlide(index);
				startAuto();
			});
		});

		startAuto();

		// Cleanup on container removal
		if (typeof MutationObserver !== 'undefined') {
			var cleanupObserver = new MutationObserver(function(mutations) {
				mutations.forEach(function(m) {
					m.removedNodes.forEach(function(node) {
						if (node === container || node.contains && node.contains(container)) {
							clearInterval(autoTimer);
							cleanupObserver.disconnect();
						}
					});
				});
			});
			if (container.parentNode) {
				cleanupObserver.observe(container.parentNode, { childList: true });
			}
		}
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

		// Load Source Sans 3 font.
		if (!document.querySelector('link[href*="Source+Sans+3"]')) {
			var fontLink = document.createElement('link');
			fontLink.rel = 'stylesheet';
			fontLink.href = 'https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700;800&display=swap';
			document.head.appendChild(fontLink);
		}

		var css = '' +
			'.ltv-widget-container { font-family: "Source Sans 3", system-ui, -apple-system, sans-serif; line-height: 1.5; }' +
			'.ltv-widget-container * { box-sizing: border-box; }' +
			'.ltv-widget-container a { transition: opacity 0.2s ease; }' +
			'.ltv-widget-container a:hover { opacity: 0.85; }' +
			'.ltv-widget-container .ltv-review-btn:hover { opacity: 0.9; transform: translateY(-1px); }' +
			'.ltv-widget-container .ltv-review-card:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }' +
			'.ltv-widget-container .ltv-prev:hover, .ltv-widget-container .ltv-next:hover { opacity: 1; }' +
			/* Scrollbar styling */
			'.ltv-reviews-list::-webkit-scrollbar { width: 6px; }' +
			'.ltv-reviews-list::-webkit-scrollbar-track { background: transparent; }' +
			'.ltv-reviews-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }' +
			'.ltv-reviews-list::-webkit-scrollbar-thumb:hover { background: #94a3b8; }' +
			/* Responsive narrow overrides */
			'.ltv-narrow .ltv-review-card { padding: 12px; }' +
			'.ltv-narrow .ltv-dots { gap: 6px; }' +
			/* Responsive wide overrides */
			'.ltv-wide .ltv-reviews-list { max-height: 500px; }' +
			'';

		var style = document.createElement('style');
		style.id = 'ltv-widget-styles';
		style.textContent = css;
		document.head.appendChild(style);
	}
})();
JAVASCRIPT;

		$js = $js_header . $js_body;
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
