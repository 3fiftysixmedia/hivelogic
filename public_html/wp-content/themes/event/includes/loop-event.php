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
global $themify, $themify_event;
$is_singular = is_singular( 'event' ) && $themify->is_shortcode == false;
?>

<?php themify_post_before(); // hook ?>
<article id="post-<?php the_id(); ?>" <?php post_class('post clearfix event-post'); ?>>
	<?php themify_post_start(); // hook ?>

	<?php if ( 'below' != $themify->media_position && ( ! is_single() || isset( $themify->show_post_media ) ) ) get_template_part( 'includes/post-media',
		'event'); ?>

	<div class="post-content">

		<div class="<?php echo $is_singular ? 'event-single-wrap' : 'event-info-wrap'; ?> clearfix">

			<?php if ( $is_singular && ($map_address=themify_get( 'map_address' ) )) :;?>
				<div class="event-map">
					<?php echo $themify_event->get_map($map_address); ?>
				</div><!-- / .event-map -->
			<?php endif; ?>

			<div class="<?php echo ( $themify->is_shortcode == true ) ? '': 'event-single-details'; ?>">
				<div class="event-info-wrap">

					<?php
					$has_start_date = themify_check( 'start_date' );
					$has_end_date = themify_check( 'end_date' );
					if ( ( $has_start_date || $has_end_date ) && ( ( isset( $themify->is_shortcode ) && isset( $themify->hide_event_date ) && $themify->hide_event_date != 'yes' ) || $is_singular || ( themify_is_query_page() && $themify->hide_event_date != 'yes' ) ) ) : ?>

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
                                echo $repeat?  themify_theme_get_repeat_date($repeat, $repeat_x,$start_date_parts[0], $start_date_parts[1])
                                    :(date_i18n( get_option( 'date_format' ), strtotime( $start_date_parts[0] ) ) . _x( ' @ ', 'Connector between date and time (with spaces around itself) in event date and time.', 'themify' ) . date_i18n( get_option( 'time_format' ), strtotime( $start_date_parts[1] ) ) );
                                ?>
								</span>
							<?php endif; // has start date ?>

							<?php if (!$repeat && $has_end_date  && ($is_singular || (!$is_singular && !themify_check( 'event_end_date_hide' )))) : ?>
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

					<?php if ( themify_check( 'location' ) && ( ( isset( $themify->is_shortcode ) && isset( $themify->hide_event_location ) && $themify->hide_event_location != 'yes' ) || $is_singular || ( themify_is_query_page() && $themify->hide_event_location != 'yes' ) ) ) : ?>
						<span class="location">
							<?php echo themify_get( 'location' ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $is_singular && themify_check( 'map_address' ) ) : ?>
						<div class="address">
							<?php echo wpautop( themify_get( 'map_address' ) ); ?>
						</div>
						<!-- /address -->
					<?php endif; ?>

				</div>
				<!-- / .event-info-wrap -->

				<?php if ( $is_singular && themify_check( 'buy_tickets' ) ) : ?>
					<p>
						<a href="<?php echo themify_get( 'buy_tickets' ); ?>" class="button buy">
							<span class="ticket"></span> <?php _e( 'Buy Tickets', 'themify' ); ?>
						</a>
					</p>
				<?php endif; ?>

			</div><!-- / .event-single-details -->

		</div>

		<?php if($themify->hide_meta != 'yes'): ?>
			<div class='post-category-wrap'>
				<?php the_terms( get_the_id(), 'event-category', ' <span class="post-category">', ', ', '<i class="divider-small"></i></span>' ); ?>
				<?php the_terms( get_the_id(), 'event-tag', ' <span class="post-tag">', ', ', '</span>' ); ?>
			</div>
		<?php endif; //post meta ?>

		<?php if($themify->hide_title != 'yes'): ?>
			<?php themify_post_title( array( 'tag' => 'h2' ) ); ?>
		<?php endif; //post title ?>

		<?php if( ! is_single() && 'below' == $themify->media_position ) get_template_part( 'includes/post-media', 'loop'); ?>

		<?php if ( $themify->hide_meta != 'yes' || $themify->hide_date != 'yes' ) : ?>
			<div class="post-meta entry-meta clearfix">

				<?php if ( $is_singular ) : ?>
					<div class="meta-left clearfix">
						<?php if($themify->hide_meta != 'yes'): ?>
							<span class="author-avatar"><?php echo get_avatar( get_the_author_meta('user_email'), $themify->avatar_size_loop, '' ); ?></span>
							<span class="post-author"><?php echo themify_get_author_link() ?></span>
						<?php endif; ?>

						<?php if ( $themify->hide_date != 'yes' && is_single() && ! isset( $themify->is_shortcode ) ): ?>
							<time datetime="<?php the_time('o-m-d') ?>" class="post-date entry-date updated"><?php echo get_the_date( apply_filters( 'themify_loop_date', '' ) ) ?></time>
						<?php endif; //post date ?>
					</div>

					<div class="meta-right clearfix">
				<?php endif; ?>

					<?php get_template_part( 'includes/post-stats' ); ?>

				<?php if ( $is_singular ) : ?>
					</div>
				<?php endif; ?>

			</div>
		<?php endif; //post meta ?>

		<div class="entry-content">

			<?php if ( 'excerpt' == $themify->display_content && ! is_attachment() ) : ?>

				<?php the_excerpt(); ?>

				<?php if( themify_check('setting-excerpt_more') ) : ?>
					<p><a href="<?php the_permalink(); ?>" class="more-link"><?php echo themify_check('setting-default_more_text')? themify_get('setting-default_more_text') : __('More &rarr;', 'themify') ?></a></p>
				<?php endif; ?>

			<?php elseif($themify->display_content == 'none'): ?>

			<?php else: ?>

				<?php the_content(themify_check('setting-default_more_text')? themify_get('setting-default_more_text') : __('More &rarr;', 'themify')); ?>

			<?php endif; //display content ?>

		</div><!-- /.entry-content -->

		<?php edit_post_link(__('Edit', 'themify'), '<span class="edit-button">[', ']</span>'); ?>

	</div>
	<!-- /.post-content -->
	<?php themify_post_end(); // hook ?>

</article>
<!-- /.post -->
<?php themify_post_after(); // hook ?>
