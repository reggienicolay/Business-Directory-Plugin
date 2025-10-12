<?php

/**
 * Premium Single Business Template
 */

get_header();

while (have_posts()) : the_post();
    $business_id = get_the_ID();
    $location = get_post_meta($business_id, 'bd_location', true);
    $contact = get_post_meta($business_id, 'bd_contact', true);
    $hours = get_post_meta($business_id, 'bd_hours', true);
    $price_level = get_post_meta($business_id, 'bd_price_level', true);
    $avg_rating = get_post_meta($business_id, 'bd_avg_rating', true);
    $review_count = get_post_meta($business_id, 'bd_review_count', true);
    $categories = wp_get_post_terms($business_id, 'bd_category');
    $areas = wp_get_post_terms($business_id, 'bd_area');
?>

    <div class="bd-business-hero">
        <div class="bd-business-hero-content">
            <div class="bd-business-hero-left">
                <h1><?php the_title(); ?></h1>

                <div class="bd-business-meta">
                    <?php if ($avg_rating): ?>
                        <div class="bd-rating-badge">
                            <span class="bd-rating-number"><?php echo number_format($avg_rating, 1); ?></span>
                            <div class="bd-rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="<?php echo $i <= $avg_rating ? 'filled' : 'empty'; ?>">★</span>
                                <?php endfor; ?>
                            </div>
                            <span class="bd-review-count">(<?php echo $review_count; ?> reviews)</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($price_level): ?>
                        <span class="bd-price-badge"><?php echo esc_html($price_level); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($categories)): ?>
                        <span class="bd-category-badge"><?php echo esc_html($categories[0]->name); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($location): ?>
                    <div class="bd-location-quick">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 0C5.2 0 3 2.2 3 5c0 3.5 5 11 5 11s5-7.5 5-11c0-2.8-2.2-5-5-5zm0 7.5c-1.4 0-2.5-1.1-2.5-2.5S6.6 2.5 8 2.5s2.5 1.1 2.5 2.5S9.4 7.5 8 7.5z" />
                        </svg>
                        <?php echo esc_html($location['address']); ?>, <?php echo esc_html($location['city']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bd-business-hero-actions">
                <?php if (!empty($contact['website'])): ?>
                    <a href="<?php echo esc_url($contact['website']); ?>" target="_blank" class="bd-btn bd-btn-primary">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="10" cy="10" r="8" />
                            <path d="M2 10h16M10 2a15.3 15.3 0 0 1 4 8 15.3 15.3 0 0 1-4 8 15.3 15.3 0 0 1-4-8 15.3 15.3 0 0 1 4-8z" />
                        </svg>
                        Visit Website
                    </a>
                <?php endif; ?>

                <?php if (!empty($contact['phone'])): ?>
                    <a href="tel:<?php echo esc_attr($contact['phone']); ?>" class="bd-btn bd-btn-secondary">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                        </svg>
                        <?php echo esc_html($contact['phone']); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bd-business-content-wrapper">

        <!-- Photo Gallery Section -->
        <?php
        $photo_ids = get_post_meta($business_id, 'bd_photos', true);
        $featured_image = get_post_thumbnail_id($business_id);

        // Combine featured image with gallery photos
        $all_photos = [];
        if ($featured_image) {
            $all_photos[] = $featured_image;
        }
        if ($photo_ids && is_array($photo_ids)) {
            $all_photos = array_merge($all_photos, $photo_ids);
        }
        $all_photos = array_unique($all_photos); // Remove duplicates
        ?>

        <?php if (!empty($all_photos)): ?>
            <section class="bd-photo-gallery">
                <!-- Main large photo -->
                <div class="bd-gallery-main">
                    <?php
                    $main_photo = wp_get_attachment_image_url($all_photos[0], 'large');
                    ?>
                    <img src="<?php echo esc_url($main_photo); ?>" alt="<?php the_title(); ?>">
                </div>

                <!-- Side grid of 4 photos -->
                <?php if (count($all_photos) > 1): ?>
                    <div class="bd-gallery-grid">
                        <?php
                        // Show photos 2-5 (up to 4 photos in the grid)
                        $grid_photos = array_slice($all_photos, 1, 4);
                        foreach ($grid_photos as $index => $photo_id):
                            $photo_url = wp_get_attachment_image_url($photo_id, 'medium');

                            // If this is the 4th grid item and there are more photos, show "+X more" overlay
                            $is_last = ($index === 3 || $index === count($grid_photos) - 1);
                            $has_more = count($all_photos) > 5;
                        ?>
                            <div class="bd-gallery-item <?php echo ($is_last && $has_more) ? 'bd-gallery-more' : ''; ?>">
                                <?php if ($is_last && $has_more): ?>
                                    <div class="bd-gallery-more-overlay">
                                        <span>+<?php echo count($all_photos) - 5; ?> more</span>
                                    </div>
                                <?php endif; ?>
                                <img src="<?php echo esc_url($photo_url); ?>" alt="Photo <?php echo $index + 2; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <!-- Claim listing CTA if no photos -->
            <section class="bd-photo-gallery-placeholder">
                <div class="bd-add-photos-cta">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="currentColor">
                        <path d="M24 4C12.95 4 4 12.95 4 24s8.95 20 20 20 20-8.95 20-20S35.05 4 24 4zm10 22h-8v8h-4v-8h-8v-4h8v-8h4v8h8v4z" />
                    </svg>
                    <h3>Is this your business?</h3>
                    <p>Claim this listing to add photos, update info, and respond to reviews</p>
                    <a href="mailto:admin@business-directory.local?subject=Claim Listing: <?php echo urlencode(get_the_title()); ?>" class="bd-btn bd-btn-ghost">Claim This Listing</a>
                </div>
            </section>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="bd-business-grid">

            <!-- Left Column: About & Hours -->
            <div class="bd-business-main">

                <!-- About Section -->
                <div class="bd-info-card bd-about-section">
                    <h2>About <?php the_title(); ?></h2>
                    <div class="bd-description">
                        <?php the_content(); ?>
                    </div>
                </div>

                <!-- Amenities/Features -->
                <?php $features = get_post_meta($business_id, 'bd_features', true); ?>
                <?php if ($features && is_array($features)): ?>
                    <div class="bd-info-card bd-amenities">
                        <h3>Amenities & Features</h3>
                        <div class="bd-amenities-grid">
                            <?php foreach ($features as $feature): ?>
                                <div class="bd-amenity-item">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 0l3 7h7l-5.5 4.5L17 20l-7-5-7 5 2.5-8.5L0 7h7z" />
                                    </svg>
                                    <?php echo esc_html($feature); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Sidebar: Contact & Hours -->
            <aside class="bd-business-sidebar">

                <!-- Hours Card -->
                <?php if ($hours): ?>
                    <div class="bd-info-card bd-hours-card">
                        <h3>Hours</h3>
                        <div class="bd-hours-list">
                            <?php
                            $days = [
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday'
                            ];
                            $today = strtolower(date('l'));

                            foreach ($days as $key => $label):
                                $day_hours = $hours[$key] ?? null;
                                $is_today = ($key === $today);
                            ?>
                                <div class="bd-hours-row <?php echo $is_today ? 'bd-today' : ''; ?>">
                                    <span class="bd-day"><?php echo $label; ?></span>
                                    <span class="bd-time">
                                        <?php
                                        if (empty($day_hours) || !empty($day_hours['closed'])) {
                                            echo 'Closed';
                                        } else {
                                            echo esc_html($day_hours['open']) . ' - ' . esc_html($day_hours['close']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Contact Card -->
                <div class="bd-info-card bd-contact-card">
                    <h3>Contact</h3>
                    <div class="bd-contact-list">
                        <?php if ($location): ?>
                            <div class="bd-contact-item">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 0C6.5 0 3.75 2.75 3.75 6.25c0 4.375 6.25 13.75 6.25 13.75s6.25-9.375 6.25-13.75C16.25 2.75 13.5 0 10 0zm0 9.375c-1.75 0-3.125-1.375-3.125-3.125S8.25 3.125 10 3.125s3.125 1.375 3.125 3.125S11.75 9.375 10 9.375z" />
                                </svg>
                                <div>
                                    <strong>Address</strong>
                                    <p><?php echo esc_html($location['address']); ?><br>
                                        <?php echo esc_html($location['city']); ?>, <?php echo esc_html($location['state']); ?> <?php echo esc_html($location['zip']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($contact['phone'])): ?>
                            <div class="bd-contact-item">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M3.75 2.5a1.25 1.25 0 011.25-1.25h10a1.25 1.25 0 011.25 1.25v15a1.25 1.25 0 01-1.25 1.25H5a1.25 1.25 0 01-1.25-1.25v-15z" />
                                </svg>
                                <div>
                                    <strong>Phone</strong>
                                    <p><a href="tel:<?php echo esc_attr($contact['phone']); ?>"><?php echo esc_html($contact['phone']); ?></a></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($contact['email'])): ?>
                            <div class="bd-contact-item">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2.5 5.625A1.875 1.875 0 014.375 3.75h11.25A1.875 1.875 0 0117.5 5.625v8.75a1.875 1.875 0 01-1.875 1.875H4.375A1.875 1.875 0 012.5 14.375v-8.75zM10 11.25l-7.5-5v1.25l7.5 5 7.5-5V6.25l-7.5 5z" />
                                </svg>
                                <div>
                                    <strong>Email</strong>
                                    <p><a href="mailto:<?php echo esc_attr($contact['email']); ?>"><?php echo esc_html($contact['email']); ?></a></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<!-- Map Preview -->
                <?php if ($location && !empty($location['lat']) && !empty($location['lng'])): ?>
                    <div class="bd-info-card bd-map-preview">
                        <h3>Location</h3>
                        <div id="bd-location-map" style="height: 250px; border-radius: 12px; overflow: hidden; margin-bottom: 16px;"
                            data-lat="<?php echo esc_attr($location['lat']); ?>"
                            data-lng="<?php echo esc_attr($location['lng']); ?>"></div>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($location['address'] . ', ' . $location['city']); ?>"
                            target="_blank" 
                            class="bd-map-link"
                            style="display: inline-flex; align-items: center; gap: 8px; color: #6b2c3e; font-weight: 600; text-decoration: none;">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 0C5.2 0 3 2.2 3 5c0 3.5 5 11 5 11s5-7.5 5-11c0-2.8-2.2-5-5-5zm0 7.5c-1.4 0-2.5-1.1-2.5-2.5S6.6 2.5 8 2.5s2.5 1.1 2.5 2.5S9.4 7.5 8 7.5z"/>
                            </svg>
                            Get Directions →
                        </a>
                    </div>
                <?php endif; ?>

            </aside>

        </div>

    </div>

<?php
endwhile;

// Load Leaflet.js for map if location exists
if ($location && !empty($location['lat']) && !empty($location['lng'])): 
?>
<!-- Load Leaflet.js for map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var mapElement = document.getElementById('bd-location-map');
    
    if (mapElement) {
        var lat = parseFloat(mapElement.dataset.lat);
        var lng = parseFloat(mapElement.dataset.lng);
        
        // Initialize map
        var map = L.map('bd-location-map', {
            scrollWheelZoom: false,
            dragging: true,
            touchZoom: true,
            doubleClickZoom: true
        }).setView([lat, lng], 15);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Custom marker icon
        var customIcon = L.divIcon({
            className: 'custom-map-marker',
            html: '<div style="background: #6b2c3e; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><div style="transform: rotate(45deg); margin-top: 5px; margin-left: 7px; font-size: 16px; color: white;">📍</div></div>',
            iconSize: [30, 42],
            iconAnchor: [15, 42]
        });
        
        // Add marker
        L.marker([lat, lng], { icon: customIcon }).addTo(map);
    }
});
</script>
<?php endif; ?>

<?php get_footer(); ?>