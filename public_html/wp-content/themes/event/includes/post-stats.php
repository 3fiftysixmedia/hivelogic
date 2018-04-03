<?php
/**
 * Template for post stats: like button, number of comments, views, share.
 * @since 1.0.0
 */
?>

<?php
/** Themify Default Variables
 *  @var object */
global $themify; ?>

<?php if ( 'yes' != $themify->hide_meta ) : ?>

	<div class="post-stats">

		<?php
			if ( ( is_singular() && '' != $themify->query_category && ! themify_get( 'setting-hidelike_index' ) )
			|| ( is_singular() && '' == $themify->query_category && ! themify_get( 'setting-hidelike_single' ) )
			|| ( ! is_singular() && ! themify_get( 'setting-hidelike_index' ) ) ) : ?>
				<?php $is_liker = themify_theme_is_liker( get_the_ID() ); ?>
				<span class="stats-likes">
					<a href="#" class="likeit <?php echo $is_liker? 'likeit_done': ''; ?>" data-postid="<?php the_ID(); ?>" title="<?php echo $is_liker? __( 'You like this!', 'themify' ): __( 'Like it!', 'themify' ); ?>">
						<i class="count"><?php echo themify_theme_get_like(); ?></i>
					</a>
				</span>
				<!-- /.likeit-wrap -->

		<?php endif; ?>

		<?php  if ( ! themify_get( 'setting-comments_posts' ) && comments_open() && $themify->hide_meta_comment != 'yes' ) : ?>
			<span class="stats-comments">
				<?php comments_popup_link( '<i class="count">0</i>', '<i class="count">1</i>', '<i class="count">%</i>' ); ?>
			</span>
		<?php endif; ?>

		<span class="stats-views"><i class="count"><?php echo themify_theme_get_pageviews(); ?></i></span>

	</div>
	<!-- /.post-stats -->


	<?php get_template_part( 'includes/social-share' ); ?>

<?php endif; // hide post stats ?>