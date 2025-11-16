<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

        add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_field' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_field' ) );

        add_action( 'add_meta_boxes', array( __CLASS__, 'order_meta_box' ) );

        add_action( 'wp_ajax_sg365_admin_generate_cid', array( __CLASS__, 'ajax_admin_generate_cid' ) );
        add_action( 'admin_post_sg365_delete_logs', array( __CLASS__, 'handle_delete_logs' ) );
    }

    public static function admin_menu() {
        add_options_page( 'SG365 CID Settings', 'SG365 CID', 'manage_options', 'sg365-cid-settings', array( __CLASS__, 'settings_page' ) );
        add_menu_page( 'SG365 CID Logs', 'SG365 CID', 'manage_options', 'sg365-cid-logs', array( __CLASS__, 'logs_page' ), 'dashicons-admin-network', 56 );
    }

    public static function register_settings() {
        register_setting( 'sg365_cid_group', SG365_CID_OPTION, array( __CLASS__, 'sanitize_options' ) );
        add_settings_section( 'sg365_cid_main', 'API Settings', null, 'sg365-cid-settings' );

        add_settings_field( 'api_key', 'API Key', array( __CLASS__, 'field_api_key' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'primary_api', 'Primary API Endpoint', array( __CLASS__, 'field_primary_api' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'fallback_api', 'Fallback API Endpoint', array( __CLASS__, 'field_fallback_api' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'timeout', 'API Timeout (seconds)', array( __CLASS__, 'field_timeout' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'rate_limit_seconds', 'Rate limit (seconds)', array( __CLASS__, 'field_rate_limit' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'allow_guests', 'Allow guests to use shortcode', array( __CLASS__, 'field_allow_guests' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'logs_retention', 'Logs retention (days)', array( __CLASS__, 'field_logs_retention' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'enable_captcha', 'Enable Math Captcha', array( __CLASS__, 'field_enable_captcha' ), 'sg365-cid-settings', 'sg365_cid_main' );
    }

    public static function sanitize_options( $in ) {
        $out = array();
        $out['api_key'] = sanitize_text_field( $in['api_key'] ?? '' );
        $out['primary_api'] = esc_url_raw( $in['primary_api'] ?? 'https://pidkey.com/ajax/cidms_api' );
        $out['fallback_api'] = esc_url_raw( $in['fallback_api'] ?? 'https://khoatoantin.com/ajax/cidms_api' );
        $out['timeout'] = intval( $in['timeout'] ?? 110 );
        $out['rate_limit_seconds'] = intval( $in['rate_limit_seconds'] ?? 30 );
        $out['allow_guests'] = ! empty( $in['allow_guests'] ) ? 1 : 0;
        $out['logs_retention'] = intval( $in['logs_retention'] ?? 365 );
        $out['enable_captcha'] = ! empty( $in['enable_captcha'] ) ? 1 : 0;
        return $out;
    }

    public static function get_opts() {
        return get_option( SG365_CID_OPTION, array() );
    }
    /* fields */
    public static function field_api_key() {
        $o = self::get_opts();
        printf( '<input type="text" size="60" name="%s[api_key]" value="%s" class="regular-text" />', SG365_CID_OPTION, esc_attr( $o['api_key'] ?? '' ) );
    }
    public static function field_primary_api() {
        $o = self::get_opts();
        printf( '<input type="text" size="60" name="%s[primary_api]" value="%s" class="regular-text" />', SG365_CID_OPTION, esc_attr( $o['primary_api'] ?? '' ) );
    }
    public static function field_fallback_api() {
        $o = self::get_opts();
        printf( '<input type="text" size="60" name="%s[fallback_api]" value="%s" class="regular-text" />', SG365_CID_OPTION, esc_attr( $o['fallback_api'] ?? '' ) );
    }
    public static function field_timeout() {
        $o = self::get_opts();
        printf( '<input type="number" min="10" max="300" name="%s[timeout]" value="%d" />', SG365_CID_OPTION, intval( $o['timeout'] ?? 110 ) );
    }
    public static function field_rate_limit() {
        $o = self::get_opts();
        printf( '<input type="number" min="1" max="3600" name="%s[rate_limit_seconds]" value="%d" />', SG365_CID_OPTION, intval( $o['rate_limit_seconds'] ?? 30 ) );
    }
    public static function field_allow_guests() {
        $o = self::get_opts();
        printf( '<input type="checkbox" name="%s[allow_guests]" value="1" %s /> Allow guests to request CID (guest users won\'t have persistent accounts).', SG365_CID_OPTION, checked( 1, $o['allow_guests'] ?? 0, false ) );
    }
    public static function field_logs_retention() {
        $o = self::get_opts();
        printf( '<input type="number" min="0" max="3650" name="%s[logs_retention]" value="%d" /> days (0 = keep forever)', SG365_CID_OPTION, intval( $o['logs_retention'] ?? 365 ) );
    }
    public static function field_enable_captcha() {
        $o = self::get_opts();
        printf( '<input type="checkbox" name="%s[enable_captcha]" value="1" %s /> Enable math captcha for frontend CID requests (show random math questions).', SG365_CID_OPTION, checked( 1, $o['enable_captcha'] ?? 0, false ) );
    }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>SG365 CID Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'sg365_cid_group' );
                do_settings_sections( 'sg365-cid-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /* Logs admin page */
    public static function logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 50;
        $res = SG365_CID_Logger::fetch_logs( $search, $paged, $per_page );
        $total = $res['total'];
        $rows = $res['rows'];
        $total_pages = ceil( $total / $per_page );
        ?>
        <div class="wrap">
            <h1>SG365 CID Logs</h1>

            <form method="get">
                <input type="hidden" name="page" value="sg365-cid-logs">
                <p class="search-box">
                    <label class="screen-reader-text" for="sg365-log-search-input">Search Logs</label>
                    <input type="search" id="sg365-log-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by order ID or email" />
                    <input type="submit" class="button" value="Search">
                </p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="sg365-logs-form">
                <?php wp_nonce_field( 'sg365_delete_logs', 'sg365_delete_logs_nonce' ); ?>
                <input type="hidden" name="action" value="sg365_delete_logs" />
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:24px;"><input type="checkbox" id="sg365_check_all"></th>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Order</th>
                            <th>Email</th>
                            <th>Products</th>
                            <th>IID</th>
                            <th>CID</th>
                            <th>Status</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( $rows ) : foreach ( $rows as $r ): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo esc_attr( $r->id ); ?>"></td>
                            <td><?php echo esc_html( $r->id ); ?></td>
                            <td><?php echo esc_html( $r->created_at ); ?></td>
                            <td><?php echo $r->order_id ? '<a href="' . esc_url( admin_url( 'post.php?post=' . intval( $r->order_id ) . '&action=edit' ) ) . '">' . esc_html( $r->order_id ) . '</a>' : '-'; ?></td>
                            <td><?php echo esc_html( $r->email ); ?></td>
                            <td><?php
                                $p = maybe_unserialize( $r->product_ids );
                                if ( is_array( $p ) ) {
                                    $out = array();
                                    foreach ( $p as $pid ) {
                                        $out[] = get_the_title( $pid ) . ' (' . intval( $pid ) . ')';
                                    }
                                    echo esc_html( implode( ', ', $out ) );
                                } else {
                                    echo '-';
                                }
                            ?></td>
                            <td><?php echo esc_html( wp_trim_words( $r->iid, 6, '...' ) ); ?></td>
                            <td><?php echo esc_html( $r->cid ); ?></td>
                            <td><?php echo esc_html( $r->status ); ?></td>
                            <td><?php echo esc_html( $r->ip ); ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="10">No logs found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <p>
                    <button type="submit" class="button button-primary" onclick="return confirm('Delete selected logs?')">Delete Selected</button>
                </p>
            </form>

            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $base = add_query_arg( array( 'page' => 'sg365-cid-logs', 's' => $search, 'paged' => '%#%' ), admin_url( 'admin.php' ) );
                    echo paginate_links( array( 'base' => $base, 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $total_pages, 'current' => $paged ) );
                    ?>
                </div>
            </div>

        </div>

        <script>
        (function(){
            const checkAll = document.getElementById('sg365_check_all');
            if (checkAll) {
                checkAll.addEventListener('change', function(){
                    const boxes = document.querySelectorAll('#sg365-logs-form input[type="checkbox"][name="ids[]"]');
                    boxes.forEach(b => b.checked = checkAll.checked);
                });
            }
        })();
        </script>
        <style>
            /* make logs mobile friendly */
            @media (max-width:800px){
                .widefat th:nth-child(6), .widefat td:nth-child(6),
                .widefat th:nth-child(7), .widefat td:nth-child(7),
                .widefat th:nth-child(9), .widefat td:nth-child(9) { display:none; }
            }
        </style>
        <?php
    }

    /* Product field */
    public static function product_field() {
        if ( ! function_exists( 'woocommerce_wp_checkbox' ) ) return;
        woocommerce_wp_checkbox( array(
            'id' => '_sg365_enable_cid',
            'wrapper_class' => 'show_if_simple',
            'label' => __( 'Enable CID limit for this product', 'sg365-cid' ),
            'description' => __( 'If checked, buying this product grants CID request allowance equal to purchased quantity.', 'sg365-cid' ),
        ) );
    }

    public static function save_product_field( $post_id ) {
        $val = isset( $_POST['_sg365_enable_cid'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_sg365_enable_cid', $val );
    }

    public static function order_meta_box() {
        add_meta_box( 'sg365-cid-order', 'SG365 CID', array( __CLASS__, 'order_meta_box_cb' ), 'shop_order', 'side', 'default' );
    }

    public static function order_meta_box_cb( $post ) {
        $order_id = $post->ID;
        $remaining = intval( get_post_meta( $order_id, '_sg365_cid_remaining', true ) );
        echo '<p><strong>CID remaining:</strong> ' . intval( $remaining ) . '</p>';

        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %s ORDER BY created_at DESC LIMIT 10", (string)$order_id ) );
        if ( $rows ) {
            echo '<p><strong>Recent CIDs:</strong></p><ul>';
            foreach ( $rows as $r ) {
                echo '<li>' . esc_html( $r->created_at ) . ' — ' . esc_html( $r->cid ? $r->cid : $r->status ) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No CIDs generated yet.</p>';
        }

        // admin generate (requires IID)
        ?>
        <p>
            <input type="text" id="sg365_admin_iid" placeholder="Installation ID (IID)" style="width:100%; margin-bottom:6px;" />
            <button class="button" id="sg365-admin-gen-btn" data-order="<?php echo esc_attr( $order_id ); ?>">Generate CID (Admin)</button>
            <span id="sg365-admin-gen-status" style="margin-left:8px;"></span>
        </p>
        <script>
        (function(){
            const btn = document.getElementById('sg365-admin-gen-btn');
            if (!btn) return;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const order = btn.getAttribute('data-order');
                const iid = document.getElementById('sg365_admin_iid').value.trim();
                if (!iid) { alert('Please enter IID'); return; }
                if (!confirm('Generate CID for order ' + order + '? This will consume one remaining CID for the order if available.')) return;
                const data = new FormData();
                data.append('action','sg365_admin_generate_cid');
                data.append('order_id', order);
                data.append('iid', iid);
                data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'sg365_admin_generate_cid' ) ); ?>');
                fetch(ajaxurl, { method:'POST', body: data })
                    .then(r => r.json())
                    .then(j => {
                        const status = document.getElementById('sg365-admin-gen-status');
                        if (j.error) status.innerText = 'Error: ' + j.error;
                        else status.innerText = 'CID: ' + j.cid + (j.remaining !== undefined ? ' (remaining: '+ j.remaining +')' : '');
                    })
                    .catch(err => {
                        const status = document.getElementById('sg365-admin-gen-status');
                        status.innerText = 'Request failed';
                    });
            });
        })();
        </script>
        <?php
    }

    /* admin generate AJAX */
    public static function ajax_admin_generate_cid() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( array( 'error' => 'Unauthorized' ) );
        }
        check_ajax_referer( 'sg365_admin_generate_cid', 'nonce' );
        $order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ?? '' ) );
        $iid = sanitize_text_field( wp_unslash( $_POST['iid'] ?? '' ) );
        if ( empty( $order_id ) || empty( $iid ) ) wp_send_json( array( 'error' => 'Invalid request' ) );
        if ( ! function_exists( 'wc_get_order' ) ) wp_send_json( array( 'error' => 'WooCommerce not active' ) );
        $order = wc_get_order( $order_id );
        if ( ! $order ) wp_send_json( array( 'error' => 'Order not found' ) );

        $remaining = intval( get_post_meta( $order_id, '_sg365_cid_remaining', true ) );
        if ( $remaining <= 0 ) {
            wp_send_json( array( 'error' => 'No remaining CID allowance' ) );
        }

        $email = $order->get_billing_email();
        $user_id = $order->get_user_id() ? $order->get_user_id() : null;

        // rate-limit
        $opts = self::get_opts();
        $rate_seconds = max( 1, intval( $opts['rate_limit_seconds'] ?? 30 ) );
        $transient = "sg365_admin_rl_" . md5( $order_id . '|' . $iid );
        if ( get_transient( $transient ) ) wp_send_json( array( 'error' => 'Too many requests. Try later.' ) );
        set_transient( $transient, 1, $rate_seconds );

        // call API
        $res = SG365_CID_API::request_cid( $iid );
        if ( ! $res['success'] ) {
            SG365_CID_Logger::log_error( array( 'order_id' => $order_id, 'user_id' => $user_id, 'email' => $email, 'product_ids' => self::order_product_ids( $order ), 'iid' => $iid, 'api_response' => $res['raw'], 'status' => 'error', 'ip' => SG365_CID_Helpers::get_ip() ) );
            wp_send_json( array( 'error' => $res['error'] ) );
        }

        // success: don't consume allowance? For admin we will consume as requested earlier
        $remaining_after = max( 0, $remaining - 1 );
        update_post_meta( $order_id, '_sg365_cid_remaining', $remaining_after );

        SG365_CID_Logger::log_success( array( 'order_id' => $order_id, 'user_id' => $user_id, 'email' => $email, 'product_ids' => self::order_product_ids( $order ), 'iid' => $iid, 'cid' => $res['cid'], 'api_response' => $res['raw'], 'status' => 'success', 'ip' => SG365_CID_Helpers::get_ip() ) );

        wp_send_json( array( 'cid' => $res['cid'], 'remaining' => $remaining_after ) );
    }

    public static function order_product_ids( $order ) {
        $ids = array();
        foreach ( $order->get_items() as $item ) {
            $ids[] = $item->get_product_id();
        }
        return $ids;
    }

    /* handle delete logs post */
    public static function handle_delete_logs() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'sg365_delete_logs', 'sg365_delete_logs_nonce' );
        $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
        if ( $ids ) {
            SG365_CID_Logger::delete_logs( $ids );
        }
        wp_redirect( admin_url( 'admin.php?page=sg365-cid-logs' ) );
        exit;
    }
}
