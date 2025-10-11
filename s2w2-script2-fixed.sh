#!/bin/bash

# Sprint 2 Week 2 - Script 2: Filter UI (FIXED & IDEMPOTENT)
# Creates filter panel, JavaScript for real-time updates, and styling

set -e

PLUGIN_DIR="wp-content/plugins/business-directory"

echo "🎨 Sprint 2 Week 2 - Script 2: Filter UI (FIXED)"
echo "============================================================"

# Create directories
mkdir -p "$PLUGIN_DIR/src/Frontend"
mkdir -p "$PLUGIN_DIR/assets/js"
mkdir -p "$PLUGIN_DIR/assets/css"

# ============================================================================
# 1. Filters.php - Renders filter UI
# ============================================================================
echo "Creating Filters.php..."
cat > "$PLUGIN_DIR/src/Frontend/Filters.php" << 'EOF'
<?php
namespace BusinessDirectory\Frontend;

use BusinessDirectory\Search\FilterHandler;

class Filters {
    
    public static function init() {
        add_shortcode('business_filters', [__CLASS__, 'render_filters']);
    }
    
    /**
     * Render filter panel
     */
    public static function render_filters($atts = []) {
        $metadata = FilterHandler::get_filter_metadata();
        
        ob_start();
        ?>
        <div id="bd-filter-panel" class="bd-filter-panel">
            <!-- Mobile Toggle Button -->
            <button class="bd-filter-toggle bd-mobile-only" aria-label="Toggle Filters">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M3 5h14M3 10h10M3 15h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span>Filters</span>
                <span class="bd-filter-count" style="display: none;"></span>
            </button>
            
            <!-- Filter Panel Content -->
            <div class="bd-filter-content">
                <div class="bd-filter-header">
                    <h3>Filters</h3>
                    <button class="bd-filter-close bd-mobile-only" aria-label="Close Filters">×</button>
                </div>
                
                <!-- Keyword Search -->
                <div class="bd-filter-group">
                    <label class="bd-filter-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M11.5 6.5a5 5 0 11-10 0 5 5 0 0110 0z"/>
                            <path d="M13.5 13.5l-3-3"/>
                        </svg>
                        Search
                    </label>
                    <input 
                        type="text" 
                        id="bd-keyword-search" 
                        class="bd-filter-input" 
                        placeholder="Search businesses..."
                        autocomplete="off"
                    />
                    <div id="bd-search-autocomplete" class="bd-autocomplete" style="display: none;"></div>
                </div>
                
                <!-- Location & Radius -->
                <div class="bd-filter-group">
                    <label class="bd-filter-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 0C5.2 0 3 2.2 3 5c0 3.5 5 11 5 11s5-7.5 5-11c0-2.8-2.2-5-5-5zm0 7.5c-1.4 0-2.5-1.1-2.5-2.5S6.6 2.5 8 2.5s2.5 1.1 2.5 2.5S9.4 7.5 8 7.5z"/>
                        </svg>
                        Location
                    </label>
                    <div class="bd-location-buttons">
                        <button id="bd-near-me-btn" class="bd-btn bd-btn-primary">
                            📍 Near Me
                        </button>
                        <button id="bd-use-city-btn" class="bd-btn bd-btn-secondary">
                            Use City
                        </button>
                    </div>
                    
                    <!-- Manual Location Input (hidden by default) -->
                    <div id="bd-manual-location" style="display: none;">
                        <input 
                            type="text" 
                            id="bd-city-input" 
                            class="bd-filter-input" 
                            placeholder="Enter city or zip code"
                        />
                        <button id="bd-city-submit" class="bd-btn bd-btn-small">Go</button>
                    </div>
                    
                    <!-- Location Display -->
                    <div id="bd-location-display" class="bd-location-display" style="display: none;"></div>
                    
                    <!-- Radius Slider -->
                    <div id="bd-radius-container" style="display: none;">
                        <label class="bd-radius-label">
                            Radius: <span id="bd-radius-value">10</span> miles
                        </label>
                        <input 
                            type="range" 
                            id="bd-radius-slider" 
                            min="1" 
                            max="50" 
                            value="10" 
                            class="bd-slider"
                        />
                    </div>
                </div>
                
                <!-- Categories -->
                <?php if (!empty($metadata['categories'])): ?>
                <div class="bd-filter-group">
                    <label class="bd-filter-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M2 2h5v5H2V2zm7 0h5v5H9V2zM2 9h5v5H2V9zm7 0h5v5H9V9z"/>
                        </svg>
                        Category
                    </label>
                    <div class="bd-checkbox-group">
                        <?php foreach ($metadata['categories'] as $category): ?>
                        <label class="bd-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="categories[]" 
                                value="<?php echo esc_attr($category['id']); ?>"
                                data-count="<?php echo esc_attr($category['count']); ?>"
                            />
                            <span><?php echo esc_html($category['name']); ?> (<?php echo $category['count']; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Areas -->
                <?php if (!empty($metadata['areas'])): ?>
                <div class="bd-filter-group">
                    <label class="bd-filter-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 0L0 4v8l8 4 8-4V4L8 0zM8 2l5.5 2.5L8 7 2.5 4.5 8 2z"/>
                        </svg>
                        Area
                    </label>
                    <div class="bd-checkbox-group">
                        <?php foreach ($metadata['areas'] as $area): ?>
                        <label class="bd-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="areas[]" 
                                value="<?php echo esc_attr($area['id']); ?>"
                                data-count="<?php echo esc_attr($area['count']); ?>"
                            />
                            <span><?php echo esc_html($area['name']); ?> (<?php echo $area['count']; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Price Level -->
                <div class="bd-filter-group">
                    <label class="bd-filter-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 2v12M5 5l3-3 3 3M5 11l3 3 3-3"/>
                        </svg>
                        Price Level
                    </label>
                    <div class="bd-checkbox-group">
                        <?php 
                        $price_levels = [
                            '$' => 'Budget',
                            '$$' => 'Moderate',
                            '$$$' => 'Expensive',
                            '$$$$' => 'Very Expensive'
                        ];
                        foreach ($price_levels as $symbol => $label):
                            $count = $metadata['price_levels'][$symbol] ?? 0;
                        ?>
                        <label class="bd-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="price_level[]" 
                                value="<?php echo esc_attr($symbol); ?>"
                            />
                            <span><?php echo esc_html($symbol); ?> <?php echo esc_html($label); ?> (<?php echo $count; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Rating Filter -->
                <div class="bd-filter-group">
                    <label class="bd-filter-label">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 0l2 5h5l-4 4 2 5-5-3-5 3 2-5-4-4h5z"/>
                        </svg>
                        Rating
                    </label>
                    <div class="bd-radio-group">
                        <label class="bd-radio-label">
                            <input type="radio" name="min_rating" value="" checked />
                            <span>All ratings</span>
                        </label>
                        <?php foreach ($metadata['rating_ranges'] as $range => $count): ?>
                        <label class="bd-radio-label">
                            <input 
                                type="radio" 
                                name="min_rating" 
                                value="<?php echo esc_attr(rtrim($range, '+')); ?>"
                            />
                            <span>⭐ <?php echo esc_html($range); ?> stars (<?php echo $count; ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Open Now -->
                <div class="bd-filter-group">
                    <label class="bd-checkbox-label bd-toggle-label">
                        <input type="checkbox" id="bd-open-now" name="open_now" value="1" />
                        <span>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <circle cx="8" cy="8" r="7"/>
                                <path d="M8 3v5l3 3" stroke="white" stroke-width="2" fill="none"/>
                            </svg>
                            Open Now
                        </span>
                    </label>
                </div>
                
                <!-- Clear Filters Button -->
                <div class="bd-filter-actions">
                    <button id="bd-clear-filters" class="bd-btn bd-btn-secondary bd-btn-block">
                        Clear All Filters
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Results Info Bar -->
        <div id="bd-results-info" class="bd-results-info">
            <div class="bd-result-count">
                <span id="bd-result-count-text">Loading...</span>
            </div>
            <div class="bd-sort-options">
                <label for="bd-sort-select">Sort by:</label>
                <select id="bd-sort-select" class="bd-select">
                    <option value="distance">Distance</option>
                    <option value="rating">Rating</option>
                    <option value="newest">Newest</option>
                    <option value="name">Name (A-Z)</option>
                </select>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
EOF

# ============================================================================
# 2. directory-filters.js - Filter interactions
# ============================================================================
echo "Creating directory-filters.js..."
cat > "$PLUGIN_DIR/assets/js/directory-filters.js" << 'EOF'
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
                url: bdVars.apiUrl + '/bd/v1/businesses',
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
EOF

# ============================================================================
# 3. filters.css - Filter panel styling
# ============================================================================
echo "Creating filters.css..."
cat > "$PLUGIN_DIR/assets/css/filters.css" << 'EOF'
/**
 * Business Directory - Filter Panel Styles
 */

/* ============================================================================
   Filter Panel Container
   ========================================================================= */
.bd-filter-panel {
    width: 280px;
    height: 100vh;
    position: sticky;
    top: 0;
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
    overflow-y: auto;
    flex-shrink: 0;
}

.bd-filter-content {
    padding: 20px;
}

.bd-filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e5e7eb;
}

.bd-filter-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #111827;
}

.bd-filter-close {
    display: none;
    background: none;
    border: none;
    font-size: 28px;
    line-height: 1;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
}

/* ============================================================================
   Filter Groups
   ========================================================================= */
.bd-filter-group {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid #f3f4f6;
}

.bd-filter-group:last-child {
    border-bottom: none;
}

.bd-filter-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 12px;
}

.bd-filter-label svg {
    color: #6b7280;
}

/* ============================================================================
   Input Fields
   ========================================================================= */
.bd-filter-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s;
}

.bd-filter-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.bd-select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    cursor: pointer;
}

/* ============================================================================
   Checkboxes & Radio Buttons
   ========================================================================= */
.bd-checkbox-group,
.bd-radio-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bd-checkbox-label,
.bd-radio-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #4b5563;
    cursor: pointer;
    padding: 6px 0;
    transition: color 0.2s;
}

.bd-checkbox-label:hover,
.bd-radio-label:hover {
    color: #111827;
}

.bd-checkbox-label input[type="checkbox"],
.bd-radio-label input[type="radio"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.bd-toggle-label {
    padding: 12px;
    background: #f9fafb;
    border-radius: 6px;
    font-weight: 500;
}

/* ============================================================================
   Location Controls
   ========================================================================= */
.bd-location-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}

.bd-btn {
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.bd-btn-primary {
    background: #3b82f6;
    color: white;
    flex: 1;
}

.bd-btn-primary:hover {
    background: #2563eb;
}

.bd-btn-secondary {
    background: #f3f4f6;
    color: #374151;
    flex: 1;
}

.bd-btn-secondary:hover {
    background: #e5e7eb;
}

.bd-btn-small {
    padding: 8px 16px;
    font-size: 13px;
}

.bd-btn-block {
    width: 100%;
}

.bd-location-display {
    padding: 12px;
    background: #f0f9ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    font-size: 13px;
    color: #1e40af;
    margin-top: 12px;
}

#bd-manual-location {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

#bd-manual-location input {
    flex: 1;
}

/* ============================================================================
   Radius Slider
   ========================================================================= */
#bd-radius-container {
    margin-top: 16px;
}

.bd-radius-label {
    display: block;
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 8px;
}

.bd-slider {
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: #e5e7eb;
    outline: none;
    -webkit-appearance: none;
}

.bd-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.bd-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* ============================================================================
   Results Info Bar
   ========================================================================= */
.bd-results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 10;
}

.bd-result-count {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.bd-sort-options {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #6b7280;
}

/* ============================================================================
   Mobile Styles
   ========================================================================= */
.bd-mobile-only {
    display: none;
}

.bd-filter-toggle {
    display: none;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 100;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.bd-filter-count {
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}

@media (max-width: 768px) {
    .bd-mobile-only {
        display: block;
    }
    
    .bd-filter-toggle {
        display: flex;
    }
    
    .bd-filter-panel {
        position: fixed;
        top: 0;
        left: -100%;
        width: 85%;
        max-width: 320px;
        height: 100vh;
        z-index: 1000;
        transition: left 0.3s ease;
        box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    }
    
    .bd-filter-panel.bd-filter-open {
        left: 0;
    }
    
    .bd-filter-close {
        display: block;
    }
    
    body.bd-filter-panel-open::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }
}

/* ============================================================================
   Loading & Empty States
   ========================================================================= */
.bd-loading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
}

.bd-loading::before {
    content: '';
    width: 14px;
    height: 14px;
    border: 2px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: bd-spin 0.8s linear infinite;
}

@keyframes bd-spin {
    to { transform: rotate(360deg); }
}

/* ============================================================================
   Autocomplete
   ========================================================================= */
.bd-autocomplete {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 6px 6px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    max-height: 200px;
    overflow-y: auto;
    z-index: 10;
}

.bd-autocomplete-item {
    padding: 10px 12px;
    cursor: pointer;
    transition: background 0.2s;
}

.bd-autocomplete-item:hover {
    background: #f3f4f6;
}

/* ============================================================================
   Filter Actions
   ========================================================================= */
.bd-filter-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 2px solid #e5e7eb;
}
EOF

# ============================================================================
# 4. Create loader file for Script 2 (IDEMPOTENT)
# ============================================================================
echo "Creating sprint2-week2-script2-loader.php..."
cat > "$PLUGIN_DIR/includes/sprint2-week2-script2-loader.php" << 'EOF'
<?php
/**
 * Sprint 2 Week 2 Script 2 - Filter UI Loader
 * This file is safe to include multiple times
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load once
if (defined('BD_S2W2_SCRIPT2_LOADED')) {
    return;
}

define('BD_S2W2_SCRIPT2_LOADED', true);

// Load Frontend classes
require_once plugin_dir_path(__FILE__) . '../src/Frontend/Filters.php';

// Initialize
\BusinessDirectory\Frontend\Filters::init();

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', function() {
    // Only load on directory pages
    if (is_page_template('templates/directory.php') || is_singular('business') || has_shortcode(get_post()->post_content ?? '', 'business_filters')) {
        
        // Enqueue filter CSS
        wp_enqueue_style(
            'bd-filters',
            plugins_url('assets/css/filters.css', dirname(__FILE__)),
            [],
            '1.0.0'
        );
        
        // Enqueue filter JS
        wp_enqueue_script(
            'bd-filters',
            plugins_url('assets/js/directory-filters.js', dirname(__FILE__)),
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Localize script with API URL
        wp_localize_script('bd-filters', 'bdVars', [
            'apiUrl' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}, 20);
EOF

# ============================================================================
# 5. Update main plugin file (IDEMPOTENT)
# ============================================================================
echo "Updating business-directory.php..."

# Check if Script 2 loader is already included
if ! grep -q "sprint2-week2-script2-loader.php" "$PLUGIN_DIR/business-directory.php" 2>/dev/null; then
    cat >> "$PLUGIN_DIR/business-directory.php" << 'EOF'

// Load Sprint 2 Week 2 Script 2 - Filter UI
if (file_exists(plugin_dir_path(__FILE__) . 'includes/sprint2-week2-script2-loader.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/sprint2-week2-script2-loader.php';
}
EOF
    echo "✓ Added Script 2 loader to business-directory.php"
else
    echo "✓ Script 2 loader already present in business-directory.php (skipped)"
fi

echo ""
echo "✅ Script 2 Complete: Filter UI"
echo "============================================================"
echo "Created:"
echo "  ✓ Filters.php - Filter panel renderer"
echo "  ✓ directory-filters.js - Real-time filter interactions"
echo "  ✓ filters.css - Mobile-responsive styling"
echo "  ✓ sprint2-week2-script2-loader.php - Idempotent loader"
echo ""
echo "Features:"
echo "  ✓ 7+ filter types (categories, areas, price, rating, etc.)"
echo "  ✓ Real-time updates with debouncing"
echo "  ✓ Mobile-friendly drawer interface"
echo "  ✓ Shareable URLs with filter state"
echo "  ✓ Safe to run multiple times"
echo ""
echo "Next: Run sprint2-week2-script3.sh for Geolocation"
echo ""