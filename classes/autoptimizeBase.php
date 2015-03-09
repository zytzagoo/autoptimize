<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

abstract class autoptimizeBase
{
	protected $content = '';

    public $debug_log = false;

	public function __construct($content)
	{
		$this->content = $content;
		// Best place to catch errors
	}

	//Reads the page and collects tags
	abstract public function read($justhead);

	//Joins and optimizes collected things
	abstract public function minify();

	//Caches the things
	abstract public function cache();

	//Returns the content
	abstract public function getcontent();

	//Converts an URL to a full path
	protected function getpath($url)
	{
		$url = apply_filters( 'autoptimize_filter_cssjs_alter_url', $url);

		if ( false !== strpos( $url,'%' ) ) {
			$url = urldecode( $url );
		}

		// normalize
		if (0 === strpos( $url, '//' ) ) {
			if ( is_ssl() ) {
				$url = 'https:' . $url;
			} else {
				$url = 'http:' . $url;
			}
		} else if ( ( false === strpos($url, '//' ) ) && ( strpos( $url, parse_url( AUTOPTIMIZE_WP_SITE_URL, PHP_URL_HOST ) ) === false ) ) {
			$url = AUTOPTIMIZE_WP_SITE_URL . $url;
		}

		// first check; hostname wp site should be hostname of url
		if ( parse_url( $url, PHP_URL_HOST ) !== parse_url( AUTOPTIMIZE_WP_SITE_URL, PHP_URL_HOST ) ) {
			return false;
		}

		// try to remove "wp root url" from url while not minding http<>https
		$tmp_ao_root = preg_replace( '/https?/', '', AUTOPTIMIZE_WP_ROOT_URL );
		$tmp_url     = preg_replace( '/https?/', '', $url );
    	$path        = str_replace( $tmp_ao_root, '', $tmp_url );

		// final check; if path starts with :// or //, this is not a URL in the WP context and we have to assume we can't aggregate
		if ( preg_match( '#^:?//#', $path ) ) {
     		/** External script/css (adsense, etc) */
			return false;
   		}

    	$path = str_replace( '//', '/', WP_ROOT_DIR . $path );
    	return $path;
	}

	// hide everything between noptimize-comment tags
	protected function hide_noptimize($noptimize_in)
	{
		if ( preg_match( '/<!--\s?noptimize\s?-->/', $noptimize_in ) ) {
			$noptimize_out = preg_replace_callback(
				'#<!--\s?noptimize\s?-->.*?<!--\s?/\s?noptimize\s?-->#is',
				create_function(
					'$matches',
					'return "%%NOPTIMIZE%%".base64_encode($matches[0])."%%NOPTIMIZE%%";'
				),
				$noptimize_in
			);
		} else {
			$noptimize_out = $noptimize_in;
		}
		return $noptimize_out;
	}

	// unhide noptimize-tags
	protected function restore_noptimize($noptimize_in)
	{
		if ( false !== strpos( $noptimize_in, '%%NOPTIMIZE%%' ) ) {
			$noptimize_out = preg_replace_callback(
				'#%%NOPTIMIZE%%(.*?)%%NOPTIMIZE%%#is',
				create_function(
					'$matches',
					'return stripslashes(base64_decode($matches[1]));'
				),
				$noptimize_in
			);
		} else {
			$noptimize_out = $noptimize_in;
		}
		return $noptimize_out;
	}

	protected function hide_iehacks($iehacks_in)
	{
		if ( false !== strpos( $iehacks_in, '<!--[if' ) ) {
			$iehacks_out = preg_replace_callback(
				'#<!--\[if.*?\[endif\]-->#is',
				create_function(
					'$matches',
					'return "%%IEHACK%%".base64_encode($matches[0])."%%IEHACK%%";'
				),
				$iehacks_in
			);
		} else {
			$iehacks_out = $iehacks_in;
		}
		return $iehacks_out;
	}

	protected function restore_iehacks($iehacks_in)
	{
		if ( false !== strpos( $iehacks_in, '%%IEHACK%%' ) ) {
			$iehacks_out = preg_replace_callback(
				'#%%IEHACK%%(.*?)%%IEHACK%%#is',
				create_function(
					'$matches',
					'return stripslashes(base64_decode($matches[1]));'
				),
				$iehacks_in
			);
		} else {
			$iehacks_out = $iehacks_in;
		}
		return $iehacks_out;
	}

    protected function hide_comments($comments_in)
    {
        if ( false !== strpos( $comments_in, '<!--' ) ) {
            $comments_out = preg_replace_callback(
                '#<!--.*?-->#is',
                create_function(
                    '$matches',
                    'return "%%COMMENTS%%".base64_encode($matches[0])."%%COMMENTS%%";'
                ),
                $comments_in
            );
        } else {
            $comments_out = $comments_in;
        }
        return $comments_out;
    }

    protected function restore_comments($comments_in)
    {
        if ( false !== strpos( $comments_in, '%%COMMENTS%%' ) ) {
            $comments_out = preg_replace_callback(
                '#%%COMMENTS%%(.*?)%%COMMENTS%%#is',
                create_function(
                    '$matches',
                    'return stripslashes(base64_decode($matches[1]));'
                ),
                $comments_in
            );
        } else {
            $comments_out = $comments_in;
        }
        return $comments_out;
	}

    protected function url_replace_cdn($url)
    {
        if ( ! empty( $this->cdn_url ) ) {
        	$this->debug_log('before=' . $url);
            // first allow API filter to take care of CDN replacement
            $tmp_url = apply_filters( 'autoptimize_base_replace_cdn', $url );
            if ( $tmp_url === $url ) {
                // Filter didn't change anything, proceed
                // Simple str_replace-based approach fails when $url is protocol-or-host-relative.
                $is_protocol_relative = ( '/' === $url{1} ); // second char is '/'
                $is_host_relative     = ( ! $is_protocol_relative && ( '/' === $url{0} ) );
                $cdn_url              = rtrim( $this->cdn_url, '/' );

                // $this->debug_log('is_protocol_relative=' . $is_protocol_relative);
                // $this->debug_log('is_host_relative=' . $is_host_relative);
                // $this->debug_log('cdn_url=' . $cdn_url);

                if ( $is_host_relative ) {
                    // Prepending host-relative urls with the cdn url
                    $url = $cdn_url . $url;
                } else {
                	// Either a protocol-relative or "regular" url, replacing it either way
                	if ( $is_protocol_relative ) {
                		// Massage $site_url so that simple str_replace still "works" by
                		// searching for the protocol-relative version of AUTOPTIMIZE_WP_SITE_URL
                		$site_url = str_replace( array( 'http:', 'https:' ), '', AUTOPTIMIZE_WP_SITE_URL );
                	} else {
                		$site_url = AUTOPTIMIZE_WP_SITE_URL;
                	}
            		$this->debug_log('`' . $site_url . '` -> `' . $cdn_url . '` in `' . $url . '`');
                	$url = str_replace( $site_url, $cdn_url, $url );
                }
            }
            $this->debug_log('after=' . $url);
        }
        return $url;
    }

	protected function inject_in_html($payload,$replaceTag)
	{
		$warned = false;
		if ( false !== strpos( $this->content, $replaceTag[0] ) ) {
			if ('after' === $replaceTag[1] ) {
				$replaceBlock = $replaceTag[0] . $payload;
			} else if ('replace' === $replaceTag[1] ){
				$replaceBlock = $payload;
			} else {
				$replaceBlock = $payload . $replaceTag[0];
			}
			$this->content = str_replace( $replaceTag[0], $replaceBlock, $this->content );
		} else {
			$this->content .= $payload;
			if ( ! $warned ) {
				$this->content .= "<!--noptimize--><!-- Autoptimize found a problem with the HTML in your Theme, tag `" . $replaceTag[0] . "` missing --><!--/noptimize-->";
				$warned = true;
			}
		}
	}

    protected function debug_log($data)
    {
        if ( ! isset( $this->debug_log ) || ! $this->debug_log ) {
            return;
        }

        if ( ! is_string( $data ) ) {
            $data = var_export( $data, true );
        }

        error_log( $data );
    }
}
