<?php
/*
Plugin Name: BlogFlutter
Plugin URI: https://blogflutter.com
Description: Automatically sends tweets from your blog with zero maintenance. Just add content to posts and pages to generate an appealing mix of content for your followers.
Version: 2.35
Date: 14 June 2021
Author: Dawn Gregory
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if( !defined( 'ABSPATH' ) )
	exit;


//******************************************************************** 
// Include files
//******************************************************************** 

require_once('inc/Content.php');	// Manage custom tweets table (v 1.2)
include_once('inc/SendTweet.php');	// Send a tweet when published
require_once('inc/MagicTags.php');	// Custom document type to define magic hashtags (v 1.3)
require_once('inc/MetaBox.php');	// Metabox for pages and posts
require_once('inc/Admin.php');		// Admin settings and dashboard widget

//******************************************************************** 
// These functions implement the CRON task schedule
//******************************************************************** 

add_action('wp_loaded','blogflutter_do_tasks');
function blogflutter_do_tasks () {
	do_action('blogflutter_cron_tasks');
}

// Send a tweet based on the specified schedule
add_action('blogflutter_cron_tasks', 'blogflutter_post_on_schedule');
function blogflutter_post_on_schedule () {
	if (is_string($_GET["page"]) && $_GET["page"]=="blogflutter_sendnow") return;
	$minutes=get_option('blogflutter_curr_tweet_interval', 60 );
	$startTime = get_option('blogflutter_next_time_to_tweet', 0, true );
	if (empty($startTime) || ($startTime < time())) {
		// Calculate next event time
		if (!is_numeric($minutes)) $minutes=60;
		else if ($minutes < 5) $minutes=5;
		$minutes=$minutes*2/3 + rand(0,$minutes*2/3);	// Mix it up a little
		$startTime=time()+(60*$minutes);
		update_option( 'blogflutter_next_time_to_tweet', $startTime, true );
		// Now send the next tweet in the queue
		blogflutter_send_a_tweet ();
	}
}

// Call this function any time you want to tweet 
function blogflutter_tweet_task() {	
	if ( get_option('blogflutter_next_time_to_tweet', 0 ) < time() ) {
		spawn_cron(); 
	}
}

//******************************************************************** 
// Configuration functions
//******************************************************************** 

// Add settings link on plugins page
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'blogflutter_add_plugin_page_settings_link');
function blogflutter_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'admin.php?page=blogflutter_config' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

// Create custom table on plugin activation
register_activation_hook( __FILE__, 'blogflutter_install_tweets_table' );
function blogflutter_install_tweets_table() {
	global $wpdb,$blogflutter_table_name,$blogflutter_table_version;

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $blogflutter_table_name (
		tweet_id int(11) NOT NULL AUTO_INCREMENT,
		post_id int(11) NOT NULL,
		tweet_text text NOT NULL,
		tweet_len int(11) NOT NULL,
		tweet_tags text NOT NULL,
		tweet_sent datetime NOT NULL,
		tweet_mode varchar(25) NOT NULL,
		tweet_seq int(11) NOT NULL,
		tweet_status varchar(6) NOT NULL,
		tweet_rank int(11) NOT NULL,
		tweet_error text NOT NULL,
		PRIMARY KEY  (tweet_id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	if (isset($GLOBALS['blogflutter_quotes_table']))
		blogflutter_install_quotes_table();

	add_option( 'blogflutter_table_version', $blogflutter_table_version );
	
	// Verify that default magictags are available in this installation
	$magictags=explode (' ',blogflutter_special_tags());
	if (count($magictags)<1) {
		wp_insert_post( array( 'post_type' => 'magic_hashtag' , 'post_name' => 'quote' , 'post_title' => 'Quotes' , 'post_excerpt' => '#quotes #motivation #quoteoftheday', 'post_status' => 'publish' ));
		wp_insert_post( array( 'post_type' => 'magic_hashtag' , 'post_name' => 'positive' , 'post_title' => 'Positive Thinking' , 'post_excerpt' => '#happy #smiling #positivity #healing #goodvibes #affirmation #affirmators #positivethinking #positiveaffirmations #rise' ));
		wp_insert_post( array( 'post_type' => 'magic_hashtag' , 'post_name' => 'marketing' , 'post_title' => 'Marketing' , 'post_excerpt' => '#SEO,#contentmarketing,#digitalmarketing,#socialmedia,#marketingtips,#growthhacking' ));
	}
	
}

?>