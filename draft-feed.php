<?php
/**
 * Plugin Name: Drafts Feed
 * Plugin URI:  http://bueltge.de/wordpress-feed-fuer-entwuerfe/829/
 * Description: Add a new Feed for drafts: <code>/?feed=drafts</code>
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
			
			$drafts_query = new WP_Query( array(
				'post_type' => 'post',
				'post_status' => 'draft',
				'posts_per_page' => $posts_per_page,
				'orderby' => 'modified',
				'order' => 'DESC'
			) );
			
			return $drafts_query->posts;
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
		 * Add feed with key 'drafts'
		 * 
		 * @return  void
		 */
		public function add_draft_feed() {
			
			// set name for the feed
			// http://examble.com/?feed=drafts
			add_feed( 'drafts', array( $this, 'get_draft_feed') );
		}
		
		
		/**
		 * Create RSS2 feed
		 * 
		 * @return void
		 */
		public function get_draft_feed() {
			
			$items = $this->get_drafts( 20 );
			
			if ( ! headers_sent() )
				header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), TRUE );
			$more = 1;
		
		echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>'; ?>

<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	<?php do_action('rss2_ns'); ?>
>

<channel>
	<title><?php bloginfo_rss( 'name' ); wp_title_rss(); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss( 'url' ) ?></link>
	<description><?php bloginfo_rss( 'description' ) ?></description>
	<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false ); ?></pubDate>
	<generator>http://bueltge.de/</generator>
	<language><?php echo get_option( 'rss_language' ); ?></language>
	<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
	<?php do_action('rss2_head'); ?>
	<?php
	if ( empty($items) ) {
		echo '<!-- No submissions found yet. //-->';
	} else {
		foreach ($items as $item) {
	?>
		<item>
			<title><?php echo stripslashes( apply_filters( 'comment_author', $item->post_title ) ); ?></title>
			<link><?php echo stripslashes( apply_filters( 'comment_author_url', get_permalink($item->ID) ) ); ?></link>
			<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', $item->post_date ); ?></pubDate>
			<dc:creator><?php echo stripslashes( apply_filters('comment_author', $item->post_author) ); ?></dc:creator>

			<guid isPermaLink="false"><?php echo stripslashes( 
				apply_filters('comment_author_url', $item->guid)
			); ?></guid>
			<?php if ( $item->post_excerpt != '' ) { ?>
			<description><![CDATA[<?php echo trim(
				stripslashes( apply_filters('comment_text', $item->post_excerpt) )
			); ?>]]></description>
			<?php } else { ?>
			<description><![CDATA[<?php echo strip_tags(
				trim( stripslashes( apply_filters('comment_text', $item->post_content) ) )
			); ?>]]></description>
			<?php } ?>
			<content:encoded><![CDATA[<?php echo trim(
				stripslashes( apply_filters( 'comment_text', $item->post_content ) )
			); ?>]]></content:encoded>
			<?php do_action( 'rss2_item' ); ?>
		</item>
	<?php
		} 
	}
	?>
	</channel>
</rss>
	<?php
		}
	
	} // end class
} // end if class exists