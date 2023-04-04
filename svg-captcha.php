<?php 
/**
 * Plugin Name: SVG Captcha
 * Plugin URI: https://code.recuweb.com/get/svg-captcha/
 * Version: 1.0.8
 * Description: Validate your forms with a self hosted SVG Captcha.
 * Author: rafasashi
 * Author URI: https://code.recuweb.com
 * Text Domain: svg-captcha
 * Domain Path: /lang/
 * Requires at least: 4.0
 * Requires PHP: 6.0
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires WP: 6.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'includes/class-svg-captcha.php';
require_once 'includes/class-svg-captcha-settings.php';

require_once 'includes/lib/class-svg-captcha-generator.php';
require_once 'includes/lib/class-svg-captcha-admin-api.php';
require_once 'includes/lib/class-svg-captcha-post-type.php';
require_once 'includes/lib/class-svg-captcha-taxonomy.php';


function svg_captcha() {
	
	$instance = SVG_Captcha::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = SVG_Captcha_Settings::instance( $instance );
	}

	return $instance;
}

svg_captcha();
