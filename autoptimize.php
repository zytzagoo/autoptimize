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

// plugin_dir_path() returns the trailing slash!
define( 'AUTOPTIMIZE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeConfig.php';
include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeToolbar.php';

// Bail early if attempting to run on non-supported php versions
if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
    function autoptimize_incompatible_admin_notice() {
        echo '<div class="error"><p>' . __( 'Autoptimize requires PHP 5.3 (or higher) to function properly. Please upgrade PHP. The Plugin has been auto-deactivated.', 'autoptimize' ) . '</p></div>';
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
    function autoptimize_deactivate_self() {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
    add_action( 'admin_notices', 'autoptimize_incompatible_admin_notice' );
    add_action( 'admin_init', 'autoptimize_deactivate_self' );
    return;
}

// Load partners tab if admin (and not for admin-ajax.php)
function autoptimize_load_partners_tab() {
    if ( autoptimizeConfig::is_admin_and_not_ajax() ) {
        include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizePartners.php';
        new autoptimizePartners();
    }
}
add_action( 'plugins_loaded', 'autoptimize_load_partners_tab' );

// Do we gzip when caching (needed early to load autoptimizeCache.php)
define( 'AUTOPTIMIZE_CACHE_NOGZIP', (bool) get_option( 'autoptimize_cache_nogzip' ) );

// Load cache class
include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeCache.php';

// wp-content dir name (automagically set, should not be needed), dirname of AO cache dir and AO-prefix can be overridden in wp-config.php
if ( ! defined( 'AUTOPTIMIZE_WP_CONTENT_NAME' ) ) { define( 'AUTOPTIMIZE_WP_CONTENT_NAME', '/' . wp_basename( WP_CONTENT_DIR ) ); }
if ( ! defined( 'AUTOPTIMIZE_CACHE_CHILD_DIR' ) ) { define( 'AUTOPTIMIZE_CACHE_CHILD_DIR', '/cache/autoptimize/' ); }
if ( ! defined( 'AUTOPTIMIZE_CACHEFILE_PREFIX' ) ) { define( 'AUTOPTIMIZE_CACHEFILE_PREFIX', 'autoptimize_' ); }

// Plugin dir constants (plugin url's defined later to accomodate domain mapped sites)
if ( ! defined( 'AUTOPTIMIZE_CACHE_DIR' ) ) {
    if ( is_multisite() && apply_filters( 'autoptimize_separate_blog_caches', true ) ) {
        $blog_id = get_current_blog_id();
        define( 'AUTOPTIMIZE_CACHE_DIR' , WP_CONTENT_DIR . AUTOPTIMIZE_CACHE_CHILD_DIR . $blog_id . '/' );
    } else {
        define( 'AUTOPTIMIZE_CACHE_DIR', WP_CONTENT_DIR . AUTOPTIMIZE_CACHE_CHILD_DIR );
    }
}
define( 'WP_ROOT_DIR', substr( WP_CONTENT_DIR, 0, strlen( WP_CONTENT_DIR ) - strlen( AUTOPTIMIZE_WP_CONTENT_NAME ) ) );

// WP CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeCLI.php';
}

// Define some more constants, but delayed/hooked on `plugins_loaded`, since domain mapping might be required for it
function autoptimize_define_more_constants() {
    if ( ! defined( 'AUTOPTIMIZE_WP_SITE_URL' ) ) {
        if ( function_exists( 'domain_mapping_siteurl' ) ) {
            define( 'AUTOPTIMIZE_WP_SITE_URL', domain_mapping_siteurl( get_current_blog_id() ) );
        } else {
            define( 'AUTOPTIMIZE_WP_SITE_URL', site_url() );
        }
    }
    if ( ! defined( 'AUTOPTIMIZE_WP_CONTENT_URL' ) ) {
        if ( function_exists( 'domain_mapping_siteurl' ) ) {
            define( 'AUTOPTIMIZE_WP_CONTENT_URL', str_replace( get_original_url( AUTOPTIMIZE_WP_SITE_URL ), AUTOPTIMIZE_WP_SITE_URL, content_url() ) );
        } else {
            define( 'AUTOPTIMIZE_WP_CONTENT_URL', content_url() );
        }
    }

    if ( ! defined( 'AUTOPTIMIZE_CACHE_URL' ) ) {
        if ( is_multisite() && apply_filters( 'autoptimize_separate_blog_caches', true ) ) {
            $blog_id = get_current_blog_id();
            define( 'AUTOPTIMIZE_CACHE_URL', AUTOPTIMIZE_WP_CONTENT_URL . AUTOPTIMIZE_CACHE_CHILD_DIR . $blog_id . '/' );
        } else {
            define( 'AUTOPTIMIZE_CACHE_URL', AUTOPTIMIZE_WP_CONTENT_URL . AUTOPTIMIZE_CACHE_CHILD_DIR );
        }
    }

    if ( ! defined( 'AUTOPTIMIZE_WP_ROOT_URL' ) ) {
        define( 'AUTOPTIMIZE_WP_ROOT_URL', str_replace( AUTOPTIMIZE_WP_CONTENT_NAME, '', AUTOPTIMIZE_WP_CONTENT_URL ) );
    }

    if ( ! defined( 'AUTOPTIMIZE_HASH' ) ) {
        define( 'AUTOPTIMIZE_HASH', wp_hash( AUTOPTIMIZE_CACHE_URL ) );
    }
}
add_action( 'plugins_loaded', 'autoptimize_define_more_constants' );

// Initialize the cache at least once
$conf = autoptimizeConfig::instance();

/* Check if we're updating, in which case we might need to do stuff and flush the cache
to avoid old versions of aggregated files lingering around */

define( 'AUTOPTIMIZE_PLUGIN_VERSION', '2.4.0-beta1' );
$autoptimize_db_version = get_option( 'autoptimize_version', 'none' );

if ( $autoptimize_db_version !== AUTOPTIMIZE_PLUGIN_VERSION ) {
    if ( 'none' === $autoptimize_db_version ) {
        add_action( 'admin_notices', 'autoptimize_install_config_notice' );
    } else {
        // updating, include the update-code
        include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeVersionUpdatesHandler.php';
        $ao_updater = new autoptimizeVersionUpdatesHandler( $autoptimize_db_version );
        $ao_updater->run_needed_major_upgrades();
    }

    update_option( 'autoptimize_version', AUTOPTIMIZE_PLUGIN_VERSION );
}

// Load translations
function autoptimize_load_plugin_textdomain() {
    load_plugin_textdomain( 'autoptimize' );
}
add_action( 'init', 'autoptimize_load_plugin_textdomain' );

function autoptimize_uninstall(){
    autoptimizeCache::clearall();

    $delete_options = array(
        'autoptimize_cache_clean', 'autoptimize_cache_nogzip', 'autoptimize_css',
        'autoptimize_css_datauris', 'autoptimize_css_justhead', 'autoptimize_css_defer',
        'autoptimize_css_defer_inline', 'autoptimize_css_inline', 'autoptimize_css_exclude',
        'autoptimize_html', 'autoptimize_html_keepcomments', 'autoptimize_js',
        'autoptimize_js_exclude', 'autoptimize_js_forcehead', 'autoptimize_js_justhead',
        'autoptimize_js_trycatch', 'autoptimize_version', 'autoptimize_show_adv',
        'autoptimize_cdn_url', 'autoptimize_cachesize_notice',
        'autoptimize_css_include_inline', 'autoptimize_js_include_inline',
        'autoptimize_optimize_logged', 'autoptimize_optimize_checkout', 'autoptimize_extra_settings'
    );

    if ( ! is_multisite() ) {
        foreach ( $delete_options as $del_opt ) {
            delete_option( $del_opt );
        }
    } else {
        global $wpdb;
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        $original_blog_id = get_current_blog_id();
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            foreach ( $delete_options as $del_opt ) {
                delete_option( $del_opt );
            }
        }
        switch_to_blog( $original_blog_id );
    }

    if ( wp_get_schedule( 'ao_cachechecker' ) ) {
        wp_clear_scheduled_hook( 'ao_cachechecker' );
    }
}

function autoptimize_install_config_notice() {
    echo '<div class="updated"><p>';
    _e('Thank you for installing and activating Autoptimize. Please configure it under "Settings" -> "Autoptimize" to start improving your site\'s performance.', 'autoptimize' );
    echo '</p></div>';
}

function autoptimize_update_config_notice() {
    echo '<div class="updated"><p>';
    _e('Autoptimize has just been updated. Please <strong>test your site now</strong> and adapt Autoptimize config if needed.', 'autoptimize' );
    echo '</p></div>';
}

function autoptimize_cache_unavailable_notice() {
    echo '<div class="error"><p>';
    printf( __( 'Autoptimize cannot write to the cache directory (%s), please fix to enable CSS/ JS optimization!', 'autoptimize' ), AUTOPTIMIZE_CACHE_DIR );
    echo '</p></div>';
}

/**
 * Returns true if all the conditions to start output buffering are satisfied
 *
 * @return bool
 */
function autoptimize_do_buffering($doing_tests = false) {
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
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeSpeedupper.php';
            $ao_speedupper = new autoptimizeSpeedupper();
        }

        // Config element
        $conf = autoptimizeConfig::instance();

        // Load our base class
        include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeBase.php';

        // Load extra classes and set some vars
        if ( $conf->get('autoptimize_html') ) {
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeHTML.php';
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/external/php/minify-html.php';
        }

        if ( $conf->get('autoptimize_js') ) {
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeScripts.php';
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/external/php/minify-2.3.2-jsmin.php';
            if ( ! defined( 'CONCATENATE_SCRIPTS' ) ) {
                define( 'CONCATENATE_SCRIPTS', false );
            }
            if ( ! defined( 'COMPRESS_SCRIPTS' ) ) {
                define( 'COMPRESS_SCRIPTS', false );
            }
        }

        if ( $conf->get('autoptimize_css') ) {
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeStyles.php';
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeCSSmin.php';
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/external/php/yui-php-cssmin-bundled/Colors.php';
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/external/php/yui-php-cssmin-bundled/Utils.php';
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/external/php/yui-php-cssmin-bundled/Minifier.php';

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
function autoptimize_is_amp_markup($content) {
    $is_amp_markup = preg_match( '/<html[^>]*(?:amp|⚡)/i', $content );

    return (bool) $is_amp_markup;
}

/**
 * Returns true if the markup contains something that indicates that it shouldn't be modified at all.
 *
 * @param string $content
 * @return boolean
 */
function autoptimize_should_bail_from_processing_buffer($content) {
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
function autoptimize_end_buffering($content) {
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
        if ( $instance->read($classoptions[$name]) ) {
            $instance->minify();
            $instance->cache();
            $content = $instance->getcontent();
        }
        unset( $instance );
    }

    $content = apply_filters( 'autoptimize_html_after_minify', $content );
    return $content;
}

if ( autoptimizeCache::cacheavail() ) {
    $conf = autoptimizeConfig::instance();
    if ( $conf->get('autoptimize_html') || $conf->get('autoptimize_js') || $conf->get('autoptimize_css' ) ) {
        // Hook to wordpress
        if ( defined( 'AUTOPTIMIZE_INIT_EARLIER' ) ) {
            add_action( 'init', 'autoptimize_start_buffering', -1 );
        } else {
            if ( ! defined( 'AUTOPTIMIZE_HOOK_INTO' ) ) {
                define( 'AUTOPTIMIZE_HOOK_INTO', 'template_redirect' );
            }
            add_action( constant('AUTOPTIMIZE_HOOK_INTO'), 'autoptimize_start_buffering' , 2 );
        }
    }
} else {
    add_action( 'admin_notices', 'autoptimize_cache_unavailable_notice' );
}

function autoptimize_activate() {
    register_uninstall_hook( __FILE__, 'autoptimize_uninstall' );
}
register_activation_hook( __FILE__, 'autoptimize_activate' );

include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeCacheChecker.php';
$ao_cache_checker = new autoptimizeCacheChecker();

function autoptimize_maybe_run_ao_extra() {
    if ( apply_filters( 'autoptimize_filter_extra_activate', true ) ) {
        include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeExtra.php';
        $ao_extra = new autoptimizeExtra();
        $ao_extra->run();
    }
}
add_action( 'plugins_loaded', 'autoptimize_maybe_run_ao_extra' );

// Do not pollute other plugins
unset( $conf );
