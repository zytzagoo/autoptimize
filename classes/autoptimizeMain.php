<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeMain
{
    protected $version     = null;
    protected $plugin_file = null;

    public function __construct( $version, $plugin_file )
    {
        $this->version     = $version;
        $this->plugin_file = $plugin_file;
    }

    public function run()
    {
        $this->add_hooks();
    }

    protected function add_hooks()
    {
        add_action( 'plugins_loaded', array( $this, 'setup' ) );

        add_action( 'autoptimize_setup_done', array( $this, 'version_upgrades_check' ) );
        add_action( 'autoptimize_setup_done', array( $this, 'check_cache_and_run' ) );
        add_action( 'autoptimize_setup_done', array( $this, 'maybe_run_ao_extra' ) );
        add_action( 'autoptimize_setup_done', array( $this, 'maybe_run_partners_tab' ) );

        add_action( 'init', array( $this, 'load_textdomain' ) );

        register_activation_hook( $this->plugin_file, array( $this, 'on_activate' ) );
    }

    public function on_activate()
    {
        register_uninstall_hook( $this->plugin_file, array( $this, 'on_uninstall' ) );
    }

    public function load_textdomain()
    {
        load_plugin_textdomain( 'autoptimize' );
    }

    public function setup()
    {
        // Do we gzip in php when caching or is the webserver doing it?
        define( 'AUTOPTIMIZE_CACHE_NOGZIP', (bool) get_option( 'autoptimize_cache_nogzip' ) );

        // These can be overridden by specifying them in wp-config.php or such.
        if ( ! defined( 'AUTOPTIMIZE_WP_CONTENT_NAME' ) ) {
            define( 'AUTOPTIMIZE_WP_CONTENT_NAME', '/' . wp_basename( WP_CONTENT_DIR ) );
        }
        if ( ! defined( 'AUTOPTIMIZE_CACHE_CHILD_DIR' ) ) {
            define( 'AUTOPTIMIZE_CACHE_CHILD_DIR', '/cache/autoptimize/' );
        }
        if ( ! defined( 'AUTOPTIMIZE_CACHEFILE_PREFIX' ) ) {
            define( 'AUTOPTIMIZE_CACHEFILE_PREFIX', 'autoptimize_' );
        }
        if ( ! defined( 'AUTOPTIMIZE_CACHE_DIR' ) ) {
            if ( is_multisite() && apply_filters( 'autoptimize_separate_blog_caches', true ) ) {
                $blog_id = get_current_blog_id();
                define( 'AUTOPTIMIZE_CACHE_DIR', WP_CONTENT_DIR . AUTOPTIMIZE_CACHE_CHILD_DIR . $blog_id . '/' );
            } else {
                define( 'AUTOPTIMIZE_CACHE_DIR', WP_CONTENT_DIR . AUTOPTIMIZE_CACHE_CHILD_DIR );
            }
        }

        define( 'WP_ROOT_DIR', substr( WP_CONTENT_DIR, 0, strlen( WP_CONTENT_DIR ) - strlen( AUTOPTIMIZE_WP_CONTENT_NAME ) ) );

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

        do_action( 'autoptimize_setup_done' );
    }

    /**
     * Checks if there's a need to upgrade/update options and whatnot,
     * in which case we might need to do stuff and flush the cache
     * to avoid old versions of aggregated files lingering around.
     */
    public function version_upgrades_check()
    {
        autoptimizeVersionUpdatesHandler::check_installed_and_update( $this->version );
    }

    public function check_cache_and_run()
    {
        if ( autoptimizeCache::cacheavail() ) {
            $conf = autoptimizeConfig::instance();
            if ( $conf->get( 'autoptimize_html' ) || $conf->get( 'autoptimize_js' ) || $conf->get( 'autoptimize_css' ) ) {
                // Hook into WordPress frontend.
                if ( defined( 'AUTOPTIMIZE_INIT_EARLIER' ) ) {
                    add_action( 'init', 'autoptimize_start_buffering', -1 );
                } else {
                    if ( ! defined( 'AUTOPTIMIZE_HOOK_INTO' ) ) {
                        define( 'AUTOPTIMIZE_HOOK_INTO', 'template_redirect' );
                    }
                    add_action( constant( 'AUTOPTIMIZE_HOOK_INTO' ), 'autoptimize_start_buffering', 2 );
                }
            }
        } else {
            add_action( 'admin_notices', 'autoptimizeMain::notice_cache_unavailable' );
        }
    }

    public function maybe_run_ao_extra()
    {
        if ( apply_filters( 'autoptimize_filter_extra_activate', true ) ) {
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizeExtra.php';
            $ao_extra = new autoptimizeExtra();
            $ao_extra->run();
        }
    }

    public function maybe_run_partners_tab()
    {
        // Loads partners tab code if in admin (and not in admin-ajax.php)!
        if ( autoptimizeConfig::is_admin_and_not_ajax() ) {
            include AUTOPTIMIZE_PLUGIN_DIR . 'classes/autoptimizePartners.php';
            new autoptimizePartners();
        }
    }

    public function on_uninstall()
    {
        autoptimizeCache::clearall();

        $delete_options = array(
            'autoptimize_cache_clean',
            'autoptimize_cache_nogzip',
            'autoptimize_css',
            'autoptimize_css_datauris',
            'autoptimize_css_justhead',
            'autoptimize_css_defer',
            'autoptimize_css_defer_inline',
            'autoptimize_css_inline',
            'autoptimize_css_exclude',
            'autoptimize_html',
            'autoptimize_html_keepcomments',
            'autoptimize_js',
            'autoptimize_js_exclude',
            'autoptimize_js_forcehead',
            'autoptimize_js_justhead',
            'autoptimize_js_trycatch',
            'autoptimize_version',
            'autoptimize_show_adv',
            'autoptimize_cdn_url',
            'autoptimize_cachesize_notice',
            'autoptimize_css_include_inline',
            'autoptimize_js_include_inline',
            'autoptimize_optimize_logged',
            'autoptimize_optimize_checkout',
            'autoptimize_extra_settings',
        );

        if ( ! is_multisite() ) {
            foreach ( $delete_options as $del_opt ) {
                delete_option( $del_opt );
            }
        } else {
            global $wpdb;
            $blog_ids         = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
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

    public static function notice_cache_unavailable()
    {
        echo '<div class="error"><p>';
        // Translators: %s is the cache directory location.
        printf( __( 'Autoptimize cannot write to the cache directory (%s), please fix to enable CSS/ JS optimization!', 'autoptimize' ), AUTOPTIMIZE_CACHE_DIR );
        echo '</p></div>';
    }

    public static function notice_installed()
    {
        echo '<div class="updated"><p>';
        _e( 'Thank you for installing and activating Autoptimize. Please configure it under "Settings" -> "Autoptimize" to start improving your site\'s performance.', 'autoptimize' );
        echo '</p></div>';
    }

    public static function notice_updated()
    {
        echo '<div class="updated"><p>';
        _e( 'Autoptimize has just been updated. Please <strong>test your site now</strong> and adapt Autoptimize config if needed.', 'autoptimize' );
        echo '</p></div>';
    }

}
