<?php
/* 
	ExtraCode.php - Additional features for pro version
	
	Add dashboard widget
	Activate metabox for custom post types
*/

if( !defined( 'ABSPATH' ) )
	exit;

//******************************************************************** 
// Version number activates pro features in base version
//******************************************************************** 

global $blogflutter_pro_version;
$blogflutter_pro_version=1.0;

//******************************************************************** 
// Configure the dashboard widget
//******************************************************************** 

add_action( 'wp_dashboard_setup', 'blogflutter_add_dashboard_widget' );
function blogflutter_add_dashboard_widget () {
	add_meta_box( 
		'blogflutter_direct', 
		esc_html__( 'Add a Tweet', 'wporg' ), 
		'blogflutter_direct_tweet', 
		'dashboard', 
		'side', 'high'
	);	
}


function blogflutter_direct_tweet() {
	if (isset($_POST["blogflutter_quick_tweet"]) && $_POST["blogflutter_quick_tweet"]) {
		$tweet=esc_attr($_POST["blogflutter_quick_tweet"]);
		if ($_POST["blogflutter_quick_link"]) {
			if (strtolower(substr($_POST["blogflutter_quick_link"],0,4))=="http")
				$tweet.=' '.esc_url($_POST["blogflutter_quick_link"]);
			else 
				$tweet.=' https://'.esc_url($_POST["blogflutter_quick_link"]);
		}
		blogflutter_add_batch($tweet);	// Even though it's only one...
	}
	blogflutter_verify_plugin_config();	// Show a message if tweets aren't sending
	echo '<form method="post">';
	echo '<b>Tweet:</b> <input type="text" name="blogflutter_quick_tweet" class="widefat" cols="50" /><br />';
	echo '<b>Link:</b> <input type="text" name="blogflutter_quick_link" class="widefat" cols="30" /></p>';
	@submit_button();
	echo '</form>';

}

//******************************************************************** 
// Add meta box for custom post types too
//******************************************************************** 

add_action('add_meta_boxes','blogflutter_public_posttypes');
function blogflutter_public_posttypes ($post_type) {
	if (in_array($post_type,array("page","post","attachment","media")) ) return;
	
	$keys=get_post_types(array( "public" => true) );
	if (in_array($post_type,$keys)) {
		add_meta_box('blogflutter_schedule', 'BlogFlutter','blogflutter_post_metabox', $post_type);
	} 
}

?>