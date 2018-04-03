;// Themify Theme Scripts - http://themify.me/

// Initialize object literals
var FixedHeader = {},
	ThemifyEqualHeight = {},
	ThemifyTabs = {},
	ThemifyShortest = {},
	ThemifySlider = {},
	ThemifyVideo = {},
	ThemifyParallax = {};

/////////////////////////////////////////////
// jQuery functions					
/////////////////////////////////////////////
(function($){

	var t,n,r,s=$.event,i={_:0},h=0;t=s.special.throttledresize={setup:function(){$(this).on("resize",t.handler);},teardown:function(){$(this).off("resize",t.handler);},handler:function(o,a){var l=this,d=arguments;n=!0,r||(setInterval(function(){h++,(h>t.threshold&&n||a)&&(o.type="throttledresize",s.dispatch.apply(l,d),n=!1,h=0),h>9&&($(i).stop(),r=!1,h=0);},30),r=!0);},threshold:0};

	// Test if touch event exists //////////////////////////////
	function is_touch_device() {
		return $('body').hasClass('touch');
	}

	// Fixed Header /////////////////////////
	FixedHeader = {
		init: function() {
			if( '' != themifyScript.fixedHeader ) {
				var cons = is_touch_device() ? 10 : 74;
				FixedHeader.headerHeight = $('#headerwrap').height() - cons;
				this.activate();
				$(window).on('scroll touchstart.touchScroll touchmove.touchScroll', this.activate);
			} else {
				$('#pagewrap').css('paddingTop', Math.floor( $('#headerwrap').height() ));
			}
		},
		activate: function() {
			var $window = $(window),
				scrollTop = $window.scrollTop(),
				$h_height = $('#headerwrap').height();
			$('#pagewrap').css('paddingTop', Math.floor( $h_height ));
			if( scrollTop > FixedHeader.headerHeight ) {
				FixedHeader.scrollEnabled();
			} else {
				FixedHeader.scrollDisabled();
			}
		},
		scrollDisabled: function() {
			$('#headerwrap').removeClass('fixed-header');
			$('#header').removeClass('header-on-scroll');
			$('body').removeClass('fixed-header-on');
		},
		scrollEnabled: function() {
			$('#headerwrap').addClass('fixed-header');
			$('#header').addClass('header-on-scroll');
			$('body').addClass('fixed-header-on');
		}
	};

	// Equal height for Content and Sidebar /////////////////////////
	ThemifyEqualHeight = {
		$content: $('#content'),
		$sidebar: $('#sidebar'),
		contentMarginTop: 10,
		smallScreen: 0,
		resizeRefresh: 0,

		init: function( smallScreen, resizeRefresh ) {
			this.smallScreen = parseInt( smallScreen, 10 );
			this.resizeRefresh = parseInt( resizeRefresh, 10 );
			this.contentMarginTop = parseInt( this.$content.css( 'margin-top' ).replace(/-/, '').replace(/px/, ''), 10 );

			if ( ! this.isSmallScreen() ) {
				this.setHeights();
			}

			if( !( 'orientation' in window ) ) {
				var didResize = false;

				$(window).resize(function() {
					didResize = true;
				});

				setInterval(function() {
					if ( didResize ) {
						didResize = false;
						if ( ! ThemifyEqualHeight.isSmallScreen() ) {
							ThemifyEqualHeight.setHeights();
						} else {
							ThemifyEqualHeight.$content.css( 'height', 'auto' );
							ThemifyEqualHeight.$sidebar.css( 'height', 'auto' );
						}
					}
				}, ThemifyEqualHeight.resizeRefresh);
			}
		},

		isSmallScreen: function() {
			return $(window).width() <= this.smallScreen;
		},

		setHeights: function() {
			if ( Math.floor( this.$content.innerHeight() ) - this.contentMarginTop > Math.floor( this.$sidebar.innerHeight() ) ) {
				this.$sidebar.innerHeight( Math.floor( this.$content.innerHeight() ) );
			} else {
				this.$content.innerHeight( Math.floor( this.$sidebar.innerHeight() ) + this.contentMarginTop );
			}
		}
	};

	ThemifyTabs = {
		init: function( tabset, suffix ) {
			$( tabset ).each(function(){
				var $tabset = $(this);

				$('.htab-link:first', $tabset).addClass('current');
				$('.btab-panel:first', $tabset).show();

				$( $tabset ).on( 'click', '.htab-link', function(e){
					e.preventDefault();

					var $a = $(this),
						tab = '.' + $a.data('tab') + suffix,
						$tab = $( tab, $tabset );

					$( '.htab-link', $tabset ).removeClass('current');
					$a.addClass('current');

					$( '.btab-panel', $tabset ).hide();
					$tab.show();

					$(document.body).trigger( 'themify-tab-switched', $tab );
				});
			});
		}
	};

	ThemifyShortest = {
		init: function($context, area) {
			this.setShortest( $context, area );
			$(window).on('resize', function(){
				ThemifyShortest.setShortest( $context, area );
			});
		},

		setShortest: function($context, area) {
			var imgs = $('img', $context),
				shortestImg = 999999;

			imgs.each(function () {
				var $img = $(this),
					imgHeight = $img.outerHeight();
				shortestImg = shortestImg <= imgHeight ? shortestImg : imgHeight;
			});

			imgs.each(function () {
				var $img = $(this),
					imgHeight = $img.outerHeight();
				$img.css('marginTop', -Math.abs(imgHeight - shortestImg)/2);
			});

			$context.height(shortestImg);
			if ( 'video' == area ) {
				$('.slideshow, .caroufredsel_wrapper, .carousel-prev, .carousel-next', $context).height(shortestImg);
			} else {
				$('.post-image', $context).height(shortestImg);
			}
		}
	};

	ThemifySlider = {
		// Initialize carousels
		create: function(obj) {
			obj.each(function() {
				var $this = $(this ),
					$sliderWrapper = $this.closest('.loops-wrapper.slider');
				// Start Carousel
				$this.carouFredSel({
					responsive : true,
					prev : function() {
						if ( ! $sliderWrapper.hasClass('video') ) {
							return '#' + $this.data('id') + ' .carousel-prev';
						} else {
							$('#' + $this.data('id') + ' .carousel-prev').remove();
						}
					},
					next: function() {
						if ( ! $sliderWrapper.hasClass('video') ) {
							return '#' + $this.data( 'id' ) + ' .carousel-next';
						} else {
							$('#' + $this.data('id') + ' .carousel-next').remove();
						}
					},
					pagination : {
						container : '#' + $this.data('id') + ' .carousel-pager',
						anchorBuilder: function( nr, item ) {}
					},
					circular : true,
					infinite : true,
					swipe: true,
					scroll : {
						items : 1,
						fx : $this.data('effect'),
						duration : parseInt($this.data('speed')),
						onBefore: function() {
							var pos = $(this).triggerHandler( 'currentPosition' );
							$('#' + $this.data('thumbsid') + ' a').removeClass( 'selected' );
							$('#' + $this.data('thumbsid') + ' a.itm'+pos).addClass( 'selected' );
							var page = Math.floor( pos / 3 );
							$('#' + $this.data('thumbsid')).trigger( 'slideToPage', page );
						}
					},
					auto : {
						play : !!('off' != $this.data('autoplay')),
						timeoutDuration : 'off' != $this.data('autoplay') ? parseInt($this.data('autoplay')) : 0
					},
					items : {
						visible : {
							min : 1,
							max : 1
						},
						width : 222
					},
					onCreate : function() {
						$this.closest('.slideshow-wrap').css({
							'visibility' : 'visible',
							'height' : 'auto'
						});
						$this.closest('.loops-wrapper.slider').css({
							'visibility' : 'visible',
							'height' : 'auto'
						});
						$('.carousel-next, .carousel-prev', $this.closest('.slideshow-wrap')).empty().show();
						if ( $sliderWrapper.hasClass('video') ) {
							ThemifyShortest.init( $this );
						}
						$(window).resize();
						$('.slideshow-slider-loader', $this.closest('.slider')).remove(); // remove slider loader
					}
				});
				// End Carousel

				// Start video slider thumbnails carousel
				if ( $sliderWrapper.hasClass('video') ) {
					var $videoPager = $('#' + $this.data('thumbsid'));
					$('.carousel-nav-wrap', $this.closest('.slideshow-wrap')).remove();
					$('a', $videoPager).each(function(i) {
						$(this).addClass( 'itm'+i );
						$(this).click(function() {
							$this.trigger( 'slideTo', [i, 0, true] );
							return false;
						});
					});
					$( '.slideshow', $videoPager ).carouFredSel({
						direction: 'left',
						circular: true,
						responsive: true,
						infinite: false,
						align: false,
						auto: false,
						items : {
							visible : {
								min : 1,
								max : 4
							},
							width : 250,
							height: 140
						},
						prev: '#' + $this.data('thumbsid') + ' .carousel-prev',
						next: '#' + $this.data('thumbsid') + ' .carousel-next',
						onCreate: function() {
							$('.slideshow-wrap', $videoPager).css({
								'visibility' : 'visible',
								'height' : 'auto'
							});
							$('.carousel-next, .carousel-prev', $videoPager).empty().show();
						}
					});
					// Get shortest image
					ThemifyShortest.init( $videoPager, 'video' );
				}
			});
		},

		isSmallScreen: function() {
			return $(window).width() <= 905;
		},

		// Initialize carousels
		createGallery: function(obj) {

			var waitForFinalEvent = (function () {
				var timers = {};
				return function (callback, ms, uniqueId) {
					if (timers[uniqueId]) {
						clearTimeout (timers[uniqueId]);
					}
					timers[uniqueId] = setTimeout(callback, ms);
				};
			})();

			$(window).resize(function () {
				waitForFinalEvent(function(){
					obj.each(function() {
						if ( ThemifySlider.isSmallScreen() ) {
							$(this).trigger('configuration', {
									items : {
										visible : {
											min : 1,
											max : 1
										},
										width : 400,
										height: 'variable'
									}
								},
								null,
								true
							);
							$('.type-gallery', $(this)).css({
								'opacity': 1,
								'margin': 0,
								'left': 'auto'
							});
							$(this).trigger('prev');
						} else {
							$(this).trigger('configuration', {
									items : {
										visible : {
											min : 3,
											max : 3
										},
										width : 400,
										height: 'variable'
									}
								},
								null,
								true
							);
							$(this).trigger('next');
							$(this ).delay(500).trigger('next');
						}
					});
				}, 1000, 'themifyuniqueidresize');
			});

			obj.each(function() {
				var $this = $(this),
					$sliderWrapper = $this.closest( '.loops-wrapper.slider' ),
					$builderRow = $this.closest( '.themify_builder_row' ),
					isFirstColor = 1,
					thisSpeed =  parseInt($this.data('speed'), 10 ),
					sliderWidth = $sliderWrapper.width(),
					leftCSS = {
						'width': Math.floor( sliderWidth / 1.5 ),
						'marginLeft':  0,
						'marginRight': -Math.floor( sliderWidth / 2.6 ),
						'opacity': 0.5
					},
					bigCSS = {
						'width': Math.floor( sliderWidth / 1.2 ),
						'marginLeft':  -Math.floor( sliderWidth / 5 ),
						'marginRight': -Math.floor( sliderWidth / 5 ),
						'opacity': 1
					},
					rightCSS = {
						'width': Math.floor( sliderWidth / 1.5 ),
						'marginLeft':  0,
						'marginRight': 0,
						'opacity': 0.5
					},
					aniOpts = {
						queue: false,
						duration: thisSpeed/1.5
					},
					aniOptsBig =  {
						queue: false,
						duration: thisSpeed
					}
					;

				$this.carouFredSel({
					responsive : true,
					prev : '#' + $this.data('id') + ' .carousel-prev',
					next : '#' + $this.data('id') + ' .carousel-next',
					pagination : {
						container : '#' + $this.data('id') + ' .carousel-pager'
					},
					circular : true,
					infinite : true,
					swipe: true,
					scroll : {
						items : 1,
						fx : 'scroll',
						duration : thisSpeed,
						onBefore : function( items ) {

                                                        var newItems = items.items.visible;
							if ( ! ThemifySlider.isSmallScreen() ) {
								sliderWidth = $sliderWrapper.width();
								leftCSS = {
									'width': Math.floor( sliderWidth / 1.5 ),
									'marginLeft':  0,
									'marginRight': -Math.floor( sliderWidth / 3 ),
									'opacity': 0.5
								};
								bigCSS = {
									'width': Math.floor( sliderWidth / 1.2 ),
									'marginLeft':  -Math.floor( sliderWidth / 4.5 ),
									'marginRight': -Math.floor( sliderWidth / 5 ),
									'left': -Math.floor( sliderWidth / 32 ),
									'opacity': 1
								};
								rightCSS = {
									'width': Math.floor( sliderWidth / 1.5 ),
									'marginLeft':  0,
									'marginRight': 0,
									'opacity': 0.5
								};

								newItems.eq(0)
									.addClass('gallerySlide')
									.removeClass('galleryBigSlide')
									.animate(leftCSS, aniOpts);

								newItems.eq(1)
									.addClass('galleryBigSlide')
									.removeClass('gallerySlide')
									.animate(bigCSS, aniOptsBig);

								newItems.eq(2)
									.addClass('gallerySlide')
									.removeClass('galleryBigSlide')
									.animate(rightCSS, aniOpts);
							}

						},
						onAfter : function( items ) {
							var newItems = items.items.visible;
							var $first = $('.post-image img', newItems.filter(':visible').eq(1));
							$sliderWrapper.css( 'background-color', $first.data('color') );
							$builderRow.css( 'background-color', $first.data('color') );
						}
					},
					auto : {
						play : !!('off' != $this.data('autoplay')),
						timeoutDuration : 'off' != $this.data('autoplay') ? parseInt($this.data('autoplay')) : 0
					},
					items : {
						visible : {
							min : ! ThemifySlider.isSmallScreen() ? 3 : 1,
							max : ! ThemifySlider.isSmallScreen() ? 3 : 1
						},
						width : 400,
						height: 'variable'
					},
					onCreate : function(items) {
						items = items.items;
						if ( ! ThemifySlider.isSmallScreen() ) {
							items.addClass('gallerySlide').removeClass('galleryBigSlide').css({
								'width': '',
								'marginLeft': '',
								'marginRight': ''
							});

							items.eq(0).animate(leftCSS, aniOpts);

							items.eq(1).addClass('galleryBigSlide').removeClass('gallerySlide').animate(bigCSS, aniOptsBig);

							items.eq(2).animate(rightCSS, aniOpts);
						}
						$( '.post-image img', $this ).each(function(){
							var $img = $(this),
								src = $img.attr('src' ),
								imageURL = '';
							if ( src.match(/(img\.php)/) ) {
								src = src.split('?src=');
								src = src[1].split('&');
								imageURL = src[0];
							} else {
								imageURL = src;
							}
							RGBaster.colors( imageURL, function(data){
								$img.attr('data-color', data.dominant);

								if ( 0 == isFirstColor && 'undefined' != typeof data.dominant ) {
									isFirstColor = false;
									$sliderWrapper.css( 'background-color', data.dominant );
									$builderRow.css( 'background-color', data.dominant );
								}
								isFirstColor--;
							});
						});

						$this.closest('.slideshow-wrap').css({
							'visibility' : 'visible',
							'height' : 'auto'
						});
						$this.closest('.loops-wrapper.slider').css({
							'visibility' : 'visible',
							'height' : 'auto'
						});
						$('.carousel-next, .carousel-prev', $this.closest('.slideshow-wrap')).empty().show();
						$('.carousel-pager', $this.closest('.slideshow-wrap')).remove();
						$(window).resize();
						$('.slideshow-slider-loader', $this.closest('.slider')).remove(); // remove slider loader
					}
				});
			});
		}
	};


	ThemifyVideo = {
		video: [],
		didResize: false,
		ratio: themifyScript.videoRatio,

		init: function( $object ) {
			$object.on('loadeddata', function(e){
				var $video = $(this),
					height = Math.floor( $video.width() / ThemifyVideo.ratio ),
					css = 'width: 100%; height: ' + height + 'px !important;';

				$video.css( 'cssText', css);
				$video.closest('.mejs-container').css( 'cssText', css );

				$('.mejs-layer', $video.closest('.mejs-inner') ).each(function(){
					var $el = $(this);
					$el.attr( 'style', $el.attr('style').replace(/height:(.*?);/, 'height:' + height + 'px !important;') );
				});

				ThemifyVideo.video['#'+$video.attr('id')] = $video;
			});

			this.setHeight();

		},

		onResize: function ( c, t ) {
			onresize = function () {
				clearTimeout( t );
				t = setTimeout( c, 100 );
			};
			return c;
		},

		setHeight: function() {

			this.onResize(function(){

				for( var object in ThemifyVideo.video ) {
					var $video = ThemifyVideo.video[object],
						height = Math.floor( $video.width() / ThemifyVideo.ratio ),
						css = 'width: 100%; height: ' + height + 'px !important;';

					$video.css( 'cssText', css);
					$video.closest('.mejs-container').css( 'cssText', css );

					$('.mejs-layer', $video.closest('.mejs-inner') ).each(function(){
						var $el = $(this);
						$el.attr( 'style', $el.attr('style').replace(/height:(.*?);/, 'height:' + height + 'px !important;') );
					});
				}

			});
		}
	};

	ThemifyParallax = {
		init: function(){
			this.sliderWrapper = ['.single #pagewrap .featured-area'];
			this.lastScrollPoint = 0;

			if(!themifyScript.parallaxHeader) return;
			this.onLoaded();
		},

		onLoaded: function(){
			var self = ThemifyParallax, resizeId;
			$('body').addClass('parallax-header');

			$.each(self.sliderWrapper, function(i,v){
				if($(v).length > 0)	{
					var $layoutWrap = $('#layout' ),
						$h_height = $('#headerwrap').height(),
						$ab = $('#wpadminbar');
					if ( $ab.length > 0 ) {
						$h_height += $ab.height();
					}
					$layoutWrap.css('marginTop', $(v).height() );
					$(v).css('top', $h_height);
					
					$(window).on('scroll', function(){
						self.transition(v);
					}).on('touchmove.touchScroll', function(){
						self.transition(v);
					}).on('throttledresize', function(){
						$layoutWrap.css('marginTop', $(v).height() );
						$h_height = $('#headerwrap').height();
						$ab = $('#wpadminbar');
						if ( $ab.length > 0 ) {
							$h_height += $ab.height();
						}
						$(v).css('top', $h_height);
					}).on('orientationchange', function(){
						self.transition(v);
					});
				}
			});

			setTimeout(function(){
				$(window).resize();
			}, 500);
		},

		transition: function(obj){
			var $obj = $(obj), $window = $(window), item_h = Math.ceil( $obj.find('.item').first().height() / 2 ),
				scrollTop = $window.scrollTop(),
				activePoint = Math.ceil($obj.height() - item_h ), n;
			
			if ( scrollTop > activePoint ) {
				n = Math.ceil( scrollTop + (this.lastScrollPoint - scrollTop) / 2);
			} else {
				n = Math.ceil(scrollTop);
				this.lastScrollPoint = scrollTop;
			}
			$obj.css({
				'-webkit-transform' : 'translateY(-'+n+'px)',
				'-moz-transform' : 'translateY(-'+n+'px)',
				'-o-transform' : 'translateY(-'+n+'px)',
				'-ms-transform' : 'translateY(-'+n+'px)',
				'transform' : 'translateY(-'+n+'px)'
			});
		}
	};

// DOCUMENT READY
$(document).ready(function() {

	var $body = $('body');

	if ( is_touch_device() ) {
		$body.addClass('touch');
	}

	// Carousel initialization //////////////////
        var carouselCallBack = function( $context ) {
            $context = $context || $body;
            ThemifySlider.create( $( '.loops-wrapper.shortcode.event .slideshow', $context ) );
            ThemifySlider.create( $( '.loops-wrapper.shortcode.video .slideshow', $context ) );
            ThemifySlider.createGallery( $( '.loops-wrapper.shortcode.gallery .slideshow', $context ) );
        }
        var carouselInit = function( $context ) {
            if(!$.fn.carouFredSel){
                Themify.LoadAsync(themify_vars.url+'/js/carousel.min.js',function(){
                    carouselCallBack($context);
                });
            }
            else{
                carouselCallBack($context);
            }
        };
        if($('.slideshow').length>0){
            carouselInit();
        }
	$body.on('builder_toggle_frontend', function(event, is_edit){
            carouselInit($(this));
        }).on('builder_load_module_partial', function(event, $newElems){
            carouselInit($newElems);
        });
	// Set shortest height
	$('.loops-wrapper.shortcode.video.slider').each(function(){
		ThemifyShortest.init( $(this), 'grids' );
	});

	/////////////////////////////////////////////
	// Scroll to top
	/////////////////////////////////////////////
	$('.back-top a').click(function() {
		$('body,html').animate({ scrollTop: 0 }, 800);
		return false;
	});

	/////////////////////////////////////////////
	// Toggle main nav on mobile
	/////////////////////////////////////////////
	if ( $(window).width() < 780 ) {
		$('#main-nav').addClass('scroll-nav');
	}

	$('#menu-icon').themifySideMenu({
		close: '#menu-icon-close'
	});
        
        var $overlay = $( '<div class="body-overlay">' );
        $body.append( $overlay ).on( 'sidemenushow.themify', function () {
            $overlay.addClass( 'body-overlay-on' );
        } ).on( 'sidemenuhide.themify', function () {
            $overlay.removeClass( 'body-overlay-on' );
        } ).on( 'click.themify touchend.themify', '.body-overlay', function () {
            $( '#menu-icon' ).themifySideMenu( 'hide' );
        } );
	// Reset slide nav width
	$(window).resize(function(){
	    if ( $(window).width() > 780) {
		    $body.removeAttr('style');
		    $('#main-nav').removeClass('scroll-nav');
              
	    } else {
		    $('#main-nav').addClass('scroll-nav');
	    }
            if( $( '#menu-icon' ).is(':visible') && $('#mobile-menu').hasClass('sidemenu-on')){
                $overlay.addClass( 'body-overlay-on' );
            }
            else{
                 $overlay.removeClass( 'body-overlay-on' );
            }
	});

	if( $( 'body' ).hasClass( 'touch' ) && typeof jQuery.fn.themifyDropdown != 'function' ) {
		Themify.LoadAsync(themify_vars.url + '/js/themify.dropdown.js', function(){
			$( '#main-nav' ).themifyDropdown();
		});
	}

	// Initialize Tabs for Widget ///////////////
	ThemifyTabs.init( '.event-posts', '-events' );

	// Initialize video aspect ratio
	ThemifyVideo.init( $( 'video.wp-video-shortcode' ) );

});

// WINDOW LOAD
$(window).load(function() {
	
	/////////////////////////////////////////////
	// Single Gallery Post Type
	/////////////////////////////////////////////
	if ( $('body.single-gallery').length > 0 && 'undefined' !== typeof $.fn.masonry ) {
		$('.masonry').masonry({
			'columnWidth': '.grid-size',
			'itemSelector': '.item'
		});
	}

	// Initialize internal Like button ////////////////
	$(document).on('click', '.likeit', function(e) {
		e.preventDefault();
		var $self = $(this),
			post_id = $self.data('postid');
		$.post(
			themifyScript.ajax_url,
			{
				action: 'themify_likeit',
				nonce : themifyScript.ajax_nonce,
				post_id: post_id
			},
			function(response) {
				data = $.parseJSON(response);
				if( 'new' == data.status ) {
					$('.count', $self).fadeOut('slow', function(){
						$(this).text(data.likers).fadeIn('slow');
					});
					$(this).addClass('likeit_done');
				}
			}
		);
	});

	// Set content and sidebar equal height ///////////
	ThemifyEqualHeight.init(themifyScript.smallScreen, themifyScript.resizeRefresh);

	// Parallax Header ///////////
	if ( 'undefined' !== typeof ThemifyParallax ) {
		ThemifyParallax.init();
	}
	
	// Fixed header /////////////////////////////
	FixedHeader.init();
	
	// EDGE MENU //
	jQuery(function ($) {
		$("#main-nav li").on('mouseenter mouseleave dropdown_open', function (e) {
			if ($('ul', this).length) {
				var elm = $('ul:first', this);
				var off = elm.offset();
				var l = off.left;
				var w = elm.width();
				var docW = $(window).width();
				var isEntirelyVisible = (l + w <= docW);

				if (!isEntirelyVisible) {
					$(this).addClass('edge');
				} else {
					$(this).removeClass('edge');
				}

			}
		});
	});
	
});
	
})(jQuery);