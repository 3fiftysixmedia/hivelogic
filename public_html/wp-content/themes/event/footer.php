<?php
/**
 * Template for site footer
 * @package themify
 * @since 1.0.0
 */
?>
<?php
/** Themify Default Variables
 *  @var object */
	global $themify; ?>

				<?php themify_layout_after(); // hook ?>
			</div>
			<!-- /body -->

			<div id="footerwrap">

				<?php themify_footer_before(); // hook ?>
				<footer id="footer" class="pagewidth clearfix" itemscope="itemscope" itemtype="https://schema.org/WPFooter">
					<?php themify_footer_start(); // hook ?>

					<div class="footer-inner">

						<?php get_template_part( 'includes/footer-widgets'); ?>

						<div class="col2-1 first">
							<div class="footer-text clearfix">
								<?php themify_the_footer_text(); ?>
								<?php themify_the_footer_text('right'); ?>
							</div>
							<!-- /footer-text -->
						</div>

						<div class="footer-nav-wrap col2-1">
							<?php if ( function_exists( 'wp_nav_menu' ) ) {
								wp_nav_menu( array( 'theme_location' => 'footer-nav', 'fallback_cb' => '', 'container'  => '', 'menu_id' => 'footer-nav', 'menu_class' => 'footer-nav' ) );
							} else {
								themify_default_main_nav();
							} ?>
						</div>
						<!-- /.footer-nav-wrap -->

					</div>
					<!-- /.footer-inner -->

					<?php themify_footer_end(); // hook ?>
				</footer>
				<!-- /#footer -->
				<?php themify_footer_after(); // hook ?>

			</div>
			<!-- /#footerwrap -->

		</div>
		<!-- /#pagewrap -->

		<?php
		/**
		 *  Stylesheets and Javascript files are enqueued in theme-functions.php
		 */
		?>

		<!-- wp_footer -->
		<?php themify_body_end(); // hook ?>
		<?php wp_footer(); ?>
	</body>
</html>