<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_API {

    public static function init() {}

    public static function request_cid( $iid ) {
        $opts     = get_option( SG365_CID_OPTION, array() );
        $api_key  = isset( $opts['api_key'] ) ? $opts['api_key'] : '';
        $primary  = rtrim( $opts['primary_api'] ?? '', '/' );
        $fallback = rtrim( $opts['fallback_api'] ?? '', '/' );
        $timeout  = max( 10, intval( $opts['timeout'] ?? 110 ) );

        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'cid'     => null,
                'raw'     => '',
                'error'   => 'API key not set',
                'used'    => null,
            );
        }

        $iid_clean = preg_replace( '/[\s-]+/', '', trim( $iid ) );

        $params = array(
            'iids'         => $iid_clean,
            'justforcheck' => 0,
            'apikey'       => $api_key,
        );

        $query    = add_query_arg( $params, $primary );
        $response = wp_remote_get( $query, array( 'timeout' => $timeout, 'sslverify' => false ) );
        $used     = 'primary';

        if ( is_wp_error( $response ) || intval( wp_remote_retrieve_response_code( $response ) ) !== 200 ) {
            $query    = add_query_arg( $params, $fallback );
            $response = wp_remote_get( $query, array( 'timeout' => $timeout, 'sslverify' => false ) );
            $used     = 'fallback';
        }

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'cid'     => null,
                'raw'     => maybe_serialize( $response->get_error_messages() ),
                'error'   => 'Server is down, please contact us',
                'used'    => $used,
            );
        }

        $code = intval( wp_remote_retrieve_response_code( $response ) );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return array(
                'success' => false,
                'cid'     => null,
                'raw'     => $body,
                'error'   => 'Server is down, please contact us',
                'used'    => $used,
            );
        }

        $body_lower = strtolower( $body );

        if ( strpos( $body_lower, 'iid is not correct' ) !== false
          || strpos( $body_lower, 'installation id is not correct' ) !== false
          || preg_match( '/iid.*not.*correct/i', $body ) ) {
            return array(
                'success' => false,
                'cid'     => null,
                'raw'     => $body,
                'error'   => 'Installation ID is not correct. Please check again; if you think it is correct, contact us.',
                'used'    => $used,
            );
        }

        if ( strpos( $body_lower, 'exceeded the set number of activations' ) !== false
          || strpos( $body_lower, 'has exceeded the set number of activations' ) !== false
          || strpos( $body_lower, 'exceeded the set number' ) !== false ) {
            return array(
                'success' => false,
                'cid'     => null,
                'raw'     => $body,
                'error'   => 'No CID found in response please contact us',
                'used'    => $used,
            );
        }

        if ( strpos( $body_lower, 'invalid' ) !== false && strpos( $body_lower, 'iid' ) !== false ) {
            return array(
                'success' => false,
                'cid'     => null,
                'raw'     => $body,
                'error'   => 'Installation ID is not correct. Please check again; if you think it is correct, contact us.',
                'used'    => $used,
            );
        }

        $cid = null;
        $json = json_decode( $body, true );

        if ( is_array( $json ) ) {
            $keys_to_try = array( 'cid', 'CID', 'confirmation_id', 'confirmationId', 'ConfirmationID', 'Confirmation_Id' );
            foreach ( $keys_to_try as $k ) {
                if ( ! empty( $json[ $k ] ) ) {
                    $candidate = (string) $json[ $k ];
                    $cid = self::normalize_cid_candidate( $candidate );
                    if ( $cid ) break;
                }
            }

            if ( ! $cid && ! empty( $json['data'] ) && is_array( $json['data'] ) ) {
                $first = reset( $json['data'] );
                if ( is_array( $first ) ) {
                    foreach ( $keys_to_try as $k ) {
                        if ( isset( $first[ $k ] ) && $first[ $k ] ) {
                            $candidate = (string) $first[ $k ];
                            $cid = self::normalize_cid_candidate( $candidate );
                            if ( $cid ) break;
                        }
                    }
                } else {
                    $cid = self::extract_cid_from_text( (string) $first );
                }
            }

            if ( ! $cid ) {
                $flat = wp_json_encode( $json );
                $cid = self::extract_cid_from_text( $flat );
            }
        } else {
            $cid = self::extract_cid_from_text( $body );
        }

        if ( ! $cid ) {
            return array(
                'success' => false,
                'cid'     => null,
                'raw'     => $body,
                'error'   => 'No CID found in response please contact us',
                'used'    => $used,
            );
        }

        return array(
            'success' => true,
            'cid'     => $cid,
            'raw'     => $body,
            'error'   => null,
            'used'    => $used,
        );
    }

    protected static function normalize_cid_candidate( $candidate ) {
        if ( ! is_string( $candidate ) ) return null;
        $candidate = trim( $candidate );
        if ( $candidate === '' ) return null;

        if ( preg_match_all( '/\d{6,7}/', $candidate, $matches ) ) {
            $groups = $matches[0];
            if ( count( $groups ) >= 8 ) {
                $first8 = array_slice( $groups, 0, 8 );
                return implode( ' ', $first8 );
            }
        }

        $digits = preg_replace( '/\D+/', '', $candidate );
        $len = strlen( $digits );
        if ( $len < 48 ) {
            return null;
        }
        $base = intdiv( $len, 8 );
        $rem  = $len % 8;
        $pos  = 0;
        $blocks = array();
        for ( $i = 0; $i < 8; $i++ ) {
            $size = $base + ( $i < $rem ? 1 : 0 );
            $blocks[] = substr( $digits, $pos, $size );
            $pos += $size;
        }
        foreach ( $blocks as $b ) {
            if ( $b === '' || ! ctype_digit( $b ) ) return null;
        }
        return implode( ' ', $blocks );
    }

    protected static function extract_cid_from_text( $text ) {
        if ( ! is_string( $text ) ) return null;

        if ( preg_match( '/((?:\d{6,7}[\s-]?){8,})/', $text, $m ) ) {
            $candidate = $m[1];
            $normalized = self::normalize_cid_candidate( $candidate );
            if ( $normalized ) return $normalized;
        }

        if ( preg_match_all( '/\d{48,}/', $text, $matches ) ) {
            $longest = '';
            foreach ( $matches[0] as $seq ) {
                if ( strlen( $seq ) > strlen( $longest ) ) $longest = $seq;
            }
            $normalized = self::normalize_cid_candidate( $longest );
            if ( $normalized ) return $normalized;
        }

        return null;
    }

    public static function find_existing_cid( $order_id, $email, $iid = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;

        if ( empty( $iid ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_id = %s AND email = %s AND status = %s ORDER BY created_at DESC LIMIT 1",
                (string) $order_id,
                $email,
                'success'
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_id = %s AND email = %s AND iid = %s AND status = %s ORDER BY created_at DESC LIMIT 1",
                (string) $order_id,
                $email,
                $iid,
                'success'
            );
        }

        return $wpdb->get_row( $sql );
    }
}
