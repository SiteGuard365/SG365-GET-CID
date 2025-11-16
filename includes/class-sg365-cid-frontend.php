<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Frontend {

    private static $captcha_pool = array();

    public static function init() {
        add_shortcode( 'sg365_cid_form', array( __CLASS__, 'shortcode_form' ) );
        add_shortcode( 'sg365_cid_history', array( __CLASS__, 'shortcode_history' ) );

        add_action( 'wp_ajax_sg365_get_cid', array( __CLASS__, 'ajax_get_cid' ) );
        add_action( 'wp_ajax_nopriv_sg365_get_cid', array( __CLASS__, 'ajax_get_cid' ) );

        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'allocate_on_order' ) );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'allocate_on_order' ) );

        add_action( 'woocommerce_thankyou', array( __CLASS__, 'show_order_cid_info' ), 10, 1 );
        add_action( 'woocommerce_view_order', array( __CLASS__, 'show_order_cid_info' ), 10, 1 ); // My account > view order
    }

    public static function enqueue_assets() {
        wp_enqueue_style( 'sg365-cid-style', SG365_CID_URL . 'assets/css/sg365-cid.css', array(), SG365_CID_VERSION );
        wp_enqueue_script( 'sg365-cid-js', SG365_CID_URL . 'assets/js/sg365-cid.js', array(), SG365_CID_VERSION, true );
        $local = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'sg365_cid_request' ),
            'enable_captcha' => intval( self::get_opts()['enable_captcha'] ?? 0 ),
        );
        wp_localize_script( 'sg365-cid-js', 'SG365_CID', $local );
    }

    public static function get_opts() {
        return get_option( SG365_CID_OPTION, array() );
    }

    public static function shortcode_form( $atts ) {
        $opts = self::get_opts();
        if ( ! is_user_logged_in() && empty( $opts['allow_guests'] ) ) {
            return '<p>Please log in to request a Confirmation ID.</p>';
        }

        // prepare captcha question if enabled
        $captcha_html = '';
        if ( ! empty( $opts['enable_captcha'] ) ) {
            $q = self::random_captcha_question();
            // store in transient keyed to session or cookie
            $cid_token = 'sg365_captcha_' . wp_generate_password( 12, false, false );
            set_transient( $cid_token, $q, 10 * MINUTE_IN_SECONDS ); // 10 minutes
            $captcha_html = '<p><label>Verification: <br><strong>' . esc_html( $q['question'] ) . '</strong><br><input type="text" id="sg365_captcha_answer" name="sg365_captcha_answer" placeholder="Answer" required></label><input type="hidden" id="sg365_captcha_token" value="' . esc_attr( $cid_token ) . '"></p>';
        }

        ob_start();
        ?>
        <div id="sg365-cid-app" class="sg365-cid-wrap">
            <form id="sg365-cid-form" method="post">
                <?php wp_nonce_field( 'sg365_cid_request', 'sg365_cid_nonce' ); ?>
                <p><label>Order Number:<br><input type="text" id="sg365_order_id" name="order_id" placeholder="123456" required></label></p>
                <p><label>Email:<br><input type="email" id="sg365_email" name="email" placeholder="your-email@domain.com" required></label></p>
                <p><label>Installation ID:<br><input type="text" id="sg365_iid" name="iid" placeholder="1234567-1234-..." required></label></p>
                <?php echo $captcha_html; ?>
                <p><button id="sg365_get_cid" class="button button-primary">Get Confirmation ID</button></p>
            </form>

            <div id="sg365_status" style="display:none;">
                <p id="sg365_step">Starting...</p>
                <p>Elapsed: <span id="sg365_timer">0s</span></p>
                <div id="sg365_result" style="margin-top:1em;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* Pick random math questions pool (10 questions) */
    private static function math_pool() {
        return array(
            array('question'=>'7 + 5 = ?', 'answer'=>'12'),
            array('question'=>'9 - 4 = ?', 'answer'=>'5'),
            array('question'=>'6 + 8 = ?', 'answer'=>'14'),
            array('question'=>'5 * 3 = ?', 'answer'=>'15'),
            array('question'=>'20 - 6 = ?', 'answer'=>'14'),
            array('question'=>'12 / 4 = ?', 'answer'=>'3'),
            array('question'=>'8 + 7 = ?', 'answer'=>'15'),
            array('question'=>'10 - 3 = ?', 'answer'=>'7'),
            array('question'=>'3 * 4 = ?', 'answer'=>'12'),
            array('question'=>'18 / 3 = ?', 'answer'=>'6'),
        );
    }

    private static function random_captcha_question() {
        $pool = self::math_pool();
        $idx = array_rand( $pool );
        return $pool[ $idx ];
    }

    public static function shortcode_history( $atts ) {
        if ( ! is_user_logged_in() ) return '<p>Please login to view your CID history.</p>';
        $user = wp_get_current_user();
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_LOG_TABLE;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50", $user->ID ) );
        if ( ! $rows ) return '<p>No history found.</p>';
        ob_start();
        echo '<table class="widefat sg365-cid-history"><thead><tr><th>Date</th><th>Order</th><th>IID</th><th>CID</th><th>Status</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            echo '<tr><td>' . esc_html( $r->created_at ) . '</td><td>' . esc_html( $r->order_id ) . '</td><td>' . esc_html( wp_trim_words( $r->iid, 6, '...' ) ) . '</td><td>' . esc_html( $r->cid ? $r->cid : '-' ) . '</td><td>' . esc_html( $r->status ) . '</td></tr>';
        }
        echo '</tbody></table>';
        return ob_get_clean();
    }

    public static function allocate_on_order( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $total_allow = 0;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;
            $enabled = get_post_meta( $product->get_id(), '_sg365_enable_cid', true );
            if ( $enabled === 'yes' ) {
                $total_allow += intval( $item->get_quantity() );
            }
        }

        if ( $total_allow > 0 ) {
            update_post_meta( $order_id, '_sg365_cid_remaining', $total_allow );
        }
    }

    /* Show CID limit on thank you / order details */
    public static function show_order_cid_info( $order_id ) {
        if ( empty( $order_id ) ) return;
        $remaining = intval( get_post_meta( $order_id, '_sg365_cid_remaining', true ) );
        if ( $remaining > 0 ) {
            echo '<div class="sg365-order-cid-info" style="margin:1em 0;padding:12px;border:1px dashed #ddd;background:#f9f9f9;">';
            echo '<strong>Confirmation ID allowance for this order:</strong> ' . intval( $remaining ) . ' request(s) available.';
            echo '</div>';
        } else {
            // show 0 or no message
        }
    }

    /* AJAX endpoint */
    public static function ajax_get_cid() {
        $nonce = $_POST['nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'sg365_cid_request' ) ) {
            wp_send_json( array( 'error' => 'Invalid request (nonce).' ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $iid = isset( $_POST['iid'] ) ? sanitize_text_field( wp_unslash( $_POST['iid'] ) ) : '';
        $captcha_answer = isset( $_POST['captcha_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_answer'] ) ) : '';
        $captcha_token = isset( $_POST['captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_token'] ) ) : '';

        if ( empty( $order_id ) || empty( $email ) || empty( $iid ) ) {
            wp_send_json( array( 'error' => 'All fields are required.' ) );
        }

        $opts = self::get_opts();
        if ( ! is_user_logged_in() && empty( $opts['allow_guests'] ) ) {
            wp_send_json( array( 'error' => 'Please log in to request CID.' ) );
        }

        if ( ! function_exists( 'wc_get_order' ) ) {
            wp_send_json( array( 'error' => 'WooCommerce not active.' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json( array( 'error' => 'Order not found.' ) );
        }

        // check email matches order
        $order_email = $order->get_billing_email();
        if ( strtolower( $order_email ) !== strtolower( $email ) ) {
            wp_send_json( array( 'error' => 'Email does not match order billing email.' ) );
        }

        $allowed_statuses = array( 'completed', 'processing' );
        if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
            wp_send_json( array( 'error' => 'Order status does not allow CID requests (needs to be completed or processing).' ) );
        }

        // captcha if enabled
        if ( ! empty( $opts['enable_captcha'] ) ) {
            if ( empty( $captcha_token ) || empty( $captcha_answer ) ) {
                wp_send_json( array( 'error' => 'Captcha required.' ) );
            }
            $stored = get_transient( $captcha_token );
            if ( ! $stored || ! is_array( $stored ) ) wp_send_json( array( 'error' => 'Invalid or expired captcha.' ) );
            if ( trim( $captcha_answer ) !== trim( $stored['answer'] ) ) wp_send_json( array( 'error' => 'Captcha answer incorrect.' ) );
            // clear transient
            delete_transient( $captcha_token );
        }

        // If a success CID already exists for this order+email+iid, return it and DO NOT consume limit
        $existing = SG365_CID_API::find_existing_cid( $order_id, $email, $iid );
        if ( $existing ) {
            wp_send_json( array( 'cid' => $existing->cid, 'remaining' => intval( get_post_meta( $order_id, '_sg365_cid_remaining', true ) ), 'message' => 'This Confirmation ID was already generated for your order.' ) );
        }

        // Check remaining allowance
        $remaining = intval( get_post_meta( $order_id, '_sg365_cid_remaining', true ) );
        if ( $remaining <= 0 ) {
            wp_send_json( array( 'error' => 'You have no CID limit on this order.' ) );
        }

        // rate limiting per order+ip
        $rate_seconds = max( 1, intval( $opts['rate_limit_seconds'] ?? 30 ) );
        $ip = SG365_CID_Helpers::get_ip();
        $order_key = "sg365_cid_rl_order_{$order_id}";
        $ip_key = "sg365_cid_rl_ip_" . md5( $ip );

        if ( get_transient( $order_key ) || get_transient( $ip_key ) ) {
            wp_send_json( array( 'error' => 'Too many requests. Please wait and try again.' ) );
        }
        set_transient( $order_key, 1, $rate_seconds );
        set_transient( $ip_key, 1, $rate_seconds );

        // call API
        $res = SG365_CID_API::request_cid( $iid );

        if ( ! $res['success'] ) {
            // log error optionally
            SG365_CID_Logger::log_error( array( 'order_id' => $order_id, 'user_id' => $order->get_user_id(), 'email' => $email, 'product_ids' => self::order_product_ids( $order ), 'iid' => $iid, 'api_response' => $res['raw'], 'status' => 'error', 'ip' => $ip ) );
            wp_send_json( array( 'error' => $res['error'], 'step' => 'API request failed' ) );
        }

        // success: decrement remaining and log
        $remaining_after = max( 0, $remaining - 1 );
        update_post_meta( $order_id, '_sg365_cid_remaining', $remaining_after );

        SG365_CID_Logger::log_success( array( 'order_id' => $order_id, 'user_id' => $order->get_user_id(), 'email' => $email, 'product_ids' => self::order_product_ids( $order ), 'iid' => $iid, 'cid' => $res['cid'], 'api_response' => $res['raw'], 'status' => 'success', 'ip' => $ip ) );

        wp_send_json( array( 'step' => 'Almost done...', 'cid' => $res['cid'], 'remaining' => $remaining_after ) );
    }

    public static function order_product_ids( $order ) {
        $ids = array();
        foreach ( $order->get_items() as $item ) {
            $ids[] = $item->get_product_id();
        }
        return $ids;
    }
}
