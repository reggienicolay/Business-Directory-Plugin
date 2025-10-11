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
                    <path d="M3 5h14M3 10h10M3 15h6"/>
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
                
                <!-- Areas -->
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
                                <path d="M8 3v5l3 3" stroke="white" fill="none"/>
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

// Initialize
Filters::init();
