/**
 * Business Directory - Complete Directory System
 * Handles Filters, Map, List/Grid views, and Tag Bar
 * Updated with blue/teal color scheme
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
                tags: [],
                price_level: [],
                min_rating: null,
                open_now: false,
                q: '',
                sort: 'distance',
                page: 1,
                per_page: 20
            },
            businesses: [],
            availableTags: [],
            total: 0,
            bounds: null,
            currentView: 'list' // Default to list view
        },

        debounceTimer: null,
        maxVisibleTags: 8, // Number of tags to show before "+X more"

        /**
         * Initialize the entire directory system
         */
        init: function () {
            console.log('Business Directory initializing...');

            if (!$('#bd-filter-panel').length) {
                console.log('No filter panel found, aborting init');
                return;
            }

            // Load view preference from localStorage
            this.loadViewPreference();

            this.initMap();
            this.bindFilterEvents();
            this.bindViewToggle();
            this.bindTagBarEvents();
            this.loadFiltersFromURL();
            this.initMobileToggle();
            this.loadInitialTags();

            // Initial load
            this.applyFilters();

            // Make globally available
            window.BusinessDirectory = this;
        },

        // ====================================================================
        // VIEW TOGGLE MANAGEMENT
        // ====================================================================

        loadViewPreference: function () {
            const savedView = localStorage.getItem('bd_view_preference');
            if (savedView && (savedView === 'list' || savedView === 'grid')) {
                this.state.currentView = savedView;
            }
            // Update UI to reflect current view
            this.updateViewToggleUI();
        },

        saveViewPreference: function (view) {
            localStorage.setItem('bd_view_preference', view);
        },

        bindViewToggle: function () {
            const self = this;

            $('.bd-view-btn').on('click', function () {
                const view = $(this).data('view');
                if (view === self.state.currentView) return;

                self.state.currentView = view;
                self.saveViewPreference(view);
                self.updateViewToggleUI();
                self.updateList(self.state.businesses);
            });
        },

        updateViewToggleUI: function () {
            const view = this.state.currentView;

            // Update button states
            $('.bd-view-btn').removeClass('active').attr('aria-pressed', 'false');
            $(`.bd-view-btn[data-view="${view}"]`).addClass('active').attr('aria-pressed', 'true');

            // Update list container class
            const $list = $('#bd-business-list');
            $list.removeClass('bd-view-list bd-view-grid').addClass(`bd-view-${view}`);
            $list.attr('data-view', view);
        },

        // ====================================================================
        // TAG BAR MANAGEMENT
        // ====================================================================

        loadInitialTags: function () {
            // Try to load tags from embedded JSON data
            const $tagsData = $('#bd-tags-data');
            if ($tagsData.length) {
                try {
                    const tags = JSON.parse($tagsData.text());
                    if (tags && tags.length > 0) {
                        this.renderTagBar(tags);
                    }
                } catch (e) {
                    console.log('Could not parse initial tags data');
                }
            }
        },

        bindTagBarEvents: function () {
            const self = this;

            // Tag click handler (delegated)
            $(document).on('click', '.bd-tag-pill', function (e) {
                e.preventDefault();
                const tagId = parseInt($(this).data('tag-id'));
                self.toggleTag(tagId);
            });

            // Expand button click
            $('#bd-tag-expand-btn').on('click', function () {
                self.toggleTagExpand();
            });

            // Close expanded panel when clicking outside
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.bd-tag-bar').length) {
                    self.closeTagExpand();
                }
            });
        },

        toggleTag: function (tagId) {
            const index = this.state.filters.tags.indexOf(tagId);

            if (index === -1) {
                this.state.filters.tags.push(tagId);
            } else {
                this.state.filters.tags.splice(index, 1);
            }

            this.state.filters.page = 1;
            this.updateTagBarUI();
            this.applyFilters();
        },

        toggleTagExpand: function () {
            const $panel = $('#bd-tag-expanded-panel');
            const $btn = $('#bd-tag-expand-btn');

            if ($panel.is(':visible')) {
                this.closeTagExpand();
            } else {
                $panel.slideDown(200);
                $btn.addClass('expanded');
            }
        },

        closeTagExpand: function () {
            $('#bd-tag-expanded-panel').slideUp(200);
            $('#bd-tag-expand-btn').removeClass('expanded');
        },

        renderTagBar: function (tags) {
            if (!tags || tags.length === 0) {
                $('#bd-tag-bar').hide();
                return;
            }

            $('#bd-tag-bar').show();
            this.state.availableTags = tags;

            const visibleTags = tags.slice(0, this.maxVisibleTags);
            const hiddenTags = tags.slice(this.maxVisibleTags);
            const selectedTags = this.state.filters.tags;

            // Render visible tags
            let html = '';
            visibleTags.forEach(tag => {
                const isActive = selectedTags.includes(tag.id);
                html += this.renderTagPill(tag, isActive);
            });
            $('#bd-tag-list').html(html);

            // Handle expand button
            if (hiddenTags.length > 0) {
                $('#bd-tag-expand-btn')
                    .show()
                    .find('.bd-tag-more-count')
                    .text(`+${hiddenTags.length}`);

                // Render expanded panel
                let expandedHtml = '';
                tags.forEach(tag => {
                    const isActive = selectedTags.includes(tag.id);
                    expandedHtml += this.renderTagPill(tag, isActive);
                });
                $('#bd-tag-expanded-list').html(expandedHtml);
            } else {
                $('#bd-tag-expand-btn').hide();
                $('#bd-tag-expanded-panel').hide();
            }
        },

        renderTagPill: function (tag, isActive) {
            return `
                <button type="button" 
                        class="bd-tag-pill ${isActive ? 'active' : ''}" 
                        data-tag-id="${tag.id}"
                        data-tag-slug="${tag.slug}">
                    ${tag.name}
                    <span class="bd-tag-count">${tag.count}</span>
                </button>
            `;
        },

        updateTagBarUI: function () {
            const selectedTags = this.state.filters.tags;

            $('.bd-tag-pill').each(function () {
                const tagId = parseInt($(this).data('tag-id'));
                if (selectedTags.includes(tagId)) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
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

            // Check if map is already initialized (in this instance)
            if (this.map) {
                console.log('Map already initialized, skipping');
                return;
            }

            // Check if map container already has a Leaflet map (from another script instance)
            const mapContainer = document.getElementById('bd-map');
            if (mapContainer && mapContainer._leaflet_id) {
                console.log('Map container already has a Leaflet map, skipping');
                return;
            }

            // Initialize Leaflet map
            this.map = L.map('bd-map').setView([37.6819, -121.7680], 11); // Livermore default

            // Define map styles (all free, no API key needed)
            this.mapStyles = {
                clean: {
                    name: 'Clean',
                    icon: '<i class="fas fa-map"></i>',
                    layer: L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/attributions">CARTO</a>',
                        maxZoom: 19
                    })
                },
                light: {
                    name: 'Light',
                    icon: '<i class="fas fa-sun"></i>',
                    layer: L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/attributions">CARTO</a>',
                        maxZoom: 19
                    })
                },
                terrain: {
                    name: 'Terrain',
                    icon: '<i class="fas fa-mountain"></i>',
                    layer: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                        attribution: '© <a href="https://opentopomap.org">OpenTopoMap</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: 17
                    })
                },
                satellite: {
                    name: 'Satellite',
                    icon: '<i class="fas fa-satellite"></i>',
                    layer: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                        attribution: '© Esri, Maxar, Earthstar Geographics',
                        maxZoom: 18
                    })
                }
            };

            // Get saved preference or default to 'clean'
            const savedStyle = localStorage.getItem('bd_map_style') || 'clean';
            this.currentStyle = savedStyle;
            this.mapStyles[savedStyle].layer.addTo(this.map);

            // Add style switcher control
            this.addStyleSwitcher();

            // Initialize marker cluster group with branded styling
            this.markerCluster = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                iconCreateFunction: function(cluster) {
                    const count = cluster.getChildCount();
                    let size = 'small';
                    if (count > 10) size = 'medium';
                    if (count > 25) size = 'large';
                    
                    return L.divIcon({
                        html: '<div class="bd-cluster bd-cluster-' + size + '"><span>' + count + '</span></div>',
                        className: 'bd-cluster-icon',
                        iconSize: L.point(40, 40)
                    });
                }
            });

            this.map.addLayer(this.markerCluster);

            console.log('Map initialized');
        },

        /**
         * Create heart-shaped marker icon
         */
        createHeartIcon: function() {
            return L.divIcon({
                html: '<div class="bd-heart-marker"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></div>',
                className: 'bd-heart-icon',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });
        },

        /**
         * Add map style switcher control
         */
        addStyleSwitcher: function() {
            const self = this;
            
            // Create custom control
            const StyleControl = L.Control.extend({
                options: { position: 'topright' },
                
                onAdd: function(map) {
                    const container = L.DomUtil.create('div', 'bd-style-switcher');
                    
                    // Current style button (shows current, click to expand)
                    const currentBtn = L.DomUtil.create('button', 'bd-style-current', container);
                    currentBtn.innerHTML = self.mapStyles[self.currentStyle].icon;
                    currentBtn.title = 'Change map style';
                    
                    // Dropdown options
                    const dropdown = L.DomUtil.create('div', 'bd-style-dropdown', container);
                    
                    Object.keys(self.mapStyles).forEach(function(key) {
                        const style = self.mapStyles[key];
                        const btn = L.DomUtil.create('button', 'bd-style-option', dropdown);
                        btn.innerHTML = style.icon + ' ' + style.name;
                        btn.dataset.style = key;
                        
                        if (key === self.currentStyle) {
                            btn.classList.add('active');
                        }
                        
                        L.DomEvent.on(btn, 'click', function(e) {
                            L.DomEvent.stopPropagation(e);
                            self.setMapStyle(key);
                            currentBtn.innerHTML = style.icon;
                            
                            // Update active state
                            dropdown.querySelectorAll('.bd-style-option').forEach(function(b) {
                                b.classList.remove('active');
                            });
                            btn.classList.add('active');
                            
                            // Close dropdown
                            container.classList.remove('open');
                        });
                    });
                    
                    // Toggle dropdown
                    L.DomEvent.on(currentBtn, 'click', function(e) {
                        L.DomEvent.stopPropagation(e);
                        container.classList.toggle('open');
                    });
                    
                    // Close on map click
                    map.on('click', function() {
                        container.classList.remove('open');
                    });
                    
                    // Prevent map interactions when clicking control
                    L.DomEvent.disableClickPropagation(container);
                    L.DomEvent.disableScrollPropagation(container);
                    
                    return container;
                }
            });
            
            new StyleControl().addTo(this.map);
        },

        /**
         * Set map tile style
         */
        setMapStyle: function(styleKey) {
            if (!this.mapStyles[styleKey]) return;
            
            // Remove current layer
            this.map.eachLayer(function(layer) {
                if (layer instanceof L.TileLayer) {
                    layer.remove();
                }
            });
            
            // Add new layer
            this.mapStyles[styleKey].layer.addTo(this.map);
            this.currentStyle = styleKey;
            
            // Save preference
            localStorage.setItem('bd_map_style', styleKey);
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

            // Create reusable heart icon
            const heartIcon = this.createHeartIcon();

            // Add markers for each business
            businesses.forEach(business => {
                if (!business.location || !business.location.lat || !business.location.lng) {
                    return;
                }

                const marker = L.marker([business.location.lat, business.location.lng], {
                    icon: heartIcon
                });

                // Create popup content
                const popupContent = this.createPopupContent(business);
                marker.bindPopup(popupContent, {
                    maxWidth: 300,
                    minWidth: 220,
                    autoPanPadding: [60, 60],
                    closeButton: true
                });

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
            // Check popup style setting (default: minimal)
            // Set via: add_filter('bd_popup_style', fn() => 'detailed');
            const style = (typeof bdVars !== 'undefined' && bdVars.popupStyle) || 'minimal';
            
            if (style === 'detailed') {
                return this.createDetailedPopup(business);
            }
            return this.createMinimalPopup(business);
        },

        /**
         * Minimal popup - premium wine country feel
         * Shows: Title, Stars, Price, Category, Tag, Distance
         */
        createMinimalPopup: function (business) {
            let html = '<a href="' + business.permalink + '" class="bd-popup" title="View ' + business.title + '">';

            // Accent bar
            html += '<div class="bd-popup-accent"></div>';

            // Content wrapper
            html += '<div class="bd-popup-inner">';

            // Row 1: Title + Arrow
            html += '<div class="bd-popup-header">';
            html += '<span class="bd-popup-title">' + business.title + '</span>';
            html += '<i class="fas fa-chevron-right bd-popup-arrow"></i>';
            html += '</div>';

            // Row 2: Stars + Review Count + Price (or invitation)
            html += '<div class="bd-popup-ratings">';
            if (business.review_count > 0) {
                html += this.renderStars(business.rating || 0);
                html += '<span class="bd-popup-reviews">(' + business.review_count + ')</span>';
            } else {
                html += '<span class="bd-popup-first-review"><i class="fas fa-star"></i> Be first to review!</span>';
            }
            if (business.price_level && business.price_level !== 'Free') {
                html += '<span class="bd-popup-price">' + business.price_level + '</span>';
            }
            html += '</div>';

            // Row 3: Category + Distance or Tag
            html += '<div class="bd-popup-tags">';
            if (business.categories && business.categories.length > 0) {
                html += '<span class="bd-popup-cat"><i class="fas fa-folder"></i> ' + business.categories[0] + '</span>';
            }
            // Prioritize distance when available (user clicked Near Me)
            if (business.distance && business.distance.display) {
                html += '<span class="bd-popup-dist"><i class="fas fa-map-marker-alt"></i> ' + business.distance.display + '</span>';
            } else if (business.tags && business.tags.length > 0 && business.tags[0].name) {
                html += '<span class="bd-popup-tag"><i class="fas fa-tag"></i> ' + business.tags[0].name + '</span>';
            }
            html += '</div>';

            html += '</div>'; // Close inner
            html += '</a>';
            return html;
        },

        /**
         * Render 5-star rating with filled/empty stars
         */
        renderStars: function (rating) {
            let html = '<span class="bd-popup-stars">';
            const fullStars = Math.floor(rating);
            const hasHalf = (rating % 1) >= 0.5;
            const emptyStars = 5 - fullStars - (hasHalf ? 1 : 0);

            // Filled stars
            for (let i = 0; i < fullStars; i++) {
                html += '<i class="fas fa-star"></i>';
            }
            // Half star
            if (hasHalf) {
                html += '<i class="fas fa-star-half-alt"></i>';
            }
            // Empty stars
            for (let i = 0; i < emptyStars; i++) {
                html += '<i class="far fa-star"></i>';
            }

            if (rating > 0) {
                html += '<span class="bd-popup-rating-num">' + rating.toFixed(1) + '</span>';
            }
            html += '</span>';
            return html;
        },

        /**
         * Detailed popup - elaborate design with CTA button
         * Shows: Title, Rating, Price, Categories, Distance, CTA
         */
        createDetailedPopup: function (business) {
            let html = '<div class="bd-popup-content">';

            // Title
            html += '<h3 class="bd-popup-title"><a href="' + business.permalink + '">' + business.title + '</a></h3>';

            // Rating & Price
            html += '<div class="bd-popup-meta">';
            if (business.rating > 0) {
                html += '<span class="bd-popup-rating">★ ' + business.rating + ' (' + (business.review_count || 0) + ')</span>';
            }
            if (business.price_level) {
                html += '<span class="bd-popup-price">' + business.price_level + '</span>';
            }
            html += '</div>';

            // Categories
            if (business.categories && business.categories.length > 0) {
                html += '<div class="bd-popup-categories">' + business.categories.join(', ') + '</div>';
            }

            // Distance
            if (business.distance && business.distance.display) {
                html += '<div class="bd-popup-distance">📍 ' + business.distance.display + '</div>';
            }

            // CTA Button
            html += '<a href="' + business.permalink + '" class="bd-popup-cta">View Details →</a>';

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
                    <div class="bd-empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <p class="bd-empty-title">No businesses found</p>
                        <p class="bd-empty-subtitle">Try adjusting your filters or search location</p>
                    </div>
                `);
                return;
            }

            // Render based on current view
            if (this.state.currentView === 'list') {
                this.renderListView(businesses, $list);
            } else {
                this.renderGridView(businesses, $list);
            }
        },

        // ====================================================================
        // LIST VIEW RENDERER
        // ====================================================================
        renderListView: function (businesses, $list) {
            let html = '<div class="bd-list-container">';

            businesses.forEach(business => {
                const tags = business.tags || [];
                const tagsHtml = tags.slice(0, 3).map(tag =>
                    `<span class="bd-list-tag">${tag.name}</span>`
                ).join('');

                const excerpt = business.excerpt
                    ? business.excerpt.replace(/<[^>]*>/g, '').substring(0, 100) + '...'
                    : '';

                html += `
                    <article class="bd-list-item">
                        <div class="bd-list-image">
                            ${business.featured_image
                        ? `<img src="${business.featured_image}" alt="${business.title}" loading="lazy">`
                        : `<div class="bd-list-image-placeholder">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                        </svg>
                                    </div>`
                    }
                            ${business.distance ? `<span class="bd-list-distance">${business.distance.display}</span>` : ''}
                        </div>
                        
                        <div class="bd-list-content">
                            <div class="bd-list-header">
                                <h3 class="bd-list-title">
                                    <a href="${business.permalink}">${business.title}</a>
                                </h3>
                                ${business.categories && business.categories.length > 0
                        ? `<span class="bd-list-category">${business.categories[0]}</span>`
                        : ''}
                            </div>
                            
                            ${excerpt ? `<p class="bd-list-excerpt">${excerpt}</p>` : ''}
                            
                            ${tags.length > 0 ? `<div class="bd-list-tags">${tagsHtml}</div>` : ''}
                        </div>
                        
                        <div class="bd-list-meta">
                            <div class="bd-list-location">
                                <svg width="14" height="14" viewBox="0 0 384 512" fill="currentColor">
                                    <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                                </svg>
                                ${business.areas && business.areas.length > 0 ? business.areas[0] : 'Location'}
                            </div>
                            
                            <div class="bd-list-rating">
                                ${business.rating > 0
                        ? `<div class="bd-list-stars">${this.renderStars(business.rating)}</div>
                                       <span class="bd-list-rating-value">${business.rating}</span>
                                       <span class="bd-list-review-count">(${business.review_count})</span>`
                        : '<span class="bd-list-no-reviews">No reviews yet</span>'
                    }
                            </div>
                            
                            <a href="${business.permalink}" class="bd-list-cta">
                                View Details
                                <svg width="14" height="14" viewBox="0 0 448 512" fill="currentColor">
                                    <path d="M438.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L338.8 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l306.7 0L233.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z"/>
                                </svg>
                            </a>
                        </div>
                    </article>
                `;
            });

            html += '</div>';
            $list.html(html);
            console.log(`Rendered ${businesses.length} businesses in list view`);
        },

        // ====================================================================
        // GRID VIEW RENDERER
        // ====================================================================
        renderGridView: function (businesses, $list) {
            let html = '<div class="bd-grid-container">';

            businesses.forEach(business => {
                const tags = business.tags || [];
                const tagsHtml = tags.slice(0, 2).map(tag =>
                    `<span class="bd-grid-tag">${tag.name}</span>`
                ).join('');

                html += `
                    <article class="bd-grid-card">
                        <div class="bd-grid-image" style="background: ${business.featured_image ? `url('${business.featured_image}') center/cover` : 'linear-gradient(135deg, #0A1F33 0%, #0F2A43 50%, #2CB1BC 100%)'};">
                            ${business.distance ? `
                                <span class="bd-grid-distance">
                                    <svg width="12" height="12" viewBox="0 0 384 512" fill="#0F2A43">
                                        <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                                    </svg>
                                    ${business.distance.display}
                                </span>
                            ` : ''}
                        </div>

                        <div class="bd-grid-content">
                            <h3 class="bd-grid-title">
                                <a href="${business.permalink}">${business.title}</a>
                            </h3>

                            <div class="bd-grid-rating-row">
                                ${business.rating > 0 ? `
                                    <div class="bd-grid-rating">
                                        <div class="bd-grid-stars">${this.renderStars(business.rating)}</div>
                                        <span class="bd-grid-rating-value">${business.rating}</span>
                                        <span class="bd-grid-review-count">(${business.review_count})</span>
                                    </div>
                                ` : '<span class="bd-grid-no-reviews">No reviews yet</span>'}
                                
                                ${business.price_level ? `
                                    <span class="bd-grid-price">${business.price_level}</span>
                                ` : ''}
                            </div>

                            ${business.categories && business.categories.length > 0 ? `
                                <div class="bd-grid-categories">
                                    ${business.categories.slice(0, 2).map(cat => `
                                        <span class="bd-grid-category">${cat}</span>
                                    `).join('')}
                                </div>
                            ` : ''}

                            ${business.areas && business.areas.length > 0 ? `
                                <div class="bd-grid-area">
                                    <svg width="14" height="14" viewBox="0 0 384 512" fill="currentColor">
                                        <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
                                    </svg>
                                    ${business.areas[0]}
                                </div>
                            ` : ''}

                            ${business.excerpt ? `
                                <p class="bd-grid-excerpt">${business.excerpt.replace(/<[^>]*>/g, '').substring(0, 120)}...</p>
                            ` : ''}

                            ${tags.length > 0 ? `<div class="bd-grid-tags">${tagsHtml}</div>` : ''}

                            <a href="${business.permalink}" class="bd-grid-cta">
                                View Details
                                <svg width="14" height="14" viewBox="0 0 448 512" fill="currentColor">
                                    <path d="M438.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L338.8 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l306.7 0L233.4 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160z"/>
                                </svg>
                            </a>
                        </div>
                    </article>
                `;
            });

            html += '</div>';
            $list.html(html);
            console.log(`Rendered ${businesses.length} businesses in grid view`);
        },

        // Helper function to render star ratings
        renderStars: function (rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= Math.floor(rating)) {
                    // Full star - teal
                    stars += '<svg width="16" height="16" viewBox="0 0 24 24" fill="#2CB1BC"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
                } else if (i - 0.5 === rating) {
                    // Half star
                    stars += '<svg width="16" height="16" viewBox="0 0 24 24" fill="#2CB1BC"><defs><linearGradient id="half"><stop offset="50%" stop-color="#2CB1BC"/><stop offset="50%" stop-color="#d1d5db"/></linearGradient></defs><path fill="url(#half)" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
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
                const val = $(this).val();
                self.state.filters.min_rating = val ? parseFloat(val) : null;
                self.state.filters.page = 1;
                self.applyFilters();
            });

            // Open now toggle
            $('#bd-open-now').on('change', function () {
                self.state.filters.open_now = $(this).is(':checked');
                self.state.filters.page = 1;
                self.applyFilters();
            });

            // Sort dropdown
            $('#bd-sort-select').on('change', function () {
                self.state.filters.sort = $(this).val();
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
                $('#bd-manual-location').toggle();
            });

            // City submit
            $('#bd-city-submit').on('click', function () {
                const city = $('#bd-city-input').val();
                if (city) {
                    self.geocodeCity(city);
                }
            });

            // Radius slider
            $('#bd-radius-slider').on('input', function () {
                const miles = $(this).val();
                $('#bd-radius-value').text(miles);
                self.state.filters.radius_km = miles * 1.60934;
            });

            $('#bd-radius-slider').on('change', function () {
                self.applyFilters();
            });
        },

        updateArrayFilter: function (filterName, selector) {
            const self = this;
            self.state.filters[filterName] = [];

            $(selector).each(function () {
                self.state.filters[filterName].push(parseInt($(this).val()));
            });

            self.state.filters.page = 1;
            self.applyFilters();
        },

        getUserLocation: function () {
            const self = this;

            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser');
                return;
            }

            $('#bd-near-me-btn').text('Locating...').prop('disabled', true);

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    self.state.filters.lat = position.coords.latitude;
                    self.state.filters.lng = position.coords.longitude;

                    $('#bd-location-display')
                        .html(`📍 Using your location`)
                        .show();

                    $('#bd-radius-container').show();
                    $('#bd-near-me-btn').text('Near Me').prop('disabled', false);

                    self.applyFilters();
                },
                function (error) {
                    alert('Unable to get your location. Please try entering a city.');
                    $('#bd-near-me-btn').text('Near Me').prop('disabled', false);
                }
            );
        },

        geocodeCity: function (city) {
            const self = this;

            $.ajax({
                url: `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(city)}`,
                success: function (data) {
                    if (data && data.length > 0) {
                        self.state.filters.lat = parseFloat(data[0].lat);
                        self.state.filters.lng = parseFloat(data[0].lon);

                        $('#bd-location-display')
                            .html(`📍 ${data[0].display_name.split(',')[0]}`)
                            .show();

                        $('#bd-radius-container').show();
                        $('#bd-manual-location').hide();

                        self.applyFilters();
                    } else {
                        alert('City not found. Please try a different search.');
                    }
                },
                error: function () {
                    alert('Error searching for city. Please try again.');
                }
            });
        },

        applyFilters: function () {
            const self = this;
            const params = this.buildQueryParams();

            console.log('Applying filters:', params);

            // Show loading state
            $('#bd-result-count-text').text('Loading...');

            $.ajax({
                url: bdVars.apiUrl + 'businesses',
                data: params,
                headers: {
                    'X-WP-Nonce': bdVars.nonce
                },
                success: function (response) {
                    self.handleResponse(response);
                },
                error: function (xhr, status, error) {
                    console.error('API Error:', error);
                    $('#bd-result-count-text').text('Error loading businesses');
                }
            });
        },

        buildQueryParams: function () {
            const filters = this.state.filters;
            const params = {};

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

            if (filters.tags.length > 0) {
                params.tags = filters.tags.join(',');
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

            // Update tag bar with available tags from current results
            if (response.available_tags) {
                this.renderTagBar(response.available_tags);
            }

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
            if (urlParams.has('tags')) {
                this.state.filters.tags = urlParams.get('tags').split(',').map(Number);
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
            if (urlParams.has('view')) {
                const view = urlParams.get('view');
                if (view === 'list' || view === 'grid') {
                    this.state.currentView = view;
                    this.updateViewToggleUI();
                }
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
                tags: [],
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

            // Reset tag bar
            this.updateTagBarUI();

            // Apply cleared filters
            this.applyFilters();
        },

        updateFilterCount: function () {
            let count = 0;
            const filters = this.state.filters;

            count += filters.categories.length;
            count += filters.areas.length;
            count += filters.tags.length;
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
