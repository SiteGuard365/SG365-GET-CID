<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'sg365_cid_cron_cleanup' );
    }
}
