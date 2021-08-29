<?php
/* 
	SendTweet.php - Hooks and functions to send tweets via twitter API
	Author: Dawn Gregory
	
	Pick a tweet and send it
	Make more tweets available by type
	Call the TwitterAPIExchange to upload images and send tweets
	
	Note: The Twitter 2.0 interface does not support status updates yet
*/

if( !defined( 'ABSPATH' ) )
	exit;


//******************************************************************** 
// Pick a tweet and send it
//******************************************************************** 

function blogflutter_send_a_tweet ($tweet=0) {
	global $wpdb,$blogflutter_table_name;
			
	
	if (is_numeric($tweet) && $tweet>0) {
		$picked=$wpdb->get_row("select * from $blogflutter_table_name where tweet_id=$tweet");
	} else {
		// Get the next tweet type to send, from options
		$tweetTypes='Page,Quote,Post,Info,Quote,Link,Post';
		$userTypes = get_option('blogflutter_tweet_type_sequence', $tweetTypes);
		$typeList=explode(',',$userTypes);
		if (count($typeList)<5) $typeList=explode(',',$tweetTypes);
		// Select the tweet type to use
		$typeIndex = get_option('blogflutter_tweet_type_offset', 0 );		
		$typeIndex = ($typeIndex+1)%count($typeList);
		$thisType=trim($typeList[$typeIndex]);
		update_option( 'blogflutter_tweet_type_offset', $typeIndex, true );
		// Pick a tweet		
		$query="select * from $blogflutter_table_name ";
		$query.=" where tweet_status='' and tweet_mode='$thisType'";
		$query.=" order by tweet_seq ASC, tweet_sent ASC, tweet_id DESC limit 0,1";
		$picked=$wpdb->get_row($query);
		if (!is_object($picked) || $picked->tweet_seq>=10) {
			blogflutter_get_more_tweets ($thisType);
			$picked=$wpdb->get_row($query);
		}
		if (is_object($picked)) {
			// Update last posted stats in the WordPress options
			update_option( 'blogflutter_last_type_tweeted', $thisType, true );
			if ($picked->post_id>0) update_option( 'blogflutter_last_post_tweeted', $lastPost=$picked->post_id, true );
		} else if (is_admin() && esc_attr($_GET["page"])=="blogflutter_sendnow") {
			echo "<br /><b>Need more $thisType tweets</b>";
		}
	}
	
	// Send it!
	if (is_object($picked) && $picked->tweet_text) {
		$now=date('Y-m-d H:i:s');
		$err_msg=blogflutter_send_published_tweet ($picked->tweet_text,$picked->tweet_tags,$picked->post_id,$picked->tweet_id);
		$stat=($err_msg ? 'Error' : 'Sent');

		// Update the DB
		$wpdb->update($blogflutter_table_name,
			array("tweet_sent" => $now,"tweet_status" => $stat,"tweet_error" => $err_msg),
			array("tweet_id" => $picked->tweet_id),
			array('%s','%s','%s'),array('%d'));
	}
}

//******************************************************************** 
// Re-activate old tweets so we don't run out of things to say
//******************************************************************** 
 function blogflutter_get_more_tweets ($type='',$max=25) {
	global $wpdb,$blogflutter_table_name;
	$cutoff=date("Y-m-d H:i:s",time()-(60*3600));
	$rows=$wpdb->get_results("select * from $blogflutter_table_name where tweet_mode='$type' and tweet_sent<'$cutoff'".
		" order by tweet_sent ASC, tweet_id DESC limit 0,$max"); 
	if (count($rows)==0) return;
	$min=1; $max=count($rows);
	if (is_admin() && esc_attr($_GET["page"])=='blogflutter_sendnow') echo 'Loading '.$max.' more '.$type.' tweets';
	foreach ($rows as $tweet) {
		if ($tweet->post_id>0 && $tweet->post_id==$lastPost) $num=rand($num,$max);
		else $num=rand($min,$max);
		$wpdb->update($blogflutter_table_name,
			array("tweet_status" => '',"tweet_seq" => $num),
			array("tweet_id" => $tweet->tweet_id),
			array('%s','%d'),array('%d'));
		$lastPost=$tweet->post_id;
		$min+=1;
	}
}

 //******************************************************************** 
// Managing the Twitter API
// This sends the tweet - returns nothing if sent, message if error
//******************************************************************** 

function blogflutter_send_published_tweet ($tweet_text, $tweet_tags='', $postid=0, $tweetid=0) {
	global $wpdb,$blogflutter_table_name;
	try {
		$whichPage=esc_attr($_GET["page"]);
		
		// First check if we can even tweet about this post
		if ($postid>0 && get_post_status($postid)!='publish') 
			return 'The linked post is not publicly viewable';

		// Cleanup the text
		$tweet_text=stripslashes($tweet_text);
		$tweet_text=preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $tweet_text); 
		
		// How much space is left for hashtags? must calc >before< adding the permalink
		$space = get_option('blogflutter_max_tweet_len',280) - 1 - blogflutter_tweet_len($tweet_text,$postid);
		
		// Now add the post link, if any
		if ($postid) $tweet_text.=' '.esc_url(get_permalink($postid));
		
		// If there is space, we'll add some hashtags
		if ( $space>6 ) {	// Kind of assuming hashtags >= 5 chars - not sure if it's true		
			$tagPicks=explode(' ',str_replace(',',' ',blogflutter_explode_taglist($tweet_tags)));
			shuffle( $tagPicks );
			$tags=0; $numTags=rand(0 , get_option('blogflutter_max_hashtags', 3 )); 
			foreach($tagPicks as $tag) {
				$tag=trim($tag);
				if (substr($tag,0,1)!='#') $tag='#'.$tag;
				if( strlen($tag)>1 && strlen($tag) <= $space && $tags < $numTags ) {	
					$tweet_text.=' '.$tag;
					$space -= strlen( $tag ) + 1;
					$tags++;
				}
			}
		}
		
		// Grab the post's featured image and upload it 
		if ($postid 
			&& ( $tweet_img = wp_get_attachment_image_src( get_post_thumbnail_id($postid), 'large' ) ) 
			&& ($whichPage=="cron" || substr($whichPage,0,11)=='blogflutter') 	// Avoids images for front-end users
			&& rand(1,2)==1 ) 	// TODO: 2 could be a user setting for image density 
			$img_id=blogflutter_upload_image_file($tweet_img[0]);
		else $img_id=null;
		
		// Now send the tweet
		$result=blogflutter_send_tweet($tweet_text,$img_id);

	} catch (Exception $e) {
		return $e->getMessage();
	}
	return '';	// Success = no message
}

//******************************************************************** 
// Get tag picks from magic hashtags
//******************************************************************** 

function blogflutter_explode_taglist ($tweet_tags) {
	global $wpdb;
    $magictags = $wpdb->get_results(  "SELECT * FROM {$wpdb->posts} WHERE post_type = 'magic_hashtag' and post_status = 'publish'" );
    if ( ! $magictags ) return;
	foreach ($magictags as $index => $tweettag) {
		if (stristr($tweet_tags,'#'.$tweettag->post_name)) 
			$tweet_tags.=' '.$tweettag->post_excerpt;
	}
	return $tweet_tags;
}

//******************************************************************** 
// Initialize the tweet interface
// This may throw errors, make sure it is wrapped in try/catch
//******************************************************************** 

function blogflutter_init_interface () {
	// Initialization for the twitter API
	if(!class_exists('TwitterAPIExchange'))
		require_once('TwitterAPIExchange.php');
	
	// Get the API keys from admin settings; trim to eliminate spurious spaces
	$settings=array (
		'oauth_access_token' => trim( get_option( 'blogflutter_access_token' ) ),
		'oauth_access_token_secret' => trim( get_option( 'blogflutter_access_token_secret' ) ),
		'consumer_key' => trim( get_option( 'blogflutter_consumer_key' ) ),
		'consumer_secret' => trim( get_option( 'blogflutter_consumer_secret' ) )
	);
	
	// Setup an API call
	try {
		$api=new TwitterAPIExchange($settings);
		return $api;
	} catch (Exception $e) {
		throw new Exception("Error connecting to API - check your settings. ".$e->getMessage());
	}
}

//******************************************************************** 
// Upload an image file to twitter so we can attach it to a post
// This may throw errors, make sure it is wrapped in try/catch
//******************************************************************** 

function blogflutter_upload_image_file($file) {
	// Load the file using remote_get, in case allow_url_fopen is off (otherwise we could use file_get_contents)
	$response = wp_remote_get( $file );
	$err = wp_remote_retrieve_response_code( $response );
	if ( $err == "200" ) {
		$imgsrc=wp_remote_retrieve_body( $response );
	} else { 
		throw new Exception("HTTP $err loading image data (File=$file)");
	}
	$imgdata = base64_encode($imgsrc);
	if (strlen($imgdata)==0) throw new Exception("Couldn't convert image data");
	
	// Call the twitter API
	$url    = 'https://upload.twitter.com/1.1/media/upload.json';
	$params = array( 'media_data' => $imgdata );
	$twitter = blogflutter_init_interface();
	$result = $twitter->request($url, 'POST', $params);
	if (!$result) throw new Exception('Nothing returned from Twitter API'); 
	
	// Grab the ID for attaching to the tweet
	$data = @json_decode($result, true);
	if (isset($data['media_id'])) $img_id=$data['media_id'];
	else throw new Exception('Error uploading image on twitter API: '.$result);
	return $img_id;
}

//******************************************************************** 
// Send a tweet with (optional) attached image
// This may throw errors, make sure it is wrapped in try/catch
//******************************************************************** 

function blogflutter_send_tweet($tweet_text,$img_id) {
	// Setup the parameters, depending whether we have an image or not
	if ($img_id)  $params = array(
			'status' => $tweet_text,
			'media_ids' => $img_id
		);
	else  $params = array(
			'status' => $tweet_text
		);

	// Call the twitter API
	$url    = 'https://api.twitter.com/1.1/statuses/update.json';
	$twitter = blogflutter_init_interface();
	$response= $twitter->request($url, 'POST', $params);
	if (!$response) throw new Exception('Nothing returned from Twitter API');
	if (is_admin() && esc_attr($_GET["page"])=="blogflutter_sendnow") 
		echo "$tweet_text<br />Twitter responds: $response";
	
	// Grab the text of the tweet that was actually sent
	$data = @json_decode($response, true);
	if ( isset($data['errors']) ) throw new Exception('Error on twitter API: '.$response);
	else if ( isset($data['id_str']) ) $result=$data['text'];
	return $result;
}

//******************************************************************** 
// Helpful common function
//******************************************************************** 

function blogflutter_tweet_len($tweet_text,$post_id) {
	return strlen($item->tweet_text) + ($item->ID ? 26 : 0);
}


?>