<?php
/*
Plugin Name: Autoptimize
Plugin URI: https://autoptimize.com/
Description: Optimizes your website, concatenating the CSS and JavaScript code, and compressing it.
Version: 2.4.0-beta1
Author: Frank Goossens (futtta)
Author URI: https://autoptimize.com/
Text Domain: autoptimize
Released under the GNU General Public License (GPL)
http://www.gnu.org/licenses/gpl.txt
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AUTOPTIMIZE_PLUGIN_VERSION', '2.4.0-beta1' );

// plugin_dir_path() returns the trailing slash!
define( 'AUTOPTIMIZE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTOPTIMIZE_PLUGIN_FILE', __FILE__ );

// Bail early if attempting to run on non-supported php versions
if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
    function autoptimize_incompatible_admin_notice() {
        echo '<div class="error"><p>' . __( 'Autoptimize requires PHP 5.3 (or higher) to function properly. Please upgrade PHP. The Plugin has been auto-deactivated.', 'autoptimize' ) . '</p></div>';
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
    function autoptimize_deactivate_self() {
        deactivate_plugins( plugin_basename( AUTOPTIMIZE_PLUGIN_FILE ) );
    }
    add_action( 'admin_notices', 'autoptimize_incompatible_admin_notice' );
    add_action( 'admin_init', 'autoptimize_deactivate_self' );
    return;
}

function autoptimize_autoload( $class_name ) {
    if ( in_array( $class_name, array( 'Minify_HTML', 'JSMin' ) ) ) {
        $file     = strtolower( $class_name );
        $file     = str_replace( '_', '-', $file );
        $path     = dirname( __FILE__ ) . '/classes/external/php/';
        $filepath = $path . $file . '.php';
    } elseif ( false !== strpos( $class_name, 'Autoptimize\\tubalmartin\\CssMin' ) ) {
        $file     = str_replace( 'Autoptimize\\tubalmartin\\CssMin\\', '', $class_name );
        $path     = dirname( __FILE__ ) . '/classes/external/php/yui-php-cssmin-bundled/';
        $filepath = $path . $file . '.php';
    } elseif ( 'autoptimize' === substr( $class_name, 0, 11 ) ) {
        // One of our "old" classes
        $file     = $class_name;
        $path     = dirname( __FILE__ ) . '/classes/';
        $filepath = $path . $file . '.php';
    }

    // If we didn't match one of our rules, bail!
    if ( ! isset( $filepath ) ) {
        return;
    }

    require $filepath;
}

spl_autoload_register( 'autoptimize_autoload' );

// WP CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeCLI.php';
}

$ao = new autoptimizeMain( AUTOPTIMIZE_PLUGIN_VERSION, AUTOPTIMIZE_PLUGIN_FILE );
$ao->run();

// Always runs for now! (hooks itself on plugins_loaded, could perhaps be changed further)
$ao_cache_checker = new autoptimizeCacheChecker();
unset( $ao_cache_checker );

/**
 * Returns true if all the conditions to start output buffering are satisfied
 *
 * @return bool
 */
function autoptimize_do_buffering( $doing_tests = false ) {
    static $do_buffering = null;

    // Only check once in case we're called multiple times by others but
    // still allowing multiple calls when doing tests
    if ( null === $do_buffering || $doing_tests ) {

        $ao_noptimize = false;
        // check for DONOTMINIFY constant as used by e.g. WooCommerce POS
        if ( defined( 'DONOTMINIFY' ) && ( constant( 'DONOTMINIFY' ) === true || constant( 'DONOTMINIFY' ) === 'true' ) ) {
            $ao_noptimize = true;
        }

        // No need to check query-string if the functionality is explicitly disabled via filter
        if ( apply_filters( 'autoptimize_filter_honor_qs_noptimize', true ) ) {
            // Check for `ao_noptimize` (and other) keys in qs to get non-optimized page for debugging
            $keys = array(
                'ao_noptimize',
                'ao_noptirocket'
            );
            foreach ( $keys as $key ) {
                if ( array_key_exists( $key, $_GET ) && '1' === $_GET[ $key ] ) {
                    $ao_noptimize = true;
                    break;
                }
            }
        }

        // If setting says not to optimize logged in user and user is logged in
        if ( 'on' !== get_option( 'autoptimize_optimize_logged', 'on' ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            $ao_noptimize = true;
        }

        // if setting says not to optimize cart/ checkout
        if ( 'on' !== get_option( 'autoptimize_optimize_checkout', 'on' ) ) {
            // checking for woocommerce, easy digital downloads and wp ecommerce
            foreach ( array( 'is_checkout', 'is_cart', 'edd_is_checkout', 'wpsc_is_cart', 'wpsc_is_checkout') as $shopCond ) {
                if ( function_exists( $shopCond ) && $shopCond() ) {
                    $ao_noptimize = true;
                    break;
                }
            }
        }

        // Allows blocking of autoptimization on your own terms regardless of above decisions
        $ao_noptimize = (bool) apply_filters( 'autoptimize_filter_noptimize', $ao_noptimize );

        // Check for site being previewed in the Customizer (available since WP 4.0)
        $is_customize_preview = false;
        if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
            $is_customize_preview = is_customize_preview();
        }

        // We only buffer the frontend requests (and then only if not a feed and not turned off explicitly and not when
        // being previewed in Customizer)
        // TODO/FIXME: Tests throw a notice here since we're calling is_feed() without the main query being ran
        $do_buffering = ( ! is_admin() && ! is_feed() && ! $ao_noptimize && ! $is_customize_preview );
    }

    return $do_buffering;
}

// Set up the buffering
function autoptimize_start_buffering() {
    if ( autoptimize_do_buffering() ) {

        // load speedupper conditionally (true by default?)
        if ( apply_filters( 'autoptimize_filter_speedupper', true ) ) {
            $ao_speedupper = new autoptimizeSpeedupper();
        }

        // Config element
        $conf = autoptimizeConfig::instance();

        if ( $conf->get('autoptimize_js') ) {
            if ( ! defined( 'CONCATENATE_SCRIPTS' ) ) {
                define( 'CONCATENATE_SCRIPTS', false );
            }
            if ( ! defined( 'COMPRESS_SCRIPTS' ) ) {
                define( 'COMPRESS_SCRIPTS', false );
            }
        }

        if ( $conf->get('autoptimize_css') ) {
            if ( ! defined( 'COMPRESS_CSS' ) ) {
                define( 'COMPRESS_CSS', false );
            }
        }

        if ( apply_filters( 'autoptimize_filter_obkiller', false ) ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // Now, start the real thing!
        ob_start( 'autoptimize_end_buffering' );
    }
}

/**
 * Returns true if markup is considered to be AMP.
 * This is far from actual validation against AMP spec, but it'll do for now.
 */
function autoptimize_is_amp_markup( $content ) {
    $is_amp_markup = preg_match( '/<html[^>]*(?:amp|⚡)/i', $content );

    return (bool) $is_amp_markup;
}

/**
 * Returns true if the markup contains something that indicates that it shouldn't be modified at all.
 *
 * @param string $content
 * @return boolean
 */
function autoptimize_should_bail_from_processing_buffer( $content ) {
    $bail = false;

    $has_no_html_tag = ( false === stripos( $content, '<html' ) );
    $has_xsl_stylesheet = ( false !== stripos( $content, '<xsl:stylesheet' ) );
    $has_html5_doctype = ( preg_match( '/^<!DOCTYPE.+html>/i', $content ) > 0 );

    if ( $has_no_html_tag ) {
        // Can't be valid amp markup without an html tag preceding it
        $is_amp_markup = false;
    } else {
        $is_amp_markup = autoptimize_is_amp_markup( $content );
    }

    if ( $has_no_html_tag && ! $has_html5_doctype || $is_amp_markup || $has_xsl_stylesheet ) {
        $bail = true;
    }

    return $bail;
}

// Action on end, this is where the magic happens
function autoptimize_end_buffering( $content ) {
    if ( autoptimize_should_bail_from_processing_buffer( $content ) ) {
        return $content;
    }

    // Config element
    $conf = autoptimizeConfig::instance();

    // Choose the classes
    $classes = array();
    if ( $conf->get('autoptimize_js') ) {
        $classes[] = 'autoptimizeScripts';
    }
    if ( $conf->get('autoptimize_css') ) {
        $classes[] = 'autoptimizeStyles';
    }
    if ( $conf->get('autoptimize_html') ) {
        $classes[] = 'autoptimizeHTML';
    }

    // Set some options
    $classoptions = array(
        'autoptimizeScripts' => array(
            'justhead' => $conf->get('autoptimize_js_justhead'),
            'forcehead' => $conf->get('autoptimize_js_forcehead'),
            'trycatch' => $conf->get('autoptimize_js_trycatch'),
            'js_exclude' => $conf->get('autoptimize_js_exclude'),
            'cdn_url' => $conf->get('autoptimize_cdn_url'),
            'include_inline' => $conf->get('autoptimize_js_include_inline')
        ),
        'autoptimizeStyles' => array(
            'justhead' => $conf->get('autoptimize_css_justhead'),
            'datauris' => $conf->get('autoptimize_css_datauris'),
            'defer' => $conf->get('autoptimize_css_defer'),
            'defer_inline' => $conf->get('autoptimize_css_defer_inline'),
            'inline' => $conf->get('autoptimize_css_inline'),
            'css_exclude' => $conf->get('autoptimize_css_exclude'),
            'cdn_url' => $conf->get('autoptimize_cdn_url'),
            'include_inline' => $conf->get('autoptimize_css_include_inline'),
            'nogooglefont' => $conf->get('autoptimize_css_nogooglefont')
        ),
        'autoptimizeHTML' => array(
            'keepcomments' => $conf->get('autoptimize_html_keepcomments')
        )
    );

    $content = apply_filters( 'autoptimize_filter_html_before_minify', $content );

    // Run the classes
    foreach ( $classes as $name ) {
        $instance = new $name( $content );
        if ( $instance->read( $classoptions[ $name ] ) ) {
            $instance->minify();
            $instance->cache();
            $content = $instance->getcontent();
        }
        unset( $instance );
    }

    $content = apply_filters( 'autoptimize_html_after_minify', $content );
    return $content;
}
