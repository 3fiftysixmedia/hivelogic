<?php
/**
 * Page Meta Box Options
 * @param array $args
 * @return array
 * @since 1.0.0
 */
function themify_theme_page_meta_box( $args = array() ){
	extract( $args );
	return array(
		// Page Layout
		array(
			'name' 		=> 'page_layout',
			'title'		=> __('Sidebar Option', 'themify'),
			'description'	=> '',
			'type'		=> 'layout',
			'show_title' => true,
			'meta'		=> array(
				array('value' => 'default', 'img' => 'images/layout-icons/default.png', 'selected' => true, 'title' => __('Default', 'themify')),
				array('value' => 'sidebar1', 'img' => 'images/layout-icons/sidebar1.png', 'title' => __('Sidebar Right', 'themify')),
				array('value' => 'sidebar1 sidebar-left', 'img' => 'images/layout-icons/sidebar1-left.png', 'title' => __('Sidebar Left', 'themify')),
				array('value' => 'sidebar-none', 'img' => 'images/layout-icons/sidebar-none.png', 'title' => __('No Sidebar ', 'themify'))
			)
		),
		// Content Width
		array(
			'name'=> 'content_width',
			'title' => __('Content Width', 'themify'),
			'description' => '',
			'type' => 'layout',
			'show_title' => true,
			'meta' => array(
				array(
					'value' => 'default_width',
					'img' => 'themify/img/default.png',
					'selected' => true,
					'title' => __( 'Default', 'themify' )
				),
				array(
					'value' => 'full_width',
					'img' => 'themify/img/fullwidth.png',
					'title' => __( 'Fullwidth', 'themify' )
				)
			)
		),
		// Hide page title
		array(
			'name' 		=> 'hide_page_title',
			'title'		=> __('Hide Page Title', 'themify'),
			'description'	=> '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Custom menu for page
		array(
			'name'        => 'custom_menu',
			'title'       => __( 'Custom Menu', 'themify' ),
			'description' => '',
			'type'        => 'dropdown',
			// extracted from $args
			'meta'        => $nav_menus,
		),
	);
}

/**
 * Query Posts Options
 * @param array $args
 * @return array
 * @since 1.0.0
 */
function themify_theme_query_post_meta_box($args = array()) {
	extract( $args );
	return array(
		// Notice
		array(
			'name' => '_query_posts_notice',
			'title' => '',
			'description' => '',
			'type' => 'separator',
			'meta' => array(
				'html' => '<div class="themify-info-link">' . sprintf( __( '<a href="%s">Query Posts</a> allows you to query WordPress posts from any category on the page. To use it, select a Query Category.', 'themify' ), 'http://themify.me/docs/query-posts' ) . '</div>'
			),
		),
		// Query Category
		array(
			'name' 		=> 'query_category',
			'title'		=> __('Query Category', 'themify'),
			'description'	=> __('Select a category or enter multiple category IDs (eg. 2,5,6). Enter 0 to display all category.', 'themify'),
			'type'		=> 'query_category',
			'meta'		=> array()
		),
		// Query All Post Types
		array(
			'name' => 'query_all_post_types',
			'type' => 'dropdown',
			'title' => __( 'Query All Post Types', 'themify'),
			'meta' =>array(
				array(
				'value' => '',
				'name' => '',
				),
				array(
				'value' => 'yes',
				'name' => 'Yes',
				),
				array(
				'value' => 'no',
				'name' => 'No',
				),
			)
		),
		// Descending or Ascending Order for Posts
		array(
			'name' 		=> 'order',
			'title'		=> __('Order', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Descending', 'themify'), 'value' => 'desc', 'selected' => true),
				array('name' => __('Ascending', 'themify'), 'value' => 'asc')
			)
		),
		// Criteria to Order By
		array(
			'name' 		=> 'orderby',
			'title'		=> __('Order By', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Date', 'themify'), 'value' => 'date', 'selected' => true),
				array('name' => __('Random', 'themify'), 'value' => 'rand'),
				array('name' => __('Author', 'themify'), 'value' => 'author'),
				array('name' => __('Post Title', 'themify'), 'value' => 'title'),
				array('name' => __('Comments Number', 'themify'), 'value' => 'comment_count'),
				array('name' => __('Modified Date', 'themify'), 'value' => 'modified'),
				array('name' => __('Post Slug', 'themify'), 'value' => 'name'),
				array('name' => __('Post ID', 'themify'), 'value' => 'ID')
			)
		),
		// Post Layout
		array(
			'name' 		=> 'layout',
			'title'		=> __('Query Post Layout', 'themify'),
			'description'	=> '',
			'type'		=> 'layout',
			'show_title' => true,
			'meta'		=> array(
				array('value' => 'list-post', 'img' => 'images/layout-icons/list-post.png', 'selected' => true, 'title' => __('List Post', 'themify')),
				array('value' => 'grid4', 'img' => 'images/layout-icons/grid4.png', 'title' => __('Grid 4', 'themify')),
				array('value' => 'grid3', 'img' => 'images/layout-icons/grid3.png', 'title' => __('Grid 3', 'themify')),
				array('value' => 'grid2', 'img' => 'images/layout-icons/grid2.png', 'title' => __('Grid 2', 'themify')),
				array('value' => 'list-large-image', 'img' => 'images/layout-icons/list-large-image.png', 'title' => __('List Large Image', 'themify')),
				array('value' => 'list-thumb-image', 'img' => 'images/layout-icons/list-thumb-image.png', 'title' => __('List Thumb Image', 'themify')),
				array('value' => 'grid2-thumb', 'img' => 'images/layout-icons/grid2-thumb.png', 'title' => __('Grid 2 Thumb', 'themify'))
			)
		),
		// Posts Per Page
		array(
			'name' 		=> 'posts_per_page',
			'title'		=> __('Posts Per Page', 'themify'),
			'description'	=> '',
			'type'		=> 'textbox',
			'meta'		=> array('size' => 'small')
		),
		// Display Content
		array(
			'name' 		=> 'display_content',
			'title'		=> __('Display Content', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array( 'name' => __('Full Content', 'themify'), 'value' => 'content' ),
				array( 'name' => __('Excerpt', 'themify'), 'value' => 'excerpt', 'selected' => true ),
				array( 'name' => __('None', 'themify'), 'value' => 'none' )
			),
			'default' => 'excerpt',
		),
		// Featured Image Size
		array(
			'name'	=>	'feature_size_page',
			'title'	=>	__('Image Size', 'themify'),
			'description' => sprintf(__('Image sizes can be set at <a href="%s">Media Settings</a> and <a href="%s" target="_blank">Regenerated</a>', 'themify'), 'options-media.php', 'https://wordpress.org/plugins/regenerate-thumbnails/'),
			'type'		 =>	'featimgdropdown',
			'display_callback' => 'themify_is_image_script_disabled'
		),
		// Multi field: Image Dimension
		themify_image_dimensions_field(),
		// Hide Title
		array(
			'name' 		=> 'hide_title',
			'title'		=> __('Hide Post Title', 'themify'),
			'description'	=> '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Unlink Post Title
		array(
			'name' 		=> 'unlink_title',
			'title' 		=> __('Unlink Post Title', 'themify'),
			'description' => __('Unlink post title (it will display the post title without link)', 'themify'),
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Hide Post Date
		array(
			'name' 		=> 'hide_date',
			'title'		=> __('Hide Post Date', 'themify'),
			'description'	=> '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Hide Post Meta
		themify_multi_meta_field(),
		// Hide Post Image
		array(
			'name' 		=> 'hide_image',
			'title' 		=> __('Hide Featured Image', 'themify'),
			'description' => '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Unlink Post Image
		array(
			'name' 		=> 'unlink_image',
			'title' 		=> __('Unlink Featured Image', 'themify'),
			'description' => __('Display the Featured Image without link', 'themify'),
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Page Navigation Visibility
		array(
			'name' 		=> 'hide_navigation',
			'title'		=> __('Hide Page Navigation', 'themify'),
			'description'	=> '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
	);
}

/**
 * Query Sections Meta Box Options
 * @param array $args
 * @return array
 * @since 1.0.0
 */
function themify_theme_query_section_meta_box($args = array()) {
	extract( $args );
	return array(
		// Query Category
		array(
			'name' 		=> 'section_query_category',
			'title'		=> __('Section Category', 'themify'),
			'description'	=> __('Select a section category or enter multiple section category IDs (eg. 2,5,6). Enter 0 to display all section categories.', 'themify'),
			'type'		=> 'query_category',
			'meta'		=> array('taxonomy' => 'section-category')
		),
		// Descending or Ascending Order for Sections
		array(
			'name' 		=> 'section_order',
			'title'		=> __('Order', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Descending', 'themify'), 'value' => 'desc', 'selected' => true),
				array('name' => __('Ascending', 'themify'), 'value' => 'asc')
			)
		),
		// Criteria to Order By
		array(
			'name' 		=> 'section_orderby',
			'title'		=> __('Order By', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Date', 'themify'), 'value' => 'date', 'selected' => true),
				array('name' => __('Random', 'themify'), 'value' => 'rand'),
				array('name' => __('Author', 'themify'), 'value' => 'author'),
				array('name' => __('Post Title', 'themify'), 'value' => 'title'),
				array('name' => __('Comments Number', 'themify'), 'value' => 'comment_count'),
				array('name' => __('Modified Date', 'themify'), 'value' => 'modified'),
				array('name' => __('Post Slug', 'themify'), 'value' => 'name'),
				array('name' => __('Post ID', 'themify'), 'value' => 'ID')
			)
		),
		// Posts Per Page
		array(
			  'name' 		=> 'section_posts_per_page',
			  'title'		=> __('Sections Per Page', 'themify'),
			  'description'	=> '',
			  'type'		=> 'textbox',
			  'meta'		=> array('size' => 'small')
			),
		// Featured Image Size
		array(
			'name'	=>	'section_feature_size_page',
			'title'	=>	__('Image Size', 'themify'),
			'description' => __('Image sizes can be set at <a href="options-media.php">Media Settings</a> and <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerated</a>', 'themify'),
			'type'		 =>	'featimgdropdown',
			'display_callback' => 'themify_is_image_script_disabled'
		),
		// Hide Title
		array(
			  'name' 		=> 'section_hide_title',
			  'title'		=> __('Hide Section Title', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
					array('value' => 'default', 'name' => '', 'selected' => true),
					array('value' => 'yes', 'name' => __('Yes', 'themify')),
					array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
	);
}

/**
 * Event Meta Box Options
 * @param array $args
 * @return array
 * @since 1.0.7
 */
function themify_theme_query_event_meta_box($args = array()){
	extract( $args );
	return array(
		// Query Category
		array(
			'name' 		=> 'event_query_category',
			'title'		=> __('Event Category', 'themify'),
			'description'	=> __('Select a event category or enter multiple event category IDs (eg. 2,5,6). Enter 0 to display all event categories.', 'themify'),
			'type'		=> 'query_category',
			'meta'		=> array('taxonomy' => 'event-category')
		),
		// Events to show
		array(
			'name' 		=> 'event_display',
			'title'		=> __('Display', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Upcoming Events', 'themify'), 'value' => 'upcoming', 'selected' => true),
				array('name' => __('Past Events', 'themify'), 'value' => 'past'),
				array('name' => __('Both', 'themify'), 'value' => 'both'),
			)
		),
		// Descending or Ascending Order for Events
		array(
			'name' 		=> 'event_order',
			'title'		=> __('Order', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Descending', 'themify'), 'value' => 'desc', 'selected' => true),
				array('name' => __('Ascending', 'themify'), 'value' => 'asc')
			)
		),
		// Criteria to Order By
		array(
			'name' 		=> 'event_orderby',
			'title'		=> __('Order By', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Event Date', 'themify'), 'value' => 'meta_value', 'selected' => true),
				array('name' => __('Date', 'themify'), 'value' => 'date'),
				array('name' => __('Random', 'themify'), 'value' => 'rand'),
				array('name' => __('Author', 'themify'), 'value' => 'author'),
				array('name' => __('Post Title', 'themify'), 'value' => 'title'),
				array('name' => __('Comments Number', 'themify'), 'value' => 'comment_count'),
				array('name' => __('Modified Date', 'themify'), 'value' => 'modified'),
				array('name' => __('Post Slug', 'themify'), 'value' => 'name'),
				array('name' => __('Post ID', 'themify'), 'value' => 'ID')
			)
		),
		// Post Layout
		array(
			  'name' 		=> 'event_layout',
			  'title'		=> __('Event Layout', 'themify'),
			  'description'	=> '',
			  'type'		=> 'layout',
			  'show_title' => true,
			  'meta'		=> array(
					array('value' => 'list-post', 'img' => 'images/layout-icons/list-post.png', 'selected' => true),
					array('value' => 'grid4', 'img' => 'images/layout-icons/grid4.png', 'title' => __('Grid 4', 'themify')),
					array('value' => 'grid3', 'img' => 'images/layout-icons/grid3.png', 'title' => __('Grid 3', 'themify')),
					array('value' => 'grid2', 'img' => 'images/layout-icons/grid2.png', 'title' => __('Grid 2', 'themify'))
				)
			),
		// Posts Per Page
		array(
			  'name' 		=> 'event_posts_per_page',
			  'title'		=> __('Events Per Page', 'themify'),
			  'description'	=> '',
			  'type'		=> 'textbox',
			  'meta'		=> array('size' => 'small')
			),

		// Display Content
		array(
			  'name' 		=> 'event_display_content',
			  'title'		=> __('Display Content', 'themify'),
			  'description'	=> '',
			  'type'		=> 'dropdown',
			'meta'		=> array(
				array( 'name' => __('Full Content', 'themify'), 'value' => 'content' ),
				array( 'name' => __('Excerpt', 'themify'), 'value' => 'excerpt', 'selected' => true ),
				array( 'name' => __('None', 'themify'), 'value' => 'none' )
			),
			'default' => 'excerpt',
		),
		// Featured Image Size
		array(
			'name'	=>	'event_feature_size_page',
			'title'	=>	__('Image Size', 'themify'),
			'description' => __('Image sizes can be set at <a href="options-media.php">Media Settings</a> and <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerated</a>', 'themify'),
			'type'		 =>	'featimgdropdown',
			'display_callback' => 'themify_is_image_script_disabled'
			),

		// Multi field: Image Dimension
		array(
			'type' => 'multi',
			'name' => '_event_image_dimensions',
			'title' => __('Image Dimensions', 'themify'),
			'meta' => array(
				'fields' => array(
					// Image Width
					array(
					  'name' 		=> 'event_image_width',
					  'label' => __('width', 'themify'),
					  'description' => '',
					  'type' 		=> 'textbox',
					  'meta'		=> array('size'=>'small')
					),
					// Image Height
					array(
					  'name' 		=> 'event_image_height',
					  'label' => __('height', 'themify'),
					  'description' => '',
					  'type' 		=> 'textbox',
					  'meta'		=> array('size'=>'small')
					),
				),
				'description' => __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify'),
				'before' => '',
				'after' => '',
				'separator' => ''
			)
		),
		// Hide Title
		array(
			  'name' 		=> 'event_hide_title',
			  'title'		=> __('Hide Event Title', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Unlink Post Title
		array(
			  'name' 		=> 'event_unlink_title',
			  'title' 		=> __('Unlink Event Title', 'themify'),
			  'description' => __('Unlink event title (it will display the post title without link)', 'themify'),
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Hide Post Date
		array(
			  'name' 		=> 'event_hide_date',
			  'title'		=> __('Hide Event Publish Date', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Hide Post Meta
		array(
			'name' 		=> 'event_hide_meta_all',
			'title' 	=> __('Hide Event Meta', 'themify'),
			'description' => '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Hide Post Image
		array(
			  'name' 		=> 'event_hide_image',
			  'title' 		=> __('Hide Featured Image', 'themify'),
			  'description' => '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Unlink Post Image
		array(
			  'name' 		=> 'event_unlink_image',
			  'title' 		=> __('Unlink Featured Image', 'themify'),
			  'description' => __('Display the Featured Image Without Link', 'themify'),
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Page Navigation Visibility
		array(
			  'name' 		=> 'event_hide_navigation',
			  'title'		=> __('Hide Page Navigation', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Event Location
		array(
			'name' 		=> 'event_hide_event_location',
			'title'		=> __('Hide Event Location', 'themify'),
			'description'	=> '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Event Date
		array(
			'name' 		=> 'event_hide_event_date',
			'title'		=> __('Hide Event Date', 'themify'),
			'description'	=> '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
	);
}

/**
 * Video Meta Box Options
 * @param array $args
 * @return array
 * @since 1.0.7
 */
function themify_theme_query_video_meta_box($args = array()){
	extract( $args );
	return array(
		// Query Category
		array(
			'name' 		=> 'video_query_category',
			'title'		=> __('Video Category', 'themify'),
			'description'	=> __('Select a video category or enter multiple video category IDs (eg. 2,5,6). Enter 0 to display all video categories.', 'themify'),
			'type'		=> 'query_category',
			'meta'		=> array('taxonomy' => 'video-category')
		),
		// Descending or Ascending Order for Videos
		array(
			'name' 		=> 'video_order',
			'title'		=> __('Order', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Descending', 'themify'), 'value' => 'desc', 'selected' => true),
				array('name' => __('Ascending', 'themify'), 'value' => 'asc')
			)
		),
		// Criteria to Order By
		array(
			'name' 		=> 'video_orderby',
			'title'		=> __('Order By', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Date', 'themify'), 'value' => 'date', 'selected' => true),
				array('name' => __('Random', 'themify'), 'value' => 'rand'),
				array('name' => __('Author', 'themify'), 'value' => 'author'),
				array('name' => __('Post Title', 'themify'), 'value' => 'title'),
				array('name' => __('Comments Number', 'themify'), 'value' => 'comment_count'),
				array('name' => __('Modified Date', 'themify'), 'value' => 'modified'),
				array('name' => __('Post Slug', 'themify'), 'value' => 'name'),
				array('name' => __('Post ID', 'themify'), 'value' => 'ID')
			)
		),
		// Post Layout
		array(
			  'name' 		=> 'video_layout',
			  'title'		=> __('Video Layout', 'themify'),
			  'description'	=> '',
			  'type'		=> 'layout',
			  'show_title' => true,
			  'meta'		=> array(
					array('value' => 'list-post', 'img' => 'images/layout-icons/list-post.png', 'selected' => true),
					array('value' => 'grid4', 'img' => 'images/layout-icons/grid4.png', 'title' => __('Grid 4', 'themify')),
					array('value' => 'grid3', 'img' => 'images/layout-icons/grid3.png', 'title' => __('Grid 3', 'themify')),
					array('value' => 'grid2', 'img' => 'images/layout-icons/grid2.png', 'title' => __('Grid 2', 'themify'))
				)
			),
		// Posts Per Page
		array(
			  'name' 		=> 'video_posts_per_page',
			  'title'		=> __('Videos Per Page', 'themify'),
			  'description'	=> '',
			  'type'		=> 'textbox',
			  'meta'		=> array('size' => 'small')
			),

		// Display Content
		array(
			  'name' 		=> 'video_display_content',
			  'title'		=> __('Display Content', 'themify'),
			  'description'	=> '',
			  'type'		=> 'dropdown',
			'meta'		=> array(
				array( 'name' => __('Full Content', 'themify'), 'value' => 'content' ),
				array( 'name' => __('Excerpt', 'themify'), 'value' => 'excerpt', 'selected' => true ),
				array( 'name' => __('None', 'themify'), 'value' => 'none' )
			),
			'default' => 'excerpt',
		),
		// Featured Image Size
		array(
			'name'	=>	'video_feature_size_page',
			'title'	=>	__('Image Size', 'themify'),
			'description' => __('Image sizes can be set at <a href="options-media.php">Media Settings</a> and <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerated</a>', 'themify'),
			'type'		 =>	'featimgdropdown',
			'display_callback' => 'themify_is_image_script_disabled'
			),

		// Multi field: Image Dimension
		array(
			'type' => 'multi',
			'name' => '_video_image_dimensions',
			'title' => __('Image Dimensions', 'themify'),
			'meta' => array(
				'fields' => array(
					// Image Width
					array(
					  'name' 		=> 'video_image_width',
					  'label' => __('width', 'themify'),
					  'description' => '',
					  'type' 		=> 'textbox',
					  'meta'		=> array('size'=>'small')
					),
					// Image Height
					array(
					  'name' 		=> 'video_image_height',
					  'label' => __('height', 'themify'),
					  'description' => '',
					  'type' 		=> 'textbox',
					  'meta'		=> array('size'=>'small')
					),
				),
				'description' => __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify'),
				'before' => '',
				'after' => '',
				'separator' => ''
			)
		),
		// Hide Title
		array(
			  'name' 		=> 'video_hide_title',
			  'title'		=> __('Hide Video Title', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Unlink Post Title
		array(
			  'name' 		=> 'video_unlink_title',
			  'title' 		=> __('Unlink Video Title', 'themify'),
			  'description' => __('Unlink video title (it will display the post title without link)', 'themify'),
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Hide Post Date
		array(
			  'name' 		=> 'video_hide_date',
			  'title'		=> __('Hide Video Date', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Hide Post Meta
		array(
			'name' 		=> 'video_hide_meta_all',
			'title' 	=> __('Hide Video Meta', 'themify'),
			'description' => '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Hide Post Image
		array(
			  'name' 		=> 'video_hide_image',
			  'title' 		=> __('Hide Featured Image', 'themify'),
			  'description' => '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Unlink Post Image
		array(
			  'name' 		=> 'video_unlink_image',
			  'title' 		=> __('Unlink Featured Image', 'themify'),
			  'description' => __('Display the Featured Image Without Link', 'themify'),
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Page Navigation Visibility
		array(
			  'name' 		=> 'video_hide_navigation',
			  'title'		=> __('Hide Page Navigation', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
	);
}

/**
 * Gallery Meta Box Options
 * @param array $args
 * @return array
 * @since 1.0.7
 */
function themify_theme_query_gallery_meta_box($args = array()){
	extract( $args );
	return array(
		// Query Category
		array(
			'name' 		=> 'gallery_query_category',
			'title'		=> __('Gallery Category', 'themify'),
			'description'	=> __('Select a gallery category or enter multiple gallery category IDs (eg. 2,5,6). Enter 0 to display all gallery categories.', 'themify'),
			'type'		=> 'query_category',
			'meta'		=> array('taxonomy' => 'gallery-category')
		),
		// Descending or Ascending Order for Galleries
		array(
			'name' 		=> 'gallery_order',
			'title'		=> __('Order', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Descending', 'themify'), 'value' => 'desc', 'selected' => true),
				array('name' => __('Ascending', 'themify'), 'value' => 'asc')
			)
		),
		// Criteria to Order By
		array(
			'name' 		=> 'gallery_orderby',
			'title'		=> __('Order By', 'themify'),
			'description'	=> '',
			'type'		=> 'dropdown',
			'meta'		=> array(
				array('name' => __('Date', 'themify'), 'value' => 'date', 'selected' => true),
				array('name' => __('Random', 'themify'), 'value' => 'rand'),
				array('name' => __('Author', 'themify'), 'value' => 'author'),
				array('name' => __('Post Title', 'themify'), 'value' => 'title'),
				array('name' => __('Comments Number', 'themify'), 'value' => 'comment_count'),
				array('name' => __('Modified Date', 'themify'), 'value' => 'modified'),
				array('name' => __('Post Slug', 'themify'), 'value' => 'name'),
				array('name' => __('Post ID', 'themify'), 'value' => 'ID')
			)
		),
		// Post Layout
		array(
			  'name' 		=> 'gallery_layout',
			  'title'		=> __('Gallery Layout', 'themify'),
			  'description'	=> '',
			  'type'		=> 'layout',
			  'show_title' => true,
			  'meta'		=> array(
					array('value' => 'list-post', 'img' => 'images/layout-icons/list-post.png', 'selected' => true),
					array('value' => 'grid4', 'img' => 'images/layout-icons/grid4.png', 'title' => __('Grid 4', 'themify')),
					array('value' => 'grid3', 'img' => 'images/layout-icons/grid3.png', 'title' => __('Grid 3', 'themify')),
					array('value' => 'grid2', 'img' => 'images/layout-icons/grid2.png', 'title' => __('Grid 2', 'themify'))
				)
			),
		// Posts Per Page
		array(
			  'name' 		=> 'gallery_posts_per_page',
			  'title'		=> __('Galleries Per Page', 'themify'),
			  'description'	=> '',
			  'type'		=> 'textbox',
			  'meta'		=> array('size' => 'small')
			),

		// Display Content
		array(
			  'name' 		=> 'gallery_display_content',
			  'title'		=> __('Display Content', 'themify'),
			  'description'	=> '',
			  'type'		=> 'dropdown',
			'meta'		=> array(
				array( 'name' => __('Full Content', 'themify'), 'value' => 'content' ),
				array( 'name' => __('Excerpt', 'themify'), 'value' => 'excerpt', 'selected' => true ),
				array( 'name' => __('None', 'themify'), 'value' => 'none' )
			),
			'default' => 'excerpt',
		),
		// Featured Image Size
		array(
			'name'	=>	'gallery_feature_size_page',
			'title'	=>	__('Image Size', 'themify'),
			'description' => __('Image sizes can be set at <a href="options-media.php">Media Settings</a> and <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerated</a>', 'themify'),
			'type'		 =>	'featimgdropdown',
			'display_callback' => 'themify_is_image_script_disabled'
			),

		// Multi field: Image Dimension
		array(
			'type' => 'multi',
			'name' => '_gallery_image_dimensions',
			'title' => __('Image Dimensions', 'themify'),
			'meta' => array(
				'fields' => array(
					// Image Width
					array(
					  'name' 		=> 'gallery_image_width',
					  'label' => __('width', 'themify'),
					  'description' => '',
					  'type' 		=> 'textbox',
					  'meta'		=> array('size'=>'small')
					),
					// Image Height
					array(
					  'name' 		=> 'gallery_image_height',
					  'label' => __('height', 'themify'),
					  'description' => '',
					  'type' 		=> 'textbox',
					  'meta'		=> array('size'=>'small')
					),
				),
				'description' => __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify'),
				'before' => '',
				'after' => '',
				'separator' => ''
			)
		),
		// Hide Title
		array(
			  'name' 		=> 'gallery_hide_title',
			  'title'		=> __('Hide Gallery Title', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Unlink Post Title
		array(
			  'name' 		=> 'gallery_unlink_title',
			  'title' 		=> __('Unlink Gallery Title', 'themify'),
			  'description' => __('Unlink gallery title (it will display the post title without link)', 'themify'),
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Hide Post Date
		array(
			  'name' 		=> 'gallery_hide_date',
			  'title'		=> __('Hide Gallery Date', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
									array('value' => 'default', 'name' => '', 'selected' => true),
									array('value' => 'yes', 'name' => __('Yes', 'themify')),
									array('value' => 'no',	'name' => __('No', 'themify'))
								)
			),
		// Hide Post Meta
		array(
			'name' 		=> 'gallery_hide_meta_all',
			'title' 	=> __('Hide Gallery Meta', 'themify'),
			'description' => '',
			'type' 		=> 'dropdown',
			'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Hide Post Image
		array(
			  'name' 		=> 'gallery_hide_image',
			  'title' 		=> __('Hide Featured Image', 'themify'),
			  'description' => '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Unlink Post Image
		array(
			  'name' 		=> 'gallery_unlink_image',
			  'title' 		=> __('Unlink Featured Image', 'themify'),
			  'description' => __('Display the Featured Image Without Link', 'themify'),
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
		// Page Navigation Visibility
		array(
			  'name' 		=> 'gallery_hide_navigation',
			  'title'		=> __('Hide Page Navigation', 'themify'),
			  'description'	=> '',
			  'type' 		=> 'dropdown',
			  'meta'		=> array(
				array('value' => 'default', 'name' => '', 'selected' => true),
				array('value' => 'yes', 'name' => __('Yes', 'themify')),
				array('value' => 'no',	'name' => __('No', 'themify'))
			)
		),
	);
}

/**
 * Default Page Layout Module
 * @param array $data Theme settings data
 * @return string Markup for module.
 * @since 1.0.0
 */
function themify_default_page_layout($data = array()){
	$data = themify_get_data();

	/**
	 * Theme Settings Option Key Prefix
	 * @var string
	 */
	$prefix = 'setting-default_page_';

	/**
	 * Sidebar placement options
	 * @var array
	 */
	$sidebar_location_options = array(
		array('value' => 'sidebar1', 'img' => 'images/layout-icons/sidebar1.png', 'selected' => true, 'title' => __('Sidebar Right', 'themify')),
		array('value' => 'sidebar1 sidebar-left', 'img' => 'images/layout-icons/sidebar1-left.png', 'title' => __('Sidebar Left', 'themify')),
		array('value' => 'sidebar-none', 'img' => 'images/layout-icons/sidebar-none.png', 'title' => __('No Sidebar', 'themify'))
	);

	/**
	 * Tertiary options <blank>|yes|no
	 * @var array
	 */
	$default_options = array(
		array('name' => '', 'value' => ''),
		array('name' => __('Yes', 'themify'), 'value' => 'yes'),
		array('name' => __('No', 'themify'), 'value' => 'no')
	);

	/**
	 * Module markup
	 * @var string
	 */
	$output = '';

	/**
	 * Page sidebar placement
	 */
	$output .= '<p>
					<span class="label">' . __('Page Sidebar Option', 'themify') . '</span>';
	$val = isset( $data[$prefix.'layout'] ) ? $data[$prefix.'layout'] : '';
	foreach ( $sidebar_location_options as $option ) {
		if ( ( '' == $val || ! $val || ! isset( $val ) ) && ( isset( $option['selected'] ) && $option['selected'] ) ) {
			$val = $option['value'];
		}
		if ( $val == $option['value'] ) {
			$class = "selected";
		} else {
			$class = "";
		}
		$output .= '<a href="#" class="preview-icon '.$class.'" title="'.$option['title'].'"><img src="'.THEME_URI.'/'.$option['img'].'" alt="'.$option['value'].'"  /></a>';
	}
	$output .= '<input type="hidden" name="'.$prefix.'layout" class="val" value="'.$val.'" /></p>';

	/**
	 * Hide Title in All Pages
	 */
	$output .= '<p>
					<span class="label">' . __('Hide Title in All Pages', 'themify') . '</span>
					<select name="setting-hide_page_title">'.
						themify_options_module($default_options, 'setting-hide_page_title') . '
					</select>
				</p>';

	/**
	 * Hide Feauted images in All Pages
	 */
	$output .= '<p>
				<span class="label">' . __('Hide Featured Image', 'themify') . '</span>
				<select name="setting-hide_page_image">' .
					themify_options_module($default_options, 'setting-hide_page_image') . '
				</select>
			</p>';
	/**
	 * Featured Image dimensions
	 */
	$output .= '<p>
				<span class="label">' . __('Image Size', 'themify') . '</span>
				<input type="text" class="width2" name="setting-page_featured_image_width" value="' . themify_get( 'setting-page_featured_image_width' ) . '" /> ' . __('width', 'themify') . ' <small>(px)</small>
				<input type="text" class="width2 show_if_enabled_img_php" name="setting-page_featured_image_height" value="' . themify_get( 'setting-page_featured_image_height' ) . '" /> <span class="show_if_enabled_img_php">' . __('height', 'themify') . ' <small>(px)</small></span>
				<br /><span class="pushlabel show_if_enabled_img_php"><small>' . __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify') . '</small></span>
			</p>';

	/**
	 * Page Comments
	 */
	$pre = 'setting-comments_pages';
	$output .= '<p><span class="label">' . __('Page Comments', 'themify') . '</span><label for="'.$pre.'"><input type="checkbox" id="'.$pre.'" name="'.$pre.'" ' . checked( themify_get( $pre ), 'on', false ) . ' /> ' . __('Disable comments in all Pages', 'themify') . '</label></p>';

	return $output;
}