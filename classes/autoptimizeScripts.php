<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeScripts extends autoptimizeBase
{
    private $scripts = array();
    private $move    = array(
        'first' => array(),
        'last' => array()
    );

    private $dontmove = array(
        'document.write','html5.js','show_ads.js','google_ad','blogcatalog.com/w',
        'tweetmeme.com/i','mybloglog.com/','histats.com/js','ads.smowtion.com/ad.js',
        'statcounter.com/counter/counter.js','widgets.amung.us','ws.amazon.com/widgets',
        'media.fastclick.net','/ads/','comment-form-quicktags/quicktags.php','edToolbar',
        'intensedebate.com','scripts.chitika.net/','_gaq.push','jotform.com/',
        'admin-bar.min.js','GoogleAnalyticsObject','plupload.full.min.js',
        'syntaxhighlighter','adsbygoogle','gist.github.com','_stq','nonce',
        'post_id', 'data-noptimize'
    );
    private $domove     = array(
        'gaJsHost','load_cmc','jd.gallery.transitions.js','swfobject.embedSWF(','tiny_mce.js','tinyMCEPreInit.go'
    );
    private $domovelast = array(
        'addthis.com','/afsonline/show_afs_search.js','disqus.js','networkedblogs.com/getnetworkwidget','infolinks.com/js/',
        'jd.gallery.js.php','jd.gallery.transitions.js','swfobject.embedSWF(','linkwithin.com/widget.js','tiny_mce.js','tinyMCEPreInit.go'
    );

    private $trycatch        = false;
    private $alreadyminified = false;
    private $forcehead       = true;
    private $include_inline  = false;
    private $jscode          = '';
    private $url             = '';
    private $restofcontent   = '';
    private $md5hash         = '';
    private $whitelist       = '';
    private $jsremovables    = array();
    private $inject_min_late = '';

    // Reads the page and collects script tags
    public function read($options)
    {
        $noptimizeJS = apply_filters( 'autoptimize_filter_js_noptimize', false, $this->content );
        if ( $noptimizeJS ) {
            return false;
        }

        // only optimize known good JS?
        $whitelistJS = apply_filters( 'autoptimize_filter_js_whitelist', '' );
        if ( ! empty( $whitelistJS ) ) {
            $this->whitelist = array_filter( array_map( 'trim', explode( ',', $whitelistJS ) ) );
        }

        // is there JS we should simply remove
        $removableJS = apply_filters( 'autoptimize_filter_js_removables', '');
        if (!empty($removableJS)) {
            $this->jsremovables = array_filter( array_map( 'trim', explode( ',', $removableJS ) ) );
        }

        // only header?
        if ( apply_filters( 'autoptimize_filter_js_justhead', $options['justhead'] ) ) {
            $content             = explode( '</head>', $this->content, 2 );
            $this->content       = $content[0] . '</head>';
            $this->restofcontent = $content[1];
        }

        // include inline?
        if ( apply_filters( 'autoptimize_js_include_inline', $options['include_inline'] ) ) {
            $this->include_inline = true;
        }

        // filter to "late inject minified JS", default to true for now (it is faster)
        $this->inject_min_late = apply_filters( 'autoptimize_filter_js_inject_min_late', true );

        // filters to override hardcoded do(nt)move(last) array contents (array in, array out!)
        $this->dontmove = apply_filters( 'autoptimize_filter_js_dontmove', $this->dontmove );
        $this->domovelast = apply_filters( 'autoptimize_filter_js_movelast', $this->domovelast );
        $this->domove = apply_filters( 'autoptimize_filter_js_domove', $this->domove );

        // get extra exclusions settings or filter
        $excludeJS = $options['js_exclude'];
        $excludeJS = apply_filters( 'autoptimize_filter_js_exclude', $excludeJS );

        if ( '' !== $excludeJS ) {
            $exclJSArr      = array_filter( array_map( 'trim', explode( ',', $excludeJS ) ) );
            $this->dontmove = array_merge( $exclJSArr, $this->dontmove );
        }

        // Should we add try-catch?
        if ( $options['trycatch'] ) {
            $this->trycatch = true;
        }

        // force js in head?
        if ( $options['forcehead'] ) {
            $this->forcehead = true;
        } else {
            $this->forcehead = false;
        }

        $this->forcehead = apply_filters( 'autoptimize_filter_js_forcehead', $this->forcehead );

        // get cdn url
        $this->cdn_url = $options['cdn_url'];

        // noptimize me
        $this->content = $this->hide_noptimize($this->content);

        // Save IE hacks
        $this->content = $this->hide_iehacks($this->content);

        // comments
        $this->content = $this->hide_comments($this->content);

        // Get script files
        if ( preg_match_all( '#<script.*</script>#Usmi', $this->content, $matches ) ) {
            foreach( $matches[0] as $tag ) {
                // Do aggregation only when no js type is specified or when type is text/javascript
                $should_aggregate = $this->should_aggregate($tag);
                if ( ! $should_aggregate ) {
                    $tag = '';
                    continue;
                }

                if ( preg_match( '#src=("|\')(.*)("|\')#Usmi', $tag, $source ) ) {
                    if ( $this->isremovable($tag, $this->jsremovables) ) {
                        $this->content = str_replace( $tag, '', $this->content );
                        continue;
                    }

                    // External script
                    $url = current( explode( '?', $source[2], 2 ) );
                    $path = $this->getpath($url);
                    if ( false !== $path && preg_match( '#\.js$#', $path ) ) {
                        // Local
                        if ( $this->ismergeable($tag) ) {
                            // We can merge it
                            $this->scripts[] = $path;
                        } else {
                            // No merge, but maybe we can move it
                            if ( $this->ismovable($tag) ) {
                                // Yeah, move it
                                if ( $this->movetolast($tag) ) {
                                    $this->move['last'][] = $tag;
                                } else {
                                    $this->move['first'][] = $tag;
                                }
                            } else {
                                // We shouldn't touch this
                                $tag = '';
                            }
                        }
                    } else {
                        // External script (example: google analytics)
                        // OR Script is dynamic (.php etc)
                        if ( $this->ismovable($tag) ) {
                            if ( $this->movetolast($tag) )  {
                                $this->move['last'][] = $tag;
                            } else {
                                $this->move['first'][] = $tag;
                            }
                        } else {
                            // We shouldn't touch this
                            $tag = '';
                        }
                    }
                } else {
                    // Inline script
                    if ( $this->isremovable($tag, $this->jsremovables) ) {
                        $this->content = str_replace( $tag, '', $this->content );
                        continue;
                    }

                    // unhide comments, as javascript may be wrapped in comment-tags for old times' sake
                    $tag = $this->restore_comments($tag);
                    if ( $this->ismergeable($tag) && $this->include_inline ) {
                        preg_match( '#<script.*>(.*)</script>#Usmi', $tag , $code );
                        $code = preg_replace('#.*<!\[CDATA\[(?:\s*\*/)?(.*)(?://|/\*)\s*?\]\]>.*#sm', '$1', $code[1] );
                        $code = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/', '', $code );
                        $this->scripts[] = 'INLINE;' . $code;
                    } else {
                        // Can we move this?
                        if ( $this->ismovable($tag) ) {
                            if ( $this->movetolast($tag) ) {
                                $this->move['last'][] = $tag;
                            } else {
                                $this->move['first'][] = $tag;
                            }
                        } else {
                            // We shouldn't touch this
                            $tag = '';
                        }
                    }
                    // Re-hide comments to be able to do the removal based on tag from $this->content
                    $tag = $this->hide_comments($tag);
                }

                //Remove the original script tag
                $this->content = str_replace( $tag, '', $this->content );
            }

            return true;
        }

        // No script files, great ;-)
        return false;
    }

    /**
     * Determines wheter a certain `<script>` $tag should be aggregated or not.
     *
     * We consider these as "aggregation-safe" currently:
     * - script tags without a `type` attribute
     * - script tags with these `type` attribute values: `text/javascript`, `text/ecmascript`, `application/javascript`,
     * and `application/ecmascript`
     *
     * Everything else should return false.
     *
     * @link https://developer.mozilla.org/en/docs/Web/HTML/Element/script#attr-type
     *
     * @param string $tag
     * @return bool
     */
    public function should_aggregate($tag)
    {
        // We're only interested in the type attribute of the <script> tag itself, not any possible
        // inline code that might just contain the 'type=' string...
        $tag_parts = array();
        preg_match( '#<(script[^>]*)>#i', $tag, $tag_parts);
        $tag_without_contents = null;
        if ( ! empty( $tag_parts[1] ) ) {
            $tag_without_contents = $tag_parts[1];
        }

        $has_type = ( strpos( $tag_without_contents, 'type=' ) !== false );

        $type_valid = false;
        if ( $has_type ) {
            $type_valid = (bool) preg_match( '/type\s*=\s*[\'"]?(?:text|application)\/(?:javascript|ecmascript)[\'"]?/i', $tag_without_contents );
        }

        $should_aggregate = false;
        if ( ! $has_type || $type_valid ) {
            $should_aggregate = true;
        }

        return $should_aggregate;
    }

    //Joins and optimizes JS
    public function minify()
    {
        foreach ( $this->scripts as $script ) {
            // TODO/FIXME: some duplicate code here, can be reduced/simplified
            if ( preg_match( '#^INLINE;#', $script ) ) {
                // Inline script
                $script = preg_replace( '#^INLINE;#', '', $script );
                $script = rtrim( $script, ";\n\t\r" ) . ';';
                // Add try-catch?
                if ( $this->trycatch ) {
                    $script = 'try{' . $script . '}catch(e){}';
                }
                $tmpscript = apply_filters( 'autoptimize_js_individual_script', $script, '' );
                if ( has_filter( 'autoptimize_js_individual_script' ) && ! empty( $tmpscript ) ) {
                    $script = $tmpscript;
                    $this->alreadyminified = true;
                }
                $this->jscode .= "\n" . $script;
            } else {
                // External script
                if ( false !== $script && file_exists( $script ) && is_readable( $script ) ) {
                    $scriptsrc = file_get_contents( $script );
                    $scriptsrc = preg_replace( '/\x{EF}\x{BB}\x{BF}/', '', $scriptsrc );
                    $scriptsrc = rtrim( $scriptsrc, ";\n\t\r" ) . ';';
                    // Add try-catch?
                    if ( $this->trycatch ) {
                        $scriptsrc = 'try{' . $scriptsrc . '}catch(e){}';
                    }
                    $tmpscriptsrc = apply_filters( 'autoptimize_js_individual_script', $scriptsrc, $script );
                    if ( has_filter( 'autoptimize_js_individual_script' ) && ! empty( $tmpscriptsrc ) ) {
                        $scriptsrc = $tmpscriptsrc;
                        $this->alreadyminified = true;
                    } else if ( ( false !== strpos( $script, 'min.js' ) ) && ( true === $this->inject_min_late ) ) {
                        $scriptsrc = '%%INJECTLATER%%' . base64_encode( $script ) . '|' . md5( $scriptsrc ) . '%%INJECTLATER%%';
                    }
                    $this->jscode .= "\n" . $scriptsrc;
                }/*else{
                    //Couldn't read JS. Maybe getpath isn't working?
                }*/
            }
        }

        // Check for already-minified code
        $this->md5hash = md5( $this->jscode );
        $ccheck = new autoptimizeCache($this->md5hash, 'js');
        if ( $ccheck->check() ) {
            $this->jscode = $ccheck->retrieve();
            return true;
        }
        unset( $ccheck );

        // $this->jscode has all the uncompressed code now.
        if ( true !== $this->alreadyminified ) {
            if ( class_exists( 'JSMin' ) && apply_filters( 'autoptimize_js_do_minify', true ) ) {
                if ( @is_callable( array( 'JSMin', 'minify' ) ) ) {
                    $tmp_jscode = trim( JSMin::minify($this->jscode) );
                    if ( ! empty( $tmp_jscode ) ) {
                        $this->jscode = $tmp_jscode;
                        unset( $tmp_jscode );
                    }
                    $this->jscode = $this->inject_minified($this->jscode);
                    $this->jscode = apply_filters( 'autoptimize_js_after_minify', $this->jscode );
                    return true;
                } else {
                    $this->jscode = $this->inject_minified($this->jscode);
                    return false;
                }
            } else {
                $this->jscode = $this->inject_minified($this->jscode);
                return false;
            }
        }

        return true;
    }

    // Caches the JS in uncompressed, deflated and gzipped form.
    public function cache()
    {
        $cache = new autoptimizeCache($this->md5hash, 'js');
        if ( ! $cache->check() ) {
            // Cache our code
            $cache->cache($this->jscode, 'text/javascript');
        }
        $this->url = AUTOPTIMIZE_CACHE_URL . $cache->getname();
        $this->url = $this->url_replace_cdn($this->url);
    }

    // Returns the content
    public function getcontent()
    {
        // Restore the full content
        if ( ! empty( $this->restofcontent ) ) {
            $this->content .= $this->restofcontent;
            $this->restofcontent = '';
        }

        // Add the scripts taking forcehead/ deferred (default) into account
        if ( $this->forcehead ) {
            $replaceTag = array( '</head>', 'before' );
            $defer = '';
        } else {
            $replaceTag = array( '</body>', 'before' );
            $defer = 'defer ';
        }

        $defer = apply_filters( 'autoptimize_filter_js_defer', $defer );

        $bodyreplacementpayload = '<script type="text/javascript" ' . $defer . 'src="' . $this->url . '"></script>';
        $bodyreplacementpayload = apply_filters( 'autoptimize_filter_js_bodyreplacementpayload', $bodyreplacementpayload );

        $bodyreplacement = implode( '',$this->move['first'] );
        $bodyreplacement .= $bodyreplacementpayload;
        $bodyreplacement .= implode( '', $this->move['last'] );

        $replaceTag = apply_filters( 'autoptimize_filter_js_replacetag', $replaceTag );

        if ( strlen( $this->jscode ) > 0 ) {
            $this->inject_in_html($bodyreplacement, $replaceTag);
        }

        // restore comments
        $this->content = $this->restore_comments($this->content);

        // Restore IE hacks
        $this->content = $this->restore_iehacks($this->content);

        // Restore noptimize
        $this->content = $this->restore_noptimize($this->content);

        // Return the modified HTML
        return $this->content;
    }

    // Checks against the white- and blacklists
	private function ismergeable($tag)
    {
		if ( ! empty( $this->whitelist ) ) {
			foreach ( $this->whitelist as $match ) {
				if (false !== strpos( $tag, $match ) ) {
					return true;
				}
			}
			// no match with whitelist
			return false;
		} else {
			foreach($this->domove as $match) {
				if ( false !== strpos( $tag, $match ) )	{
					// Matched something
					return false;
				}
			}

			if ( $this->movetolast($tag) ) {
				return false;
            }

			foreach( $this->dontmove as $match ) {
				if ( false !== strpos( $tag, $match ) )	{
					// Matched something
					return false;
				}
			}

			// If we're here it's safe to merge
			return true;
		}
	}

    // Checks agains the blacklist
    private function ismovable($tag)
    {
        if ( true !== $this->include_inline || apply_filters( 'autoptimize_filter_js_unmovable', false ) ) {
            return false;
        }

        foreach ( $this->domove as $match ) {
            if ( false !== strpos( $tag, $match ) ) {
                // Matched something
                return true;
            }
        }

        if ( $this->movetolast($tag) ) {
            return true;
        }

        foreach ( $this->dontmove as $match ) {
            if ( false !== strpos( $tag, $match ) ) {
                // Matched something
                return false;
            }
        }

        // If we're here it's safe to move
        return true;
    }

    private function movetolast($tag)
    {
        foreach ( $this->domovelast as $match ) {
            if ( false !== strpos( $tag, $match ) ) {
                // Matched, return true
                return true;
            }
        }

        // Should be in 'first'
        return false;
    }
}
