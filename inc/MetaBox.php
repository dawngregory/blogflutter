<?php
/* 
	MetaBox.php - Put a tweet metabox on each page and post, to define & view tweets for that page/post
	Author: Dawn Gregory
*/

if( !defined( 'ABSPATH' ) )
	exit;

//******************************************************************** 
// Add the metabox for pages and posts
//******************************************************************** 

add_action('add_meta_boxes','blogflutter_add_metabox');
function blogflutter_add_metabox () {
	add_meta_box('blogflutter_schedule', 'BlogFlutter','blogflutter_post_metabox', 'page');
	add_meta_box('blogflutter_schedule', 'BlogFlutter','blogflutter_post_metabox', 'post');
}

//******************************************************************** 
// Here we do the markdown for the metabox content
//******************************************************************** 

function blogflutter_post_metabox ( $post ) {
	global $wpdb,$blogflutter_table_name;
	?>
	<label for="blogflutter_new_messages">Add tweets to the queue, one per row:</label>
	<textarea name="blogflutter_new_messages" id="blogflutter_new_messages" class="widefat"></textarea>
	<p>Use the tags <b><?php echo blogflutter_special_tags(); ?></b> for some special magic to happen!</p>
	<?php
	blogflutter_verify_plugin_config();	// Show a message if tweets aren't sending
	// Show list of defined tweets for this page/post	
	$lst=$wpdb->get_results("select * from $blogflutter_table_name where post_id=".$post->ID);
	if (count($lst)) {
		echo '<p><b>In Queue:</b>';
		foreach($lst as $tweet) echo '<br />'.stripslashes($tweet->tweet_text);
		echo '</p>';
	}
}

//******************************************************************** 
// Save the metaboz content
//******************************************************************** 
add_action( 'save_post', 'blogflutter_save_metabox' );

function blogflutter_save_metabox( int $post_id ) {		
	// Abort if it's an autosave
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; 
	
	// Check if user can edit this post
   if( !current_user_can( 'edit_post' ) ) return;  
	
	// This is what we really want to save
	$post_type=get_post_type($post_id);
	$keys=get_post_types(array( "public" => true) );
	if (in_array($post_type,$keys) || $post_type=="page" || $post_type=="post")  {
		if ( array_key_exists( 'blogflutter_new_messages', $_POST )) {
			$newtweets=esc_attr($_POST["blogflutter_new_messages"]);
			blogflutter_add_batch($newtweets,$post_id);
			$_POST["blogflutter_new_messages"]='';
		}
	}
	
}
 

?>