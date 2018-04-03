<?php
/**
 * Template for single post view
 * @package themify
 * @since 1.0.0
 */
?>

<?php get_header(); ?>

<?php 
/** Themify Default Variables
 *  @var object */
global $themify;
?>

<?php if( have_posts() ) while ( have_posts() ) : the_post(); ?>

	<section class="featured-area <?php echo themify_theme_featured_area_style(); ?>">
		<?php get_template_part( 'includes/post-media', get_post_type()); ?>
	</section>

	<!-- layout-container -->
	<div id="layout" class="pagewidth clearfix">

		<?php themify_content_before(); // hook ?>
		<!-- content -->
		<div id="content" class="list-post">
			<?php themify_content_start(); // hook ?>

			<?php get_template_part( 'includes/loop', get_post_type()); ?>

			<?php wp_link_pages(array('before' => '<p class="post-pagination"><strong>' . __('Pages:', 'themify') . ' </strong>', 'after' => '</p>', 'next_or_number' => 'number')); ?>

			<?php get_template_part( 'includes/author-box', 'single'); ?>

			<?php get_template_part( 'includes/post-nav'); ?>

				<?php comments_template(); ?>

		<?php themify_content_end(); // hook ?>	
	</div>
	<!-- /content -->
    <?php themify_content_after(); // hook ?>

<?php endwhile; ?>

<?php 
/////////////////////////////////////////////
// Sidebar							
/////////////////////////////////////////////
if ($themify->layout != "sidebar-none"): get_sidebar(); endif; ?>

</div>
<!-- /layout-container -->
	
<?php get_footer(); ?>