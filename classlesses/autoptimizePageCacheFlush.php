<?php

// flush as many page cache plugin's caches as possible
// hyper cache and gator cache hook into AO, so we don't need to :-)

function autoptimize_flush_pagecache() {
    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        if ( is_multisite() ) {
            $blog_id = get_current_blog_id();
            wp_cache_clear_cache( $blog_id );
        } else {
            wp_cache_clear_cache();
        }
    } elseif ( has_action('cachify_flush_cache') ) {
        do_action( 'cachify_flush_cache' );
    } elseif ( function_exists( 'w3tc_pgcache_flush' ) ) {
        w3tc_pgcache_flush(); // w3 total cache
/*
    } elseif ( function_exists( 'hyper_cache_invalidate' ) ) {
        hyper_cache_invalidate(); // hypercache
*/
    } else if ( has_action('hyper_cache_clean') ) {
        // hypercache NOK, hyper_cache_clean only removes pages older then time+max_age
        // do_action('hyper_cache_clean');
    } elseif ( function_exists( 'wp_fast_cache_bulk_delete_all' ) ) {
        wp_fast_cache_bulk_delete_all(); // still to retest
    } elseif ( class_exists( 'WpFastestCache' ) ) {
        $wpfc = new WpFastestCache(); // wp fastest cache
        $wpfc->deleteCache();
    } elseif ( class_exists( 'c_ws_plugin__qcache_purging_routines' ) ) {
        c_ws_plugin__qcache_purging_routines::purge_cache_dir(); // quick cache
    } elseif ( class_exists( 'zencache' ) ) {
        zencache::clear();
    } elseif ( class_exists( 'comet_cache' ) ) {
        comet_cache::clear();
    } elseif ( class_exists( 'WpeCommon' ) ) {
        // WPEngine cache purge/flush methods to call by default
        $wpe_methods = array(
            'purge_varnish_cache'
        );

        // More agressive clear/flush/purge behind a filter
        if ( apply_filters('autoptimize_flush_wpengine_aggressive', false ) ) {
            $wpe_methods = array_merge( $wpe_methods, array( 'purge_memcached', 'clear_maxcdn_cache' ) );
        }

        // Filtering the entire list of WpeCommon methods to be called (for advanced usage + easier testing)
        $wpe_methods = apply_filters( 'autoptimize_flush_wpengine_methods', $wpe_methods );

        foreach ( $wpe_methods as $wpe_method ) {
            if ( method_exists( 'WpeCommon', $wpe_method ) ) {
                WpeCommon::$wpe_method();
            }
        }
    } elseif ( file_exists ( WP_CONTENT_DIR . '/wp-cache-config.php' ) && function_exists( 'prune_super_cache' ) ) {
        // fallback for WP-Super-Cache
        global $cache_path;
        if ( is_multisite() ) {
            $blog_id = get_current_blog_id();
            prune_super_cache( get_supercache_dir( $blog_id ), true );
            prune_super_cache( $cache_path . 'blogs/', true );
        } else {
            prune_super_cache( $cache_path . 'supercache/', true);
            prune_super_cache( $cache_path, true );
        }
    }
}
