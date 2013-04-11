<?php
/**
 * Plugin Name: Drafts Feed
 * Plugin URI:  http://bueltge.de/wordpress-feed-fuer-entwuerfe/829/
 * Description: Add a new Feed for drafts: <code>/?feed=drafts</code> or with active permalinks <code>/feed/drafts</code>
 * Version:     1.0.1
 * Author:      Frank Bültge
 * Author URI:  http://bueltge.de/
 * Licence:     GPLv3
 * Last Change: 04/11/2013
 */

//avoid direct calls to this file, because now WP core and framework has been used
if ( ! function_exists( 'add_filter' ) ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

if ( ! class_exists( 'Draft_Feed' ) ) {
	add_action( 'plugins_loaded', array( 'Draft_Feed', 'init' ) );
	
	class Draft_Feed {
		
		protected static $classobj = NULL;
		
		public static $feed_slug = 'drafts';
		
		/**
		* Handler for the action 'init'. Instantiates this class.
		* 
		* @access  public
		* @return  $classobj
		*/
		public static function init() {
			
			NULL === self::$classobj and self::$classobj = new self();
			
			return self::$classobj;
		}
		
		/**
		 * Constructor, init in WP
		 * 
		 * @return  void
		 */
		public function __construct() {
			
			add_action( 'init', array( &$this, 'add_draft_feed' ) );
			
			add_action( 'pre_get_posts', array( $this, 'feed_content' ) );
			
			if ( is_admin() ) {
				add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget') );
				add_action( 'admin_head', array( $this, 'add_my_css') );
				add_action( 'admin_init', array( $this, 'textdomain') );
			}
		}
		
		/**
		 * Load language file for translations
		 * 
		 * @return  void
		 */
		public function textdomain() {
			
			load_plugin_textdomain( 'draft_feed', FALSE, dirname( plugin_basename(__FILE__) ) . '/languages' );
		}
		
		/**
		 * Return the drafts
		 * 
		 * @param   Integer $post_per_page for count of drafts
		 * @return  Array 
		 */
		public function get_drafts( $posts_per_page = 5 ) {
			
			$args = array(
				'post_type'      => 'post',
				'post_status'    => 'draft',
				'posts_per_page' => (int) $posts_per_page,
				'orderby'        => 'modified',
				'order'          => 'DESC'
			);
			
			$drafts_query = new WP_Query( $args );
			
			return $drafts_query->posts;
		}
		
		/**
		 * Customizes the query.
		 * It will bail if $query is not an object, filters are suppressed 
		 * and it's not our feed query.
		 *
		 * @param  WP_Query $query The current query
		 * @return void
		 */
		public function feed_content( $query ) {
			// Bail if $query is not an object or of incorrect class
			if ( ! $query instanceof WP_Query )
				return;

			// Bail if filters are suppressed on this query
			if ( $query->get( 'suppress_filters' ) )
				return;
			
			// Bail if it's not our feed
			if ( ! $query->is_feed( self::$feed_slug ) )
				return;
			
			$query->set( 'post_status', array( 'draft' ) );
			$query->set( 'orderby', 'modified' );
		}
		
		/**
		 * Get dashbaord content
		 * 
		 * @param  Array $drafts
		 * @return void
		 */
		public function dashboard_recent_drafts( $drafts = FALSE ) {
			
			if ( $drafts )
				return;
				
			$drafts = $this->get_drafts();
			
			if ( $drafts && is_array( $drafts ) ) {
				
				$list = array();
				foreach ( $drafts as $draft ) {
					$url    = get_edit_post_link( $draft->ID );
					$title  = _draft_or_post_title( $draft->ID );
					$user   = get_userdata($draft->post_author);
					$author = $user->display_name;
					$item   = '<a href="' . $url . '" title="'
						. sprintf( __( 'Edit &#8220;%s&#8221;', 'draft_feed' ), esc_attr( $title ) ) . '">' 
						. $title . '</a> ' . __( 'by', 'draft_feed' ) . ' ' 
						. stripslashes( apply_filters( 'comment_author', $author ) ) 
						. ' <abbr title="' . get_the_time( __( 'Y/m/d g:i:s A' ), $draft ) . '">' 
						. get_the_time( get_option( 'date_format' ), $draft ) . '</abbr>';
					$list[] = $item;
				}
			?>
			<ul>
				<li><?php echo join( "</li>\n<li>", $list ); ?></li>
			</ul>
			<p class="textright"><a href="edit.php?post_status=draft" class="button"><?php _e( 'View all', 'draft_feed' ); ?></a></p>
			<?php
			} else {
				
				_e( 'There are no drafts at the moment', 'draft_feed' );
			}
		}
		
		/**
		 * Add Dashbaord widget
		 * 
		 * @return  void
		 */
		public function add_dashboard_widget() {
			
			wp_add_dashboard_widget(
				'dashboard_recent_all_drafts',
				__( 'Recents Drafts', 'draft_feed' ) . ' <small>' 
				 . __( 'of all authors', 'draft_feed' ) . '</small>',
				array( $this, 'dashboard_recent_drafts')
			);
		}
		
		/**
		 * Add custom css, inline
		 * 
		 * @return  String $output
		 */
		public function add_my_css() {
			
			$output  = '';
			$output .= "\n";
			$output .= '<style type="text/css">'."\n";
			$output .= '<!--'."\n";
			$output .= '#dashboard_recent_drafts abbr {' . "\n";
			$output .= 'font-family: "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif;' . "\n";;
			$output .= 'font-size: 11px;' . "\n";
			$output .= 'color: #999;' . "\n";
			$output .= 'margin-left: 3px;' . "\n";
			$output .= '}'."\n";
			$output .= '-->'."\n";
			$output .= '</style>'."\n";
			
			echo $output;
		}
		
		
		/**
		 * Add feed with key
		 * Use as key the var $feed_strings
		 * 
		 * @return  void
		 */
		public function add_draft_feed() {
			
			// set name for the feed
			// http://examble.com/?feed=drafts
			add_feed( self::$feed_slug, array( $this, 'get_feed_template' ) );
		}
		
		/**
		 * Loads the feed template
		 * 
		 * @return  void
		 */
		public function get_feed_template() {
			
			load_template( ABSPATH . WPINC . '/feed-rss2.php' );
		}
	
	} // end class
} // end if class exists