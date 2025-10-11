<?php
/**
 * Template Name: Business Directory
 */

get_header();
?>

<div class="bd-directory-container" style="display: flex; gap: 20px; max-width: 1400px; margin: 0 auto; padding: 20px;">
    
    <!-- Filter Sidebar -->
    <aside class="bd-sidebar" style="flex: 0 0 280px;">
        <?php echo do_shortcode('[business_filters]'); ?>
    </aside>
    
    <!-- Main Content -->
    <main class="bd-main-content" style="flex: 1;">
        
        <!-- Map/List Toggle -->
        <div class="bd-view-toggle" style="margin-bottom: 20px;">
            <button id="bd-map-view" class="bd-view-btn active" style="padding: 10px 20px; margin-right: 10px; cursor: pointer;">Map</button>
            <button id="bd-list-view" class="bd-view-btn" style="padding: 10px 20px; cursor: pointer;">List</button>
        </div>
        
        <!-- Map View -->
        <div id="bd-map" style="height: 600px; border-radius: 8px; overflow: hidden;"></div>
        
        <!-- List View -->
        <div id="bd-list-container" style="display: none;">
            <div id="bd-business-list"></div>
        </div>
        
    </main>
    
</div>

<script>
jQuery(document).ready(function($) {
    // View toggle
    $('#bd-map-view').on('click', function() {
        $(this).addClass('active');
        $('#bd-list-view').removeClass('active');
        $('#bd-map').show();
        $('#bd-list-container').hide();
    });
    
    $('#bd-list-view').on('click', function() {
        $(this).addClass('active');
        $('#bd-map-view').removeClass('active');
        $('#bd-map').hide();
        $('#bd-list-container').show();
    });
});
</script>

<?php
get_footer();