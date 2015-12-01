<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCache
{
	private $filename;
	private $cachedir;
	private $delayed;

	public function __construct($md5, $ext = 'php')
    {
		$this->cachedir = AUTOPTIMIZE_CACHE_DIR;
		$this->delayed  = AUTOPTIMIZE_CACHE_DELAY;
		$this->nogzip   = AUTOPTIMIZE_CACHE_NOGZIP; // true => we don't gzip, web server does it (default), false => we do gzipping ourselves

		if ( ! $this->nogzip ) {
			$this->filename = AUTOPTIMIZE_CACHEFILE_PREFIX . $md5 . '.php';
		} else {
			if ( in_array( $ext, array( 'js', 'css' ) ) ) {
				$this->filename = $ext . '/' . AUTOPTIMIZE_CACHEFILE_PREFIX . $md5 . '.' . $ext;
			} else {
				$this->filename = '/' . AUTOPTIMIZE_CACHEFILE_PREFIX . $md5 . '.' . $ext;
			}
		}
	}

	public function check()
    {
		if ( ! file_exists( $this->cachedir . $this->filename ) ) {
			// No cached file, sorry
			return false;
		}

		// Cache exists!
		return true;
	}

	public function retrieve()
    {
		if ( $this->check() ) {
			if ( false == $this->nogzip ) {
				return file_get_contents( $this->cachedir . $this->filename . '.none' );
			} else {
				return file_get_contents( $this->cachedir . $this->filename );
			}
		}
		return false;
	}

	public function cache($code, $mime)
    {
		if ( false == $this->nogzip ) {
			$file    = $this->delayed ? 'delayed.php' : 'default.php';
			$phpcode = file_get_contents( AUTOPTIMIZE_PLUGIN_DIR . 'config/' . $file);
			$phpcode = str_replace( array( '%%CONTENT%%', 'exit;' ), array( $mime, '' ), $phpcode );

			file_put_contents( $this->cachedir . $this->filename, $phpcode, LOCK_EX );
			file_put_contents( $this->cachedir . $this->filename . '.none', $code, LOCK_EX );

			if ( ! $this->delayed ) {
				// Compress now!
				file_put_contents( $this->cachedir . $this->filename . '.deflate', gzencode( $code, 9, FORCE_DEFLATE ), LOCK_EX );
				file_put_contents( $this->cachedir . $this->filename . '.gzip', gzencode( $code, 9, FORCE_GZIP ), LOCK_EX );
			}
		} else {
			// Write code to cache without doing anything else
			file_put_contents( $this->cachedir . $this->filename, $code, LOCK_EX );
		}
	}

	public function getname()
    {
		return $this->filename;
	}

    /**
    * Returns true if $file is considered a valid Autoptimize cache file, false otherwise.
    *
    * @param $file Filename/pathname to check
    * @return bool
    */
    static function is_valid_cache_file($dir, $file)
    {
        if ( '.' !== $file && '..' !== $file &&
            false !== strpos( $file, AUTOPTIMIZE_CACHEFILE_PREFIX ) &&
            is_file( $dir . $file ) ) {

            // It's a valid file
            return true;
        }

        // Everything else is considered invalid
        return false;
    }

	static function clearall()
    {
		if ( ! autoptimizeCache::cacheavail() ) {
			return false;
		}

		// scan the cachedirs
        $scan = self::get_cache_contents();

		// clear the cachedirs
		foreach ( $scan as $scandirName => $scanneddir ) {
			$dir = rtrim( AUTOPTIMIZE_CACHE_DIR . $scandirName, '/' ) . '/';
			foreach ( $scanneddir as $file ) {
                if ( self::is_valid_cache_file( $dir, $file ) ) {
                    @unlink( $dir . $file );
                }
			}
		}

		@unlink( AUTOPTIMIZE_CACHE_DIR . '/.htaccess' );
        delete_transient( 'autoptimize_stats' );

        // TODO/FIXME: Why is this a verbatim duplicate from autoptimize_flush_pagecache() ?
        // and it then even schedules basically that same function for later

		// Do we need to clean any caching plugins cache-files?
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            if (is_multisite()) {
                $blog_id = get_current_blog_id();
                wp_cache_clear_cache( $blog_id );
            } else {
                wp_cache_clear_cache();
            }
        } elseif ( has_action('cachify_flush_cache') ) {
            do_action( 'cachify_flush_cache' );
        } elseif ( function_exists( 'w3tc_pgcache_flush' ) ) {
            w3tc_pgcache_flush(); // w3 total cache
        } elseif ( function_exists( 'hyper_cache_invalidate' ) ) {
            hyper_cache_invalidate(); // hypercache
        } elseif ( function_exists( 'wp_fast_cache_bulk_delete_all' ) ) {
            wp_fast_cache_bulk_delete_all(); // wp fast cache
        } elseif ( class_exists( 'WpFastestCache' ) ) {
            $wpfc = new WpFastestCache(); // wp fastest cache
            $wpfc->deleteCache();
        } elseif ( class_exists( 'c_ws_plugin__qcache_purging_routines' ) ) {
            c_ws_plugin__qcache_purging_routines::purge_cache_dir(); // quick cache
        } elseif ( class_exists( 'zencache' ) ) {
            zencache::clear(); // zen cache, tbc
        } elseif ( file_exists ( WP_CONTENT_DIR . '/wp-cache-config.php' ) && function_exists( 'prune_super_cache' ) ) {
            // fallback for WP-Super-Cache
            global $cache_path;
            if (is_multisite()) {
                $blog_id = get_current_blog_id();
                prune_super_cache( get_supercache_dir( $blog_id ), true );
                prune_super_cache( $cache_path . 'blogs/', true );
            } else {
                prune_super_cache( $cache_path . 'supercache/', true);
                prune_super_cache( $cache_path, true );
            }
        } else {
            // fallback; schedule event and try to clear there
            wp_schedule_single_event( time() + 1, 'ao_flush_pagecache' , array( time() ) );
        }

        if ( ! function_exists( 'autoptimize_do_cachepurged_action ' ) ) {
            function autoptimize_do_cachepurged_action() {
                do_action( 'autoptimize_action_cachepurged' );
            }
        }
        add_action( 'after_setup_theme', 'autoptimize_do_cachepurged_action' );

		return true;
	}

    // returns contents of our cache dirs
    static function get_cache_contents()
    {
        $contents = array();

        foreach ( array( '', 'js', 'css' ) as $dir_name ) {
            $contents[$dir_name] = scandir( AUTOPTIMIZE_CACHE_DIR . $dir_name );
        }

        return $contents;
    }

	static function stats()
    {
        // Get stats from transient
        $AOstats = get_transient( 'autoptimize_stats' );

        // If no transient, do the actual scan
        if ( ! is_array( $AOstats ) ) {
            // Cache not available :(
            if ( ! autoptimizeCache::cacheavail() ) {
                return 0;
            }

            // Collect stats from cache dirs
            $AOstats = self::stats_scan();

            $count = $AOstats[0];
            if ( $count > 100 ) {
                // Store results in transient
                set_transient( 'autoptimize_stats', $AOstats, HOUR_IN_SECONDS );
            }
        }

        return $AOstats;
	}

    static function stats_scan()
    {
        $count = 0;
        $size  = 0;

        // scan the cachedirs
		foreach ( self::get_cache_contents() as $scandirName => $scanneddir ) {
            $dir = rtrim( AUTOPTIMIZE_CACHE_DIR . $scandirName, '/' ) . '/';
			foreach ( $scanneddir as $file ) {
                if ( self::is_valid_cache_file( $dir, $file ) ) {
                    if ( AUTOPTIMIZE_CACHE_NOGZIP &&
                        (
                            false !== strpos( $file, '.js' ) ||
                            false !== strpos( $file, '.css' ) ||
                            false !== strpos( $file, '.img' ) ||
                            false !== strpos( $file, '.txt' )
                        )
                    ) {
                        // web server is gzipping, we count .js|.css|.img|.txt files
						$count++;
					} elseif ( ! AUTOPTIMIZE_CACHE_NOGZIP && false !== strpos( $file, '.none' ) ) {
                        // we are gzipping ourselves via php, counting only .none files
						$count++;
					}
                    $size += filesize( $dir . $file );
				}
			}
		}

        $AOstats = array( $count, $size, time() );

        return $AOstats;
    }

	static function cacheavail()
    {
		if ( ! defined( 'AUTOPTIMIZE_CACHE_DIR' ) ) {
			// We didn't set a cache
			return false;
		}

		foreach ( array( '', 'js', 'css' ) as $checkDir ) {
			if ( ! autoptimizeCache::checkCacheDir( AUTOPTIMIZE_CACHE_DIR . $checkDir ) ) {
				return false;
			}
		}

        /** write .htaccess here to overrule wp_super_cache */
        $htAccess = AUTOPTIMIZE_CACHE_DIR . '/.htaccess';
        if ( ! is_file( $htAccess ) ) {
			/**
			 * create wp-content/AO_htaccess_tmpl with
			 * whatever htaccess rules you might need
			 * if you want to override default AO htaccess
			 */
			$htaccess_tmpl = WP_CONTENT_DIR . '/AO_htaccess_tmpl';
			if ( is_file( $htaccess_tmpl ) ) {
				$htAccessContent = file_get_contents( $htaccess_tmpl );
			} elseif ( is_multisite() || ! AUTOPTIMIZE_CACHE_NOGZIP ) {
				$htAccessContent = '<IfModule mod_headers.c>
        Header set Vary "Accept-Encoding"
        Header set Cache-Control "max-age=10672000, must-revalidate"
</IfModule>
<IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css A30672000
        ExpiresByType text/javascript A30672000
        ExpiresByType application/javascript A30672000
</IfModule>
<IfModule mod_deflate.c>
        <FilesMatch "\.(js|css)$">
        SetOutputFilter DEFLATE
    </FilesMatch>
</IfModule>
<IfModule mod_authz_core.c>
    <Files *.php>
        Require all granted
    </Files>
</IfModule>
<IfModule !mod_authz_core.c>
    <Files *.php>
        Order allow,deny
        Allow from all
    </Files>
</IfModule>';
			} else {
                $htAccessContent = '<IfModule mod_headers.c>
        Header set Vary "Accept-Encoding"
        Header set Cache-Control "max-age=10672000, must-revalidate"
</IfModule>
<IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css A30672000
        ExpiresByType text/javascript A30672000
        ExpiresByType application/javascript A30672000
</IfModule>
<IfModule mod_deflate.c>
    <FilesMatch "\.(js|css)$">
        SetOutputFilter DEFLATE
    </FilesMatch>
</IfModule>
<IfModule mod_authz_core.c>
    <Files *.php>
        Require all denied
    </Files>
</IfModule>
<IfModule !mod_authz_core.c>
    <Files *.php>
        Order deny,allow
        Deny from all
    </Files>
</IfModule>';
			}
			@file_put_contents($htAccess, $htAccessContent);
		}

        // All OK
        return true;
	}

	static function checkCacheDir($dir)
    {
		// Check and create if not exists
		if ( ! file_exists( $dir ) ) {
			@mkdir( $dir, 0775, true );
			if ( ! file_exists( $dir ) ) {
				return false;
			}
		}

		// check if we can now write
		if ( ! is_writable( $dir ) ) {
			return false;
		}

		// and write index.html here to avoid prying eyes
		$indexFile = rtrim( $dir, "/\\" ) . '/index.html';
		if ( ! is_file( $indexFile ) ) {
			@file_put_contents( $indexFile, '<html><body>Generated by <a href="http://wordpress.org/extend/plugins/autoptimize/">Autoptimize</a></body></html>' );
		}

		return true;
	}
}
