<?php
/**
 * Template for event post type display.
 * @package themify
 * @since 1.0.0
 */
?>
<?php if(!is_single()){ global $more; $more = 0; } //enable more link ?>

<?php
/** Themify Default Variables
 *  @var object */
global $themify, $themify_event; ?>

<?php themify_post_before(); // hook ?>
<article id="post-<?php the_id(); ?>" <?php post_class('post clearfix event-post'); ?>>
	<?php themify_post_start(); // hook ?>

	<?php get_template_part( 'includes/post-media', 'event'); ?>

	<div class="post-content">

		<?php if ( $themify->hide_event_date != 'yes' || $themify->hide_event_location != 'yes' ) : ?>

			<div class="event-info-wrap clearfix">

				<?php
				$has_start_date = themify_check( 'start_date' );
				$has_end_date = themify_check( 'end_date' );
				if ( ( $has_start_date || $has_end_date ) && $themify->hide_event_date != 'yes' ) : ?>
					<time class="post-date entry-date updated">
						<span class="day">
                        <?php $repeat = false;?>
						<?php if ( $has_start_date ) : ?>
							<span class="event-start-date">
							<?php
                            $repeat = themify_get('repeat');
                            if($repeat){
                                $repeat_x = intval(themify_get('repeat_x'));
                                if(!$repeat_x || $repeat_x<0){
                                    $repeat = false;
                                }
                            }
							$start_date = themify_get( 'start_date' );
							$start_date_parts = explode( ' ', $start_date );
							echo $repeat?
                                themify_theme_get_repeat_date($repeat, $repeat_x,$start_date_parts[0], $start_date_parts[1]):
                                date_i18n( get_option( 'date_format' ), strtotime( $start_date_parts[0] ) ) . _x( ' @ ', 'Connector between date and time (with spaces around itself) in event date and time.', 'themify' ) . date_i18n( get_option( 'time_format' ), strtotime( $start_date_parts[1] ) );
							?>
							</span>
						<?php endif; // has start date ?>

						<?php if (!$repeat && $has_end_date ) : ?>
							<span class="event-end-date">
							<?php
							$end_date = themify_get( 'end_date' );
							$end_date_parts = explode( ' ', $end_date );
                                echo !isset($start_date_parts) || $start_date_parts[0]!=$end_date_parts[0]?_x( ' &#8211; ', 'Character to provide a hint that this is the event end date and time.', 'themify' ) . date_i18n( get_option( 'date_format' ), strtotime( $end_date_parts[0] ) ):' &#8211; ';
                                echo  _x( ' @ ', 'Connector between date and time (with spaces around itself) in event date and time.', 'themify' ) . date_i18n( get_option( 'time_format' ), strtotime( $end_date_parts[1] ) );
							?>
							</span>
						<?php endif; ?>
						</span>
					</time>

					<!-- / .post-date -->
				<?php endif; ?>

				<?php if ( themify_check( 'location' ) && $themify->hide_event_location != 'yes' ) : ?>
					<span class="location">
						<?php echo themify_get( 'location' ); ?>
					</span>
				<?php endif; ?>

			</div>

		<?php endif; // hide event date or location ?>

		<?php if ( $themify->hide_meta != 'yes' ): ?>
			<div class='post-category-wrap'>
				<?php the_terms( get_the_id(), 'event-category', ' <span class="post-category">', ', ', '<i class="divider-small"></i></span>' ); ?>
				<?php the_terms( get_the_id(), 'event-tag', ' <span class="post-tag">', ', ', '</span>' ); ?>
			</div>
		<?php endif; //post meta ?>

		<?php if ( $themify->hide_title != 'yes' ): ?>
			<?php themify_post_title( array( 'tag' => 'h2' ) ); ?>
		<?php endif; //post title ?>


		<?php if ( $themify->hide_meta != 'yes' ) : ?>
			<div class="post-meta entry-meta clearfix">
				<?php get_template_part( 'includes/post-stats' ); ?>
			</div>
		<?php endif; //post meta ?>

		<?php if ( $themify->display_content == 'excerpt' ) : ?>

			<?php the_excerpt(); ?>

			<?php if( themify_check('setting-excerpt_more') ) : ?>
				<p><a href="<?php the_permalink(); ?>" class="more-link"><?php echo themify_check('setting-default_more_text')? themify_get('setting-default_more_text') : __('More &rarr;', 'themify') ?></a></p>
			<?php endif; ?>

		<?php elseif ( $themify->display_content == 'none' ) : ?>

		<?php else: ?>

			<?php the_content(themify_check('setting-default_more_text')? themify_get('setting-default_more_text') : __('More &rarr;', 'themify')); ?>

		<?php endif; //display content ?>

		<div><!-- /.entry-content -->

		<?php edit_post_link(__('Edit', 'themify'), '<span class="edit-button">[', ']</span>'); ?>

	</div>
	<!-- /.post-content -->
	<?php themify_post_end(); // hook ?>

</article>
<!-- /.post -->
<?php themify_post_after(); // hook ?>
