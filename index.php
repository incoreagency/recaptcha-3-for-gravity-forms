<?php
/*
Plugin Name: Gravity Forms Google Captcha V3
Plugin URI:
Description:
Version: 0.1
Author:
Author URI:
*/

define( 'GF_GOOGLE_CAPTCHA_ADDON_VERSION', '2.1' );

add_action( 'gform_loaded', array( 'GF_Google_Captcha_AddOn_Bootstrap', 'load' ), 5 );

class GF_Google_Captcha_AddOn_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'class-gfgooglecaptchaaddon.php' );

        GFAddOn::register( 'GFGoogleCaptchaAddOn' );
    }

}

function gf_google_captcha_addon() {
    return GFGoogleCaptchaAddOn::get_instance();
}