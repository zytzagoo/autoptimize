<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class autoptimizeHTML extends autoptimizeBase
{
    private $keepcomments = false;

    //Does nothing
    public function read($options)
    {
        $noptimizeHTML = apply_filters( 'autoptimize_filter_html_noptimize', false, $this->content );
        if ( $noptimizeHTML ) {
            return false;
        }

        //Remove the HTML comments?
        $this->keepcomments = (bool) $options['keepcomments'];

        // filter to force xhtml
        $this->forcexhtml = apply_filters( 'autoptimize_filter_html_forcexhtml', 'false' );

        //Nothing to read for HTML
        return true;
    }

    //Joins and optimizes CSS
    public function minify()
    {
        if ( class_exists( 'Minify_HTML' ) ) {
            // noptimize me
            $this->content = $this->hide_noptimize($this->content);

            // Minify html
            $options = array( 'keepComments' => $this->keepcomments );
            if ( $this->forcexhtml ) {
                $options['xhtml'] = true;
            }

            if ( is_callable( array( 'Minify_HTML', 'minify' ) ) ) {
                $tmp_content = Minify_HTML::minify($this->content, $options);
                if ( ! empty( $tmp_content ) ) {
                    $this->content = $tmp_content;
                    unset( $tmp_content );
                }
            }

            // revslider data attribs somehow suffer from HTML optimization, this fixes that
            if ( class_exists( 'RevSlider' ) || apply_filters( 'autoptimize_filter_html_dataattrib_cleanup', false ) ) {
                $this->content = preg_replace( '#\n(data-.*$)\n#Um', ' $1 ', $this->content );
                $this->content = preg_replace( '#(=\"[^"]*\")(\w)#', '$1 $2', $this->content );
            }

            // restore noptimize
            $this->content = $this->restore_noptimize($this->content);
            return true;
        }

        //Didn't minify :(
        return false;
    }

    //Does nothing
    public function cache()
    {
        //No cache for HTML
        return true;
    }

    //Returns the content
    public function getcontent()
    {
        return $this->content;
    }
}
