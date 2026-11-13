<?php
/**
 * Plugin Name:  Lookit Cloudflare Cache Purge

 * Description:  Adds a surgical single-URL Cloudflare cache purge button to the wp-admin admin bar.
 * Version:      1.0.0
 * Author:       Lookit Design
 * Author URI:   https://lookitdesign.com
 * License:      GPL-2.0+
 * Text Domain:  lookit-cf-purge
 */

defined( 'ABSPATH' ) || exit;

define( 'LOOKIT_CF_PURGE_VERSION', '1.0.0' );
define( 'LOOKIT_CF_PURGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOOKIT_CF_PURGE_URL', plugin_dir_url( __FILE__ ) );

require_once LOOKIT_CF_PURGE_DIR . 'includes/class-lookit-cf-purge-settings.php';
require_once LOOKIT_CF_PURGE_DIR . 'includes/class-lookit-cf-purge-cloudflare-api.php';
require_once LOOKIT_CF_PURGE_DIR . 'includes/class-lookit-cf-purge-admin-bar.php';
require_once LOOKIT_CF_PURGE_DIR . 'includes/class-lookit-cf-purge-ajax-handler.php';

add_action( 'plugins_loaded', array( 'Lookit_CF_Purge_Settings', 'init' ) );
add_action( 'plugins_loaded', array( 'Lookit_CF_Purge_Admin_Bar', 'init' ) );
add_action( 'plugins_loaded', array( 'Lookit_CF_Purge_Ajax_Handler', 'init' ) );
