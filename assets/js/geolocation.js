/**
 * Business Directory - Geolocation
 */

(function($) {
    'use strict';
    
    const Geolocation = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkLocationPermission();
        },
        
        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;
            
            // "Near Me" button
            $(document).on('click', '#bd-near-me-btn', function(e) {
                e.preventDefault();
                self.requestLocation();
            });
            
            // "Use City" button
            $(document).on('click', '#bd-use-city-btn', function(e) {
                e.preventDefault();
                self.showManualInput();
            });
            
            // Manual location submit
            $(document).on('click', '#bd-city-submit', function(e) {
                e.preventDefault();
                self.geocodeAddress();
            });
            
            // Enter key in city input
            $(document).on('keypress', '#bd-city-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.geocodeAddress();
                }
            });
        },
        
        /**
         * Check if geolocation permission already granted
         */
        checkLocationPermission: function() {
            if (navigator.permissions) {
                navigator.permissions.query({ name: 'geolocation' }).then(function(result) {
                    if (result.state === 'granted') {
                        // Auto-load last known location from localStorage
                        const lastLocation = localStorage.getItem('bd_last_location');
                        if (lastLocation) {
                            const loc = JSON.parse(lastLocation);
                            Geolocation.setLocation(loc.lat, loc.lng, loc.display);
                        }
                    }
                });
            }
        },
        
        /**
         * Request user's location
         */
        requestLocation: function() {
            const self = this;
            
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser. Please use the "Use City" option.');
                return;
            }
            
            // Show loading state
            $('#bd-near-me-btn').html('<span class="bd-loading"></span> Getting location...').prop('disabled', true);
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Reverse geocode to get city name
                    self.reverseGeocode(lat, lng);
                },
                function(error) {
                    self.handleLocationError(error);
                },
                {
                    enableHighAccuracy: false,
                    timeout: 10000,
                    maximumAge: 300000 // 5 minutes
                }
            );
        },
        
        /**
         * Handle geolocation errors
         */
        handleLocationError: function(error) {
            $('#bd-near-me-btn').html('📍 Near Me').prop('disabled', false);
            
            let message = 'Unable to get your location. ';
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Please enable location permissions in your browser settings or use the "Use City" option.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    message += 'The request timed out.';
                    break;
                default:
                    message += 'An unknown error occurred.';
            }
            
            alert(message);
            this.showManualInput();
        },
        
        /**
         * Reverse geocode coordinates to address
         */
        reverseGeocode: function(lat, lng) {
            const self = this;
            
            $.ajax({
                url: bdVars.apiUrl + 'bd/v1/geocode/reverse',
                method: 'GET',
                data: { lat: lat, lng: lng },
                success: function(response) {
                    const displayName = response.city || response.display_name || 'Current Location';
                    self.setLocation(lat, lng, displayName);
                },
                error: function() {
                    // Even if reverse geocoding fails, still use the coordinates
                    self.setLocation(lat, lng, 'Current Location');
                }
            });
        },
        
        /**
         * Geocode address to coordinates
         */
        geocodeAddress: function() {
            const self = this;
            const address = $('#bd-city-input').val().trim();
            
            if (!address) {
                alert('Please enter a city or zip code');
                return;
            }
            
            // Show loading
            $('#bd-city-submit').html('<span class="bd-loading"></span>').prop('disabled', true);
            
            $.ajax({
                url: bdVars.apiUrl + 'bd/v1/geocode',
                method: 'GET',
                data: { address: address },
                success: function(response) {
                    if (response.lat && response.lng) {
                        self.setLocation(response.lat, response.lng, response.display_name || address);
                        $('#bd-manual-location').hide();
                    } else {
                        alert('Location not found. Please try a different search.');
                        $('#bd-city-submit').html('Go').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error finding location. Please try again.');
                    $('#bd-city-submit').html('Go').prop('disabled', false);
                }
            });
        },
        
        /**
         * Set location and update filters
         */
        setLocation: function(lat, lng, displayName) {
            // Update filters
            if (window.DirectoryFilters) {
                window.DirectoryFilters.filters.lat = lat;
                window.DirectoryFilters.filters.lng = lng;
                window.DirectoryFilters.filters.radius_km = 16; // 10 miles default
                window.DirectoryFilters.filters.page = 1;
                window.DirectoryFilters.applyFilters();
            }
            
            // Save to localStorage
            localStorage.setItem('bd_last_location', JSON.stringify({
                lat: lat,
                lng: lng,
                display: displayName,
                timestamp: Date.now()
            }));
            
            // Update UI
            $('#bd-location-display').html(`
                <strong>📍 ${displayName}</strong>
                <button id="bd-change-location" class="bd-btn-link">Change</button>
            `).show();
            
            $('#bd-radius-container').show();
            $('#bd-near-me-btn').html('📍 Near Me').prop('disabled', false);
            $('#bd-city-submit').html('Go').prop('disabled', false);
            
            // Update map
            if (window.DirectoryMap && window.DirectoryMap.map) {
                window.DirectoryMap.setUserLocation(lat, lng);
            }
            
            // Bind change location
            $('#bd-change-location').on('click', function(e) {
                e.preventDefault();
                Geolocation.clearLocation();
            });
        },
        
        /**
         * Show manual input form
         */
        showManualInput: function() {
            $('#bd-manual-location').show();
            $('#bd-city-input').focus();
        },
        
        /**
         * Clear location
         */
        clearLocation: function() {
            if (window.DirectoryFilters) {
                window.DirectoryFilters.filters.lat = null;
                window.DirectoryFilters.filters.lng = null;
                window.DirectoryFilters.filters.radius_km = 16;
                window.DirectoryFilters.applyFilters();
            }
            
            localStorage.removeItem('bd_last_location');
            
            $('#bd-location-display').hide();
            $('#bd-radius-container').hide();
            $('#bd-manual-location').hide();
            $('#bd-city-input').val('');
            
            if (window.DirectoryMap && window.DirectoryMap.userMarker) {
                window.DirectoryMap.map.removeLayer(window.DirectoryMap.userMarker);
                window.DirectoryMap.userMarker = null;
            }
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        if ($('#bd-near-me-btn').length) {
            Geolocation.init();
            window.Geolocation = Geolocation;
        }
    });
    
})(jQuery);
