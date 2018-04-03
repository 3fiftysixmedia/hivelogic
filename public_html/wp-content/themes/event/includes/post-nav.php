<?php 
/**
 * Post Navigation Template
 * @package themify
 * @since 1.0.0
 */

if ( ! themify_check( 'setting-post_nav_disable' ) ) :

	$in_same_cat = themify_check( 'setting-post_nav_same_cat' ) ? true : false;
	$this_post_type = get_post_type();
	$this_taxonomy = ( 'post' == $this_post_type ) ? 'category' : $this_post_type . '-category';
	$previous = get_previous_post_link( '<span class="prev">%link</span>', '<span class="arrow">' . _x( '&laquo;', 'Previous entry link arrow','themify') . '</span> %title', $in_same_cat, '', $this_taxonomy );
	$next = get_next_post_link( '<span class="next">%link</span>', '<span class="arrow">' . _x( '&raquo;', 'Next entry link arrow','themify') . '</span> %title', $in_same_cat, '', $this_taxonomy );
	
    if ( ! empty( $previous ) || ! empty( $next ) ) : ?>

		<div class="post-nav clearfix">
			<?php echo $previous; ?>
			<?php echo $next; ?>
		</div>
		<!-- /.post-nav -->

	<?php endif; // empty previous or next

endif; // check setting nav disable