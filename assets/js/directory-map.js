/**
 * Business Directory - Map Component
 */

(function($) {
    'use strict';
    
    const DirectoryMap = {
        
        map: null,
        markers: [],
        markerCluster: null,
        
        /**
         * Initialize map
         */
        init: function() {
            if (!$('#bd-map').length) {
                return;
            }
            
            // Default center (Austin, TX)
            const defaultLat = 30.2672;
            const defaultLng = -97.7431;
            
            // Initialize Leaflet map
            this.map = L.map('bd-map').setView([defaultLat, defaultLng], 12);
            
            // Add tile layer (OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(this.map);
            
            // Initialize marker cluster group
            this.markerCluster = L.markerClusterGroup({
                chunkedLoading: true,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true
            });
            
            this.map.addLayer(this.markerCluster);
            
            // Listen for filter updates
            $(document).on('bd:filters:applied', (e, response) => {
                this.updateBusinesses(response.businesses);
                
                if (response.bounds) {
                    this.fitBounds(response.bounds);
                }
            });
        },
        
        /**
         * Update map markers with businesses
         */
        updateBusinesses: function(businesses) {
            // Clear existing markers
            this.markerCluster.clearLayers();
            this.markers = [];
            
            if (!businesses || businesses.length === 0) {
                return;
            }
            
            // Add markers for each business
            businesses.forEach(business => {
                if (!business.location || !business.location.lat || !business.location.lng) {
                    return;
                }
                
                const marker = this.createMarker(business);
                this.markers.push(marker);
                this.markerCluster.addLayer(marker);
            });
        },
        
        /**
         * Create marker for business
         */
        createMarker: function(business) {
            const lat = business.location.lat;
            const lng = business.location.lng;
            
            // Create marker
            const marker = L.marker([lat, lng]);
            
            // Create popup content
            const popupContent = this.createPopupContent(business);
            marker.bindPopup(popupContent);
            
            // Store business data
            marker.businessData = business;
            
            return marker;
        },
        
        /**
         * Create popup HTML for business
         */
        createPopupContent: function(business) {
            let html = '<div class="bd-map-popup">';
            
            // Image
            if (business.featured_image) {
                html += `<div class="bd-popup-image">
                    <img src="${business.featured_image}" alt="${business.title}">
                </div>`;
            }
            
            // Title
            html += `<h3 class="bd-popup-title">
                <a href="${business.permalink}">${business.title}</a>
            </h3>`;
            
            // Rating
            if (business.rating > 0) {
                html += `<div class="bd-popup-rating">
                    ${this.renderStars(business.rating)}
                    <span class="bd-rating-text">${business.rating} (${business.review_count})</span>
                </div>`;
            }
            
            // Price level
            if (business.price_level) {
                html += `<div class="bd-popup-price">${business.price_level}</div>`;
            }
            
            // Categories
            if (business.categories && business.categories.length > 0) {
                html += `<div class="bd-popup-categories">
                    ${business.categories.join(', ')}
                </div>`;
            }
            
            // Address
            if (business.location && business.location.address) {
                html += `<div class="bd-popup-address">
                    📍 ${business.location.address}
                </div>`;
            }
            
            // Distance
            if (business.distance) {
                html += `<div class="bd-popup-distance">
                    ${business.distance.display}
                </div>`;
            }
            
            // View details link
            html += `<div class="bd-popup-link">
                <a href="${business.permalink}" class="bd-btn">View Details →</a>
            </div>`;
            
            html += '</div>';
            
            return html;
        },
        
        /**
         * Render star rating
         */
        renderStars: function(rating) {
            let stars = '';
            const fullStars = Math.floor(rating);
            const hasHalfStar = rating % 1 >= 0.5;
            
            for (let i = 0; i < 5; i++) {
                if (i < fullStars) {
                    stars += '<span class="bd-star bd-star-full">★</span>';
                } else if (i === fullStars && hasHalfStar) {
                    stars += '<span class="bd-star bd-star-half">★</span>';
                } else {
                    stars += '<span class="bd-star bd-star-empty">☆</span>';
                }
            }
            
            return stars;
        },
        
        /**
         * Fit map to bounds
         */
        fitBounds: function(bounds) {
            if (!bounds || !bounds.north || !bounds.south) {
                return;
            }
            
            const leafletBounds = L.latLngBounds(
                [bounds.south, bounds.west],
                [bounds.north, bounds.east]
            );
            
            this.map.fitBounds(leafletBounds, {
                padding: [50, 50],
                maxZoom: 15
            });
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        if ($('#bd-map').length && typeof L !== 'undefined') {
            DirectoryMap.init();
            
            // Make available globally
            window.DirectoryMap = DirectoryMap;
        }
    });
    
})(jQuery);