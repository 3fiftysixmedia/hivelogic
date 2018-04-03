<?php get_header(); ?>

<?php
/** Themify Default Variables
 *  @var object */
global $themify; ?>

<!-- layout -->
<div id="layout" class="pagewidth clearfix">

	<?php themify_content_before(); //hook ?>
	<!-- content -->
	<div id="content">
    	<?php themify_content_start(); //hook ?>
	
		<?php 
		/////////////////////////////////////////////
		// Category Title	 							
		/////////////////////////////////////////////
		?>
		
		<h1 class="page-title"><?php single_cat_title(); ?></h1>
		<?php echo themify_get_term_description( $wp_query->query_vars['taxonomy'] ); ?>
		
		<?php
		global $query_string;
		// If it's a taxonomy, set the related post type
		if ( false !== stripos( $wp_query->query_vars['taxonomy'], '-tag' ) ) {
			$set_post_type = str_replace( '-tag', '', $wp_query->query_vars['taxonomy'] );
		} else {
			$set_post_type = str_replace( '-category', '', $wp_query->query_vars['taxonomy'] );
		}
                
		if (!have_posts() && in_array( $wp_query->query_vars['taxonomy'], get_object_taxonomies( $set_post_type ) ) ) {
                        $themify_query = $query_string . '&post_type=' . $set_post_type . '&paged=' . urlencode( $paged );
			if ( 'event' == $set_post_type ) {
				global $themify_event;
				$themify_query = wp_parse_args( $themify_query, apply_filters( 'themify_theme_event_sorting', array(
					'meta_key' => 'end_date',
					'orderby' => 'meta_value',
					'order' => 'ASC',
					'meta_compare' => '>=',
					'meta_value' => date_i18n( $themify_event->date_time_format ),
				)));
			}
			query_posts( $themify_query );
		}
		?>

		<?php 
		/////////////////////////////////////////////
		// Loop	 							
		/////////////////////////////////////////////
		?>
		<?php if (have_posts()) : ?>
                        <?php 
                            $post_type = get_query_var('post_type');
                            $class = is_array($post_type)?implode('-',$post_type):$post_type;
                        ?>
			<!-- loops-wrapper -->
			<div id="loops-wrapper" class="loops-wrapper <?php echo "$themify->layout $themify->post_layout " .$class;
			?>">

				<?php while ( have_posts() ) : the_post(); ?>

					<?php if ( in_array( $set_post_type, array( 'event', 'gallery', 'video' ) ) ) : ?>
						<?php get_template_part( 'includes/loop-' . $set_post_type, 'index' ); ?>
					<?php else: ?>
						<?php get_template_part( 'includes/loop', 'index' ); ?>
					<?php endif; ?>

				<?php endwhile; ?>

			</div>
			<!-- /loops-wrapper -->

			<?php get_template_part( 'includes/pagination' ); ?>

		<?php 
		/////////////////////////////////////////////
		// Error - No Page Found	 							
		/////////////////////////////////////////////
		?>
	
		<?php else : ?>

			<p><?php _e( 'Sorry, nothing found.', 'themify' ); ?></p>
	
		<?php endif; ?>			
	
    	<?php themify_content_end(); //hook ?>
	</div>
	<!-- /#content -->
    <?php themify_content_after() //hook; ?>

	<?php 
	/////////////////////////////////////////////
	// Sidebar							
	/////////////////////////////////////////////
	 if ( $themify->layout != 'sidebar-none' ): get_sidebar(); endif; ?>

</div>
<!-- /#layout -->

<?php get_footer(); ?>