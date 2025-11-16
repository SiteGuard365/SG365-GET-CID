<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Helpers {
    public static function get_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $parts[0] );
        }
        return sanitize_text_field( $ip );
    }
}
