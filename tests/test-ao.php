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

    const TEST_MARKUP = <<<MARKUP
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

    const TEST_MARKUP_OUTPUT = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/css/autoptimize_d667caf7140c2935d18eb478db07525f.css" rel="stylesheet" /><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

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

<script type="text/javascript" defer src="http://cdn.example.org/wp-content/cache/autoptimize/js/autoptimize_47db61d1b156c88b4952aee1229599cc.js"></script></body>
</html>
MARKUP;

    // When `is_multisite()` returns true, default path to files is different
    const TEST_MARKUP_OUTPUT_MS = <<<MARKUP
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8 lt-ie7"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 7]> <html class="no-svg no-js lt-ie9 lt-ie8"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if IE 8]> <html class="no-svg no-js lt-ie9"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-svg no-js"  xmlns:fb="https://www.facebook.com/2008/fbml"  xmlns:og="http://ogp.me/ns#" lang="hr"> <!--<![endif]-->
<head>
<meta charset="utf-8">
<link type="text/css" media="all" href="http://cdn.example.org/wp-content/cache/autoptimize/1/css/autoptimize_d667caf7140c2935d18eb478db07525f.css" rel="stylesheet" /><title>Mliječna juha od brokule &#9832; Kuhaj.hr</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

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

<script type="text/javascript" defer src="http://cdn.example.org/wp-content/cache/autoptimize/1/js/autoptimize_47db61d1b156c88b4952aee1229599cc.js"></script></body>
</html>
MARKUP;

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
        return array(

            array(
                // input
                self::TEST_MARKUP,
                // expected output
                // TODO/FIXME: this seemed like the fastest way to get MS crude test to pass
                ( is_multisite() ? self::TEST_MARKUP_OUTPUT_MS : self::TEST_MARKUP_OUTPUT )
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
            // application/ld+json should not be aggregated by default regardless of spacing around attr/values
            array(
                '<script type = "application/ld+json" >{   "@context": "" }',
                false
            ),
            array(
                '<script type="application/ld+json">{   "@context": "" }',
                false
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
            ),
            array(
                '<html dir="ltr" amp>',
                true
            ),
            array(
                '<html dir="ltr" ⚡>',
                true
            ),
            array(
                '<html amp dir="ltr">',
                true
            ),
            array(
                '<html ⚡ dir="ltr">',
                true
            ),
            array(
                '<HTML ⚡ DIR="LTR">',
                true
            ),
            array(
                '<HTML AMP DIR="LTR">',
                true
            ),
            // https://github.com/futtta/autoptimize/commit/54385939db06f725fcafe68598cce6ed148ef6c1
            array(
                '<!doctype html>',
                false
            ),
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

    /**
     * Test various conditions that can/should prevent autoptimize from buffering content.
     */

// This is causing testability issues due to the use of the constant, which,
// once defined, interferes with other tests because `autoptimize_do_buffering()` is
// checking it again and again... And we cannot test stuff in isolation any more.
/*
    public function test_skips_buffering_with_DONOTMINIFY_constant_defined()
    {
        define( 'DONOTMINIFY', true );

        // buffering should not run due to te constant above being defined
        $expected = false;
        $actual = autoptimize_do_buffering($doing_tests = true);

        $this->assertEquals($expected, $actual);
    }
//*/
    public function test_skips_buffering_when_ao_noptimize_filter_is_true()
    {
        // true => disable autoptimize
        add_filter( 'autoptimize_filter_noptimize', '__return_true' );

        // buffering should not run due to the above filter
        $expected = false;
        $actual = autoptimize_do_buffering($doing_tests = true);

        $this->assertEquals($expected, $actual);
    }

    public function test_does_buffering_when_ao_noptimize_filter_is_false()
    {
        // false => disable noptimize, aka, run normally (weird, yes...)
        add_filter( 'autoptimize_filter_noptimize', '__return_false' );

        // buffering should run because of above
        $expected = true;
        $actual = autoptimize_do_buffering($doing_tests = true);

        $this->assertEquals($expected, $actual);
    }

/*
    public function test_skips_buffering_with_ao_noptimize_qs_set()
    {
        // Simulating `ao_noptimize=1` qs by using 'autoptimize_filter_noptimize' because superglobals...
        $this->test_skips_buffering_when_ao_noptimize_filter_is_true();
    }
//*/

    public function test_ignores_ao_noptimize_qs_when_instructed()
    {
        // Should skip checking for the qs completely due to filter
        add_filter( 'autoptimize_filter_honor_qs_noptimize', '__return_false' );

        // Which should then result in the "current" value being `false` when passed to 'autoptimize_filter_noptimize'
        // unless the DONOTMINIFY constant is defined, which changes the result... Which
        // basically means this test changes its' expected result depending on the order of tests
        // execution and/or the environment, which is AAAARGGGGGGHHH...

        $that = $this; // Make it work on 5.3
        add_filter( 'autoptimize_filter_noptimize', function ($current_value) use ($that) {
            $expected = false;
            if ( defined( 'DONOTMINIFY' ) && DONOTMINIFY ) {
                $expected = true;
            }

            $that->assertEquals($expected, $current_value);
        });

        autoptimize_do_buffering($doing_tests = true);
    }

    public function test_wpengine_cache_flush()
    {
        include_once AUTOPTIMIZE_PLUGIN_DIR . 'classlesses/autoptimizePageCacheFlush.php';

        // Creating a mock so that we can get past class_exists() and method_exists() checks `autoptimize_flush_pagecache()`...
        $stub = $this->getMockBuilder('WpeCommon')->disableAutoload()
                ->disableOriginalConstructor()->setMethods(array(
                    'purge_varnish_cache'))
                ->getMock();

        $that = $this;
        add_filter( 'autoptimize_flush_wpengine_methods', function($methods) use ($that) {
            $expected_methods = array('purge_varnish_cache');
            $that->assertEquals($methods, $expected_methods);

            return $methods;
        });

        autoptimize_flush_pagecache();
    }

    // Test with the `autoptimize_flush_wpengine_aggressive` filter
    public function test_wpengine_cache_flush_agressive()
    {
        include_once AUTOPTIMIZE_PLUGIN_DIR . 'classlesses/autoptimizePageCacheFlush.php';

        // Creating a mock so that we can get past class_exists() and method_exists() checks `autoptimize_flush_pagecache()`...
        $stub = $this->getMockBuilder('WpeCommon')->disableAutoload()
                ->disableOriginalConstructor()->setMethods(array(
                    'purge_varnish_cache',
                    'purge_memcached',
                    'clear_maxcdn_cache'))
                ->getMock();

        add_filter( 'autoptimize_flush_wpengine_aggressive', function(){
            return true;
        });

        $that = $this;
        add_filter( 'autoptimize_flush_wpengine_methods', function($methods) use ($that) {
            $expected_methods = array(
                'purge_varnish_cache',
                'purge_memcached',
                'clear_maxcdn_cache'
            );

            $that->assertEquals($methods, $expected_methods);

            return $methods;
        });

        autoptimize_flush_pagecache();
    }

    /**
     * @dataProvider provider_test_url_replace_cdn
     * @covers autoptimizeBase::url_replace_cdn
     */
    public function test_url_replace_cdn($cdn_url, $input, $expected)
    {
        $mock = $this->getMockBuilder('autoptimizeBase')->disableOriginalConstructor()->getMockForAbstractClass();
        $mock->cdn_url = $cdn_url;

        $actual = $mock->url_replace_cdn($input);
        $this->assertEquals($expected, $actual);
    }

    public function provider_test_url_replace_cdn()
    {
        return array(
            // host-relative links get properly transformed
            array(
                // cdn base url, url, expected result
                'http://cdn-test.example.org',
                '/a.jpg',
                'http://cdn-test.example.org/a.jpg',
            ),
            // full link with a matching AUTOPTIMIZE_WP_SITE_URL gets properly replaced
            array(
                'http://cdn-test.example.org',
                'http://example.org/wp-content/themes/something/example.svg',
                'http://cdn-test.example.org/wp-content/themes/something/example.svg'
            ),
            // www.example.org does not match example.org (AUTOPTIMIZE_WP_SITE_URL) so it's left alone
            array(
                'http://cdn-test.example.org',
                'http://www.example.org/wp-content/themes/something/example.svg',
                'http://www.example.org/wp-content/themes/something/example.svg'
            ),
            // ssl cdn url + host-relative link
            array(
                'https://cdn.example.org',
                '/a.jpg',
                'https://cdn.example.org/a.jpg'
            ),
            // ssl cdn url + http site url that matches AUTOPTIMIZE_WP_SITE_URL is properly replaced
            array(
                'https://cdn.example.org',
                'http://example.org/wp-content/themes/something/example.svg',
                'https://cdn.example.org/wp-content/themes/something/example.svg'
            ),
            // protocol-relative cdn url given with protocol relative link that matches AUTOPTIMIZE_WP_SITE_URL host
            array(
                '//cdn.example.org',
                '//example.org/something.jpg',
                '//cdn.example.org/something.jpg'
            ),
            // protocol-relative cdn url given a http link that matches AUTOPTIMIZE_WP_SITE_URL host
            array(
                '//cdn.example.org',
                'http://example.org/something.png',
                '//cdn.example.org/something.png',
            ),
            // protocol-relative cdn url with a host-relative link
            array(
                '//cdn.example.org',
                '/a.jpg',
                '//cdn.example.org/a.jpg',
            ),
            // Testing cdn urls with an explicit port number
            array(
                'http://cdn.com:8080',
                '/a.jpg',
                'http://cdn.com:8080/a.jpg'
            ),
            array(
                '//cdn.com:4433',
                '/a.jpg',
                '//cdn.com:4433/a.jpg'
            ),
            array(
                '//cdn.com:4433',
                'http://example.org/something.jpg',
                '//cdn.com:4433/something.jpg'
            ),
            array(
                '//cdn.com:1234',
                '//example.org/something.jpg',
                '//cdn.com:1234/something.jpg'
            )
        );
    }

    // test `autoptimize_filter_base_cdnurl` filtering as described here: https://wordpress.org/support/topic/disable-cdn-of-ssl-pages
    public function test_autoptimize_filter_base_cdnurl()
    {
        $test_link = '/a.jpg';
        $cdn_url = '//cdn.example.org';

        $with_ssl = function($cdn) {
            return '';
        };
        $expected_with_ssl = '/a.jpg';

        $without_ssl = function($cdn) {
            return $cdn;
        };
        $expected_without_ssl = '//cdn.example.org/a.jpg';

        // with a filter that returns something considered "empty", cdn replacement shouldn't occur
        add_filter( 'autoptimize_filter_base_cdnurl', $with_ssl );
        $mock = $this->getMockBuilder('autoptimizeBase')->disableOriginalConstructor()->getMockForAbstractClass();
        $mock->cdn_url = $cdn_url;
        $actual_with_ssl = $mock->url_replace_cdn($test_link);
        $this->assertEquals($expected_with_ssl, $actual_with_ssl);
        remove_filter( 'autoptimize_filter_base_cdnurl', $with_ssl );

        // with a filter that returns an actual cdn url, cdn replacement should occur
        add_filter( 'autoptimize_filter_base_cdnurl', $without_ssl );
        $actual_without_ssl = $mock->url_replace_cdn($test_link);
        $this->assertEquals($expected_without_ssl, $actual_without_ssl);
    }

    public function provider_cssmin_issues()
    {
        return array(
            // https://wordpress.org/support/topic/css-minify-breaks-calc-subtract-operation-in-css/?replies=2#post-6610027
            array(
                // input
                'width: calc(33.33333% - ((0.75em*2)/3));',
                // expected output
                'width:calc(33.33333% - ((0.75em*2)/3));'
            ),
            // https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/issues/22#issuecomment-251401341
            array(
                'input { width: calc(100% - (1em*1.5) - 2em); }',
                'input{width:calc(100% - (1em*1.5) - 2em)}'
            ),
            // https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port/issues/26
            array(
                '.px { flex: 1 1 0px; }, .percent {flex: 1 1 0%}',
                '.px{flex:1 1 0px},.percent{flex:1 1 0%}'
            )
        );
    }

    /**
     * @dataProvider provider_cssmin_issues
     * @covers CSSmin::replace_calc
     */
    public function test_cssmin_issues($input, $expected)
    {
        $minifier = new CSSmin(false); // no need to raise limits for now

        $actual = $minifier->run($input);
        $this->assertEquals($expected, $actual);
    }

    public function provider_getpath()
    {
        return array(
            // These all don't really exist, and getpath() returns
            // false for non-existing files since upstream's 1386e4fe1d commit
            array(
                'img/something.svg',
                false
            ),
            array(
                '../../somewhere-else/svg.svg',
                false
            ),
            array(
                '//something/somewhere/example.png',
                false
            ),
            // This file comes with core, so should exist...
            array(
                '/wp-includes/js/jquery/jquery.js',
                WP_ROOT_DIR . '/wp-includes/js/jquery/jquery.js'
            ),
            // Empty $url should return false
            array(
                '',
                false
            ),
            array(
                false,
                false
            ),
            array(
                null,
                false
            ),
            array(
                0,
                false
            )
        );
    }

    /**
     * @dataProvider provider_getpath
     * @covers autoptimizeBase::getpath
     */
    public function test_getpath($input, $expected)
    {
        $mock = $this->getMockBuilder('autoptimizeBase')->disableOriginalConstructor()->getMockForAbstractClass();

        $actual = $mock->getpath($input);
        $this->assertEquals($expected, $actual);
    }
}
