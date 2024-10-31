<?php
/**
 * Plugin Name: Products for Holvi
 * Plugin URI: http://www.delektre.fi/products-for-holvi/
 * Description: Simple plugin to create a simple text-only list from Holvi (https://holvi.com) to wordpress shortcode. For HTML parsing the Pharse -parser is used, from https://github.com/ressio/pharse by
 * Version: 1.3
 * Author: Tommi Rintala (Delektre Ltd)
 * Author URI: https://profiles.wordpress.org/softanalle/
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */


define( 'PROFORHOL_DEBUG', false );      // Enable plugin debugging?
define( 'PROFORHOL_USE_CACHE', false );  // TODO: Implement cache feature
// define('PROFORHOL_CACHE_DB', '');
define( 'PROFORHOL_LOG_PREFIX', 'products-for-holvi: ' );
define( 'PROFORHOL_DOMAIN', 'proforhol_textdomain' );

// field/attribute names
define( 'PROFORHOL_F_STRATEGY', 'strategy' );
define( 'PROFORHOL_F_TITLE', 'title' );
define( 'PROFORHOL_F_ORDER', 'sort' );
define( 'PROFORHOL_F_URL', 'url' );
define( 'PROFORHOL_F_ERROR', 'show_error' );

// fetch strategies
define( 'PROFORHOL_O_WORDPRESS', 'wordpress' );
define( 'PROFORHOL_O_INTERNAL', 'internal' );
define( 'PROFORHOL_O_CURL', 'curl' );

// check result that inclusion of Pharse library is ok, and therefore
// safe to run plugin:
$proforhol_has_pharse_lib = false;



function proforhol_admin_notice() {
    /* If we don't have Pharse library, or there is wrong version of it
     * show notice to admin, since plugin is not working
     */
    if ( is_admin() ) {
?>
        <div class="error notice">
        <p><?php
        _e( 'There are conflicting versions used of the Pharse '.
        '-library. The <strong>Products For Holvi</strong> -plugin cannot be'.
        ' run and is disabled.', PROFORHOL_DOMAIN ); ?></p>
        </div>
<?php
    }
}

if ( !class_exists( 'Pharse' ) ) {
    include_once 'pharse.php';
    // Check the library version (by checking that it has needed funcs)
    if (
        !(
            method_exists( 'Pharse', 'str_get_dom' ) &&
            method_exists( 'Pharse', 'file_get_dom' ) &&
            method_exists( 'Pharse', 'dom_format' )
        )
    ) {
        // Check failed, notice admin
        add_action( 'admin_notices', 'proforhol_admin_notice' );
    } else {
        $proforhol_has_pharse_lib = true;
    }
}


/**
 * Make sure we have a way to log to Wordpress logfile
 */
if ( !function_exists( 'write_log' ) ) {    
    function write_log( $log ) {
        if ( defined( 'PROFOLHOL_DEBUG' ) && true == PROFORHOL_DEBUG ) {
            if (is_array( $log ) || is_object( $log )) {
                error_log( PROFORHOL_LOG_PREFIX . print_r( $log, true ) );
            } else {
                error_log( PROFORHOL_LOG_PREFIX . $log );
            }
        }
    }
}


/**
 * Fetch remote URL. Only http/https protocols are accepted
 *
 * @param url URL string or empty to use default URI
 * @return string|array Target content or empty string, if error occures
 */
function proforhol_fetch_url( $url = '', $strategy = PROFORHOL_O_WORDPRESS, $show_error = false ) {
    // check acceptable protocols
    $safe_url = esc_url( $url, array( 'https', 'http' ), 'fetch' );
    
    // be (relatively) sure that we are asking from safe (Holvi) URL
    if ( $safe_url != '' && preg_match( '/holvi/i', $safe_url ) > 0 ) {

        if (PROFORHOL_DEBUG) {
            $start_ts = microtime();
            write_log( "Begin requrest from URL: $safe_url" );
        }
        
        /* Note:
         * We cannot use here wp_safe_remote_*() -calls, since
         * (1) safe url filters can block them
         * (2) we should not change the safe url -filter(s) from code and
         * (3) if content editor wishes to add a request from remote host, it
         * is in the best interest of content editor to make sure that the
         * URL is safe
         */
        switch( $strategy ) {
        case PROFORHOL_O_INTERNAL:
            $body = file_get_contents( $safe_url );
            break;
        case PROFORHOL_O_CURL:
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_HEADER, false );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
            curl_setopt( $ch, CURLOPT_URL, $safe_url );
            curl_setopt( $ch, CURLOPT_REFERER, $safe_url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            $body = curl_exec( $ch );
            curl_close( $ch );
            break;
        case PROFORHOL_O_WORDPRESS:
        default:
            $response = wp_remote_get( $safe_url );
            $body = wp_remote_retrieve_body( $response );
            $http_code = wp_remote_retrieve_response_code( $response );
            if ( $show_error && $http_code != 200 ) {
                print "<div class=\"error\">Products For Holvi: Error code: $http_code</div>";
            }
        }
        
        if (PROFORHOL_DEBUG) {
            $end_ts = microtime();
            write_log( 'Request took ' . abs( $end_ts - $start_ts ) . ' ms' );
        }
        
        return $body;
    }
    write_log( "Invalid URL given: $url" );
    return '';
}

/**
 * Fetch and parse given URL.
 *
 * @param url String pointing to URL
 * @return Array containing each product as array element, or empty array if no results
 */
function proforhol_parse_url( $url = '', $strategy = '', $show_errors = false ) {
    if ( PROFORHOL_USE_CACHE ) {
        // TODO: implement simple cache to avoid excess re-loading
    }
    $src = proforhol_fetch_url( $url, $strategy, $show_errors );
    if ( is_null( $src ) or $src == '' ) {
        write_log( "Got empty result from request: '$url'" );
    }
    
    if ( is_array( $src ) ) {
        $src = join( '', $src );
    }

    // initialize return array
    $products = array();

    if ( trim($src) != '' ) {
        $html = Pharse::str_get_dom( $src );
        if ( $html->select( '"!DOCTYPE"', 0 ) ) {
            // if we have doctype, delete if
            $html->select( '"!DOCTYPE"', 0 )->delete();
        }
        if ( $html->select( 'head', 0 ) ) {
            // if we have header -delete it
            $html->select( 'head', 0 )->delete();
        }
        $data = array();
        $index = 0;
        foreach ( $html->select( 'div#purchase-area div.store-item' ) as $item ) {
            $title = ''; $url = ''; $price = ''; $stock = ''; $soldout = '';
            $desc = '';
            $value = $item->select( 'p.store-item-name', 0 );
            if ( $value != null )
                $title = trim($value->getPlainText());

            $value = $item->select( 'a.store-item-wrapper', 0 );
            if ( $value != null ) {                
                $url = $value->href;
            }

            $value = $item->select( 'span.store-item-price-amount', 0 );
            if ( $value != null ) {
                $price = trim($value->getPlainText());
            }

            $value = $item->select( 'span.store-item-stock', 0 );
            if ( $value != null ) {
                $stock = trim($value->getPlainText());
            }

            $value = $item->select( 'div.store-item-sold-out-banner', 0 );
            if ( $value != null ) {
                $soldout = trim($value->getPlainText());
            }
            $value = $item->select( 'p.store-item-description', 0 );
            if ( $value != null ) {
                $desc = trim($value->getPlainText());
            }
            
            $products[ $index++ ] = array(
                'title'   => $title,
                'price'   => $price,
                'stock'   => $stock,
                'soldout' => $soldout,
                'desc'    => $desc,
                'url'     => $url
            );
        }
    }

    return $products;
}

/**
 * Compare list entries by date (oldest first), only dd.mm.YYYY -format is currently supported.
 * @param $a First array, containing 'title' key 
 * @param $b Second array, containing also 'title' key
 * @return int -1 if first array is smaller, 1 if second array entry is smaller
 */
function proforhol_sort_by_date( $a, $b ) {
    preg_match( '/(\d+)\.(\d+)\.(\d+)/', $a['title'], $a_re );
    preg_match( '/(\d+)\.(\d+)\.(\d+)/', $b['title'], $b_re );
    if ( count( $a_re ) > 0 && count( $b_re ) > 0 ) {
        $d = mktime( $a_re[3], $a_re[2], $a_re[1] );
        $e = mktime( $b_re[3], $b_re[2], $b_re[1] );
        return ( $d < $e ) ? -1 : 1;
    }
    return -1;
}

/**
 * Compare product entries alphabetically, based on product title
 *
 * @param $a first product array entry
 * @param $b second product array entry
 * @return int Odd cases returns 0, if first entry -1, if second entry +1
 */
function proforhol_sort_by_alpha( $a, $b ) {
    if ( array_key_exists( 'title', $a ) &&
    array_key_exists( 'title', $b ) )
        return strcmp( $a['title'], $b['title'] ); // ? -1 : 1;
    return 0;
}

/**
 * This function implements the actual shortcode. Simple and crude
 * 
 * @param atts Array containing attributes and values
 * @param content Any content between shortcode tags
 * @param tag The name of tag
 * @return String Product listing as HTML code
 *
 * Example of short code:
 * [products_for_holvi url="http://www.delektre.fi/" title="example"]
 * ...sample text before list...
 * [/products_for_holvi]
 */

function proforhol_shortcode( $atts = [], $content = null, $tag = '' )
{
// normalize attribute keys, lowercase
    $atts = array_change_key_case( (array) $atts, CASE_LOWER );

    // override default attributes with user attributes
    $proforhol_atts = shortcode_atts(
        array(
            PROFORHOL_F_TITLE => '',
            PROFORHOL_F_URL => 'http://wordpress.delektre.fi/products-for-holvi/',
            PROFORHOL_F_ORDER => 'none',
            PROFORHOL_F_STRATEGY => PROFORHOL_O_WORDPRESS
        ), $atts, $tag );

    $show_error = false;
    if ( isset( $atts['show_error'] ) && preg_match('/(1|true|yes)/i', $atts['show_error'] ) > 0 ) {
        $show_error = true;
    }
    
    // start output
    $o = '';

    // start box
    $o .= '<div class="proforhol-box">';

    // title
    if ( $proforhol_atts[PROFORHOL_F_TITLE] != '' ) {
        $o .= '<h2 class="proforhol-title">' .
            esc_html__( $proforhol_atts[PROFORHOL_F_TITLE], 'proforhol' ) . '</h2>';
    }

    // enclosing tags
    if ( !is_null( $content ) ) {
        // secure output by executing the_content filter hook on $content
        $o .= apply_filters( 'the_content', $content );

        // run shortcode parser recursively
        //$o .= do_shortcode($content);
    }

    // fill in our content

    
    $data = proforhol_parse_url(
        $proforhol_atts[PROFORHOL_F_URL],
        $proforhol_atts[PROFORHOL_F_STRATEGY],
        $show_error
    );

    if ( count( $data ) == 0 ) {
        // If we receive no product data
        write_log( "No response from remote server, show 'no data'" );
        $o .= '<b class="proforhol-list">No product data</b>';
    } else {

        switch ( $proforhol_atts[PROFORHOL_F_ORDER] ) {
        case 'date':
            uasort( $data, 'proforhol_sort_by_date' );
            break;
        case 'alpha':
            uasort( $data, 'proforhol_sort_by_alpha' );
            break;
        case 'none':
        default:
        }
        $o .= '<ul class="proforhol-list">';

        foreach ( $data as $item ) {
            if ( $item['soldout'] != '' ) {
                $soldout_flag = " proforhol-soldout";
                $link = '<span class="proforhol-item-soldout' .
                    $soldout_flag . '">' .
                    $item[PROFORHOL_F_TITLE] . ' (' . $item['soldout'] . ')' .
                    '</span> ';
            } else {
                $soldout_flag = false;
                $link = '<a class="proforhol-item-link' . $soldout_flag .
                    '" href="' . $item[PROFORHOL_F_URL] . '">' .
                    '<span class="proforhol-item-title' . $soldout_flag . '">' .
                    $item[PROFORHOL_F_TITLE] .
                    '</span></a> ';
            }
            
            $o .= '<li class="proforhol-list-item' . $soldout_flag . '">'.
                $link .
                '<span class="proforhol-item-price' . $soldout_flag . '">' .
                $item['price'] .
                '</span> ' .
                '<span class="proforhol-item-description' . $soldout_flag . '">'
                .$item['desc'] .
                '</span>' .
                '</li>';
            
            // $o .= $line;
        }
        $o .= '</ul>';
    }
    
    // end box
    $o .= '</div>';
    
    // return output
    return $o;
}

/**
 * Simple Widget container for product listing
 */
class ProductsForHolvi_Widget extends WP_Widget
{
    /**
     * Class initialization
     */
    public function __construct() {
        parent::__construct(
            'proforhol_widget',
            __( 'Products For Holvi', PROFORHOL_DOMAIN ),
            array(
                'description' => 'Product listing widget'
            )
        );
    }

    /**
     * Output product listing to widget container
     * @param $args Array of widget arguments
     * @param $instance Array instance values
     */
    public function widget( $args = array(), $instance = '' ) {
        $widget_title = apply_filters( 'widget_title', $instance[PROFORHOL_F_TITLE] );
        $widget_atts = wp_parse_args(
            $instance,
            array(
                PROFORHOL_F_TITLE => '',
                PROFORHOL_F_URL => 'http://wordpress.delektre.fi/products-for-holvi/',
                PROFORHOL_F_ORDER => 'none',
                PROFORHOL_F_STRATEGY => PROFORHOL_O_WORDPRESS
            )
        );
        if ( strlen( $widget_title ) > 0 ) {
            $widget_atts[PROFORHO_F_TITLE] = $widget_title;
        }
        
        // check sort order
        if (! (isset( $widget_atts[PROFORHOL_F_ORDER]) && in_array( $widget_atts[PROFORHOL_F_ORDER], array( 'alpha', 'date', 'none' ) ) ) ) {
            $widget_atts[PROFORHOL_F_ORDER] = 'none';
        }          

        if (array_key_exists( 'before_widget', $args ) ) {
            echo $args['before_widget'];
        }

        $html = proforhol_shortcode( $widget_atts );
        echo $html;
        
        if (array_key_exists( 'after_widget', $args ) ) {
            echo $args['after_widget'];
        }
    }

    /**
     *  Management interface form
     * @param $item Array of saved values
     */
    public function form( $item = array() ) {
        $widget_title = '';
        $url_value = '';
        $strategy_value = '';
        
        if ( isset( $item[PROFORHOL_F_TITLE] ) ) {
            $widget_title = $item[PROFORHOL_F_TITLE];
        }
        if ( isset( $item[PROFORHOL_F_URL] ) ) {
            $url_value = $item[PROFORHOL_F_URL];
        }
        if ( isset( $item[PROFORHOL_F_STRATEGY] ) && (
            strcasecmp( PROFORHOL_O_CURL, $item[PROFORHOL_F_STRATEGY]) == 0 or
            strcasecmp( PROFORHOL_O_WORDPRESS, $item[PROFORHOL_F_STRATEGY]) == 0 or
            strcasecmp( PROFORHOL_O_INTERNAL, $item[PROFORHOL_F_STRATEGY]) == 0 ) ) {
            $strategy_value = $item[PROFORHOL_F_STRATEGY];
        }
        $title_id = $this->get_field_id( PROFORHOL_F_TITLE );
        $sort_id = $this->get_field_id( PROFORHOL_F_ORDER );
        $url_id = $this->get_field_id( PROFORHOL_F_URL );
        $strategy_id = $this->get_field_id( PROFORHOL_F_STRATEGY );

        
        ?>
        <p>
          <label for="<?php echo $title_id; ?>"><?php _e( 'Title', PROFORHOL_DOMAIN ); ?></label>
          <input type="text" value="<?php echo esc_attr( $widget_title ); ?>" name="<?php echo $this->get_field_name( PROFORHOL_F_TITLE ); ?>" id="<?php echo $title_id; ?>">
        </p>
        <p>
          <label for="<?php echo $url_id; ?>"><?php _e( 'URL', PROFORHOL_DOMAIN ); ?></label>
          <input type="text" value="<?php echo esc_attr( $url_value ); ?>" name="<?php echo $this->get_field_name( PROFORHOL_F_URL ); ?>" id="<?php echo $url_id; ?>">
        </p>
        <p>
          <label for="<?php echo $sort_id; ?>"><?php _e( 'Sort order', PROFORHOL_DOMAIN ); ?></label>
          <select name="<?php echo $this->get_field_name( PROFORHOL_F_ORDER ); ?>" id="<?php echo $sort_id; ?>">
            <option value="none" <?php selected($sort_value, 'none'); ?>><?php _e( 'None', PROFORHOL_DOMAIN ); ?></option>
            <option value="date" <?php selected($sort_value, 'date'); ?>><?php _e( 'Date', PROFORHOL_DOMAIN ); ?></option>
            <option value="alpha" <?php selected($sort_value, 'alpha'); ?>><?php _e( 'Alpha', PROFORHOL_DOMAIN ); ?></option>
          </select>
        </p>
        <p>
          <label for="<?php echo $strategy_id; ?>"><?php _e(PROFORHOL_F_STRATEGY, PROFORHOL_DOMAIN ); ?></label>
          <select name="<?php echo $this->get_field_name( PROFORHOL_F_STRATEGY ); ?>" id="<?php echo $strategy_id; ?>">
            <option value="wordpress" <?php selected( $strategy_value, PROFORHOL_O_WORDPRESS ); ?>>WordPress Std</option>
            <option value="internal" <?php selected( $strategy_value, PROFORHOL_O_INTERNAL ); ?>>Internal site</option>
<?php
        if ( in_array( 'curl', get_loaded_extensions() ) ) {
?>
            <option value="curl" <?php selected( $strategy_value, PROFORHOL_O_CURL ); ?>>CURL</option>
<?php
        }
?>
          </select>
        </p>
        <p>
          <label for="<?php echo $error_id; ?>"><?php _e( 'Show error (for debugging)', PROFORHOL_DOMAIN ); ?>
          <input type="checkbox" name="<?php echo $this->get_field_name( PROFORHOL_F_ERROR ); ?>" id="<?php echo $error_id; ?>" <?php checked( $error_value, true )?>>
          </label>
        </p>
<?php        
    }

    /**
     * Update values
     * @param new_item Array of new values
     * @param old_item Array of old values
     * @return Array of checked values
     */
    public function update( $new_item = array(), $old_item = array() ) {
        $item = array();
        $item[PROFORHOL_F_TITLE] = strip_tags( $new_item[PROFORHOL_F_TITLE] );
        $item[PROFORHOL_F_URL] = $new_item[PROFORHOL_F_URL];
        $item[PROFORHOL_F_ORDER] = $new_item[PROFORHOL_F_ORDER];
        $item[PROFORHOL_F_STRATEGY] = $new_item[PROFORHOL_F_STRATEGY];
        $item[PROFORHOL_F_ERROR] = $new_item[PROFORHOL_F_ERROR];
        return $item;
    }
}

// Register widget
function proforhol_register_widget() {
    register_widget( 'ProductsForHolvi_Widget' );
}


// Activate widget
add_action( 'widgets_init', 'proforhol_register_widget' );
    

 // Add the shortcode to Wordpress env
function proforhol_shortcodes_init()
{
    add_shortcode( 'products_for_holvi', 'proforhol_shortcode' );
}

// test that we have pharse lib and it is meaningfull to enable functionality
if ( true == $proforhol_has_pharse_lib ) {
    add_action( 'init', 'proforhol_shortcodes_init' );
}
