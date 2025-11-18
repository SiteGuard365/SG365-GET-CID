<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Tokens {

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(100) NOT NULL,
            name VARCHAR(191) NULL,
            email VARCHAR(191) NOT NULL,
            limit_total INT NOT NULL DEFAULT 0,
            used INT NOT NULL DEFAULT 0,
            expiry_date DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function create_token( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        $defaults = array(
            'token'       => '',
            'name'        => '',
            'email'       => '',
            'limit_total' => 0,
            'expiry_date' => null,
            'status'      => 'active',
        );
        $data = wp_parse_args( $args, $defaults );
        $data['token'] = $data['token'] ? strtoupper( sanitize_text_field( $data['token'] ) ) : self::generate_token();
        if ( empty( $data['token'] ) ) {
            $data['token'] = self::generate_token();
        }
        $data['name']  = sanitize_text_field( $data['name'] );
        $data['email'] = sanitize_email( $data['email'] );
        $data['limit_total'] = max( 0, intval( $data['limit_total'] ) );
        $data['expiry_date'] = $data['expiry_date'] ? sanitize_text_field( $data['expiry_date'] ) : null;
        $data['status'] = in_array( $data['status'], array( 'active', 'suspended' ), true ) ? $data['status'] : 'active';

        $wpdb->insert( $table, array(
            'token'       => $data['token'],
            'name'        => $data['name'],
            'email'       => $data['email'],
            'limit_total' => $data['limit_total'],
            'used'        => 0,
            'expiry_date' => $data['expiry_date'],
            'status'      => $data['status'],
            'updated_at'  => current_time( 'mysql', 1 ),
        ), array( '%s','%s','%s','%d','%d','%s','%s','%s' ) );

        return $wpdb->insert_id;
    }

    public static function generate_token() {
        $candidate = strtoupper( wp_generate_password( 10, false, false ) );
        return preg_replace( '/[^A-Z0-9]/', '', $candidate );
    }

    public static function get_token_by_code( $code ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", strtoupper( $code ) ) );
    }

    public static function get_tokens( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        $defaults = array(
            'paged'   => 1,
            'per_page'=> 20,
            'search'  => '',
            'email'   => '',
        );
        $args = wp_parse_args( $args, $defaults );
        $offset = max( 0, ( $args['paged'] - 1 ) * $args['per_page'] );
        $where = 'WHERE 1=1';
        $params = array();
        if ( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= " AND (token LIKE %s OR email LIKE %s OR name LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ( ! empty( $args['email'] ) ) {
            $where .= " AND email = %s";
            $params[] = sanitize_email( $args['email'] );
        }
        $sql_total = "SELECT COUNT(*) FROM {$table} {$where}";
        $sql_rows  = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params_total = $params;
        $params_rows  = $params;
        $params_rows[] = $args['per_page'];
        $params_rows[] = $offset;

        $total_query = $params_total ? call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql_total ), $params_total ) ) : $sql_total;
        $rows_query  = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql_rows ), $params_rows ) );

        $total = $wpdb->get_var( $total_query );
        $rows  = $wpdb->get_results( $rows_query );
        return array( 'total' => intval( $total ), 'rows' => $rows );
    }

    public static function increment_usage( $token_id ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET used = used + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', 1 ), $token_id ) );
    }

    public static function update_status( $token_id, $status = 'active' ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        $wpdb->update( $table, array( 'status' => $status, 'updated_at' => current_time( 'mysql', 1 ) ), array( 'id' => $token_id ), array( '%s','%s' ), array( '%d' ) );
    }

    public static function delete_token( $token_id ) {
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        $wpdb->delete( $table, array( 'id' => $token_id ), array( '%d' ) );
    }

    public static function tokens_for_email( $email ) {
        if ( empty( $email ) ) {
            return array();
        }
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY created_at DESC", sanitize_email( $email ) ) );
    }

    public static function remaining( $row ) {
        if ( ! $row ) return 0;
        return max( 0, intval( $row->limit_total ) - intval( $row->used ) );
    }

    public static function is_expired( $row ) {
        if ( empty( $row->expiry_date ) ) return false;
        $today = current_time( 'Y-m-d' );
        return $row->expiry_date < $today;
    }
}
