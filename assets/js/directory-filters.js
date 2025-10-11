/**
 * Business Directory - Advanced Filters
 */

(function($) {
    'use strict';
    
    const DirectoryFilters = {
        
        // State
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
        
        debounceTimer: null,
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadFromURL();
            this.initMobileToggle();
            
            // Initial load
            this.applyFilters();
        },
        
        /**
         * Bind all event listeners
         */
        bindEvents: function() {
            const self = this;
            
            // Keyword search with debounce
            $('#bd-keyword-search').on('input', function() {
                clearTimeout(self.debounceTimer);
                self.debounceTimer = setTimeout(function() {
                    self.filters.q = $('#bd-keyword-search').val();
                    self.filters.page = 1;
                    self.applyFilters();
                }, 300);
            });
            
            // Category checkboxes
            $(document).on('change', 'input[name="categories[]"]', function() {
                self.updateArrayFilter('categories', 'input[name="categories[]"]:checked');
            });
            
            // Area checkboxes
            $(document).on('change', 'input[name="areas[]"]', function() {
                self.updateArrayFilter('areas', 'input[name="areas[]"]:checked');
            });
            
            // Price level checkboxes
            $(document).on('change', 'input[name="price_level[]"]', function() {
                self.filters.price_level = [];
                $('input[name="price_level[]"]:checked').each(function() {
                    self.filters.price_level.push($(this).val());
                });
                self.filters.page = 1;
                self.applyFilters();
            });
            
            // Rating radio buttons
            $(document).on('change', 'input[name="min_rating"]', function() {
                const value = $(this).val();
                self.filters.min_rating = value ? parseFloat(value) : null;
                self.filters.page = 1;
                self.applyFilters();
            });
            
            // Open now checkbox
            $('#bd-open-now').on('change', function() {
                self.filters.open_now = $(this).is(':checked');
                self.filters.page = 1;
                self.applyFilters();
            });
            
            // Radius slider
            $('#bd-radius-slider').on('input', function() {
                const miles = $(this).val();
                $('#bd-radius-value').text(miles);
                self.filters.radius_km = miles * 1.60934; // Convert to km
                
                clearTimeout(self.debounceTimer);
                self.debounceTimer = setTimeout(function() {
                    self.filters.page = 1;
                    self.applyFilters();
                }, 300);
            });
            
            // Sort dropdown
            $('#bd-sort-select').on('change', function() {
                self.filters.sort = $(this).val();
                self.filters.page = 1;
                self.applyFilters();
            });
            
            // Clear filters button
            $('#bd-clear-filters').on('click', function() {
                self.clearFilters();
            });
        },
        
        /**
         * Update array-based filter
         */
        updateArrayFilter: function(filterName, selector) {
            this.filters[filterName] = [];
            $(selector).each(function() {
                DirectoryFilters.filters[filterName].push(parseInt($(this).val()));
            });
            this.filters.page = 1;
            this.applyFilters();
        },
        
        /**
         * Apply filters - fetch filtered businesses
         */
        applyFilters: function() {
            const self = this;
            
            // Show loading state
            $('#bd-result-count-text').html('<span class="bd-loading">Searching...</span>');
            
            // Build query params
            const params = this.buildQueryParams();
            
            // Make API request
            $.ajax({
                url: bdVars.apiUrl + 'businesses',
                method: 'GET',
                data: params,
                success: function(response) {
                    self.handleResponse(response);
                    self.updateURL();
                    self.updateFilterCount();
                },
                error: function(xhr) {
                    console.error('Filter error:', xhr);
                    $('#bd-result-count-text').text('Error loading businesses');
                }
            });
        },
        
        /**
         * Build query parameters from filters
         */
        buildQueryParams: function() {
            const params = {};
            
            if (this.filters.lat && this.filters.lng) {
                params.lat = this.filters.lat;
                params.lng = this.filters.lng;
                params.radius_km = this.filters.radius_km;
            }
            
            if (this.filters.categories.length > 0) {
                params.categories = this.filters.categories.join(',');
            }
            
            if (this.filters.areas.length > 0) {
                params.areas = this.filters.areas.join(',');
            }
            
            if (this.filters.price_level.length > 0) {
                params.price_level = this.filters.price_level.join(',');
            }
            
            if (this.filters.min_rating) {
                params.min_rating = this.filters.min_rating;
            }
            
            if (this.filters.open_now) {
                params.open_now = 1;
            }
            
            if (this.filters.q) {
                params.q = this.filters.q;
            }
            
            params.sort = this.filters.sort;
            params.page = this.filters.page;
            params.per_page = this.filters.per_page;
            
            return params;
        },
        
        /**
         * Handle API response
         */
        handleResponse: function(response) {
            // Update result count
            const count = response.total;
            const plural = count === 1 ? 'business' : 'businesses';
            $('#bd-result-count-text').text(`Showing ${count} ${plural}`);
            
            // Update map
            if (window.DirectoryMap) {
                window.DirectoryMap.updateBusinesses(response.businesses);
                
                // Auto-zoom to fit results
                if (response.bounds) {
                    window.DirectoryMap.fitBounds(response.bounds);
                }
            }
            
            // Update list view
            if (window.DirectoryList) {
                window.DirectoryList.updateList(response.businesses);
            }
            
            // Trigger custom event
            $(document).trigger('bd:filters:applied', [response]);
        },
        
        /**
         * Update URL with current filters (for sharing)
         */
        updateURL: function() {
            const params = this.buildQueryParams();
            const queryString = $.param(params);
            
            const newURL = window.location.pathname + (queryString ? '?' + queryString : '');
            window.history.replaceState({}, '', newURL);
        },
        
        /**
         * Load filters from URL parameters
         */
        loadFromURL: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('lat')) {
                this.filters.lat = parseFloat(urlParams.get('lat'));
            }
            if (urlParams.has('lng')) {
                this.filters.lng = parseFloat(urlParams.get('lng'));
            }
            if (urlParams.has('radius_km')) {
                this.filters.radius_km = parseFloat(urlParams.get('radius_km'));
            }
            if (urlParams.has('categories')) {
                this.filters.categories = urlParams.get('categories').split(',').map(Number);
                // Check appropriate checkboxes
                this.filters.categories.forEach(id => {
                    $(`input[name="categories[]"][value="${id}"]`).prop('checked', true);
                });
            }
            if (urlParams.has('areas')) {
                this.filters.areas = urlParams.get('areas').split(',').map(Number);
                this.filters.areas.forEach(id => {
                    $(`input[name="areas[]"][value="${id}"]`).prop('checked', true);
                });
            }
            if (urlParams.has('price_level')) {
                this.filters.price_level = urlParams.get('price_level').split(',');
                this.filters.price_level.forEach(level => {
                    $(`input[name="price_level[]"][value="${level}"]`).prop('checked', true);
                });
            }
            if (urlParams.has('min_rating')) {
                this.filters.min_rating = parseFloat(urlParams.get('min_rating'));
                $(`input[name="min_rating"][value="${this.filters.min_rating}"]`).prop('checked', true);
            }
            if (urlParams.has('open_now')) {
                this.filters.open_now = true;
                $('#bd-open-now').prop('checked', true);
            }
            if (urlParams.has('q')) {
                this.filters.q = urlParams.get('q');
                $('#bd-keyword-search').val(this.filters.q);
            }
            if (urlParams.has('sort')) {
                this.filters.sort = urlParams.get('sort');
                $('#bd-sort-select').val(this.filters.sort);
            }
        },
        
        /**
         * Clear all filters
         */
        clearFilters: function() {
            // Reset filter state
            this.filters = {
                lat: this.filters.lat, // Keep location
                lng: this.filters.lng,
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
        
        /**
         * Update filter count badge
         */
        updateFilterCount: function() {
            let count = 0;
            
            count += this.filters.categories.length;
            count += this.filters.areas.length;
            count += this.filters.price_level.length;
            if (this.filters.min_rating) count++;
            if (this.filters.open_now) count++;
            if (this.filters.q) count++;
            
            const $badge = $('.bd-filter-count');
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.hide();
            }
        },
        
        /**
         * Initialize mobile filter toggle
         */
        initMobileToggle: function() {
            $('.bd-filter-toggle').on('click', function() {
                $('#bd-filter-panel').addClass('bd-filter-open');
                $('body').addClass('bd-filter-panel-open');
            });
            
            $('.bd-filter-close').on('click', function() {
                $('#bd-filter-panel').removeClass('bd-filter-open');
                $('body').removeClass('bd-filter-panel-open');
            });
            
            // Close on overlay click
            $(document).on('click', function(e) {
                if ($('#bd-filter-panel').hasClass('bd-filter-open') && 
                    !$(e.target).closest('.bd-filter-panel, .bd-filter-toggle').length) {
                    $('#bd-filter-panel').removeClass('bd-filter-open');
                    $('body').removeClass('bd-filter-panel-open');
                }
            });
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        if ($('#bd-filter-panel').length) {
            DirectoryFilters.init();
            
            // Make available globally
            window.DirectoryFilters = DirectoryFilters;
        }
    });
    
})(jQuery);
