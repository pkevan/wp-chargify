<?php
/**
 * Plugin Name:     WP Chargify
 * Plugin URI:      https://thecode.co/
 * Description:     A plugin to add Chargify payments to your WordPress site.
 * Author:          The Code Company
 * Author URI:      https://thecode.co/
 * Text Domain:     wp-chargify
 * Domain Path:     /languages
 * Version:         0.1.0
 * Requires PHP:    7.0
 *
 * @package         Wp_Chargify
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname(__FILE__) . '/includes/commands/class-chargify-product-families.php';
	require_once dirname(__FILE__) . '/includes/commands/class-chargify-products.php';
}

// Load Composer’s autoloader for CMB2
if ( ! class_exists( 'CMB2_Bootstrap_270' ) ) {
	require_once( dirname( __FILE__ ) . '/vendor/autoload.php' );
}
require_once( 'includes/bootstrap.php' );
require_once( 'includes/admin/settings.php' );
require_once( 'includes/chargify/endpoints/product-families.php' );
require_once( 'includes/helpers/options.php' );
require_once( 'includes/meta-boxes/chargify-product.php' );
require_once( 'includes/post-types/chargify-product.php' );
