<?php
/**
 * Thin wrapper around css minifiers to avoid rewriting a bunch of existing code.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class autoptimizeCSSmin
{
    protected $minifier = null;

    /**
     * @param bool $raise_limits
     */
    public function __construct( $raise_limits = true )
    {
        $this->minifier = new Autoptimize\tubalmartin\CssMin\Minifier( $raise_limits );
    }

    /**
     * @param string $css CSS to minify
     * @return string
     */
    public function run( $css )
    {
        $result = $this->minifier->run( $css );

        return $result;
    }

    /**
     * Static helper.
     *
     * @param string $css CSS to minify
     * @return string
     */
    public static function minify( $css )
    {
        $minifier = new self();

        return $minifier->run( $css );
    }
}
