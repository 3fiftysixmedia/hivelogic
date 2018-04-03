<?php
/**
 * Template for single gallery post view
 * @package themify
 * @since 1.0.0
 */
?>

<?php get_header(); ?>

<?php 
/** Themify Default Variables
 *  @var object */
global $themify, $themify_gallery;
?>

<?php if( have_posts() ) while ( have_posts() ) : the_post(); ?>

	<section id="featured-area-<?php the_ID(); ?>" class="featured-area <?php echo themify_theme_featured_area_style(); ?>">

		<?php
		/**
		 * GALLERY GRID LAYOUT
		 */
		if ( themify_get( 'gallery_shortcode' ) != '' ) :
			$use = themify_check('setting-img_settings_use');
			$images = $themify_gallery->get_gallery_images();
			$columns = $themify_gallery->get_gallery_columns();
			$columns = ( $columns == '' ) ? 3 : $columns;
			
			$thumb_size = $themify_gallery->get_gallery_size();
			if (!$thumb_size) {
				$thumb_size = 'thumbnail';
			}
			if ($thumb_size !== 'full') {
				$size['width'] = get_option("{$thumb_size}_size_w");
				$size['height'] = get_option("{$thumb_size}_size_h");
			}
		
			if ( $images ) : $counter = 0; ?>

				<div class="gallery-wrapper gallery-columns-<?php echo esc_attr( $columns ); ?> masonry clearfix">
					<div class="grid-size"></div>

				<?php foreach ( $images as $image ) :
					$counter++;
					
					$caption = $themify_gallery->get_caption( $image );
					$description = $themify_gallery->get_description( $image );
					$alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
					if (!$alt) {
                                            $alt = $caption ? $caption : ($description ? $description : the_title_attribute('echo=0'));
					}
					$featured = get_post_meta( $image->ID, 'themify_gallery_featured', true );
					$img_size = $thumb_size !== 'full' ? $size : ( $featured ? array('width' => 500, 'height' => 500) : array('width' => 250, 'height' => 250));
					$img_size = apply_filters('themify_single_gallery_image_size', $img_size, $featured);
					$height = $thumb_size !== 'full' && $featured ? 2 * $size['height'] : $size['height'];
					$thumb = $featured ? 'large' : $thumb_size;
					$img = wp_get_attachment_image_src($image->ID, apply_filters('themify_gallery_post_type_single', $thumb));
					$url = !$featured || $use ? $img[0]:themify_get_image("src={$img[0]}&w={$img_size['width']}&h={$height}&ignore=true&urlonly=true");
					$lightbox_url = $thumb_size!=='large'?wp_get_attachment_image_src($image->ID, 'large'):$img;
				
					?>
					<div class="item gallery-icon <?php echo esc_attr( $featured ); ?>">
						<a href="<?php echo esc_url( $lightbox_url[0] ); ?>" title="<?php esc_attr_e($image->post_title) ?>" data-image="<?php echo esc_url( $lightbox_url[0] ); ?>" data-caption="<?php esc_attr_e($caption); ?>" data-description="<?php esc_attr_e($description); ?>">
							<img src="<?php echo esc_url( $url ) ?>" alt="<?php echo esc_attr( $alt ); ?>" width="<?php echo esc_attr( $img_size['width'] ); ?>" height="<?php echo esc_attr( $height ); ?>" />
							<span><?php echo esc_attr( $caption ); ?></span>
						</a>
					</div>
				<?php endforeach; // images as image ?>

				</div>
				<!-- /.gallery-wrapper -->

			<?php endif; // images ?>

		<?php endif; // gallery shortcode ?>

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

			<?php if(!themify_check('setting-comments_posts')): ?>
				<?php comments_template(); ?>
			<?php endif; ?>

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