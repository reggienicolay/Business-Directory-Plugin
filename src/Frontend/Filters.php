<?php
namespace BusinessDirectory\Frontend;

use BusinessDirectory\Search\FilterHandler;

class Filters {
    
    public static function init() {
        add_shortcode('business_filters', [__CLASS__, 'render_filters']);
        add_shortcode('business_directory_complete', [__CLASS__, 'render_complete_directory']);
        
        // Disable wpautop for our shortcodes to prevent <br> injection
        add_filter('the_content', [__CLASS__, 'disable_wpautop_for_shortcodes'], 9);
    }
    
    /**
     * Disable wpautop filter for our shortcodes
     */
    public static function disable_wpautop_for_shortcodes($content) {
        if (has_shortcode($content, 'business_directory_complete') || 
            has_shortcode($content, 'business_filters')) {
            remove_filter('the_content', 'wpautop');
            remove_filter('the_content', 'wptexturize');
        }
        return $content;
    }
    
    /**
     * Render complete directory (filters + map + list)
     */
    public static function render_complete_directory($atts = []) {
        $metadata = FilterHandler::get_filter_metadata();
        
        ob_start();
        ?>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <!-- Filters Sidebar -->
            <div style="flex: 0 0 280px;">
                <?php echo self::render_filter_panel_only($metadata); ?>
            </div>
            
            <!-- Main Content Area -->
            <div style="flex: 1; min-width: 300px;">
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
                
                <!-- Map -->
                <div id="bd-map" style="height: 600px; margin: 20px 0; border: 1px solid #ddd; border-radius: 8px; background: #f5f5f5;"></div>
                
                <!-- Business List -->
                <div id="bd-business-list"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('Complete directory initializing...');
            
            // Initialize map
            if (typeof L !== 'undefined' && window.DirectoryMap) {
                DirectoryMap.init('bd-map', []);
            } else {
                $('#bd-map').html('<p style="padding: 40px; text-align: center; color: #666;">Map requires Leaflet library</p>');
            }
            
            // Handle filter updates
            $(document).on('bd:filters:applied', function(e, response) {
                console.log('Filters applied, businesses:', response.businesses);
                
                // Update map
                if (window.DirectoryMap && response.businesses) {
                    DirectoryMap.updateBusinesses(response.businesses);
                    if (response.bounds) {
                        DirectoryMap.fitBounds(response.bounds);
                    }
                }
                
                // Render business list
                var html = '';
                if (response.businesses && response.businesses.length > 0) {
                    response.businesses.forEach(function(business) {
                        html += '<div style="border: 1px solid #e5e7eb; padding: 20px; margin-bottom: 15px; border-radius: 8px; background: white;">';
                        html += '<h3 style="margin: 0 0 10px 0;">' + business.title + '</h3>';
                        
                        if (business.rating > 0) {
                            html += '<div style="margin-bottom: 8px;">⭐ ' + business.rating + ' (' + business.review_count + ' reviews)</div>';
                        }
                        
                        if (business.price_level) {
                            html += '<div style="margin-bottom: 8px;">💰 ' + business.price_level + '</div>';
                        }
                        
                        if (business.distance) {
                            html += '<div style="margin-bottom: 8px;">📍 ' + business.distance.display + '</div>';
                        }
                        
                        if (business.categories && business.categories.length > 0) {
                            html += '<div style="margin-bottom: 8px; color: #6b7280;">🏷️ ' + business.categories.join(', ') + '</div>';
                        }
                        
                        html += '<a href="' + business.permalink + '" style="color: #3b82f6; text-decoration: none; font-weight: 500;">View Details →</a>';
                        html += '</div>';
                    });
                } else {
                    html = '<p style="text-align: center; padding: 40px; color: #666;">No businesses found. Try adjusting your filters or location.</p>';
                }
                
                $('#bd-business-list').html(html);
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render just the filter panel (no results bar)
     */
    private static function render_filter_panel_only($metadata) {
        ob_start();
        ?>
        <div id="bd-filter-panel" class="bd-filter-panel"><button class="bd-filter-toggle bd-mobile-only" aria-label="Toggle Filters"><span>Filters</span><span class="bd-filter-count" style="display: none;"></span></button><div class="bd-filter-content"><div class="bd-filter-header"><h3>Filters</h3><button class="bd-filter-close bd-mobile-only">×</button></div><div class="bd-filter-group"><label class="bd-filter-label">🔍 Search</label><input type="text" id="bd-keyword-search" class="bd-filter-input" placeholder="Search businesses..."/></div><div class="bd-filter-group"><label class="bd-filter-label">📍 Location</label><div class="bd-location-buttons"><button id="bd-near-me-btn" class="bd-btn bd-btn-primary">📍 Near Me</button><button id="bd-use-city-btn" class="bd-btn bd-btn-secondary">Use City</button></div><div id="bd-manual-location" style="display: none; margin-top: 10px;"><input type="text" id="bd-city-input" class="bd-filter-input" placeholder="Enter city" style="margin-bottom: 8px;"/><button id="bd-city-submit" class="bd-btn bd-btn-small">Go</button></div><div id="bd-location-display" class="bd-location-display" style="display: none;"></div><div id="bd-radius-container" style="display: none; margin-top: 12px;"><label class="bd-radius-label">Radius: <span id="bd-radius-value">10</span> miles</label><input type="range" id="bd-radius-slider" min="1" max="50" value="10" class="bd-slider"/></div></div><?php if (!empty($metadata['categories'])): ?><div class="bd-filter-group"><label class="bd-filter-label">🏷️ Categories</label><div class="bd-checkbox-group"><?php foreach ($metadata['categories'] as $category): ?><label class="bd-checkbox-label"><input type="checkbox" name="categories[]" value="<?php echo esc_attr($category['id']); ?>"/><span><?php echo esc_html($category['name']); ?> (<?php echo $category['count']; ?>)</span></label><?php endforeach; ?></div></div><?php endif; ?><?php if (!empty($metadata['areas'])): ?><div class="bd-filter-group"><label class="bd-filter-label">🗺️ Areas</label><div class="bd-checkbox-group"><?php foreach ($metadata['areas'] as $area): ?><label class="bd-checkbox-label"><input type="checkbox" name="areas[]" value="<?php echo esc_attr($area['id']); ?>"/><span><?php echo esc_html($area['name']); ?> (<?php echo $area['count']; ?>)</span></label><?php endforeach; ?></div></div><?php endif; ?><div class="bd-filter-group"><label class="bd-filter-label">💰 Price Level</label><div class="bd-checkbox-group"><?php 
                        $price_levels = ['$' => 'Budget', '$$' => 'Moderate', '$$$' => 'Expensive', '$$$$' => 'Very Expensive'];
                        foreach ($price_levels as $symbol => $label):
                            $count = $metadata['price_levels'][$symbol] ?? 0;
                        ?><label class="bd-checkbox-label"><input type="checkbox" name="price_level[]" value="<?php echo esc_attr($symbol); ?>"/><span><?php echo esc_html($symbol . ' ' . $label); ?> (<?php echo $count; ?>)</span></label><?php endforeach; ?></div></div><div class="bd-filter-group"><label class="bd-filter-label">⭐ Rating</label><div class="bd-radio-group"><label class="bd-radio-label"><input type="radio" name="min_rating" value="" checked/><span>All ratings</span></label><?php foreach ($metadata['rating_ranges'] as $range => $count): ?><label class="bd-radio-label"><input type="radio" name="min_rating" value="<?php echo esc_attr(rtrim($range, '+')); ?>"/><span>⭐ <?php echo esc_html($range); ?> stars (<?php echo $count; ?>)</span></label><?php endforeach; ?></div></div><div class="bd-filter-group"><label class="bd-checkbox-label bd-toggle-label"><input type="checkbox" id="bd-open-now" name="open_now" value="1"/><span>🕐 Open Now</span></label></div><div class="bd-filter-actions"><button id="bd-clear-filters" class="bd-btn bd-btn-secondary bd-btn-block">Clear All Filters</button></div></div></div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render filter panel with results bar (standalone use)
     */
    public static function render_filters($atts = []) {
        $metadata = FilterHandler::get_filter_metadata();
        
        ob_start();
        echo self::render_filter_panel_only($metadata);
        ?>
        
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