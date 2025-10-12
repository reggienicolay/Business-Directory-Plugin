<?php
/**
 * Single Business Template
 * Template override from Business Directory Plugin
 */

get_header();
?>

<main id="content" class="site-main business-detail-page">
    
    <!-- Back to Directory Button -->
    <a href="<?php echo home_url('/business-directory/'); ?>" class="bd-back-to-directory">
        ← Back to Directory
    </a>
    
    <?php
    while (have_posts()) :
        the_post();
        ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            
            <!-- Business Title -->
            <h1 class="business-title"><?php the_title(); ?></h1>
            
            <!-- Business Content -->
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
            
        </article>
        
    <?php endwhile; ?>
    
</main>

<?php
get_footer();
?>