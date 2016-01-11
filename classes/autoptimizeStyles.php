<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeStyles extends autoptimizeBase
{
    const ASSETS_REGEX = '/url\s*\(\s*(?!["\']?data:)([^)]+)\s*\)/i';

    private $css             = array();
    private $csscode         = array();
    private $url             = array();
    private $restofcontent   = '';
    private $mhtml           = '';
    private $datauris        = false;
    private $hashmap         = array();
    private $alreadyminified = false;
    private $inline          = false;
    private $defer           = false;
    private $defer_inline    = false;
    private $whitelist       = '';
    private $cssinlinesize   = '';
    private $cssremovables   = array();
    private $include_inline  = false;
    private $inject_min_late = '';

    // Reads the page and collects style tags
    public function read($options)
    {
        $noptimizeCSS = apply_filters( 'autoptimize_filter_css_noptimize', false, $this->content );
        if ( $noptimizeCSS ) {
            return false;
        }

        $whitelistCSS = apply_filters( 'autoptimize_filter_css_whitelist', '' );
        if ( ! empty( $whitelistCSS ) ) {
            $this->whitelist = array_filter( array_map( 'trim', explode(',', $whitelistCSS ) ) );
        }

        if ($options['nogooglefont']) {
            $removableCSS = 'fonts.googleapis.com';
        } else {
            $removableCSS = '';
        }
        $removableCSS = apply_filters( 'autoptimize_filter_css_removables', $removableCSS );
        if ( ! empty( $removableCSS ) ) {
            $this->cssremovables = array_filter( array_map( 'trim', explode( ',', $removableCSS ) ) );
        }

        $this->cssinlinesize = apply_filters( 'autoptimize_filter_css_inlinesize', 256 );

        // filter to "late inject minified CSS", default to true for now (it is faster)
        $this->inject_min_late = apply_filters( 'autoptimize_filter_css_inject_min_late', true );

        // Remove everything that's not the header
        if ( apply_filters( 'autoptimize_filter_css_justhead', $options['justhead'] ) ) {
            $content             = explode( '</head>', $this->content, 2 );
            $this->content       = $content[0] . '</head>';
            $this->restofcontent = $content[1];
        }

        // include inline?
        if ( apply_filters( 'autoptimize_css_include_inline', $options['include_inline'] ) ) {
            $this->include_inline = true;
        }

        // List of CSS strings which are excluded from autoptimization
        $excludeCSS = apply_filters( 'autoptimize_filter_css_exclude', $options['css_exclude'] );
        if ( '' !== $excludeCSS ) {
            $this->dontmove = array_filter( array_map( 'trim', explode( ',', $excludeCSS ) ) );
        } else {
            $this->dontmove = '';
        }

        // Should we defer css?
        // value: true / false
        $this->defer = $options['defer'];
        $this->defer = apply_filters( 'autoptimize_filter_css_defer', $this->defer );

        // Should we inline while deferring?
        // value: inlined CSS
        $this->defer_inline = $options['defer_inline'];

        // Should we inline?
        // value: true / false
        $this->inline = $options['inline'];
        $this->inline = apply_filters( 'autoptimize_filter_css_inline', $this->inline );

        // Store cdn url
        $this->cdn_url = $options['cdn_url'];

        // Store data: URIs setting for later use
        $this->datauris = $options['datauris'];

        // noptimize me
        $this->content = $this->hide_noptimize($this->content);

        // Exclude (no)script, as those may contain CSS which should be left as is
        if ( false !== strpos( $this->content, '<script' ) ) {
            $this->content = preg_replace_callback(
                '#<(?:no)?script.*?<\/(?:no)?script>#is',
                create_function(
                    '$matches',
                    'return "%%SCRIPT%%".base64_encode($matches[0])."%%SCRIPT%%";'
                ),
                $this->content
            );
        }

        // Save IE hacks
        $this->content = $this->hide_iehacks($this->content);

        // Hide HTML comments
        $this->content = $this->hide_comments($this->content);

        // Get <style> and <link>
        if ( preg_match_all( '#(<style[^>]*>.*</style>)|(<link[^>]*stylesheet[^>]*>)#Usmi', $this->content, $matches ) ) {

            foreach ( $matches[0] as $tag ) {
                if ( $this->isremovable($tag, $this->cssremovables ) ) {
                    $this->content = str_replace( $tag, '', $this->content );
                } else if ( $this->ismovable($tag) ) {
                    // Get the media
                    if ( false !== strpos( $tag, 'media=' ) ) {
                        preg_match( '#media=(?:"|\')([^>]*)(?:"|\')#Ui', $tag, $medias );
                        $medias = explode( ',', $medias[1] );
                        $media = array();
                        foreach ( $medias as $elem ) {
                            // $media[] = current(explode(' ',trim($elem),2));
                            if ( empty( $elem ) ) {
                                $elem = 'all';
                            }

                            $media[] = $elem;
                        }
                    } else {
                        // No media specified - applies to all
                        $media = array( 'all' );
                    }

                    $media = apply_filters( 'autoptimize_filter_css_tagmedia', $media, $tag );

                    if ( preg_match( '#<link.*href=("|\')(.*)("|\')#Usmi', $tag, $source ) ) {
                        // <link>
                        $url  = current( explode( '?', $source[2], 2 ) );
                        $path = $this->getpath($url);

                        if ( false !== $path && preg_match( '#\.css$#', $path ) ) {
                            // Good link
                            $this->css[] = array( $media, $path );
                        } else {
                            // Link is dynamic (.php etc)
                            $tag = '';
                        }
                    } else {
                        // Inline css in style tags can be wrapped in comment tags, so restore comments
                        $tag = $this->restore_comments($tag);
                        preg_match( '#<style.*>(.*)</style>#Usmi', $tag, $code );

                        // And re-hide them to be able to to the removal based on tag
                        $tag = $this->hide_comments($tag);

                        if ( $this->include_inline ) {
                            $code = preg_replace( '#^.*<!\[CDATA\[(?:\s*\*/)?(.*)(?://|/\*)\s*?\]\]>.*$#sm', '$1', $code[1] );
                            $this->css[] = array( $media, 'INLINE;' . $code );
                        } else {
                            $tag = '';
                        }
                    }

                    // Remove the original style tag
                    $this->content = str_replace( $tag, '', $this->content );
                }
            }
            return true;
        }

        // Really, no styles?
        return false;
    }

    /**
     * Checks if the local file referenced by $path is a valid
     * candidate for being inlined into a data: URI
     *
     * @param string $path
     * @return boolean
     */
    private function is_datauri_candidate($path)
    {
        // Call only once since it's called from a loop
        static $max_size = null;
        if ( null === $max_size ) {
            $max_size = $this->get_datauri_maxsize();
        }

        if ( $path && preg_match( '#\.(jpe?g|png|gif|webp|bmp)$#i', $path ) &&
            file_exists( $path ) && is_readable( $path ) && filesize( $path ) <= $max_size ) {

            // Seems we have a candidate
            return true;
        }

        // Noup, anything else ain't a candidate
        return false;
    }

    /**
     * Returns the amount of bytes that shouldn't be exceeded if a file is to
     * be inlined into a data: URI. Defaults to 4096, passed through
     * `autoptimize_filter_css_datauri_maxsize` filter.
     *
     * @return mixed
     */
    private function get_datauri_maxsize()
    {
        static $max_size = null;

        /**
         * No need to apply the filter multiple times in case the
         * method itself is invoked multiple times during a single request.
         * This prevents some wild stuff like having different maxsizes
         * for different files/site-sections etc. But if you're into that sort
         * of thing you're probably better of building assets completely
         * outside of WordPress anyway.
         */
        if (null === $max_size) {
            $max_size = (int) apply_filters( 'autoptimize_filter_css_datauri_maxsize', 4096 );
        }

        return $max_size;
    }

    private function check_datauri_exclude_list($url)
    {
        static $exclude_list = null;
        $no_datauris         = array();

        // Again, skip doing certain stuff repeatedly when loop-called
        if ( null === $exclude_list ) {
            $exclude_list = apply_filters( 'autoptimize_filter_css_datauri_exclude', '' );
            $no_datauris = array_filter( array_map( 'trim', explode( ',', $exclude_list ) ) );
        }

        $matched = false;

        if ( ! empty( $exclude_list ) ) {
            foreach ( $no_datauris as $no_datauri ) {
                if ( false !== strpos( $url, $no_datauri ) ) {
                    $matched = true;
                    break;
                }
            }
        }

        return $matched;
    }

    private function build_or_get_datauri_image($path)
    {
        $hash = md5( $path );
        $check = new autoptimizeCache($hash, 'img');
        if ( $check->check() ) {
            // we have the base64 image in cache
            $headAndData = $check->retrieve();
            $_base64data = explode( ';base64,', $headAndData );
            $base64data  = $_base64data[1];
            unset( $_base64data );
        } else {
            // It's an image and we don't have it in cache, get the type by extension
            $exploded_path = explode( '.', $path );
            $type = end( $exploded_path );

            switch ( $type ) {
                case 'jpg':
                case 'jpeg':
                    $dataurihead = 'data:image/jpeg;base64,';
                    break;
                case 'gif':
                    $dataurihead = 'data:image/gif;base64,';
                    break;
                case 'png':
                    $dataurihead = 'data:image/png;base64,';
                    break;
                case 'bmp':
                    $dataurihead = 'data:image/bmp;base64,';
                    break;
                case 'webp':
                    $dataurihead = 'data:image/webp;base64,';
                    break;
                default:
                    $dataurihead = 'data:application/octet-stream;base64,';
            }

            // Encode the data
            $base64data  = base64_encode( file_get_contents( $path ) );
            $headAndData = $dataurihead . $base64data;

            // Save in cache
            $check->cache($headAndData, 'text/plain');
        }
        unset( $check );

        return array( 'full' => $headAndData, 'base64data' => $base64data );
    }

    // Re-write (and/or inline) referenced assets
    public function rewrite_assets($code)
    {
        // Re-write (and/or inline) URLs to point them to the CDN host
        $url_src_matches = array();
        // Matches and captures anything specified within the literal `url()` and excludes those containing data: URIs
        preg_match_all( self::ASSETS_REGEX, $code, $url_src_matches );
        if ( is_array( $url_src_matches ) && ! empty( $url_src_matches ) ) {
            foreach ( $url_src_matches[1] as $count => $original_url ) {
                // Removes quotes and other cruft
                $url = trim( $original_url, " \t\n\r\0\x0B\"'" );

                // If datauri inlining is turned on, do it
                $inlined = false;
                if ( $this->datauris ) {
                    $iurl = $url;
                    if ( false !== strpos( $iurl, '?' ) ) {
                        $iurl = strtok( $iurl, '?' );
                    }

                    $ipath = $this->getpath($iurl);

                    $excluded = $this->check_datauri_exclude_list($ipath);
                    if ( ! $excluded ) {
                        $is_datauri_candidate = $this->is_datauri_candidate($ipath);
                        if ( $is_datauri_candidate ) {
                            $datauri     = $this->build_or_get_datauri_image($ipath);
                            $base64data  = $datauri['base64data'];

                            // Add it to the list for replacement
                            $imgreplace[$url_src_matches[1][$count]] = str_replace( $original_url, $datauri['full'], $url_src_matches[1][$count] ) . ";\n*" . str_replace( $original_url, 'mhtml:%%MHTML%%!' . $mhtmlcount, $url_src_matches[1][$count] ) . ";\n_" . $url_src_matches[1][$count] . ';';

                            // Store image on the mhtml document
                            $this->mhtml .= "--_\r\nContent-Location:{$mhtmlcount}\r\nContent-Transfer-Encoding:base64\r\n\r\n{$base64data}\r\n";
                            $mhtmlcount++;
                            $inlined = true;
                        }
                    }
                }

                /**
                 * Doing CDN URL replacement for every found match (if CDN is
                 * specified). This way we make sure to do it even if
                 * inlining isn't turned on, or if a resource is skipped from
                 * being inlined for whatever reason above.
                 */
                if ( ! $inlined  && ! empty( $this->cdn_url ) ) {
                    // Just do the "simple" CDN replacement
                    $cdn_url = $this->url_replace_cdn($url);
                    $imgreplace[ $url_src_matches[1][ $count ] ] = str_replace(
                        $original_url, $cdn_url, $url_src_matches[1][$count]
                    );
                }
            }
        }

        if ( ! empty( $imgreplace ) ) {
            $this->debug_log( $imgreplace );
            $code = str_replace( array_keys( $imgreplace ), array_values( $imgreplace ), $code );
        }

        return $code;
    }

    // Joins and optimizes CSS
    public function minify()
    {
        foreach ( $this->css as $group ) {
            list( $media, $css ) = $group;
            if ( preg_match( '#^INLINE;#', $css ) ) {
                // <style>
                $css = preg_replace( '#^INLINE;#', '', $css );
                $css = self::fixurls(ABSPATH . 'index.php', $css); // ABSPATH already contains a trailing slash
                $tmpstyle = apply_filters( 'autoptimize_css_individual_style', $css, '' );
                if ( has_filter( 'autoptimize_css_individual_style' ) && ! empty( $tmpstyle ) ) {
                    $css = $tmpstyle;
                    $this->alreadyminified = true;
                }
            } else {
                // <link>
                if ( false !== $css && file_exists( $css ) && is_readable( $css ) ) {
                    $cssPath = $css;
                    $css = self::fixurls($cssPath, file_get_contents( $cssPath ));
                    $css = preg_replace( '/\x{EF}\x{BB}\x{BF}/', '', $css );
                    $tmpstyle = apply_filters( 'autoptimize_css_individual_style', $css, $cssPath );
                    if ( has_filter( 'autoptimize_css_individual_style' ) && ! empty( $tmpstyle ) ) {
                        $css = $tmpstyle;
                        $this->alreadyminified = true;
                    } else if ( $this->can_inject_late($css_path, $css) ) {
                        $css = '%%INJECTLATER%%' . base64_encode( $cssPath ) . '|' . md5( $css ) . '%%INJECTLATER%%';
                    }
                } else {
                    // Couldn't read CSS. Maybe getpath isn't working?
                    $css = '';
                }
            }

            foreach ( $media as $elem ) {
                if ( ! empty( $css ) ) {
                    if ( ! isset( $this->csscode[$elem] ) ) {
                        $this->csscode[$elem] = '';
                    }
                    $this->csscode[$elem] .= "\n/*FILESTART*/" . $css;
                }
            }
        }

        // Check for duplicate code
        $md5list = array();
        $tmpcss  = $this->csscode;
        foreach ( $tmpcss as $media => $code ) {
            $md5sum    = md5( $code );
            $medianame = $media;
            foreach ( $md5list as $med => $sum ) {
                // If same code
                if ( $sum === $md5sum ) {
                    // Add the merged code
                    $medianame                 = $med . ', ' . $media;
                    $this->csscode[$medianame] = $code;
                    $md5list[$medianame]       = $md5list[$med];
                    unset( $this->csscode[$med], $this->csscode[$media], $med5list[$med] );
                }
            }
            $md5list[$medianame] = $md5sum;
        }
        unset( $tmpcss );

        // Manage @imports, while is for recursive import management
        foreach ( $this->csscode as &$thiscss ) {
            // Flag to trigger import reconstitution and var to hold external imports
            $fiximports       = false;
            $external_imports = '';

            while ( preg_match_all( '#^(/*\s?)@import.*(?:;|$)#Um', $thiscss, $matches ) ) {
                foreach ( $matches[0] as $import ) {
                    if ( $this->isremovable( $import, $this->cssremovables ) ) {
                        $thiscss = str_replace( $import, '', $thiscss );
                        $import_ok = true;
                    } else {
                        $url = trim( preg_replace( '#^.*((?:https?:|ftp:)?//.*\.css).*$#', '$1', trim( $import ) ), " \t\n\r\0\x0B\"'" );
                        $path = $this->getpath($url);
                        $import_ok = false;
                        if ( file_exists( $path ) && is_readable( $path ) ) {
                            $code = addcslashes( self::fixurls($path, file_get_contents( $path ) ), "\\" );
                            $code = preg_replace( '/\x{EF}\x{BB}\x{BF}/', '', $code );
                            $tmpstyle = apply_filters( 'autoptimize_css_individual_style', $code, '' );
                            if ( has_filter( 'autoptimize_css_individual_style' ) && ! empty( $tmpstyle ) ) {
                                $code = $tmpstyle;
                                $this->alreadyminified = true;
                            } else if ( $this->can_inject_late($path, $code) ) {
                                $code = '%%INJECTLATER%%' . base64_encode( $path ) . '|' . md5( $code ) . '%%INJECTLATER%%';
                            }

                            if ( ! empty( $code ) ) {
                                $tmp_thiscss = preg_replace( '#(/\*FILESTART\*/.*)' . preg_quote( $import, '#' ) . '#Us', '/*FILESTART2*/' . $code . '$1', $thiscss );
                                if ( ! empty( $tmp_thiscss ) ) {
                                    $thiscss = $tmp_thiscss;
                                    $import_ok = true;
                                    unset( $tmp_thiscss );
                                }
                            }
                            unset( $code );
                        }
                    }
                    if ( ! $import_ok ) {
                        // External imports and general fall-back
                        $external_imports .= $import;

                        $thiscss    = str_replace( $import, '', $thiscss );
                        $fiximports = true;
                    }
                }
                $thiscss = preg_replace( '#/\*FILESTART\*/#', '', $thiscss );
                $thiscss = preg_replace( '#/\*FILESTART2\*/#', '/*FILESTART*/', $thiscss );
            }

            // Add external imports to top of aggregated CSS
            if ( $fiximports ) {
                $thiscss = $external_imports . $thiscss;
            }
        }
        unset( $thiscss );

        // $this->csscode has all the uncompressed code now.
        $mhtmlcount = 0;
        foreach ( $this->csscode as &$code ) {
            // Check for already-minified code
            $hash = md5( $code );
            $ccheck = new autoptimizeCache($hash, 'css');
            if ( $ccheck->check() ) {
                $code = $ccheck->retrieve();
                $this->hashmap[md5( $code )] = $hash;
                continue;
            }
            unset( $ccheck );

            // Rewrite and/or inline referenced assets
            $code = $this->rewrite_assets($code);

            // $code = $this->cdn_fonts($code);

            // Minify
            $code = $this->run_minifier_on($code);

            $this->hashmap[md5( $code )] = $hash;
        }

        unset( $code );
        return true;
    }

    public function run_minifier_on($code)
    {
        if ( ! $this->alreadyminified ) {
            $do_minify = apply_filters( 'autoptimize_css_do_minify', true );

            if ( $do_minify ) {
                $tmp_code = null;
                if ( class_exists( 'Minify_CSS_Compressor' ) ) {
                    $tmp_code = trim( Minify_CSS_Compressor::process($code) );
                } elseif ( class_exists( 'CSSmin' ) ) {
                    $cssmin = new CSSmin();
                    if ( method_exists( $cssmin, 'run' ) ) {
                        $tmp_code = trim( $cssmin->run($code) );
                    } elseif ( @is_callable( array( $cssmin, 'minify') ) ) {
                        $tmp_code = trim( CssMin::minify($code) );
                    }
                }

                $tmp_code = $this->inject_minified($tmp_code);

                $tmp_code = apply_filters( 'autoptimize_css_after_minify', $tmp_code );
                if ( ! empty( $tmp_code ) ) {
                    $code = $tmp_code;
                    unset( $tmp_code );
                }
            }
        }

        return $code;
    }

    public function cdn_fonts($code)
    {
        // CDN the fonts!
        if ( ( ! empty( $this->cdn_url ) ) && apply_filters( 'autoptimize_filter_css_fonts_cdn', false ) ) {
            $fontreplace = array();
            $fonturl_regex = <<<'LOD'
~(?(DEFINE)(?<quoted_content>(["']) (?>[^"'\\]++ | \\{2} | \\. | (?!\g{-1})["'] )*+ \g{-1})(?<comment> /\* .*? \*/ ) (?<url_skip>(?: data: ) [^"'\s)}]*+ ) (?<other_content>(?> [^u}/"']++ | \g<quoted_content> | \g<comment> | \Bu | u(?!rl\s*+\() | /(?!\*) | \g<url_start> \g<url_skip> ["']?+ )++ ) (?<anchor> \G(?<!^) ["']?+ | @font-face \s*+ { ) (?<url_start> url\( \s*+ ["']?+ ) ) \g<comment> (*SKIP)(*FAIL) | \g<anchor> \g<other_content>?+ \g<url_start> \K ((?:(?:https?:)?(?://[[:alnum:]\-\.]+)(?::[0-9]+)?)?\/[^"'\s)}]*+) ~xs
LOD;

            preg_match_all($fonturl_regex, $code, $matches);
            if ( is_array( $matches ) ) {
                foreach ( $matches[8] as $count => $quotedurl ) {
                    $url = trim($quotedurl, " \t\n\r\0\x0B\"'");
                    $cdn_url = $this->url_replace_cdn($url);
                    $fontreplace[$matches[8][$count]] = str_replace( $quotedurl, $cdn_url, $matches[8][$count] );
                }
                if ( ! empty( $fontreplace ) ) {
                    $code = str_replace( array_keys( $fontreplace ), array_values( $fontreplace ), $code );
                }
            }
        }

        return $code;
    }

    // Caches the CSS in uncompressed, deflated and gzipped form
    public function cache()
    {
        if ( $this->datauris ) {
            // MHTML Preparation
            $this->mhtml = "/*\r\nContent-Type: multipart/related; boundary=\"_\"\r\n\r\n" . $this->mhtml . "*/\r\n";
            $md5 = md5( $this->mhtml );
            $cache = new autoptimizeCache($md5, 'txt');
            if(!$cache->check()) {
                // Cache our images for IE
                $cache->cache($this->mhtml, 'text/plain');
            }
            $mhtml = AUTOPTIMIZE_CACHE_URL . $cache->getname();
        }

        // CSS cache
        foreach ( $this->csscode as $media => $code ) {
            $md5 = $this->hashmap[md5( $code )];

            if ( $this->datauris ) {
                // Images for ie! Get the right url
                $code = str_replace( '%%MHTML%%', $mhtml, $code );
            }

            $cache = new autoptimizeCache($md5, 'css');
            if( ! $cache->check() ) {
                // Cache our code
                $cache->cache($code, 'text/css');
            }
            $this->url[$media] = AUTOPTIMIZE_CACHE_URL . $cache->getname();
        }
    }

    // Returns the content
    public function getcontent()
    {
        // restore IE hacks
        $this->content = $this->restore_iehacks($this->content);

        // restore comments
        $this->content = $this->restore_comments($this->content);

        // restore (no)script
        if ( strpos( $this->content, '%%SCRIPT%%' ) !== false ) {
            $this->content = preg_replace_callback(
                '#%%SCRIPT%%(.*?)%%SCRIPT%%#is',
                create_function(
                    '$matches',
                    'return base64_decode($matches[1]);'
                ),
                $this->content
            );
        }

        // Restore noptimize
        $this->content = $this->restore_noptimize($this->content);

        // Restore the full content
        if ( ! empty( $this->restofcontent ) ) {
            $this->content .= $this->restofcontent;
            $this->restofcontent = '';
        }

        // Inject the new stylesheets
        $replaceTag = array( '<title', 'before' );
        $replaceTag = apply_filters( 'autoptimize_filter_css_replacetag', $replaceTag );

        if ( $this->inline ) {
            foreach ( $this->csscode as $media => $code ) {
                $this->inject_in_html('<style type="text/css" media="' . $media . '">' . $code . '</style>', $replaceTag);
            }
        } else {
            if ( $this->defer ) {
                $deferredCssBlock = "<script>function lCss(url,media) {var d=document;var l=d.createElement('link');l.rel='stylesheet';l.type='text/css';l.href=url;l.media=media;aoin=d.getElementsByTagName('noscript')[0];aoin.parentNode.insertBefore(l,aoin.nextSibling);}function deferredCSS() {";
                $noScriptCssBlock = '<noscript>';

                $defer_inline_code = $this->defer_inline;
                $defer_inline_code = apply_filters( 'autoptimize_filter_css_defer_inline', $defer_inline_code );

                if ( ! empty( $defer_inline_code ) ){

                    $iCssHash = md5( $defer_inline_code );
                    $iCssCache = new autoptimizeCache($iCssHash, 'css');
                    if ( $iCssCache->check() ) {
                        // we have the optimized inline CSS in cache
                        $defer_inline_code = $iCssCache->retrieve();
                    } else {
                        if ( class_exists( 'Minify_CSS_Compressor' ) ) {
                            $tmp_code = trim( Minify_CSS_Compressor::process($this->defer_inline) );
                        } elseif ( class_exists( 'CSSmin' ) ) {
                            $cssmin = new CSSmin();
                            $tmp_code = trim( $cssmin->run($defer_inline_code) );
                        }

                        if ( ! empty( $tmp_code ) ) {
                            $defer_inline_code = $tmp_code;
                            $iCssCache->cache($defer_inline_code, 'text/css');
                            unset( $tmp_code );
                        }
                    }
                    $code_out = '<style type="text/css" id="aoatfcss" media="all">' . $defer_inline_code . '</style>';
                    $this->inject_in_html($code_out, $replaceTag);
                }
            }

            foreach ( $this->url as $media => $url ) {
                $url = $this->url_replace_cdn($url);

                // Add the stylesheet either deferred (import at bottom) or normal links in head
                if ( $this->defer ) {
                    $deferredCssBlock .= "lCss('" . $url . "','" . $media . "');";
                    $noScriptCssBlock .= '<link type="text/css" media="' . $media . '" href="' . $url . '" rel="stylesheet" />';
                } else {
                    // $this->inject_in_html('<link type="text/css" media="' . $media . '" href="' . $url . '" rel="stylesheet" />', $replaceTag);
                    if ( strlen( $this->csscode[$media] ) > $this->cssinlinesize ) {
                        $this->inject_in_html('<link type="text/css" media="' . $media . '" href="' . $url . '" rel="stylesheet" />', $replaceTag);
                    } else if ( strlen( $this->csscode[$media] ) > 0 ) {
                        $this->inject_in_html('<style type="text/css" media="' . $media . '">' . $this->csscode[$media] . '</style>', $replaceTag);
                    }
                }
            }

            if ( $this->defer ) {
                $deferredCssBlock .= "document.getElementById('aoatfcss').media='none';}if(window.addEventListener){window.addEventListener('DOMContentLoaded',deferredCSS,false);}else{window.onload = deferredCSS;}</script>";
                $noScriptCssBlock .= '</noscript>';
                $this->inject_in_html($noScriptCssBlock, array( '<title>', 'before' ) );
                $this->inject_in_html($deferredCssBlock, array( '</body>', 'before' ) );
            }
        }

        // Return the modified stylesheet
        return $this->content;
    }

    static function fixurls($file, $code)
    {
        // Quick fix for import-troubles in e.g. arras theme
        $code = preg_replace( '#@import ("|\')(.+?)\.css("|\')#', '@import url("${2}.css")', $code );

        // Loosened the regex to fix certain edge cases (spaces around `url`)
        if ( preg_match_all( self::ASSETS_REGEX, $code, $matches ) ) {
            $file = str_replace( WP_ROOT_DIR, '/', $file );
            $dir  = dirname( $file ); // Like /wp-content

            // $dir should not contain backslashes, since it's used to replace
            // urls, but it can contain them when running on Windows because
            // fixurls() is sometimes called with `ABSPATH . 'index.php'`
            $dir = str_replace( '\\', '/', $dir );
            unset( $file ); // not used below at all

            $replace = array();
            foreach ( $matches[1] as $k => $url ) {
                // Remove quotes
                $url    = trim( $url," \t\n\r\0\x0B\"'" );
                $noQurl = trim( $url, "\"'" );
                if ( $url !== $noQurl ) {
                    $removedQuotes = true;
                } else {
                    $removedQuotes = false;
                }
                $url = $noQurl;
                if ( '/' === $url{0} || preg_match( '#^(https?://|ftp://|data:)#i', $url ) ) {
                    // URL is protocol-relative, host-relative or something we don't touch
                    continue;
                } else {
                    // Relative URL
                    $newurl = preg_replace( '/https?:/', '', str_replace( ' ', '%20', AUTOPTIMIZE_WP_ROOT_URL . str_replace( '//', '/', $dir . '/' . $url ) ) );

                    $hash = md5( $url );
                    $code = str_replace( $matches[0][$k], $hash, $code );

                    if ( $removedQuotes ) {
                        $replace[$hash] = "url('" . $newurl . "')";
                    } else {
                        $replace[$hash] = 'url(' . $newurl . ')';
                    }
                }
            }

            // Replace URLs found within $code
            $code = str_replace( array_keys( $replace ), array_values( $replace ), $code );
        }

        return $code;
    }

    private function ismovable($tag)
    {
		if ( ! empty( $this->whitelist ) ) {
			foreach ( $this->whitelist as $match) {
				if ( false !== strpos( $tag, $match ) ) {
					return true;
				}
			}
			// no match with whitelist
			return false;
		} else {
			if ( is_array( $this->dontmove ) ) {
				foreach ( $this->dontmove as $match ) {
					if (false !== strpos( $tag, $match ) ) {
						//Matched something
						return false;
					}
				}
			}

			//If we're here it's safe to move
			return true;
		}
	}

    private function can_inject_late($cssPath, $css)
    {
        if ( false === strpos( $cssPath, 'min.css' ) || ( true !== $this->inject_min_late ) ) {
            // late-inject turned off or file not minified based on filename
            return false;
        } else if ( false !== strpos( $css, '@import' ) ) {
            // can't late-inject files with imports as those need to be aggregated
            return false;
        } else if ( ( false !== strpos( $css, '@font-face') ) && ( apply_filters( 'autoptimize_filter_css_fonts_cdn', false ) === true) && ( ! empty( $this->cdn_url ) ) ) {
            // don't late-inject CSS with font-src's if fonts are set to be CDN'ed
            return false;
        } else if ( ( ( $this->datauris == true ) || ( ! empty( $this->cdn_url ) ) ) && preg_match( '#(background[^;}]*url\(#Ui', $css ) ) {
            // don't late-inject CSS with images if CDN is set OR is image inlining is on
            return false;
        } else {
            // phew, all is safe, we can late-inject
            return true;
        }
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function replaceOptions($options)
    {
        $this->options = $options;
    }

    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
        $this->$name = $value;
    }

    public function getOption($name)
    {
        return $this->options[$name];
    }
}
