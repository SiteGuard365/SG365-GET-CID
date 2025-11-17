<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SG365_CID_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_init', array( __CLASS__, 'ensure_token_table' ) );
        add_action( 'init', array( __CLASS__, 'maybe_schedule_license_cron' ) );
        add_action( 'sg365_cid_weekly_license_check', array( __CLASS__, 'cron_verify_license' ) );
        add_action( 'admin_post_sg365_cid_verify_license', array( __CLASS__, 'handle_license_form' ) );
        add_action( 'admin_post_sg365_cid_create_token', array( __CLASS__, 'handle_create_token' ) );
        add_action( 'admin_post_sg365_cid_token_action', array( __CLASS__, 'handle_token_action' ) );

        add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_field' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_field' ) );
        add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_field' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_field' ), 10, 2 );

        add_action( 'add_meta_boxes', array( __CLASS__, 'order_meta_box' ) );
        add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_admin_order_summary' ), 20, 1 );

        add_action( 'wp_ajax_sg365_admin_generate_cid', array( __CLASS__, 'ajax_admin_generate_cid' ) );
        add_action( 'admin_post_sg365_delete_logs', array( __CLASS__, 'handle_delete_logs' ) );
    }

    public static function admin_menu() {
        add_menu_page( 'SG365 CID', 'SG365 CID', 'manage_options', 'sg365-cid-dashboard', array( __CLASS__, 'dashboard_page' ), 'dashicons-admin-network', 56 );
        add_submenu_page( 'sg365-cid-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'sg365-cid-dashboard', array( __CLASS__, 'dashboard_page' ) );
        add_submenu_page( 'sg365-cid-dashboard', 'SG365 CID Logs', 'Logs', 'manage_options', 'sg365-cid-logs', array( __CLASS__, 'logs_page' ) );
        add_submenu_page( 'sg365-cid-dashboard', 'Settings', 'Settings', 'manage_options', 'sg365-cid-settings', array( __CLASS__, 'settings_page' ) );
        add_submenu_page( 'sg365-cid-dashboard', 'License', 'License', 'manage_options', 'sg365-cid-license', array( __CLASS__, 'license_page' ) );

        if ( self::license_is_business() ) {
            add_submenu_page( 'sg365-cid-dashboard', 'Create Token', 'Create Token', 'manage_options', 'sg365-cid-token-create', array( __CLASS__, 'token_create_page' ) );
            add_submenu_page( 'sg365-cid-dashboard', 'Token Management', 'Token Management', 'manage_options', 'sg365-cid-token-manage', array( __CLASS__, 'token_manage_page' ) );
        }
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
        add_settings_field( 'get_cid_page_url', 'Get CID page link', array( __CLASS__, 'field_get_cid_page' ), 'sg365-cid-settings', 'sg365_cid_main' );
        add_settings_field( 'show_order_detail_data', 'Show data in order details', array( __CLASS__, 'field_show_order_details' ), 'sg365-cid-settings', 'sg365_cid_main' );
    }

    public static function sanitize_options( $in ) {
        $current = self::get_opts();
        $out = array();
        $out['api_key'] = sanitize_text_field( $in['api_key'] ?? '' );
        $out['primary_api'] = esc_url_raw( $current['primary_api'] ?? 'https://pidkey.com/ajax/cidms_api' );
        $out['fallback_api'] = esc_url_raw( $current['fallback_api'] ?? 'https://khoatoantin.com/ajax/cidms_api' );
        $out['timeout'] = intval( $in['timeout'] ?? 110 );
        $out['rate_limit_seconds'] = intval( $in['rate_limit_seconds'] ?? 30 );
        $out['allow_guests'] = ! empty( $in['allow_guests'] ) ? 1 : 0;
        $out['logs_retention'] = intval( $in['logs_retention'] ?? 365 );
        $out['enable_captcha'] = ! empty( $in['enable_captcha'] ) ? 1 : 0;
        $out['get_cid_page_url'] = esc_url_raw( $in['get_cid_page_url'] ?? '' );
        $out['show_order_detail_data'] = ! empty( $in['show_order_detail_data'] ) ? 1 : 0;
        return $out;
    }

    public static function get_opts() {
        return get_option( SG365_CID_OPTION, array() );
    }

    public static function get_license() {
        $defaults = array(
            'license_key' => '',
            'plan'        => 'free',
            'status'      => 'inactive',
            'data'        => array(),
            'checked_at'  => '',
        );
        $license = get_option( SG365_CID_LICENSE_OPTION, array() );
        return wp_parse_args( is_array( $license ) ? $license : array(), $defaults );
    }

    public static function ensure_token_table() {
        if ( ! class_exists( 'SG365_CID_Tokens' ) ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . SG365_CID_TOKEN_TABLE;
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            SG365_CID_Tokens::create_table();
        }
    }

    public static function maybe_schedule_license_cron() {
        $license = self::get_license();
        if ( empty( $license['license_key'] ) ) {
            self::clear_license_schedule();
            return;
        }
        if ( ! wp_next_scheduled( 'sg365_cid_weekly_license_check' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'sg365_cid_weekly_license_check' );
        }
    }

    public static function cron_verify_license() {
        $license = self::get_license();
        $key = $license['license_key'] ?? '';
        if ( empty( $key ) ) {
            self::clear_license_schedule();
            return;
        }
        $result = self::verify_license_remote( $key );
        if ( is_wp_error( $result ) ) {
            self::store_inactive_license_record( $key, $result->get_error_message() );
            return;
        }
        update_option( SG365_CID_LICENSE_OPTION, $result );
    }

    public static function license_status() {
        $license = self::get_license();
        return strtolower( $license['status'] ?? 'inactive' );
    }

    public static function license_is_active() {
        return 'active' === self::license_status();
    }

    public static function license_plan() {
        $license = self::get_license();
        return $license['plan'] ?? 'free';
    }

    public static function license_is_premium() {
        if ( ! self::license_is_active() ) {
            return false;
        }
        $plan = self::license_plan();
        return in_array( $plan, array( 'premium', 'business' ), true );
    }

    public static function license_is_business() {
        return self::license_is_active() && self::license_plan() === 'business';
    }
    /* fields */
    public static function field_api_key() {
        $o = self::get_opts();
        printf( '<input type="password" size="60" name="%s[api_key]" value="%s" class="regular-text" autocomplete="off" />', SG365_CID_OPTION, esc_attr( $o['api_key'] ?? '' ) );
    }
    public static function field_primary_api() {
        $o = self::get_opts();
        printf( '<input type="text" size="60" name="%s[primary_api]" value="%s" class="regular-text" readonly disabled /> <em>Managed automatically</em>', SG365_CID_OPTION, esc_attr( $o['primary_api'] ?? '' ) );
    }
    public static function field_fallback_api() {
        $o = self::get_opts();
        printf( '<input type="text" size="60" name="%s[fallback_api]" value="%s" class="regular-text" readonly disabled /> <em>Managed automatically</em>', SG365_CID_OPTION, esc_attr( $o['fallback_api'] ?? '' ) );
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
        printf( '<input type="checkbox" name="%s[allow_guests]" value="1" %s /> Allow users to see the Get CID form (disable to hide the form when needed).', SG365_CID_OPTION, checked( 1, $o['allow_guests'] ?? 0, false ) );
    }
    public static function field_logs_retention() {
        $o = self::get_opts();
        printf( '<input type="number" min="0" max="3650" name="%s[logs_retention]" value="%d" /> days (0 = keep forever)', SG365_CID_OPTION, intval( $o['logs_retention'] ?? 365 ) );
    }
    public static function field_enable_captcha() {
        $o = self::get_opts();
        printf( '<input type="checkbox" name="%s[enable_captcha]" value="1" %s /> Enable math captcha for frontend CID requests (show random math questions).', SG365_CID_OPTION, checked( 1, $o['enable_captcha'] ?? 0, false ) );
    }

    public static function field_get_cid_page() {
        $o = self::get_opts();
        printf( '<input type="url" size="60" name="%s[get_cid_page_url]" value="%s" class="regular-text" placeholder="https://example.com/get-cid" />', SG365_CID_OPTION, esc_attr( $o['get_cid_page_url'] ?? '' ) );
        echo '<p class="description">Used for buttons on the thank you page so customers can jump straight to the generator.</p>';
    }

    public static function field_show_order_details() {
        $o = self::get_opts();
        printf( '<input type="checkbox" name="%s[show_order_detail_data]" value="1" %s /> Show allowance data inside the WooCommerce order details.', SG365_CID_OPTION, checked( 1, $o['show_order_detail_data'] ?? 0, false ) );
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

    public static function dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $seven  = SG365_CID_Logger::count_success_since_days( 7 );
        $thirty = SG365_CID_Logger::count_success_since_days( 30 );
        $half   = SG365_CID_Logger::count_success_since_days( 180 );
        $license = self::get_license();
        ?>
        <div class="wrap">
            <h1>SG365 CID Dashboard</h1>
            <div class="sg365-metrics" style="display:flex;gap:20px;flex-wrap:wrap;">
                <?php self::dashboard_card( 'Last 7 days', $seven ); ?>
                <?php self::dashboard_card( 'Last 30 days', $thirty ); ?>
                <?php self::dashboard_card( 'Last 6 months', $half ); ?>
            </div>

            <h2 style="margin-top:40px;">License snapshot</h2>
            <p><strong>Plan:</strong> <?php echo esc_html( ucfirst( self::license_plan() ) ); ?> &mdash; <strong>Status:</strong> <?php echo esc_html( $license['status'] ?? 'inactive' ); ?><?php if ( ! empty( $license['data']['expiry'] ) ): ?> &mdash; <strong>Expires:</strong> <?php echo esc_html( $license['data']['expiry'] ); ?><?php endif; ?></p>

            <h2>Plugin quick instructions</h2>
            <ol>
                <li>Mark each product or variation that should grant CID limits using the product checkbox.</li>
                <li>Completed or processing orders automatically inherit the allowance equal to the purchased quantity.</li>
                <li>Use the Logs tab to review the last 20 CID requests per page or bulk delete older entries.</li>
                <li>Configure the Get CID page link plus access rules from the Settings tab.</li>
                <li>Upgrade with a premium/business license to unlock order detail summaries and token automation.</li>
            </ol>
        </div>
        <?php
    }

    protected static function dashboard_card( $label, $value ) {
        printf( '<div style="flex:1 1 200px;background:#fff;padding:20px;border:1px solid #e5e5e5;border-radius:4px;">'
            . '<h3 style="margin-top:0;">%s</h3><p style="font-size:32px;margin:0;">%s</p></div>',
            esc_html( $label ),
            esc_html( number_format_i18n( $value ) )
        );
    }

    public static function license_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $license = self::get_license();
        $status = self::license_status();
        $status_msg = '';
        if ( isset( $_GET['license_status'] ) ) {
            if ( 'success' === $_GET['license_status'] ) {
                $status_msg = '<div class="notice notice-success"><p>License verified successfully.</p></div>';
            } elseif ( 'warning' === $_GET['license_status'] ) {
                $status_msg = '<div class="notice notice-warning"><p>The license was synced, but the server marked it as ' . esc_html( $status ) . '. Paid features remain disabled until the status returns to active.</p></div>';
            } elseif ( 'error' === $_GET['license_status'] ) {
                $error = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : 'Unable to verify license.';
                $status_msg = '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
            }
        }

        $notices = array();
        if ( in_array( $status, array( 'pending_activation', 'activated' ), true ) ) {
            $notices[] = array( 'type' => 'warning', 'text' => __( 'This key still needs to be bound to the current domain. Please open https://www.siteguard365.com/contact-us to bind or reset the domain for this license.', 'sg365-cid' ) );
        }
        if ( 'expired' === $status ) {
            $notices[] = array( 'type' => 'error', 'text' => __( 'This license expired. Premium or Business features are disabled until you renew the key at https://www.siteguard365.com/products.', 'sg365-cid' ) );
        }
        if ( in_array( $status, array( 'suspended', 'deleted' ), true ) ) {
            $notices[] = array( 'type' => 'error', 'text' => __( 'This license is suspended. Please contact https://www.siteguard365.com/contact-us for assistance or to request a domain reset.', 'sg365-cid' ) );
        }
        if ( $status && 'active' !== $status && empty( $notices ) ) {
            $notices[] = array( 'type' => 'warning', 'text' => __( 'Paid features stay disabled until the license status returns to active.', 'sg365-cid' ) );
        }

        $input_type = self::license_is_active() ? 'password' : 'text';
        ?>
        <div class="wrap">
            <h1>SG365 CID License</h1>
            <?php echo wp_kses_post( $status_msg ); ?>
            <?php foreach ( $notices as $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
            <?php endforeach; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sg365_cid_license' ); ?>
                <input type="hidden" name="action" value="sg365_cid_verify_license" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sg365_license_key">License key</label></th>
                        <td>
                            <input type="<?php echo esc_attr( $input_type ); ?>" id="sg365_license_key" name="license_key" class="regular-text" value="<?php echo esc_attr( $license['license_key'] ?? '' ); ?>" placeholder="SG365-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off" />
                            <p class="description">Enter your Premium or Business key and click Verify to sync with Site Guard 365.<?php if ( self::license_is_active() ) : ?> Current key: <?php echo esc_html( self::mask_license_key( $license['license_key'] ?? '' ) ); ?>.<?php endif; ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Verify License' ); ?>
            </form>

            <h2>Current license status</h2>
            <p><strong>Plan:</strong> <?php echo esc_html( ucfirst( self::license_plan() ) ); ?><br>
            <strong>Status:</strong> <?php echo esc_html( $license['status'] ?? 'inactive' ); ?><br>
            <?php if ( ! empty( $license['data']['activation'] ) ): ?><strong>Activated:</strong> <?php echo esc_html( $license['data']['activation'] ); ?><br><?php endif; ?>
            <?php if ( ! empty( $license['data']['expiry'] ) ): ?><strong>Expiry date:</strong> <?php echo esc_html( $license['data']['expiry'] ); ?><br><?php endif; ?>
            <?php if ( isset( $license['data']['months_remaining'] ) && '' !== $license['data']['months_remaining'] ): ?><strong>Months remaining:</strong> <?php echo esc_html( $license['data']['months_remaining'] ); ?><br><?php endif; ?>
            <?php if ( ! empty( $license['data']['product_id'] ) ): ?><strong>Product ID:</strong> <?php echo esc_html( $license['data']['product_id'] ); ?><br><?php endif; ?>
            <strong>Last checked:</strong> <?php echo esc_html( $license['checked_at'] ?? 'never' ); ?></p>
            <?php if ( ! empty( $license['data']['message'] ) ): ?>
                <p><strong>Server message:</strong> <?php echo esc_html( $license['data']['message'] ); ?></p>
            <?php endif; ?>

            <div class="notice notice-info" style="margin-top:20px;">
                <p><?php esc_html_e( 'The plugin re-checks your license automatically every week (daily schedule) and each time you click Verify License, so suspended or expired keys are revoked quickly.', 'sg365-cid' ); ?></p>
            </div>
            <p>
                <?php esc_html_e( 'Need to renew, upgrade, or reset the domain assigned to this key?', 'sg365-cid' ); ?>
                <a href="https://www.siteguard365.com/products" target="_blank" rel="noopener">siteguard365.com/products</a>
                <?php esc_html_e( 'or', 'sg365-cid' ); ?>
                <a href="https://www.siteguard365.com/contact-us" target="_blank" rel="noopener">siteguard365.com/contact-us</a>.
            </p>
        </div>
        <?php
    }

    /* Logs admin page */
    public static function logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 20;
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
                            <td><code><?php echo esc_html( $r->iid ); ?></code></td>
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

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
                <?php wp_nonce_field( 'sg365_delete_logs', 'sg365_delete_logs_nonce' ); ?>
                <input type="hidden" name="action" value="sg365_delete_logs" />
                <fieldset>
                    <legend><strong>Bulk delete logs</strong></legend>
                    <p>Remove every log created before:</p>
                    <button class="button" name="delete_range" value="30" onclick="return confirm('Delete logs older than 1 month?');">1 month</button>
                    <button class="button" name="delete_range" value="90" onclick="return confirm('Delete logs older than 3 months?');">3 months</button>
                    <button class="button" name="delete_range" value="180" onclick="return confirm('Delete logs older than 6 months?');">6 months</button>
                </fieldset>
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
        if ( ! empty( $_POST['delete_range'] ) ) {
            SG365_CID_Logger::delete_logs_before_days( intval( $_POST['delete_range'] ) );
        }
        wp_redirect( admin_url( 'admin.php?page=sg365-cid-logs' ) );
        exit;
    }

    public static function handle_license_form() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'sg365_cid_license' );
        $key = isset( $_POST['license_key'] ) ? self::normalize_license_key( wp_unslash( $_POST['license_key'] ) ) : '';
        $current = self::get_license();

        if ( empty( $key ) ) {
            self::store_inactive_license_record( '', '' );
            self::clear_license_schedule();
            wp_safe_redirect( admin_url( 'admin.php?page=sg365-cid-license&license_status=success' ) );
            exit;
        }

        $is_new_key = empty( $current['license_key'] ) || $current['license_key'] !== $key;
        if ( $is_new_key ) {
            self::store_inactive_license_record( $key, __( 'Awaiting verification', 'sg365-cid' ) );
        }

        $result = self::verify_license_remote( $key );
        if ( is_wp_error( $result ) ) {
            $msg = rawurlencode( $result->get_error_message() );
            self::store_inactive_license_record( $key, $result->get_error_message() );
            wp_safe_redirect( admin_url( 'admin.php?page=sg365-cid-license&license_status=error&message=' . $msg ) );
            exit;
        }

        update_option( SG365_CID_LICENSE_OPTION, $result );
        self::maybe_schedule_license_cron();
        $status_flag = ( 'active' === strtolower( $result['status'] ?? '' ) ) ? 'success' : 'warning';
        wp_safe_redirect( admin_url( 'admin.php?page=sg365-cid-license&license_status=' . $status_flag ) );
        exit;
    }

    protected static function verify_license_remote( $key ) {
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        $response = wp_remote_post( 'https://pro.siteguard365.com/wp-json/site-guard-pro/v1/verify', array(
            'timeout' => 20,
            'body'    => array(
                'license' => $key,
                'domain'  => $domain,
                'product_id' => (int) SG365_CID_PRODUCT_ID,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'sg365_license_http', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 200 !== $code || empty( $body ) || empty( $body['status'] ) ) {
            return new WP_Error( 'sg365_license_invalid', __( 'Invalid response from license server', 'sg365-cid' ) );
        }

        if ( ! empty( $body['product_id'] ) && (int) $body['product_id'] !== (int) SG365_CID_PRODUCT_ID ) {
            return new WP_Error( 'sg365_product_mismatch', __( 'This license belongs to a different Site Guard 365 product. Please enter the key assigned to SG365 CID.', 'sg365-cid' ) );
        }

        $plan = ! empty( $body['plan'] ) ? strtolower( $body['plan'] ) : self::derive_plan_from_key( $key );
        $status = strtolower( $body['status'] ?? 'inactive' );

        $data = array(
            'activation'        => $body['activation'] ?? '',
            'expiry'            => $body['expiry'] ?? '',
            'plan'              => $body['plan'] ?? '',
            'product_id'        => $body['product_id'] ?? '',
            'months_remaining'  => isset( $body['months_remaining'] ) ? $body['months_remaining'] : '',
            'message'           => $body['message'] ?? '',
            'status'            => $body['status'] ?? '',
        );

        return array(
            'license_key' => $key,
            'plan'        => $plan,
            'status'      => $status,
            'data'        => $data,
            'checked_at'  => current_time( 'mysql' ),
        );
    }

    protected static function derive_plan_from_key( $key ) {
        if ( preg_match( '/^SG365\-B/i', $key ) || preg_match( '/^SG365\-B[A-Z0-9]+/i', $key ) ) {
            return 'business';
        }
        return 'premium';
    }

    protected static function normalize_license_key( $key ) {
        $key = strtoupper( trim( (string) $key ) );
        $key = preg_replace( '/[^A-Z0-9\-]/', '', $key );
        return $key;
    }

    protected static function mask_license_key( $key ) {
        if ( empty( $key ) ) {
            return '';
        }
        if ( stripos( $key, 'SG365-' ) === 0 ) {
            return 'SG365-XXXXX-XXXXX-XXXXX-XXXXX';
        }
        return str_repeat( 'X', min( 10, strlen( $key ) ) );
    }

    protected static function store_inactive_license_record( $key, $message = '' ) {
        $data = array();
        if ( ! empty( $message ) ) {
            $data['message'] = $message;
        }
        update_option( SG365_CID_LICENSE_OPTION, array(
            'license_key' => $key,
            'plan'        => 'free',
            'status'      => 'inactive',
            'data'        => $data,
            'checked_at'  => current_time( 'mysql' ),
        ) );
    }

    protected static function clear_license_schedule() {
        wp_clear_scheduled_hook( 'sg365_cid_weekly_license_check' );
    }

    public static function token_create_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>Create Business Token</h1>
            <?php if ( ! self::license_is_business() ): ?>
                <div class="notice notice-warning"><p>Activate a Business license to create tokens.</p></div>
            <?php else: ?>
                <?php if ( isset( $_GET['status'] ) && 'created' === $_GET['status'] ): ?>
                    <div class="notice notice-success"><p>Token created successfully.</p></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'sg365_cid_token_create' ); ?>
                    <input type="hidden" name="action" value="sg365_cid_create_token" />
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="sg365_token_label">Token label</label></th>
                            <td><input type="text" id="sg365_token_label" name="token_label" class="regular-text" placeholder="Internal name" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sg365_token_string">Custom token</label></th>
                            <td><input type="text" id="sg365_token_string" name="token_string" class="regular-text" placeholder="Leave blank to auto-generate" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sg365_token_email">Email</label></th>
                            <td><input type="email" id="sg365_token_email" name="token_email" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sg365_token_limit">Token limit</label></th>
                            <td><input type="number" id="sg365_token_limit" name="token_limit" min="1" value="1" /> requests</td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sg365_token_expiry">Token expiry (months)</label></th>
                            <td><input type="number" id="sg365_token_expiry" name="token_expiry" min="0" value="0" /> <span class="description">0 = lifetime</span></td>
                        </tr>
                    </table>
                    <?php submit_button( 'Create token' ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function token_manage_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>Token Management</h1>
            <?php if ( ! self::license_is_business() ): ?>
                <div class="notice notice-warning"><p>Business license required.</p></div>
            <?php else: ?>
                <?php
                $search = sanitize_text_field( $_GET['s'] ?? '' );
                $paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
                $per_page = 20;
                $tokens = SG365_CID_Tokens::get_tokens( array( 'search' => $search, 'paged' => $paged, 'per_page' => $per_page ) );
                $total_pages = ceil( $tokens['total'] / $per_page );
                ?>
                <form method="get">
                    <input type="hidden" name="page" value="sg365-cid-token-manage" />
                    <p class="search-box">
                        <label class="screen-reader-text" for="sg365-token-search">Search Tokens</label>
                        <input type="search" id="sg365-token-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search token, email or label" />
                        <button class="button">Search</button>
                    </p>
                </form>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Label</th>
                            <th>Email</th>
                            <th>Limit</th>
                            <th>Used</th>
                            <th>Remaining</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $tokens['rows'] ) ): ?>
                            <tr><td colspan="9">No tokens found.</td></tr>
                        <?php else: foreach ( $tokens['rows'] as $token ): ?>
                            <tr>
                                <td><code><?php echo esc_html( $token->token ); ?></code></td>
                                <td><?php echo esc_html( $token->name ); ?></td>
                                <td><?php echo esc_html( $token->email ); ?></td>
                                <td><?php echo intval( $token->limit_total ); ?></td>
                                <td><?php echo intval( $token->used ); ?></td>
                                <td><?php echo max( 0, intval( $token->limit_total ) - intval( $token->used ) ); ?></td>
                                <td><?php echo $token->expiry_date ? esc_html( $token->expiry_date ) : '<em>Lifetime</em>'; ?></td>
                                <td><?php echo esc_html( ucfirst( $token->status ) ); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                        <?php wp_nonce_field( 'sg365_cid_token_action' ); ?>
                                        <input type="hidden" name="action" value="sg365_cid_token_action" />
                                        <input type="hidden" name="token_id" value="<?php echo esc_attr( $token->id ); ?>" />
                                        <input type="hidden" name="redirect" value="<?php echo esc_attr( admin_url( 'admin.php?page=sg365-cid-token-manage&paged=' . $paged ) ); ?>" />
                                        <?php if ( 'suspended' === $token->status ): ?>
                                            <button class="button" name="token_action" value="activate">Activate</button>
                                        <?php else: ?>
                                            <button class="button" name="token_action" value="suspend">Suspend</button>
                                        <?php endif; ?>
                                        <button class="button-link-delete" name="token_action" value="delete" onclick="return confirm('Delete this token?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php if ( $total_pages > 1 ): ?>
                    <div class="tablenav"><div class="tablenav-pages">
                        <?php
                        echo paginate_links( array(
                            'base' => add_query_arg( array( 'page' => 'sg365-cid-token-manage', 's' => $search, 'paged' => '%#%' ), admin_url( 'admin.php' ) ),
                            'format' => '',
                            'total' => $total_pages,
                            'current' => $paged,
                        ) );
                        ?>
                    </div></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_create_token() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'sg365_cid_token_create' );
        if ( ! self::license_is_business() ) wp_die( 'Business license required.' );
        $label = sanitize_text_field( wp_unslash( $_POST['token_label'] ?? '' ) );
        $token_string = sanitize_text_field( wp_unslash( $_POST['token_string'] ?? '' ) );
        $email = sanitize_email( wp_unslash( $_POST['token_email'] ?? '' ) );
        $limit = max( 1, intval( $_POST['token_limit'] ?? 1 ) );
        $expiry_months = max( 0, intval( $_POST['token_expiry'] ?? 0 ) );
        if ( empty( $email ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=sg365-cid-token-create&status=error&message=email' ) );
            exit;
        }
        $expiry_date = $expiry_months > 0 ? date( 'Y-m-d', strtotime( '+' . $expiry_months . ' months' ) ) : null;

        SG365_CID_Tokens::create_token( array(
            'token'       => $token_string,
            'name'        => $label,
            'email'       => $email,
            'limit_total' => $limit,
            'expiry_date' => $expiry_date,
        ) );

        wp_safe_redirect( admin_url( 'admin.php?page=sg365-cid-token-create&status=created' ) );
        exit;
    }

    public static function handle_token_action() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'sg365_cid_token_action' );
        if ( ! self::license_is_business() ) wp_die( 'Business license required.' );
        $token_id = intval( $_POST['token_id'] ?? 0 );
        $action = sanitize_text_field( wp_unslash( $_POST['token_action'] ?? '' ) );
        $redirect = isset( $_POST['redirect'] ) ? esc_url_raw( $_POST['redirect'] ) : admin_url( 'admin.php?page=sg365-cid-token-manage' );

        if ( $token_id ) {
            switch ( $action ) {
                case 'suspend':
                    SG365_CID_Tokens::update_status( $token_id, 'suspended' );
                    break;
                case 'activate':
                    SG365_CID_Tokens::update_status( $token_id, 'active' );
                    break;
                case 'delete':
                    SG365_CID_Tokens::delete_token( $token_id );
                    break;
            }
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function variation_field( $loop, $variation_data, $variation ) {
        woocommerce_wp_checkbox( array(
            'id'            => '_sg365_enable_cid_' . $variation->ID,
            'label'         => __( 'Enable CID limit for this variation', 'sg365-cid' ),
            'value'         => get_post_meta( $variation->ID, '_sg365_enable_cid', true ),
            'cbvalue'       => 'yes',
        ) );
    }

    public static function save_variation_field( $variation_id ) {
        $val = isset( $_POST[ '_sg365_enable_cid_' . $variation_id ] ) ? 'yes' : 'no';
        update_post_meta( $variation_id, '_sg365_enable_cid', $val );
    }

    public static function render_admin_order_summary( $order ) {
        if ( ! self::license_is_premium() ) return;
        $opts = self::get_opts();
        if ( empty( $opts['show_order_detail_data'] ) ) return;
        if ( ! class_exists( 'WC_Order' ) || ! $order instanceof WC_Order ) return;
        $order_id = $order->get_id();
        $remaining = max( 0, intval( get_post_meta( $order_id, '_sg365_cid_remaining', true ) ) );
        $generated = SG365_CID_Logger::count_success_for_order( $order_id );
        $allocated = $remaining + $generated;
        if ( $allocated <= 0 ) return;
        $logs_link = $generated ? add_query_arg( array( 'page' => 'sg365-cid-logs', 's' => $order_id ), admin_url( 'admin.php' ) ) : '';
        echo '<div class="sg365-order-meta" style="margin:10px 0;padding:10px;border:1px solid #ddd;">';
        echo '<strong>Confirmation ID applied to this order ' . intval( $allocated ) . '.</strong><br>';
        echo 'CID generated ' . intval( $generated );
        if ( $logs_link ) {
            echo ' (<a href="' . esc_url( $logs_link ) . '">view logs</a>)';
        }
        echo '. CID pending ' . intval( $remaining ) . '.';
        echo '</div>';
    }
}
