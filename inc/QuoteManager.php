<?php

if( !defined( 'ABSPATH' ) )
	exit;

global $wpdb,$blogflutter_quotes_table;
$blogflutter_quotes_table = $wpdb->prefix . 'auto_quotes';


if(!class_exists('WP_List_Table'))
   require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

// A class for displaying quotes table

class blogflutter_Quote_List extends WP_List_Table {
	public $my_debug;
	
    function __construct() {
		parent::__construct( array(
				'singular'=> 'Quote',  
				'plural' => 'Quotes',  
				'ajax'   => false //We won't support Ajax for this table
			) 
		);
    }
	
	function extra_tablenav( $which ) {
		$pagenum=esc_attr($_GET["paged"]);
		if ($which == "top" && (!is_numeric($pagenum) || $pagenum<2)) {
			echo '<form action="" method="get">';
			$this->search_box( __( 'Search' ), 'blogflutter' ); 
			echo '</form>';
		}
		if ( $which == "bottom" ){
			//for debugging we can set this value
			echo $this->my_debug;
		}
	}
	
	function get_columns() {
		return $columns= array(
			'cb' => '<input type="checkbox" />',
			'quote_text'=>__( 'Quote' ),
			'quote_author'=>__( 'Author' ),
		);
	}
	
	public function get_sortable_columns() {
		return $sortable = array(
			'quote_text'=>array( 'quote_text',true),
			'quote_author'=>array( 'quote_author',true),
	   );
	}

	public function get_bulk_actions() {
		$actions = array(
			'share' 	=> 'Share on Twitter',
			'delete'    => 'Delete',
		);
		return $actions;
	}
	
	function column_default( $item, $column_name ) {
		return $column_name;
	}
	function column_cb($item) {        return sprintf(
        '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ strtolower($this->_args['singular']),  //Let's simply repurpose the table's singular label
            /*$2%s*/ $item->quote_id           //The value of the checkbox should be the record's id
        );
    }
	function column_quote_text($item) { 
		return '<b>'.stripslashes($item->quote_text).'</b>'; 
	}
	function column_quote_author($item) {
		return $item->quote_author;
	}

	function prepare_items() {
		global $wpdb,$blogflutter_quotes_table;

		try {
			// Setup the query
			$query = "select * from $blogflutter_quotes_table";
			
			// Handle a search, if any
			$srch=esc_attr($_GET["s"] ? $_GET["s"] : $_POST["s"]);
			if ($srch) {
				$match="'%".$srch."%'";
				$query .= " WHERE (quote_text LIKE $match or quote_author LIKE $match)";
			}
			$totalitems = count($wpdb->get_results($query));

			// Setup the sorting
			if ( $orderby=esc_attr($_GET["orderby"]) ) {
				$query.=' ORDER BY '.$orderby;
				if ($_GET["order"]) $query.=' '.esc_attr($_GET["order"]);
			} else $query.=" ORDER BY rand()";

			// Setup the pagination
			if ($srch) {
				$perpage=$totalitems;
				$offset=0;
			} else {
				$perpage = 30;
				$pagenum = esc_attr($_GET["paged"]);
				if(empty($pagenum) || !is_numeric($pagenum) || $pagenum<=0 ){ $pagenum=1; } 
				$offset=($pagenum-1)*$perpage; 	
			}
			$totalpages = ceil($totalitems/$perpage); 
			$query.=' LIMIT '.(int)$offset.','.(int)$perpage; 
			
			/* -- Register the pagination -- */ 
			$this->set_pagination_args( array(
					"total_items" => $totalitems,
					"total_pages" => $totalpages,
					"per_page" => $perpage,
				) 
			);
			
			// Setup the columns
			$screen = get_current_screen();
			$this->_column_headers=array($this->get_columns(),array(),$this->get_sortable_columns(),'col_quote_text');

			// Fetch the items for this page
			$this->items = $wpdb->get_results($query);
		}
		catch (Exception $e) { echo "An error occurred querying the items.";}
	}
	
	public function process_bulk_actions() {
		global $wpdb,$blogflutter_table_name,$blogflutter_quotes_table;
		if (!current_user_can( 'edit_others_posts' )) return;
		$checked=array_filter( is_array($_GET['quote']) ? $_GET['quote'] : 
					is_array($_POST['quote']) ? $_POST['quote'] : array(), 'is_numeric');
		$action=$this->current_action(); 
		try {
				$count=1;
				foreach($checked as $quote) { 
					if ($action=="share") { $this->my_debug.="select * from $blogflutter_quotes_table where quote_id=$quote ";
						$row=$wpdb->get_row("select * from $blogflutter_quotes_table where quote_id=$quote");
						$tweet=$row->quote_text.' --'.$row->quote_author; 
						$wpdb->replace($blogflutter_table_name, 
							array("tweet_text" => $tweet,"tweet_len" => strlen($tweet),
								"tweet_tags" => '#quote', "tweet_mode" => "Quote", "tweet_seq" =>$count),
							array("%s","%d","%s","%s","%d"));
						$count++;
					}
					if ($action=="delete")
						$wpdb->delete($blogflutter_quotes_table,
							array( "quote_id" => $quote), array("%d"));
				}
			}
		catch (Exception $e) { print("An error occurred in the bulk update."); }
    }	
}

// The main function for managing quotes
// /wp-admin/admin.php?page=blogflutter_admin[&action=quote&quote=#]
function blogflutter_manage_quotes() { 
	global $wpdb,$blogflutter_quotes_table;
	if (!current_user_can( 'edit_others_posts' )) return;

	echo '<h1>All Quotes</h1>';
	echo '<form method="post">';
	
	try {
		// Prepare the dynamic quote list
		$wp_list_table = new blogflutter_Quote_List(); 

		echo '<div id="quote-list">';
		// Handle bulk actions before load / display of quote table
		$wp_list_table->process_bulk_actions();
		$wp_list_table->prepare_items();
		$wp_list_table->display();
	} catch (Exception $e) { echo "An error occured displaying the list.";}
	echo '</div></form>';
}

function blogflutter_install_quotes_table() {
	global $wpdb,$blogflutter_quotes_table;

	try {
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $blogflutter_quotes_table (
			quote_id int(11) NOT NULL AUTO_INCREMENT,
			quote_text text NOT NULL,
			quote_author text NOT NULL,
			PRIMARY KEY  (quote_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		
		// Check if we need to load the quotes
		$rows=$wpdb->get_row("select count(*) as numRows from $blogflutter_quotes_table");
		if ($rows->numRows==0) {
			include('quotes_source_v20.php');
		}
	} catch (Exception $e) {}
}

?>