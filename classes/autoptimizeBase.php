<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

abstract class autoptimizeBase
{
    protected $content = '';

    public $debug_log = false;

    public function __construct($content)
    {
        $this->content = $content;
    }

    // Reads the page and collects tags
    abstract public function read($justhead);

    // Joins and optimizes collected things
    abstract public function minify();

    // Caches the things
    abstract public function cache();

    // Returns the content
    abstract public function getcontent();

    // Converts an URL to a full path
    public function getpath($url)
    {
        $url = apply_filters( 'autoptimize_filter_cssjs_alter_url', $url );

        if ( false !== strpos( $url, '%' ) ) {
            $url = urldecode( $url );
        }

        $site_host = parse_url( AUTOPTIMIZE_WP_SITE_URL, PHP_URL_HOST );
        $content_host = parse_url( AUTOPTIMIZE_WP_ROOT_URL, PHP_URL_HOST );

        // Normalize
        if (0 === strpos( $url, '//' ) ) {
            if ( is_ssl() ) {
                $url = 'https:' . $url;
            } else {
                $url = 'http:' . $url;
            }
        } elseif ( ( false === strpos($url, '//' ) ) && ( false === strpos( $url, $site_host ) ) ) {
            if ( AUTOPTIMIZE_WP_SITE_URL === $site_host ) {
                $url = AUTOPTIMIZE_WP_SITE_URL . $url;
            } else {
                $subdir_levels = substr_count( preg_replace( '/https?:\/\//', '', AUTOPTIMIZE_WP_SITE_URL ), '/' );
                $url = AUTOPTIMIZE_WP_SITE_URL . str_repeat( '/..', $subdir_levels ) . $url;
            }
        }

        if ($site_host !== $content_host) {
            $url = str_replace( AUTOPTIMIZE_WP_CONTENT_URL, AUTOPTIMIZE_WP_SITE_URL . AUTOPTIMIZE_WP_CONTENT_NAME, $url );
        }

        // First check; hostname wp site should be hostname of url
        $url_host = @parse_url( $url, PHP_URL_HOST );
        if ( $url_host !== $site_host ) {
            /*
            * first try to get all domains from WPML (if available)
            * then explicitely declare $this->cdn_url as OK as well
            * then apply own filter autoptimize_filter_cssjs_multidomain takes an array of hostnames
            * each item in that array will be considered part of the same WP multisite installation
            */
            $multidomains = array();

            $multidomainsWPML = apply_filters( 'wpml_setting', array(), 'language_domains' );
            if (!empty($multidomainsWPML)) {
                $multidomains = array_map( array( $this, 'ao_getDomain'), $multidomainsWPML );
            }

            if ( ! empty( $this->cdn_url ) ) {
                $multidomains[] = parse_url( $this->cdn_url, PHP_URL_HOST );
            }

            $multidomains = apply_filters( 'autoptimize_filter_cssjs_multidomain', $multidomains );

            if ( ! empty( $multidomains ) ) {
                if ( in_array( $url_host, $multidomains ) ) {
                    $url = str_replace( $url_host, $site_host, $url );
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Try to remove "wp root url" from url while not minding http<>https
        $tmp_ao_root = preg_replace( '/https?:/', '', AUTOPTIMIZE_WP_ROOT_URL );

        if ($site_host !== $content_host) {
            // as we replaced the content-domain with the site-domain, we should match against that
            $tmp_ao_root = preg_replace( '/https?:/', '', AUTOPTIMIZE_WP_SITE_URL );
        }

        $tmp_url     = preg_replace( '/https?:/', '', $url );
        $path        = str_replace( $tmp_ao_root, '', $tmp_url );

        // if path starts with :// or //, this is not a URL in the WP context and we have to assume we can't aggregate
        if ( preg_match( '#^:?//#', $path ) ) {
            /** External script/css (adsense, etc) */
            return false;
        }

        // prepend with WP_ROOT_DIR to have full path to file
        $path = str_replace( '//', '/', WP_ROOT_DIR . $path );

        // final check: does file exist and is it readable
        if ( file_exists( $path ) && is_file( $path ) && is_readable( $path ) ) {
            return $path;
        } else {
            return false;
        }
    }

    // needed for WPML-filter
    protected function ao_getDomain($in) {
        // make sure the url starts with something vaguely resembling a protocol
        if ( ( strpos( $in, 'http' ) !== 0 ) && (strpos($in, '//') !== 0 ) ) {
            $in = 'http://' . $in;
        }

        // do the actual parse_url
        $out = parse_url( $in, PHP_URL_HOST );

        // fallback if parse_url does not understand the url is in fact a url
        if ( empty( $out ) ) {
            $out = in;
        }

        return $out;
    }

    // hide everything between noptimize-comment tags
    protected function hide_noptimize($markup)
    {
        return $this->replace_contents_with_marker_if_exists(
                'NOPTIMIZE',
                '/<!--\s?noptimize\s?-->/',
                '#<!--\s?noptimize\s?-->.*?<!--\s?/\s?noptimize\s?-->#is',
                $markup
        );
    }

    // Unhide noptimize-tags
    protected function restore_noptimize($markup)
    {
        return $this->restore_marked_content('NOPTIMIZE', $markup);
    }

    protected function hide_iehacks($markup)
    {
        return $this->replace_contents_with_marker_if_exists(
                'IEHACK', // marker name
                '<!--[if', // not-valid regex, will fallback to search using strpos()
                '#<!--\[if.*?\[endif\]-->#is', // replacement regex
                $markup
        );
    }

    protected function restore_iehacks($markup)
    {
        return $this->restore_marked_content('IEHACK', $markup);
    }

    /**
     * "Hides" content within HTML comments using a regex-based replacement
     * if HTML comment markers are found.
     * `<!--example-->` becomes `%%COMMENTS%%ZXhhbXBsZQ==%%COMMENTS%%`
     *
     * @param string $markup
     * @return string
     */
    protected function hide_comments($markup)
    {
        return $this->replace_contents_with_marker_if_exists(
                'COMMENTS',
                '<!--',
                '#<!--.*?-->#is',
                $markup
        );
    }

    /**
     * Restores original HTML comment markers inside a string whose HTML
     * comments have been "hidden" by using `hide_comments()`.
     *
     * @param string $markup
     * @return string
     */
    protected function restore_comments($markup)
    {
        return $this->restore_marked_content('COMMENTS', $markup);
    }

    public function url_replace_cdn($url)
    {
        $cdn_url = apply_filters( 'autoptimize_filter_base_cdnurl', $this->cdn_url );
        if ( ! empty( $cdn_url ) ) {
            $this->debug_log('before=' . $url);

            // Simple str_replace-based approach fails when $url is protocol-or-host-relative.
            $is_protocol_relative = ( '/' === $url{1} ); // second char is '/'
            $is_host_relative     = ( ! $is_protocol_relative && ( '/' === $url{0} ) );
            $cdn_url              = rtrim( $cdn_url, '/' );

            // $this->debug_log('is_protocol_relative=' . $is_protocol_relative);
            // $this->debug_log('is_host_relative=' . $is_host_relative);
            // $this->debug_log('cdn_url=' . $cdn_url);

            // TODO/FIXME: This relies on the `AUTOPTIMIZE_WP_SITE_URL` constant being defined, which
            // might be fine when everything is being called through `autoptimize_end_buffering()`, but
            // we really can't easily unit test things that way... So much coupling...

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

            $this->debug_log('after=' . $url);
        }

        // allow API filter to take care of CDN replacement
        $url = apply_filters( 'autoptimize_filter_base_replace_cdn', $url );

        return $url;
    }

    protected function inject_in_html($payload, $replaceTag)
    {
        $warned = false;
        if ( false !== strpos( $this->content, $replaceTag[0] ) ) {
            if ( 'after' === $replaceTag[1] ) {
                $replaceBlock = $replaceTag[0] . $payload;
            } else if ( 'replace' === $replaceTag[1] ){
                $replaceBlock = $payload;
            } else {
                $replaceBlock = $payload . $replaceTag[0];
            }
            $this->content = substr_replace( $this->content, $replaceBlock, strpos( $this->content, $replaceTag[0] ), strlen( $replaceTag[0] ) );
        } else {
            $this->content .= $payload;
            if ( ! $warned ) {
                $tag_display = str_replace( array( '<', '>' ), '', $replaceTag[0] );
                $this->content .= "<!--noptimize--><!-- Autoptimize found a problem with the HTML in your Theme, tag `" . $tag_display . "` missing --><!--/noptimize-->";
                $warned = true;
            }
        }
    }

    protected function isremovable($tag, $removables)
    {
        foreach ( $removables as $match ) {
            if ( false !== strpos( $tag, $match ) ) {
                return true;
            }
        }

        return false;
    }

    // Callback used in self::inject_minified()
    public function inject_minified_callback($matches)
    {
        static $conf = null;
        if ( null === $conf ) {
            $conf = autoptimizeConfig::instance();
        }

        /**
         * $hashes[1] holds the whole match caught by regex in inject_minified(),
         * so we take that and split the string on `|`.
         * First element is the filepath, second is the md5 hash of contents the filepath
         * had when it was being processed.
         * If we don't have those, or the md5 hashes don't match, we'll bail out early.
         *
         * N.B.:
         * This introduces the potential of a race condition (in the sense that the file in
         * question could've legitimately been changed between when it was being processed
         * and now that it is being placed back). This now results in the new file contents
         * no longer being included in the resulting AO-ed file (while previously, they
         * would've been included).
         */
        $filepath = null;
        $filehash = null;
        $hash_missmatch = false;

        // Grab the parts we need
        $parts = explode( $matches[1], '|' );
        if ( ! empty( $parts ) ) {
            $filepath = isset($parts[0]) ? $parts[0] : null;
            $filehash = isset($parts[1]) ? $parts[1] : null;
        }

        // Check that hash matches if we've gotten the parts earlier
        if ( $filepath && $filehash ) {
            $filecontent = file_get_contents( $filepath );
            $hash_actual = md5( $filecontent );
            if ( $hash_actual !== $filehash ) {
                $hash_missmatch = true;
            }
        }

        // Bail early if something's not right...
        if ( ! $filepath || ! $filehash || $hash_missmatch ) {
            return "\n";
        }

        $filecontent = file_get_contents( $filepath );

        // Some things are differently handled for css/js
        $is_js_file = ( '.js' === substr( $filepath, -3, 3 ) );

        $is_css_file = false;
        if ( ! $is_js_file ) {
            $is_css_file = ( '.css' === substr( $filepath, -4, 4 ) );
        }

        // Upstream nukes BOM here, although not really sure why...
        // It doesn't seem like something AO should be doing... And if it should, it shouldn't
        // just blindly strip all occurences of those three bytes in the entire file, no?
        // (but rather strip the first three bytes [if those first three bytes are the BOM])
        // $filecontent = preg_replace( "#\x{EF}\x{BB}\x{BF}#", '', $filecontent );

        // Remove comments and blank lines
        if ( $is_js_file ) {
            $filecontent = preg_replace( '#^\s*\/\/.*$#Um', '', $filecontent );
        }

        // Nuke un-important comments
        $filecontent = preg_replace( '#^\s*\/\*[^!].*\*\/\s?#Um', '', $filecontent );

        // Normalize newlines
        $filecontent = preg_replace( '#(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+#', "\n", $filecontent );

        // JS specifics
        if ( $is_js_file ) {
            // Append a semicolon at the end of js files if it's missing
            $last_char = substr( $filecontent, -1, 1);
            if ( ';' !== $last_char && '}' !== $last_char ) {
                $filecontent .= ';';
            }
            // Check if try/catch should be used
            $opt_js_try_catch = $conf->get('autoptimize_js_trycatch');
            if ( 'on' === $opt_js_try_catch ) {
                // Wrap in try/catch
                $filecontent = 'try{' . $filecontent . '}catch(e){}';
            }
        } elseif ( $is_css_file ) {
            $filecontent = autoptimizeStyles::fixurls($filepath, $filecontent);
        } else {
            $filecontent = '';
        }

        // Return modified code
        return "\n" . $filecontent;
    }

    // Inject already minified code in optimized JS/CSS
    protected function inject_minified($in) {
        $out = $in;

        if ( false !== strpos( $in, '%%INJECTLATER%%' ) ) {
            $out = preg_replace_callback(
                '#\/\*\!%%INJECTLATER' . AUTOPTIMIZE_HASH . '%%(.*?)%%INJECTLATER%%\*\/#is',
                array( $this, 'inject_minified_callback' ),
                $in
            );
        }

        return $out;
    }

    /**
     * Returns true if the string is a valid regex.
     *
     * @param string $string
     *
     * @return bool
     */
    protected function str_is_valid_regex($string)
    {
        set_error_handler( function() {}, E_WARNING );
        $is_regex = ( false !== preg_match( $string, '' ) );
        restore_error_handler();

        return $is_regex;
    }

    /**
     * Searches for `$search` in `$content` (using either `preg_match()`
     * or `strpos()`, depending on whether `$search` is a valid regex pattern or not).
     * If something is found, it replaces `$content` using `$re_replace_pattern`,
     * effectively creating our named markers (`%%{$marker}%%`.
     * These are then at some point replaced back to their actual/original/modified
     * contents using `autoptimizeBase::restore_marked_content()`.
     *
     * @param string $marker Marker name (without percent characters)
     * @param string $search A string or full blown regex pattern to search for in $content. Uses `strpos()` or `preg_match()`
     * @param string $re_replace_pattern Regex pattern to use when replacing contents
     * @param string $content Content to work on
     *
     * @return string
     */
    protected function replace_contents_with_marker_if_exists($marker, $search, $re_replace_pattern, $content)
    {
        $found = false;

        $is_regex = $this->str_is_valid_regex($search);
        if ( $is_regex ) {
            $found = preg_match( $search, $content );
        } else {
            $found = ( false !== strpos( $content, $search ) );
        }

        if ( $found ) {
            $content = preg_replace_callback(
                $re_replace_pattern,
                create_function(
                    '$matches',
                    'return "%%' . $marker . AUTOPTIMIZE_HASH . '%%" . base64_encode($matches[0]) . "%%' . $marker . '%%";'
                ),
                $content
            );
        }

        return $content;
    }

    /**
     * Complements `autoptimizeBase::replace_contents_with_marker_if_exists()`
     *
     * @param string $marker
     * @param string $content
     * @return string
     */
    protected function restore_marked_content($marker, $content)
    {
        if ( false !== strpos( $content, $marker ) ) {
            $content = preg_replace_callback(
                '#%%' . $marker . AUTOPTIMIZE_HASH . '%%(.*?)%%' . $marker . '%%#is',
                create_function(
                    '$matches',
                    'return base64_decode($matches[1]);'
                ),
                $content
            );
        }

        return $content;
    }

    protected function debug_log($data)
    {
        if ( ! isset( $this->debug_log ) || ! $this->debug_log ) {
            return;
        }

        if ( ! is_string( $data ) && !is_resource( $data ) ) {
            $data = var_export( $data, true );
        }

        error_log( $data );
    }
}
