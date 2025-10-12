/**
 * Business Directory - Complete Directory System
 * Handles Filters, Map, and List rendering in one unified module
 */

(function ($) {
    'use strict';

    // ========================================================================
    // MAIN CONTROLLER
    // ========================================================================
    const BusinessDirectory = {

        // State
        state: {
            filters: {
                lat: null,
                lng: null,
                radius_km: 16, // ~10 miles
                categories: [],
                areas: [],
                price_level: [],
                min_rating: null,
                open_now: false,
                q: '',
                sort: 'distance',
                page: 1,
                per_page: 20
            },
            businesses: [],
            total: 0,
            bounds: null
        },

        debounceTimer: null,

        /**
         * Initialize the entire directory system
         */
        init: function () {
            console.log('Business Directory initializing...');

            if (!$('#bd-filter-panel').length) {
                console.log('No filter panel found, aborting init');
                return;
            }

            this.initMap();
            this.bindFilterEvents();
            this.loadFiltersFromURL();
            this.initMobileToggle();

            // Initial load
            this.applyFilters();

            // Make globally available
            window.BusinessDirectory = this;
        },

        // ====================================================================
        // MAP MANAGEMENT
        // ====================================================================

        map: null,
        markers: [],
        markerCluster: null,

        initMap: function () {
            if (typeof L === 'undefined') {
                console.error('Leaflet not loaded');
                $('#bd-map').html('<p style="padding: 40px; text-align: center; color: #666;">Map library not available</p>');
                return;
            }

            if (!$('#bd-map').length) {
                console.log('No map container found');
                return;
            }

            // Initialize Leaflet map
            this.map = L.map('bd-map').setView([30.2672, -97.7431], 11); // Austin default

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(this.map);

            // Initialize marker cluster group
            this.markerCluster = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false
            });

            this.map.addLayer(this.markerCluster);

            console.log('Map initialized');
        },

        updateMap: function (businesses) {
            if (!this.map) return;

            // Clear existing markers
            this.markerCluster.clearLayers();
            this.markers = [];

            if (!businesses || businesses.length === 0) {
                console.log('No businesses to display on map');
                return;
            }

            // Add markers for each business
            businesses.forEach(business => {
                if (!business.location || !business.location.lat || !business.location.lng) {
                    return;
                }

                const marker = L.marker([business.location.lat, business.location.lng]);

                // Create popup content
                const popupContent = this.createPopupContent(business);
                marker.bindPopup(popupContent);

                this.markers.push(marker);
                this.markerCluster.addLayer(marker);
            });

            console.log(`Added ${this.markers.length} markers to map`);

            // Fit bounds if we have bounds data
            if (this.state.bounds) {
                this.fitMapBounds(this.state.bounds);
            }
        },

        createPopupContent: function (business) {
            let html = '<div class="bd-popup-content">';

            // Business Title
            html += `<h3 class="bd-popup-title">
        <a href="${business.permalink}">${business.title}</a>
                    </h3>`;

            // Rating & Price Row (side by side)
            html += '<div class="bd-popup-meta">';

            // Rating (with inline SVG star)
            if (business.rating > 0) {
                html += `<div class="bd-popup-rating">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#C9A86A" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
            <span class="rating-value">${business.rating}</span>
            <span class="rating-count">(${business.review_count})</span>
                        </div>`;
            }

            // Price Level (with inline SVG dollar)
            if (business.price_level) {
                html += `<div class="bd-popup-price">
            <svg width="14" height="18" viewBox="0 0 320 512" fill="#B87333" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                <path d="M160 0c17.7 0 32 14.3 32 32V67.7c1.6 .2 3.1 .4 4.7 .7c.4 .1 .7 .1 1.1 .2l48 8.8c17.4 3.2 28.9 19.9 25.7 37.2s-19.9 28.9-37.2 25.7l-47.5-8.7c-31.3-4.6-58.9-1.5-78.3 6.2s-27.2 18.3-29 28.1c-2 10.7-.5 16.7 1.2 20.4c1.8 3.9 5.5 8.3 12.8 13.2c16.3 10.7 41.3 17.7 73.7 26.3l2.9 .8c28.6 7.6 63.6 16.8 89.6 33.8c14.2 9.3 27.6 21.9 35.9 39.5c8.5 17.9 10.3 37.9 6.4 59.2c-6.9 38-33.1 63.4-65.6 76.7c-13.7 5.6-28.6 9.2-44.4 11V480c0 17.7-14.3 32-32 32s-32-14.3-32-32V445.1c-.4-.1-.9-.1-1.3-.2l-.2 0 0 0c-24.4-3.8-64.5-14.3-91.5-26.3c-16.1-7.2-23.4-26.1-16.2-42.2s26.1-23.4 42.2-16.2c20.9 9.3 55.3 18.5 75.2 21.6c31.9 4.7 58.2 2 76-5.3c16.9-6.9 24.6-16.9 26.8-28.9c1.9-10.6 .4-16.7-1.3-20.4c-1.9-4-5.6-8.4-13-13.3c-16.4-10.7-41.5-17.7-74-26.3l-2.8-.7 0 0C119.4 279.3 84.4 270 58.4 253c-14.2-9.3-27.5-22-35.8-39.6c-8.4-17.9-10.1-37.9-6.1-59.2C23.7 116 52.3 91.2 84.8 78.3c13.3-5.3 27.9-8.9 43.2-11V32c0-17.7 14.3-32 32-32z"/>
            </svg>
            <span>${business.price_level}</span>
                        </div>`;
            }

            html += '</div>'; // Close meta row

            // Categories/Services (styled beautifully)
            if (business.categories && business.categories.length > 0) {
                html += `<div class="bd-popup-categories">
            <svg width="16" height="16" viewBox="0 0 512 512" fill="#6B2C3E" style="display: inline-block; vertical-align: middle; margin-right: 8px; flex-shrink: 0;">
                <path d="M345 39.1L472.8 168.4c52.4 53 52.4 138.2 0 191.2L360.8 472.9c-9.3 9.4-24.5 9.5-33.9 .2s-9.5-24.5-.2-33.9L438.6 325.9c33.9-34.3 33.9-89.4 0-123.7L310.9 72.9c-9.3-9.4-9.2-24.6 .2-33.9s24.6-9.2 33.9 .2zM0 229.5V80C0 53.5 21.5 32 48 32H197.5c17 0 33.3 6.7 45.3 18.7l168 168c25 25 25 65.5 0 90.5L277.3 442.7c-25 25-65.5 25-90.5 0l-168-168C6.7 262.7 0 246.5 0 229.5zM144 144a32 32 0 1 0 -64 0 32 32 0 1 0 64 0z"/>
            </svg>
            <span>${business.categories.join(', ')}</span>
                        </div>`;
            }

            // Distance (with inline SVG pin)
            if (business.distance) {
                html += `<div class="bd-popup-distance">
            <svg width="14" height="18" viewBox="0 0 384 512" fill="#8B3A52" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
            </svg>
            <span>${business.distance.display}</span>
                        </div>`;
            }

            // View Details Button (with inline SVG arrow)
            html += `<a href="${business.permalink}" class="bd-popup-cta">
        View Details
        <svg width="16" height="16" viewBox="0 0 448 512" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-left: 8px;">
            <path d="M438.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L338.8 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l306.7 0L233.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z"/>
        </svg>
                    </a>`;

            html += '</div>';
            return html;
        },

        fitMapBounds: function (bounds) {
            if (!this.map || !bounds) return;

            const southWest = L.latLng(bounds.south, bounds.west);
            const northEast = L.latLng(bounds.north, bounds.east);
            const leafletBounds = L.latLngBounds(southWest, northEast);

            this.map.fitBounds(leafletBounds, { padding: [50, 50] });
        },

        // ====================================================================
        // LIST MANAGEMENT
        // ====================================================================
        updateList: function (businesses) {
            const $list = $('#bd-business-list');

            if (!$list.length) {
                console.log('No business list container found');
                return;
            }

            if (!businesses || businesses.length === 0) {
                $list.html(`
            <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 8px; border: 2px dashed #e5e7eb;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="margin: 0 auto 16px;">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p style="font-size: 18px; color: #6b7280; margin: 0;">No businesses found</p>
                <p style="color: #9ca3af; margin: 8px 0 0 0;">Try adjusting your filters or search location</p>
            </div>
        `);
                return;
            }

            // Grid container
            let html = '<div class="bd-business-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; margin-top: 20px;">';

            businesses.forEach(business => {
                html += `
            <div class="bd-business-card" style="
                border: 1px solid #e5e7eb; 
                border-radius: 12px; 
                background: white; 
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                transition: all 0.2s ease;
                display: flex;
                flex-direction: column;
                height: 100%;
            " onmouseover="this.style.boxShadow='0 10px 25px rgba(0,0,0,0.15)'; this.style.transform='translateY(-4px)';" 
               onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)';">
            
                <!-- Image -->
                <div style="
                    width: 100%; 
                    height: 200px; 
                    background: ${business.featured_image ? `url('${business.featured_image}') center/cover` : 'linear-gradient(135deg, #4A1F2C 0%, #6B2C3E 100%)'};
                    position: relative;
                ">
                    ${business.distance ? `
                        <div style="
                            position: absolute;
                            top: 12px;
                            right: 12px;
                            background: rgba(255,255,255,0.95);
                            backdrop-filter: blur(8px);
                            padding: 6px 12px;
                            border-radius: 20px;
                            font-size: 13px;
                            font-weight: 600;
                            color: #374151;
                            display: flex;
                            align-items: center;
                            gap: 4px;
                            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        ">
                            <svg width="12" height="12" viewBox="0 0 384 512" fill="#6b2c3e">
                                <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                            </svg>
                            ${business.distance.display}
                        </div>
                    ` : ''}
                </div>

                <!-- Content -->
                <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
                    
                    <!-- Title -->
                    <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 600; line-height: 1.3;">
                        <a href="${business.permalink}" style="color: #111827; text-decoration: none;">${business.title}</a>
                    </h3>

                    <!-- Rating & Price Row -->
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                        ${business.rating > 0 ? `
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="color: #fbbf24; display: flex; gap: 2px;">
                                    ${this.renderStars(business.rating)}
                                </div>
                                <span style="font-weight: 600; color: #111827;">${business.rating}</span>
                                <span style="color: #9ca3af; font-size: 14px;">(${business.review_count})</span>
                            </div>
                        ` : '<span style="color: #9ca3af; font-size: 14px;">No reviews yet</span>'}
                        
                        ${business.price_level ? `
                            <div style="
                                color: #059669; 
                                font-weight: 700; 
                                font-size: 18px; 
                                letter-spacing: 2px;
                                font-family: Georgia, serif;
                            ">
                                ${business.price_level}
                            </div>
                        ` : ''}
                    </div>

                    <!-- Categories -->
                    ${business.categories && business.categories.length > 0 ? `
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                            ${business.categories.slice(0, 2).map(cat => `
                                <span style="
                                    background: #fef3c7;
                                    color: #92400e;
                                    padding: 4px 10px;
                                    border-radius: 6px;
                                    font-size: 12px;
                                    font-weight: 500;
                                ">${cat}</span>
                            `).join('')}
                        </div>
                    ` : ''}

                    <!-- Area -->
                    ${business.areas && business.areas.length > 0 ? `
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 6px;
                            color: #6b7280;
                            font-size: 13px;
                            margin-bottom: 12px;
                        ">
                            <svg width="14" height="14" viewBox="0 0 384 512" fill="currentColor">
                                <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                            </svg>
                            ${business.areas[0]}
                        </div>
                    ` : ''}

                    <!-- Excerpt -->
                    ${business.excerpt ? `
                        <p style="
                            color: #6b7280;
                            font-size: 14px;
                            line-height: 1.6;
                            margin: 0 0 16px 0;
                            flex: 1;
                            display: -webkit-box;
                            -webkit-line-clamp: 3;
                            -webkit-box-orient: vertical;
                            overflow: hidden;
                        ">${business.excerpt.replace(/<[^>]*>/g, '').substring(0, 120)}...</p>
                    ` : ''}

                    <!-- CTA Button -->
                    <a href="${business.permalink}" style="
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                        width: 100%;
                        padding: 12px 20px;
                        background: #6b2c3e;
                        color: white;
                        text-decoration: none;
                        border-radius: 8px;
                        font-weight: 600;
                        font-size: 14px;
                        transition: all 0.2s ease;
                        margin-top: auto;
                    " onmouseover="this.style.background='#8b3a52';" onmouseout="this.style.background='#6b2c3e';">
                        View Details
                        <svg width="14" height="14" viewBox="0 0 448 512" fill="currentColor">
                            <path d="M438.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L338.8 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l306.7 0L233.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z"/>
                        </svg>
                    </a>

                </div>
            </div>
        `;
            });

            html += '</div>'; // Close grid

            $list.html(html);
            console.log(`Rendered ${businesses.length} businesses in card grid`);
        },

        // Helper function to render star ratings
        renderStars: function (rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= Math.floor(rating)) {
                    // Full star
                    stars += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
                } else if (i - 0.5 === rating) {
                    // Half star
                    stars += '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><defs><linearGradient id="half"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="#d1d5db"/></linearGradient></defs><path fill="url(#half)" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
                } else {
                    // Empty star
                    stars += '<svg width="16" height="16" viewBox="0 0 24 24" fill="#d1d5db"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
                }
            }
            return stars;
        },

        // ====================================================================
        // FILTER MANAGEMENT
        // ====================================================================

        bindFilterEvents: function () {
            const self = this;

            // Keyword search with debounce
            $('#bd-keyword-search').on('input', function () {
                clearTimeout(self.debounceTimer);
                self.debounceTimer = setTimeout(function () {
                    self.state.filters.q = $('#bd-keyword-search').val();
                    self.state.filters.page = 1;
                    self.applyFilters();
                }, 300);
            });

            // Category checkboxes
            $(document).on('change', 'input[name="categories[]"]', function () {
                self.updateArrayFilter('categories', 'input[name="categories[]"]:checked');
            });

            // Area checkboxes
            $(document).on('change', 'input[name="areas[]"]', function () {
                self.updateArrayFilter('areas', 'input[name="areas[]"]:checked');
            });

            // Price level checkboxes
            $(document).on('change', 'input[name="price_level[]"]', function () {
                self.state.filters.price_level = [];
                $('input[name="price_level[]"]:checked').each(function () {
                    self.state.filters.price_level.push($(this).val());
                });
                self.state.filters.page = 1;
                self.applyFilters();
            });

            // Rating radio buttons
            $(document).on('change', 'input[name="min_rating"]', function () {
                const value = $(this).val();
                self.state.filters.min_rating = value ? parseFloat(value) : null;
                self.state.filters.page = 1;
                self.applyFilters();
            });

            // Open now checkbox
            $('#bd-open-now').on('change', function () {
                self.state.filters.open_now = $(this).is(':checked');
                self.state.filters.page = 1;
                self.applyFilters();
            });

            // Radius slider
            $('#bd-radius-slider').on('input', function () {
                const miles = $(this).val();
                $('#bd-radius-value').text(miles);
                self.state.filters.radius_km = miles * 1.60934; // Convert to km

                clearTimeout(self.debounceTimer);
                self.debounceTimer = setTimeout(function () {
                    self.state.filters.page = 1;
                    self.applyFilters();
                }, 300);
            });

            // Sort dropdown
            $('#bd-sort-select').on('change', function () {
                self.state.filters.sort = $(this).val();
                self.state.filters.page = 1;
                self.applyFilters();
            });

            // Clear filters button
            $('#bd-clear-filters').on('click', function () {
                self.clearFilters();
            });

            // Near Me button
            $('#bd-near-me-btn').on('click', function () {
                self.getUserLocation();
            });

            // Use City button
            $('#bd-use-city-btn').on('click', function () {
                $('#bd-manual-location').slideToggle();
            });

            // City submit
            $('#bd-city-submit').on('click', function () {
                const city = $('#bd-city-input').val();
                if (city) {
                    self.geocodeCity(city);
                }
            });
        },

        updateArrayFilter: function (filterName, selector) {
            this.state.filters[filterName] = [];
            $(selector).each(function () {
                BusinessDirectory.state.filters[filterName].push(parseInt($(this).val()));
            });
            this.state.filters.page = 1;
            this.applyFilters();
        },

        getUserLocation: function () {
            const self = this;

            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser');
                return;
            }

            $('#bd-near-me-btn').text('Getting location...').prop('disabled', true);

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    self.state.filters.lat = position.coords.latitude;
                    self.state.filters.lng = position.coords.longitude;

                    $('#bd-location-display').html(`
                        <strong>Your Location</strong><br>
                        Lat: ${self.state.filters.lat.toFixed(4)}, Lng: ${self.state.filters.lng.toFixed(4)}
                    `).show();

                    $('#bd-radius-container').slideDown();
                    $('#bd-near-me-btn').text('📍 Near Me').prop('disabled', false);

                    self.applyFilters();
                },
                function (error) {
                    alert('Unable to get your location: ' + error.message);
                    $('#bd-near-me-btn').text('📍 Near Me').prop('disabled', false);
                }
            );
        },

        geocodeCity: function (city) {
            const self = this;

            $('#bd-city-submit').text('Loading...').prop('disabled', true);

            // Use Nominatim for geocoding
            const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(city)}&format=json&limit=1`;

            $.ajax({
                url: url,
                method: 'GET',
                headers: {
                    'User-Agent': 'Business Directory Plugin'
                },
                success: function (data) {
                    if (data && data.length > 0) {
                        self.state.filters.lat = parseFloat(data[0].lat);
                        self.state.filters.lng = parseFloat(data[0].lon);

                        $('#bd-location-display').html(`
                            <strong>${data[0].display_name}</strong>
                        `).show();

                        $('#bd-radius-container').slideDown();
                        $('#bd-manual-location').slideUp();

                        self.applyFilters();
                    } else {
                        alert('City not found. Please try again.');
                    }

                    $('#bd-city-submit').text('Go').prop('disabled', false);
                },
                error: function () {
                    alert('Error geocoding city. Please try again.');
                    $('#bd-city-submit').text('Go').prop('disabled', false);
                }
            });
        },

        // ====================================================================
        // API & DATA MANAGEMENT
        // ====================================================================

        applyFilters: function () {
            const self = this;

            // Show loading state
            $('#bd-result-count-text').html('<span class="bd-loading">Searching...</span>');

            // Build query params
            const params = this.buildQueryParams();

            console.log('Applying filters:', params);

            // Make API request
            $.ajax({
                url: bdVars.apiUrl + 'businesses',
                method: 'GET',
                data: params,
                success: function (response) {
                    console.log('API response:', response);
                    self.handleResponse(response);
                },
                error: function (xhr) {
                    console.error('API error:', xhr);
                    $('#bd-result-count-text').text('Error loading businesses');
                }
            });
        },

        buildQueryParams: function () {
            const params = {};
            const filters = this.state.filters;

            if (filters.lat && filters.lng) {
                params.lat = filters.lat;
                params.lng = filters.lng;
                params.radius_km = filters.radius_km;
            }

            if (filters.categories.length > 0) {
                params.categories = filters.categories.join(',');
            }

            if (filters.areas.length > 0) {
                params.areas = filters.areas.join(',');
            }

            if (filters.price_level.length > 0) {
                params.price_level = filters.price_level.join(',');
            }

            if (filters.min_rating) {
                params.min_rating = filters.min_rating;
            }

            if (filters.open_now) {
                params.open_now = 1;
            }

            if (filters.q) {
                params.q = filters.q;
            }

            params.sort = filters.sort;
            params.page = filters.page;
            params.per_page = filters.per_page;

            return params;
        },

        handleResponse: function (response) {
            // Update state
            this.state.businesses = response.businesses || [];
            this.state.total = response.total || 0;
            this.state.bounds = response.bounds || null;

            // Update result count
            const count = this.state.total;
            const plural = count === 1 ? 'business' : 'businesses';
            $('#bd-result-count-text').text(`Showing ${count} ${plural}`);

            // Update map
            this.updateMap(this.state.businesses);

            // Update list
            this.updateList(this.state.businesses);

            // Update URL
            this.updateURL();

            // Update filter count badge
            this.updateFilterCount();

            console.log(`Updated directory with ${count} businesses`);
        },

        // ====================================================================
        // URL & STATE MANAGEMENT
        // ====================================================================

        updateURL: function () {
            const params = this.buildQueryParams();
            const queryString = $.param(params);

            const newURL = window.location.pathname + (queryString ? '?' + queryString : '');
            window.history.replaceState({}, '', newURL);
        },

        loadFiltersFromURL: function () {
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('lat')) {
                this.state.filters.lat = parseFloat(urlParams.get('lat'));
            }
            if (urlParams.has('lng')) {
                this.state.filters.lng = parseFloat(urlParams.get('lng'));
            }
            if (urlParams.has('radius_km')) {
                this.state.filters.radius_km = parseFloat(urlParams.get('radius_km'));
            }
            if (urlParams.has('categories')) {
                this.state.filters.categories = urlParams.get('categories').split(',').map(Number);
                this.state.filters.categories.forEach(id => {
                    $(`input[name="categories[]"][value="${id}"]`).prop('checked', true);
                });
            }
            if (urlParams.has('areas')) {
                this.state.filters.areas = urlParams.get('areas').split(',').map(Number);
                this.state.filters.areas.forEach(id => {
                    $(`input[name="areas[]"][value="${id}"]`).prop('checked', true);
                });
            }
            if (urlParams.has('price_level')) {
                this.state.filters.price_level = urlParams.get('price_level').split(',');
                this.state.filters.price_level.forEach(level => {
                    $(`input[name="price_level[]"][value="${level}"]`).prop('checked', true);
                });
            }
            if (urlParams.has('min_rating')) {
                this.state.filters.min_rating = parseFloat(urlParams.get('min_rating'));
                $(`input[name="min_rating"][value="${this.state.filters.min_rating}"]`).prop('checked', true);
            }
            if (urlParams.has('open_now')) {
                this.state.filters.open_now = true;
                $('#bd-open-now').prop('checked', true);
            }
            if (urlParams.has('q')) {
                this.state.filters.q = urlParams.get('q');
                $('#bd-keyword-search').val(this.state.filters.q);
            }
            if (urlParams.has('sort')) {
                this.state.filters.sort = urlParams.get('sort');
                $('#bd-sort-select').val(this.state.filters.sort);
            }
        },

        clearFilters: function () {
            // Reset filter state
            this.state.filters = {
                lat: this.state.filters.lat, // Keep location
                lng: this.state.filters.lng,
                radius_km: 16,
                categories: [],
                areas: [],
                price_level: [],
                min_rating: null,
                open_now: false,
                q: '',
                sort: 'distance',
                page: 1,
                per_page: 20
            };

            // Reset UI
            $('#bd-keyword-search').val('');
            $('input[name="categories[]"]').prop('checked', false);
            $('input[name="areas[]"]').prop('checked', false);
            $('input[name="price_level[]"]').prop('checked', false);
            $('input[name="min_rating"]').prop('checked', false);
            $('input[name="min_rating"][value=""]').prop('checked', true);
            $('#bd-open-now').prop('checked', false);
            $('#bd-radius-slider').val(10);
            $('#bd-radius-value').text(10);
            $('#bd-sort-select').val('distance');

            // Apply cleared filters
            this.applyFilters();
        },

        updateFilterCount: function () {
            let count = 0;
            const filters = this.state.filters;

            count += filters.categories.length;
            count += filters.areas.length;
            count += filters.price_level.length;
            if (filters.min_rating) count++;
            if (filters.open_now) count++;
            if (filters.q) count++;

            const $badge = $('.bd-filter-count');
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.hide();
            }
        },

        // ====================================================================
        // MOBILE UI
        // ====================================================================

        initMobileToggle: function () {
            $('.bd-filter-toggle').on('click', function () {
                $('#bd-filter-panel').addClass('bd-filter-open');
                $('body').addClass('bd-filter-panel-open');
            });

            $('.bd-filter-close').on('click', function () {
                $('#bd-filter-panel').removeClass('bd-filter-open');
                $('body').removeClass('bd-filter-panel-open');
            });

            // Close on overlay click
            $(document).on('click', function (e) {
                if ($('#bd-filter-panel').hasClass('bd-filter-open') &&
                    !$(e.target).closest('.bd-filter-panel, .bd-filter-toggle').length) {
                    $('#bd-filter-panel').removeClass('bd-filter-open');
                    $('body').removeClass('bd-filter-panel-open');
                }
            });
        }
    };

    // ========================================================================
    // INITIALIZATION
    // ========================================================================

    $(document).ready(function () {
        BusinessDirectory.init();
    });

})(jQuery);