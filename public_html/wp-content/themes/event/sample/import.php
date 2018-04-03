<?php

defined( 'ABSPATH' ) or die;

$GLOBALS['processed_terms'] = array();
$GLOBALS['processed_posts'] = array();

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function themify_import_post( $post ) {
	global $processed_posts, $processed_terms;

	if ( ! post_type_exists( $post['post_type'] ) ) {
		return;
	}

	/* Menu items don't have reliable post_title, skip the post_exists check */
	if( $post['post_type'] !== 'nav_menu_item' ) {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
		if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
			$processed_posts[ intval( $post['ID'] ) ] = intval( $post_exists );
			return;
		}
	}

	if( $post['post_type'] == 'nav_menu_item' ) {
		if( ! isset( $post['tax_input']['nav_menu'] ) || ! term_exists( $post['tax_input']['nav_menu'], 'nav_menu' ) ) {
			return;
		}
		$_menu_item_type = $post['meta_input']['_menu_item_type'];
		$_menu_item_object_id = $post['meta_input']['_menu_item_object_id'];

		if ( 'taxonomy' == $_menu_item_type && isset( $processed_terms[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_terms[ intval( $_menu_item_object_id ) ];
		} else if ( 'post_type' == $_menu_item_type && isset( $processed_posts[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_posts[ intval( $_menu_item_object_id ) ];
		} else if ( 'custom' != $_menu_item_type ) {
			// associated object is missing or not imported yet, we'll retry later
			// $missing_menu_items[] = $item;
			return;
		}
	}

	$post_parent = ( $post['post_type'] == 'nav_menu_item' ) ? $post['meta_input']['_menu_item_menu_item_parent'] : (int) $post['post_parent'];
	$post['post_parent'] = 0;
	if ( $post_parent ) {
		// if we already know the parent, map it to the new local ID
		if ( isset( $processed_posts[ $post_parent ] ) ) {
			if( $post['post_type'] == 'nav_menu_item' ) {
				$post['meta_input']['_menu_item_menu_item_parent'] = $processed_posts[ $post_parent ];
			} else {
				$post['post_parent'] = $processed_posts[ $post_parent ];
			}
		}
	}

	/**
	 * for hierarchical taxonomies, IDs must be used so wp_set_post_terms can function properly
	 * convert term slugs to IDs for hierarchical taxonomies
	 */
	if( ! empty( $post['tax_input'] ) ) {
		foreach( $post['tax_input'] as $tax => $terms ) {
			if( is_taxonomy_hierarchical( $tax ) ) {
				$terms = explode( ', ', $terms );
				$post['tax_input'][ $tax ] = array_map( 'themify_get_term_id_by_slug', $terms, array_fill( 0, count( $terms ), $tax ) );
			}
		}
	}

	$post['post_author'] = (int) get_current_user_id();
	$post['post_status'] = 'publish';

	$old_id = $post['ID'];

	unset( $post['ID'] );
	$post_id = wp_insert_post( $post, true );
	if( is_wp_error( $post_id ) ) {
		return false;
	} else {
		$processed_posts[ $old_id ] = $post_id;

		if( isset( $post['has_thumbnail'] ) && $post['has_thumbnail'] ) {
			$placeholder = themify_get_placeholder_image();
			if( ! is_wp_error( $placeholder ) ) {
				set_post_thumbnail( $post_id, $placeholder );
			}
		}

		return $post_id;
	}
}

function themify_get_placeholder_image() {
	static $placeholder_image = null;

	if( $placeholder_image == null ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		$upload = wp_upload_bits( $post['post_name'] . '.jpg', null, $wp_filesystem->get_contents( THEMIFY_DIR . '/img/image-placeholder.jpg' ) );

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'themify' ) );

		$post['guid'] = $upload['url'];
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		$placeholder_image = $post_id;
	}

	return $placeholder_image;
}

function themify_import_term( $term ) {
	global $processed_terms;

	if( $term_id = term_exists( $term['slug'], $term['taxonomy'] ) ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term['term_id'] ) )
			$processed_terms[ intval( $term['term_id'] ) ] = (int) $term_id;
		return (int) $term_id;
	}

	if ( empty( $term['parent'] ) ) {
		$parent = 0;
	} else {
		$parent = term_exists( $term['parent'], $term['taxonomy'] );
		if ( is_array( $parent ) ) $parent = $parent['term_id'];
	}

	$id = wp_insert_term( $term['name'], $term['taxonomy'], array(
		'parent' => $parent,
		'slug' => $term['slug'],
		'description' => $term['description'],
	) );
	if ( ! is_wp_error( $id ) ) {
		if ( isset( $term['term_id'] ) ) {
			$processed_terms[ intval($term['term_id']) ] = $id['term_id'];
			return $term['term_id'];
		}
	}

	return false;
}

function themify_get_term_id_by_slug( $slug, $tax ) {
	$term = get_term_by( 'slug', $slug, $tax );
	if( $term ) {
		return $term->term_id;
	}

	return false;
}

function themify_undo_import_term( $term ) {
	$term_id = term_exists( $term['slug'], $term['term_taxonomy'] );
	if ( $term_id ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term_id ) ) {
			wp_delete_term( $term_id, $term['term_taxonomy'] );
		}
	}
}

/**
 * Determine if a post exists based on title, content, and date
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $args array of database parameters to check
 * @return int Post ID if post exists, 0 otherwise.
 */
function themify_post_exists( $args = array() ) {
	global $wpdb;

	$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
	$db_args = array();

	foreach ( $args as $key => $value ) {
		$value = wp_unslash( sanitize_post_field( $key, $value, 0, 'db' ) );
		if( ! empty( $value ) ) {
			$query .= ' AND ' . $key . ' = %s';
			$db_args[] = $value;
		}
	}

	if ( !empty ( $args ) )
		return (int) $wpdb->get_var( $wpdb->prepare($query, $args) );

	return 0;
}

function themify_undo_import_post( $post ) {
	if( $post['post_type'] == 'nav_menu_item' ) {
		$post_exists = themify_post_exists( array(
			'post_name' => $post['post_name'],
			'post_modified' => $post['post_date'],
			'post_type' => 'nav_menu_item',
		) );
	} else {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
	}
	if( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
		/**
		 * check if the post has been modified, if so leave it be
		 *
		 * NOTE: posts are imported using wp_insert_post() which modifies post_modified field
		 * to be the same as post_date, hence to check if the post has been modified,
		 * the post_modified field is compared against post_date in the original post.
		 */
		if( $post['post_date'] == get_post_field( 'post_modified', $post_exists ) ) {
			wp_delete_post( $post_exists, true ); // true: bypass trash
		}
	}
}

function themify_do_demo_import() {
$term = array (
  'term_id' => 5,
  'name' => 'Blog',
  'slug' => 'blog',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 7,
  'name' => 'News',
  'slug' => 'news',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 8,
  'name' => 'Sports',
  'slug' => 'sports',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 7,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 9,
  'name' => 'Top Stories',
  'slug' => 'top-stories',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 7,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 12,
  'name' => 'World',
  'slug' => 'world',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 7,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 13,
  'name' => 'Culture',
  'slug' => 'culture',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 7,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 15,
  'name' => 'Lifestyle',
  'slug' => 'lifestyle',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 7,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 35,
  'name' => 'Events',
  'slug' => 'events',
  'term_group' => 0,
  'taxonomy' => 'event-category',
  'description' => '',
  'parent' => 0,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 3,
  'name' => 'Image Gallery',
  'slug' => 'image-gallery',
  'term_group' => 0,
  'taxonomy' => 'gallery-category',
  'description' => '',
  'parent' => 0,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 4,
  'name' => 'Videos',
  'slug' => 'videos',
  'term_group' => 0,
  'taxonomy' => 'video-category',
  'description' => '',
  'parent' => 0,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 36,
  'name' => 'halloween',
  'slug' => 'halloween',
  'term_group' => 0,
  'taxonomy' => 'video-tag',
  'description' => '',
  'parent' => 0,
);
if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$post = array (
  'ID' => 27,
  'post_date' => '2014-02-25 00:48:02',
  'post_date_gmt' => '2014-02-25 00:48:02',
  'post_content' => 'Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Etiam pellentesque magna nec sodales condimentum. Pellentesque sodales sodales lacus eget faucibus. Vestibulum aliquet purus vitae tincidunt mattis.',
  'post_title' => 'Blog Post',
  'post_excerpt' => '',
  'post_name' => 'blog-post',
  'post_modified' => '2017-08-21 07:40:03',
  'post_modified_gmt' => '2017-08-21 07:40:03',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?p=27',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'blog',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1802,
  'post_date' => '2013-06-26 00:43:06',
  'post_date_gmt' => '2013-06-26 00:43:06',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam lobortis ac tellus id tempor. Aliquam pellentesque nibh quis justo commodo tristique. Aliquam erat volutpat. Etiam ut justo aliquam, euismod dolor eget, ullamcorper tortor. Aliquam eu ipsum a urna lacinia aliquam id non dui.',
  'post_title' => 'Travel the world',
  'post_excerpt' => '',
  'post_name' => 'travel-the-world',
  'post_modified' => '2017-08-21 07:40:43',
  'post_modified_gmt' => '2017-08-21 07:40:43',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1802',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'top-stories',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1805,
  'post_date' => '2013-06-26 00:44:06',
  'post_date_gmt' => '2013-06-26 00:44:06',
  'post_content' => 'Fusce hendrerit adipiscing diam vitae sodales. Sed faucibus venenatis lectus sed laoreet. Sed in libero ac nisi placerat dictum. Donec dui neque, aliquam non nunc nec, porttitor tempor leo. Maecenas non sagittis neque.',
  'post_title' => 'Morning News',
  'post_excerpt' => '',
  'post_name' => 'morning-news',
  'post_modified' => '2017-08-21 07:40:37',
  'post_modified_gmt' => '2017-08-21 07:40:37',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1805',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'top-stories',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1808,
  'post_date' => '2013-06-26 00:45:33',
  'post_date_gmt' => '2013-06-26 00:45:33',
  'post_content' => 'Duis laoreet tortor magna, sit amet viverra elit dignissim sit amet. Aenean tempor et tortor eget blandit. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Sed aliquam, sapien et tincidunt sodales, risus lectus rutrum turpis.',
  'post_title' => 'Greenhouse Plants',
  'post_excerpt' => '',
  'post_name' => 'greenhouse-plants',
  'post_modified' => '2017-08-21 07:40:36',
  'post_modified_gmt' => '2017-08-21 07:40:36',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1808',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'top-stories',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1811,
  'post_date' => '2013-06-26 00:46:53',
  'post_date_gmt' => '2013-06-26 00:46:53',
  'post_content' => 'Duis diam urna, aliquam id mauris nec, tristique ultrices turpis. Nam non ante in nunc euismod rutrum. Cras tristique feugiat neque sed vestibulum.',
  'post_title' => 'Shop on the Run',
  'post_excerpt' => '',
  'post_name' => 'shop-on-the-run',
  'post_modified' => '2017-08-21 07:40:35',
  'post_modified_gmt' => '2017-08-21 07:40:35',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1811',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'top-stories',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1815,
  'post_date' => '2013-06-26 00:51:24',
  'post_date_gmt' => '2013-06-26 00:51:24',
  'post_content' => 'Sed eu urna quis lacus aliquet fermentum vel sed risus. Integer laoreet pretium interdum. Proin consequat consequat feugiat. Integer pellentesque faucibus aliquet.',
  'post_title' => 'The Desert Run',
  'post_excerpt' => '',
  'post_name' => 'the-desert-run',
  'post_modified' => '2017-08-21 07:40:33',
  'post_modified_gmt' => '2017-08-21 07:40:33',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1815',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'sports',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1819,
  'post_date' => '2013-06-26 00:58:12',
  'post_date_gmt' => '2013-06-26 00:58:12',
  'post_content' => 'hasellus at nibh in erat rhoncus ornare. In convallis quis est fermentum sollicitudin. Phasellus nec purus elit. Aenean tempus tincidunt dolor, quis auctor diam auctor non.',
  'post_title' => 'Football League',
  'post_excerpt' => '',
  'post_name' => 'football-league',
  'post_modified' => '2017-08-21 07:40:31',
  'post_modified_gmt' => '2017-08-21 07:40:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1819',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'sports',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1822,
  'post_date' => '2013-06-26 23:38:36',
  'post_date_gmt' => '2013-06-26 23:38:36',
  'post_content' => 'Donec auctor consectetur tellus, in hendrerit urna vulputate non. Ut elementum fringilla purus. Nam dui erat, porta eu gravida sit amet, ornare sit amet sem.

&nbsp;',
  'post_title' => 'Dirt Championship',
  'post_excerpt' => '',
  'post_name' => 'dirt-championship',
  'post_modified' => '2017-08-21 07:40:04',
  'post_modified_gmt' => '2017-08-21 07:40:04',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1822',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'sports',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1825,
  'post_date' => '2013-06-26 01:20:01',
  'post_date_gmt' => '2013-06-26 01:20:01',
  'post_content' => 'Vestibulum posuere, nunc eu consequat pulvinar, ipsum tortor dictum massa, vitae auctor diam nisi id dolor. Quisque blandit sem ac mauris rutrum, vitae dapibus orci ultrices. Fusce dignissim dignissim bibendum.',
  'post_title' => 'Kids Division',
  'post_excerpt' => '',
  'post_name' => 'kids-division',
  'post_modified' => '2017-08-21 07:40:27',
  'post_modified_gmt' => '2017-08-21 07:40:27',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1825',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'sports',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1828,
  'post_date' => '2013-06-26 01:21:13',
  'post_date_gmt' => '2013-06-26 01:21:13',
  'post_content' => 'Aliquam mattis mauris a sapien tincidunt, ac vestibulum urna porta. Aenean aliquet vulputate lacus vel venenatis. Etiam lorem sapien, vestibulum ut nisl sed, egestas dignissim enim.',
  'post_title' => 'From the Marathon',
  'post_excerpt' => '',
  'post_name' => 'from-the-marathon',
  'post_modified' => '2017-08-21 07:40:26',
  'post_modified_gmt' => '2017-08-21 07:40:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1828',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'sports',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1833,
  'post_date' => '2013-06-26 01:30:51',
  'post_date_gmt' => '2013-06-26 01:30:51',
  'post_content' => 'Etiam lorem sapien, vestibulum ut nisl sed, egestas dignissim enim. Nam lacus massa, pellentesque eget pulvinar vitae, sagittis eget justo. Maecenas bibendum sit amet odio et sodales. Praesent cursus mattis tortor, ut vestibulum purus venenatis at.',
  'post_title' => 'Watercolor',
  'post_excerpt' => '',
  'post_name' => 'watercolor',
  'post_modified' => '2017-08-21 07:40:24',
  'post_modified_gmt' => '2017-08-21 07:40:24',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1833',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'culture',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1836,
  'post_date' => '2013-06-26 01:31:26',
  'post_date_gmt' => '2013-06-26 01:31:26',
  'post_content' => 'Cras tristique feugiat neque sed vestibulum. Sed eu urna quis lacus aliquet fermentum vel sed risus. Integer laoreet pretium interdum. Proin consequat consequat feugiat. Integer pellentesque faucibus aliquet.',
  'post_title' => 'Living Art',
  'post_excerpt' => '',
  'post_name' => 'living-art',
  'post_modified' => '2017-08-21 07:40:22',
  'post_modified_gmt' => '2017-08-21 07:40:22',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1836',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'culture',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1839,
  'post_date' => '2013-06-26 01:33:00',
  'post_date_gmt' => '2013-06-26 01:33:00',
  'post_content' => 'In convallis quis est fermentum sollicitudin. Phasellus nec purus elit. Aenean tempus tincidunt dolor, quis auctor diam auctor non. Quisque at fermentum purus, a aliquet arcu.',
  'post_title' => 'Long Exposures',
  'post_excerpt' => '',
  'post_name' => 'long-exposures',
  'post_modified' => '2017-08-21 07:40:20',
  'post_modified_gmt' => '2017-08-21 07:40:20',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1839',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'culture',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1845,
  'post_date' => '2013-06-26 01:36:35',
  'post_date_gmt' => '2013-06-26 01:36:35',
  'post_content' => 'Donec hendrerit, lectus in dapibus consequat, libero arcu dignissim turpis, id dictum odio felis eget ante. In ullamcorper pulvinar rutrum. In id neque pulvinar, tempor orci ac, tincidunt libero. Fusce ultricies arcu at mauris semper bibendum.',
  'post_title' => 'Cooking Courses',
  'post_excerpt' => '',
  'post_name' => 'cooking-courses',
  'post_modified' => '2017-08-21 07:40:16',
  'post_modified_gmt' => '2017-08-21 07:40:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1845',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'world',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1849,
  'post_date' => '2013-06-26 01:38:43',
  'post_date_gmt' => '2013-06-26 01:38:43',
  'post_content' => 'Phasellus dui erat, tincidunt pulvinar tempor at, lacinia eu lacus. Aenean euismod tellus laoreet turpis viverra facilisis. Nunc eu viverra eros, et facilisis dui. Sed pretium id risus eu tincidunt.',
  'post_title' => 'Maritime Shipping',
  'post_excerpt' => '',
  'post_name' => 'maritime-shipping',
  'post_modified' => '2017-08-21 07:40:14',
  'post_modified_gmt' => '2017-08-21 07:40:14',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1849',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'world',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1852,
  'post_date' => '2013-06-26 01:42:25',
  'post_date_gmt' => '2013-06-26 01:42:25',
  'post_content' => 'In lobortis vehicula lectus, et venenatis velit euismod sit amet. Morbi egestas malesuada turpis, dictum consequat mauris scelerisque ac. Mauris luctus commodo lorem, pulvinar sollicitudin ante porttitor id.',
  'post_title' => 'Water Town',
  'post_excerpt' => '',
  'post_name' => 'water-town',
  'post_modified' => '2017-08-21 07:40:11',
  'post_modified_gmt' => '2017-08-21 07:40:11',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1852',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'world',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1857,
  'post_date' => '2013-06-26 02:46:21',
  'post_date_gmt' => '2013-06-26 02:46:21',
  'post_content' => 'Nullam fringilla facilisis ultricies. Ut volutpat ultricies rutrum. In laoreet, nunc et auctor condimentum, enim lacus lacinia dolor, non accumsan leo nisl id lorem. Duis vehicula et turpis fringilla hendrerit.',
  'post_title' => 'Remote Places',
  'post_excerpt' => '',
  'post_name' => 'remote-places',
  'post_modified' => '2017-08-21 07:40:09',
  'post_modified_gmt' => '2017-08-21 07:40:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1857',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1860,
  'post_date' => '2013-06-26 02:47:20',
  'post_date_gmt' => '2013-06-26 02:47:20',
  'post_content' => 'Duis eget tellus nisl. Donec porta orci vel iaculis porta. Vivamus aliquet, ligula et tempus mattis, tortor ipsum eleifend massa, ac gravida dui est quis dui.',
  'post_title' => 'Evening Rides',
  'post_excerpt' => '',
  'post_name' => 'evening-rides',
  'post_modified' => '2017-08-21 07:40:07',
  'post_modified_gmt' => '2017-08-21 07:40:07',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1860',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1863,
  'post_date' => '2013-06-26 02:48:34',
  'post_date_gmt' => '2013-06-26 02:48:34',
  'post_content' => 'Proin vitae lectus eu turpis sollicitudin sagittis. Aliquam nunc odio, semper lacinia tincidunt a, dapibus vitae dolor. Class aptent taciti sociosqu ad litora torquent per conubia.',
  'post_title' => 'Learn Something New',
  'post_excerpt' => '',
  'post_name' => 'learn-something-new',
  'post_modified' => '2017-08-21 07:40:06',
  'post_modified_gmt' => '2017-08-21 07:40:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1863',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1865,
  'post_date' => '2013-06-26 02:49:39',
  'post_date_gmt' => '2013-06-26 02:49:39',
  'post_content' => 'Vivamus pharetra magna fermentum tincidunt imperdiet. Aenean venenatis sollicitudin odio in ultrices. Proin a nibh at dolor rhoncus pulvinar. Nullam eget tincidunt enim.',
  'post_title' => 'Clean Air',
  'post_excerpt' => '',
  'post_name' => 'clean-air',
  'post_modified' => '2017-08-21 07:40:04',
  'post_modified_gmt' => '2017-08-21 07:40:04',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1865',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2299,
  'post_date' => '2014-03-24 12:23:58',
  'post_date_gmt' => '2014-03-24 12:23:58',
  'post_content' => '',
  'post_title' => 'Blog - 4 Column',
  'post_excerpt' => '',
  'post_name' => 'blog-4-column',
  'post_modified' => '2017-08-21 07:42:33',
  'post_modified_gmt' => '2017-08-21 07:42:33',
  'post_content_filtered' => '',
  'post_parent' => 2207,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2299',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'grid4',
    'posts_per_page' => '8',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2302,
  'post_date' => '2014-03-24 12:24:53',
  'post_date_gmt' => '2014-03-24 12:24:53',
  'post_content' => '',
  'post_title' => 'Blog - 3 Column',
  'post_excerpt' => '',
  'post_name' => 'blog-3-column',
  'post_modified' => '2017-08-21 07:42:32',
  'post_modified_gmt' => '2017-08-21 07:42:32',
  'post_content_filtered' => '',
  'post_parent' => 2207,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2302',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'grid3',
    'posts_per_page' => '6',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2304,
  'post_date' => '2014-03-24 12:25:41',
  'post_date_gmt' => '2014-03-24 12:25:41',
  'post_content' => '',
  'post_title' => 'Blog - 2 Column',
  'post_excerpt' => '',
  'post_name' => 'blog-2-column',
  'post_modified' => '2017-08-21 07:42:26',
  'post_modified_gmt' => '2017-08-21 07:42:26',
  'post_content_filtered' => '',
  'post_parent' => 2207,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2304',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'grid2',
    'posts_per_page' => '4',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2306,
  'post_date' => '2014-03-24 12:28:17',
  'post_date_gmt' => '2014-03-24 12:28:17',
  'post_content' => '',
  'post_title' => 'Blog - Fullwidth',
  'post_excerpt' => '',
  'post_name' => 'blog-fullwidth',
  'post_modified' => '2017-08-21 07:43:38',
  'post_modified_gmt' => '2017-08-21 07:43:38',
  'post_content_filtered' => '',
  'post_parent' => 2207,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2306',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'yes',
    'query_category' => '0',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'grid4',
    'posts_per_page' => '12',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"32\\"],\\"_edit_lock\\":[\\"1395664011:32\\"],\\"layout\\":[\\"grid4\\"],\\"content_width\\":[\\"default_width\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"yes\\"],\\"query_category\\":[\\"0\\"],\\"order\\":[\\"desc\\"],\\"orderby\\":[\\"content\\"],\\"posts_per_page\\":[\\"12\\"],\\"display_content\\":[\\"excerpt\\"],\\"feature_size_page\\":[\\"blank\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"hide_post_stats\\":[\\"default\\"],\\"event_order\\":[\\"desc\\"],\\"event_orderby\\":[\\"content\\"],\\"event_layout\\":[\\"list-post\\"],\\"event_display_content\\":[\\"content\\"],\\"event_feature_size_page\\":[\\"blank\\"],\\"event_hide_title\\":[\\"default\\"],\\"event_unlink_title\\":[\\"default\\"],\\"event_hide_date\\":[\\"default\\"],\\"event_hide_meta_all\\":[\\"default\\"],\\"event_hide_image\\":[\\"default\\"],\\"event_unlink_image\\":[\\"default\\"],\\"event_hide_navigation\\":[\\"default\\"],\\"event_hide_post_stats\\":[\\"default\\"],\\"event_hide_event_location\\":[\\"default\\"],\\"event_hide_event_date\\":[\\"default\\"],\\"video_order\\":[\\"desc\\"],\\"video_orderby\\":[\\"content\\"],\\"video_layout\\":[\\"list-post\\"],\\"video_display_content\\":[\\"content\\"],\\"video_feature_size_page\\":[\\"blank\\"],\\"video_hide_title\\":[\\"default\\"],\\"video_unlink_title\\":[\\"default\\"],\\"video_hide_date\\":[\\"default\\"],\\"video_hide_meta_all\\":[\\"default\\"],\\"video_hide_image\\":[\\"default\\"],\\"video_unlink_image\\":[\\"default\\"],\\"video_hide_navigation\\":[\\"default\\"],\\"video_hide_post_stats\\":[\\"default\\"],\\"gallery_order\\":[\\"desc\\"],\\"gallery_orderby\\":[\\"content\\"],\\"gallery_layout\\":[\\"list-post\\"],\\"gallery_display_content\\":[\\"content\\"],\\"gallery_feature_size_page\\":[\\"blank\\"],\\"gallery_hide_title\\":[\\"default\\"],\\"gallery_unlink_title\\":[\\"default\\"],\\"gallery_hide_date\\":[\\"default\\"],\\"gallery_hide_meta_all\\":[\\"default\\"],\\"gallery_hide_image\\":[\\"default\\"],\\"gallery_unlink_image\\":[\\"default\\"],\\"gallery_hide_navigation\\":[\\"default\\"],\\"gallery_hide_post_stats\\":[\\"default\\"],\\"themify_pageviews\\":[\\"3305\\"],\\"builder_switch_frontend\\":[\\"0\\"]}',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2308,
  'post_date' => '2014-03-24 22:34:18',
  'post_date_gmt' => '2014-03-24 22:34:18',
  'post_content' => '',
  'post_title' => 'Videos - 4 Column',
  'post_excerpt' => '',
  'post_name' => 'videos-4-column',
  'post_modified' => '2017-08-21 07:42:22',
  'post_modified_gmt' => '2017-08-21 07:42:22',
  'post_content_filtered' => '',
  'post_parent' => 2237,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2308',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_query_category' => '0',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'grid4',
    'video_display_content' => 'none',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'yes',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2310,
  'post_date' => '2014-03-24 22:36:33',
  'post_date_gmt' => '2014-03-24 22:36:33',
  'post_content' => '',
  'post_title' => 'Galleries - 4 Column',
  'post_excerpt' => '',
  'post_name' => 'galleries-4-column',
  'post_modified' => '2017-08-21 07:42:15',
  'post_modified_gmt' => '2017-08-21 07:42:15',
  'post_content_filtered' => '',
  'post_parent' => 2234,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2310',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_query_category' => '0',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'grid4',
    'gallery_posts_per_page' => '8',
    'gallery_display_content' => 'none',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2374,
  'post_date' => '2014-03-24 23:02:44',
  'post_date_gmt' => '2014-03-24 23:02:44',
  'post_content' => '',
  'post_title' => 'Galleries - 3 Column',
  'post_excerpt' => '',
  'post_name' => 'galleries-3-column',
  'post_modified' => '2017-08-21 07:42:12',
  'post_modified_gmt' => '2017-08-21 07:42:12',
  'post_content_filtered' => '',
  'post_parent' => 2234,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2374',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_query_category' => '0',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'grid3',
    'gallery_posts_per_page' => '6',
    'gallery_display_content' => 'none',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2376,
  'post_date' => '2014-03-24 23:03:19',
  'post_date_gmt' => '2014-03-24 23:03:19',
  'post_content' => '',
  'post_title' => 'Galleries - 2 Column',
  'post_excerpt' => '',
  'post_name' => 'galleries-2-column',
  'post_modified' => '2017-08-21 07:42:09',
  'post_modified_gmt' => '2017-08-21 07:42:09',
  'post_content_filtered' => '',
  'post_parent' => 2234,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2376',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_query_category' => '0',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'grid2',
    'gallery_posts_per_page' => '4',
    'gallery_display_content' => 'none',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2378,
  'post_date' => '2014-03-24 23:09:02',
  'post_date_gmt' => '2014-03-24 23:09:02',
  'post_content' => '',
  'post_title' => 'Videos - 3 Column',
  'post_excerpt' => '',
  'post_name' => 'videos-3-column',
  'post_modified' => '2017-08-21 07:42:21',
  'post_modified_gmt' => '2017-08-21 07:42:21',
  'post_content_filtered' => '',
  'post_parent' => 2237,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2378',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_query_category' => '0',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'grid3',
    'video_posts_per_page' => '6',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2380,
  'post_date' => '2014-03-24 23:10:13',
  'post_date_gmt' => '2014-03-24 23:10:13',
  'post_content' => '',
  'post_title' => 'Videos - 2 Column',
  'post_excerpt' => '',
  'post_name' => 'videos-2-column',
  'post_modified' => '2017-08-21 07:42:17',
  'post_modified_gmt' => '2017-08-21 07:42:17',
  'post_content_filtered' => '',
  'post_parent' => 2237,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2380',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_query_category' => '0',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'grid2',
    'video_posts_per_page' => '4',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2382,
  'post_date' => '2014-03-24 23:16:35',
  'post_date_gmt' => '2014-03-24 23:16:35',
  'post_content' => '',
  'post_title' => 'Events - 4 Column',
  'post_excerpt' => '',
  'post_name' => 'events-4-column',
  'post_modified' => '2017-08-21 07:42:06',
  'post_modified_gmt' => '2017-08-21 07:42:06',
  'post_content_filtered' => '',
  'post_parent' => 2216,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2382',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta_all' => 'yes',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_query_category' => '0',
    'event_display' => 'both',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'grid4',
    'event_posts_per_page' => '8',
    'event_display_content' => 'none',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2384,
  'post_date' => '2014-03-24 23:18:16',
  'post_date_gmt' => '2014-03-24 23:18:16',
  'post_content' => '',
  'post_title' => 'Events - 3 Column',
  'post_excerpt' => '',
  'post_name' => 'events-3-column',
  'post_modified' => '2017-08-21 07:42:03',
  'post_modified_gmt' => '2017-08-21 07:42:03',
  'post_content_filtered' => '',
  'post_parent' => 2216,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2384',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_query_category' => '0',
    'event_display' => 'both',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'grid3',
    'event_posts_per_page' => '6',
    'event_display_content' => 'none',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2386,
  'post_date' => '2014-03-24 23:37:05',
  'post_date_gmt' => '2014-03-24 23:37:05',
  'post_content' => '',
  'post_title' => 'Events - 2 Column',
  'post_excerpt' => '',
  'post_name' => 'events-2-column',
  'post_modified' => '2017-08-21 07:42:04',
  'post_modified_gmt' => '2017-08-21 07:42:04',
  'post_content_filtered' => '',
  'post_parent' => 2216,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2386',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_query_category' => '0',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'grid2',
    'event_posts_per_page' => '4',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2256,
  'post_date' => '2014-03-17 22:43:33',
  'post_date_gmt' => '2014-03-17 22:43:33',
  'post_content' => '<!--themify_builder_static--><article id="post-2400"> <figure>
 <a href="https://themify.me/demo/themes/event/event/sunday-lounge/"><img src="https://themify.me/demo/themes/event/files/2014/03/132682073-1280x500.jpg" width="1280" height="500" alt="132682073" /></a> </figure>
 
 
 
 <time> Apr 24, 2015 @ 2:00 pm </time> 
 Eight Day Club 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/sunday-lounge/">Sunday Lounge</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2400&#038;action=edit">Edit</a>] </article>
<article id="post-2398"> <figure>
 <a href="https://themify.me/demo/themes/event/event/dress-code/"><img src="https://themify.me/demo/themes/event/files/2014/03/119002144-1280x500.jpg" width="1280" height="500" alt="119002144" /></a> </figure>
 
 
 
 <time> Sep 30, 2015 @ 10:00 pm </time> 
 Eight Day Club 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/dress-code/">Dress Code</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2398&#038;action=edit">Edit</a>] </article>
<article id="post-2224"> <figure>
 <a href="https://themify.me/demo/themes/event/event/lightbox-showcase/"><img src="https://themify.me/demo/themes/event/files/2014/03/111339836-1280x500.jpg" width="1280" height="500" alt="111339836" /></a> </figure>
 
 
 
 <time> Oct 7, 2015 @ 10:00 pm </time> 
 Québec 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/lightbox-showcase/">Lightbox Showcase</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2224&#038;action=edit">Edit</a>] </article>
 <h2>Videos</h2>
<article id="post-2325"> <figure data-thumb="https://i.vimeocdn.com/video/570255381_295x166.jpg">
 <a href="https://vimeo.com/131586644?iframe=true&width=100%25&height=100%25"><img alt="The Emperor of Time" title="The Emperor of Time" src="https://i.vimeocdn.com/video/570255381_295x166.jpg" width="1280" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/video/the-emperor-of-time/">The Emperor of Time</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2325&#038;action=edit">Edit</a>] </article>
<article id="post-2324"> <figure data-thumb="https://img.youtube.com/vi/XX6lCIuXMEo/hqdefault.jpg">
 <a href="http://youtu.be/XX6lCIuXMEo?iframe=true&width=100%25&height=100%25"><img alt="" title="" src="https://img.youtube.com/vi/XX6lCIuXMEo/hqdefault.jpg" width="1280" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/video/yuna-live-session/">Yuna Live Session</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2324&#038;action=edit">Edit</a>] </article>
<article id="post-2323"> <figure data-thumb="https://i.vimeocdn.com/video/508021302_295x166.jpg">
 <a href="http://vimeo.com/69455608?iframe=true&width=100%25&height=100%25"><img alt="Studio Pigeon explainer: Swap DJs" title="Studio Pigeon explainer: Swap DJs" src="https://i.vimeocdn.com/video/508021302_295x166.jpg" width="1280" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/video/swap-djs/">Swap DJs</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2323&#038;action=edit">Edit</a>] </article>
<article id="post-2322"> <figure data-thumb="https://img.youtube.com/vi/sd7GLvMYSHI/hqdefault.jpg">
 <a href="http://youtu.be/sd7GLvMYSHI?iframe=true&width=100%25&height=100%25"><img alt="" title="" src="https://img.youtube.com/vi/sd7GLvMYSHI/hqdefault.jpg" width="1280" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/video/kimbra-settle/">Kimbra &#8211; Settle Down</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2322&#038;action=edit">Edit</a>] </article>
<article id="post-2321"> <figure data-thumb="https://i.vimeocdn.com/video/109662986_295x166.jpg">
 <a href="http://vimeo.com/17607732?iframe=true&width=100%25&height=100%25"><img alt="DJ Light (DJ Luz), Lima 2010" title="DJ Light (DJ Luz), Lima 2010" src="https://i.vimeocdn.com/video/109662986_295x166.jpg" width="1280" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/video/dj-light-peru/">DJ Light &#8211; Peru</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2321&#038;action=edit">Edit</a>] </article>
<article id="post-2251"> <figure data-thumb="https://themify.me/demo/themes/event/files/2014/04/apple-tv-ad-1280x500.jpg">
 <a href="https://themify.me/demo/demo-videos/Apple-Iphone4s-TvAd-Life1.mp4"><img src="https://themify.me/demo/themes/event/files/2014/04/apple-tv-ad-1280x500.jpg" width="1280" height="500" alt="apple tv ad" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/demo-videos/Apple-Iphone4s-TvAd-Life1.mp4">Apple TV Ad (mp4)</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2251&#038;action=edit">Edit</a>] 
</article>
<a href="#"><img alt="The Emperor of Time" title="The Emperor of Time" src="https://i.vimeocdn.com/video/570255381_295x166.jpg" width="1280" /></a><a href="#"><img alt="" title="" src="https://img.youtube.com/vi/XX6lCIuXMEo/hqdefault.jpg" width="1280" /></a><a href="#"><img alt="Studio Pigeon explainer: Swap DJs" title="Studio Pigeon explainer: Swap DJs" src="https://i.vimeocdn.com/video/508021302_295x166.jpg" width="1280" /></a><a href="#"><img alt="" title="" src="https://img.youtube.com/vi/sd7GLvMYSHI/hqdefault.jpg" width="1280" /></a><a href="#"><img alt="DJ Light (DJ Luz), Lima 2010" title="DJ Light (DJ Luz), Lima 2010" src="https://i.vimeocdn.com/video/109662986_295x166.jpg" width="1280" /></a><a href="#"><img src="https://themify.me/demo/themes/event/files/2014/04/apple-tv-ad-1280x500.jpg" width="1280" height="500" alt="apple tv ad" /></a>
 
<article id="post-2358"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/6-column-masonry-gallery/"><img src="https://themify.me/demo/themes/event/files/2014/03/dv1444021-1280x700.jpg" width="1280" height="700" alt="dv1444021" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/6-column-masonry-gallery/">6-Column Masonry Gallery</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2358&#038;action=edit">Edit</a>] </article>
<article id="post-2349"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-4-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/101428541-1280x700.jpg" width="1280" height="700" alt="101428541" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-4-columns/">Gallery &#8211; 4 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2349&#038;action=edit">Edit</a>] </article>
<article id="post-2340"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-2-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/106410254-1280x700.jpg" width="1280" height="700" alt="106410254" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-2-columns/">Gallery &#8211; 2 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2340&#038;action=edit">Edit</a>] </article>
<article id="post-2326"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-3-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/132682073-1280x700.jpg" width="1280" height="700" alt="132682073" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-3-columns/">Gallery &#8211; 3 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2326&#038;action=edit">Edit</a>] </article>
 <h2>Events</h2><ul><li><a href="#" data-tab="upcoming">Upcoming</a></li><li><a href="#" data-tab="past">Past</a></li></ul><ul><li>
<article id="post-2400"> <figure>
 <a href="https://themify.me/demo/themes/event/event/sunday-lounge/"><img src="https://themify.me/demo/themes/event/files/2014/03/132682073-350x200.jpg" width="350" height="200" alt="132682073" srcset="https://themify.me/demo/themes/event/files/2014/03/132682073-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/132682073-470x270.jpg 470w, https://themify.me/demo/themes/event/files/2014/03/132682073-730x420.jpg 730w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 
 
 <time> Apr 24, 2015 @ 2:00 pm </time> 
 Eight Day Club 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/sunday-lounge/">Sunday Lounge</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2400&#038;action=edit">Edit</a>] </article>
<article id="post-2398"> <figure>
 <a href="https://themify.me/demo/themes/event/event/dress-code/"><img src="https://themify.me/demo/themes/event/files/2014/03/119002144-350x200.jpg" width="350" height="200" alt="119002144" srcset="https://themify.me/demo/themes/event/files/2014/03/119002144-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/119002144-730x420.jpg 730w, https://themify.me/demo/themes/event/files/2014/03/119002144-470x270.jpg 470w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 
 
 <time> Sep 30, 2015 @ 10:00 pm </time> 
 Eight Day Club 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/dress-code/">Dress Code</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2398&#038;action=edit">Edit</a>] </article>
<article id="post-2224"> <figure>
 <a href="https://themify.me/demo/themes/event/event/lightbox-showcase/"><img src="https://themify.me/demo/themes/event/files/2014/03/111339836-350x200.jpg" width="350" height="200" alt="111339836" srcset="https://themify.me/demo/themes/event/files/2014/03/111339836-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/111339836-470x270.jpg 470w, https://themify.me/demo/themes/event/files/2014/03/111339836-730x420.jpg 730w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 
 
 <time> Oct 7, 2015 @ 10:00 pm </time> 
 Québec 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/lightbox-showcase/">Lightbox Showcase</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2224&#038;action=edit">Edit</a>] </article>
<li>
<article id="post-2402"> <figure>
 <a href="https://themify.me/demo/themes/event/event/alice-new-album-release/"><img src="https://themify.me/demo/themes/event/files/2014/03/680443631-350x200.jpg" width="350" height="200" alt="68044363" srcset="https://themify.me/demo/themes/event/files/2014/03/680443631-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/680443631-730x420.jpg 730w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 
 
 <time> Jan 3, 2014 @ 10:00 pm &#8211; Jan 4, 2014 @ 1:00 am </time> 
 Eight Day Club 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/alice-new-album-release/">Alice &#8211; New Album Release</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2402&#038;action=edit">Edit</a>] </article>
<article id="post-2404"> <figure>
 <a href="https://themify.me/demo/themes/event/event/the-fall-new-album-release/"><img src="https://themify.me/demo/themes/event/files/2014/03/92694433-350x200.jpg" width="350" height="200" alt="92694433" srcset="https://themify.me/demo/themes/event/files/2014/03/92694433-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/92694433-730x420.jpg 730w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 
 
 <time> Jan 17, 2014 @ 10:00 pm &#8211; Jan 18, 2014 @ 4:00 am </time> 
 Eight Day Club 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/the-fall-new-album-release/">The Fall &#8211; New Album Release</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2404&#038;action=edit">Edit</a>] </article>
<article id="post-2220"> <figure>
 <a href="https://themify.me/demo/themes/event/event/car-show/"><img src="https://themify.me/demo/themes/event/files/2013/06/120546220-350x200.jpg" width="350" height="200" alt="120546220" /></a> </figure>
 
 
 
 <time> Feb 1, 2014 @ 4:00 pm &#8211; @ 10:00 pm </time> 
 New York 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/car-show/">Car Show</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2220&#038;action=edit">Edit</a>] </article>
<article id="post-2396"> <figure>
 <a href="https://themify.me/demo/themes/event/event/dj-frank/"><img src="https://themify.me/demo/themes/event/files/2014/02/106679522-350x200.jpg" width="350" height="200" alt="106679522" /></a> </figure>
 
 
 
 <time> Feb 5, 2014 @ 10:00 pm &#8211; Feb 6, 2014 @ 5:00 am </time> 
 Eight Day Club 
 
 
 <h2><a href="https://themify.me/demo/themes/event/event/dj-frank/">DJ Frank</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2396&#038;action=edit">Edit</a>] </article>
</ul>
 <h2>More Demos</h2> <h3>Click on the buttons below to view more demos</h3>
 
 <a href="https://themify.me/demo/themes/event/home/demo-2" > Demo 2 </a> <a href="https://themify.me/demo/themes/event/home/demo-3/" > Demo 3 </a> <a href="https://themify.me/demo/themes/event/home/demo-4/" > Demo 4 </a><!--/themify_builder_static-->',
  'post_title' => 'Home',
  'post_excerpt' => '',
  'post_name' => 'home',
  'post_modified' => '2017-10-28 14:56:25',
  'post_modified_gmt' => '2017-10-28 14:56:25',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?page_id=2256',
  'menu_order' => 1,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[themify_event_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" auto=\\\\\\\\\\\\\\"6\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"cid\\":\\"c16\\"}}]}],\\"styling\\":{\\"row_width\\":\\"fullwidth-content\\",\\"background_color\\":\\"000000_1.00\\",\\"font_color\\":\\"ffffff_1.00\\",\\"link_color\\":\\"d4fffe_1.00\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Videos<\\\\/h2><p>[themify_video_posts limit=\\\\\\\\\\\\\\"6\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" auto=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"cid\\":\\"c27\\"}}]}],\\"styling\\":{\\"background_color\\":\\"000000\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"font_color\\":\\"ffffff\\",\\"link_color\\":\\"ffffff\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" auto=\\\\\\\\\\\\\\"5\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"cid\\":\\"c38\\"}}]}],\\"styling\\":{\\"row_width\\":\\"fullwidth\\",\\"background_color\\":\\"3aa9a5\\",\\"font_color\\":\\"ffffff\\",\\"link_color\\":\\"fffee3\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Events<\\\\/h2><p>[themify_event_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid4 fly-in\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"350\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"cid\\":\\"c49\\"}}]}],\\"styling\\":{\\"background_color\\":\\"00c28e_1.00\\",\\"font_color\\":\\"ffffff_1.00\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"d4fffe_1.00\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h2>More Demos<\\\\/h2>\\\\n<h3>Click on the buttons below to view more demos<\\\\/h3>\\",\\"cid\\":\\"c60\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"large\\",\\"buttons_style\\":\\"outline\\",\\"content_button\\":[{\\"label\\":\\"Demo 2\\",\\"link\\":\\"https://themify.me/demo/themes/event\\\\/home\\\\/demo-2\\",\\"link_options\\":\\"regular\\"},{\\"label\\":\\"Demo 3\\",\\"link\\":\\"https://themify.me/demo/themes/event\\\\/home\\\\/demo-3\\\\/\\",\\"link_options\\":\\"regular\\"},{\\"label\\":\\"Demo 4\\",\\"link\\":\\"https://themify.me/demo/themes/event\\\\/home\\\\/demo-4\\\\/\\",\\"link_options\\":\\"regular\\"}]}}]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/sb10068474al-001.jpg\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_color\\":\\"000000_1.00\\",\\"font_color\\":\\"ffffff_1.00\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"ffffff_1.00\\",\\"padding_top\\":\\"8\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"8\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2294,
  'post_date' => '2014-03-22 03:25:22',
  'post_date_gmt' => '2014-03-22 03:25:22',
  'post_content' => '',
  'post_title' => 'Demo 2',
  'post_excerpt' => '',
  'post_name' => 'demo-2',
  'post_modified' => '2017-08-21 07:41:52',
  'post_modified_gmt' => '2017-08-21 07:41:52',
  'post_content_filtered' => '',
  'post_parent' => 2256,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2294',
  'menu_order' => 1,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[themify_event_posts limit=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" auto=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" event_tab=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"fullwidth\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Magazine Layouts</h2><h3>This page demonstrates how magazine-styled layouts can be created with Themify\\\\\\\\\\\'s drag & drop Builder. Ads can be inserted in between content to maximize performance.</h3>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_color\\":\\"ffe478_1.00\\",\\"font_color\\":\\"000000_1.00\\",\\"link_color\\":\\"80570c_1.00\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right_unit\\":\\"%\\",\\"padding_bottom\\":\\"4\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-3 first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[themify_event_posts limit=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid3 fly-in\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"350\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p><a href=\\\\\\\\\\\\\\"#\\\\\\\\\\\\\\"><img alt=\\\\\\\\\\\\\\"\\\\\\\\\\\\\\" src=\\\\\\\\\\\\\\"https://themify.me/demo/themes/magazine/files/2013/08/728x90.png\\\\\\\\\\\\\\"></a></p>\\\\n\\",\\"font_family\\":\\"default\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"mod_title_text\\":\\"Videos\\",\\"content_text\\":\\"<p>[themify_video_posts limit=\\\\\\\\\\\\\\"6\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"mod_title_text\\":\\"Gallery\\",\\"content_text\\":\\"<p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid3 slide-up\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"350\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"mod_title_text\\":\\"Blog\\",\\"content_text\\":\\"<p>[themify_list_posts limit=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid3 fade-in\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"350\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<a href=\\\\\\\\\\\\\\"#\\\\\\\\\\\\\\"><img src=\\\\\\\\\\\\\\"https://themify.me/demo/themes/magazine/files/2013/08/300x250.png\\\\\\\\\\\\\\" alt=\\\\\\\\\\\\\\"\\\\\\\\\\\\\\" /></a>\\",\\"column_divider_style\\":\\"solid\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"mod_name\\":\\"widgetized\\",\\"mod_settings\\":{\\"sidebar_widgetized\\":\\"sidebar-main\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":{\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1 first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h4>Twitter</h4><p>[twitter username=\\\\\\\\\\\\\\"themify\\\\\\\\\\\\\\" show_count=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" show_follow=\\\\\\\\\\\\\\"true\\\\\\\\\\\\\\" follow_text=\\\\\\\\\\\\\\"Follow Themify\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h4>Photos</h4>\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"font_family_h1\\":\\"default\\",\\"font_family_h2\\":\\"default\\",\\"font_family_h3\\":\\"default\\",\\"font_family_h4\\":\\"default\\",\\"font_family_h5\\":\\"default\\",\\"font_family_h6\\":\\"default\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}},{\\"mod_name\\":\\"gallery\\",\\"mod_settings\\":{\\"layout_gallery\\":\\"grid\\",\\"shortcode_gallery\\":\\"[gallery _orderByField=\\\\\\\\\\\\\\"menu_order ID\\\\\\\\\\\\\\" ids=\\\\\\\\\\\\\\"2365,2364,2363,2362,2361,2360,2359,2357,16,17,18,19\\\\\\\\\\\\\\"]\\",\\"gallery_pagination\\":\\"|\\",\\"thumb_w_gallery\\":\\"150\\",\\"thumb_h_gallery\\":\\"150\\",\\"gallery_columns\\":\\"4\\",\\"link_opt\\":\\"file\\",\\"appearance_gallery\\":\\"|\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_padding_apply_all_padding\\":\\"padding\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_margin_apply_all_margin\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"checkbox_border_apply_all_border\\":\\"border\\",\\"visibility_desktop\\":\\"show\\",\\"visibility_desktop_show\\":\\"show\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet\\":\\"show\\",\\"visibility_tablet_show\\":\\"show\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile\\":\\"show\\",\\"visibility_mobile_show\\":\\"show\\",\\"visibility_mobile_hide\\":\\"hide\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h4>Map</h4>\\\\n<p>[map address=\\\\\\\\\\\\\\"1 Yonge St. Toronto, Ontario, Canada\\\\\\\\\\\\\\" width=100% height=250px scroll_wheel=no]</p>\\\\n\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"34b9f1\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2338,
  'post_date' => '2014-03-24 19:44:50',
  'post_date_gmt' => '2014-03-24 19:44:50',
  'post_content' => '<!--themify_builder_static--><article id="post-2358"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/6-column-masonry-gallery/"><img src="https://themify.me/demo/themes/event/files/2014/03/dv1444021-1280x700.jpg" width="1280" height="700" alt="dv1444021" /></a> </figure>
 
 <time datetime="2014-03-24">Mar 24, 2014</time> <a href="https://themify.me/demo/themes/event/gallery-category/image-gallery/" rel="tag">Image Gallery</a> <h2><a href="https://themify.me/demo/themes/event/gallery/6-column-masonry-gallery/">6-Column Masonry Gallery</a> </h2> 
 
 
 
 
 <a href="#" data-postid="2358" title="Like it!"> <i>27</i> </a> 
 <a href="https://themify.me/demo/themes/event/gallery/6-column-masonry-gallery/#respond"><i>0</i></a> <i>7.6k</i>
 
 <a href="javascript:void(0);" >Share</a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2F6-column-masonry-gallery&#038;text=6-Column+Masonry+Gallery\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2F6-column-masonry-gallery&#038;t=6-Column+Masonry+Gallery&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2F6-column-masonry-gallery%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2F6-column-masonry-gallery&#038;description=6-Column+Masonry+Gallery&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Ffiles%2F2014%2F03%2Fdv1444021.jpg\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2F6-column-masonry-gallery\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2F6-column-masonry-gallery&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 
 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2358&#038;action=edit">Edit</a>] </article>
<article id="post-2349"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-4-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/101428541-1280x700.jpg" width="1280" height="700" alt="101428541" /></a> </figure>
 
 <time datetime="2014-03-24">Mar 24, 2014</time> <a href="https://themify.me/demo/themes/event/gallery-category/image-gallery/" rel="tag">Image Gallery</a> <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-4-columns/">Gallery &#8211; 4 Columns</a> </h2> 
 
 
 
 
 <a href="#" data-postid="2349" title="Like it!"> <i>22</i> </a> 
 <a href="https://themify.me/demo/themes/event/gallery/gallery-4-columns/#respond"><i>0</i></a> <i>5.5k</i>
 
 <a href="javascript:void(0);" >Share</a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-4-columns&#038;text=Gallery+%E2%80%93+4+Columns\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-4-columns&#038;t=Gallery+%E2%80%93+4+Columns&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-4-columns%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-4-columns&#038;description=Gallery+%E2%80%93+4+Columns&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Ffiles%2F2014%2F03%2F101428541.jpg\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-4-columns\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-4-columns&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 
 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2349&#038;action=edit">Edit</a>] </article>
<article id="post-2340"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-2-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/106410254-1280x700.jpg" width="1280" height="700" alt="106410254" /></a> </figure>
 
 <time datetime="2014-03-24">Mar 24, 2014</time> <a href="https://themify.me/demo/themes/event/gallery-category/image-gallery/" rel="tag">Image Gallery</a> <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-2-columns/">Gallery &#8211; 2 Columns</a> </h2> 
 
 
 
 
 <a href="#" data-postid="2340" title="Like it!"> <i>23</i> </a> 
 <a href="https://themify.me/demo/themes/event/gallery/gallery-2-columns/#respond"><i>0</i></a> <i>5.6k</i>
 
 <a href="javascript:void(0);" >Share</a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-2-columns&#038;text=Gallery+%E2%80%93+2+Columns\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-2-columns&#038;t=Gallery+%E2%80%93+2+Columns&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-2-columns%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-2-columns&#038;description=Gallery+%E2%80%93+2+Columns&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Ffiles%2F2014%2F03%2F106410254.jpg\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-2-columns\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-2-columns&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 
 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2340&#038;action=edit">Edit</a>] </article>
<article id="post-2326"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-3-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/132682073-1280x700.jpg" width="1280" height="700" alt="132682073" /></a> </figure>
 
 <time datetime="2014-03-24">Mar 24, 2014</time> <a href="https://themify.me/demo/themes/event/gallery-category/image-gallery/" rel="tag">Image Gallery</a> <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-3-columns/">Gallery &#8211; 3 Columns</a> </h2> 
 
 
 
 
 <a href="#" data-postid="2326" title="Like it!"> <i>21</i> </a> 
 <a href="https://themify.me/demo/themes/event/gallery/gallery-3-columns/#respond"><i>0</i></a> <i>5.2k</i>
 
 <a href="javascript:void(0);" >Share</a> <a onclick="window.open(\'//twitter.com/intent/tweet?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-3-columns&#038;text=Gallery+%E2%80%93+3+Columns\',\'twitter\',\'toolbar=0, status=0, width=650, height=360\')" title="Twitter" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-3-columns&#038;t=Gallery+%E2%80%93+3+Columns&#038;original_referer=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-3-columns%2F\',\'facebook\',\'toolbar=0, status=0, width=900, height=500\')" title="Facebook" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//pinterest.com/pin/create/button/?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-3-columns&#038;description=Gallery+%E2%80%93+3+Columns&#038;media=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Ffiles%2F2014%2F03%2F132682073.jpg\',\'pinterest\',\'toolbar=no,width=700,height=300\')" title="Pinterest" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//plus.google.com/share?hl=en-US&#038;url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-3-columns\',\'googlePlus\',\'toolbar=0, status=0, width=900, height=500\')" title="Google+" rel="nofollow" href="javascript:void(0);"></a> <a onclick="window.open(\'//www.linkedin.com/cws/share?url=https%3A%2F%2Fthemify.me%2Fdemo%2Fthemes%2Fevent%2Fgallery%2Fgallery-3-columns&#038;token=&#038;isFramed=true\',\'linkedin\',\'toolbar=no,width=550,height=550\')" title="LinkedIn" rel="nofollow" href="javascript:void(0);"></a> 
 
 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2326&#038;action=edit">Edit</a>] </article>
 <h2>Get This Theme Now</h2> <h3>Event theme is the perfect theme for entertainment and event sites. Use the Themify&#8217;s drag &#038; drop Builder to create beautiful pages.</h3> <p> </p>
 
 <a href="https://themify.me/themes/event" > HOME </a> <a href="https://themify.me/themes/event" > BUY THEME </a> 
 <h2>Continuously Scrolling Slider</h2>
 <ul data-id="slider-0-" data-visible="5" data-scroll="1" data-auto-scroll="4" data-speed="1" data-wrap="yes" data-arrow="no" data-pagination="no" data-effect="continuously" data-height="variable" data-pause-on-hover="resume" > 
 <li> <img src="https://themify.me/demo/themes/event/files/2014/03/53217325-200x160.jpg" width="200" height="160" alt="Image One" /> 
 <h3> Image One </h3> </li> <li> <img src="https://themify.me/demo/themes/event/files/2014/03/52607113-200x160.jpg" width="200" height="160" alt="Image Two" /> 
 <h3> Image Two </h3> </li> <li> <img src="https://themify.me/demo/themes/event/files/2014/03/33733618-200x160.jpg" width="200" height="160" alt="Image Three" /> 
 <h3> Image Three </h3> </li> <li> <img src="https://themify.me/demo/themes/event/files/2014/03/70561198-200x160.jpg" width="200" height="160" alt="Image Four" /> 
 <h3> Image Four </h3> </li> <li> <img src="https://themify.me/demo/themes/event/files/2014/03/70502623-200x160.jpg" width="200" height="160" alt="Image Five" /> 
 <h3> Image Five </h3> </li> <li> <img src="https://themify.me/demo/themes/event/files/2014/03/67488769-200x160.jpg" width="200" height="160" alt="Image Six" /> 
 <h3> Image Six </h3> </li> </ul> 
 <h2>Gallery Posts</h2>
<article id="post-2358"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/6-column-masonry-gallery/"><img src="https://themify.me/demo/themes/event/files/2014/03/dv1444021-350x200.jpg" width="350" height="200" alt="dv1444021" srcset="https://themify.me/demo/themes/event/files/2014/03/dv1444021-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/dv1444021-470x270.jpg 470w, https://themify.me/demo/themes/event/files/2014/03/dv1444021-730x420.jpg 730w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/6-column-masonry-gallery/">6-Column Masonry Gallery</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2358&#038;action=edit">Edit</a>] </article>
<article id="post-2349"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-4-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/101428541-350x200.jpg" width="350" height="200" alt="101428541" srcset="https://themify.me/demo/themes/event/files/2014/03/101428541-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/101428541-470x270.jpg 470w, https://themify.me/demo/themes/event/files/2014/03/101428541-730x420.jpg 730w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-4-columns/">Gallery &#8211; 4 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2349&#038;action=edit">Edit</a>] </article>
<article id="post-2340"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-2-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/106410254-350x200.jpg" width="350" height="200" alt="106410254" srcset="https://themify.me/demo/themes/event/files/2014/03/106410254-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/106410254-470x270.jpg 470w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-2-columns/">Gallery &#8211; 2 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2340&#038;action=edit">Edit</a>] </article>
<article id="post-2326"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-3-columns/"><img src="https://themify.me/demo/themes/event/files/2014/03/132682073-350x200.jpg" width="350" height="200" alt="132682073" srcset="https://themify.me/demo/themes/event/files/2014/03/132682073-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/03/132682073-470x270.jpg 470w, https://themify.me/demo/themes/event/files/2014/03/132682073-730x420.jpg 730w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-3-columns/">Gallery &#8211; 3 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2326&#038;action=edit">Edit</a>] </article>
<article id="post-2241"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/5-column-mixed-large-images/"><img src="https://themify.me/demo/themes/event/files/2014/02/120106730-350x200.jpg" width="350" height="200" alt="120106730" srcset="https://themify.me/demo/themes/event/files/2014/02/120106730-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2014/02/120106730-730x420.jpg 730w, https://themify.me/demo/themes/event/files/2014/02/120106730-470x270.jpg 470w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/5-column-mixed-large-images/">5-Column Mixed With Large Images</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2241&#038;action=edit">Edit</a>] </article>
<article id="post-2233"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-7-columns/"><img src="https://themify.me/demo/themes/event/files/2013/06/128686832-350x200.jpg" width="350" height="200" alt="128686832" srcset="https://themify.me/demo/themes/event/files/2013/06/128686832-350x200.jpg 350w, https://themify.me/demo/themes/event/files/2013/06/128686832-470x270.jpg 470w" sizes="(max-width: 350px) 100vw, 350px" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-7-columns/">Gallery &#8211; 7 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2233&#038;action=edit">Edit</a>] </article>
<article id="post-2232"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/vintage/"><img src="https://themify.me/demo/themes/event/files/2013/06/53614087-350x200.jpg" width="350" height="200" alt="53614087" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/vintage/">Vintage</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=2232&#038;action=edit">Edit</a>] </article>
<article id="post-9"> <figure>
 <a href="https://themify.me/demo/themes/event/gallery/gallery-post/"><img src="https://themify.me/demo/themes/event/files/2014/03/106413309-350x200.jpg" width="350" height="200" alt="106413309" /></a> </figure>
 
 <h2><a href="https://themify.me/demo/themes/event/gallery/gallery-post/">Gallery Post 5 Columns</a> </h2> 
 
 [<a href="https://themify.me/demo/themes/event/wp-admin/post.php?post=9&#038;action=edit">Edit</a>] </article>
 
 <iframe src="https://player.vimeo.com/video/6929537" width="1165" height="655" title="&quot;Whale Song&quot; for Modest Mouse" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe> 
 <h3> Video From Vimeo </h3> You can embed any video here. 
 <h2>WordPress Gallery</h2><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/119002144.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/119002144-150x150.jpg" alt="" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/53217325.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/53217325-150x150.jpg" alt="" srcset="https://themify.me/demo/themes/event/files/2014/03/53217325-150x150.jpg 150w, https://themify.me/demo/themes/event/files/2014/03/53217325-250x250.jpg 250w" sizes="(max-width: 150px) 100vw, 150px" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/33733618.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/33733618-150x150.jpg" alt="" srcset="https://themify.me/demo/themes/event/files/2014/03/33733618-150x150.jpg 150w, https://themify.me/demo/themes/event/files/2014/03/33733618-250x250.jpg 250w" sizes="(max-width: 150px) 100vw, 150px" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/70502623.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/70502623-150x150.jpg" alt="" srcset="https://themify.me/demo/themes/event/files/2014/03/70502623-150x150.jpg 150w, https://themify.me/demo/themes/event/files/2014/03/70502623-250x250.jpg 250w" sizes="(max-width: 150px) 100vw, 150px" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/70561198.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/70561198-150x150.jpg" alt="" srcset="https://themify.me/demo/themes/event/files/2014/03/70561198-150x150.jpg 150w, https://themify.me/demo/themes/event/files/2014/03/70561198-250x250.jpg 250w" sizes="(max-width: 150px) 100vw, 150px" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/134602640.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/134602640-150x150.jpg" alt="" srcset="https://themify.me/demo/themes/event/files/2014/03/134602640-150x150.jpg 150w, https://themify.me/demo/themes/event/files/2014/03/134602640-250x250.jpg 250w" sizes="(max-width: 150px) 100vw, 150px" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/67488769.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/67488769-150x150.jpg" alt="" srcset="https://themify.me/demo/themes/event/files/2014/03/67488769-150x150.jpg 150w, https://themify.me/demo/themes/event/files/2014/03/67488769-250x250.jpg 250w" sizes="(max-width: 150px) 100vw, 150px" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/92694433.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/92694433-150x150.jpg" alt="" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/68044363.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/68044363-150x150.jpg" alt="" /></a> </figure><figure class=\'gallery-item\'> <a href=\'https://themify.me/demo/themes/event/files/2014/03/52607113.jpg\'><img width="150" height="150" src="https://themify.me/demo/themes/event/files/2014/03/52607113-150x150.jpg" alt="" srcset="https://themify.me/demo/themes/event/files/2014/03/52607113-150x150.jpg 150w, https://themify.me/demo/themes/event/files/2014/03/52607113-250x250.jpg 250w" sizes="(max-width: 150px) 100vw, 150px" /></a> </figure><!--/themify_builder_static-->',
  'post_title' => 'Demo 3',
  'post_excerpt' => '',
  'post_name' => 'demo-3',
  'post_modified' => '2017-10-28 15:00:22',
  'post_modified_gmt' => '2017-10-28 15:00:22',
  'post_content_filtered' => '',
  'post_parent' => 2256,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2338',
  'menu_order' => 1,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" auto=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"cid\\":\\"c17\\"}}]}],\\"styling\\":{\\"background_color\\":\\"000000\\",\\"font_color\\":\\"ffffff\\",\\"link_color\\":\\"ffffff\\",\\"custom_css_row\\":\\"fullwidth\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h2>Get This Theme Now<\\\\/h2>\\\\n<h3>Event theme is the perfect theme for entertainment and event sites. Use the Themify\\\\\\\\\\\'s drag &amp; drop Builder to create beautiful pages.<\\\\/h3>\\\\n<p> <\\\\/p>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"font_weight\\":\\"bold\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"xlarge\\",\\"buttons_style\\":\\"outline\\",\\"content_button\\":[{\\"label\\":\\"HOME\\",\\"link\\":\\"https:\\\\/\\\\/themify.me\\\\/themes\\\\/event\\",\\"link_options\\":\\"regular\\"},{\\"label\\":\\"BUY THEME\\",\\"link\\":\\"https:\\\\/\\\\/themify.me\\\\/themes\\\\/event\\",\\"link_options\\":\\"regular\\"}]}}]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/134602640.jpg\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_color\\":\\"000000_1.00\\",\\"font_color\\":\\"ffffff_1.00\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"d6faff_1.00\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right_unit\\":\\"%\\",\\"padding_bottom\\":\\"4\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Continuously Scrolling Slider<\\\\/h2>\\",\\"cid\\":\\"c39\\"}},{\\"mod_name\\":\\"slider\\",\\"mod_settings\\":{\\"layout_display_slider\\":\\"image\\",\\"blog_category_slider\\":\\"|single\\",\\"slider_category_slider\\":\\"0|multiple\\",\\"portfolio_category_slider\\":\\"|single\\",\\"testimonial_category_slider\\":\\"|single\\",\\"posts_per_page_slider\\":\\"6\\",\\"offset_slider\\":\\"4\\",\\"display_slider\\":\\"content\\",\\"img_content_slider\\":[{\\"img_url_slider\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/53217325.jpg\\",\\"img_title_slider\\":\\"Image One\\"},{\\"img_url_slider\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/52607113.jpg\\",\\"img_title_slider\\":\\"Image Two\\"},{\\"img_url_slider\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/33733618.jpg\\",\\"img_title_slider\\":\\"Image Three\\"},{\\"img_url_slider\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/70561198.jpg\\",\\"img_title_slider\\":\\"Image Four\\"},{\\"img_url_slider\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/70502623.jpg\\",\\"img_title_slider\\":\\"Image Five\\"},{\\"img_url_slider\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/67488769.jpg\\",\\"img_title_slider\\":\\"Image Six\\"}],\\"layout_slider\\":\\"slider-default\\",\\"img_w_slider\\":\\"200\\",\\"img_h_slider\\":\\"160\\",\\"visible_opt_slider\\":\\"5\\",\\"auto_scroll_opt_slider\\":\\"4\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"continuously\\",\\"pause_on_hover_slider\\":\\"resume\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"no\\",\\"show_arrow_slider\\":\\"no\\",\\"padding_top\\":\\"0\\",\\"padding_right\\":\\"0\\",\\"padding_bottom\\":\\"50\\",\\"padding_left\\":\\"0\\"}}]}],\\"styling\\":{\\"custom_css_row\\":\\"fullwidth\\",\\"background_color\\":\\"3aa9a5_1.00\\",\\"font_color\\":\\"ffffff_1.00\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"ffffff_1.00\\",\\"padding_top\\":\\"40\\",\\"padding_left\\":\\"40\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Gallery Posts<\\\\/h2><p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"8\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid4 slide-up\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"350\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"cid\\":\\"c54\\"}}]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/92694433.jpg\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_color\\":\\"000000_1.00\\",\\"font_color\\":\\"ffffff_1.00\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"ffffff_1.00\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right_unit\\":\\"%\\",\\"padding_bottom\\":\\"4\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"video\\",\\"mod_settings\\":{\\"style_video\\":\\"video-top\\",\\"url_video\\":\\"http:\\\\/\\\\/vimeo.com\\\\/6929537\\",\\"title_video\\":\\"Video From Vimeo\\",\\"caption_video\\":\\"You can embed any video here.\\"}}]}],\\"styling\\":{\\"background_color\\":\\"000000\\",\\"font_color\\":\\"ffffff\\",\\"link_color\\":\\"ffffff\\",\\"custom_css_row\\":\\"fullwidth\\"}},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>WordPress Gallery<\\\\/h2><p>[gallery columns=\\\\\\\\\\\\\\"5\\\\\\\\\\\\\\" link=\\\\\\\\\\\\\\"file\\\\\\\\\\\\\\" ids=\\\\\\\\\\\\\\"2399,2336,2333,2331,2332,2296,2329,2405,2330,2335\\\\\\\\\\\\\\"]<\\\\/p>\\",\\"cid\\":\\"c76\\"}}]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2013\\\\/06\\\\/83151367.jpg\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_color\\":\\"000000_1.00\\",\\"cover_color\\":\\"000000_0.52\\",\\"font_color\\":\\"ffffff_1.00\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"ffffff_1.00\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right_unit\\":\\"%\\",\\"padding_bottom\\":\\"4\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"6\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2409,
  'post_date' => '2014-03-25 21:34:07',
  'post_date_gmt' => '2014-03-25 21:34:07',
  'post_content' => '<!--themify_builder_static--><h2>Limitless Possibilities</h2> <h3>With Themify Builder, you can build unlimited layouts by dropping modules on the page&#8230;<br /><br />It comes with parallax scrolling and various animation effects</h3>
 
 <a href="https://themify.me/builder" > BUILDER </a> <a href="https://themify.me/themes/event" > BUY EVENT </a> 
 <h3>Watch the video below to see how this page can be built with Themify Builder.</h3>
 
 <iframe width="1165" height="655" src="https://www.youtube.com/embed/4noQ8bKxQ0k?feature=oembed" gesture="media" allowfullscreen></iframe> 
 
 <h3>Calendar Widget</h3><table id="wp-calendar"> <caption>October 2017</caption> <thead> <tr> <th scope="col" title="Monday">M</th> <th scope="col" title="Tuesday">T</th> <th scope="col" title="Wednesday">W</th> <th scope="col" title="Thursday">T</th> <th scope="col" title="Friday">F</th> <th scope="col" title="Saturday">S</th> <th scope="col" title="Sunday">S</th> </tr> </thead>
 <tfoot> <tr> <td colspan="3" id="prev"><a href="https://themify.me/demo/themes/event/2014/02/">&laquo; Feb</a></td> <td>&nbsp;</td> <td colspan="3" id="next">&nbsp;</td> </tr> </tfoot>
 <tbody> <tr> <td colspan="6">&nbsp;</td><td>1</td> </tr> <tr> <td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td><td>8</td> </tr> <tr> <td>9</td><td>10</td><td>11</td><td>12</td><td>13</td><td>14</td><td>15</td> </tr> <tr> <td>16</td><td>17</td><td>18</td><td>19</td><td>20</td><td>21</td><td>22</td> </tr> <tr> <td>23</td><td>24</td><td>25</td><td>26</td><td>27</td><td id="today">28</td><td>29</td> </tr> <tr> <td>30</td><td>31</td> <td colspan="5">&nbsp;</td> </tr> </tbody> </table> 
<ul> <li> <a href="http://facebook.com/themify">Facebook</a> </li> <li> <a href="http://twitter.com/themify">Twitter</a> </li> <li> <a href="http://instagram.com/themify">Instagram</a> </li> <li> <a href="https://www.youtube.com/user/themifyme">YouTube</a> </li></ul> <h3>Twitter Widget</h3><p>https://twitter.com/themify</p><!--/themify_builder_static-->',
  'post_title' => 'Demo 4',
  'post_excerpt' => '',
  'post_name' => 'demo-4',
  'post_modified' => '2017-10-28 15:18:56',
  'post_modified_gmt' => '2017-10-28 15:18:56',
  'post_content_filtered' => '',
  'post_parent' => 2256,
  'guid' => 'https://themify.me/demo/themes/event/?page_id=2409',
  'menu_order' => 1,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h2>Limitless Possibilities<\\\\/h2>\\\\n<h3>With Themify Builder, you can build unlimited layouts by dropping modules on the page...<br \\\\/><br \\\\/>It comes with parallax scrolling and various animation effects<\\\\/h3>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"font_weight\\":\\"bold\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"40\\",\\"margin_bottom\\":\\"40\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"link_color\\":\\"#ffffff\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"large\\",\\"buttons_style\\":\\"outline\\",\\"content_button\\":[{\\"label\\":\\"BUILDER\\",\\"link\\":\\"https:\\\\/\\\\/themify.me\\\\/builder\\",\\"link_options\\":\\"regular\\"},{\\"label\\":\\"BUY EVENT\\",\\"link\\":\\"https:\\\\/\\\\/themify.me\\\\/themes\\\\/event\\",\\"link_options\\":\\"regular\\"}]}}]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event\\\\/files\\\\/2014\\\\/03\\\\/53217325.jpg\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"background_color\\":\\"000000_1.00\\",\\"cover_color\\":\\"000000_0.57\\",\\"font_color\\":\\"ffffff_1.00\\",\\"text_align\\":\\"center\\",\\"link_color\\":\\"d6faff_1.00\\",\\"padding_top\\":\\"8\\",\\"padding_top_unit\\":\\"%\\",\\"padding_right_unit\\":\\"%\\",\\"padding_bottom\\":\\"6\\",\\"padding_bottom_unit\\":\\"%\\",\\"padding_left_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h3>Watch the video below to see how this page can be built with Themify Builder.<\\\\/h3>\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"margin_bottom\\":\\"30\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"cid\\":\\"c26\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-1\\",\\"grid_width\\":\\"17\\"},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"video\\",\\"mod_settings\\":{\\"style_video\\":\\"video-top\\",\\"url_video\\":\\"https:\\\\/\\\\/www.youtube.com\\\\/watch?v=4noQ8bKxQ0k\\",\\"autoplay_video\\":\\"no\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}}],\\"grid_width\\":\\"67\\"},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col4-1\\",\\"grid_width\\":\\"16\\"}],\\"gutter\\":\\"gutter-none\\"}]}],\\"styling\\":{\\"text_align\\":\\"center\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Blog Posts\\",\\"layout_post\\":\\"grid4\\",\\"category_post\\":\\"0|multiple\\",\\"post_per_page_post\\":\\"4\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"400\\",\\"img_height_post\\":\\"250\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"css_post\\":\\"fly-in\\"}}]}],\\"styling\\":{\\"background_color\\":\\"3a4647\\",\\"font_color\\":\\"ffffff\\",\\"link_color\\":\\"8abeff\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"widget\\",\\"mod_settings\\":{\\"mod_title_widget\\":\\"Calendar Widget\\",\\"class_widget\\":\\"WP_Widget_Calendar\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"widget\\",\\"mod_settings\\":{\\"mod_title_widget\\":\\"Social Widget\\",\\"class_widget\\":\\"Themify_Social_Links\\",\\"instance_widget\\":{\\"widget-themify-social-links[3\\":{\\"show_link_name\\":\\"on\\",\\"icon_size\\":\\"icon-large\\"}}}}]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"widget\\",\\"mod_settings\\":{\\"mod_title_widget\\":\\"Twitter Widget\\",\\"class_widget\\":\\"Themify_Twitter\\",\\"instance_widget\\":{\\"widget-themify-twitter[4][username]\\":\\"themify\\",\\"widget-themify-twitter[4][show_count]\\":\\"3\\",\\"widget-themify-twitter[4][show_follow]\\":\\"on\\",\\"widget-themify-twitter[4][follow_text]\\":\\"→ Follow me\\",\\"widget-themify-twitter[4][include_retweets]\\":\\"on\\"},\\"background_image-type\\":\\"image\\",\\"background_image-type_image\\":\\"image\\",\\"background_image-gradient-angle\\":\\"0\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"custom_parallax_scroll_reverse_reverse\\":\\"reverse\\",\\"custom_parallax_scroll_fade_fade\\":\\"fade\\",\\"visibility_desktop_hide\\":\\"hide\\",\\"visibility_tablet_hide\\":\\"hide\\",\\"visibility_mobile_hide\\":\\"hide\\"}}]}],\\"styling\\":{\\"animation_effect\\":\\"fly-in\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2216,
  'post_date' => '2014-03-04 19:49:26',
  'post_date_gmt' => '2014-03-04 19:49:26',
  'post_content' => '',
  'post_title' => 'Events',
  'post_excerpt' => '',
  'post_name' => 'events',
  'post_modified' => '2017-08-21 07:42:00',
  'post_modified_gmt' => '2017-08-21 07:42:00',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?page_id=2216',
  'menu_order' => 2,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Events</h2>\\\\n<h3>Fullwidth Event slider</h3>\\\\n<p>[themify_event_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" event_tab=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\\\n\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"000000\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"d4fffe\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"fullwidth\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Event Tabs</h2><h3>Event posts are automatically categorized in Upcoming and Past event tabs by the event date.</h3><p>[themify_event_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid4 fly-in\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"350\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"00c28e\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"e5fff8\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Events - Grid3 No Tabs</h2><p>[themify_event_posts limit=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid3 slide-up\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" event_tab=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"470\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"270\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"654c9e\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"dcccff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[themify_event_posts limit=\\\\\\\\\\\\\\"2\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid2 fade-in\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"730\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"420\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"ef008c\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffebf7\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2234,
  'post_date' => '2014-03-06 16:17:02',
  'post_date_gmt' => '2014-03-06 16:17:02',
  'post_content' => '',
  'post_title' => 'Galleries',
  'post_excerpt' => '',
  'post_name' => 'galleries',
  'post_modified' => '2017-08-21 07:42:08',
  'post_modified_gmt' => '2017-08-21 07:42:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?page_id=2234',
  'menu_order' => 3,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Gallery - Slider</h2>\\\\n<h3>The Gallery post type can be queried in standard WordPress static page or by using the built-in shortcodes</h3>\\\\n<p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\\\n\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"000000\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"fullwidth\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Gallery - Grid4</h2><p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"8\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid4 fly-in\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"350\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event/files/2014/03/sb10068474al-001.jpg\\",\\"background_color\\":\\"000000\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Gallery - Grid3</h2><p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"6\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid3 slide-up\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"470\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"270\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event/files/2012/09/63092077.jpg\\",\\"background_color\\":\\"000000\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Gallery - Grid2</h2><p>[themify_gallery_posts limit=\\\\\\\\\\\\\\"2\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid2 fade-in\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" image_w=\\\\\\\\\\\\\\"730\\\\\\\\\\\\\\" image_h=\\\\\\\\\\\\\\"420\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event/files/2013/06/129025022.jpg\\",\\"background_color\\":\\"f0ecd3\\",\\"background_repeat\\":\\"builder-parallax-scrolling\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"593c3c\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"4d0600\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2237,
  'post_date' => '2014-03-06 23:38:09',
  'post_date_gmt' => '2014-03-06 23:38:09',
  'post_content' => '',
  'post_title' => 'Videos',
  'post_excerpt' => '',
  'post_name' => 'videos',
  'post_modified' => '2017-08-21 07:42:17',
  'post_modified_gmt' => '2017-08-21 07:42:17',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?page_id=2237',
  'menu_order' => 4,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Video - Slider</h2><h3>The Video post type can be queried in standard WordPress static page or by using the built-in shortcodes</h3><p>[themify_video_posts limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"slider\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"\\",\\"background_color\\":\\"9ce0ed\\",\\"background_repeat\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"000000\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"000000\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Video - Grid4</h2><p>[themify_video_posts limit=\\\\\\\\\\\\\\"8\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid4 fly-in\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event/files/2013/01/121914223.jpg\\",\\"background_color\\":\\"000000\\",\\"background_repeat\\":\\"fullcover\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Video - Grid3</h2><p>[themify_video_posts limit=\\\\\\\\\\\\\\"6\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid3 slide-up\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event/files/2012/09/130056410.jpg\\",\\"background_color\\":\\"c4f5ea\\",\\"background_repeat\\":\\"fullcover\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"000000\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"000000\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Video - Grid2</h2><p>[themify_video_posts limit=\\\\\\\\\\\\\\"2\\\\\\\\\\\\\\" style=\\\\\\\\\\\\\\"grid2 fade-in\\\\\\\\\\\\\\" post_meta=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_date=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\" post_stats=\\\\\\\\\\\\\\"no\\\\\\\\\\\\\\"]</p>\\",\\"font_family\\":\\"default\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"background_image\\":\\"https://themify.me/demo/themes/event/files/2013/06/83151367.jpg\\",\\"background_color\\":\\"000000\\",\\"background_repeat\\":\\"fullcover\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"ffffff\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"link_color\\":\\"ffffff\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_right\\":\\"\\",\\"padding_bottom\\":\\"\\",\\"padding_left\\":\\"\\",\\"margin_top\\":\\"\\",\\"margin_right\\":\\"\\",\\"margin_bottom\\":\\"\\",\\"margin_left\\":\\"\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2207,
  'post_date' => '2014-02-28 18:45:43',
  'post_date_gmt' => '2014-02-28 18:45:43',
  'post_content' => '',
  'post_title' => 'Blog',
  'post_excerpt' => '',
  'post_name' => 'blog',
  'post_modified' => '2017-08-21 07:43:37',
  'post_modified_gmt' => '2017-08-21 07:43:37',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?page_id=2207',
  'menu_order' => 5,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'posts_per_page' => '5',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'event_display' => 'upcoming',
    'event_order' => 'desc',
    'event_orderby' => 'meta_value',
    'event_layout' => 'list-post',
    'event_display_content' => 'content',
    'event_hide_title' => 'default',
    'event_unlink_title' => 'default',
    'event_hide_date' => 'default',
    'event_hide_meta_all' => 'default',
    'event_hide_image' => 'default',
    'event_unlink_image' => 'default',
    'event_hide_navigation' => 'default',
    'event_hide_event_location' => 'default',
    'event_hide_event_date' => 'default',
    'video_order' => 'desc',
    'video_orderby' => 'date',
    'video_layout' => 'list-post',
    'video_display_content' => 'content',
    'video_hide_title' => 'default',
    'video_unlink_title' => 'default',
    'video_hide_date' => 'default',
    'video_hide_meta_all' => 'default',
    'video_hide_image' => 'default',
    'video_unlink_image' => 'default',
    'video_hide_navigation' => 'default',
    'gallery_order' => 'desc',
    'gallery_orderby' => 'date',
    'gallery_layout' => 'list-post',
    'gallery_display_content' => 'content',
    'gallery_hide_title' => 'default',
    'gallery_unlink_title' => 'default',
    'gallery_hide_date' => 'default',
    'gallery_hide_meta_all' => 'default',
    'gallery_hide_image' => 'default',
    'gallery_unlink_image' => 'default',
    'gallery_hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2400,
  'post_date' => '2014-03-25 01:56:21',
  'post_date_gmt' => '2014-03-25 01:56:21',
  'post_content' => 'Duis ac quam leo. Phasellus malesuada, leo at lobortis egestas, felis lorem condimentum lacus, id iaculis quam urna ac urna. Vivamus aliquam laoreet semper. Ut lacinia sem nisi. Sed vulputate convallis odio posuere porttitor. Aliquam turpis felis, faucibus non orci vel, laoreet dapibus diam. Nam rhoncus tortor velit, sollicitudin semper nisl pulvinar sed.',
  'post_title' => 'Sunday Lounge',
  'post_excerpt' => '',
  'post_name' => 'sunday-lounge',
  'post_modified' => '2017-08-21 07:49:05',
  'post_modified_gmt' => '2017-08-21 07:49:05',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2400',
  'menu_order' => 1,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-04-24 14:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2224,
  'post_date' => '2014-03-25 13:24:44',
  'post_date_gmt' => '2014-03-25 13:24:44',
  'post_content' => 'Aliquam nunc diam, volutpat eu mattis quis, tincidunt nec enim. Maecenas at posuere nisl, at semper quam. Curabitur eleifend risus nunc, elementum cursus tortor ullamcorper sit amet. Nullam aliquam ligula porttitor urna fermentum. Curabitur pulvinar ante id nulla facilisis, sit amet viverra risus interdum. Vestibulum tincidunt in diam at viverra. Pellentesque consequat metus odio, a egestas odio accumsan non. Sed id orci egestas, accumsan tellus ac, sodales ante. Duis facilisis semper euismod. Integer quis massa diam. Pellentesque sit amet lorem eget nibh dictum vut viverra. Pellentesque consequat metus odio, a egestas odio accumsan non.

Aliquam nunc diam, volutpat eu mattis quis, tincidunt nec enim. Maecenas at posuere nisl, at semper quam. Curabitur eleifend risus nunc, elementum cursus tortor ullamcorper sit amet. Nullam aliquam ligula porttitor urna fermentum. Curabitur pulvinar ante id nulla facilisis, sit amet viverra risus interdum. Aliquam nunc diam, volutpat eu mattis quis, tincidunt nec enim. Maecenas at posuere nisl, at semper quam. Curabitur eleifend risus nunc, elementum cursus tortor ullamcorper sit amet. Nullam aliquam ligula porttitor urna fermentum. Curabitur pulvinar ante id nulla facilisis, sit amet viverra risus interdum.',
  'post_title' => 'Lightbox Showcase',
  'post_excerpt' => '',
  'post_name' => 'lightbox-showcase',
  'post_modified' => '2017-08-21 07:49:03',
  'post_modified_gmt' => '2017-08-21 07:49:03',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=event&#038;p=2224',
  'menu_order' => 2,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-10-07 22:00',
    'location' => 'Québec',
    'map_address' => 'Hôtel de Glace',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2367,
  'post_date' => '2014-03-25 01:30:03',
  'post_date_gmt' => '2014-03-25 01:30:03',
  'post_content' => 'Morbi et augue a massa convallis imperdiet fermentum sit amet lacus. Curabitur nunc odio, dictum non ipsum eget, tempus ullamcorper augue. Maecenas faucibus ante ligula, non mattis risus cursus vel. Suspendisse faucibus ipsum sit amet feugiat congue. Nunc feugiat ut arcu eu placerat. Ut vestibulum eleifend nisi. Integer non cursus erat. Vivamus non posuere mi.',
  'post_title' => 'Clementine - Live Session',
  'post_excerpt' => '',
  'post_name' => 'clementine-live-session',
  'post_modified' => '2017-08-21 07:49:07',
  'post_modified_gmt' => '2017-08-21 07:49:07',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2367',
  'menu_order' => 3,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-11-27 20:00',
    'end_date' => '2015-11-27 22:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2398,
  'post_date' => '2014-03-25 01:20:35',
  'post_date_gmt' => '2014-03-25 01:20:35',
  'post_content' => 'Aliquam vitae cursus lectus, eget adipiscing ligula. Mauris non odio sit amet sapien dictum ullamcorper. Phasellus gravida venenatis felis ac sagittis. In a interdum velit. Nam pharetra tortor imperdiet, vehicula lacus nec, tincidunt mauris. Aenean in sem a odio ultrices consequat ac sit amet velit.',
  'post_title' => 'Dress Code',
  'post_excerpt' => '',
  'post_name' => 'dress-code',
  'post_modified' => '2017-08-21 07:49:09',
  'post_modified_gmt' => '2017-08-21 07:49:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2398',
  'menu_order' => 4,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-09-30 22:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2373,
  'post_date' => '2014-03-24 22:19:26',
  'post_date_gmt' => '2014-03-24 22:19:26',
  'post_content' => 'Etiam et urna est. Vivamus posuere nisi vel ligula ultrices, id dignissim turpis hendrerit. Duis velit tortor, dictum quis tincidunt sed, scelerisque id felis. Ut auctor interdum neque, ut molestie lectus commodo non. Integer at nulla tortor. Aliquam volutpat blandit nunc at porta. Ut feugiat ac est vitae consequat.',
  'post_title' => 'Scratch Competition',
  'post_excerpt' => '',
  'post_name' => 'scratch-competition',
  'post_modified' => '2017-08-21 07:49:11',
  'post_modified_gmt' => '2017-08-21 07:49:11',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2373',
  'menu_order' => 5,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-11-14 21:00',
    'end_date' => '2015-11-15 04:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2372,
  'post_date' => '2014-03-24 21:57:52',
  'post_date_gmt' => '2014-03-24 21:57:52',
  'post_content' => 'Donec elementum blandit accumsan. Morbi eget massa at nulla luctus bibendum. Vivamus et quam sit amet justo ullamcorper pulvinar ut vitae tellus. Suspendisse turpis turpis, condimentum nec placerat in, rhoncus sed augue. Vivamus ut ante urna. Nullam vel nisi arcu. Aenean faucibus porttitor nisl, in ullamcorper odio egestas quis. Quisque varius eros nec vulputate pulvinar.',
  'post_title' => 'The Creative',
  'post_excerpt' => '',
  'post_name' => 'the-creative',
  'post_modified' => '2017-08-21 07:49:13',
  'post_modified_gmt' => '2017-08-21 07:49:13',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2372',
  'menu_order' => 6,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-11-21 22:00',
    'end_date' => '2015-11-22 03:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2369,
  'post_date' => '2014-03-24 21:27:06',
  'post_date_gmt' => '2014-03-24 21:27:06',
  'post_content' => 'Etiam odio tortor, tempor eu placerat et, posuere sit amet diam. Sed id arcu ac felis iaculis sagittis. Sed elementum mauris sed molestie suscipit. Nam nec sollicitudin ipsum. Nunc diam augue, congue ac est sed, aliquam ultrices nulla. Maecenas scelerisque rutrum ultrices. Nulla laoreet varius tempus.',
  'post_title' => '"Air" Guitar',
  'post_excerpt' => '',
  'post_name' => 'air-guitar',
  'post_modified' => '2017-08-21 07:49:18',
  'post_modified_gmt' => '2017-08-21 07:49:18',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2369',
  'menu_order' => 7,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-11-20 18:00',
    'end_date' => '2015-11-20 21:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2319,
  'post_date' => '2014-03-24 14:19:15',
  'post_date_gmt' => '2014-03-24 14:19:15',
  'post_content' => 'Curabitur malesuada ipsum eget leo imperdiet, id pulvinar ipsum ullamcorper. Curabitur dignissim malesuada consectetur. Praesent consequat mauris nisi, sit amet consequat ligula vestibulum sed. In tempus, odio non gravida pharetra, massa quam elementum mi, a ultricies nisi diam congue diam. Aliquam iaculis dapibus congue. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Morbi pharetra luctus nunc, sed blandit tellus accumsan nec.
Curabitur a sagittis quam. Duis sed tellus orci. In ut tortor non nibh tristique pharetra in id enim. Cras rhoncus posuere rhoncus. Etiam sodales faucibus nisi, malesuada pulvinar felis consequat ut. In ac aliquet dolor. In hac habitasse platea dictumst. Nunc eu nisi nisi.',
  'post_title' => 'The After Party',
  'post_excerpt' => '',
  'post_name' => 'the-after-party',
  'post_modified' => '2017-08-21 07:49:19',
  'post_modified_gmt' => '2017-08-21 07:49:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2319',
  'menu_order' => 8,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-12-05 03:00',
    'end_date' => '2015-12-06 07:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and St. Claire, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2317,
  'post_date' => '2014-03-24 13:54:34',
  'post_date_gmt' => '2014-03-24 13:54:34',
  'post_content' => 'Vestibulum sed consectetur quam, ut pretium urna. Quisque venenatis, justo ac aliquet viverra, velit ligula consequat nulla, vitae porta turpis risus varius neque. Nunc aliquam sem vestibulum, bibendum turpis vitae, convallis urna. Etiam varius tellus turpis, at cursus purus convallis non. Nunc congue, odio et fringilla luctus, purus lacus sodales magna, quis sagittis est arcu et libero. Pellentesque sagittis risus et tellus molestie tristique. Praesent ante magna, congue sed ultricies et, commodo eu leo.',
  'post_title' => 'Cabaret',
  'post_excerpt' => '',
  'post_name' => 'cabaret',
  'post_modified' => '2017-08-21 07:49:20',
  'post_modified_gmt' => '2017-08-21 07:49:20',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2317',
  'menu_order' => 9,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-12-11 17:00',
    'end_date' => '2015-12-11 18:00',
    'location' => 'Broadway Theaters',
    'map_address' => '254 West 54th Street, New York, NY 10019',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2316,
  'post_date' => '2014-03-24 13:48:47',
  'post_date_gmt' => '2014-03-24 13:48:47',
  'post_content' => 'Fusce viverra imperdiet nisl. In non fermentum ante. Duis et tortor ut dui eleifend viverra. Sed sagittis convallis ligula, eget lobortis quam sagittis nec. Proin ultricies ante lorem, quis malesuada justo rhoncus molestie. Morbi sollicitudin bibendum mauris at auctor. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.

Nam massa risus, dictum eget ligula a, hendrerit luctus orci. Praesent venenatis sollicitudin euismod.',
  'post_title' => 'DJ Pandora',
  'post_excerpt' => '',
  'post_name' => 'dj-pandora',
  'post_modified' => '2017-08-21 07:49:22',
  'post_modified_gmt' => '2017-08-21 07:49:22',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2316',
  'menu_order' => 10,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-12-04 22:00',
    'end_date' => '2015-12-05 06:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2315,
  'post_date' => '2014-03-24 13:36:51',
  'post_date_gmt' => '2014-03-24 13:36:51',
  'post_content' => 'Nunc gravida scelerisque nibh, quis luctus elit euismod vehicula. Phasellus fermentum, erat ac porta pretium, ante est vulputate risus, pellentesque ultrices odio enim ac dui. Sed ut nulla iaculis, mollis nulla non, ornare tortor. Sed ut pellentesque tortor. Curabitur rhoncus dictum libero, vitae rhoncus ligula. Aenean malesuada velit eu dui egestas porttitor. Fusce ac dictum purus. Praesent condimentum purus a metus convallis, a pretium nunc facilisis. Praesent elementum sem vestibulum, pellentesque risus nec, molestie ante.',
  'post_title' => 'Fashion Days',
  'post_excerpt' => '',
  'post_name' => 'fashion-days',
  'post_modified' => '2017-08-21 07:49:27',
  'post_modified_gmt' => '2017-08-21 07:49:27',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2315',
  'menu_order' => 11,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-12-25 17:00',
    'end_date' => '2015-12-25 23:00',
    'location' => 'Hilton Toronto',
    'map_address' => '145 Richmond St W, Toronto, ON M5H 2L2, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2313,
  'post_date' => '2014-03-24 13:28:27',
  'post_date_gmt' => '2014-03-24 13:28:27',
  'post_content' => 'Sed ut rhoncus massa, quis imperdiet ante. Suspendisse facilisis iaculis turpis, semper vestibulum magna posuere a. Sed tempus lacinia gravida. Vivamus libero ligula, pharetra et tellus id, consequat placerat diam. Ut dignissim, nunc id interdum porta, felis magna consectetur nisl, quis auctor lectus turpis sed sapien.
Proin sed justo lorem. Donec lacinia enim eu velit aliquam sagittis aliquet at ligula. Integer eu volutpat mi. Nam at sapien lectus. Proin gravida lectus nec massa congue rutrum. Pellentesque scelerisque lectus nunc, sit amet auctor ipsum rutrum vel. Vivamus venenatis mi diam, vitae tristique tortor tincidunt at. Donec orci velit, lobortis quis euismod at, accumsan non nisi.',
  'post_title' => 'Saturday Lights',
  'post_excerpt' => '',
  'post_name' => 'saturday-lights',
  'post_modified' => '2017-08-21 07:49:30',
  'post_modified_gmt' => '2017-08-21 07:49:30',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2313',
  'menu_order' => 12,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-12-19 22:00',
    'end_date' => '2015-12-20 07:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2312,
  'post_date' => '2014-03-24 13:25:34',
  'post_date_gmt' => '2014-03-24 13:25:34',
  'post_content' => 'Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Nunc ut lacus in nibh vulputate feugiat sit amet non nulla. Praesent faucibus bibendum erat non malesuada. Maecenas quis est nibh. Maecenas placerat feugiat adipiscing. Sed vitae euismod diam. Proin feugiat egestas turpis, sed facilisis est egestas non.

Nullam consequat placerat turpis. Sed nulla tellus, pulvinar sed lobortis nec, suscipit quis purus. Duis sollicitudin elit ut justo scelerisque suscipit eget eget augue. Praesent id quam in ante interdum gravida. Donec sollicitudin auctor ipsum, varius congue leo rutrum at. Donec fermentum a magna quis gravida. Etiam sed libero a turpis mattis aliquam. Nunc pretium fermentum aliquam.',
  'post_title' => 'Weekend Warmup',
  'post_excerpt' => '',
  'post_name' => 'weekend-warmup',
  'post_modified' => '2017-08-21 07:49:33',
  'post_modified_gmt' => '2017-08-21 07:49:33',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2312',
  'menu_order' => 13,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2016-12-23 22:00',
    'end_date' => '2016-12-24 04:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2212,
  'post_date' => '2014-03-24 13:23:16',
  'post_date_gmt' => '2014-03-24 13:23:16',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras ut libero nisi. Pellentesque luctus purus sed pellentesque dapibus. Cras ut mattis nibh. In porttitor vestibulum enim eget condimentum. Vestibulum in varius quam. Nullam eu ante nec leo egestas malesuada iaculis nec augue.',
  'post_title' => 'Create Your Mix',
  'post_excerpt' => '',
  'post_name' => 'main-event',
  'post_modified' => '2017-08-21 07:49:35',
  'post_modified_gmt' => '2017-08-21 07:49:35',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=event&#038;p=2212',
  'menu_order' => 14,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2015-11-19 22:10',
    'end_date' => '2015-11-20 17:00',
    'location' => 'Toronto',
    'map_address' => 'Toronto, ON, Canada',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2229,
  'post_date' => '2014-03-04 22:17:09',
  'post_date_gmt' => '2014-03-04 22:17:09',
  'post_content' => 'Curabitur pulvinar ante id nulla facilisis, sit amet viverra risus interdum. Vestibulum tincidunt in diam at viverra. Pellentesque consequat metus odio, a egestas odio accumsan non. Sed id orci egestas, accumsan tellus ac, sodales ante. Duis facilisis semper euismod. Integer quis massa diam. Pellentesque sit amet lorem eget nibh dictum vut viverra. Pellentesque consequat metus odio, a egestas odio accumsan non. Sed id orci egestas, accumsan tellus ac, sodales ante. Duis facilisis semper euismod. Integer quis massa diam. Pellentesque sit amet lorem eget nibh dictum vulput lputate.<!--more-->

Tstibulum tincidunt in diam at viverra. Pellentesque consequat metus odio, a egestas odio accumsan non. Sed id orci egestas, accumsan tellus ac, sodales ante. Duis facilisis semper euismod. Integer quis massa diam. Pellentesque sit amet lorem eget nibh dictum vut viverra. Pellentesque consequat metus odio, a egestas odio accumsan non.

Curabitur pulvinar ante id nulla facilisis, sit amet viverra risus interdum. Vestibulum tincidunt in diam at viverra. Pellentesque consequat metus odio, a egestas odio accumsan non. Sed id orci egestas, accumsan tellus ac, sodales ante. Duis facilisis semper euismod. Integer quis massa diam. Pellentesque sit amet lorem eget nibh dictum vut viverra. Pellentesque consequat metus odio, a egestas odio accumsan non.',
  'post_title' => 'Butterfly Party',
  'post_excerpt' => '',
  'post_name' => 'butterfly-party',
  'post_modified' => '2017-08-21 07:49:36',
  'post_modified_gmt' => '2017-08-21 07:49:36',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=event&#038;p=2229',
  'menu_order' => 15,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-02-07 22:00',
    'end_date' => '2014-02-08 05:00',
    'location' => 'Googleplex',
    'map_address' => '1600 Amphitheatre Pkwy
Mountain View, CA 94043
United States',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2218,
  'post_date' => '2014-03-04 21:00:26',
  'post_date_gmt' => '2014-03-04 21:00:26',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras turpis augue, ullamcorper non mollis ac, suscipit vel orci. Nullam condimentum enim vel auctor condimentum. Donec ut magna varius, lacinia libero gravida, sagittis nulla. Maecenas eu ultrices eros, eget ornare mauris. Aliquam in congue felis, at vestibulum felis. Phasellus vel erat vel diam rutrum euismod nec vel quam. Etiam consequat dictum velit ac ultricies.',
  'post_title' => 'Past Event',
  'post_excerpt' => '',
  'post_name' => 'past-event',
  'post_modified' => '2017-08-21 07:49:39',
  'post_modified_gmt' => '2017-08-21 07:49:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=event&#038;p=2218',
  'menu_order' => 16,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-02-07 12:00',
    'end_date' => '2014-03-08 12:00',
    'location' => 'HILTON HOTEL',
    'map_address' => '1600 Amphitheatre Pkwy, Mountain View
CA 94043, United States',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2222,
  'post_date' => '2014-03-04 20:38:23',
  'post_date_gmt' => '2014-03-04 20:38:23',
  'post_content' => 'Vestibulum tincidunt in diam at viverra. Pellentesque consequat metus odio, a egestas odio accumsan non. Sed id orci egestas, accumsan tellus ac, sodales ante. Duis facilisis semper euismod. Integer quis massa diam. Pellentesque sit amet lorem eget nibh dictum vulputate. Aliquam nunc diam, volutpat eu mattis quis, tincidunt nec enim. Maecenas at posuere nisl, at semper quam. Curabitur eleifend risus nunc, elementum cursus tortor ullamcorper sit amet. Nullam aliquam ligula porttitor urna fermentum. Curabitur pulvinar ante id nulla facilisis, sit amet viverra risus interdum.',
  'post_title' => 'Fun Time!',
  'post_excerpt' => '',
  'post_name' => 'fun-time',
  'post_modified' => '2017-08-21 07:49:40',
  'post_modified_gmt' => '2017-08-21 07:49:40',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=event&#038;p=2222',
  'menu_order' => 17,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-03-01 18:00',
    'end_date' => '2014-03-02 04:00',
    'location' => 'New York',
    'map_address' => 'West 57 Street, New York, NY, United States',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2220,
  'post_date' => '2014-03-04 20:18:41',
  'post_date_gmt' => '2014-03-04 20:18:41',
  'post_content' => 'Vestibulum purus metus, dignissim vel elementum a, faucibus volutpat urna. Integer eu libero et mi sodales ultrices. Integer at lorem suscipit, tempor urna sit amet, scelerisque urna. Nullam tellus dolor, pharetra vel ipsum id, dignissim ultrices purus.',
  'post_title' => 'Car Show',
  'post_excerpt' => '',
  'post_name' => 'car-show',
  'post_modified' => '2017-08-21 07:49:41',
  'post_modified_gmt' => '2017-08-21 07:49:41',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=event&#038;p=2220',
  'menu_order' => 18,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-02-01 16:00',
    'end_date' => '2014-02-01 22:00',
    'location' => 'New York',
    'map_address' => 'Times Square, New York, NY, United States',
    'buy_tickets' => 'https://themify.me',
    'lightbox_icon' => 'on',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2394,
  'post_date' => '2014-02-25 01:15:02',
  'post_date_gmt' => '2014-02-25 01:15:02',
  'post_content' => 'Mauris et est tincidunt, tincidunt sapien sed, ultricies nulla. Duis ligula sapien, dictum in ipsum in, hendrerit pulvinar lorem. Pellentesque tincidunt tristique arcu blandit ornare. Nulla aliquam nunc viverra erat vehicula, nec facilisis enim viverra. Proin mattis erat non purus ultricies lobortis. Integer non justo sit amet libero ultricies laoreet. Quisque non posuere dolor.',
  'post_title' => 'The Nightlife',
  'post_excerpt' => '',
  'post_name' => 'nightlife',
  'post_modified' => '2017-08-21 07:49:43',
  'post_modified_gmt' => '2017-08-21 07:49:43',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2394',
  'menu_order' => 19,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-02-25 23:00',
    'end_date' => '2014-02-26 07:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2396,
  'post_date' => '2014-02-05 01:18:24',
  'post_date_gmt' => '2014-02-05 01:18:24',
  'post_content' => 'Aliquam eu purus non odio ullamcorper placerat vel et diam. Maecenas non urna in tortor mattis interdum. Donec tincidunt enim a enim convallis imperdiet. In ut lectus viverra, sollicitudin felis eget, dapibus quam. Quisque bibendum ullamcorper augue in gravida. Maecenas sagittis fringilla augue sit amet sollicitudin.',
  'post_title' => 'DJ Frank',
  'post_excerpt' => '',
  'post_name' => 'dj-frank',
  'post_modified' => '2017-08-21 07:49:46',
  'post_modified_gmt' => '2017-08-21 07:49:46',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2396',
  'menu_order' => 20,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-02-05 22:00',
    'end_date' => '2014-02-06 05:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2404,
  'post_date' => '2014-01-16 02:06:18',
  'post_date_gmt' => '2014-01-16 02:06:18',
  'post_content' => 'Sed vehicula erat non ipsum tincidunt blandit. Nullam mollis luctus odio, non gravida ligula. Praesent non bibendum nunc. Etiam ante nunc, commodo ut volutpat a, rhoncus sit amet tortor. Duis ut auctor sem, eu porta enim. Praesent felis nisi, commodo at elementum eu, sodales non tellus. Duis massa risus, vehicula ac lacus vitae, tempor vestibulum elit. Pellentesque pretium feugiat nulla.',
  'post_title' => 'The Fall - New Album Release',
  'post_excerpt' => '',
  'post_name' => 'the-fall-new-album-release',
  'post_modified' => '2017-08-21 07:49:48',
  'post_modified_gmt' => '2017-08-21 07:49:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2404',
  'menu_order' => 21,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-01-17 22:00',
    'end_date' => '2014-01-18 04:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2402,
  'post_date' => '2014-01-02 02:03:16',
  'post_date_gmt' => '2014-01-02 02:03:16',
  'post_content' => 'Cras placerat venenatis ligula, a venenatis nisi lobortis in. Nam ultrices sem turpis, et tincidunt ligula faucibus id. Aenean ac arcu non ipsum iaculis pulvinar. Morbi varius rhoncus leo, et facilisis dolor ullamcorper vel. Mauris hendrerit sapien sit amet quam semper congue. Integer nibh libero, volutpat sed nibh non, venenatis eleifend nulla.',
  'post_title' => 'Alice - New Album Release',
  'post_excerpt' => '',
  'post_name' => 'alice-new-album-release',
  'post_modified' => '2017-08-21 07:49:50',
  'post_modified_gmt' => '2017-08-21 07:49:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=event&#038;p=2402',
  'menu_order' => 22,
  'post_type' => 'event',
  'meta_input' => 
  array (
    'layout' => 'default',
    'start_date' => '2014-01-03 22:00',
    'end_date' => '2014-01-04 01:00',
    'location' => 'Eight Day Club',
    'map_address' => 'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada',
    'buy_tickets' => 'https://themify.me',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'event-category' => 'events',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 9,
  'post_date' => '2014-02-24 23:59:22',
  'post_date_gmt' => '2014-02-24 23:59:22',
  'post_content' => '',
  'post_title' => 'Gallery Post 5 Columns',
  'post_excerpt' => '',
  'post_name' => 'gallery-post',
  'post_modified' => '2017-08-21 07:50:51',
  'post_modified_gmt' => '2017-08-21 07:50:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=gallery&#038;p=9',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery link="file" columns="5" size="medium" ids="10,11,12,13,14,16,17,18,19"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2241,
  'post_date' => '2014-03-08 00:05:21',
  'post_date_gmt' => '2014-03-08 00:05:21',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed posuere neque sed tincidunt sagittis. Quisque convallis bibendum urna, quis dictum magna mollis ac. Nulla non malesuada nisl. Ut iaculis odio non vulputate mollis. Donec lacinia at velit nec volutpat. Pellentesque egestas dignissim imperdiet. Aenean eget risus a elit elementum tincidunt in vel magna. Donec pretium convallis metus a pharetra. Fusce diam ligula, ultricies imperdiet ligula volutpat, convallis elementum eros. Duis nisi justo, eleifend a mi sit amet, fringilla ultrices neque. Donec vulputate sapien egestas, viverra tellus eu, tincidunt felis. Sed malesuada et neque vitae sagittis. Aenean quis mi congue, ultricies lorem sit amet, viverra mi. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque in facilisis sapien.',
  'post_title' => '5-Column Mixed With Large Images',
  'post_excerpt' => '',
  'post_name' => '5-column-mixed-large-images',
  'post_modified' => '2017-08-21 07:50:42',
  'post_modified_gmt' => '2017-08-21 07:50:42',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=gallery&#038;p=2241',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery link="file" columns="5" size="medium" ids="16,18,1812,2267,2223,1803,2354,2363,2353,2366,2356,17"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2232,
  'post_date' => '2014-03-06 16:12:52',
  'post_date_gmt' => '2014-03-06 16:12:52',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed bibendum consectetur magna, a ultrices nibh vulputate eu. Nunc interdum eros non consectetur dapibus. Morbi vitae eros convallis, posuere nulla eget, accumsan leo. Quisque ut ante feugiat, volutpat diam at, lobortis metus. Pellentesque eget est id nunc aliquam sollicitudin quis sit amet justo. Nulla suscipit iaculis tellus, id aliquam ipsum bibendum vel. Nam aliquam libero quis arcu egestas, et interdum nisi mattis. Nam cursus tempus orci, vel cursus ligula porta at. Integer quis aliquet eros. Morbi tempor lacus nec pellentesque iaculis. Donec quis est vel risus mattis ultricies elementum vitae metus.',
  'post_title' => 'Vintage',
  'post_excerpt' => '',
  'post_name' => 'vintage',
  'post_modified' => '2017-08-21 07:50:47',
  'post_modified_gmt' => '2017-08-21 07:50:47',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=gallery&#038;p=2232',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery link="file" size="full" ids="36,41,42,43,37,38,35,40"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2233,
  'post_date' => '2014-03-06 16:15:04',
  'post_date_gmt' => '2014-03-06 16:15:04',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed bibendum consectetur magna, a ultrices nibh vulputate eu. Nunc interdum eros non consectetur dapibus. Morbi vitae eros convallis, posuere nulla eget, accumsan leo. Quisque ut ante feugiat, volutpat diam at, lobortis metus. Pellentesque eget est id nunc aliquam sollicitudin quis sit amet justo. Nulla suscipit iaculis tellus, id aliquam ipsum bibendum vel. Nam aliquam libero quis arcu egestas, et interdum nisi mattis. Nam cursus tempus orci, vel cursus ligula porta at. Integer quis aliquet eros. Morbi tempor lacus nec pellentesque iaculis. Donec quis est vel risus mattis ultricies elementum vitae metus.',
  'post_title' => 'Gallery - 7 Columns',
  'post_excerpt' => '',
  'post_name' => 'gallery-7-columns',
  'post_modified' => '2017-08-21 07:50:45',
  'post_modified_gmt' => '2017-08-21 07:50:45',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=gallery&#038;p=2233',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery link="file" columns="7" size="medium" ids="1858,1853,1850,33,1671,1773,2370,2230,2225,1866,1826,1824,1820,1861"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2326,
  'post_date' => '2014-03-24 20:24:35',
  'post_date_gmt' => '2014-03-24 20:24:35',
  'post_content' => 'Phasellus volutpat velit sapien, vel hendrerit massa rhoncus eu. Sed congue dui quis dui congue aliquet. Donec dictum consequat felis nec ullamcorper. Aenean sed augue tincidunt, faucibus augue in, commodo ante. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.',
  'post_title' => 'Gallery - 3 Columns',
  'post_excerpt' => '',
  'post_name' => 'gallery-3-columns',
  'post_modified' => '2017-08-21 07:50:41',
  'post_modified_gmt' => '2017-08-21 07:50:41',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=gallery&#038;p=2326',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery link="file" size="full" ids="2329,2331,2332,2333,2335,2336" orderby="rand"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2340,
  'post_date' => '2014-03-24 20:35:09',
  'post_date_gmt' => '2014-03-24 20:35:09',
  'post_content' => 'Suspendisse id augue lacus. Donec velit erat, ullamcorper euismod urna at, pretium ultrices purus. Fusce ut augue fermentum nisl suscipit adipiscing. Vivamus euismod augue vel turpis sodales ultricies. Sed et risus bibendum, vulputate libero eget, molestie massa. Fusce vulputate quis turpis sit amet porttitor. Nunc semper mollis velit, in molestie lectus rhoncus non. Sed tincidunt quam eget faucibus scelerisque.',
  'post_title' => 'Gallery - 2 Columns',
  'post_excerpt' => '',
  'post_name' => 'gallery-2-columns',
  'post_modified' => '2017-08-21 07:50:40',
  'post_modified_gmt' => '2017-08-21 07:50:40',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=gallery&#038;p=2340',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery link="file" columns="2" size="large" ids="2296,2333,2329,1623"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2349,
  'post_date' => '2014-03-24 20:45:29',
  'post_date_gmt' => '2014-03-24 20:45:29',
  'post_content' => 'Nam urna massa, ullamcorper quis pellentesque at, tincidunt vitae arcu. Curabitur ac enim vitae eros scelerisque imperdiet in nec urna. Duis eget magna et enim fermentum mattis. Etiam et facilisis massa. Suspendisse in lorem ultricies turpis egestas tempus at sed quam.',
  'post_title' => 'Gallery - 4 Columns',
  'post_excerpt' => '',
  'post_name' => 'gallery-4-columns',
  'post_modified' => '2017-08-21 07:50:39',
  'post_modified_gmt' => '2017-08-21 07:50:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=gallery&#038;p=2349',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery columns="4" link="file" size="large" ids="2350,2351,2352,2353,2354,2355,2356,2357" orderby="rand"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2358,
  'post_date' => '2014-03-24 21:09:41',
  'post_date_gmt' => '2014-03-24 21:09:41',
  'post_content' => 'Nam imperdiet eu odio et ornare. Aliquam luctus ullamcorper dictum. Praesent accumsan lectus ac nisl dictum, ut tincidunt libero volutpat. Curabitur ultricies erat vitae felis auctor, ut gravida lectus posuere. Quisque auctor, elit sit amet rutrum luctus, sapien felis dapibus leo, ac dictum turpis arcu sed neque. Cras ut nunc pharetra, eleifend turpis nec, pretium eros. Nam non purus nec leo luctus molestie sit amet ut risus. Morbi pulvinar lacinia scelerisque. Vivamus vel purus quis velit imperdiet pellentesque ac a augue.',
  'post_title' => '6-Column Masonry Gallery',
  'post_excerpt' => '',
  'post_name' => '6-column-masonry-gallery',
  'post_modified' => '2017-08-21 07:50:35',
  'post_modified_gmt' => '2017-08-21 07:50:35',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=gallery&#038;p=2358',
  'menu_order' => 0,
  'post_type' => 'gallery',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'gallery_shortcode' => '[gallery link="file" columns="6" size="medium" ids="2359,2360,2361,2362,2363,2364,2365,2366,2353,2354,2351,2355"]',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'gallery-category' => 'image-gallery',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 20,
  'post_date' => '2014-02-25 00:02:54',
  'post_date_gmt' => '2014-02-25 00:02:54',
  'post_content' => 'Duis fermentum lacus metus, ac blandit urna consequat nec. Proin rutrum dui eu vulputate dictum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed lacinia nec mauris in ultricies.',
  'post_title' => 'Video Post',
  'post_excerpt' => '',
  'post_name' => 'video-post',
  'post_modified' => '2017-08-21 07:54:28',
  'post_modified_gmt' => '2017-08-21 07:54:28',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=video&#038;p=20',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'https://www.youtube.com/watch?v=YRerQwKAM2A',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2214,
  'post_date' => '2014-02-28 20:35:55',
  'post_date_gmt' => '2014-02-28 20:35:55',
  'post_content' => 'Integer placerat at orci vitae porttitor. Vivamus volutpat ultricies nulla vel volutpat. Praesent et mi ut ante ornare egestas. Curabitur feugiat quam vel ligula sollicitudin, vel ultrices mauris semper.',
  'post_title' => 'The Weight of Mountains',
  'post_excerpt' => '',
  'post_name' => 'weight-mountains',
  'post_modified' => '2017-08-21 07:54:24',
  'post_modified_gmt' => '2017-08-21 07:54:24',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=video&#038;p=2214',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://vimeo.com/87651855',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2251,
  'post_date' => '2014-03-13 22:59:14',
  'post_date_gmt' => '2014-03-13 22:59:14',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Proin adipiscing placerat augue eu sodales. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Aenean mollis pretium enim ac malesuada. Nunc et eros laoreet, tincidunt dui ut, laoreet purus. Etiam tortor ligula, suscipit vehicula augue vel, adipiscing tincidunt elit. Aliquam libero nulla, tincidunt non sem eu, fermentum iaculis sem. Phasellus consectetur nulla et nisi gravida interdum. Ut rutrum fringilla eros lacinia tristique. Sed vel scelerisque purus. Nam facilisis augue vel viverra blandit. Vivamus ac lectus nibh. Nam laoreet tincidunt eros et ornare. Donec tincidunt nulla vel dapibus tincidunt. Praesent libero arcu, porta ac volutpat in, ultrices sed nulla. Aenean molestie scelerisque ante vel fringilla.',
  'post_title' => 'Apple TV Ad (mp4)',
  'post_excerpt' => '',
  'post_name' => 'apple-tv-ad-mp4',
  'post_modified' => '2017-08-21 07:54:15',
  'post_modified_gmt' => '2017-08-21 07:54:15',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=video&#038;p=2251',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'file',
    'video_file' => 'https://themify.me/demo/demo-videos/Apple-Iphone4s-TvAd-Life1.mp4',
    'lightbox_link' => 'https://themify.me/demo/demo-videos/Apple-Iphone4s-TvAd-Life1.mp4',
    'background_image' => 'https://themify.me/demo/themes/event-dev/files/2014/03/89794010.jpg',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
    'video-tag' => 'halloween',
  ),
  'has_thumbnail' => true,
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2245,
  'post_date' => '2014-03-10 18:29:14',
  'post_date_gmt' => '2014-03-10 18:29:14',
  'post_content' => 'This is daily motion video',
  'post_title' => 'Another Video',
  'post_excerpt' => '',
  'post_name' => 'another-video',
  'post_modified' => '2017-08-21 07:54:24',
  'post_modified_gmt' => '2017-08-21 07:54:24',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=video&#038;p=2245',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://www.dailymotion.com/embed/video/xn090k',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2246,
  'post_date' => '2014-03-10 18:29:39',
  'post_date_gmt' => '2014-03-10 18:29:39',
  'post_content' => 'Unbelievable Way to Create Art',
  'post_title' => 'Create Art',
  'post_excerpt' => '',
  'post_name' => 'blip-tv',
  'post_modified' => '2017-08-21 07:54:20',
  'post_modified_gmt' => '2017-08-21 07:54:20',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=video&#038;p=2246',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'https://www.youtube.com/watch?v=Oy1PaghHNpg',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2247,
  'post_date' => '2014-03-10 18:29:59',
  'post_date_gmt' => '2014-03-10 18:29:59',
  'post_content' => 'This is youtube embed here',
  'post_title' => 'YouTube Video',
  'post_excerpt' => '',
  'post_name' => 'youtube-video',
  'post_modified' => '2017-08-21 07:54:20',
  'post_modified_gmt' => '2017-08-21 07:54:20',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=video&#038;p=2247',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://www.youtube.com/watch?v=NmRTreaCJXs',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2248,
  'post_date' => '2014-03-10 18:30:22',
  'post_date_gmt' => '2014-03-10 18:30:22',
  'post_content' => 'Hello, Vimeo',
  'post_title' => 'Vimeo',
  'post_excerpt' => '',
  'post_name' => 'vimeo',
  'post_modified' => '2017-08-21 07:54:16',
  'post_modified_gmt' => '2017-08-21 07:54:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event-dev/?post_type=video&#038;p=2248',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://vimeo.com/6929537',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2321,
  'post_date' => '2014-03-24 14:35:54',
  'post_date_gmt' => '2014-03-24 14:35:54',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas id turpis pretium, feugiat tortor eu, aliquet urna. Cras ac velit velit. Aenean ut quam lorem. Phasellus at dui lectus. Maecenas ultrices id mauris vitae accumsan. Pellentesque ornare imperdiet odio vitae laoreet. Fusce placerat, est venenatis malesuada ullamcorper, nisl purus tincidunt risus, id mollis mauris sapien ac massa. Pellentesque eu vestibulum ligula.',
  'post_title' => 'DJ Light - Peru',
  'post_excerpt' => '',
  'post_name' => 'dj-light-peru',
  'post_modified' => '2017-08-21 07:54:13',
  'post_modified_gmt' => '2017-08-21 07:54:13',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=video&#038;p=2321',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://vimeo.com/17607732',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2322,
  'post_date' => '2014-03-24 14:38:27',
  'post_date_gmt' => '2014-03-24 14:38:27',
  'post_content' => 'Curabitur ante nunc, faucibus a libero sed, fermentum rutrum diam. Donec tempus tincidunt purus in imperdiet. Donec consequat arcu magna, at aliquam quam semper congue.

Nam a venenatis lorem, eget volutpat ligula. Donec sodales scelerisque ligula, in fringilla enim pharetra sit amet. Nullam tempus tortor sit amet ultricies tempor. Pellentesque sit amet est tellus. Sed consectetur velit ut turpis vehicula interdum. Nullam gravida ante non massa porttitor dignissim. Curabitur neque elit, facilisis varius nulla quis, eleifend tincidunt turpis.',
  'post_title' => 'Kimbra - Settle Down',
  'post_excerpt' => '',
  'post_name' => 'kimbra-settle',
  'post_modified' => '2017-08-21 07:54:11',
  'post_modified_gmt' => '2017-08-21 07:54:11',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=video&#038;p=2322',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://youtu.be/sd7GLvMYSHI',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2323,
  'post_date' => '2014-03-24 14:57:16',
  'post_date_gmt' => '2014-03-24 14:57:16',
  'post_content' => 'Sed consectetur velit ut turpis vehicula interdum. Nullam gravida ante non massa porttitor dignissim. Curabitur neque elit, facilisis varius nulla quis, eleifend tincidunt turpis. Mauris placerat malesuada ante ac scelerisque. Phasellus nibh velit, vulputate vel volutpat eget, commodo sit amet odio. Vivamus semper nisi erat, at consectetur enim euismod sit amet.

Donec feugiat laoreet tincidunt. Morbi sit amet purus laoreet, scelerisque elit eu, tristique urna. Nullam quis eros lacus. Vivamus luctus facilisis gravida. Quisque ullamcorper leo elit, nec porta est vehicula in.',
  'post_title' => 'Swap DJs',
  'post_excerpt' => '',
  'post_name' => 'swap-djs',
  'post_modified' => '2017-08-21 07:54:09',
  'post_modified_gmt' => '2017-08-21 07:54:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=video&#038;p=2323',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://vimeo.com/69455608',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2324,
  'post_date' => '2014-03-24 15:02:28',
  'post_date_gmt' => '2014-03-24 15:02:28',
  'post_content' => 'Phasellus ornare, neque vitae auctor rhoncus, ligula tortor euismod diam, a faucibus lacus lorem eu sem. Phasellus ut leo at libero consectetur scelerisque lobortis et nunc. Aenean at lacus lobortis, imperdiet libero non, molestie purus. Nunc mi sem, feugiat in rhoncus eget, aliquam sit amet nulla. Mauris molestie cursus felis, quis ultrices velit. Duis vel feugiat velit. Praesent turpis mi, dignissim sed turpis ullamcorper, lobortis hendrerit quam. Fusce lobortis, tellus in facilisis pretium, nisl leo imperdiet arcu, a placerat libero ligula pretium nibh. Quisque a mi aliquam, consectetur ipsum eu, auctor dui. Nullam nisi enim, vehicula a risus dapibus, cursus consequat felis.',
  'post_title' => 'Yuna Live Session',
  'post_excerpt' => '',
  'post_name' => 'yuna-live-session',
  'post_modified' => '2017-08-21 07:54:07',
  'post_modified_gmt' => '2017-08-21 07:54:07',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=video&#038;p=2324',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'http://youtu.be/XX6lCIuXMEo',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2325,
  'post_date' => '2014-03-24 15:10:25',
  'post_date_gmt' => '2014-03-24 15:10:25',
  'post_content' => 'Mauris sed fermentum lorem. Aliquam rhoncus ligula at vehicula aliquet. Interdum et malesuada fames ac ante ipsum primis in faucibus. Mauris feugiat nec dolor ac sagittis. Fusce porta, tellus at facilisis pellentesque, nulla est pellentesque libero, sed cursus ligula lorem ac enim. Proin tincidunt aliquam dictum. Integer eget ante laoreet augue eleifend vulputate. Phasellus dignissim justo odio, eu dignissim odio dictum a. Maecenas laoreet consequat dolor, quis bibendum augue. Quisque aliquam tempus metus. Praesent et interdum enim, in elementum mauris. Etiam vitae porta mauris. Aenean tempor venenatis mi at pharetra.',
  'post_title' => 'The Emperor of Time',
  'post_excerpt' => '',
  'post_name' => 'the-emperor-of-time',
  'post_modified' => '2017-08-21 07:54:05',
  'post_modified_gmt' => '2017-08-21 07:54:05',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/event/?post_type=video&#038;p=2325',
  'menu_order' => 0,
  'post_type' => 'video',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'video_type' => 'embed',
    'video_url' => 'https://vimeo.com/131586644',
    'background_repeat' => 'fullcover',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'video-category' => 'videos',
  ),
);
if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}


function themify_import_get_term_id_from_slug( $slug ) {
	$menu = get_term_by( "slug", $slug, "nav_menu" );
	return is_wp_error( $menu ) ? 0 : (int) $menu->term_id;
}

	$widgets = get_option( "widget_themify-twitter" );
$widgets[1002] = array (
  'title' => 'Twitter Widget',
  'username' => 'themify',
  'show_count' => '3',
  'hide_timestamp' => NULL,
  'show_follow' => NULL,
  'follow_text' => '→ Follow me',
  'include_retweets' => 'on',
  'exclude_replies' => NULL,
);
update_option( "widget_themify-twitter", $widgets );

$widgets = get_option( "widget_themify-event-posts" );
$widgets[1003] = array (
  'title' => 'Events',
  'category' => '35',
  'past' => '0',
  'show_count' => '1',
  'show_thumb' => 'on',
  'hide_title' => NULL,
  'thumb_width' => '280',
  'thumb_height' => '170',
  'hide_post_stats' => 'on',
  'hide_event_location' => NULL,
  'hide_event_date' => NULL,
  'hide_meta' => NULL,
);
update_option( "widget_themify-event-posts", $widgets );

$widgets = get_option( "widget_themify-feature-posts" );
$widgets[1004] = array (
  'title' => 'Recent Posts',
  'category' => '0',
  'show_count' => '3',
  'show_date' => 'on',
  'show_thumb' => 'on',
  'display' => 'none',
  'hide_title' => NULL,
  'thumb_width' => '50',
  'thumb_height' => '50',
  'excerpt_length' => '55',
  'orderby' => 'date',
  'order' => 'DESC',
);
update_option( "widget_themify-feature-posts", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1005] = array (
  'title' => '',
  'show_link_name' => NULL,
  'open_new_window' => NULL,
  'icon_size' => 'icon-medium',
);
update_option( "widget_themify-social-links", $widgets );



$sidebars_widgets = array (
  'sidebar-main' => 
  array (
    0 => 'themify-twitter-1002',
    1 => 'themify-event-posts-1003',
    2 => 'themify-feature-posts-1004',
  ),
  'social-widget' => 
  array (
    0 => 'themify-social-links-1005',
  ),
); 
update_option( "sidebars_widgets", $sidebars_widgets );

$menu_locations = array();
set_theme_mod( "nav_menu_locations", $menu_locations );


$homepage = get_posts( array( 'name' => 'home', 'post_type' => 'page' ) );
			if( is_array( $homepage ) && ! empty( $homepage ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $homepage[0]->ID );
			}
			
	ob_start(); ?>a:66:{s:16:"setting-page_404";s:1:"0";s:21:"setting-webfonts_list";s:4:"full";s:22:"setting-default_layout";s:8:"sidebar1";s:27:"setting-default_post_layout";s:9:"list-post";s:30:"setting-default_layout_display";s:7:"content";s:25:"setting-default_more_text";s:4:"More";s:21:"setting-index_orderby";s:4:"date";s:19:"setting-index_order";s:4:"DESC";s:30:"setting-default_media_position";s:5:"above";s:31:"setting-image_post_feature_size";s:5:"blank";s:32:"setting-default_page_post_layout";s:8:"sidebar1";s:30:"setting-default_page_post_meta";s:2:"no";s:38:"setting-image_post_single_feature_size";s:5:"blank";s:27:"setting-default_page_layout";s:8:"sidebar1";s:53:"setting-customizer_responsive_design_tablet_landscape";s:4:"1024";s:43:"setting-customizer_responsive_design_tablet";s:3:"768";s:43:"setting-customizer_responsive_design_mobile";s:3:"480";s:33:"setting-mobile_menu_trigger_point";s:4:"1200";s:24:"setting-gallery_lightbox";s:8:"lightbox";s:26:"setting-page_builder_cache";s:2:"on";s:27:"setting-script_minification";s:7:"disable";s:27:"setting-page_builder_expiry";s:1:"2";s:19:"setting-entries_nav";s:8:"numbered";s:22:"setting-footer_widgets";s:17:"footerwidget-3col";s:27:"setting-global_feature_size";s:5:"blank";s:22:"setting-link_icon_type";s:9:"font-icon";s:32:"setting-link_type_themify-link-0";s:10:"image-icon";s:33:"setting-link_title_themify-link-0";s:7:"Twitter";s:31:"setting-link_img_themify-link-0";s:95:"https://themify.me/demo/themes/event-dev/wp-content/themes/event/themify/img/social/twitter.png";s:32:"setting-link_type_themify-link-1";s:10:"image-icon";s:33:"setting-link_title_themify-link-1";s:8:"Facebook";s:31:"setting-link_img_themify-link-1";s:96:"https://themify.me/demo/themes/event-dev/wp-content/themes/event/themify/img/social/facebook.png";s:32:"setting-link_type_themify-link-2";s:10:"image-icon";s:33:"setting-link_title_themify-link-2";s:7:"Google+";s:31:"setting-link_img_themify-link-2";s:99:"https://themify.me/demo/themes/event-dev/wp-content/themes/event/themify/img/social/google-plus.png";s:32:"setting-link_type_themify-link-3";s:10:"image-icon";s:33:"setting-link_title_themify-link-3";s:7:"YouTube";s:31:"setting-link_img_themify-link-3";s:95:"https://themify.me/demo/themes/event-dev/wp-content/themes/event/themify/img/social/youtube.png";s:32:"setting-link_type_themify-link-4";s:10:"image-icon";s:33:"setting-link_title_themify-link-4";s:9:"Pinterest";s:31:"setting-link_img_themify-link-4";s:97:"https://themify.me/demo/themes/event-dev/wp-content/themes/event/themify/img/social/pinterest.png";s:32:"setting-link_type_themify-link-6";s:9:"font-icon";s:33:"setting-link_title_themify-link-6";s:8:"Facebook";s:32:"setting-link_link_themify-link-6";s:27:"http://facebook.com/themify";s:33:"setting-link_ficon_themify-link-6";s:11:"fa-facebook";s:32:"setting-link_type_themify-link-5";s:9:"font-icon";s:33:"setting-link_title_themify-link-5";s:7:"Twitter";s:32:"setting-link_link_themify-link-5";s:26:"http://twitter.com/themify";s:33:"setting-link_ficon_themify-link-5";s:10:"fa-twitter";s:32:"setting-link_type_themify-link-7";s:9:"font-icon";s:33:"setting-link_title_themify-link-7";s:9:"Instagram";s:32:"setting-link_link_themify-link-7";s:28:"http://instagram.com/themify";s:33:"setting-link_ficon_themify-link-7";s:12:"fa-instagram";s:32:"setting-link_type_themify-link-8";s:9:"font-icon";s:33:"setting-link_title_themify-link-8";s:7:"YouTube";s:32:"setting-link_link_themify-link-8";s:38:"https://www.youtube.com/user/themifyme";s:33:"setting-link_ficon_themify-link-8";s:10:"fa-youtube";s:32:"setting-link_type_themify-link-9";s:9:"font-icon";s:33:"setting-link_title_themify-link-9";s:9:"Pinterest";s:33:"setting-link_ficon_themify-link-9";s:12:"fa-pinterest";s:33:"setting-link_type_themify-link-10";s:10:"image-icon";s:22:"setting-link_field_ids";s:377:"{"themify-link-0":"themify-link-0","themify-link-1":"themify-link-1","themify-link-2":"themify-link-2","themify-link-3":"themify-link-3","themify-link-4":"themify-link-4","themify-link-6":"themify-link-6","themify-link-5":"themify-link-5","themify-link-7":"themify-link-7","themify-link-8":"themify-link-8","themify-link-9":"themify-link-9","themify-link-10":"themify-link-10"}";s:23:"setting-link_field_hash";s:2:"12";s:30:"setting-page_builder_is_active";s:6:"enable";s:46:"setting-page_builder_animation_parallax_scroll";s:6:"mobile";s:4:"skin";s:85:"https://themify.me/demo/themes/event/wp-content/themes/event/themify/img/non-skin.gif";}<?php $themify_data = unserialize( ob_get_clean() );

	// fix the weird way "skin" is saved
	if( isset( $themify_data['skin'] ) ) {
		$parsed_skin = parse_url( $themify_data['skin'], PHP_URL_PATH );
		$basedir_skin = basename( dirname( $parsed_skin ) );
		$themify_data['skin'] = trailingslashit( get_template_directory_uri() ) . 'skins/' . $basedir_skin . '/style.css';
	}

	themify_set_data( $themify_data );
	
}
themify_do_demo_import();