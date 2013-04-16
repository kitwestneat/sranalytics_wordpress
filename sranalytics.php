<?php
/**
 Plugin Name: SimpleReach Analytics
 Plugin URI: https://www.simplereach.com
 Description: After installation, you must click '<a href='options-general.php?page=SimpleReach-Analytics'>Settings &rarr; SimpleReach Analytics</a>' to turn on the Analytics.
 Version: 0.0.2
 Author: SimpleReach
 Author URI: https://www.simplereach.com
 */

define('SRANALYTICS_PLUGIN_VERSION', '0.0.2');
define('SRANALYTICS_PLUGIN_URL', PLugin_dir_url(__FILE__));
define('SRANALYTICS_PLUGIN_SUPPORT_EMAIL', 'support@simplereach.com');

/**
 * Insert analytics code onto the post page
 *
 * @author Malaney Hill <engineering@simplereach.com>
 * @param String $content the_content() output
 * @return String The content and The Slide code (if applicable)
 */
function sranalytics_insert_js($content)
{
    $sranalytics_pid = get_option('sranalytics_pid');

    // Try and check the validity of the PID
    if (empty($sranalytics_pid) || strlen($sranalytics_pid) != 24) {
        return $content;
    }

    // Return the content on anything other than post pages
    if (!is_singular()) {
        return $content;
    }

    // Skip attachments and only show on posts
    if (is_attachment()) {
        return $content;
    }

    global $post;
    $post_id = $post->ID;

    // If the post isn't published yet, we don't need a slide
    if ($post->post_status != 'publish') {
        return $content;
    }

    $SRANALYTICS_PLUGIN_VERSION = SRANALYTICS_PLUGIN_VERSION;

    // Prep the variables
    $title = sranalytics_get_post_title($post);
    $authors = sranalytics_get_post_authors($post);
    $tags = sranalytics_get_post_tags($post);
    $channels = sranalytics_get_post_channels($post);
    $published_date = $post->post_date_gmt;
    $canonical_url = addslashes(get_permalink($post->ID));

// Get the JS ready to go
$rv = <<< SRANALYTICS_SCRIPT_TAG
<!-- SimpleReach Analytics Plugin Version: {$SRANALYTICS_PLUGIN_VERSION} -->
<script type='text/javascript' id='simplereach-analytics-tag'>
    __reach_config = {
      pid: '${sranalytics_pid}',
      title: '{$title}',
      url: '${canonical_url}',
      date: '${published_date}',
      authors: {$authors},
      channels: {$channels},
      tags: {$tags}
    };
    (function(){
      var s = document.createElement('script');
      s.async = true;
      s.type = 'text/javascript';
      s.src = document.location.protocol + '//simple-cdn.s3.amazonaws.com/js/reach.js';
      (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(s);
    })();
</script>
SRANALYTICS_SCRIPT_TAG;

    return $content . $rv;
}

/**
 * Return the title for the post
 *
 * @author Eric Lubow <elubow@simplereach.com>
 * @param Object $post Post object being shown on the page
 * @return String Title of the post with slashes escaped
 */
function sranalytics_get_post_title($post)
{
    $title = $post->post_title;
    return addslashes($title);
}


/**
 * Get the post authors and return them in stringified array form
 * NOTE: Wordpress can currently only have on author per post.
 *
 * @author Eric Lubow <elubow@simplereach.com>
 * @param Object $post Wordpress Post
 * @return String $array A string representation of the array of authors
 */
function sranalytics_get_post_authors($post)
{
    $author = "'".addslashes(get_the_author())."'";
    return "[{$author}]";
}



/**
 * Get the post categories and return them in stringified array form
 *
 * @author Eric Lubow <elubow@simplereach.com>
 * @param Object $post Wordpress Post
 * @return String $array A string representation of the array of categories
 */
function sranalytics_get_post_channels($post)
{
    $post_categories = wp_get_post_categories($post->ID);
    $myCats = array();
    foreach ($post_categories as $c) {
        $cat = get_category($c);
        $myCats[] = "'".addslashes($cat->slug)."'";
    }
    $cats = join(',', $myCats);

    return "[{$cats}]";
}


/**
 * Return the tags for the post
 *
 * @author Eric Lubow <elubow@simplereach.com>
 * @param Object $post Post object being shown on the page
 * @return String Tags of the post with slashes escaped
 */
function sranalytics_get_post_tags($post)
{
    $wptags = wp_get_post_tags($post->ID);
    $myTags = array();

	// Check to see if we are using the global tag
	$sranalytics_show_global_tag = get_option('sranalytics_show_global_tag');
	$sranalytics_global_tag = get_option('sranalytics_global_tag');
	if ($sranalytics_show_global_tag) {
		array_push($myTags, "'$sranalytics_global_tag'");
	}

    foreach ($wptags as $tag) {
        $myTags[] = (is_object($tag)) ? "'".addslashes($tag->name)."'" : "'".addslashes($tag)."'";
    }
	$tags = join(',', $myTags);
	
    return "[{$tags}]";
}



/**
 * Add the SimpleReach admin section
 *
 * @author Malaney Hill <engineering@simplereach.com>
 * @param None
 * @return None
 */
function sranalytics_load_admin()
{
    include_once('sranalytics_admin.php');
}

/**
 * Add the SimpleReach admin options to the Settings Menu
 *
 * @author Malaney Hill <engineering@simplereach.com>
 * @param None
 * @return None
 */
function sranalytics_admin_actions()
{
    add_options_page("SimpleReach Analytics", "SimpleReach Analytics", 1, "SimpleReach-Analytics", "sranalytics_load_admin");
}

/**
 * Setup the locales for i18n
 *
 * @author Malaney Hill <engineering@simplereach.com>
 * @param None
 * @return None
 */
function sranalytics_textdomain()
{
    $locale        = apply_filters( 'sranalytics_locale', get_locale() );
    $mofile        = sprintf( 'sranalytics-%s.mo', $locale );
    $mofile_local  = SRanalytics_PLUGIN_DIR . '/lang/' . $mofile;

    if (file_exists($mofile_local)) {
        return load_textdomain( 'sranalytics', $mofile_local );
    } else {
        return false;
    }
}

/**
 * Get the current page URL
 *
 * @author Eric Lubow <engineering@simplereach.com>
 * @param None
 * @return None
 */
function current_page_url() {
	$pageURL = 'http';
	if( isset($_SERVER["HTTPS"]) ) {
		if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
	}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

/**
 * Run the appropriate actions on hooks
 *
 * @author Malaney Hill <engineering@simplereach.com>
 * @param None
 * @return None
 */
function sranalytics_loaded()
{
    do_action('sranalytics_loaded');
}

/**
 * Insert the SimpleReach Javascript on non-content pages
 *
 * @author Eric Lubow <engineering@simplereach.com>
 * @param None
 * @return None
 */
function sranalytics_insert_noncontent_js()
{
	// Ignore search or feeds
#	if(!is_search() || !is_feed() || !is_singular() || !is_attachment()) {
	if(is_search() || is_feed() || is_singular() || is_attachment()) {
		return;
	}

	$SRANALYTICS_PLUGIN_VERSION = SRANALYTICS_PLUGIN_VERSION;

	$sranalytics_pid = get_option('sranalytics_pid');

	$authors = '[]';
	$tags = '[]';
	$channels = '[]';

	if (is_tag()) {
		$term = get_query_var('tag');	
		$tags = "['".addslashes($term)."']";
	} else if (is_category()) {
		$term_id = get_query_var('cat');	
		$term = get_category($term_id);
		$channels = "['".addslashes($term->slug)."']";
	} else if (is_author()) {
		// XXX Need to figure out the query variable for author
		$term_id = get_query_var('tag');	
#		$term = get_author($term_id);
		$authors = "['".addslashes(the_author())."']";
	}


    $title = get_the_title();
    $published_date = date("c");
    $canonical_url = addslashes(current_page_url());

// Get the JS ready to go
echo <<< SRANALYTICS_SCRIPT_TAG
<!-- SimpleReach Analytics Plugin Version: {$SRANALYTICS_PLUGIN_VERSION} -->
<script type='text/javascript' id='simplereach-analytics-tag'>
    __reach_config = {
      pid: '${sranalytics_pid}',
      title: '{$title}',
      url: '${canonical_url}',
      date: '${published_date}',
      authors: {$authors},
      channels: {$channels},
      tags: {$tags}
    };
    (function(){
      var s = document.createElement('script');
      s.async = true;
      s.type = 'text/javascript';
      s.src = document.location.protocol + '//simple-cdn.s3.amazonaws.com/js/reach.js';
      (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(s);
    })();
</script>
SRANALYTICS_SCRIPT_TAG;

return true;
}
add_action('wp_footer','sranalytics_insert_noncontent_js');
add_filter('the_content', 'sranalytics_insert_js');
add_action('admin_menu','sranalytics_admin_actions');
?>
