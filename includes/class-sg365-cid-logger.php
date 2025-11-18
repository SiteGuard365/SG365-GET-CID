<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Logger {

    public static function init() {
        add_action( 'sg365_cid_cron_cleanup', array( __CLASS__, 'cleanup_logs' ) );
    }

    public static function log_success( $data = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $defaults = array(
            'order_id' => null,
            'user_id' => null,
            'email' => null,
            'product_ids' => null,
            'iid' => null,
            'cid' => null,
            'api_response' => null,
            'status' => 'success',
            'ip' => null,
        );
        $payload = wp_parse_args( $data, $defaults );
        $wpdb->insert( $table, array(
            'order_id' => $payload['order_id'],
            'user_id' => $payload['user_id'],
            'email' => $payload['email'],
            'product_ids' => maybe_serialize( $payload['product_ids'] ),
            'iid' => $payload['iid'],
            'cid' => $payload['cid'],
            'api_response' => $payload['api_response'],
            'status' => $payload['status'],
            'ip' => $payload['ip'],
        ), array( '%s','%d','%s','%s','%s','%s','%s','%s','%s' ) );
        return $wpdb->insert_id;
    }

    public static function log_error( $data = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $payload = wp_parse_args( $data, array(
            'order_id' => null,
            'user_id' => null,
            'email' => null,
            'product_ids' => null,
            'iid' => null,
            'cid' => null,
            'api_response' => null,
            'status' => 'error',
            'ip' => null,
        ) );
        $wpdb->insert( $table, array(
            'order_id' => $payload['order_id'],
            'user_id' => $payload['user_id'],
            'email' => $payload['email'],
            'product_ids' => maybe_serialize( $payload['product_ids'] ),
            'iid' => $payload['iid'],
            'cid' => $payload['cid'],
            'api_response' => $payload['api_response'],
            'status' => $payload['status'],
            'ip' => $payload['ip'],
        ) );
        return $wpdb->insert_id;
    }

    public static function update_log( $id, $data = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        return $wpdb->update( $table, $data, array( 'id' => $id ) );
    }

    public static function cleanup_logs() {
        $opts = get_option( SG365_CID_OPTION, array() );
        $days = intval( $opts['logs_retention'] ?? 365 );
        if ( $days <= 0 ) return;
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
    }

    /* admin helper: fetch logs with pagination & search */
    public static function fetch_logs( $search = '', $paged = 1, $per_page = 50 ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $offset = max( 0, ($paged - 1) * $per_page );
        if ( empty( $search ) ) {
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
        } else {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE order_id LIKE %s OR email LIKE %s", $like, $like ) );
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id LIKE %s OR email LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $like, $like, $per_page, $offset ) );
        }
        return array( 'total' => intval( $total ), 'rows' => $rows );
    }

    public static function delete_logs( $ids = array() ) {
        if ( empty( $ids ) ) return 0;
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $query = $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids );
        return $wpdb->query( $query );
    }

    public static function delete_logs_before_days( $days ) {
        $days = max( 1, intval( $days ) );
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
    }

    /**
     * Count successful CID generations for a specific order.
     */
    public static function count_success_for_order( $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        return intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE order_id = %s AND status = %s",
            (string) $order_id,
            'success'
        ) ) );
    }

    /**
     * Fetch the latest success log for an order.
     */
    public static function last_success_for_order( $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %s AND status = %s ORDER BY created_at DESC LIMIT 1",
            (string) $order_id,
            'success'
        ) );
    }

    public static function count_success_since_days( $days ) {
        $days = max( 1, intval( $days ) );
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
        return intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s AND created_at >= %s", 'success', $cutoff ) ) );
    }

    public static function top_tokens_usage( $days = 30, $limit = 5 ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-' . max( 1, intval( $days ) ) . ' days' ) );
        $limit  = max( 1, intval( $limit ) );
        $pattern = 'token:%';
        $sql = $wpdb->prepare(
            "SELECT order_id, COUNT(*) as uses FROM {$table} WHERE status = %s AND created_at >= %s AND order_id LIKE %s GROUP BY order_id ORDER BY uses DESC LIMIT %d",
            'success',
            $cutoff,
            $pattern,
            $limit
        );
        return $wpdb->get_results( $sql );
    }

    public static function top_orders_usage( $days = 30, $limit = 5 ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-' . max( 1, intval( $days ) ) . ' days' ) );
        $limit  = max( 1, intval( $limit ) );
        $sql = $wpdb->prepare(
            "SELECT order_id, COUNT(*) as uses FROM {$table} WHERE status = %s AND created_at >= %s AND (order_id IS NOT NULL AND order_id NOT LIKE %s) GROUP BY order_id ORDER BY uses DESC LIMIT %d",
            'success',
            $cutoff,
            'token:%',
            $limit
        );
        return $wpdb->get_results( $sql );
    }
}
