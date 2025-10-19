/**
 * Business Directory - Admin Map (Leaflet)
 */

(function ($) {
	'use strict';

	let map    = null;
	let marker = null;

	function initMap() {
		const mapContainer = document.getElementById( 'bd-map' );

		if ( ! mapContainer) {
			return;
		}

		// Get existing coordinates or default to Austin, TX
		const lat = parseFloat( $( '#bd_lat' ).val() ) || 30.2672;
		const lng = parseFloat( $( '#bd_lng' ).val() ) || -97.7431;

		// Initialize Leaflet map
		map = L.map( 'bd-map' ).setView( [lat, lng], 13 );

		// Add OpenStreetMap tiles
		L.tileLayer(
			'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			{
				attribution: '© OpenStreetMap contributors',
				maxZoom: 19
			}
		).addTo( map );

		// Add marker if coordinates exist
		if ($( '#bd_lat' ).val() && $( '#bd_lng' ).val()) {
			marker = L.marker(
				[lat, lng],
				{
					draggable: true
				}
			).addTo( map );

			marker.on(
				'dragend',
				function (e) {
					updateCoordinates( e.target.getLatLng() );
				}
			);
		}

		// Click to add/move marker
		map.on(
			'click',
			function (e) {
				if ( ! marker) {
					marker = L.marker(
						e.latlng,
						{
							draggable: true
						}
					).addTo( map );

					marker.on(
						'dragend',
						function (e) {
							updateCoordinates( e.target.getLatLng() );
						}
					);
				} else {
					marker.setLatLng( e.latlng );
				}

				updateCoordinates( e.latlng );
			}
		);

		// Manual coordinate input
		$( '#bd_lat, #bd_lng' ).on(
			'change',
			function () {
				const newLat = parseFloat( $( '#bd_lat' ).val() );
				const newLng = parseFloat( $( '#bd_lng' ).val() );

				if ( ! isNaN( newLat ) && ! isNaN( newLng )) {
					const newLatLng = L.latLng( newLat, newLng );

					if ( ! marker) {
						marker = L.marker(
							newLatLng,
							{
								draggable: true
							}
						).addTo( map );

						marker.on(
							'dragend',
							function (e) {
								updateCoordinates( e.target.getLatLng() );
							}
						);
					} else {
						marker.setLatLng( newLatLng );
					}

					map.setView( newLatLng, 13 );
				}
			}
		);
	}

	function updateCoordinates(latlng) {
		$( '#bd_lat' ).val( latlng.lat.toFixed( 6 ) );
		$( '#bd_lng' ).val( latlng.lng.toFixed( 6 ) );
	}

	$( document ).ready(
		function () {
			// Load Leaflet CSS and JS
			if ( ! document.getElementById( 'leaflet-css' )) {
				const css = document.createElement( 'link' );
				css.id    = 'leaflet-css';
				css.rel   = 'stylesheet';
				css.href  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
				document.head.appendChild( css );
			}

			if (typeof L === 'undefined') {
				const script  = document.createElement( 'script' );
				script.src    = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
				script.onload = initMap;
				document.body.appendChild( script );
			} else {
				initMap();
			}
		}
	);

})( jQuery );
