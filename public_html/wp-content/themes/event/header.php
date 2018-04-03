<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php
/** Themify Default Variables
 *  @var object */
global $themify; ?>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<!-- wp_header -->
<?php wp_head(); ?>

</head>

<body <?php body_class(); ?>>
<?php themify_body_start(); // hook ?>
<div id="pagewrap" class="hfeed site">

	<div id="headerwrap">

		<div id="nav-bar">
			<div class="pagewidth">
				<div class="header-inner clearfix">

					<?php if ( $site_desc = get_bloginfo( 'description' ) ) : ?>
						<?php global $themify_customizer; ?>
						<div id="site-description" class="site-description"><?php echo class_exists( 'Themify_Customizer' ) ? $themify_customizer->site_description( $site_desc ) : $site_desc; ?></div>
					<?php endif; ?>

					<div class="social-widget">
						<?php dynamic_sidebar('social-widget'); ?>

						<?php if ( ! themify_check('setting-exclude_rss' ) ) : ?>
							<div class="rss"><a href="<?php themify_theme_feed_link(); ?>" class="hs-rss-link"></a></div>
						<?php endif; ?>
					</div>
					<!-- /.social-widget -->

				</div>
				<!-- /.header-inner -->

			</div>
			<!-- /.pagewidth -->

		</div>
		<!-- /#nav-bar -->

		<?php themify_header_before(); // hook ?>

		<header id="header" class="pagewidth clearfix" itemscope="itemscope" itemtype="https://schema.org/WPHeader">

			<div class="header-inner">

					<?php themify_header_start(); // hook ?>

					<?php echo themify_logo_image('site_logo'); ?>

					<a id="menu-icon" href="#"></a>

					<div id="mobile-menu" class="sidemenu sidemenu-off">

							<?php if ( ! themify_check( 'setting-exclude_search_form' ) ) : ?>
								<div id="searchform-wrap">
									<?php get_search_form(); ?>
								</div>
								<!-- /#searchform-wrap -->
							<?php endif; ?>

							<nav itemscope="itemscope" itemtype="https://schema.org/SiteNavigationElement">
								<?php themify_theme_menu_nav(); ?>
								<!-- /#main-nav -->
							</nav>


							<a id="menu-icon-close" href="#sidr"></a>


					<?php themify_header_end(); // hook ?>

				</div>
				<!-- /.header-inner -->
			</div>
		</header>
		<!-- /#header -->

        <?php themify_header_after(); // hook ?>

	</div>
	<!-- /#headerwrap -->

	<div id="body" class="clearfix">
    <?php themify_layout_before(); //hook ?>
