<?php
/**
 * Plugin Name: SG365 CID
 * Plugin URI:  https://siteguard365.com/
 * Description: Generate Confirmation IDs (CID) via PIDKey / CIDMS API and integrate with WooCommerce orders.
 * Version:     1.3.0
 * Author:      siteguard365
 * Author URI:  https://siteguard365.com/
 * Text Domain: sg365-cid
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SG365_CID_VERSION', '1.3.0' );
define( 'SG365_CID_FILE', __FILE__ );
define( 'SG365_CID_DIR', plugin_dir_path( __FILE__ ) );
define( 'SG365_CID_URL', plugin_dir_url( __FILE__ ) );
define( 'SG365_CID_OPTION', 'sg365_cid_options' );
define( 'SG365_CID_LOG_TABLE', 'sg365_cid_logs' );
define( 'SG365_CID_LICENSE_OPTION', 'sg365_cid_license' );
define( 'SG365_CID_TOKEN_TABLE', 'sg365_cid_tokens' );

/* includes */
require_once SG365_CID_DIR . 'includes/class-sg365-cid-activator.php';
require_once SG365_CID_DIR . 'includes/class-sg365-cid-deactivator.php';
require_once SG365_CID_DIR . 'includes/class-sg365-cid-api.php';
require_once SG365_CID_DIR . 'includes/class-sg365-cid-logger.php';
require_once SG365_CID_DIR . 'includes/class-sg365-cid-tokens.php';
require_once SG365_CID_DIR . 'includes/class-sg365-cid-admin.php';
require_once SG365_CID_DIR . 'includes/class-sg365-cid-frontend.php';
require_once SG365_CID_DIR . 'includes/helpers.php';

register_activation_hook( __FILE__, array( 'SG365_CID_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SG365_CID_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'sg365-cid', false, dirname( plugin_basename( SG365_CID_FILE ) ) . '/languages' );

    if ( class_exists( 'SG365_CID_Admin' ) ) SG365_CID_Admin::init();
    if ( class_exists( 'SG365_CID_Frontend' ) ) SG365_CID_Frontend::init();
    if ( class_exists( 'SG365_CID_API' ) ) SG365_CID_API::init();
    if ( class_exists( 'SG365_CID_Logger' ) ) SG365_CID_Logger::init();
} );
