<?php
/**
 * Template Name: Business Directory
 */

get_header();
?>

<div class="bd-directory-page" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <!-- Back to Home Button -->
    <a href="<?php echo home_url(); ?>" class="bd-back-home" style="position: fixed; top: 20px; left: 20px; z-index: 9999;">
        ← Home
    </a>
    
    <?php echo do_shortcode('[business_directory_complete]'); ?>
</div>

<?php
get_footer();