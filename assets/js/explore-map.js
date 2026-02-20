/**
 * Explore Pages — Leaflet Map
 *
 * Lightweight map initializer for explore city and intersection pages.
 * Uses the "Love" brand heart pin markers (bd-heart-icon / bd-heart-marker)
 * and MarkerCluster rollups (bd-cluster) matching the main directory
 * and lists map styles.
 *
 * Reads business marker data from an inline JSON script block,
 * renders markers with popups, and fits bounds.
 *
 * @package    BusinessDirectory
 * @subpackage Explore
 * @since      2.2.0
 */

(function () {
	'use strict';

	var mapEl = document.getElementById('bd-explore-map');
	if (!mapEl || typeof L === 'undefined') {
		return;
	}

	var dataEl = document.getElementById('bd-explore-map-data');
	if (!dataEl) {
		return;
	}

	var businesses;
	try {
		businesses = JSON.parse(dataEl.textContent);
	} catch (e) {
		return;
	}

	if (!businesses || !businesses.length) {
		mapEl.style.display = 'none';
		return;
	}

	// Filter to only businesses with valid coordinates.
	var pins = businesses.filter(function (b) {
		return b.lat && b.lng && b.lat !== 0 && b.lng !== 0;
	});

	if (!pins.length) {
		mapEl.style.display = 'none';
		return;
	}

	// Initialize map.
	var map = L.map('bd-explore-map', {
		scrollWheelZoom: false,
		zoomControl: true
	});

	// Tile layer — CartoDB Voyager (clean, muted — matches directory).
	L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
		subdomains: 'abcd',
		maxZoom: 19
	}).addTo(map);

	/**
	 * Heart pin icon — matches bd-heart-icon / bd-heart-marker from map-markers.css.
	 * Navy fill (#0F2A43) with teal hover (#2CB1BC), drop shadow.
	 * Same SVG path used in quick-filters.js, business-directory.js, and lists.js.
	 */
	var heartIcon = L.divIcon({
		className: 'bd-heart-icon',
		html: '<div class="bd-heart-marker">' +
			'<svg viewBox="0 0 24 24" fill="currentColor">' +
			'<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>' +
			'</svg>' +
			'</div>',
		iconSize: [32, 32],
		iconAnchor: [16, 32],
		popupAnchor: [0, -32]
	});

	/**
	 * MarkerCluster — always-on rollups matching directory and lists maps.
	 * Uses bd-cluster / bd-cluster-icon classes from map-markers.css.
	 * Sizes: small (<10), medium (10-25), large (25+) at 36/44/52px.
	 * Falls back to individual markers if MarkerCluster isn't loaded.
	 */
	var useCluster = typeof L.markerClusterGroup === 'function';
	var cluster;

	if (useCluster) {
		cluster = L.markerClusterGroup({
			maxClusterRadius: 50,
			spiderfyOnMaxZoom: true,
			showCoverageOnHover: false,
			iconCreateFunction: function (c) {
				var count = c.getChildCount();
				var size = 'small';
				if (count > 10) size = 'medium';
				if (count > 25) size = 'large';

				var px = size === 'small' ? 36 : size === 'medium' ? 44 : 52;

				return L.divIcon({
					html: '<div class="bd-cluster bd-cluster-' + size + '"><span>' + count + '</span></div>',
					className: 'bd-cluster-icon',
					iconSize: L.point(px, px)
				});
			}
		});
	}

	/**
	 * Escape a string for safe HTML insertion.
	 * Prevents XSS from business titles, URLs, or addresses
	 * that may contain user-supplied content.
	 */
	function esc(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	// Add markers.
	var bounds = L.latLngBounds();

	pins.forEach(function (b) {
		var latLng = L.latLng(b.lat, b.lng);
		bounds.extend(latLng);

		var popupHtml =
			'<div class="bd-explore-popup">' +
				(b.image ? '<div class="bd-explore-popup-img"><img src="' + esc(b.image) + '" alt="' + esc(b.title) + '" loading="lazy"></div>' : '') +
				'<div class="bd-explore-popup-body">' +
					'<strong class="bd-explore-popup-title">' +
						'<a href="' + esc(b.url) + '">' + esc(b.title) + '</a>' +
					'</strong>' +
					(b.rating > 0 ? '<div class="bd-explore-popup-rating">' + '★'.repeat(Math.round(b.rating)) + ' ' + b.rating.toFixed(1) + '</div>' : '') +
					(b.address ? '<div class="bd-explore-popup-addr">' + esc(b.address) + '</div>' : '') +
				'</div>' +
			'</div>';

		var marker = L.marker(latLng, { icon: heartIcon })
			.bindPopup(popupHtml, { maxWidth: 280, minWidth: 200 });

		if (useCluster) {
			cluster.addLayer(marker);
		} else {
			marker.addTo(map);
		}
	});

	// Add cluster layer to map.
	if (useCluster) {
		map.addLayer(cluster);
	}

	// Fit map to markers with padding.
	if (bounds.isValid()) {
		map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
	}

	// Re-enable scroll zoom after first interaction.
	map.once('focus', function () {
		map.scrollWheelZoom.enable();
	});

})();
