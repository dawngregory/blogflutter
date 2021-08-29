<?php
/* 
	MagicTags.php - Custom document type for magic hashtags
	Author: Dawn Gregory
	
	Register custom doc type
	Manage custom columns: hashtag and variations (slug and extract)
	Retrieve a list of magic hashtags
*/

if( !defined( 'ABSPATH' ) )
	exit;
	
//******************************************************************** 
// Register custom document type//******************************************************************** 

add_action('init','blogflutter_register_magictags');
function blogflutter_register_magictags () {
	$myDocLabels=array(
		'name'  => __( 'Magic Hashtags' ),
		'singular_name'       => __( 'Magic Hashtag' ),
		'menu_name'       => __( 'Magic Hashtags' ),
		'all_items'       => __( 'Magic Hashtags' ),
		'add_new'       => __( 'Add Magic Hashtag' ),
		'add_new_item'       => __( 'New Magic Hashtag' ),
		'edit_item'          => __( 'Edit Magic Hashtag' ),
		'new_item'           => __( 'New Magic Hashtag' ),
		'view_item'          => __( 'View Magic Hashtag' ),
		'search_items'       => __( 'Search Magic Hashtags' ),
		'not_found'          => __( 'No magic hashtags found' ),
		'not_found_in_trash'          => __( 'No magic hashtags found in Trash' )
	);

	$myDocArgs=array (
		'labels'  => $myDocLabels,
		'description'   => 'Magic hashtags automatically substitute any of the related tags.',
		'public'        => false,
		'exclude_from_search' => true,
		'show_ui'  =>true,
		'show_in_menu'  => 'blogflutter_manage_hashtags',
		'capability_type' => 'post',
		'hierarchical' => false,
		'supports' => array( 'title',  'excerpt', 'thumbnail' ),
		'can_export' => true,
	);

	register_post_type( 'magic_hashtag', $myDocArgs );

	add_filter('manage_magictags_posts_columns', 'blogflutter_magictags_columns_head');
	add_action('manage_posts_custom_column', 'blogflutter_magictags_columns_content', 10, 2);

}

//******************************************************************** 
// Manage custom columns
//******************************************************************** 

function blogflutter_magictags_columns_head($defaults) {
    $defaults['hashtag'] = 'Hashtag';
    $defaults['options'] = 'Variations';
    return $defaults;
}
 
function blogflutter_magictags_columns_content($column_name, $post_ID) {
    if (get_post_type($post_ID)=="magic_hashtag") {
		if ($column_name == 'hashtag') {
			$post_slug = get_post_field( 'post_name', $post_ID);
			if ($post_slug) {
				echo '#'.$post_slug;
			}
		}
		if ($column_name=="options") {
			$the_tags=get_the_excerpt($post_ID);
			echo $the_tags;
		}
    }
}

//******************************************************************** 
// Retrieve a list of defined magic tags
//******************************************************************** 

function blogflutter_special_tags() {
	global $wpdb;
    $results = $wpdb->get_results(  "SELECT * FROM {$wpdb->posts} WHERE post_type = 'magic_hashtag' and post_status = 'publish'" );
    if ( ! $results ) return;
    foreach( $results as $index => $post ) {
		$magictags.=' #'.$post->post_name; 
	}
	return trim($magictags);	
}
	
?>
