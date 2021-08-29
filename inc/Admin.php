<?php
/* 
	Admin.php - Administrator functions, including plugin configuration 
	Author: Dawn Gregory
*/

if( !defined( 'ABSPATH' ) )
	exit;

//******************************************************************** 
// Admin configuration settings
//******************************************************************** 

add_action('admin_init', 'blogflutter_admin_init');
function blogflutter_admin_init() {
    register_setting('blogflutter_config', 'blogflutter_access_token');
    register_setting('blogflutter_config', 'blogflutter_access_token_secret');
    register_setting('blogflutter_config', 'blogflutter_consumer_key');
    register_setting('blogflutter_config', 'blogflutter_consumer_secret');
    register_setting('blogflutter_config', 'blogflutter_max_tweet_len');
    register_setting('blogflutter_config', 'blogflutter_link_suffix');
    register_setting('blogflutter_config', 'blogflutter_tweet_type_sequence');
    register_setting('blogflutter_config', 'blogflutter_curr_tweet_interval');
    register_setting('blogflutter_config', 'blogflutter_max_hashtags');
	add_settings_section( 'blogflutter_config', 'BlogFlutter Settings', 'blogflutter_plugin_options', 'blogflutter_config' );
	// For testing
	
}

add_action('admin_menu', 'blogflutter_plugin_menu' );
function blogflutter_plugin_menu() {
	if (!current_user_can( 'edit_others_posts' )) return;
	add_menu_page( 'BlogFlutter', 'BlogFlutter', 'manage_options', 'blogflutter_admin', 'blogflutter_manage_tweets', 'dashicons-twitter', 50 );
	add_submenu_page( 'blogflutter_admin', 'Twitter Content', 'Content Manager', 'manage_options', 'blogflutter_admin', 'blogflutter_manage_tweets' );
	add_submenu_page( 'blogflutter_admin', 'Tweet Content', 'Add New', 'manage_options', 'blogflutter_addnew', 'blogflutter_add_tweets' );
	add_submenu_page( 'blogflutter_admin', 'Send A Tweet', 'Send Now', 'manage_options', 'blogflutter_sendnow', 'blogflutter_send_tweet_now' );
	add_submenu_page( 'blogflutter_admin', 'Magic Hashtags', 'Magic Hashtags', 'manage_options', 'edit.php?post_type=magic_hashtag' );
	if(class_exists('blogflutter_Quote_List'))
		add_submenu_page( 'blogflutter_admin', 'All Quotes', 'Quote Manager', 'manage_options', 'blogflutter_quotes', 'blogflutter_manage_quotes' );
	add_submenu_page( 'blogflutter_admin', 'BlogFlutter Setup', 'Settings', 'manage_options', 'blogflutter_config', 'blogflutter_plugin_options' );
}

//******************************************************************** 
// The settings screen to set up the twitter access keys
//******************************************************************** 

function blogflutter_plugin_options() { 
	blogflutter_verify_plugin_config(false,true);
	if (!current_user_can( 'edit_others_posts' )) return;
    ?>
    <div class="wrap">
		
		<h1>BlogFlutter Settings</h1>
		<?php if (!$GLOBALS["blogflutter_pro_version"]>0) : ?>
			<p><b>The Pro version of BlogFlutter lets you assign tweets to custom document types, and much more!</b></p>
			<p>Visit <a href="blogflutter.com">BlogFlutter.com</a> for more information</p>
		<?php endif; ?>
        <form action="options.php" method="post">
            <?php settings_fields('blogflutter_config'); ?>
			<p><b>Send tweets every 
			 <input type="number" name="blogflutter_curr_tweet_interval" id="blogflutter_curr_tweet_interval" value="<?php 
				$val=get_option('blogflutter_curr_tweet_interval'); 
				echo $val ? $val : '60';
			 ?>" /> minutes</b>
			 </p>
			<p><b>Maximum tweet length
			 <input type="number" name="blogflutter_max_tweet_len" id="blogflutter_max_tweet_len" value="<?php 
				$val=get_option('blogflutter_max_tweet_len',280); 
				echo $val ? $val : '280';
			 ?>" />
			 </p>
			<p><b>Maximum hashtags/tweet
			 <input type="number" name="blogflutter_max_hashtags" id="blogflutter_max_hashtags" value="<?php 
				$val=get_option('blogflutter_max_hashtags'); 
				echo $val ? $val : '2';
			 ?>" />
			 </p>
			 <h2>Tweet Sequence</h2>
			 <p>Set the base sequence of tweets by tweet type:<br />
			 <input type="text" name="blogflutter_tweet_type_sequence" id="blogflutter_tweet_type_sequence" value="<?php 
				echo get_option('blogflutter_tweet_type_sequence');
			 ?>" placeholder="Page,Quote,Post,Info,Quote,Link,Post" size="50" />
			 </p>
			<h2>Link Suffix</h2>
			<p>If you want to add parameters to your tweet links, enter them here:<br />
			 <input type="text" name="blogflutter_link_suffix" id="blogflutter_link_suffix" value="<?php 
				echo get_option('blogflutter_link_suffix'); 
			?>" placeholder="?param=value" size="50" />
            </p>
			<h2>Twitter Access Keys</h2>
			<p>Define your application on the twitter developers console:
			<a href="https://dev.twitter.com/apps/" target="_blank">https://dev.twitter.com/apps/</a><br/>
			Then navigate to the Keys and Access Tokens tab and Create an Access Token. <br/>
			Enter the keys in the form below to activate posting to your twitter account.</p>
           <table class="form-table"> 
                <tr valign="top"> 
                    <th scope="row"><label for="blogflutter_consumer_key">API Key</label></th> 
                    <td>
                        <input type="text" name="blogflutter_consumer_key" id="blogflutter_consumer_key" value="<?php echo get_option('blogflutter_consumer_key'); ?>" size="50" />
                    </td>                
                </tr> 
                <tr valign="top"> 
                    <th scope="row"><label for="blogflutter_consumer_secret">API Key Secret</label></th> 
                    <td>
                        <input type="text" name="blogflutter_consumer_secret" id="blogflutter_consumer_secret" value="<?php echo get_option('blogflutter_consumer_secret'); ?>" size="50" />
                    </td>                
                </tr> 
                <tr valign="top"> 
                    <th scope="row"><label for="blogflutter_access_token">Access Token</label></th> 
                    <td>
                        <input type="text" name="blogflutter_access_token" id="blogflutter_access_token" value="<?php echo get_option('blogflutter_access_token'); ?>" size="50" />
                    </td>                
                </tr> 
                 <tr valign="top"> 
                    <th scope="row"><label for="blogflutter_access_token_secret">Access Token Secret</label></th> 
                    <td>
                        <input type="text" name="blogflutter_access_token_secret" id="blogflutter_access_token_secret" value="<?php echo get_option('blogflutter_access_token_secret'); ?>" size="50" />
                    </td>                
                </tr> 
           </table> 
		   
		   <?php @submit_button(); ?> 
        </form>
    </div>
    <?php
}

?>