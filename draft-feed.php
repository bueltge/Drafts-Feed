<?php
/**
 * Plugin Name: Drafts Feed
 * Plugin URI:  http://bueltge.de/wordpress-feed-fuer-entwuerfe/829/
 * Description: Add a new Feed for drafts: <code>/?feed=drafts</code> or with active permalinks <code>/feed/drafts</code>
 * Version:     1.1.0
 * Author:      Frank Bültge
 * Author URI:  http://bueltge.de/
 * Licence:     GPLv2
 * Last Change: 03/04/2014
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
		
		protected static $classobj  = NULL;
		
		public static $feed_slug    = 'drafts';
		
		public static $widget_slug  = 'dashboard_recent_drafts_all_authors';
		
		public static $options_slug = 'draft_feed_options';
		
		/**
		* Handler for the action 'init'. Instantiates this class.
		* 
		* @access  public
		* @return  $classobj
		*/
		public static function init() {
			
			NULL === self::$classobj && self::$classobj = new self();
			
			return self::$classobj;
		}
		
		/**
		 * Constructor, init in WP
		 * 
		 * @return  void
		 */
		public function __construct() {
			
			$options = $this->get_options();
			
			// if options allow the draft feed
			if ( 1 === $options[ 'feed' ] ) {
				// add custom feed
				add_action( 'init', array( $this, 'add_draft_feed' ) );
				// change query for custom feed
				add_action( 'pre_get_posts', array( $this, 'feed_content' ) );
			}
			
			// add dashboard widget
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget') );
			// add multilingual possibility, load lang file
			add_action( 'admin_init', array( $this, 'textdomain') );
		}
		
		/**
		 * Load language file for translations
		 * 
		 * @return  void
		 */
		public function textdomain() {
			
			$locale = get_locale();
			
			if ( 'en_US' === $locale )
				return;
			
			load_plugin_textdomain( 'draft_feed', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}
		
		/**
		 * Return the drafts
		 * 
		 * @param   Integer $post_per_page for count of drafts
		 * @return  Array 
		 */
		public function get_drafts() {
			
			$options = $this->get_options();
			
			$args = array(
				'post_type'      => 'post',
				'post_status'    => 'draft',
				'posts_per_page' => $options[ 'posts_per_page' ],
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
			
			if ( ! $drafts )
				$drafts = $this->get_drafts();
			
			if ( $drafts && is_array( $drafts ) ) {
				
				$count = (int) wp_count_posts()->draft;
				
				$list = array();
				foreach ( $drafts as $draft ) {
					$url    = get_edit_post_link( $draft->ID );
					$title  = _draft_or_post_title( $draft->ID );
					$user   = get_userdata($draft->post_author);
					$author = $user->display_name;
					$item = "<h4><a href='$url' title='" . sprintf( __( 'Edit &#8220;%s&#8221;' ), esc_attr( $title ) ) . "'>" . esc_html($title) . "</a> <small><abbr title='" . get_the_time(__('Y/m/d g:i:s A'), $draft) . "'>" . get_the_time( get_option( 'date_format' ), $draft ) . '</abbr></small></h4>';
					if ( $the_content = preg_split( '#[\r\n\t ]#', strip_tags( $draft->post_content ), 11, PREG_SPLIT_NO_EMPTY ) )
						$item .= '<p>' . join( ' ', array_slice( $the_content, 0, 10 ) ) . ( 10 < count( $the_content ) ? '&hellip;' : '' ) . '</p>';
					$list[] = $item;
				}
			?>
			<ul>
				<li><?php echo join( "</li>\n<li>", $list ); ?></li>
			</ul>
			<p class="textright"><a href="edit.php?post_status=draft" class="button"><?php printf( __( 'View all %d', 'draft_feed' ), $count ); ?></a></p>
			<?php
			} else {
				
				_e( 'There are no drafts at the moment', 'draft_feed' );
			}
		}
		
		/**
		 * Control for settings
		 * 
		 * @return String
		 */
		public function widget_settings() {
			
			$options = $this->get_options();
			
			// validate and update options
			if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) 
					 && isset( $_POST[ 'widget_id' ] ) && self::$widget_slug == $_POST[ 'widget_id' ]
					) {
				
				// reset
				$options[ 'feed' ] = 0;
				
				if (  $_POST[ 'feed' ] )
					$options[ 'feed' ] = (int) $_POST[ 'feed' ];
				
				if (  $_POST[ 'posts_per_page' ] )
					$options[ 'posts_per_page' ] = (int) $_POST[ 'posts_per_page' ];
				
				update_option( self::$options_slug, $options );
			}
			?>
			<p>
				<label>
					<input type="checkbox" name="feed" value="1" <?php checked( 1, $options[ 'feed' ] ); ?> /> <?php _e( 'Create Draft Feed?', 'draft_feed' ); ?>
				</label>
			</p>
			<p>
				<label for="posts_per_page">
					<input type="text" id="posts_per_page" name="posts_per_page" value="<?php esc_attr_e( $options[ 'posts_per_page' ] ); ?>" size="2" /> <?php _e( 'How many items show inside the dashboard widget?', 'draft_feed' ); ?>
				</label>
			</p>
			<?php
		}
		
		/**
		 * Get options
		 * 
		 * @return  Array
		 */
		public function get_options() {
			
			$defaults = array(
				'feed'           => 1,
				'posts_per_page' => 5
			);
			
			$args = wp_parse_args(
				get_option( self::$options_slug ),
				apply_filters( 'draft_feed_options', $defaults )
			);
			
			return $args;
		}
		
		/**
		 * Add Dashbaord widget
		 * 
		 * @return  void
		 */
		public function add_dashboard_widget() {
			
			wp_add_dashboard_widget(
				self::$widget_slug,
				__( 'Recents Drafts', 'draft_feed' ) . 
				' <small>' . __( 'of all authors', 'draft_feed' ) . '</small>',
				array( $this, 'dashboard_recent_drafts' ), // content
				array( $this, 'widget_settings' ) // control
			);
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
