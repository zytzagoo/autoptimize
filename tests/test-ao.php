<?php

class AOTest extends WP_UnitTestcase
{
    /**
     * Normalizes EOLs into "\n" otherwise some tests fail due to simple newline
     * differences in the markup (depending on how/where it was entered/generated).
     * This can occasionally get even more complicated by git changing newlines
     * on checkout (if so configured).
     *
     * @param $str
     *
     * @return mixed
     */
    private function normalize_newlines($str)
    {
        return str_replace("\r\n", "\n", $str);
    }

    /**
     * @dataProvider provider_test_rewrite_markup_with_cdn
     */
    function test_rewrite_markup_with_cdn($input, $expected)
    {
        $actual = autoptimize_end_buffering($input);

        // $this->markTestIncomplete('Full-blown rewrite test currently doesn\'t work on Windows (or with any custom WP-tests setup/location really).');
        $this->assertEquals($expected, $actual);
    }

    public function provider_test_rewrite_markup_with_cdn()
    {
        $in = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style type="text/css">
/* cdn rewrite tests */

.bg { background:url('img/something.svg'); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url("img/something.svg"); }

.whitespaces { background : url   (  "../../somewhere-else/svg.svg" ) ; }

.host-relative { background: url("/img/something.svg"); }
.protocol-relative { background: url("//something/somewhere/example.png"); }

/* roboto-100 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
/* roboto-300 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 300;
  src: url('../fonts/roboto-v15-latin-ext_latin-300.eot'); /* IE9 Compat Modes */
  src: local('Roboto Light'), local('Roboto-Light'),
       url('../fonts/roboto-v15-latin-ext_latin-300.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-300.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-300.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-300.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-300.svg#Roboto') format('svg'); /* Legacy iOS */
}
/* roboto-regular - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url('../fonts/roboto-v15-latin-ext_latin-regular.eot'); /* IE9 Compat Modes */
  src: local('Roboto'), local('Roboto-Regular'),
       url('../fonts/roboto-v15-latin-ext_latin-regular.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-regular.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-regular.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-regular.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-regular.svg#Roboto') format('svg'); /* Legacy iOS */
}
/* roboto-500 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 500;
  src: url('../fonts/roboto-v15-latin-ext_latin-500.eot'); /* IE9 Compat Modes */
  src: local('Roboto Medium'), local('Roboto-Medium'),
       url('../fonts/roboto-v15-latin-ext_latin-500.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-500.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-500.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-500.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-500.svg#Roboto') format('svg'); /* Legacy iOS */
}
</style>
    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
</head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>

<script type='text/javascript' src='http://example.org/wp-content/plugins/ajax-load-more/core/js/ajax-load-more.min.js?ver=1.1'></script>
<script type='text/javascript' src='http://example.org/wp-content/plugins/wp-ga-social-tracking-js/ga-social-tracking.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/alm-seo.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/jquery.placeholder-2.1.1.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/typeahead.bundle.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/vendor/bootstrap-tagsinput.min.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/m-mobilemenu.js'></script>
<script type='text/javascript' src='http://example.org/wp-content/themes/my-theme/js/main.js'></script>
<script type='text/javascript' src='http://example.org/wp-includes/js/comment-reply.min.js?ver=4.1.1'></script>
</body>
</html>
MARKUP;

        $out = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/css/autoptimize_b9843156b1fe2f085fab748c6666a2a5.css" rel="stylesheet" /><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
<script type="text/javascript" src="http://cdn.example.org/wp-content/cache/autoptimize/js/autoptimize_47db61d1b156c88b4952aee1229599cc.js"></script></head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>










</body>
</html>
MARKUP;

        // When `is_multisite()` returns true, default path to files is different
        $out_ms = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/1/css/autoptimize_b9843156b1fe2f085fab748c6666a2a5.css" rel="stylesheet" /><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

    <!--[if lt IE 9]>
    <script src="http://example.org/wp-content/themes/my-theme/js/vendor/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <!--[if (gte IE 6)&(lte IE 8)]>
        <script type="text/javascript" src="http://example.org/wp-content/themes/my-theme/js/vendor/respond.min.js"></script>
    <![endif]-->
<script type="text/javascript" src="http://cdn.example.org/wp-content/cache/autoptimize/1/js/autoptimize_47db61d1b156c88b4952aee1229599cc.js"></script></head>

<body class="single single-post">

    <div id="fb-root"></div>
    <script>(function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/hr_HR/sdk.js#version=v2.0&xfbml=1&appId=";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>
    </script>










</body>
</html>
MARKUP;

        // TODO/FIXME: this seemed like the fastest way to get MS crude test to pass
        if ( is_multisite() ) {
            $out = $out_ms;
        }

        return array(

            array(
                // input
                $in,
                // expected output
                $out
            ),

        );
    }

    public function test_rewrite_css_assets()
    {
        $css_in = <<<CSS
.bg { background:url('img/something.svg'); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url("img/something.svg"); }

.whitespaces { background : url   (  "../../somewhere-else/svg.svg" ) ; }

.host-relative { background: url("/img/something.svg"); }
.protocol-relative { background: url("//something/somewhere/example.png"); }

@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url('../fonts/roboto-v15-latin-ext_latin-100.eot'); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url('../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix') format('embedded-opentype'), /* IE6-IE8 */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff2') format('woff2'), /* Super Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.woff') format('woff'), /* Modern Browsers */
       url('../fonts/roboto-v15-latin-ext_latin-100.ttf') format('truetype'), /* Safari, Android, iOS */
       url('../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto') format('svg'); /* Legacy iOS */
}
CSS;
        $css_expected = <<<CSS
.bg { background:url(img/something.svg); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url(img/something.svg); }

.whitespaces { background : url   (  ../../somewhere-else/svg.svg) ; }

.host-relative { background: url(http://cdn.example.org/img/something.svg); }
.protocol-relative { background: url(//something/somewhere/example.png); }

@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url(../fonts/roboto-v15-latin-ext_latin-100.eot); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url(../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'), /* IE6-IE8 */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'), /* Super Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'), /* Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'), /* Safari, Android, iOS */
       url(../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg'); /* Legacy iOS */
}
CSS;

        $instance = new autoptimizeStyles($css_in);
        $instance->setOption('cdn_url', 'http://cdn.example.org');

        $css_actual = $instance->rewrite_assets($css_in);

        $this->assertEquals($css_expected, $css_actual);
    }

    public function test_default_cssmin_minifier()
    {
        $css = <<<CSS
.bg { background:url('img/something.svg'); }
.bg-no-quote { background: url(img/something.svg); }
.bg-double-quotes { background: url("img/something.svg"); }

.whitespaces { background : url   (  "../../somewhere-else/svg.svg" ) ; }

.host-relative { background: url("/img/something.svg"); }
.protocol-relative { background: url("//something/somewhere/example.png"); }

/* roboto-100 - latin-ext_latin */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 100;
  src: url(../fonts/roboto-v15-latin-ext_latin-100.eot); /* IE9 Compat Modes */
  src: local('Roboto Thin'), local('Roboto-Thin'),
       url(../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'), /* IE6-IE8 */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'), /* Super Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'), /* Modern Browsers */
       url(../fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'), /* Safari, Android, iOS */
       url(../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg'); /* Legacy iOS */
}
CSS;

$expected = <<<CSS
.bg{background:url('img/something.svg')}.bg-no-quote{background:url(img/something.svg)}.bg-double-quotes{background:url("img/something.svg")}.whitespaces{background:url("../../somewhere-else/svg.svg")}.host-relative{background:url("/img/something.svg")}.protocol-relative{background:url("//something/somewhere/example.png")}@font-face{font-family:'Roboto';font-style:normal;font-weight:100;src:url(../fonts/roboto-v15-latin-ext_latin-100.eot);src:local('Roboto Thin'),local('Roboto-Thin'),url(../fonts/roboto-v15-latin-ext_latin-100.eot?#iefix) format('embedded-opentype'),url(../fonts/roboto-v15-latin-ext_latin-100.woff2) format('woff2'),url(../fonts/roboto-v15-latin-ext_latin-100.woff) format('woff'),url(../fonts/roboto-v15-latin-ext_latin-100.ttf) format('truetype'),url(../fonts/roboto-v15-latin-ext_latin-100.svg#Roboto) format('svg')}
CSS;

        $instance = new autoptimizeStyles($css);
        $minified = $instance->run_minifier_on($css);

        $this->assertEquals($expected, $minified);
    }

    /**
     * @dataProvider provider_test_should_aggregate_script_types
     * @covers autoptimizeScripts::should_aggregate
     */
    public function test_should_aggregate_script_types($input, $expected)
    {
        $instance = new autoptimizeScripts('');
        $actual = $instance->should_aggregate($input);

        $this->assertEquals($expected, $actual);
    }

    public function provider_test_should_aggregate_script_types()
    {
        return array(
            // no type attribute at all
            array(
                // input
                '<script>var something=true</script>',
                // expected output
                true
            ),
            // case-insensitive
            array(
                '<script type="text/ecmaScript">var something=true</script>',
                true
            ),
            // allowed/aggregated now (wasn't previously)
            array(
                '<script type="application/javascript">var something=true</script>',
                true
            ),
            // quotes shouldn't matter, nor should case-sensitivity
            array(
                '<script type=\'text/JaVascriPt">var something=true</script>',
                true
            ),
            // liberal to whitespace around attribute names/values
            array(
                '<script tYpe = text/javascript>var something=true</script>',
                true
            ),
            // something custom, should be ignored/skipped
            array(
                '<script type=template/javascript>var something=true</script>',
                false
            ),
            // type attribute checking should be constrained to actual script tag's type attribute
            // only, regardless of any `type=` string present in the actual inline script contents
            array(
                // since there's no type attribute, it should be aggregate by default
                '<script>var type=something;</script>',
                true
            ),
        );
    }

    /**
     * @dataProvider provider_autoptimize_should_bail_from_processing_buffer
     * @covers autoptimize_should_bail_from_processing_buffer
     */
    public function test_autoptimize_should_bail_from_processing_buffer($input, $expected)
    {
        $actual = autoptimize_should_bail_from_processing_buffer($input);

        $this->assertEquals($expected, $actual);
    }

    public function provider_autoptimize_should_bail_from_processing_buffer()
    {
        return array(
            array(
                '<!doctype html>
<html ⚡>',
                true,
            ),
            array(
                '<!doctype html>
<html amp>',
                true
            ),
            array(
                '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">',
                true
            ),
            array(
                '<!doctype html>
<html>',
                false
            )
        );
    }

    /**
     * @dataProvider provider_autoptimize_is_amp_markup
     * @covers autoptimize_is_amp_markup
     */
    public function test_autoptimize_is_amp_markup($input, $expected)
    {
        $actual = autoptimize_is_amp_markup($input);

        $this->assertEquals($expected, $actual);
    }

    public function provider_autoptimize_is_amp_markup()
    {
        return array(
            array(
                '<!doctype html>
<html ⚡>',
                true,
            ),
            array(
                '<!doctype html>
<html amp>',
                true
            ),
            array(
                '<!doctype html>
<head>
<meta charset=utf-8>',
                false
            )
        );
    }
}
