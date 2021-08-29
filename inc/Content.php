<?php
/* 
	Content.php - Manage the tweet content
	Author: Dawn Gregory
*/

if( !defined( 'ABSPATH' ) )
	exit;

if(!class_exists('WP_List_Table'))
   require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

global $wpdb,$blogflutter_table_name,$blogflutter_table_version;
$blogflutter_table_name = $wpdb->prefix . 'auto_tweet';
$blogflutter_table_version = '1.0';

//******************************************************************** 
// Call this to determine if the plugin has been setup correctly
//******************************************************************** 

function blogflutter_verify_plugin_config($silent=false,$settings=true) {
	$silent=$silent || !current_user_can( 'edit_others_posts' );
	if (!function_exists('mb_convert_encoding')) {
		if (!$silent) {
			echo "<h3>Configuration Error</h3>";
			echo "<p>Configuration error: This plugin requires the MultiByte Strings PHP extension to send tweets.</p>";
			echo "<p>Please contact your host administrator to activate the MBstring extension before scheduling tweets.</p>";
			echo '<p>Your tweets will not be sent until this issue is resolved.</p>';
			}
	} else if (!$settings) {
		return true;
	} else if (trim( get_option( 'blogflutter_access_token' ) ) && trim( get_option( 'blogflutter_access_token_secret' ) )
		&& trim( get_option( 'blogflutter_consumer_key' ) ) && trim( get_option( 'blogflutter_consumer_secret' ) )) {
		return true;
	} else if (!$silent) {
		echo "<p><b>Important:</b> We need your Twitter API keys to send tweets!";
		echo '<a href="'.admin_url( 'admin.php?page=blogflutter_config' ).'">Visit the settings page</a>.</p>';
	}
	return false;
}



//******************************************************************** 
// A class for displaying tweets table
//******************************************************************** 

class blogflutter_Tweet_List extends WP_List_Table {
	protected $my_query;
	protected $tweet_active=false;
	
    function __construct() {
		parent::__construct( array(
				'singular'=> 'Tweet',  
				'plural' => 'Tweets',  
				'ajax'   => false //We won't support Ajax for this table
			) 
		);
		$this->tweet_active=blogflutter_verify_plugin_config(true);
    }
	
	function extra_tablenav( $which ) {
		$pagenum=esc_attr($_GET["paged"]);
	if ($which == "top" && (!is_numeric($pagenum) || $pagenum<2)) {
			echo '<form action="" method="get">';
			$this->search_box( __( 'Search' ), 'blogflutter' ); 
			echo '</form>';
		}
		if ( $which == "bottom" ){
			blogflutter_verify_plugin_config();
			//for debugging we can set this value
			echo $this->my_query;
		}
	}
	
	function get_columns() {
		return $columns= array(
			'cb' => '<input type="checkbox" />',
			'tweet_text'=>__( 'Tweet' ),
			'tweet_mode'=>__( 'Type' ),
			'tweet_tags'=>__( 'Hashtags' ),
			'tweet_sent'=>__( 'Last Sent' ),
			'tweet_post'=>__( 'Link to Page' )
		);
	}
	
	public function get_sortable_columns() {
		return $sortable = array(
			'tweet_text'=>array( 'tweet_text',false),
			'tweet_mode'=>array( 'tweet_mode',false),
			'tweet_tags'=>array( 'tweet_tags',false),
			'tweet_sent'=>array( 'tweet_sent',true),
			'tweet_post'=>array( 'post_title',false)
	   );
	}

	public function get_bulk_actions() {
		$actions = array(
			'enqueue' => 'Recycle',
			'delete'    => 'Delete',
		);
		// All magic tags should be options to dropdown in this list. Something like:
		foreach(explode(' ',blogflutter_special_tags()) as $tag) {
			if ($tag) $actions['tag'.substr($tag,1)]='Assign '.$tag;
		} 
		return $actions;
	}
	
	function column_default( $item, $column_name ) {
		return $column_name;
	}
	function column_cb($item) {        return sprintf(
        '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ strtolower($this->_args['singular']),  //Let's simply repurpose the table's singular label
            /*$2%s*/ $item->tweet_id            //The value of the checkbox should be the record's id
        );
    }
	function column_tweet_text($item) { 
		$tweet_max_len=get_option('blogflutter_max_tweet_len',280);
		if ($tweet_max_len>0 && strlen($item->tweet_text)>$tweet_max_len) 
			$str='<b style="color:red;">';
		else
			$str='<b>';
		$str.=stripslashes($item->tweet_text).'</b>';
		$str='<a href="/wp-admin/admin.php?page=blogflutter_addnew&edit_tweet='.$item->tweet_id.'">'.$str.'</a>';
		return $str;
	}
	function column_tweet_mode($item) { 
		if (!$this->tweet_active) return $item->tweet_mode;
		return __($item->tweet_mode);
	}
	function column_tweet_tags($item) {
		if (!$this->tweet_active) return $item->tweet_tags;
		return __($item->tweet_tags);
	}
	function column_tweet_sent($item) { 
		if (empty($item->tweet_status)) 
			return '<i>Pending'.(substr($item->tweet_sent,0,4)=="0000" ? '' : '<br />Last sent '.$item->tweet_sent).'</i>';
		return __($item->tweet_sent);
	}
	function column_tweet_post($item) { 
		if ($item->ID) {
			return '<a href="/wp-admin/post.php?post='.$item->ID.'&action=edit">'.$item->post_title.'</a>';
		} 
		return '<i>No link</i>'; 
	}
	function prepare_items() {
		global $wpdb,$blogflutter_table_name;

		// Setup the query
		$query = "SELECT * FROM $blogflutter_table_name left outer join $wpdb->posts on post_id=ID";
		
		// Handle a search, if any
		$srch=esc_attr($_GET["s"] ? $_GET["s"] : $_POST["s"]);
		if ($srch) {
			$match="'%".$srch."%'";
			$query .= " WHERE (tweet_text LIKE $match OR post_title LIKE $match)";
		}
		$totalitems = count($wpdb->get_results($query));
		
		// Setup the sorting
		$orderby=esc_attr($_GET["orderby"] ? $_GET["orderby"] : $_POST["orderby"]);
		if ($orderby && is_string($orderby)) {
			$query.=" ORDER BY $orderby";
			$ordering=esc_attr($_GET["order"] ? $_GET["order"] : $_POST["order"]);
			if (is_string($ordering)) $query.=' '.$ordering;
		} else $query.=" ORDER BY tweet_status ASC, tweet_seq ASC, tweet_sent DESC";

		// Setup the pagination
		if ($srch) {
			$perpage=$totalitems;
			$offset=0;
		} else {
			$perpage = 20;
			$pagenum = esc_attr($_GET["paged"]);
			if(empty($pagenum) || !is_numeric($pagenum) || $pagenum<=0 ){ $pagenum=1; } 
			$offset=($pagenum-1)*$perpage; 	
		}
		$totalpages = ceil($totalitems/$perpage); 
		$query.=' LIMIT '.(int)$offset.','.(int)$perpage; 	// $this->my_query=$query;
		
		/* -- Register the pagination -- */ 
		$this->set_pagination_args( array(
				"total_items" => $totalitems,
				"total_pages" => $totalpages,
				"per_page" => $perpage,
			) 
		);
		
		// Setup the columns
		$screen = get_current_screen();
		$this->_column_headers=array($this->get_columns(),array(),$this->get_sortable_columns(),'col_tweet_text');

		// Fetch the items for this page
		$this->items = $wpdb->get_results($query);
	}
	
	public function process_bulk_actions() {
		global $wpdb,$blogflutter_table_name;
		if (!current_user_can( 'edit_others_posts' )) return;
		
		$checked=array_filter( is_array($_GET['tweet']) ? $_GET['tweet'] : 
					is_array($_POST['tweet']) ? $_POST['tweet'] : array(), 'is_numeric');
		$action=$this->current_action();
		try {
			if( strtolower(substr($action,0,3)) == 'tag' ) {
				$tag='#'.strtolower(substr($action,3));
				foreach($checked as $tweet) {
					$wpdb->query($wpdb->prepare(
						"update $blogflutter_table_name set tweet_tags=concat(tweet_tags,' %s')".
						" where tweet_id=%d",$tag,$tweet));	
				}
			}
			if( $action=='delete' ) {
				foreach($checked as $tweet) { 
					$wpdb->delete($blogflutter_table_name,array( "tweet_id" => $tweet), array("%d"));
				}
			}
			if( $action=='enqueue' ) {
				$max_rank=2+$wpdb->get_var("select max(tweet_rank) from $blogflutter_table_name"); 
				foreach($checked as $tweet) {
					$wpdb->update($blogflutter_table_name,
						array("tweet_status" => "Next"),
						array("tweet_id" => $tweet),
						array('%s'),array('%d'));
				}
			}
		} catch (Exception $e) { print("An error occurred in the bulk update."); }
    }	
}

// The main function for managing tweets
// /wp-admin/admin.php?page=blogflutter_admin[&action=tweet&tweet=#]
function blogflutter_manage_tweets() { 
	global $wpdb,$blogflutter_table_name;
	echo '<h1>All Tweets</h1>';
	echo '<form method="post">';
	// Handle any actions - send tweet, delete 
	$action=esc_attr($_GET["action"] ? $_GET["action"] : $_POST["action"]);
	$tweet=esc_attr($_GET["tweet"] ? $_GET["tweet"] : $_POST["tweet"]);
	if (is_numeric($tweet) && $tweet>0) {
		blogflutter_do_action($tweet,$action);
	} 
	// Prepare and display the dynamic tweet list
	echo '<div id="tweet-list">';
	$wp_list_table = new blogflutter_Tweet_List();
	// Handle bulk actions before load / display of tweet table
	$wp_list_table->process_bulk_actions();
	$wp_list_table->prepare_items();
	$wp_list_table->display();
	echo '</div></form>';
}

// This is for one-up actions: links
function blogflutter_do_action($tweet,$action) {
	global $wpdb,$blogflutter_table_name;
	if ($action=="tweet") {
		blogflutter_send_a_tweet($tweet);
	} else if ($action=="delete") {
		$wpdb->delete($blogflutter_table_name,array( "tweet_id" => $tweet), array("%d"));
	}
}

// Display form and instructions for adding new tweets
function blogflutter_add_tweets() {
	global $wpdb,$blogflutter_table_name;
	if (!current_user_can( 'edit_others_posts' )) return;
	// We can link to this screen with an edit ID to access a form
	if (isset($_POST["edit_tweet"]) || isset($_GET["edit_tweet"])) {
		$tweet_id=esc_attr(isset($_POST["edit_tweet"]) ? $_POST["edit_tweet"] : $_GET["edit_tweet"]);
		// Save any updates, if available
		if ( array_key_exists('blogflutter_tweet_text', $_POST) ) {
			$values=array("tweet_text" => esc_attr($_POST["blogflutter_tweet_text"]),
				"tweet_tags" => esc_attr($_POST["blogflutter_tweet_tags"]));
			$wpdb->update($blogflutter_table_name,$values,
				array("tweet_id" => $tweet_id),
				array('%s','%s','%s'),array('%d'));
		}
		// Now show the edit form
		$edit_me=$wpdb->get_row("select * from $blogflutter_table_name 
			left outer join $wpdb->posts on post_id=ID where tweet_id=$tweet_id");
		echo '<h1>Edit Tweet Settings</h1>';
		echo '<form method="post">';
		?><p><b>Tweet Text:  </b>
			<input type="text" name="blogflutter_tweet_text" id="blogflutter_tweet_text" value="<?php 
			echo htmlspecialchars(stripslashes($edit_me->tweet_text)); ?>" class="widefat" /></p>
			<p><b>Hashtag:  </b>
			 <input type="text" name="blogflutter_tweet_tags" id="blogflutter_tweet_tags" value="<?php 
			echo $edit_me->tweet_tags; ?>" class="widefat" /></p>
			<p><b>Tweet Type:  </b><?php echo $edit_me->tweet_mode; ?></p>
		<?php	
			if ($edit_me->ID>0) {
				echo '<p><b>Linked to: </b><a href="/wp-admin/post.php?post='.$edit_me->post_id.'&action=edit" target="_blank">'.$edit_me->post_title.'</a></p>';
		} 

		@submit_button();
		echo '</form>';
		// Show a link to go back to ths list
		echo '<p><a href="/wp-admin/admin.php?page=blogflutter_admin">&lt; Go back to list</a></p>';
	} else {	
		// Standard  Add a tweet functionality
		echo '<h1>Add New Tweets</h1>';
		blogflutter_verify_plugin_config();	// Show a message if tweets aren't sending
		if ( array_key_exists( 'blogflutter_new_messages', $_POST )) {
			$newtweets=esc_attr($_POST["blogflutter_new_messages"]);
			blogflutter_add_batch($newtweets);
		}
		echo '<p>Add tweets to the AutoTweet queue, one per row:<br />';
		echo '<form method="post">';
		echo '<textarea name="blogflutter_new_messages" class="widefat" cols="50" rows="6"></textarea></p>';
		echo '<p>Add as many hashtags as you want to the <i>end of the tweet</i>,';
		echo ' they will be randomly selected each time the tweet is posted.';
		echo '<br />Use the tags <b>'.blogflutter_special_tags().'</b> for some special magic to happen!';
		echo '<br />Hashtags embedded in the tweet (not at the end) will stay as they are.</p>'; 
		@submit_button();
		echo '</form>';
	}
}

// Add a batch of tweets as specified
function blogflutter_add_batch($tweets,$postid=0) {
	global $wpdb,$blogflutter_table_name;
	if (!current_user_can( 'edit_others_posts' )) return;
	
	if (!empty($tweets)) {
		$list=explode("\n",$tweets);
		$minSeq=1;
		foreach($list as $new) {
			// Remove hashtags from end of tweet, to save separately
			$parts=explode('#',$new);
			$str=$new;
			$hashtags=array();
			foreach ($parts as $tag) {
				$words = preg_split('/[^[:alnum:]]+/', $tag);
				$word=array_shift($words);
				$tag='#'.$word;
				array_push($hashtags,$tag);
				if ($tag=="#quote") $mode="Quote";
				if (strlen(trim(implode(' ',$words)))>0) $hashtags=array();
			}
			foreach ($hashtags as $tag) $str=str_ireplace($tag,'',$str);
			$str=str_replace('  ',' ',$str);
			$mode = ( $postid ? ucwords(get_post_type($postid)) : (stristr($str,'http') ? 'Link' : ($mode ? $mode : 'Info' ) ) );
			$len = strlen($str) + ( $postid ? 26 : 0 );
				
			// Figure out how the sequencing is going to go:
			$maxSeq=1+$wpdb->get_var("select count(*) from $blogflutter_table_name where tweet_mode='$mode'"); 
			
			if ($postid && $nextSeq) $nextSeq=rand($nextSeq,$maxSeq);
			else $nextSeq=rand($minSeq,$maxSeq);
			$minSeq += 1;
			
			// Now insert a new row
			$tags=implode(' ',$hashtags);
			$wpdb->replace($blogflutter_table_name,
				array("post_id" => $postid,"tweet_text" => $str,"tweet_len" => $len,
					"tweet_tags" => $tags, "tweet_mode" => $mode, "tweet_seq" => $nextSeq),
				array ('%d','%s','%d','%s','%s','%d'));
			if ($postid==0) echo '<br />Added: '.$str;
		}
	}
}
	
// Send a tweet now and display the results
function blogflutter_send_tweet_now () {
	echo '<h1>Sending a random tweet</h1>';

	if (blogflutter_verify_plugin_config()) blogflutter_send_a_tweet();
}
	
?>