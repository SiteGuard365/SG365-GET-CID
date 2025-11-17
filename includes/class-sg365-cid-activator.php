<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Activator {

    public static function activate() {
        self::create_log_table();
        self::create_token_table();
        self::set_default_options();
        if ( ! wp_next_scheduled( 'sg365_cid_cron_cleanup' ) ) {
            wp_schedule_event( time() + 3600, 'daily', 'sg365_cid_cron_cleanup' );
        }
        if ( ! wp_next_scheduled( 'sg365_cid_weekly_license_check' ) ) {
            $timestamp = time() + DAY_IN_SECONDS;
            wp_schedule_event( $timestamp, 'daily', 'sg365_cid_weekly_license_check' );
        }
    }

    public static function create_log_table() {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
          id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          order_id VARCHAR(50) NULL,
          user_id BIGINT(20) NULL,
          email VARCHAR(191) NULL,
          product_ids TEXT NULL,
          iid TEXT NULL,
          cid TEXT NULL,
          api_response LONGTEXT NULL,
          status VARCHAR(50) NULL,
          ip VARCHAR(45) NULL,
          PRIMARY KEY (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function set_default_options() {
        $defaults = array(
            'api_key' => '',
            'primary_api' => 'https://pidkey.com/ajax/cidms_api',
            'fallback_api' => 'https://khoatoantin.com/ajax/cidms_api',
            'timeout' => 110,
            'rate_limit_seconds' => 30,
            'allow_guests' => 0,
            'logs_retention' => 365,
            'enable_captcha' => 0,
            'get_cid_page_url' => '',
            'show_order_detail_data' => 0,
        );
        add_option( SG365_CID_OPTION, $defaults );
        add_option( SG365_CID_LICENSE_OPTION, array(
            'license_key' => '',
            'plan'        => 'free',
            'status'      => 'inactive',
            'data'        => array(),
            'checked_at'  => '',
        ) );
    }

    protected static function create_token_table() {
        if ( class_exists( 'SG365_CID_Tokens' ) ) {
            SG365_CID_Tokens::create_table();
        }
    }
}
