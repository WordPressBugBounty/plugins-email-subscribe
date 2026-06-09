<?php
/*
  Plugin Name: Email Subscription Popup
  Plugin URI:https://www.i13websolution.com/product/wordpress-newsletter-subscription-pro-plugin/
  Author URI:https://www.i13websolution.com/
  Description: This is beautiful email subscription modal popup plugin for wordpress.Each time new user visit your site user will see modal popup for email subscription.Even you can setup email subscription form by widget.
  Author:I Thirteen Web Solution
  Version:1.2.28
  Text Domain:email-subscribe
  Domain Path: /languages
 */

// ── Free version limits & PRO upsell ─────────────────────────────────────────
define('I13_ES_FREE_SUBSCRIBER_LIMIT', 500);
define('I13_ES_FREE_IMPORT_LIMIT',     100);
define('I13_ES_PRO_URL', 'https://i13websolution.com/product/wordpress-newsletter-subscription-pro-plugin/');

add_action('admin_menu', 'email_subscription_popup_admin_menu');
//add_action( 'admin_init', 'email_subscription_popup_admin_admin_init' );
register_activation_hook(__FILE__, 'install_email_subscription_popup_admin');
register_deactivation_hook(__FILE__, 'es_email_subscribe_remove_access_capabilities');
add_action('admin_notices', 'i13_es_free_admin_notices');
add_action('admin_notices', 'i13_es_review_reminder_notice');
add_action('wp_ajax_i13_es_dismiss_review',      'i13_es_dismiss_review');
add_action('wp_ajax_i13_es_dismiss_upgrade',     'i13_es_dismiss_upgrade');
add_action('wp_ajax_i13_es_dismiss_onboarding',  'i13_es_dismiss_onboarding');
add_action('wp_ajax_i13_es_mailchimp_save',      'i13_es_mailchimp_save');
add_action('wp_ajax_i13_es_mailchimp_test',      'i13_es_mailchimp_test');
add_action('admin_init',                         'i13_es_maybe_redirect_onboarding');
add_action('wp_enqueue_scripts', 'email_subscription_popup_load_styles_and_js');
if (!is_admin()) {
    add_action('wp_footer', 'addModalPopupHtmlToWpFooter');
}
add_action('wp_head', 'unsubscribe_user_func');
add_action('wp_ajax_getEmailTemplate', 'getEmailTemplate');
add_action('widgets_init', 'nksnewslettersubscriberSet');
add_action('wp_ajax_store_email', 'store_email_callback');
add_action('wp_ajax_nopriv_store_email', 'store_email_callback');
add_action('plugins_loaded', 'load_lang_for_email_subscription_popup');
add_filter('widget_text', 'do_shortcode');
add_filter('wp_default_editor', 'force_default_editor_email_subscriber');
add_filter('user_has_cap', 'es_email_subscribe_admin_cap_list', 10, 4);
add_shortcode('print_email_subscribe_form', 'print_email_subscribe_form_func');

function force_default_editor_email_subscriber() {
    //allowed: tinymce, html
    return 'tinymce';
}

// ── Review reminder notice ────────────────────────────────────────────────────
function i13_es_review_reminder_notice() {
    // Only show on plugin pages
    $screen = get_current_screen();
    if ( !$screen || strpos($screen->id, 'email_subscription') === false ) return;

    // Don't show if dismissed
    if ( get_option('i13_es_review_dismissed') ) return;

    // Show after 30 days AND 50+ subscribers
    // In WP_DEBUG mode: show after 1 day and 1 subscriber (for testing)
    $activated_on = (int) get_option('i13_es_activated_on', time());
    $days_active  = (time() - $activated_on) / DAY_IN_SECONDS;
    $min_days = ( defined('WP_DEBUG') && WP_DEBUG ) ? 0 : 30;
    $min_subs = ( defined('WP_DEBUG') && WP_DEBUG ) ? 1 : 50;
    if ( $days_active < $min_days ) return;

    global $wpdb;
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nl_subscriptions WHERE is_subscribed=1");
    if ( $count < $min_subs ) return;

    $review_url = 'https://wordpress.org/support/plugin/email-subscribe/reviews/#new-post';
    ?>
    <div class="notice notice-success" id="i13-es-review-notice" style="border-left-color:#00a32a;padding:12px 16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <span style="font-size:24px;">⭐</span>
        <div style="flex:1;">
            <strong><?php _e('Enjoying Email Subscribe?', 'email-subscribe'); ?></strong><br>
            <?php printf(
                __('You have <strong>%d subscribers</strong> — that is awesome! Please take a moment to leave a review on WordPress.org. It helps us keep the plugin free and improve it!', 'email-subscribe'),
                $count
            ); ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo esc_url($review_url); ?>" target="_blank" class="button button-primary" onclick="i13EsDismissReview();">
                ⭐ <?php _e('Leave a Review', 'email-subscribe'); ?>
            </a>
            <a href="#" class="button" onclick="i13EsDismissReview();return false;">
                <?php _e('Already did!', 'email-subscribe'); ?>
            </a>
            <a href="#" style="color:#888;font-size:12px;align-self:center;" onclick="i13EsDismissReview();return false;">
                <?php _e('Dismiss', 'email-subscribe'); ?>
            </a>
        </div>
    </div>
    <script>
    function i13EsDismissReview(){
        document.getElementById('i13-es-review-notice').style.display='none';
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=i13_es_dismiss_review&nonce=<?php echo wp_create_nonce('i13_es_dismiss'); ?>'
        });
    }
    </script>
    <?php
}

function i13_es_dismiss_review() {
    check_ajax_referer('i13_es_dismiss', 'nonce');
    update_option('i13_es_review_dismissed', 1);
    wp_send_json_success();
}

function i13_es_dismiss_upgrade() {
    check_ajax_referer('i13_es_dismiss', 'nonce');
    update_option('i13_es_upgrade_dismissed', time());
    wp_send_json_success();
}

function i13_es_free_admin_notices() {
    global $wpdb;
    $screen = get_current_screen();
    if ( !$screen || strpos($screen->id, 'email_subscription') === false ) return;

    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nl_subscriptions WHERE is_subscribed=1");

    // Subscriber limit warning
    if ( $count >= I13_ES_FREE_SUBSCRIBER_LIMIT ) {
        echo '<div class="notice notice-warning"><p>';
        printf(
            __('<strong>Email Subscribe:</strong> You have <strong>%d active subscribers</strong> — you have reached the free version limit of %d. <a href="%s" target="_blank" style="color:#d63638;font-weight:600;">Upgrade to PRO</a> for unlimited subscribers, more popup styles, ESP integrations and more!', 'email-subscribe'),
            $count, I13_ES_FREE_SUBSCRIBER_LIMIT, I13_ES_PRO_URL
        );
        echo '</p></div>';
    } elseif ( $count >= (I13_ES_FREE_SUBSCRIBER_LIMIT * 0.8) ) {
        // 80% warning
        echo '<div class="notice notice-info"><p>';
        printf(
            __('<strong>Email Subscribe:</strong> You have <strong>%d active subscribers</strong> (%d%% of the %d free limit). <a href="%s" target="_blank">Upgrade to PRO</a> for unlimited subscribers!', 'email-subscribe'),
            $count, round($count/I13_ES_FREE_SUBSCRIBER_LIMIT*100), I13_ES_FREE_SUBSCRIBER_LIMIT, I13_ES_PRO_URL
        );
        echo '</p></div>';
    }
}

function load_lang_for_email_subscription_popup() {

    load_plugin_textdomain('email-subscribe', false, basename(dirname(__FILE__)) . '/languages/');
    add_filter('map_meta_cap', 'map_es_email_subscribe_meta_caps', 10, 4);
}

function es_email_subscribe_admin_cap_list($allcaps, $caps, $args, $user) {


    if (!in_array('administrator', $user->roles)) {

        return $allcaps;
    } else {

        if (!isset($allcaps['es_email_subscribe_settings'])) {

            $allcaps['es_email_subscribe_settings'] = true;
        }

        if (!isset($allcaps['es_email_subscribe_view_subscribers'])) {

            $allcaps['es_email_subscribe_view_subscribers'] = true;
        }
        if (!isset($allcaps['es_email_subscribe_send_email_to_selected_subscribers'])) {

            $allcaps['es_email_subscribe_send_email_to_selected_subscribers'] = true;
        }
        if (!isset($allcaps['es_email_subscribe_send_email_to_all_subscribers'])) {

            $allcaps['es_email_subscribe_send_email_to_all_subscribers'] = true;
        }
        if (!isset($allcaps['es_email_subscribe_delete_subscribers'])) {

            $allcaps['es_email_subscribe_delete_subscribers'] = true;
        }

        if (!isset($allcaps['es_email_subscribe_view_unsubscribers'])) {

            $allcaps['es_email_subscribe_view_unsubscribers'] = true;
        }

        if (!isset($allcaps['es_email_subscribe_delete_unsubscribers'])) {

            $allcaps['es_email_subscribe_delete_unsubscribers'] = true;
        }

        if (!isset($allcaps['es_email_subscribe_re_subscriber_unsubscribers'])) {

            $allcaps['es_email_subscribe_re_subscriber_unsubscribers'] = true;
        }
        if (!isset($allcaps['es_email_subscribe_view_shortcode'])) {

            $allcaps['es_email_subscribe_view_shortcode'] = true;
        }
    }

    return $allcaps;
}

function map_es_email_subscribe_meta_caps(array $caps, $cap, $user_id, array $args) {


    if (!in_array($cap, array(
                'es_email_subscribe_settings',
                'es_email_subscribe_view_subscribers',
                'es_email_subscribe_send_email_to_selected_subscribers',
                'es_email_subscribe_send_email_to_all_subscribers',
                'es_email_subscribe_delete_subscribers',
                'es_email_subscribe_view_unsubscribers',
                'es_email_subscribe_delete_unsubscribers',
                'es_email_subscribe_re_subscriber_unsubscribers',
                'es_email_subscribe_view_shortcode',
                    ), true)) {

        return $caps;
    }




    $caps = array();

    switch ($cap) {

        case 'es_email_subscribe_settings':
            $caps[] = 'es_email_subscribe_settings';
            break;

        case 'es_email_subscribe_view_subscribers':
            $caps[] = 'es_email_subscribe_view_subscribers';
            break;

        case 'es_email_subscribe_send_email_to_selected_subscribers':
            $caps[] = 'es_email_subscribe_send_email_to_selected_subscribers';
            break;

        case 'es_email_subscribe_send_email_to_all_subscribers':
            $caps[] = 'es_email_subscribe_send_email_to_all_subscribers';
            break;

        case 'es_email_subscribe_delete_subscribers':
            $caps[] = 'es_email_subscribe_delete_subscribers';
            break;

        case 'es_email_subscribe_view_unsubscribers':
            $caps[] = 'es_email_subscribe_view_unsubscribers';
            break;

        case 'es_email_subscribe_delete_unsubscribers':
            $caps[] = 'es_email_subscribe_delete_unsubscribers';
            break;

        case 'es_email_subscribe_re_subscriber_unsubscribers':
            $caps[] = 'es_email_subscribe_re_subscriber_unsubscribers';
            break;
        
        case 'es_email_subscribe_view_shortcode':
            $caps[] = 'es_email_subscribe_view_shortcode';
            break;

        default:

            $caps[] = 'do_not_allow';
            break;
    }


    return apply_filters('es_email_subscribe_meta_caps', $caps, $cap, $user_id, $args);
}

function es_email_subscribe_add_access_capabilities() {

    // Capabilities for all roles.
    $roles = array('administrator');
    foreach ($roles as $role) {

        $role = get_role($role);
        if (empty($role)) {
            continue;
        }


        if (!$role->has_cap('es_email_subscribe_settings')) {

            $role->add_cap('es_email_subscribe_settings');
        }

        if (!$role->has_cap('es_email_subscribe_view_subscribers')) {

            $role->add_cap('es_email_subscribe_view_subscribers');
        }


        if (!$role->has_cap('es_email_subscribe_send_email_to_selected_subscribers')) {

            $role->add_cap('es_email_subscribe_send_email_to_selected_subscribers');
        }

        if (!$role->has_cap('es_email_subscribe_send_email_to_all_subscribers')) {

            $role->add_cap('es_email_subscribe_send_email_to_all_subscribers');
        }

        if (!$role->has_cap('es_email_subscribe_delete_subscribers')) {

            $role->add_cap('es_email_subscribe_delete_subscribers');
        }

        if (!$role->has_cap('es_email_subscribe_view_unsubscribers')) {

            $role->add_cap('es_email_subscribe_view_unsubscribers');
        }

        if (!$role->has_cap('es_email_subscribe_delete_unsubscribers')) {

            $role->add_cap('es_email_subscribe_delete_unsubscribers');
        }

        if (!$role->has_cap('es_email_subscribe_re_subscriber_unsubscribers')) {

            $role->add_cap('es_email_subscribe_re_subscriber_unsubscribers');
        }
        
        if (!$role->has_cap('es_email_subscribe_view_shortcode')) {

            $role->add_cap('es_email_subscribe_view_shortcode');
        }
    }

    $user = wp_get_current_user();
    $user->get_role_caps();
}

function es_email_subscribe_remove_access_capabilities() {

    global $wp_roles;

    if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }

    if (isset($wp_roles) && $wp_roles != NULL && is_object($wp_roles) && 0 > count($wp_roles->roles)) {

        foreach ($wp_roles->roles as $role => $details) {
            $role = $wp_roles->get_role($role);
            if (empty($role)) {
                continue;
            }

            $role->remove_cap('es_email_subscribe_settings');
            $role->remove_cap('es_email_subscribe_view_subscribers');
            $role->remove_cap('es_email_subscribe_send_email_to_selected_subscribers');
            $role->remove_cap('es_email_subscribe_send_email_to_all_subscribers');
            $role->remove_cap('es_email_subscribe_delete_subscribers');
            $role->remove_cap('es_email_subscribe_view_unsubscribers');
            $role->remove_cap('es_email_subscribe_delete_unsubscribers');
            $role->remove_cap('es_email_subscribe_re_subscriber_unsubscribers');
            $role->remove_cap('es_email_subscribe_view_shortcode');
        }
    }

    // Refresh current set of capabilities of the user, to be able to directly use the new caps.
    $user = wp_get_current_user();
    $user->get_role_caps();
}

function nksnewslettersubscriberSet() {

    register_widget('nksnewslettersubscriber');
}

function install_email_subscription_popup_admin() {

    // Store activation date for review reminder
    if ( ! get_option('i13_es_activated_on') ) {
        update_option('i13_es_activated_on', time());
        update_option('i13_es_show_onboarding', 1); // Show onboarding wizard on first install
    }

    global $wpdb;
    $table_name = $wpdb->prefix . "nl_subscriptions";
    $table_name2 = $wpdb->prefix . "newsletters_management";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS  " . $table_name . " (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `name` varchar(200) NOT NULL,
 `email` varchar(250) NOT NULL,
 `subscribed_on` datetime NOT NULL,
 `is_subscribed` tinyint(1) NOT NULL DEFAULT '1',
 `unsubs_key` varchar(100) NOT NULL,
 PRIMARY KEY  (id)
) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    $wp_news_letter_settings = array(
        'newsletter_show_on' => 'any',
        'newsletter_cookie' => '1',
        'heading' => 'Subscribe to our newsletter',
        'subheading' => 'Want to be notified when our article is published? Enter your email address and name below to be the first to know.',
        'email' => 'Email',
        'name' => 'Name',
        'submitbtn' => 'SIGN UP FOR NEWSLETTER NOW',
        'requiredfield' => 'This field is required.',
        'iinvalidemail' => 'Please enter valid email address.',
        'wait' => 'Please wait...',
        'invalid_request' => 'Invalid request.',
        'email_exist' => 'This email is already exist.',
        'success' => 'You have successfully subscribed to our Newsletter!',
        'outgoing_email_limit' => '150',
        'unsubscribe_message' => 'You have successfully unsubscribed from email newsletter.Thank you...',
        'show_name_field' => '1',
        'show_agreement' => '0',
        'agreement_text' => 'I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>',
        'agreement_error' => 'Please read and agree to our terms & conditions.',
        'additional_css' => '',
        'centerOnScroll' => '0'
    );

    $existingopt = get_option('wp_news_letter_settings');
    if (!is_array($existingopt)) {

        update_option('wp_news_letter_settings', $wp_news_letter_settings);
    } else {

        $flag = false;
        if (!isset($existingopt['unsubscribe_message'])) {

            $flag = true;
            $existingopt['unsubscribe_message'] = 'You have successfully unsubscribed from email newsletter.Thank you...';
        }
        if (!isset($existingopt['show_name_field'])) {

            $flag = true;
            $existingopt['show_name_field'] = '1';
        }

        if (!isset($existingopt['show_agreement'])) {

            $flag = true;
            $existingopt['show_agreement'] = '0';
        }

        if (!isset($existingopt['agreement_text'])) {

            $flag = true;
            $existingopt['agreement_text'] = 'I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>';
        }

        if (!isset($existingopt['agreement_error'])) {

            $flag = true;
            $existingopt['agreement_error'] = 'Please read and agree to our terms & conditions.';
        }

        if (!isset($existingopt['additional_css'])) {
            $flag = true;
            $existingopt['additional_css'] = '';
        }
        
        if (!isset($existingopt['centerOnScroll'])) {
            $flag = true;
            $existingopt['centerOnScroll'] = '0';
        }

        if ($flag == true) {

            update_option('wp_news_letter_settings', $existingopt);
        }
    }

    es_email_subscribe_add_access_capabilities();
}

function email_subscription_popup_admin_menu() {


    $hook_suffix = add_menu_page(__('Email Subscription', 'email-subscribe'), __('Email Subscription', 'email-subscribe'), 'es_email_subscribe_settings', 'email_subscription_popup', 'email_subscription_popup_admin_options', 'dashicons-email-alt', 26);
    $hook_suffix = add_submenu_page('email_subscription_popup', __('Email Subscription Form Setting', 'email-subscribe'), __('Email Subscription Form Setting', 'email-subscribe'), 'es_email_subscribe_settings', 'email_subscription_popup', 'email_subscription_popup_admin_options');
    $hook_suffix_subscriber = add_submenu_page('email_subscription_popup', __('Manage Subscribers', 'email-subscribe'), __('Manage Subscribers', 'email-subscribe'), 'es_email_subscribe_view_subscribers', 'email_subscription_popup_subscribers_management', 'massEmailToEmail_Subscriber_Func');
    $hook_suffix_unsubscriber = add_submenu_page('email_subscription_popup', __('Unsubscribers List', 'email-subscribe'), __('Unsubscribers List', 'email-subscribe'), 'es_email_subscribe_view_unsubscribers', 'Newssletter-Email-Unsubscriber', 'email_subscription_unsubscribers_func');
    $hook_suffix_shortcode= add_submenu_page('email_subscription_popup', __('Email Subscribe Form Shortcode', 'email-subscribe'), __('Email Subscribe Form Shortcode', 'email-subscribe'), 'es_email_subscribe_view_shortcode', 'Newssletter-Email-Shortcode', 'email_subscription_shortcode_func');
    add_submenu_page('email_subscription_popup', __('Mailchimp Sync', 'email-subscribe'), __('Mailchimp Sync', 'email-subscribe'), 'es_email_subscribe_settings', 'i13_es_mailchimp', 'i13_es_mailchimp_page');

    add_action('load-' . $hook_suffix, 'email_subscription_popup_admin_admin_init');
    add_action('load-' . $hook_suffix_subscriber, 'email_subscription_popup_admin_admin_init');
    add_action('load-' . $hook_suffix_unsubscriber, 'email_subscription_popup_admin_admin_init');
    add_action('load-' . $hook_suffix_shortcode, 'email_subscription_popup_admin_admin_init');
}

function email_subscription_popup_admin_admin_init() {


    wp_enqueue_style('email-subscribe-admin-css', plugins_url('/css/styles.css', __FILE__));
    wp_enqueue_script('jquery');
    wp_enqueue_script('email-subscribe-jquery.validate', plugins_url('/js/jqueryValidate.js', __FILE__));
}

function unsubscribe_user_func() {


    if (isset($_GET) and isset($_GET['action']) and isset($_GET['unsc'])) {

        if (trim($_GET['unsc']) != '') {

            $unsubscriberEmail = trim(urldecode($_GET['unsc']));
            $wp_news_letter_settings = get_option('wp_news_letter_settings');
            $wp_news_letter_settings = stripslashes_deep($wp_news_letter_settings);
            $unsubscriberEmail = sanitize_text_field($unsubscriberEmail);
            $unsubscriberEmail = esc_html($unsubscriberEmail);

            global $wpdb;
            $query = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'nl_subscriptions where unsubs_key = %s', array($unsubscriberEmail));
            $myrow = $wpdb->get_row($query);

            if (is_object($myrow)) {

                $key = md5(uniqid(rand(), true));

                $wpdb->update(
                        $wpdb->prefix . 'nl_subscriptions',
                        array(
                            'is_subscribed' => 0, // column & new value
                            'unsubs_key' => $key // column & new value
                        ),
                        array(
                            'unsubs_key' => $unsubscriberEmail, // where clause(s)
                        ),
                        array(
                            '%d',
                            '%s'
                        ),
                        array(
                            '%s'
                        )
                );

                echo "<script>alert('" . $wp_news_letter_settings['unsubscribe_message'] . "')</script>";
                $url = get_bloginfo('url');
                echo "<script>window.location.href='" . $url . "';</script>";
                exit;
            }
        }
    }
}

function email_subscription_unsubscribers_func() {


    $selfpage = wp_get_referer();

    $action = '';
    if (isset($_REQUEST['action'])) {
        $action = $_REQUEST['action'];
    }
    ?>

    <?php
    switch ($action) {

        default:

            if (isset($_POST['deleteEmails'])) {

                if (!check_admin_referer('action_resubscribe_add_edit', 'resubscribe_and_delete_subsciber')) {

                    wp_die('Security check fail');
                }

                if (!current_user_can('es_email_subscribe_delete_unsubscribers')) {

                    wp_die(__("Access Denied", "video-grid"));
                }

                global $wpdb;
                $subscribersSelectedEmails = $_POST['ckboxs'];
                $mass_email_queue = get_option('mass_email_queue_news_subscriber');
                foreach ($subscribersSelectedEmails as $em) {

                    $em = htmlentities(strip_tags(sanitize_email($em)), ENT_QUOTES);
                    if ($em != "") {

                          $tablename='nl_subscriptions';
                        $wpdb->delete(
                                $wpdb->prefix.$tablename,
                                array('email' => $em),
                                array('%s')
                        );
                        if (is_array($mass_email_queue)) {

                            $key = (int) array_search($em, $mass_email_queue);
                            if (array_search($em, $mass_email_queue) >= 0) {

                                unset($mass_email_queue[$key]);
                            }
                        }
                    }
                }

                update_option('mass_email_subscribers_succ', __('Selected subscribers deleted successfully.', 'email-subscribe'));
                update_option('mass_email_queue_news_subscriber', $mass_email_queue);
            } else if (isset($_POST['resubscribe'])) {


                if (!check_admin_referer('action_resubscribe_add_edit', 'resubscribe_and_delete_subsciber')) {

                    wp_die('Security check fail');
                }

                if (!current_user_can('es_email_subscribe_re_subscriber_unsubscribers')) {

                    wp_die(__("Access Denied", "email-subscribe", 403));
                }

                global $wpdb;
                $subscribersSelectedEmails = $_POST['ckboxs'];
                foreach ($subscribersSelectedEmails as $em) {

                    $em = htmlentities(strip_tags(sanitize_email($em)), ENT_QUOTES);
                    if ($em != "") {

                        $query = "update " . $wpdb->prefix . "nl_subscriptions set is_subscribed=1  where email='$em'";
                        $wpdb->query($query);
                    }
                }

                update_option('mass_email_subscribers_succ', __('Selected subscribers successfully re-subscribed.', 'email-subscribe'));
            }
            $url = plugin_dir_url(__FILE__);
            $url = str_replace("\\", "/", $url);

            if (!current_user_can('es_email_subscribe_view_unsubscribers')) {

                wp_die(__("Access Denied", "email-subscribe", 403));
            }
            ?>       
            <div style="width: 100%;">  
                <div style="float:left;width:65%;" >

            <?php
            global $wpdb;

            $query = "SELECT * from " . $wpdb->prefix . "nl_subscriptions where is_subscribed=0 ";
            $queryCount = "SELECT count(*) from " . $wpdb->prefix . "nl_subscriptions where is_subscribed=0 ";

            if (isset($_GET['searchuser']) and $_GET['searchuser'] != '') {

                $term = sanitize_text_field(urldecode(esc_sql($_GET['searchuser'])));
                $query .= "  and ( name like '%$term%' or email like '%$term%'  )  ";
                $queryCount .= "  and ( name like '%$term%' or email like '%$term%'  )  ";
            }



            $totalRecordForQuery = $wpdb->get_var($queryCount);
            $selfPage = wp_get_referer() . '?page=Newssletter-Email-Unsubscriber';
            global $wp_rewrite;

            $rows_per_page = 10;
            if (isset($_GET['setPerPage']) and $_GET['setPerPage'] != "") {

                $rows_per_page = intval($_GET['setPerPage']);
            }


            $current = (isset($_GET['entrant'])) ? (intval($_GET['entrant'])) : 1;
            $pagination_args = array(
                'base' => @add_query_arg('entrant', '%#%'),
                'format' => '',
                'total' => ceil($totalRecordForQuery / $rows_per_page),
                'current' => $current,
                'show_all' => false,
                'type' => 'plain',
            );

            $selfpage = wp_get_referer();

            if ($totalRecordForQuery > 0) {
                ?>              
                <?php
                $SuccMsg = get_option('mass_email_subscribers_succ');
                update_option('mass_email_subscribers_succ', '');

                $errMsg = get_option('mass_email_subscribers_err');
                update_option('mass_email_subscribers_err', '');
                ?> 


                        <?php if ($SuccMsg != "") {
                            echo "<div class='notice notice-success is-dismissible'><p>";
                            echo $SuccMsg;
                            echo "</p></div>";
                            $SuccMsg = "";
                        } ?>
                        <?php if ($errMsg != "") {
                            echo "<div class='notice notice-error is-dismissible' ><p>";
                            _e($errMsg);
                            echo "</p></div>";
                            $errMsg = "";
                        } ?>

                        <h3><?php echo __('Unsubscribers', 'email-subscribe'); ?> </h3>                
                        <?php
                        $order_by = 'name';
                        $order_pos = "asc";
                        $setacrionpage = 'admin.php?page=Newssletter-Email-Unsubscriber';

                        if (isset($_GET['entrant']) and $_GET['entrant'] != "") {
                            $setacrionpage .= '&entrant=' . intval($_GET['entrant']);
                        }

                        if (isset($_GET['setPerPage']) and $_GET['setPerPage'] != "") {
                            $setacrionpage .= '&setPerPage=' . intval($_GET['setPerPage']);
                        }

                        $seval = "";
                        if (isset($_GET['searchuser']) and $_GET['searchuser'] != "") {
                            $seval = esc_html($_GET['searchuser']);
                        }

                        $search_term_ = '';
                        if (isset($_GET['searchuser'])) {

                            $search_term_ = '&searchuser=' . urlencode(sanitize_text_field($_GET['searchuser']));
                        }

                        if (isset($_GET['order_by'])) {

                            $order_by = trim($_GET['order_by']);
                        }

                        if (isset($_GET['order_pos'])) {

                            $order_pos = trim($_GET['order_pos']);
                        }

                        $order_by = sanitize_text_field(sanitize_sql_orderby($order_by));
                        $order_pos = sanitize_text_field(sanitize_sql_orderby($order_pos));
                        $setacrionpage = esc_html($setacrionpage);
                        ?>
                        <div style="padding-top:5px;padding-bottom:5px"><b><?php echo __('Search User', 'email-subscribe'); ?>: </b><input type="text" value="<?php echo $seval; ?>" id="searchuser" name="searchuser">&nbsp;<input type='submit'  value='Search Unsubscribers' name='searchusrsubmit' class='button-primary' id='searchusrsubmit' onclick="SearchredirectTO();" >&nbsp;<input type='submit'  value='Reset Search' name='searchreset' class='button-primary' id='searchreset' onclick="ResetSearch();" ></div>  
                        <script type="text/javascript" >
                            function SearchredirectTO() {
                                var redirectto = '<?php echo $setacrionpage; ?>';
                                var searchval = jQuery('#searchuser').val();
                                redirectto = redirectto + '&searchuser=' + jQuery.trim(encodeURIComponent(searchval)) + '&entrant=1';
                                window.location.href = redirectto;
                            }
                            function ResetSearch() {

                                var redirectto = '<?php echo $setacrionpage; ?>';
                                window.location.href = redirectto;
                            }
                        </script>
                        <form method="post" action="" id="sendemail" name="sendemail">
                            <input type="hidden" value="sendEmailForm" name="action" id="action">

                            <table class="widefat fixed" cellspacing="0" style="width:97% !important" >
                                <thead>
                                    <tr>   
                        <?php if ($order_by == "email" and $order_pos == "asc"): ?>

                                            <th>
                                                <input onclick="chkAll(this)" type="checkbox" name="chkallHeader" id='chkallHeader'>&nbsp;
                                                <a href="<?php echo $setacrionpage; ?>&order_by=email&order_pos=desc<?php echo $search_term_; ?>"><?php echo __('Email', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/desc.png', __FILE__); ?>"/></a>
                                            </th>
                        <?php else: ?>
                            <?php if ($order_by == "email"): ?>
                                                <th>
                                                    <input onclick="chkAll(this)" type="checkbox" name="chkallHeader" id='chkallHeader'>&nbsp;
                                                    <a href="<?php echo $setacrionpage; ?>&order_by=email&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Email', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/asc.png', __FILE__); ?>"/></a>
                                                </th>
                            <?php else: ?>
                                                <th>
                                                    <input onclick="chkAll(this)" type="checkbox" name="chkallHeader" id='chkallHeader'>&nbsp;
                                                    <a href="<?php echo $setacrionpage; ?>&order_by=email&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Email', 'email-subscribe'); ?></a>
                                                </th>
                            <?php endif; ?>    
                        <?php endif; ?> 

                        <?php if ($order_by == "name" and $order_pos == "asc"): ?>
                                            <th><a href="<?php echo $setacrionpage; ?>&order_by=name&order_pos=desc<?php echo $search_term_; ?>"><?php echo __('Name', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/desc.png', __FILE__); ?>"/></a></th>
                        <?php else: ?>
                            <?php if ($order_by == "name"): ?>
                                                <th><a href="<?php echo $setacrionpage; ?>&order_by=name&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Name', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/asc.png', __FILE__); ?>"/></a></th>
                    <?php else: ?>
                                                <th><a href="<?php echo $setacrionpage; ?>&order_by=name&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Name', 'email-subscribe'); ?></a></th>
                    <?php endif; ?>    
                <?php endif; ?> 


                                    </tr>
                                </thead>

                                <tfoot>
                                    <tr>
                                        <th scope="col" id="name" class="manage-column column-name" style=""><input onclick="chkAll(this)" type="checkbox" name="chkallfooter" id='chkallfooter'>&nbsp;<?php echo __('Select All Emails', 'email-subscribe'); ?></th>
                                        <th scope="col" id="name" class="manage-column column-name" style=""><?php echo __('Name', 'email-subscribe'); ?></th>


                                    </tr>
                                </tfoot>

                                <tbody id="the-list" class="list:cat">
                                        <?php
                                        $offset = ($current - 1) * $rows_per_page;
                                        $query .= " order by $order_by $order_pos";
                                        $query .= " limit $offset, $rows_per_page";
                                        $emails = $wpdb->get_results($query, ARRAY_A);

                                        foreach ($emails as $vemail) {

                                            if ($vemail != null) {

                                                $userId = $vemail['id'];
                                                $name = sanitize_text_field($vemail['name']);
                                                $email = sanitize_email($vemail['email']);

                                                $checked = "";

                                                echo"<tr class='iedit alternate'>
                <td  class='name column-name' style='border:1px solid #DBDBDB;padding-left:13px;'><input type='checkBox' name='ckboxs[]' $checked  value='" . esc_attr($email) . "'>&nbsp;" . esc_attr($email) . "</td>";
                                                echo "<td  class='name column-name' style='border:1px solid #DBDBDB;'> " . stripslashes($name) . "</td>";
                                                echo "</tr>";
                                            }
                                        }
                                        ?>  
                                </tbody>       
                            </table>
                            <table>
                                <tr>
                                    <td>
                                        <?php
                                        if ($totalRecordForQuery > 0) {
                                            echo "<div class='pagination' style='padding-top:10px'>";
                                            echo paginate_links($pagination_args);
                                            echo "</div>";
                                        }
                                        ?>

                                    </td>
                                    <td>
                                        <b>&nbsp;&nbsp;<?php echo __('Per Page', 'email-subscribe'); ?> : </b>
                <?php
                $setPerPageadmin = 'admin.php?page=Newssletter-Email-Unsubscriber';
                $setPerPageadmin .= '&setPerPage=';
                ?>
                                        <select name="setPerPage" onchange="document.location.href = '<?php echo $setPerPageadmin; ?>' + this.options[this.selectedIndex].value + ''">
                                            <option <?php if ($rows_per_page == "10"): ?>selected="selected"<?php endif; ?>  value="10">10</option>
                                            <option <?php if ($rows_per_page == "20"): ?>selected="selected"<?php endif; ?> value="20">20</option>
                                            <option <?php if ($rows_per_page == "30"): ?>selected="selected"<?php endif; ?>value="30">30</option>
                                            <option <?php if ($rows_per_page == "40"): ?>selected="selected"<?php endif; ?> value="40">40</option>
                                            <option <?php if ($rows_per_page == "50"): ?>selected="selected"<?php endif; ?> value="50">50</option>
                                            <option <?php if ($rows_per_page == "60"): ?>selected="selected"<?php endif; ?> value="60">60</option>
                                            <option <?php if ($rows_per_page == "70"): ?>selected="selected"<?php endif; ?> value="70">70</option>
                                            <option <?php if ($rows_per_page == "80"): ?>selected="selected"<?php endif; ?> value="80">80</option>
                                            <option <?php if ($rows_per_page == "90"): ?>selected="selected"<?php endif; ?> value="90">90</option>
                                            <option <?php if ($rows_per_page == "100"): ?>selected="selected"<?php endif; ?> value="100">100</option>
                                            <option <?php if ($rows_per_page == "500"): ?>selected="selected"<?php endif; ?> value="500">500</option>
                                            <option <?php if ($rows_per_page == "1000"): ?>selected="selected"<?php endif; ?> value="1000">1000</option>
                                            <option <?php if ($rows_per_page == "2000"): ?>selected="selected"<?php endif; ?> value="2000">2000</option>
                                            <option <?php if ($rows_per_page == "3000"): ?>selected="selected"<?php endif; ?> value="3000">3000</option>
                                            <option <?php if ($rows_per_page == "4000"): ?>selected="selected"<?php endif; ?> value="4000">4000</option>
                                            <option <?php if ($rows_per_page == "5000"): ?>selected="selected"<?php endif; ?> value="5000">5000</option>
                                        </select>  
                                    </td>
                                </tr>
                            </table>
                            <table> 
                                <tr>
                                    <td class='name column-name' style='padding-top:15px;padding-left:10px;'>

                                        <script type="text/javascript">
                                            function sendEmailToAll(obj) {

                                                var txt;
                                                var r = confirm("<?php echo __('It is not recommaded to send email to all at once as there is always hosting server limit for send emails hourly basis. Most of hosting providers allow 250 emails per hour. Please upgrade to pro version and use cron job newsletter to send email automatically. Do you still want to continue ?', 'email-subscribe'); ?>");
                                                if (r == true) {
                                                    return true;
                                                } else {
                                                    return false;
                                                }


                                            }
                                        </script>
                                        <?php wp_nonce_field('action_resubscribe_add_edit', 'resubscribe_and_delete_subsciber'); ?> 
                                        <input onclick="return validateSendEmailAndDeleteEmail(this)" type='submit' value='<?php echo __('Re-subscribe selected subscribers', 'email-subscribe'); ?>' name='resubscribe' class='button-primary' id='resubscribe' >&nbsp;&nbsp;<input onclick="return validateSendEmailAndDeleteEmail(this)" type='submit' value='<?php echo __('Delete Selected Subscribers', 'email-subscribe'); ?>' name='deleteEmails' class='button-primary' id='deleteEmails' ></td>
                                </tr>

                            </table>
                        </form>  


                <?php
            } else {
                echo '<center><div style="padding-bottom:50pxpadding-top:50px;"><h3>' . __('No Email Unsubscribers Found', 'email-subscribe') . '</h3></div></center>';
            }
            ?>
                </div>
                <div id="postbox-container-1" class="postbox-container" style="float:right;width:35%;margin-top: 50px" > 

                    <!-- PRO Upsell Box -->
                    <div class="postbox" style="border:2px solid #f0a500;">
                        <div class="inside" style="padding:16px;">
                            <h3 style="margin:0 0 10px;color:#f0a500;font-size:16px;">⭐ <?php _e('Upgrade to PRO','email-subscribe'); ?></h3>
                            <ul style="margin:0 0 14px;padding-left:18px;font-size:13px;line-height:2;">
                                <li><?php _e('🎨 <strong>6 new popup styles</strong> (Dark, Minimal, Split layout, Coupon reveal, Slide-in bar)','email-subscribe'); ?></li>
                                <li><?php _e('📧 <strong>ESP Integrations</strong> — Mailchimp, Brevo, Kit, Klaviyo','email-subscribe'); ?></li>
                                <li><?php _e('📊 <strong>Full analytics dashboard</strong> with growth charts','email-subscribe'); ?></li>
                                <li><?php _e('🚀 <strong>Exit-intent trigger</strong>','email-subscribe'); ?></li>
                                <li><?php _e('♾️ <strong>Unlimited subscribers</strong>','email-subscribe'); ?></li>
                                <li><?php _e('📥 <strong>Unlimited import</strong>','email-subscribe'); ?></li>
                            </ul>
                            <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank"
                                style="display:block;background:#f0a500;color:#fff;text-align:center;padding:10px;border-radius:4px;font-weight:700;text-decoration:none;font-size:14px;">
                                <?php _e('Get PRO Version →','email-subscribe'); ?>
                            </a>
                        </div>
                    </div>

                </div>
                <div class="clear"></div>             

            <?php
            break;
    }
    ?>
        <script type="text/javascript" >

            jQuery("input[name='ckboxs[]']").click(function () {
                uncheckedmanagement(this);

            });

            function uncheckedmanagement(elementset) {

                //alert(jQuery(this).is(':checked'));

                if (jQuery("#uncheckedemails").length > 0) {

                    var hiddenvals = jQuery("#uncheckedemails").val();

                } else
                    hiddenvals = "|||";

                var emailval = jQuery(elementset).val();
                var emailsUn = hiddenvals.split('|||');

                if (jQuery(elementset).is(':checked')) {

                    if (jQuery.isArray(emailsUn) == true) {

                        emailsUn.splice(jQuery.inArray(emailval, emailsUn), 1);
                        var strconvert = emailsUn.join('|||');
                        jQuery("#uncheckedemails").val(strconvert);

                    } else {

                        var addtohidden = emailval.toString() + '|||';
                        jQuery("#uncheckedemails").val(addtohidden);
                    }

                } else {

                    if (jQuery.isArray(emailsUn) == true) {

                        if (jQuery.inArray(emailval, emailsUn) <= 0) {
                            emailsUn.push(emailval);
                            var strconvert = emailsUn.join('|||');
                            jQuery("#uncheckedemails").val(strconvert);
                        }

                    } else {
                        var addtohidden = emailval.toString() + '|||';
                        jQuery("#uncheckedemails").val(addtohidden);

                    }
                }


            }

            function chkAll(id) {

                if (id.name == 'chkallfooter') {

                    var chlOrnot = id.checked;
                    document.getElementById('chkallHeader').checked = chlOrnot;

                } else if (id.name == 'chkallHeader') {

                    var chlOrnot = id.checked;
                    document.getElementById('chkallfooter').checked = chlOrnot;

                }

                if (id.checked) {

                    var objs = document.getElementsByName("ckboxs[]");

                    for (var i = 0; i < objs.length; i++)
                    {
                        objs[i].checked = true;
                        uncheckedmanagement(objs[i]);
                    }


                } else {

                    var objs = document.getElementsByName("ckboxs[]");

                    for (var i = 0; i < objs.length; i++)
                    {
                        objs[i].checked = false;
                        uncheckedmanagement(objs[i]);
                    }
                }
            }

            function validateSendEmailAndDeleteEmail(idobj) {

                var objs = document.getElementsByName("ckboxs[]");
                var ischkBoxChecked = false;
                for (var i = 0; i < objs.length; i++) {
                    if (objs[i].checked == true) {

                        ischkBoxChecked = true;
                        break;
                    }

                }

                if (ischkBoxChecked == false)
                {
                    if (idobj.name == 'resubscribe') {

                        alert('<?php echo __('Please select atleast one email.', 'email-subscribe'); ?>');
                        return false;

                    } else if (idobj.name == 'deleteEmails')
                    {
                        alert('<?php echo __('Please select atleast one email to delete.', 'email-subscribe'); ?>')
                        return false;
                    }
                } else {

                    if (idobj.name == 'deleteEmails') {


                        var r = confirm("<?php echo __('Are you sure to delete selected subscribers ?', 'email-subscribe'); ?>");
                        if (r == true) {
                            return true;
                        } else {

                            return false;
                        }

                    }


                }

            }

        </script>

    <?php
}

function email_subscription_popup_load_styles_and_js() {

    wp_register_style('wp-email-subscription-popup', plugins_url('/css/wp-email-subscription-popup.css', __FILE__), array(), '1.2.11');
    wp_register_script('wp-email-subscription-popup-js', plugins_url('/js/wp-email-subscription-popup-js.js', __FILE__), array('jquery'), '1.2.11');
    wp_register_script('subscribe-popup', plugins_url('/js/subscribe-popup.js', __FILE__), array('jquery'), '1.2.15');
    wp_register_style('subscribe-popup', plugins_url('/css/subscribe-popup.css', __FILE__), array(), '1.2.11');
}

function addModalPopupHtmlToWpFooter() {


    $imgUrl = plugin_dir_url(__FILE__) . "images/";

    $loader = $imgUrl . 'AjaxLoader.gif';
    $wp_news_letter_settings = get_option('wp_news_letter_settings');
    $wp_news_letter_settings = stripslashes_deep($wp_news_letter_settings);

    wp_enqueue_script('jquery');
    wp_enqueue_style('wp-email-subscription-popup');
    wp_enqueue_script('wp-email-subscription-popup-js');
    wp_enqueue_script('subscribe-popup');
    wp_enqueue_style('subscribe-popup');

    ob_start();
    ?>
        <div class="overlay_i13" id="mainoverlayDiv" ></div> 

        <div class="mydiv" id='formFormEmail' style="display:none" >
            <div class="container_n">

                <form id="newsletter_signup" name="newsletter_signup">


                    <div class="header">
                        <div class="AjaxLoader"><img src="<?php echo $loader; ?>"/><?php echo $wp_news_letter_settings['wait']; ?></div>
                        <div id="myerror_msg" class="myerror_msg"></div>
                        <div id="mysuccess_msg" class="mysuccess_msg"></div>

                        <h3><?php echo $wp_news_letter_settings['heading']; ?></h3>

                        <div class="subheading"><?php echo $wp_news_letter_settings['subheading']; ?></div>

                    </div>

                    <div class="sep"></div>

                    <div class="inputs">

                        <input type="email" class="textfield"  onblur="restoreInput(this, '<?php echo $wp_news_letter_settings['email']; ?>')" onfocus="return clearInput(this, '<?php echo $wp_news_letter_settings['email']; ?>');"  value="<?php echo $wp_news_letter_settings['email']; ?>" name="youremail" id="youremail"  />
                        <div style="clear:both"></div>
                        <div class="errorinput"></div>
    <?php if ($wp_news_letter_settings['show_name_field']): ?>
                            <input type="text" class="textfield" id="yourname" onblur="restoreInput(this, '<?php echo $wp_news_letter_settings['name']; ?>')" onfocus="return clearInput(this, '<?php echo $wp_news_letter_settings['name']; ?>');"  value="<?php echo $wp_news_letter_settings['name']; ?>" name="yourname" />
                            <div style="clear:both"></div>
                            <div class="errorinput"></div>
    <?php endif; ?>
    <?php if ($wp_news_letter_settings['show_agreement']): ?>
                            <input type="checkbox"  id="chkagreeornot" value="1" name="chkagreeornot" style="display:inline" /><span class="agree_term"> <?php echo html_entity_decode($wp_news_letter_settings['agreement_text']); ?></span>
                            <div style="clear:both"></div>
                            <div class="errorinput"></div>
        <?php endif; ?>
                        <a id="submit_newsletter"  onclick="submit_newsletter();" name="submit_newsletter"><?php echo $wp_news_letter_settings['submitbtn']; ?></a>

                    </div>

                </form>

            </div>      
        </div>                     
        <script type='text/javascript'>

            var htmlpopup = '';

            function clearInput(source, initialValue) {

                if (source.value.toUpperCase() == initialValue.toUpperCase())
                    source.value = '';

                return false;
            }

            function restoreInput(source, initialValue) {
                if (source.value == '')
                    source.value = initialValue;

                return false;
            }




            function submit_newsletter() {

                var emailAdd = jQuery.trim(jQuery("#youremail").val());
                var yourname = jQuery.trim(jQuery("#yourname").val());

                var returnval = false;
                var isvalidName = false;
                var isvalidEmail = false;
                var is_agreed = false;
                if (jQuery('#yourname').length > 0) {

                    var yourname = jQuery.trim(jQuery("#yourname").val());
                    if (yourname != "" && yourname != null && yourname.toLowerCase() != '<?php echo $wp_news_letter_settings['name']; ?>'.toLowerCase()) {

                        var element = jQuery("#yourname").next().next();
                        isvalidName = true;
                        jQuery(element).html('');
                    } else {
                        var element = jQuery("#yourname").next().next();
                        jQuery(element).html('<div class="image_error"><?php echo $wp_news_letter_settings['requiredfield']; ?></div>');
                        // emailAdd=false;

                    }

                } else {

                    isvalidName = true;

                }

                if (emailAdd != "") {


                    var element = jQuery("#youremail").next().next();
                    if (emailAdd.toLowerCase() == '<?php echo $wp_news_letter_settings['email']; ?>'.toLowerCase()) {

                        jQuery(element).html('<div  class="image_error"><?php echo $wp_news_letter_settings['requiredfield']; ?></div>');
                        isvalidEmail = false;
                    } else {

                        var JsRegExPatern = /^\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/

                        if (JsRegExPatern.test(emailAdd)) {

                            isvalidEmail = true;
                            jQuery(element).html('');

                        } else {

                            var element = jQuery("#youremail").next().next();
                            jQuery(element).html('<div class="image_error"><?php echo $wp_news_letter_settings['iinvalidemail']; ?></div>');
                            isvalidEmail = false;

                        }

                    }

                } else {

                    var element = jQuery("#yourname").next().next();
                    jQuery(element).html('<div class="image_error"><?php echo $wp_news_letter_settings['requiredfield']; ?></div>');
                    isvalidEmail = false;

                }

                if (jQuery('#chkagreeornot').length > 0) {

                    if (jQuery("#chkagreeornot").is(':checked')) {

                        var element = jQuery("#chkagreeornot").next().next();
                        jQuery(element).html('');
                        is_agreed = true;
                    } else {


                        var element = jQuery("#chkagreeornot").next().next();
                        jQuery(element).html('<div class="image_error"><?php echo $wp_news_letter_settings['agreement_error']; ?></div>');
                        is_agreed = false;

                    }
                } else {

                    is_agreed = true;
                }


                if (isvalidName == true && isvalidEmail == true && is_agreed == true) {

                    jQuery(".AjaxLoader").show();
                    jQuery('#mysuccess_msg').html('');
                    jQuery('#mysuccess_msg').hide();
                    jQuery('#myerror_msg').html('');
                    jQuery('#myerror_msg').hide();

                    var name = "";
                    if (jQuery('#yourname').length > 0) {

                        name = jQuery("#yourname").val();
                    }
                    var nonce = '<?php echo wp_create_nonce('newsletter-nonce'); ?>';
                    var url = '<?php echo plugin_dir_url(__FILE__); ?>';
                    var email = jQuery("#youremail").val();
                    var str = "action=store_email&email=" + email + '&name=' + name + '&is_agreed=' + is_agreed + '&sec_string=' + nonce;
                    jQuery.ajax({
                        type: "POST",
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: str,
                        async: true,
                        success: function (msg) {
                            if (msg != '') {

                                var result = msg.split("|");
                                if (result[0] == 'success') {

                                    jQuery(".AjaxLoader").hide();
                                    jQuery('#mysuccess_msg').html(result[1]);
                                    jQuery('#mysuccess_msg').show();

                                    setTimeout(function () {

                                        jQuery.fancybox_ns.close();



                                    }, 2000);

                                } else {
                                    jQuery(".AjaxLoader").hide();
                                    jQuery('#myerror_msg').html(result[1]);
                                    jQuery('#myerror_msg').show();
                                }

                            }

                        }
                    });

                }





            }


    <?php $intval = uniqid('interval_'); ?>

            var <?php echo $intval; ?> = setInterval(function () {

                if (document.readyState === 'complete') {

                    clearInterval(<?php echo $intval; ?>);
                    /* if ( jQuery.browser.msie && jQuery.browser.version >= 9 )
                     {
                     jQuery.support.noCloneEvent = true
                     }*/

                    var htmlpopup = jQuery("#formFormEmail").html();
                    jQuery("#formFormEmail").remove();

                    jQuery('body').on('click', '.shownewsletterbox', function () {

                        jQuery.fancybox_ns({

                            'overlayColor': '#000000',
                            'hideOnOverlayClick': false,
                            <?php if(isset($wp_news_letter_settings['centerOnScroll']) && $wp_news_letter_settings['centerOnScroll']==1):?>'centerOnScroll': true,<?php endif;?>
                            'padding': 10,
                            'autoScale': true,
                            'showCloseButton': true,
                            'content': htmlpopup,
                            'transitionIn': 'fade',
                            'transitionOut': 'elastic',
                            'width': 560,
                            'height': 360
                        });

                    });

    <?php if ($wp_news_letter_settings['newsletter_show_on'] == 'any'): ?>


                        jQuery(document).ready(function () {

                            if (readCookie('newsLatterPopup') == null) {

                                setTimeout(function () {

                                    jQuery.fancybox_ns({

                                        'overlayColor': '#000000',
                                        'hideOnOverlayClick': false,
                                        <?php if(isset($wp_news_letter_settings['centerOnScroll']) && $wp_news_letter_settings['centerOnScroll']==1):?>'centerOnScroll': true,<?php endif;?>
                                        'padding': 10,
                                        'autoScale': true,
                                        'showCloseButton': true,
                                        'content': htmlpopup,
                                        'transitionIn': 'fade',
                                        'transitionOut': 'elastic',
                                        'width': 560,
                                        'height': 360
                                    });


                                    createCookie('newsLatterPopup', 'donotshow', <?php echo $wp_news_letter_settings['newsletter_cookie']; ?>);

                                }, 1500);


                            }
                        });
    <?php elseif ($wp_news_letter_settings['newsletter_show_on'] == 'home'): ?>
        <?php if (is_front_page()): ?>

                            jQuery(document).ready(function () {

                                if (readCookie('newsLatterPopup') == null) {


                                    jQuery.fancybox_ns({

                                        'overlayColor': '#000000',
                                        'hideOnOverlayClick': false,
                                        <?php if(isset($wp_news_letter_settings['centerOnScroll']) && $wp_news_letter_settings['centerOnScroll']==1):?>'centerOnScroll': true,<?php endif;?>
                                        'padding': 10,
                                        'autoScale': true,
                                        'showCloseButton': true,
                                        'content': htmlpopup,
                                        'transitionIn': 'fade',
                                        'transitionOut': 'elastic',
                                        'width': 560,
                                        'height': 360
                                    });


                                    createCookie('newsLatterPopup', 'donotshow', <?php echo $wp_news_letter_settings['newsletter_cookie']; ?>);

                                }
                            });

        <?php endif; ?>
    <?php endif; ?>


                }
            }, 100);


        </script>

        <style>
    <?php echo html_entity_decode($wp_news_letter_settings['additional_css'], ENT_QUOTES); ?>
        </style>

    <?php
    $output = ob_get_clean();
    echo $output;
}

function email_subscription_popup_admin_options() {

    if (isset($_POST['btnsave'])) {

        if (!check_admin_referer('action_settings_add_edit', 'add_edit_nonce')) {

            wp_die('Security check fail');
        }

        if (!current_user_can('es_email_subscribe_settings')) {

            wp_die(__("Access Denied", "email-subscribe", 403));
        }

        $newsletter_show_on = 'none';
        $newsletter_cookie = 0;
        if (isset($_POST['newsletter_show_on'])) {

            $newsletter_show_on = htmlentities(strip_tags($_POST['newsletter_show_on']), ENT_QUOTES);
            if ($newsletter_show_on == 'home')
                $newsletter_cookie = htmlentities(strip_tags($_POST['cookieTimeUpUniqueHomePage']), ENT_QUOTES);
            else if ($newsletter_show_on == 'any')
                $newsletter_cookie = htmlentities(strip_tags($_POST['cookieTimeUpUniqueAnyPage']), ENT_QUOTES);
        }

        $options = array();
        $options['newsletter_cookie'] = intval($newsletter_cookie);
        $options['newsletter_show_on'] = sanitize_text_field($newsletter_show_on);
        $options['heading'] = trim(htmlentities(sanitize_text_field($_POST['heading']), ENT_QUOTES));
        $options['subheading'] = trim(htmlentities(sanitize_textarea_field($_POST['subheading']), ENT_QUOTES));
        $options['email'] = trim(htmlentities(sanitize_text_field($_POST['email']), ENT_QUOTES));
        $options['name'] = trim(htmlentities(sanitize_text_field($_POST['name']), ENT_QUOTES));
        $options['submitbtn'] = trim(htmlentities(sanitize_text_field($_POST['submitbtn']), ENT_QUOTES));
        $options['requiredfield'] = trim(htmlentities(sanitize_text_field($_POST['requiredfield']), ENT_QUOTES));
        $options['iinvalidemail'] = trim(htmlentities(sanitize_text_field($_POST['iinvalidemail']), ENT_QUOTES));
        $options['wait'] = trim(htmlentities(sanitize_text_field($_POST['wait']), ENT_QUOTES));
        $options['invalid_request'] = trim(htmlentities(sanitize_text_field($_POST['invalid_request']), ENT_QUOTES));
        $options['email_exist'] = trim(htmlentities(sanitize_text_field($_POST['email_exist']), ENT_QUOTES));
        $options['success'] = trim(htmlentities(sanitize_text_field($_POST['success']), ENT_QUOTES));
        $options['unsubscribe_message'] = trim(htmlentities(sanitize_text_field($_POST['unsubscribe_message']), ENT_QUOTES));
        $options['show_name_field'] = trim(htmlentities(sanitize_text_field($_POST['show_name_field']), ENT_QUOTES));
        $options['centerOnScroll'] = trim(htmlentities(sanitize_text_field($_POST['centerOnScroll']), ENT_QUOTES));
        $options['show_agreement'] = trim(htmlentities(sanitize_text_field($_POST['show_agreement']), ENT_QUOTES));
        
        $default_attribs = array(
                                'target' => array(),
                                'href' => array(),
                                'id' => array(),
                                'class' => array(),
                                'title' => array(),
                                'style' => array(),
                                'data' => array(),
                                'data-mce-id' => array(),
                                'data-mce-style' => array(),
                                'data-mce-bogus' => array(),
                                );

                                $allowed_tags = array(
                                'a'             => $default_attribs,
                                '<a>'             => $default_attribs,
                                 'b'             => $default_attribs,
                                '<b>'             => $default_attribs,
                                'strong'             => $default_attribs,
                                '<strong>'             => $default_attribs,
                                'em'             => $default_attribs,
                                '<em>'             => $default_attribs,
                                 'i'             => $default_attribs,
                                '<i>'             => $default_attribs,
                                'p'             => $default_attribs,
                                '<br>'            => $default_attribs,
                                'br'          => $default_attribs,
                                '<p>'          => $default_attribs,

                                );  

       // $options['agreement_text'] = trim(htmlentities(strip_tags(stripslashes($_POST['agreement_text']), '<a><b><p><strong><em><i>'), ENT_QUOTES));
        $options['agreement_text'] = trim(htmlentities(wp_kses($_POST['agreement_text'], $allowed_tags)));
        $options['agreement_error'] = trim(htmlentities(sanitize_text_field($_POST['agreement_error']), ENT_QUOTES));
        $options['additional_css'] = trim(htmlentities(strip_tags($_POST['additional_css']), ENT_QUOTES));

        $settings = update_option('wp_news_letter_settings', $options);
        $email_subscription_popup_messages = array();
        $email_subscription_popup_messages['type'] = 'succ';
        $email_subscription_popup_messages['message'] = 'Settings saved successfully.';
        update_option('email_subscription_popup_messages', $email_subscription_popup_messages);
    }
    $wp_news_letter_settings = get_option('wp_news_letter_settings');

    if (!isset($wp_news_letter_settings['unsubscribe_message'])) {

        $wp_news_letter_settings['unsubscribe_message'] = 'You have successfully unsubscribed from email newsletter.Thank you...';
    }

    if (!isset($wp_news_letter_settings['subscribe_message'])) {

        $wp_news_letter_settings['subscribe_message'] = 'You have successfully subscribed for email newsletter.Thank you...';
    }

    if (!isset($wp_news_letter_settings['show_name_field'])) {

        $wp_news_letter_settings['show_name_field'] = '1';
    }
    if (!isset($wp_news_letter_settings['centerOnScroll'])) {

        $wp_news_letter_settings['centerOnScroll'] = '0';
    }

    if (!isset($wp_news_letter_settings['show_agreement'])) {

        $wp_news_letter_settings['show_agreement'] = '0';
    }


    if (!isset($wp_news_letter_settings['agreement_text'])) {


        $wp_news_letter_settings['agreement_text'] = 'I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>';
    }

    if (!isset($wp_news_letter_settings['agreement_error'])) {

        $wp_news_letter_settings['agreement_error'] = 'Please read and agree to our terms & conditions.';
    }


    if (!isset($wp_news_letter_settings['additional_css'])) {

        $wp_news_letter_settings['additional_css'] = '';
    }

    $wp_news_letter_settings = stripslashes_deep($wp_news_letter_settings);

    $url = plugin_dir_url(__FILE__);
    ?>
        <style type="">
            .fieldsetAdmin {
                margin: 10px 0px;
                padding: 10px;
                border: 1px solid rgb(221, 221, 221);
                font-size: 15px;
            }
            .fieldsetAdmin legend {
                font-weight: bold;
                color: #222222;

            }
        </style>
        <?php
        // Basic analytics for free version
        global $wpdb;
        $total_active = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nl_subscriptions WHERE is_subscribed=1");
        $total_unsub  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nl_subscriptions WHERE is_subscribed=0");
        $new_today    = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nl_subscriptions WHERE DATE(subscribed_on)=CURDATE()");
        $pct_used     = min(100, round($total_active / I13_ES_FREE_SUBSCRIBER_LIMIT * 100));
        $bar_color    = $pct_used >= 100 ? '#d63638' : ($pct_used >= 80 ? '#f0a500' : '#00a32a');
        ?>
        <!-- Basic Analytics Bar -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px 20px;margin-bottom:16px;display:flex;gap:30px;align-items:center;flex-wrap:wrap;">
            <div>
                <div style="font-size:28px;font-weight:700;color:#1e1e1e;line-height:1;"><?php echo number_format($total_active); ?></div>
                <div style="font-size:12px;color:#666;margin-top:3px;"><?php _e('Active Subscribers','email-subscribe'); ?></div>
            </div>
            <div>
                <div style="font-size:28px;font-weight:700;color:#2271b1;line-height:1;"><?php echo number_format($new_today); ?></div>
                <div style="font-size:12px;color:#666;margin-top:3px;"><?php _e('New Today','email-subscribe'); ?></div>
            </div>
            <div>
                <div style="font-size:28px;font-weight:700;color:#888;line-height:1;"><?php echo number_format($total_unsub); ?></div>
                <div style="font-size:12px;color:#666;margin-top:3px;"><?php _e('Unsubscribed','email-subscribe'); ?></div>
            </div>
            <div style="flex:1;min-width:150px;">
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#666;margin-bottom:4px;">
                    <span><?php _e('Free limit usage','email-subscribe'); ?></span>
                    <span><?php echo $total_active; ?> / <?php echo I13_ES_FREE_SUBSCRIBER_LIMIT; ?></span>
                </div>
                <div style="background:#f0f0f1;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="height:8px;background:<?php echo $bar_color; ?>;width:<?php echo $pct_used; ?>%;border-radius:4px;transition:width 0.3s;"></div>
                </div>
                <?php if($pct_used >= 80): ?>
                <div style="margin-top:4px;font-size:11px;color:<?php echo $bar_color; ?>;">
                    <?php if($pct_used >= 100): ?>
                    <?php _e('Limit reached!','email-subscribe'); ?> <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" style="font-weight:600;"><?php _e('Upgrade to PRO','email-subscribe'); ?></a>
                    <?php else: ?>
                    <?php printf(__('%d%% used — ','email-subscribe'), $pct_used); ?><a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank"><?php _e('Upgrade for unlimited','email-subscribe'); ?></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" style="display:inline-block;background:#f0a500;color:#fff;padding:8px 14px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;">⭐ <?php _e('Upgrade to PRO','email-subscribe'); ?></a>
                <div style="font-size:10px;color:#888;margin-top:3px;text-align:center;"><?php _e('Unlimited subscribers + more styles','email-subscribe'); ?></div>
            </div>
        </div>

        <div style="width: 100%;">  
            <div style="float:left;width:65%;">
                <div class="wrap">

        <?php
        $messages = get_option('email_subscription_popup_messages');
        $type = '';
        $message = '';
        if (isset($messages['type']) and $messages['type'] != "") {

            $type = $messages['type'];
            $message = $messages['message'];
        }


        if ($type == 'err') {
            echo "<div class='notice notice-error is-dismissible'><p>";
            echo $message;
            echo "</p></div>";
        } else if ($type == 'succ') {
            echo "<div class='notice notice-success is-dismissible'><p>";
            echo $message;
            echo "</p></div>";
        }


        update_option('email_subscription_popup_messages', array());
        ?>     
                    <table><tr><td>
                                <div class="fb-like" data-href="https://www.facebook.com/i13websolution" data-layout="button" data-action="like" data-size="large" data-show-faces="false" data-share="false"></div>
                                <div id="fb-root"></div>
                                <script>(function (d, s, id) {
                                        var js, fjs = d.getElementsByTagName(s)[0];
                                        if (d.getElementById(id))
                                            return;
                                        js = d.createElement(s); js.id = id;
                                        js.src = 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2&appId=158817690866061&autoLogAppEvents=1';
                                        fjs.parentNode.insertBefore(js, fjs);
                                    }(document, 'script', 'facebook-jssdk'));</script>
                            </td>
                        </tr>
                    </table>

                    <h2><?php echo __('Settings', 'email-subscribe'); ?> <a href="<?php echo admin_url('admin.php?page=i13_es_onboarding&preview=1'); ?>" style="font-size:12px;font-weight:400;color:#666;text-decoration:none;border:1px solid #ddd;padding:3px 10px;border-radius:3px;margin-left:10px;" title="<?php _e('Preview setup wizard','email-subscribe'); ?>">🧙 <?php _e('Setup Wizard','email-subscribe'); ?></a></h2>
                    <br>

                    <?php
                    // Upgrade prompt: show after 400 subscribers (approaching limit)
                    global $wpdb;
                    $sub_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nl_subscriptions WHERE is_subscribed=1");
                    $upgrade_dismissed = (int)get_option('i13_es_upgrade_dismissed', 0);
                    $show_upgrade = ($sub_count >= 400 && (time() - $upgrade_dismissed) > 7 * DAY_IN_SECONDS);
                    if($show_upgrade): ?>
                    <div id="i13-upgrade-prompt" style="background:#fff8e5;border:1px solid #f0a500;border-left:4px solid #f0a500;border-radius:4px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span style="font-size:22px;">🚀</span>
                        <div style="flex:1;">
                            <strong><?php printf(__('You have %d subscribers — approaching the free limit of %d!', 'email-subscribe'), $sub_count, I13_ES_FREE_SUBSCRIBER_LIMIT); ?></strong><br>
                            <span style="font-size:12px;color:#666;"><?php _e('Upgrade to PRO for unlimited subscribers, 6 new popup styles, ESP integrations (Mailchimp, Brevo, Kit, Klaviyo), full analytics and exit-intent trigger.', 'email-subscribe'); ?></span>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" class="button button-primary" style="background:#f0a500;border-color:#f0a500;">
                                <?php _e('Upgrade to PRO →', 'email-subscribe'); ?>
                            </a>
                            <a href="#" class="button" onclick="fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=i13_es_dismiss_upgrade&nonce=<?php echo wp_create_nonce('i13_es_dismiss'); ?>'});document.getElementById('i13-upgrade-prompt').style.display='none';return false;">
                                <?php _e('Remind me later', 'email-subscribe'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PRO Feature Hints Bar -->
                    <div style="background:#f0f6fc;border:1px solid #c3d9f5;border-radius:4px;padding:10px 16px;margin-bottom:16px;font-size:12px;color:#2271b1;">
                        <strong>🔒 <?php _e('PRO Features:', 'email-subscribe'); ?></strong>
                        &nbsp;
                        <span style="margin-right:12px;">📧 <?php _e('ESP Integrations', 'email-subscribe'); ?></span>
                        <span style="margin-right:12px;">🎨 <?php _e('6 New Popup Styles', 'email-subscribe'); ?></span>
                        <span style="margin-right:12px;">📊 <?php _e('Analytics Dashboard', 'email-subscribe'); ?></span>
                        <span style="margin-right:12px;">🚀 <?php _e('Exit-Intent Trigger', 'email-subscribe'); ?></span>
                        <span style="margin-right:12px;">♾️ <?php _e('Unlimited Subscribers', 'email-subscribe'); ?></span>
                        &nbsp;
                        <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" style="font-weight:600;color:#f0a500;"><?php _e('Get PRO →', 'email-subscribe'); ?></a>
                    </div>

                    <div id="poststuff">
                        <div id="post-body" class="metabox-holder columns-2">
                            <div id="post-body-content">
                                <form method="post" action="" id="subscriptionFrmsettiings" name="subscriptionFrmsettiings" >
                                    <fieldset class="fieldsetAdmin">
                                        <legend><?php echo __('Email Lightbox Popup Settings', 'email-subscribe'); ?></legend>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Show Modal Popup On', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <table>
                                                                <tr>
                                                                    <td style="vertical-align: top">
                                                                        <input type="radio" name="newsletter_show_on" id="unique_home_page" value="home" style="width:10px">
                                                                    </td>
                                                                    <td>
                                                                        <b><?php echo __('Show newsletter modal popup on unique request only on home page', 'email-subscribe'); ?></b>
                                                                        <br/>
                                                                        <div id="cookTimeHomepageRequest" style="display:none">
    <?php echo __('Cookie Time :', 'email-subscribe'); ?>
                                                                            <input style="width:50px" type="text" size="5" name="cookieTimeUpUniqueHomePage" value="<?php echo $wp_news_letter_settings['newsletter_cookie']; ?>"  id="cookieTimeUpUniqueHomePage"/> <?php echo __('In Days', 'email-subscribe'); ?>
                                                                            <div style="clear:both"></div>
                                                                            <div></div>
                                                                        </div>
                                                                        <script>

                                                                            jQuery("#unique_home_page").click(function () {

                                                                                jQuery("#cookTimeAnypageRequest").hide();
                                                                                jQuery("#cookTimeHomepageRequest").show();
                                                                            });
                                                                        </script>    
                                                                    </td>
                                                                </tr>

                                                                <tr>
                                                                    <td style="vertical-align: top">
                                                                        <input type="radio" name="newsletter_show_on" id="unique_any" value="any" style="width:10px">
                                                                    </td>
                                                                    <td>
                                                                        <b><?php echo __('Show newsletter modal popup on unique request for any page', 'email-subscribe'); ?></b>
                                                                        <br/>
                                                                        <div id="cookTimeAnypageRequest" style="display:none">
    <?php echo __('Cookie Time :', 'email-subscribe'); ?>
                                                                            <input style="width:50px" type="text" size="5" name="cookieTimeUpUniqueAnyPage" value="<?php echo $wp_news_letter_settings['newsletter_cookie']; ?>" id="cookieTimeUpUniqueAnyPage"/> <?php echo __('In Days', 'email-subscribe'); ?>
                                                                            <div style="clear:both"></div>
                                                                            <div></div>
                                                                        </div>
                                                                        <script>

                                                                            jQuery("#unique_any").click(function () {
                                                                                jQuery("#cookTimeHomepageRequest").hide();
                                                                                jQuery("#cookTimeAnypageRequest").show();

                                                                            });
                                                                        </script> 

                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="vertical-align: top">
                                                                        <input  type="radio" name="newsletter_show_on" value="none" id="show_none" style="width:10px">

                                                                    </td>
                                                                    <td>
                                                                        <b><?php echo __('No, I will use my custom link', 'email-subscribe'); ?></b>
                                                                        <script>

                                                                            jQuery("#show_none").click(function () {
                                                                                jQuery("#cookTimeHomepageRequest").hide();
                                                                                jQuery("#cookTimeAnypageRequest").hide();

                                                                            });
                                                                        </script> 
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>


                                                                    </td>
                                                                    <td>
                                                                        <br/>
                                                                        <b><?php echo __('To show Newsletter modal Popup On Custom Link Click Use', 'email-subscribe'); ?> <i>shownewsletterbox</i> css class</b>
                                                                        <br/>
                                                                        <br/>
                                                                        <b><?php echo __('Example :', 'email-subscribe'); ?> </b>
                                                                        <pre><?php echo htmlspecialchars('<a href="#" class="shownewsletterbox">Subscribe to Newsletter</a>'); ?></pre>
                                                                    </td>

                                                                </tr>

                                                            </table>
                                                            <hr/>
                                                            <table>
                                                                <tr>
                                                                    <td class="label" style="width:35%">
                                                                        <h3 style="font-size: 13px"><label for="show_name_field"><?php echo __('Show Name Field In Newsletter Popup ?', 'email-subscribe'); ?> <span class="required">*</span></label></h3>
                                                                    </td>
                                                                    <td class="value" style="width:65%">
                                                                        <select id="show_name_field" name="show_name_field" class="select">
                                                                            <option value=""><?php echo __('Select', 'email-subscribe'); ?></option>
                                                                            <option <?php if ($wp_news_letter_settings['show_name_field'] == '1'): ?> selected="selected" <?php endif; ?>  value="1" ><?php echo __('Yes', 'email-subscribe'); ?></option>
                                                                            <option <?php if ($wp_news_letter_settings['show_name_field'] == '0'): ?> selected="selected" <?php endif; ?>  value="0"><?php echo __('No', 'email-subscribe'); ?></option>
                                                                        </select> 
                                                                        <div style="clear:both"></div>
                                                                        <div class="error_label"></div> 
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="label" style="width:35%">
                                                                        <h3 style="font-size: 13px"><label for="centerOnScroll"><?php echo __('Center Newsletter Popup on scroll?', 'email-subscribe'); ?> <span class="required">*</span></label></h3>
                                                                    </td>
                                                                    <td class="value" style="width:65%">
                                                                        <select id="centerOnScroll" name="centerOnScroll" class="select">
                                                                            <option value=""><?php echo __('Select', 'email-subscribe'); ?></option>
                                                                            <option <?php if ($wp_news_letter_settings['centerOnScroll'] == '0'): ?> selected="selected" <?php endif; ?>  value="0"><?php echo __('No', 'email-subscribe'); ?></option>
                                                                            <option <?php if ($wp_news_letter_settings['centerOnScroll'] == '1'): ?> selected="selected" <?php endif; ?>  value="1" ><?php echo __('Yes', 'email-subscribe'); ?></option>
                                                                           
                                                                        </select> 
                                                                        <div style="clear:both"></div>
                                                                        <div class="error_label"></div> 
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="label" style="width:35%">
                                                                        <h3 style="font-size: 13px" id="gdpr"><label for="show_agreement"><?php echo __('Show Checkbox For Terms and Conditions Agreement', 'email-subscribe'); ?> <span class="required">*</span></label> <span style="background:#00a32a;color:#fff;font-size:10px;padding:2px 6px;border-radius:3px;vertical-align:middle;">GDPR</span></h3>
                                                                    </td>
                                                                    <td class="value" style="width:65%">
                                                                        <select id="show_agreement" name="show_agreement" class="select">
                                                                            <option value=""><?php echo __('Select', 'email-subscribe'); ?></option>
                                                                            <option <?php if ($wp_news_letter_settings['show_agreement'] == '1'): ?> selected="selected" <?php endif; ?>  value="1" ><?php echo __('Yes', 'email-subscribe'); ?></option>
                                                                            <option <?php if ($wp_news_letter_settings['show_agreement'] == '0'): ?> selected="selected" <?php endif; ?>  value="0"><?php echo __('No', 'email-subscribe'); ?></option>
                                                                        </select> 
                                                                        <div style="clear:both"></div>
                                                                        <div class="error_label"></div> 
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="label" style="width:35%">
                                                                        <h3 style="font-size: 13px"><label for="agreement_text"><?php echo __('Agreement Text', 'email-subscribe'); ?> <span class="required">*</span></label></h3>
                                                                    </td>
                                                                    <td class="value" style="width:65%">
                                                                        <textarea name="agreement_text" id="agreement_text" style="width: 100%;height: 74px;"><?php echo html_entity_decode($wp_news_letter_settings['agreement_text']); ?></textarea>
                                                                        <div style="clear:both;font-size: 12px;color:black"><?php echo __('Replace # with your Terms of Service and Privacy Policy full Url.', 'email-subscribe'); ?></div>
                                                                        <div class="error_label"></div> 
                                                                    </td>
                                                                </tr>
                                                            </table>    
                                                            <div style="clear:both"></div>
                                                            <div></div>

                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                                <script>
    <?php if ($wp_news_letter_settings['newsletter_show_on'] == 'any'): ?>
                                                        jQuery('#unique_any').trigger('click');
    <?php elseif ($wp_news_letter_settings['newsletter_show_on'] == 'home'): ?>
                                                        jQuery('#unique_home_page').trigger('click');
    <?php else: ?>
                                                        jQuery("#show_none").trigger('click');
    <?php endif; ?>
                                                </script>    
                                            </div>
                                        </div>

                                    </fieldset> 
                                    <fieldset class="fieldsetAdmin">
                                        <legend><?php echo __('Subscription Form Settings Messages & Label Settings', 'email-subscribe'); ?></legend>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Heading', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="heading" size="50" name="heading" value="<?php echo $wp_news_letter_settings['heading']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Subheading', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <textarea id="subheading" style="width:550px;height:60px" size="50" name="subheading" ><?php echo $wp_news_letter_settings['subheading']; ?></textarea>
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Email Label', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="email" size="50" name="email" value="<?php echo $wp_news_letter_settings['email']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>   
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Name Label', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="name" size="50" name="name" value="<?php echo $wp_news_letter_settings['name']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>  
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Submit Button Label', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="submitbtn" size="50" name="submitbtn" value="<?php echo $wp_news_letter_settings['submitbtn']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>   
                                    </fieldset> 
                                    <fieldset class="fieldsetAdmin">
                                        <legend><?php echo __('Errors & validation Messages Settings', 'email-subscribe'); ?></legend>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Required Field Message', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="requiredfield" size="50" name="requiredfield" value="<?php echo $wp_news_letter_settings['requiredfield']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Invalid Email Message', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="iinvalidemail" size="50" name="iinvalidemail" value="<?php echo $wp_news_letter_settings['iinvalidemail']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Invalid Request Message', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="invalid_request" size="50" name="invalid_request" value="<?php echo $wp_news_letter_settings['invalid_request']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>   
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Email Exist Message', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="email_exist" size="50" name="email_exist" value="<?php echo $wp_news_letter_settings['email_exist']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>  
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Agreement Error', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="agreement_error" size="50" name="agreement_error" value="<?php echo $wp_news_letter_settings['agreement_error']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>  
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Success Message', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="success" size="50" name="success" value="<?php echo $wp_news_letter_settings['success']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div> 
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Unsubscribe Message', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="unsubscribe_message" size="50" name="unsubscribe_message" value="<?php echo $wp_news_letter_settings['unsubscribe_message']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div> 
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Wait Message', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <input type="text" id="wait" size="50" name="wait" value="<?php echo $wp_news_letter_settings['wait']; ?>" style="width:550px;">
                                                            <div style="clear:both"></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>   
                                    </fieldset>
                                    <fieldset class="fieldsetAdmin">
                                        <legend><?php echo __('Additional CSS', 'email-subscribe'); ?></legend>
                                        <div class="stuffbox" id="namediv" style="min-width:550px;">
                                            <h3><label><?php echo __('Add CSS', 'email-subscribe'); ?></label></h3>
                                            <div class="inside">
                                                <table>
                                                    <tr>
                                                        <td>
                                                            <textarea style="width:550px;height:200px" id="additional_css" size="50" name="additional_css" ><?php echo $wp_news_letter_settings['additional_css']; ?></textarea>
                                                            <div style="clear:both;font-size: 12px;color:black"><?php echo __("Don't, use style tag. Just add css", 'email-subscribe'); ?></div>
                                                            <div></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <div style="clear:both"></div>
                                            </div>
                                        </div>   
                                    </fieldset>
    <?php wp_nonce_field('action_settings_add_edit', 'add_edit_nonce'); ?> 
                                    <input type="submit"   name="btnsave" id="btnsave" value="<?php echo __('Save Changes', 'email-subscribe'); ?>" class="button-primary">

                                </form> 
                                <script type="text/javascript">


                                    jQuery(document).ready(function () {

                                        jQuery.validator.addMethod("checkHomeCookie", function (value, element) {


                                            if (jQuery('input[name="newsletter_show_on"]:checked').val() == 'home' && jQuery.trim(jQuery("#cookieTimeUpUniqueHomePage").val()) == '') {
                                                return false;
                                            } else {
                                                return true;
                                            }


                                        }, "Please enter cookie value");

                                        jQuery.validator.addMethod("checkanypageCookie", function (value, element) {

                                            if (jQuery('input[name="newsletter_show_on"]:checked').val() == 'any' && jQuery.trim(jQuery("#cookieTimeUpUniqueAnyPage").val()) == '') {
                                                return false;
                                            } else {
                                                return true;
                                            }


                                        }, "Please enter cookie value");
                                        jQuery("#subscriptionFrmsettiings").validate({
                                            rules: {
                                                cookieTimeUpUniqueHomePage: {
                                                    checkHomeCookie: true,
                                                    digits: true

                                                },
                                                cookieTimeUpUniqueAnyPage: {
                                                    checkanypageCookie: true,
                                                    digits: true

                                                },
                                                heading: {
                                                    required: true
                                                }, subheading: {
                                                    required: true
                                                },
                                                email: {
                                                    required: true
                                                },
                                                name: {
                                                    required: true
                                                },
                                                submitbtn: {
                                                    required: true
                                                },
                                                requiredfield: {
                                                    required: true
                                                },
                                                iinvalidemail: {
                                                    required: true
                                                },
                                                invalid_request: {
                                                    required: true
                                                },
                                                email_exist: {
                                                    required: true
                                                },
                                                success: {
                                                    required: true
                                                },
                                                success: {
                                                    required: true
                                                },
                                                unsubscribe_message: {
                                                    required: true
                                                }
                                                , wait: {
                                                    required: true
                                                }

                                            },
                                            errorClass: "image_error",
                                            errorPlacement: function (error, element) {
                                                error.appendTo(element.next().next());
                                            }


                                        })
                                    });

                                </script> 

                            </div>
                        </div>
                    </div>  
                </div>      
            </div>
            <div id="postbox-container-1" class="postbox-container" style="float:right;width:35%;margin-top: 50px" > 
                <!-- PRO Popup Styles Preview in sidebar -->
                <div class="postbox" style="margin-bottom:12px;">
                    <div class="inside" style="padding:12px;">
                        <h3 style="margin:0 0 8px;font-size:13px;">🔒 <?php _e('PRO Popup Styles','email-subscribe'); ?></h3>
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:10px;">
                            <?php
                            $pro_styles = array(
                                array('name'=>'Dark / Night',   'icon'=>'🌙', 'color'=>'#1a1a2e', 'accent'=>'#e94560'),
                                array('name'=>'Minimal Clean',  'icon'=>'✨', 'color'=>'#f5f5f5', 'accent'=>'#111111'),
                                array('name'=>'Bold / Vibrant', 'icon'=>'🔥', 'color'=>'#fff',    'accent'=>'#ff6b35'),
                                array('name'=>'Split + Image',  'icon'=>'🖼', 'color'=>'#4a90e2', 'accent'=>'#ffffff'),
                                array('name'=>'Coupon Reveal',  'icon'=>'🎟', 'color'=>'#fff',    'accent'=>'#e74c3c'),
                                array('name'=>'Slide-in Bar',   'icon'=>'📢', 'color'=>'#1a1a2e', 'accent'=>'#e94560'),
                            );
                            foreach($pro_styles as $s): ?>
                            <div style="border:1px solid #dcdcde;border-radius:4px;overflow:hidden;cursor:pointer;" onclick="window.open('<?php echo I13_ES_PRO_URL; ?>','_blank')">
                                <div style="background:<?php echo $s['color']; ?>;padding:10px 8px;height:55px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;">
                                    <div style="font-size:16px;"><?php echo $s['icon']; ?></div>
                                    <div style="background:<?php echo $s['accent']; ?>;color:#fff;font-size:8px;padding:2px 6px;border-radius:2px;"><?php _e('Subscribe','email-subscribe'); ?></div>
                                </div>
                                <div style="padding:3px 6px;background:#fafafa;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
                                    <span style="font-size:10px;color:#333;"><?php echo $s['name']; ?></span>
                                    <span style="font-size:9px;background:#f0a500;color:#fff;padding:1px 4px;border-radius:2px;">PRO</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" style="display:block;background:#f0a500;color:#fff;text-align:center;padding:8px;border-radius:4px;font-weight:700;text-decoration:none;font-size:12px;">
                            🔓 <?php _e('Unlock All Styles →','email-subscribe'); ?>
                        </a>
                    </div>
                </div>
                <div class="postbox" style="border:2px solid #f0a500;">
                    <div class="inside" style="padding:16px;">
                        <h3 style="margin:0 0 10px;color:#f0a500;font-size:16px;">⭐ <?php _e('Upgrade to PRO','email-subscribe'); ?></h3>
                        <ul style="margin:0 0 14px;padding-left:18px;font-size:13px;line-height:2;">
                            <li><?php _e('🎨 6 new popup styles','email-subscribe'); ?></li>
                            <li><?php _e('📧 ESP Integrations (Mailchimp, Brevo, Kit, Klaviyo)','email-subscribe'); ?></li>
                            <li><?php _e('📊 Full analytics dashboard','email-subscribe'); ?></li>
                            <li><?php _e('🚀 Exit-intent trigger','email-subscribe'); ?></li>
                            <li><?php _e('♾️ Unlimited subscribers','email-subscribe'); ?></li>
                        </ul>
                        <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank"
                            style="display:block;background:#f0a500;color:#fff;text-align:center;padding:10px;border-radius:4px;font-weight:700;text-decoration:none;font-size:14px;">
                            <?php _e('Get PRO Version →','email-subscribe'); ?>
                        </a>
                    </div>
                </div>
            </div>
            <div class="clear"></div></div>  
    <?php
}

function massEmailToEmail_Subscriber_Func() {


    $selfpage = wp_get_referer();

    $action = '';
    if (isset($_REQUEST['action'])) {
        $action = $_REQUEST['action'];
    }
    ?>

    <?php
    switch ($action) {


        case 'sendEmailSend':

            if (!check_admin_referer('action_settings_add_edit', 'sendEmailSend')) {

                wp_die('Security check fail');
            }

            set_time_limit(5000);
            $emailTo = preg_replace('/\s\s+/', ' ', $_POST['emailTo']);
            $toSendEmail = explode(",", $emailTo);
            global $wpdb;

            $flag = false;
            foreach ($toSendEmail as $key => $val) {

                $val = trim(htmlentities(sanitize_email($val), ENT_QUOTES));

                $subject = stripslashes($_POST['email_subject']);
                //$subject=trim(htmlentities(strip_tags($subject),ENT_QUOTES));
                $from_name = stripslashes($_POST['email_From_name']);
                // $from_name=trim(htmlentities(strip_tags($from_name),ENT_QUOTES));

                $from_email = htmlentities(sanitize_email($_POST['email_From']), ENT_QUOTES);
                $emailBody = $_POST['txtArea'];

                $query = "SELECT * FROM " . $wpdb->prefix . "nl_subscriptions WHERE email='$val'";

                $userInfo = $wpdb->get_row($query);
                $user_full_name = "";
                $user_email = "";
                $unsubscribeLinkHtml = "";
                $unsubscribeLinkPlain = "";

                if (is_object($userInfo)) {

                    $uerIdunsbs = urldecode($userInfo->unsubs_key);
                    $user_email = $userInfo->email;
                    $user_full_name = stripslashes($userInfo->name);
                }
                $url = get_home_url();
                $unsubs = $url . '?action=nks_unsubscribeuser&unsc=' . $uerIdunsbs;
                $unsubscribeLinkHtml = '<a href="' . $unsubs . '" target="_blank">Unsubscribe me from all email messages</a>';
                $unsubscribeLinkPlain = $unsubs;

                $emailBody = stripslashes($emailBody);

                $emailBody = str_replace('[user_full_name]', $user_full_name, $emailBody);
                $emailBody = str_replace('[user_email]', $user_email, $emailBody);
                $emailBody = str_replace('[unsubscribe_link_plain]', $unsubscribeLinkPlain, $emailBody);
                $emailBody = str_replace('[unsubscribe_link_html]', $unsubscribeLinkHtml, $emailBody);

                $charSet = get_bloginfo('charset');

                $mailheaders = '';
                //$mailheaders .= "X-Priority: 1\n";
                $mailheaders .= "Content-Type: text/html; charset=\"$charSet\"\n";
                $mailheaders .= "From: $from_name <$from_email>" . "\r\n";
                //$mailheaders .= "Bcc: $emailTo" . "\r\n";
                // $message=nl2br($message,true); 
                $emailBody = wpautop($emailBody);
                $emailBody = '<!DOCTYPE html><html ' . get_language_attributes() . '><head> <meta http-equiv="Content-Type" content="text/html; charset=' . get_bloginfo("charset") . '" /><title>' . get_bloginfo('name', 'display') . '</title></head><body>' . $emailBody . '</body></html>';

                $Rreturns = wp_mail($val, $subject, $emailBody, $mailheaders);

                if ($Rreturns)
                    $flag = true;
            }
            $adminUrl = get_admin_url();
            if ($flag) {

                update_option('mass_email_subscribers_succ', __('Email sent successfully.', 'email-subscribe'));
                $entrant = empty($_POST['entrant']) ? 1 : (int) $_POST['entrant'];
                $setPerPage = empty($_POST['setPerPage']) ? 10 : (int) $_POST['setPerPage'];
                $searchuser = htmlentities(strip_tags($_POST['searchuser']), ENT_QUOTES);

                echo "<script>window.location.href='" . $adminUrl . "admin.php?page=email_subscription_popup_subscribers_management&entrant=$entrant&setPerPage=$setPerPage&searchuser=$searchuser" . "';</script>";
                exit;
            } else {

                $entrant = empty($_POST['entrant']) ? 1 : (int) $_POST['entrant'];
                $setPerPage = empty($_POST['setPerPage']) ? 10 : (int) $_POST['setPerPage'];
                $searchuser = htmlentities(strip_tags($_POST['searchuser']), ENT_QUOTES);

                update_option('mass_email_subscribers_err', __('Unable to send email to newsletter subscribers.', 'email-subscribe'));
                echo "<script>window.location.href='" . $adminUrl . "admin.php?page=email_subscription_popup_subscribers_management&entrant=$entrant&setPerPage=$setPerPage&searchuser=$searchuser" . "';</script>";
                exit;
            }
            break;

        case 'sendEmailForm' :
            $referer = wp_get_referer();

            if (isset($_POST['deleteEmails'])) {

                if (!check_admin_referer('action_settings_add_edit', 'queue_and_delete_subsciber')) {

                    wp_die('Security check fail');
                }

                if (!current_user_can('es_email_subscribe_delete_subscribers')) {

                    wp_die(__("Access Denied", "email-subscribe", 403));
                }

                global $wpdb;
                $subscribersSelectedEmails = $_POST['ckboxs'];
                $mass_email_queue = get_option('mass_email_queue_news_subscriber');
                foreach ($subscribersSelectedEmails as $em) {

                    $em = sanitize_email($em);
                    if ($em != "") {

                        
                        $tablename='nl_subscriptions';
                        $wpdb->delete(
                                $wpdb->prefix.$tablename,
                                array('email' => $em),
                                array('%s')
                        );

                     
                        if (is_array($mass_email_queue)) {

                            $key = (int) array_search($em, $mass_email_queue);
                            if (array_search($em, $mass_email_queue) >= 0) {

                                unset($mass_email_queue[$key]);
                            }
                        }
                    }
                }

                update_option('mass_email_subscribers_succ', __('Selected subscribers deleted successfully.', 'email-subscribe'));
                update_option('mass_email_queue_news_subscriber', $mass_email_queue);
                echo "<script>location.href='" . $referer . "';</script>";
                exit;
            } else if (isset($_POST['resetemailqueue'])) {

                if (!check_admin_referer('action_settings_add_edit', 'queue_and_delete_subsciber')) {

                    wp_die('Security check fail');
                }
                update_option('mass_email_queue_news_subscriber', false);
                update_option('mass_email_subscribers_succ', __('Email queue reseted successfully.', 'email-subscribe'));
                $setacrionpage = 'admin.php?page=email_subscription_popup_subscribers_management';
                echo "<script>location.href='" . $setacrionpage . "';</script>";
                exit;
            }

            if (isset($_POST['sendEmailAll'])) {

                global $wpdb;

                if (!current_user_can('es_email_subscribe_send_email_to_all_subscribers')) {

                    wp_die(__("Access Denied", "email-subscribe", 403));
                }

                $query = "SELECT email as emails from " . $wpdb->prefix . "nl_subscriptions where is_subscribed=1";

                $emails = $wpdb->get_results($query, 'OBJECT');
                $convertToString = '';
                $count = 0;
                foreach ($emails as $mail) {

                    $convertToString .= $mail->emails . ",\n";
                    $count++;
                }
                $convertToString = trim($convertToString, ",\n");
            } else {
                if (isset($_POST['sendEmailQueue'])) {

                    if (!current_user_can('es_email_subscribe_send_email_to_selected_subscribers')) {

                        wp_die(__("Access Denied", "email-subscribe", 403));
                    }
                    $convertToString = esc_textarea($_POST['queueemails']);
                } else {

                    if (!current_user_can('es_email_subscribe_send_email_to_selected_subscribers')) {

                        wp_die(__("Access Denied", "email-subscribe", 403));
                    }
                    $subscribersSelectedEmails = $_POST['ckboxs'];
                    $convertToString = esc_textarea(implode(",\n", $subscribersSelectedEmails));
                }
            }
            ?>    

                <table><tr><td>
                            <div class="fb-like" data-href="https://www.facebook.com/i13websolution" data-layout="button" data-action="like" data-size="large" data-show-faces="false" data-share="false"></div>
                            <div id="fb-root"></div>
                            <script>(function (d, s, id) {
                                    var js, fjs = d.getElementsByTagName(s)[0];
                                    if (d.getElementById(id))
                                        return;
                                    js = d.createElement(s); js.id = id;
                                    js.src = 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2&appId=158817690866061&autoLogAppEvents=1';
                                    fjs.parentNode.insertBefore(js, fjs);
                                }(document, 'script', 'facebook-jssdk'));</script>
                        </td>
                    </tr>
                </table> 
                

                <h3><?php echo __('Send Email To Newsletter Subscribers', 'email-subscribe'); ?> </h3>  
                <?php $url = plugin_dir_url(__FILE__); ?> 
                <form name="frmSendEmailsToUserSend" id='frmSendEmailsToUserSend' method="post" action="" >
                    <input type="hidden" value="sendEmailSend" name="action"> 
                <?php wp_nonce_field('action_settings_add_edit', 'sendEmailSend'); ?>  
                    <input type="hidden" value="<?php echo @$entrant; ?>" name="entrant"> 
                    <input type="hidden" value="<?php echo @$setPerPage; ?>" name="setPerPage"> 
                    <input type="hidden" value="<?php echo @$searchuser; ?>" name="searchuser"> 
                    <table class="form-table" style="width:100%" >
                        <tbody>

                            <tr valign="top" id="subject">
                                <th scope="row" style="width:30%;text-align: right;"><?php echo __('Subject', 'email-subscribe'); ?> *</th>
                                <td>    
                                    <input type="text" id="email_subject" name="email_subject"  class="valid" size="70">
                                    <div style="clear: both;"></div><div></div>
                                </td>
                            </tr>
                            <tr valign="top" id="subject">
                                <th scope="row" style="width:30%;text-align: right"><?php echo __('Email From Name', 'email-subscribe'); ?> *</th>
                                <td>    
                                    <input type="text" id="email_From_name" name="email_From_name"  class="valid" size="70">
                                    <br/><?php echo __('(ex. admin)', 'email-subscribe'); ?>  
                                    <div style="clear: both;"></div><div></div>

                                </td>
                            </tr>
                            <tr valign="top" id="subject">
                                <th scope="row" style="width:30%;text-align: right"><?php echo __('Email From', 'email-subscribe'); ?> *</th>
                                <td>    
                                    <input type="text" id="email_From" name="email_From"  class="valid" size="70">
                                    <br/><?php echo __('(ex. admin@yoursite.com) ', 'email-subscribe'); ?> 
                                    <div style="clear: both;"></div><div></div>

                                </td>
                            </tr>
                            <tr valign="top" id="subject">
                                <th scope="row" style="width:30%;text-align: right"><?php echo __('Email To', 'email-subscribe'); ?> *</th>
                                <td>    
                                    <textarea id='emailTo'  name="emailTo" cols="58" rows="4"><?php echo esc_textarea($convertToString); ?></textarea>
                                    <div style="clear: both;"></div><div></div>
                                </td>
                            </tr>
                            <tr valign="top" id="subject">
                                <th scope="row" style="width:30%;text-align: right"><?php echo __('Email Body', 'email-subscribe'); ?> *</th>
                                <td>    
                                    <div class="wrap">
                <?php wp_editor('', 'txtArea'); ?>
                                        <input type="hidden" name="editor_val" id="editor_val" />  
                                        <div style="clear: both;"></div><div></div> 
                <?php echo __('you can use [user_full_name],[user_email],[unsubscribe_link_plain],[unsubscribe_link_html] place holder into email content', 'email-subscribe'); ?> 
                                    </div>
                                </td>
                            </tr>

                            <tr valign="top" id="subject">
                                <th scope="row" style="width:30%"></th>
                                <td> 

                                    <input type='submit'  value='<?php echo __('Send Email', 'email-subscribe'); ?>' name='sendEmailsend' class='button-primary' id='sendEmailsend' >  
                                </td>
                            </tr>

                    </table>
                </form>
                <script type="text/javascript">


                    jQuery(document).ready(function () {

                        jQuery.validator.addMethod("chkCont", function (value, element) {



                            var editorcontent = tinyMCE.get('txtArea').getContent();

                            if (editorcontent.length) {
                                return true;
                            } else {
                                return false;
                            }


                        },
                                "Please enter email content"
                                );

                        jQuery("#frmSendEmailsToUserSend").validate({
                            errorClass: "error_admin_massemail",
                            rules: {
                                email_subject: {
                                    required: true
                                },
                                email_From_name: {
                                    required: true
                                },
                                email_From: {
                                    required: true, email: true
                                },
                                emailTo: {

                                    required: true
                                },
                                editor_val: {
                                    chkCont: true
                                }

                            },

                            errorPlacement: function (error, element) {
                                error.appendTo(element.next().next());
                            }

                        });


                    });

                </script> 
            <?php
            break;
        default:

            if (!current_user_can('es_email_subscribe_view_subscribers')) {

                wp_die(__("Access Denied", "email-subscribe", 403));
            }
            $url = plugin_dir_url(__FILE__);
            $url = str_replace("\\", "/", $url);
            ?>       
                <div style="width: 100%;">  
                    <div style="float:left;width:65%;" >
            <?php
            global $wpdb;

            $query = "SELECT * from " . $wpdb->prefix . "nl_subscriptions where is_subscribed=1 ";
            $queryCount = "SELECT count(*) from " . $wpdb->prefix . "nl_subscriptions where is_subscribed=1 ";

            if (isset($_GET['searchuser']) and $_GET['searchuser'] != '') {
                $term = trim(urldecode(esc_sql($_GET['searchuser'])));
                $query .= "  and ( name like '%$term%' or email like '%$term%'  )  ";
                $queryCount .= "  and ( name like '%$term%' or email like '%$term%'  )  ";
            }

            $totalRecordForQuery = $wpdb->get_var($queryCount);
            $selfPage = wp_get_referer() . '?page=email_subscription_popup_subscribers_management';
            global $wp_rewrite;

            $rows_per_page = 10;
            if (isset($_GET['setPerPage']) and $_GET['setPerPage'] != "") {

                $rows_per_page = intval($_GET['setPerPage']);
            }

            $current = (isset($_GET['entrant'])) ? (intval($_GET['entrant'])) : 1;
            $pagination_args = array(
                'base' => @add_query_arg('entrant', '%#%'),
                'format' => '',
                'total' => ceil($totalRecordForQuery / $rows_per_page),
                'current' => $current,
                'show_all' => false,
                'type' => 'plain',
            );

            $selfpage = wp_get_referer();

            if ($totalRecordForQuery > 0) {
                ?>              
                <?php
                $SuccMsg = get_option('mass_email_subscribers_succ');
                update_option('mass_email_subscribers_succ', '');

                $errMsg = get_option('mass_email_subscribers_err');
                update_option('mass_email_subscribers_err', '');
                ?> 

                <?php if ($SuccMsg != "") {
                    echo "<div class='notice notice-success is-dismissible'><p>";
                    echo $SuccMsg;
                    echo "</p></div>";
                    $SuccMsg = "";
                } ?>
                <?php if ($errMsg != "") {
                    echo "<div class='notice notice-error is-dismissible' ></p>";
                    _e($errMsg);
                    echo "</p></div>";
                    $errMsg = "";
                } ?>

                            <table><tr><td>
                                        <div class="fb-like" data-href="https://www.facebook.com/i13websolution" data-layout="button" data-action="like" data-size="large" data-show-faces="false" data-share="false"></div>
                                        <div id="fb-root"></div>
                                        <script>(function (d, s, id) {
                                                var js, fjs = d.getElementsByTagName(s)[0];
                                                if (d.getElementById(id))
                                                    return;
                                                js = d.createElement(s);
                                                js.id = id;
                                                js.src = 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.2&appId=158817690866061&autoLogAppEvents=1';
                                                fjs.parentNode.insertBefore(js, fjs);
                                            }(document, 'script', 'facebook-jssdk'));</script>
                                    </td>
                                </tr>
                            </table> 
                            

                            <h3><?php echo __('Send Email To Newsletter Subscribers', 'email-subscribe'); ?></h3>
                    <?php
                    $order_by = 'name';
                    $order_pos = "asc";

                    $setacrionpage = 'admin.php?page=email_subscription_popup_subscribers_management';

                    if (isset($_GET['entrant']) and $_GET['entrant'] != "") {
                        $setacrionpage .= '&entrant=' . intval($_GET['entrant']);
                    }

                    if (isset($_GET['setPerPage']) and $_GET['setPerPage'] != "") {
                        $setacrionpage .= '&setPerPage=' . intval($_GET['setPerPage']);
                    }

                    $seval = "";
                    if (isset($_GET['searchuser']) and $_GET['searchuser'] != "") {
                        $seval = sanitize_text_field($_GET['searchuser']);
                    }

                    $search_term_ = '';
                    if (isset($_GET['searchuser'])) {

                        $search_term_ = '&searchuser=' . urlencode(sanitize_text_field($_GET['searchuser']));
                    }

                    if (isset($_GET['order_by'])) {

                        $order_by = trim($_GET['order_by']);
                    }

                    if (isset($_GET['order_pos'])) {

                        $order_pos = trim($_GET['order_pos']);
                    }

                    $order_by = sanitize_text_field(sanitize_sql_orderby($order_by));
                    $order_pos = sanitize_text_field(sanitize_sql_orderby($order_pos));
                    $setacrionpage = esc_html($setacrionpage);
                    ?>
                            <div style="padding-top:5px;padding-bottom:5px"><b><?php echo __('Search User', 'email-subscribe'); ?> : </b><input type="text" value="<?php echo esc_attr($seval); ?>" id="searchuser" name="searchuser">&nbsp;<input type='submit'  value='<?php echo __('Search Subscribers', 'email-subscribe'); ?>' name='searchusrsubmit' class='button-primary' id='searchusrsubmit' onclick="SearchredirectTO();" >&nbsp;<input type='submit'  value='<?php echo __('Reset Search', 'email-subscribe'); ?>' name='searchreset' class='button-primary' id='searchreset' onclick="ResetSearch();" ></div>  
                            <script type="text/javascript" >
                                function SearchredirectTO() {
                                    var redirectto = '<?php echo $setacrionpage; ?>';
                                    var searchval = jQuery('#searchuser').val();
                                    redirectto = redirectto + '&searchuser=' + jQuery.trim(encodeURIComponent(searchval)) + '&entrant=1';
                                    window.location.href = redirectto;
                                }
                                function ResetSearch() {

                                    var redirectto = '<?php echo $setacrionpage; ?>';
                                    window.location.href = redirectto;
                                    exit;
                                }
                            </script>
                            <form method="post" action="" id="sendemail" name="sendemail">
                                <input type="hidden" value="sendEmailForm" name="action" id="action">

                                <table class="widefat fixed" cellspacing="0" style="width:97% !important" >
                                    <thead>
                                        <tr>   
                <?php if ($order_by == "email" and $order_pos == "asc"): ?>

                                                <th>
                                                    <input onclick="chkAll(this)" type="checkbox" name="chkallHeader" id='chkallHeader'>&nbsp;
                                                    <a href="<?php echo $setacrionpage; ?>&order_by=email&order_pos=desc<?php echo $search_term_; ?>"><?php echo __('Email', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/desc.png', __FILE__); ?>"/></a>
                                                </th>
                <?php else: ?>
                    <?php if ($order_by == "email"): ?>
                                                    <th>
                                                        <input onclick="chkAll(this)" type="checkbox" name="chkallHeader" id='chkallHeader'>&nbsp;
                                                        <a href="<?php echo $setacrionpage; ?>&order_by=email&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Email', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/asc.png', __FILE__); ?>"/></a>
                                                    </th>
                    <?php else: ?>
                                                    <th>
                                                        <input onclick="chkAll(this)" type="checkbox" name="chkallHeader" id='chkallHeader'>&nbsp;
                                                        <a href="<?php echo $setacrionpage; ?>&order_by=email&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Email', 'email-subscribe'); ?></a>
                                                    </th>
                    <?php endif; ?>    
                <?php endif; ?> 

                <?php if ($order_by == "name" and $order_pos == "asc"): ?>
                                                <th><a href="<?php echo $setacrionpage; ?>&order_by=name&order_pos=desc<?php echo $search_term_; ?>"><?php echo __('Name', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/desc.png', __FILE__); ?>"/></a></th>
                            <?php else: ?>
                                <?php if ($order_by == "name"): ?>
                                                    <th><a href="<?php echo $setacrionpage; ?>&order_by=name&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Name', 'email-subscribe'); ?><img style="vertical-align:middle" src="<?php echo plugins_url('/images/asc.png', __FILE__); ?>"/></a></th>
                                <?php else: ?>
                                                    <th><a href="<?php echo $setacrionpage; ?>&order_by=name&order_pos=asc<?php echo $search_term_; ?>"><?php echo __('Name', 'email-subscribe'); ?></a></th>
                                <?php endif; ?>    
                            <?php endif; ?> 


                                        </tr>
                                    </thead>

                                    <tfoot>
                                        <tr>
                                            <th scope="col" id="name" class="manage-column column-name" style=""><input onclick="chkAll(this)" type="checkbox" name="chkallfooter" id='chkallfooter'>&nbsp;<?php echo __('Select All Emails', 'email-subscribe'); ?></th>
                                            <th scope="col" id="name" class="manage-column column-name" style=""><?php echo __('Name', 'email-subscribe'); ?></th>


                                        </tr>
                                    </tfoot>

                                    <tbody id="the-list" class="list:cat">
                            <?php
                            $mass_email_queue = array();
                            if (get_option('mass_email_queue_news_subscriber') != false and is_array(get_option('mass_email_queue_news_subscriber')))
                                $mass_email_queue = get_option('mass_email_queue_news_subscriber');

                            $offset = ($current - 1) * $rows_per_page;
                            $query .= " order by $order_by $order_pos";
                            $query .= " limit $offset, $rows_per_page";
                            $emails = $wpdb->get_results($query, ARRAY_A);

                            foreach ($emails as $vemail) {

                                if ($vemail != null) {

                                    $userId = $vemail['id'];
                                    $name = $vemail['name'];
                                    $email = sanitize_email($vemail['email']);

                                    if (in_array($email, $mass_email_queue))
                                        $checked = "checked='checked'";
                                    else
                                        $checked = "";


                                    echo"<tr class='iedit alternate'>
                      <td  class='name column-name' style='border:1px solid #DBDBDB;padding-left:13px;'><input type='checkBox' name='ckboxs[]' $checked  value='" . esc_attr($email) . "'>&nbsp;" . esc_attr($email) . "</td>";
                                    echo "<td  class='name column-name' style='border:1px solid #DBDBDB;'> " . stripslashes($name) . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>  
                                    </tbody>       
                                </table>
                                <table>
                                    <tr>
                                        <td>
                <?php
                if ($totalRecordForQuery > 0) {
                    echo "<div class='pagination' style='padding-top:10px'>";
                    echo paginate_links($pagination_args);
                    echo "</div>";
                }
                ?>

                                        </td>
                                        <td>
                                            <b>&nbsp;&nbsp;<?php echo __('Per Page :', 'email-subscribe'); ?> </b>
                <?php
                $setPerPageadmin = 'admin.php?page=email_subscription_popup_subscribers_management';
                /* if(isset($_GET['entrant']) and $_GET['entrant']!=""){
                  $setPerPageadmin.='&entrant='.(int)trim($_GET['entrant']);
                  } */
                $setPerPageadmin .= '&setPerPage=';
                ?>
                                            <select name="setPerPage" onchange="document.location.href = '<?php echo $setPerPageadmin; ?>' + this.options[this.selectedIndex].value + ''">
                                                <option <?php if ($rows_per_page == "10"): ?>selected="selected"<?php endif; ?>  value="10">10</option>
                                                <option <?php if ($rows_per_page == "20"): ?>selected="selected"<?php endif; ?> value="20">20</option>
                                                <option <?php if ($rows_per_page == "30"): ?>selected="selected"<?php endif; ?>value="30">30</option>
                                                <option <?php if ($rows_per_page == "40"): ?>selected="selected"<?php endif; ?> value="40">40</option>
                                                <option <?php if ($rows_per_page == "50"): ?>selected="selected"<?php endif; ?> value="50">50</option>
                                                <option <?php if ($rows_per_page == "60"): ?>selected="selected"<?php endif; ?> value="60">60</option>
                                                <option <?php if ($rows_per_page == "70"): ?>selected="selected"<?php endif; ?> value="70">70</option>
                                                <option <?php if ($rows_per_page == "80"): ?>selected="selected"<?php endif; ?> value="80">80</option>
                                                <option <?php if ($rows_per_page == "90"): ?>selected="selected"<?php endif; ?> value="90">90</option>
                                                <option <?php if ($rows_per_page == "100"): ?>selected="selected"<?php endif; ?> value="100">100</option>
                                                <option <?php if ($rows_per_page == "500"): ?>selected="selected"<?php endif; ?> value="500">500</option>
                                                <option <?php if ($rows_per_page == "1000"): ?>selected="selected"<?php endif; ?> value="1000">1000</option>
                                                <option <?php if ($rows_per_page == "2000"): ?>selected="selected"<?php endif; ?> value="2000">2000</option>
                                                <option <?php if ($rows_per_page == "3000"): ?>selected="selected"<?php endif; ?> value="3000">3000</option>
                                                <option <?php if ($rows_per_page == "4000"): ?>selected="selected"<?php endif; ?> value="4000">4000</option>
                                                <option <?php if ($rows_per_page == "5000"): ?>selected="selected"<?php endif; ?> value="5000">5000</option>
                                            </select>  
                                        </td>
                                    </tr>
                                </table>
                                <table> 
                                    <tr>
                                        <td class='name column-name' style='padding-top:15px;padding-left:10px;'>

                                            <script type="text/javascript">
                                                function sendEmailToAll(obj) {

                                                    var txt;
                                                    var r = confirm("<?php echo __('It is not recommaded to send email to all at once as there is always hosting server limit for send emails hourly basis. Most of hosting providers allow 250 emails per hour. Please upgrade to pro version and use cron job newsletter to send email automatically. Do you still want to continue ?', 'email-subscribe'); ?>");
                                                    if (r == true) {
                                                        return true;
                                                    } else {
                                                        return false;
                                                    }


                                                }
                                            </script>
                                        <?php wp_nonce_field('action_settings_add_edit', 'queue_and_delete_subsciber'); ?> 
                                            <input onclick="return validateSendEmailAndDeleteEmail(this)" type='submit' value='<?php echo __('Send Email To Selected Subscribers', 'email-subscribe'); ?>' name='sendEmail' class='button-primary' id='sendEmail' >&nbsp;<input onclick="return sendEmailToAll(this)" type='submit' value='<?php echo __('Send Email To All Subscribers', 'email-subscribe'); ?>' name='sendEmailAll' class='button-primary' id='sendEmailAll' >&nbsp;<input onclick="return validateSendEmailAndDeleteEmail(this)" type='submit' value='<?php echo __('Delete Selected Subscribers', 'email-subscribe'); ?>' name='deleteEmails' class='button-primary' id='deleteEmails' ></td>
                                    </tr>

                                        <?php
                                        $mass_email_queue = get_option('mass_email_queue_news_subscriber');
                                        if ($mass_email_queue != false and $mass_email_queue != null) {
                                            if (is_array($mass_email_queue)) {
                                                ?>
                                            <tr>
                                                <td>
                                                    <h3><?php echo __('Emails In Queue', 'email-subscribe'); ?></h3>
                                                    <textarea readonly="readonly" name="queueemails" id="queueemails" cols="70" rows="10"><?php
                                                foreach ($mass_email_queue as $email_) {
                                                    echo esc_attr($email_) . ",\n";
                                                }
                                                ?></textarea>
                                                    <br/>
                                                    <input type="hidden" name="uncheckedemails" id="uncheckedemails" value="">
                                                    <input  type='submit' value='<?php echo __('Send Email To Subscribers In Queue', 'email-subscribe'); ?>' name='sendEmailQueue' class='button-primary' id='sendEmailQueue' >&nbsp;<input  type='submit' value='<?php echo __('Reset Email Queue', 'email-subscribe'); ?>' name='resetemailqueue' class='button-primary' id='resetemailqueue' >
                                                </td>
                                            </tr>
                                            <?php
                                            }
                                        }
                                        ?>    
                                </table>
                            </form>  


                                            <?php
                                        } else {
                                            echo '<center><div style="padding-bottom:50pxpadding-top:50px;"><h3>' . __('No Email Subscription Found', 'email-subscribe') . '</h3></div></center>';

                                            //echo '<center><div style="padding-bottom:50pxpadding-top:50px;"><h3><a href="admin.php?page=email_subscription_popup_subscribers_management">Click Here To Continue..</a></h3></div></center>';
                                            ?>
                                            <?php
                                            $exportUrl = plugin_dir_url(__FILE__);
                                            $exportUrl .= 'export_subscribers.php';
                                            $importUrl = admin_url('admin.php?page=email_subscription_popup_subscribers_management&action=importform');
                                            $subscriberUlr = admin_url('admin.php?page=email_subscription_popup_subscribers_management');
                                            ?>

                                            <?php
                                        }
                                        ?>
                    </div>
                    <div id="postbox-container-1" class="postbox-container" style="float:right;width:35%;margin-top: 50px" > 

                    <div class="postbox" style="border:2px solid #f0a500;">
                        <div class="inside" style="padding:16px;">
                            <h3 style="margin:0 0 10px;color:#f0a500;font-size:16px;">⭐ <?php _e('Upgrade to PRO','email-subscribe'); ?></h3>
                            <ul style="margin:0 0 14px;padding-left:18px;font-size:13px;line-height:2;">
                                <li><?php _e('🎨 6 new popup styles','email-subscribe'); ?></li>
                                <li><?php _e('📧 ESP Integrations (Mailchimp, Brevo, Kit, Klaviyo)','email-subscribe'); ?></li>
                                <li><?php _e('📊 Full analytics dashboard','email-subscribe'); ?></li>
                                <li><?php _e('🚀 Exit-intent trigger','email-subscribe'); ?></li>
                                <li><?php _e('♾️ Unlimited subscribers','email-subscribe'); ?></li>
                            </ul>
                            <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank"
                                style="display:block;background:#f0a500;color:#fff;text-align:center;padding:10px;border-radius:4px;font-weight:700;text-decoration:none;font-size:14px;">
                                <?php _e('Get PRO Version →','email-subscribe'); ?>
                            </a>
                        </div>
                    </div>

                    </div>
                    <div class="clear">
                    </div>             

            <?php
            break;
    }
    ?>
            <script type="text/javascript" >

                jQuery("input[name='ckboxs[]']").click(function () {
                    uncheckedmanagement(this);

                });

                function uncheckedmanagement(elementset) {

                    //alert(jQuery(this).is(':checked'));

                    if (jQuery("#uncheckedemails").length > 0) {
                        var hiddenvals = jQuery("#uncheckedemails").val();
                    } else
                        hiddenvals = "|||";

                    var emailval = jQuery(elementset).val();
                    var emailsUn = hiddenvals.split('|||');

                    if (jQuery(elementset).is(':checked')) {

                        if (jQuery.isArray(emailsUn) == true) {

                            emailsUn.splice(jQuery.inArray(emailval, emailsUn), 1);
                            var strconvert = emailsUn.join('|||');
                            jQuery("#uncheckedemails").val(strconvert);
                        } else {

                            var addtohidden = emailval.toString() + '|||';
                            jQuery("#uncheckedemails").val(addtohidden);
                        }

                    } else {

                        if (jQuery.isArray(emailsUn) == true) {

                            if (jQuery.inArray(emailval, emailsUn) <= 0) {
                                emailsUn.push(emailval);
                                var strconvert = emailsUn.join('|||');
                                jQuery("#uncheckedemails").val(strconvert);
                            }

                        } else {
                            var addtohidden = emailval.toString() + '|||';
                            jQuery("#uncheckedemails").val(addtohidden);

                        }
                    }


                }

                function chkAll(id) {

                    if (id.name == 'chkallfooter') {

                        var chlOrnot = id.checked;
                        document.getElementById('chkallHeader').checked = chlOrnot;

                    } else if (id.name == 'chkallHeader') {

                        var chlOrnot = id.checked;
                        document.getElementById('chkallfooter').checked = chlOrnot;

                    }

                    if (id.checked) {

                        var objs = document.getElementsByName("ckboxs[]");

                        for (var i = 0; i < objs.length; i++)
                        {
                            objs[i].checked = true;
                            uncheckedmanagement(objs[i]);
                        }


                    } else {

                        var objs = document.getElementsByName("ckboxs[]");

                        for (var i = 0; i < objs.length; i++)
                        {
                            objs[i].checked = false;
                            uncheckedmanagement(objs[i]);
                        }
                    }
                }

                function validateSendEmailAndDeleteEmail(idobj) {

                    var objs = document.getElementsByName("ckboxs[]");
                    var ischkBoxChecked = false;
                    for (var i = 0; i < objs.length; i++) {
                        if (objs[i].checked == true) {

                            ischkBoxChecked = true;
                            break;
                        }

                    }

                    if (ischkBoxChecked == false)
                    {
                        if (idobj.name == 'sendEmail' || idobj.name == 'sendEmailqueue' || idobj.name == 'exportSelected') {
                            alert('<?php echo __('Please select atleast one email.', 'email-subscribe'); ?>');
                            return false;

                        } else if (idobj.name == 'deleteEmails')
                        {
                            alert('<?php echo __('Please select atleast one email to delete.', 'email-subscribe'); ?>')
                            return false;
                        }
                    } else {
                        if (idobj.name == 'deleteEmails') {

                            var r = confirm("<?php echo __('Are you sure to delete selected subscribers?', 'email-subscribe'); ?>");
                            if (r == true) {
                                return true;
                            } else {

                                return false;
                            }

                        }

                    }

                }

            </script>

    <?php
}

 function email_subscription_shortcode_func(){
?>    
  
            <div id="tab-2" aria-labelledby="ui-id-2" role="tabpanel" class="ui-tabs-panel ui-corner-bottom ui-widget-content" aria-hidden="false" style="">
              <h3 style="font-size:15px"><?php echo __('Use this shortcode only if you want to print email subscription form separately.', 'email-subscribe'); ?></h3>
                    <h4>Shortcode</h4>
                    <input style="text-align:left;height:30px;" onclick="this.focus(); this.select()" value="[print_email_subscribe_form ]">
                    <br>
                    <br>
                    <div>
                        <b>Shortcode Params can be</b>
                        <ul style="list-style-type: circle;padding-left: 50px">
                            <li>Heading="Subscribe to our newsletter" </li>
                            <li>Subheading="Want to be notified when our article is published? Enter your email address and name below to be the first to know."</li>
                            <li>EmailLabel="Email"</li>
                            <li>NameLabel="Name"</li>
                            <li>SubmitButtonLabel="SIGN UP FOR NEWSLETTER NOW"</li>
                            <li>RequiredFieldMessage="This field is required."</li>
                            <li>InvalidEmailMessage="Please enter valid email address."</li>
                            <li>InvalidRequestMessage="Invalid request."</li>
                            <li>EmailExistMessage="This email is already exist."</li>
                            <li>SuccessMessage="You have successfully subscribed to our Newsletter!"</li>
                            <li>WaitMessage="Please wait..."</li>
                            <li>ShowNameField="1"</li>
                            <li>show_agreement="0"</li>
                            <li>agreement_text="<?php echo htmlentities('I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>');?>"</li>
                            <li>agreement_error="Please read and agree to our terms & conditions."</li>
                        </ul>
                    </div>
                    
          </div>
<?php            
}

function print_email_subscribe_form_func($instance) {
   
    wp_enqueue_script('jquery');
    
     ob_start();
    $Heading = esc_html(esc_attr(apply_filters('widget_title', empty($instance['heading']) ? 'Subscribe to our newsletter' : sanitize_text_field($instance['heading']))));
    echo "<h2 class='news_titile_h2'>" . esc_html(esc_attr($Heading)) . "</h2>";
    $Subheading = esc_html(esc_attr(empty($instance['subheading']) ? 'Want to be notified when our article is published? Enter your email address and name below to be the first to know.' : sanitize_textarea_field($instance['subheading'])));
    $EmailLabel = esc_html(esc_attr(empty($instance['emaillabel']) ? 'Email' : sanitize_text_field($instance['emaillabel'])));
    $NameLabel = esc_html(esc_attr(empty($instance['namelabel']) ? 'Name' : sanitize_text_field($instance['namelabel'])));
    $SubmitButtonLabel = esc_html(esc_attr(empty($instance['submitbuttonlabel']) ? 'SIGN UP FOR NEWSLETTER NOW' : sanitize_text_field($instance['submitbuttonlabel'])));
    $RequiredFieldMessage = esc_html(esc_attr(empty($instance['requiredfieldmessage']) ? 'This field is required.' : sanitize_text_field($instance['requiredfieldmessage'])));
    $InvalidEmailMessage = esc_html(esc_attr(empty($instance['invalidemailmessage']) ? 'Please enter valid email address.' : sanitize_text_field($instance['invalidemailmessage'])));
    $InvalidRequestMessage = empty($instance['invalidrequestmessage']) ? 'Invalid request.' : sanitize_text_field($instance['invalidrequestmessage']);
    $EmailExistMessage = esc_html(esc_attr(empty($instance['emailexistmessage']) ? 'This email is already exist.' : sanitize_text_field($instance['emailexistmessage'])));
    $SuccessMessage = esc_html(esc_attr(empty($instance['successmessage']) ? 'You have successfully subscribed to our Newsletter!' : sanitize_text_field($instance['successmessage'])));
    $WaitMessage = esc_html(esc_attr(empty($instance['waitmessage']) ? 'Please wait...' : sanitize_text_field($instance['waitmessage'])));
    $ShowNameField = array_key_exists('shownamefield', $instance) ? intval($instance['shownamefield']) : 1;
    $show_agreement = array_key_exists('show_agreement', $instance) ? intval($instance['show_agreement']) : 0;
    $agreement_text = esc_html(esc_attr(empty($instance['agreement_text']) ? 'I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>' : $instance['agreement_text']));
    $agreement_error = esc_html(esc_attr(empty($instance['agreement_error']) ? 'Please read and agree to our terms & conditions.' : sanitize_text_field($instance['agreement_error'])));
    $imgUrl = plugin_dir_url(__FILE__) . "images/";
    $loader = $imgUrl . 'AjaxLoader.gif';
    $rand = uniqid('filed_');
    $rand_func = uniqid('fun');
    $wp_news_letter_settings = get_option('wp_news_letter_settings');
    $wp_news_letter_settings = stripslashes_deep($wp_news_letter_settings);
    ?>

            <div class="Nknewsletter_description"><?php echo $Subheading; ?></div>
            <div class="Nknewsletter-widget">
                <div class="<?php echo $rand; ?>_AjaxLoader ajaxLoaderWidget" id="<?php echo $rand; ?>_AjaxLoader" style="margin-bottom:8px;"><img src="<?php echo $loader; ?>"/><?php echo $WaitMessage; ?></div>
                <div class="<?php echo $rand; ?>_myerror_msg myerror_msg" id="<?php echo $rand; ?>_myerror_msg"></div>
                <div class="<?php echo $rand; ?>_mysuccess_msg mysuccess_msg" id="<?php echo $rand; ?>_mysuccess_msg"></div>
                <input type="text" name="<?php echo $rand; ?>_youremail" id="<?php echo $rand; ?>_youremail" class="Nknewsletter_email"  value="<?php echo $EmailLabel; ?>" onfocus="return clearInput(this, '<?php echo $EmailLabel; ?>');" onblur="restoreInput(this, '<?php echo $EmailLabel; ?>')"/>
                <div class="" id="<?php echo $rand; ?>_errorinput_email"></div>

    <?php if ($ShowNameField == "1"): ?>
                    <div class="Nknewsletter_space" id="<?php echo $rand; ?>_name_Nknewsletter_space" ></div>
                    <input type="text" name="<?php echo $rand; ?>_yourname" id="<?php echo $rand; ?>_yourname" class="Nknewsletter_name" value="<?php echo $NameLabel; ?>" onfocus="return clearInput(this, '<?php echo $NameLabel; ?>');" onblur="restoreInput(this, '<?php echo $NameLabel; ?>')" />
                    <div class="errorinput_widget" id="<?php echo $rand; ?>_errorinput_name"></div>
                    <div class="Nknewsletter_space" id="<?php echo $rand; ?>_name_Nknewsletter_space" ></div>   
    <?php else: ?>
                    <div class="Nknewsletter_space" id="<?php echo $rand; ?>_name_Nknewsletter_space" ></div>
    <?php endif; ?>

    <?php if ($show_agreement == "1"): ?>
                    <input class="nk_newsletter_agree" style="display:inline-block" type="checkbox"  id="<?php echo $rand; ?>_agree" value="1" name="<?php echo $rand; ?>_agree" /><span class="nk_newslteer_agree_term"> <?php echo html_entity_decode($agreement_text); ?></span>
                    <div style="clear:both"></div>
                    <div class="errorinput_widget" id="<?php echo $rand; ?>_errorinput_agree"></div>

    <?php else: ?>
                    <div class="Nknewsletter_space" id="<?php echo $rand; ?>_agree_Nknewsletter_space" ></div>
    <?php endif; ?>

                <div class="Nknewsletter_space" id="<?php echo $rand; ?>submit_space" ></div>
                <input class="Nknewsletter_space_submit" type="submit" value="<?php echo $SubmitButtonLabel; ?>" onclick="return <?php echo $rand_func; ?>_submit_newsletter();" name="<?php echo $rand; ?>_submit" />
            </div>
            <script>

                function <?php echo $rand_func; ?>_submit_newsletter() {


                    var emailAdd = jQuery.trim(jQuery("#<?php echo $rand; ?>_youremail").val());
                    var yourname = jQuery.trim(jQuery("#<?php echo $rand; ?>_yourname").val());

                    var returnval = false;
                    var isvalidName = false;
                    var isvalidEmail = false;
                    var isagree = false;

                    if (jQuery("#<?php echo $rand; ?>_yourname").length > 0) {


                        var yourname = jQuery.trim(jQuery("#<?php echo $rand; ?>_yourname").val());

                        if (yourname != "" && yourname != null && yourname.toLowerCase() != '<?php echo $NameLabel; ?>'.toLowerCase()) {

                            var element = jQuery("#<?php echo $rand; ?>_yourname").next().next();
                            isvalidName = true;
                            jQuery(element).html('');
                        } else {
                            var element = jQuery("#<?php echo $rand; ?>_yourname").next().next();
                            jQuery(element).html('<div class="image_error"><?php echo $RequiredFieldMessage; ?></div>');
                            jQuery("#<?php echo $rand; ?>_name_Nknewsletter_space").css({marginBottom: "20px"});
                            // emailAdd=false;

                        }


                    } else {
                        isvalidName = true;

                    }

                    if (jQuery("#<?php echo $rand; ?>_agree").length > 0) {

                        if (jQuery("#<?php echo $rand; ?>_agree").is(':checked')) {

                            var element = jQuery("#<?php echo $rand; ?>_agree").next().next();
                            jQuery(element).html('');
                            isagree = true;
                        } else {


                            var element = jQuery("#<?php echo $rand; ?>_agree").next().next();
                            jQuery(element).html('<div class="image_error"><?php echo $agreement_error; ?></div>');
                            jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});
                            isagree = false;

                        }
                    } else {

                        isagree = true;

                    }


                    if (emailAdd != "") {


                        var element = jQuery("#<?php echo $rand; ?>_youremail").next().next();
                        if (emailAdd.toLowerCase() == '<?php echo $EmailLabel; ?>'.toLowerCase()) {

                            jQuery(element).html('<div  class="image_error"><?php echo $RequiredFieldMessage; ?></div>');
                            isvalidEmail = false;

                            jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});

                        } else {

                            var JsRegExPatern = /^\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/
                            if (JsRegExPatern.test(emailAdd)) {

                                isvalidEmail = true;
                                jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "20px"});
                                jQuery(element).html('');

                            } else {

                                var element = jQuery("#<?php echo $rand; ?>_youremail").next().next();
                                jQuery(element).html('<div class="image_error"><?php echo $InvalidEmailMessage; ?></div>');
                                jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});
                                isvalidEmail = false;

                            }

                        }

                    } else {

                        var element = jQuery("#<?php echo $rand; ?>_yourname").next().next();
                        jQuery(element).html('<div class="image_error"><?php echo $RequiredFieldMessage; ?></div>');
                        jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});
                        isvalidEmail = false;

                    }

                    if (isvalidName == true && isvalidEmail == true && isagree == true) {

                        jQuery("#<?php echo $rand; ?>_name_Nknewsletter_space").css({marginBottom: "20px"});
                        jQuery("<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "20px"});

                        jQuery("#<?php echo $rand; ?>_AjaxLoader").show();
                        jQuery('#<?php echo $rand; ?>_mysuccess_msg').html('');
                        jQuery('#<?php echo $rand; ?>_mysuccess_msg').hide();
                        jQuery('#<?php echo $rand; ?>_myerror_msg').html('');
                        jQuery('#<?php echo $rand; ?>_myerror_msg').hide();

                        var nonce = '<?php echo wp_create_nonce('newsletter-nonce'); ?>';
                        var url = '<?php echo plugin_dir_url(__FILE__); ?>';
                        var email = jQuery("#<?php echo $rand; ?>_youremail").val();
                        var name = "";
                        if (jQuery("#<?php echo $rand; ?>_yourname").length > 0) {

                            name = jQuery("#<?php echo $rand; ?>_yourname").val();
                        }
                        var str = "action=store_email&email=" + email + '&name=' + name + '&is_agreed=' + isagree + '&sec_string=' + nonce;
                        jQuery.ajax({
                            type: "POST",
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            data: str,
                            async: true,
                            success: function (msg) {
                                if (msg != '') {

                                    var result = msg.split("|");
                                    if (result[0] == 'success') {

                                        jQuery("#<?php echo $rand; ?>_AjaxLoader").hide();
                                        jQuery('.<?php echo $rand; ?>_mysuccess_msg').html(result[1]);
                                        jQuery('.<?php echo $rand; ?>_mysuccess_msg').show();
                                        setTimeout(function () {

                                            jQuery('#<?php echo $rand; ?>_mysuccess_msg').hide();
                                            jQuery('#<?php echo $rand; ?>_mysuccess_msg').html('');
                                            jQuery("#<?php echo $rand; ?>_youremail").val('<?php echo $EmailLabel; ?>');
                                            jQuery("#<?php echo $rand; ?>_yourname").val('<?php echo $NameLabel; ?>');


                                        }, 2000);




                                    } else {
                                        jQuery("#<?php echo $rand; ?>_AjaxLoader").hide();
                                        jQuery('#<?php echo $rand; ?>_myerror_msg').html(result[1]);
                                        jQuery('#<?php echo $rand; ?>_myerror_msg').show();
                                        setTimeout(function () {

                                            jQuery('#<?php echo $rand; ?>_myerror_msg').hide();
                                            jQuery('#<?php echo $rand; ?>_myerror_msg').html('');




                                        }, 2000);

                                    }

                                }

                            }
                        });

                    }





                }
            </script>
            <style>
    <?php echo html_entity_decode($wp_news_letter_settings['additional_css'], ENT_QUOTES); ?>
            </style>
    <?php
       $output = ob_get_clean();
        return $output;
}

class nksnewslettersubscriber extends WP_Widget {

    function __construct() {

        $widget_ops = array('classname' => 'nksnewslettersubscriber', 'description' => 'Nks WordPress Newsletter');
        parent::__construct('nksnewslettersubscriber', 'Newsletter Subscribe', $widget_ops);
    }

    function widget($args, $instance) {

        if (is_array($args)) {

            extract($args);
        }

        $Heading = apply_filters('widget_title', empty($instance['Heading']) ? 'Subscribe to our newsletter' : sanitize_text_field($instance['Heading']));
        include_once(ABSPATH . WPINC . '/feed.php');
        echo @$before_widget;
        echo @$before_title . $Heading . $after_title;
        $Subheading = empty($instance['Subheading']) ? 'Want to be notified when our article is published? Enter your email address and name below to be the first to know.' : sanitize_textarea_field($instance['Subheading']);
        $EmailLabel = empty($instance['EmailLabel']) ? 'Email' : sanitize_text_field($instance['EmailLabel']);
        $NameLabel = empty($instance['NameLabel']) ? 'Name' : sanitize_text_field($instance['NameLabel']);
        $SubmitButtonLabel = empty($instance['SubmitButtonLabel']) ? 'SIGN UP FOR NEWSLETTER NOW' : sanitize_text_field($instance['SubmitButtonLabel']);
        $RequiredFieldMessage = empty($instance['RequiredFieldMessage']) ? 'This field is required.' : sanitize_text_field($instance['RequiredFieldMessage']);
        $InvalidEmailMessage = empty($instance['InvalidEmailMessage']) ? 'Please enter valid email address.' : sanitize_text_field($instance['InvalidEmailMessage']);
        $InvalidRequestMessage = empty($instance['InvalidRequestMessage']) ? 'Invalid request.' : sanitize_text_field($instance['InvalidRequestMessage']);
        $EmailExistMessage = empty($instance['EmailExistMessage']) ? 'This email is already exist.' : sanitize_text_field($instance['EmailExistMessage']);
        $SuccessMessage = empty($instance['SuccessMessage']) ? 'You have successfully subscribed to our Newsletter!' : sanitize_text_field($instance['SuccessMessage']);
        $WaitMessage = empty($instance['WaitMessage']) ? 'Please wait...' : sanitize_text_field($instance['WaitMessage']);
        $ShowNameField = empty($instance['ShowNameField']) ? 1 : intval($instance['ShowNameField']);
        $show_agreement = empty($instance['show_agreement']) ? 0 : intval($instance['show_agreement']);
        $agreement_text = empty($instance['agreement_text']) ? 'I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>' : $instance['agreement_text'];
        $agreement_error = empty($instance['agreement_error']) ? 'Please read and agree to our terms & conditions.' : sanitize_text_field($instance['agreement_error']);
        $imgUrl = plugin_dir_url(__FILE__) . "images/";
        $loader = $imgUrl . 'AjaxLoader.gif';
        $rand = uniqid('filed_');
        $rand_func = uniqid('fun');
        $wp_news_letter_settings = get_option('wp_news_letter_settings');
        $wp_news_letter_settings = stripslashes_deep($wp_news_letter_settings);
        ?>

                <div class="<?php echo $rand; ?>_AjaxLoader ajaxLoaderWidget"  id="<?php echo $rand; ?>_AjaxLoader"><img src="<?php echo $loader; ?>"/><?php echo $WaitMessage; ?></div>
                <div class="<?php echo $rand; ?>_myerror_msg myerror_msg" id="<?php echo $rand; ?>_myerror_msg"></div>         
                <div class="<?php echo $rand; ?>_mysuccess_msg mysuccess_msg" id="<?php echo $rand; ?>_mysuccess_msg"></div>
                <div class="Nknewsletter_description"><?php echo $Subheading; ?></div>
                <div class="Nknewsletter-widget">
                    <input type="text" name="<?php echo $rand; ?>_youremail" id="<?php echo $rand; ?>_youremail" class="Nknewsletter_email"  value="<?php echo $EmailLabel; ?>" onfocus="return clearInput(this, '<?php echo $EmailLabel; ?>');" onblur="restoreInput(this, '<?php echo $EmailLabel; ?>')"/>
                    <div class="" id="<?php echo $rand; ?>_errorinput_email"></div>

        <?php if ($ShowNameField == "1"): ?>
                        <div class="Nknewsletter_space" id="<?php echo $rand; ?>_name_Nknewsletter_space" ></div>
                        <input type="text" name="<?php echo $rand; ?>_yourname" id="<?php echo $rand; ?>_yourname" class="Nknewsletter_name" value="<?php echo $NameLabel; ?>" onfocus="return clearInput(this, '<?php echo $NameLabel; ?>');" onblur="restoreInput(this, '<?php echo $NameLabel; ?>')" />
                        <div class="errorinput_widget" id="<?php echo $rand; ?>_errorinput_name"></div>
                        <div class="Nknewsletter_space" id="<?php echo $rand; ?>_name_Nknewsletter_space" ></div>   
        <?php else: ?>
                        <div class="Nknewsletter_space" id="<?php echo $rand; ?>_name_Nknewsletter_space" ></div>
        <?php endif; ?>

        <?php if ($show_agreement == "1"): ?>
                        <input class="nk_newsletter_agree" style="display:inline-block" type="checkbox"  id="<?php echo $rand; ?>_agree" value="1" name="<?php echo $rand; ?>_agree" /><span class="nk_newslteer_agree_term"> <?php echo html_entity_decode($agreement_text); ?></span>
                        <div style="clear:both"></div>
                        <div class="errorinput_widget" id="<?php echo $rand; ?>_errorinput_agree"></div>

        <?php else: ?>
                        <div class="Nknewsletter_space" id="<?php echo $rand; ?>_agree_Nknewsletter_space" ></div>
        <?php endif; ?>

                    <div class="Nknewsletter_space" id="<?php echo $rand; ?>submit_space" ></div>
                    <input class="Nknewsletter_space_submit" type="submit" value="<?php echo $SubmitButtonLabel; ?>" onclick="return <?php echo $rand_func; ?>_submit_newsletter();" name="<?php echo $rand; ?>_submit" />
                </div>
                <script>

                    function <?php echo $rand_func; ?>_submit_newsletter() {


                        var emailAdd = jQuery.trim(jQuery("#<?php echo $rand; ?>_youremail").val());
                        var yourname = jQuery.trim(jQuery("#<?php echo $rand; ?>_yourname").val());

                        var returnval = false;
                        var isvalidName = false;
                        var isvalidEmail = false;
                        var isagree = false;

                        if (jQuery("#<?php echo $rand; ?>_yourname").length > 0) {


                            var yourname = jQuery.trim(jQuery("#<?php echo $rand; ?>_yourname").val());

                            if (yourname != "" && yourname != null && yourname.toLowerCase() != '<?php echo $NameLabel; ?>'.toLowerCase()) {

                                var element = jQuery("#<?php echo $rand; ?>_yourname").next().next();
                                isvalidName = true;
                                jQuery(element).html('');
                            } else {
                                var element = jQuery("#<?php echo $rand; ?>_yourname").next().next();
                                jQuery(element).html('<div class="image_error"><?php echo $RequiredFieldMessage; ?></div>');
                                jQuery("#<?php echo $rand; ?>_name_Nknewsletter_space").css({marginBottom: "20px"});
                                // emailAdd=false;

                            }


                        } else {
                            isvalidName = true;

                        }

                        if (jQuery("#<?php echo $rand; ?>_agree").length > 0) {

                            if (jQuery("#<?php echo $rand; ?>_agree").is(':checked')) {

                                var element = jQuery("#<?php echo $rand; ?>_agree").next().next();
                                jQuery(element).html('');
                                isagree = true;
                            } else {


                                var element = jQuery("#<?php echo $rand; ?>_agree").next().next();
                                jQuery(element).html('<div class="image_error"><?php echo $agreement_error; ?></div>');
                                jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});
                                isagree = false;

                            }
                        } else {

                            isagree = true;

                        }


                        if (emailAdd != "") {


                            var element = jQuery("#<?php echo $rand; ?>_youremail").next().next();
                            if (emailAdd.toLowerCase() == '<?php echo $EmailLabel; ?>'.toLowerCase()) {

                                jQuery(element).html('<div  class="image_error"><?php echo $RequiredFieldMessage; ?></div>');
                                isvalidEmail = false;

                                jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});

                            } else {

                                var JsRegExPatern = /^\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/
                                if (JsRegExPatern.test(emailAdd)) {

                                    isvalidEmail = true;
                                    jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "20px"});
                                    jQuery(element).html('');

                                } else {

                                    var element = jQuery("#<?php echo $rand; ?>_youremail").next().next();
                                    jQuery(element).html('<div class="image_error"><?php echo $InvalidEmailMessage; ?></div>');
                                    jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});
                                    isvalidEmail = false;

                                }

                            }

                        } else {

                            var element = jQuery("#<?php echo $rand; ?>_yourname").next().next();
                            jQuery(element).html('<div class="image_error"><?php echo $RequiredFieldMessage; ?></div>');
                            jQuery("#<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "0px"});
                            isvalidEmail = false;

                        }

                        if (isvalidName == true && isvalidEmail == true && isagree == true) {

                            jQuery("#<?php echo $rand; ?>_name_Nknewsletter_space").css({marginBottom: "20px"});
                            jQuery("<?php echo $rand; ?>_email_Nknewsletter_space").css({marginBottom: "20px"});

                            jQuery("#<?php echo $rand; ?>_AjaxLoader").show();
                            jQuery('#<?php echo $rand; ?>_mysuccess_msg').html('');
                            jQuery('#<?php echo $rand; ?>_mysuccess_msg').hide();
                            jQuery('#<?php echo $rand; ?>_myerror_msg').html('');
                            jQuery('#<?php echo $rand; ?>_myerror_msg').hide();

                            var nonce = '<?php echo wp_create_nonce('newsletter-nonce'); ?>';
                            var url = '<?php echo plugin_dir_url(__FILE__); ?>';
                            var email = jQuery("#<?php echo $rand; ?>_youremail").val();
                            var name = "";
                            if (jQuery("#<?php echo $rand; ?>_yourname").length > 0) {

                                name = jQuery("#<?php echo $rand; ?>_yourname").val();
                            }
                            var str = "action=store_email&email=" + email + '&name=' + name + '&is_agreed=' + isagree + '&sec_string=' + nonce;
                            jQuery.ajax({
                                type: "POST",
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                data: str,
                                async: true,
                                success: function (msg) {
                                    if (msg != '') {

                                        var result = msg.split("|");
                                        if (result[0] == 'success') {

                                            jQuery("#<?php echo $rand; ?>_AjaxLoader").hide();
                                            jQuery('.<?php echo $rand; ?>_mysuccess_msg').html(result[1]);
                                            jQuery('.<?php echo $rand; ?>_mysuccess_msg').show();
                                            setTimeout(function () {

                                                jQuery('#<?php echo $rand; ?>_mysuccess_msg').hide();
                                                jQuery('#<?php echo $rand; ?>_mysuccess_msg').html('');
                                                jQuery("#<?php echo $rand; ?>_youremail").val('<?php echo $EmailLabel; ?>');
                                                jQuery("#<?php echo $rand; ?>_yourname").val('<?php echo $NameLabel; ?>');


                                            }, 2000);




                                        } else {
                                            jQuery("#<?php echo $rand; ?>_AjaxLoader").hide();
                                            jQuery('#<?php echo $rand; ?>_myerror_msg').html(result[1]);
                                            jQuery('#<?php echo $rand; ?>_myerror_msg').show();
                                            setTimeout(function () {

                                                jQuery('#<?php echo $rand; ?>_myerror_msg').hide();
                                                jQuery('#<?php echo $rand; ?>_myerror_msg').html('');




                                            }, 2000);

                                        }

                                    }

                                }
                            });

                        }





                    }
                </script>
                <style>
        <?php echo html_entity_decode($wp_news_letter_settings['additional_css'], ENT_QUOTES); ?>
                </style>
        <?php
        echo $after_widget;
    }

    function update($new_instance, $old_instance) {


        $instance = $old_instance;
        $instance['Heading'] = sanitize_text_field($new_instance['Heading']);
        $instance['Subheading'] = sanitize_textarea_field($new_instance['Subheading']);
        $instance['EmailLabel'] = sanitize_text_field($new_instance['EmailLabel']);
        $instance['NameLabel'] = sanitize_text_field($new_instance['NameLabel']);
        $instance['SubmitButtonLabel'] = sanitize_text_field($new_instance['SubmitButtonLabel']);
        $instance['RequiredFieldMessage'] = sanitize_text_field($new_instance['RequiredFieldMessage']);
        $instance['InvalidEmailMessage'] = sanitize_text_field($new_instance['InvalidEmailMessage']);
        $instance['InvalidRequestMessage'] = sanitize_text_field($new_instance['InvalidRequestMessage']);
        $instance['EmailExistMessage'] = sanitize_text_field($new_instance['EmailExistMessage']);
        $instance['SuccessMessage'] = sanitize_text_field($new_instance['SuccessMessage']);
        $instance['WaitMessage'] = sanitize_text_field($new_instance['WaitMessage']);
        $instance['ShowNameField'] = intval($new_instance['ShowNameField']);
        $instance['show_agreement'] = isset($new_instance['show_agreement']) ? intval($new_instance['show_agreement']) : 0;
        $instance['agreement_text'] = trim(strip_tags(stripslashes($new_instance['agreement_text']), '<a><b><p><strong><em><i>'));
        $instance['agreement_error'] = sanitize_text_field($new_instance['agreement_error']);

        return $instance;
    }

    function form($instance) {

        //Defaults
        $instance = wp_parse_args((array) $instance, array(
            'Heading' => 'Subscribe to our newsletter',
            'Subheading' => 'Want to be notified when our article is published? Enter your email address and name below to be the first to know.',
            'EmailLabel' => 'Email',
            'NameLabel' => 'Name',
            'SubmitButtonLabel' => 'SIGN UP FOR NEWSLETTER NOW',
            'RequiredFieldMessage' => 'This field is required.',
            'InvalidEmailMessage' => 'Please enter valid email address.',
            'InvalidRequestMessage' => 'Invalid request.',
            'EmailExistMessage' => 'This email is already exist.',
            'SuccessMessage' => 'You have successfully subscribed to our Newsletter!',
            'WaitMessage' => 'Please wait...',
            'ShowNameField' => "1",
            'show_agreement' => '1',
            'agreement_text' => 'I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>',
            'agreement_error' => 'Please read and agree to our terms & conditions.',
                )
        );
        ?>
                <p>
                    <label for="<?php echo $this->get_field_id('ShowNameField'); ?>"><b><?php echo __('Show Name Field', 'email-subscribe'); ?>:</b></label><br/>
                    <input <?php if ($instance['ShowNameField'] == '1') { ?>checked="checked" <?php } ?> type="radio" name="<?php echo $this->get_field_name('ShowNameField'); ?>"  id="s_type_show_field_yes" value="1"> Yes
                    <input <?php if ($instance['ShowNameField'] == '2') { ?>checked="checked" <?php } ?> type="radio" name="<?php echo $this->get_field_name('ShowNameField'); ?>"   id="s_type_show_field_no" value="2"> No
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('show_agreement'); ?>"><b><?php echo __('Show Agreement', 'email-subscribe'); ?>:</b></label><br/>
                    <input <?php if ($instance['show_agreement'] == '1') { ?>checked="checked" <?php } ?> type="radio" name="<?php echo $this->get_field_name('show_agreement'); ?>"  id="s_type_show_agreement_yes" value="1"> Yes
                    <input <?php if ($instance['show_agreement'] == '2') { ?>checked="checked" <?php } ?> type="radio" name="<?php echo $this->get_field_name('show_agreement'); ?>"   id="s_type_show_agreement_no" value="2"> No
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('Heading'); ?>"><b><?php echo __('Heading:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('Heading'); ?>"
                           name="<?php echo $this->get_field_name('Heading'); ?>" type="text" value="<?php echo $instance['Heading']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('Subheading'); ?>"><b><?php echo __('Subheading:', 'email-subscribe'); ?></b></label><br/>
                    <textarea rows="4" cols="30" name="<?php echo $this->get_field_name('Subheading'); ?>" id="Subheading"><?php echo $instance['Subheading']; ?></textarea>
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('EmailLabel'); ?>"><b><?php echo __('Email Label:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('EmailLabel'); ?>"
                           name="<?php echo $this->get_field_name('EmailLabel'); ?>" type="text" value="<?php echo $instance['EmailLabel']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('NameLabel'); ?>"><b><?php echo __('Name Label:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('NameLabel'); ?>"
                           name="<?php echo $this->get_field_name('NameLabel'); ?>" type="text" value="<?php echo $instance['NameLabel']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('SubmitButtonLabel'); ?>"><b><?php echo __('Submit Button Label:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('SubmitButtonLabel'); ?>"
                           name="<?php echo $this->get_field_name('SubmitButtonLabel'); ?>" type="text" value="<?php echo $instance['SubmitButtonLabel']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('RequiredFieldMessage'); ?>"><b><?php echo __('Required Field Message:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('RequiredFieldMessage'); ?>"
                           name="<?php echo $this->get_field_name('RequiredFieldMessage'); ?>" type="text" value="<?php echo $instance['RequiredFieldMessage']; ?>" />
                </p>

                <p>
                    <label for="<?php echo $this->get_field_id('InvalidEmailMessage'); ?>"><b><?php echo __('Invalid Email Message:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('InvalidEmailMessage'); ?>"
                           name="<?php echo $this->get_field_name('InvalidEmailMessage'); ?>" type="text" value="<?php echo $instance['InvalidEmailMessage']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('InvalidRequestMessage'); ?>"><b><?php echo __('Invalid Request Message:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('InvalidRequestMessage'); ?>"
                           name="<?php echo $this->get_field_name('InvalidRequestMessage'); ?>" type="text" value="<?php echo $instance['InvalidRequestMessage']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('EmailExistMessage'); ?>"><b><?php echo __('Email Exist Message:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('EmailExistMessage'); ?>"
                           name="<?php echo $this->get_field_name('EmailExistMessage'); ?>" type="text" value="<?php echo $instance['EmailExistMessage']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('SuccessMessage'); ?>"><b><?php echo __('Success Message:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('SuccessMessage'); ?>"
                           name="<?php echo $this->get_field_name('SuccessMessage'); ?>" type="text" value="<?php echo $instance['SuccessMessage']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('WaitMessage'); ?>"><b><?php echo __('Wait Message:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('WaitMessage'); ?>"
                           name="<?php echo $this->get_field_name('WaitMessage'); ?>" type="text" value="<?php echo $instance['WaitMessage']; ?>" />
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('agreement_text'); ?>"><b><?php echo __('Agreement Text:', 'email-subscribe'); ?></b></label>
                    <textarea class="widefat" id="<?php echo $this->get_field_id('agreement_text'); ?>"
                              name="<?php echo $this->get_field_name('agreement_text'); ?>" ><?php echo $instance['agreement_text']; ?></textarea>
                </p>
                <p>
                    <label for="<?php echo $this->get_field_id('agreement_error'); ?>"><b><?php echo __('Agreement Message:', 'email-subscribe'); ?></b></label>
                    <input class="widefat" id="<?php echo $this->get_field_id('agreement_error'); ?>"
                           name="<?php echo $this->get_field_name('agreement_error'); ?>" type="text" value="<?php echo $instance['agreement_error']; ?>" />
                </p>

                <?php
            }

// function form
        }

        // widget class

        function store_email_callback() {

            if (isset($_POST['email']) and isset($_POST['name']) and isset($_POST['sec_string'])) {

                $wp_news_letter_settings = get_option('wp_news_letter_settings');
                $nonce = $_POST['sec_string'];
                $is_agreed = sanitize_text_field($_POST['is_agreed']);
                $is_agreed = esc_html($is_agreed);
                if (wp_verify_nonce($nonce, 'newsletter-nonce') and $is_agreed == true) {

                    global $wpdb;
                    $email = sanitize_email($_POST['email']);
                    $name = sanitize_text_field($_POST['name']);
                    $name = esc_html($name);

                    // Free version subscriber limit check
                    $current_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nl_subscriptions WHERE is_subscribed=1");
                    if ( $current_count >= I13_ES_FREE_SUBSCRIBER_LIMIT ) {
                        $wp_news_letter_settings = get_option('wp_news_letter_settings');
                        echo $wp_news_letter_settings['success_msg']; // Show success to user but do not store
                        die();
                    }

                    if (is_email($email)) {


                        $subscribed_on = date('Y-m-d h:i:s');
                        if (function_exists('date_i18n')) {

                            $subscribed_on = date_i18n('Y-m-d' . ' ' . get_option('time_format'), false, false);
                            if (get_option('time_format') == 'H:i')
                                $subscribed_on = date('Y-m-d H:i:s', strtotime($subscribed_on));
                            else
                                $subscribed_on = date('Y-m-d h:i:s', strtotime($subscribed_on));
                        }

                        $query = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'nl_subscriptions where email = %s', array($email));
                        $myrow = $wpdb->get_row($query);

                        if (is_object($myrow)) {

                            echo 'error|' . $wp_news_letter_settings['email_exist'];
                        } else {
                            try {

                                $key = md5(uniqid(rand(), true));

                                $wpdb->insert(
                                        $wpdb->prefix . "nl_subscriptions",
                                        array('name' => $name, 'email' => $email, 'subscribed_on' => $subscribed_on, 'is_subscribed' => 1, 'unsubs_key' => $key),
                                        array('%s', '%s', '%s', '%d', '%s')
                                );
                                // Fire action for Mailchimp sync
                                do_action('i13_es_subscriber_added', $email, $name);
                                echo 'success|' . $wp_news_letter_settings['success'];
                            } catch (Exception $e) {

                                echo 'error|' . $e->getMessage();
                            }
                        }
                    } else {

                        echo 'error|' . $wp_news_letter_settings['iinvalidemail'];
                    }
                } else {

                    echo 'error|' . $wp_news_letter_settings['invalid_request'];
                }
            } else {

                echo 'error|' . $wp_news_letter_settings['invalid_request'];
            }

            die;
        }
        ?>
<?php
// ═══════════════════════════════════════════════════════════════════════════
// FEATURE 1: GDPR — Already built into plugin settings. Highlighted in UI.
// ═══════════════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════════════
// FEATURE 2: MAILCHIMP SYNC (Free — limited to 100 syncs/month)
// ═══════════════════════════════════════════════════════════════════════════

function i13_es_mailchimp_page() {
    if ( ! current_user_can('es_email_subscribe_settings') ) wp_die('Access Denied');

    $api_key  = get_option('i13_es_mc_api_key', '');
    $list_id  = get_option('i13_es_mc_list_id', '');
    $enabled  = get_option('i13_es_mc_enabled', '0');
    $synced   = (int) get_option('i13_es_mc_synced_this_month', 0);
    $sync_limit = 100;
    ?>
    <div class="wrap">
        <h1><?php _e('Mailchimp Sync', 'email-subscribe'); ?> <span style="background:#f0a500;color:#fff;font-size:11px;padding:3px 8px;border-radius:3px;vertical-align:middle;">FREE — 100 syncs/month</span></h1>
        <p style="color:#666;"><?php _e('Automatically sync new subscribers to your Mailchimp audience. Free version is limited to 100 syncs per month.', 'email-subscribe'); ?>
        <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" style="color:#f0a500;font-weight:600;"><?php _e('Upgrade to PRO for unlimited syncs + Brevo, Kit, Klaviyo', 'email-subscribe'); ?></a></p>

        <!-- Sync usage bar -->
        <?php $pct = min(100, round($synced / $sync_limit * 100)); $bar_col = $pct >= 100 ? '#d63638' : ($pct >= 80 ? '#f0a500' : '#00a32a'); ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px 20px;margin-bottom:20px;max-width:600px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
                <span><?php _e('Monthly syncs used','email-subscribe'); ?></span>
                <span style="color:<?php echo $bar_col; ?>;font-weight:600;"><?php echo $synced; ?> / <?php echo $sync_limit; ?></span>
            </div>
            <div style="background:#f0f0f1;border-radius:4px;height:8px;">
                <div style="height:8px;background:<?php echo $bar_col; ?>;width:<?php echo $pct; ?>%;border-radius:4px;"></div>
            </div>
            <?php if($pct >= 100): ?>
            <p style="color:#d63638;font-size:12px;margin:6px 0 0;"><?php _e('Monthly limit reached. Resets on the 1st of next month. Or ', 'email-subscribe'); ?><a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank"><?php _e('upgrade to PRO for unlimited.', 'email-subscribe'); ?></a></p>
            <?php endif; ?>
        </div>

        <!-- Settings form -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px;max-width:600px;">
            <table class="form-table">
                <tr>
                    <th><?php _e('Enable Mailchimp Sync','email-subscribe'); ?></th>
                    <td>
                        <select id="i13_mc_enabled">
                            <option value="1" <?php selected($enabled,'1'); ?>><?php _e('Yes','email-subscribe'); ?></option>
                            <option value="0" <?php selected($enabled,'0'); ?>><?php _e('No','email-subscribe'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Mailchimp API Key','email-subscribe'); ?></label><br><small style="font-weight:400;color:#888;"><?php _e('Found in Mailchimp → Account → Extras → API Keys','email-subscribe'); ?></small></th>
                    <td><input type="text" id="i13_mc_api_key" value="<?php echo esc_attr($api_key); ?>" style="width:100%;max-width:400px;" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us1" /></td>
                </tr>
                <tr>
                    <th><label><?php _e('Audience List ID','email-subscribe'); ?></label><br><small style="font-weight:400;color:#888;"><?php _e('Found in Mailchimp → Audience → Settings → Audience name and defaults','email-subscribe'); ?></small></th>
                    <td><input type="text" id="i13_mc_list_id" value="<?php echo esc_attr($list_id); ?>" style="width:200px;" placeholder="abc123def" /></td>
                </tr>
            </table>
            <p>
                <button class="button button-primary" onclick="i13McSave()"><?php _e('Save Settings','email-subscribe'); ?></button>
                &nbsp;
                <button class="button" onclick="i13McTest()"><?php _e('Test Connection','email-subscribe'); ?></button>
                <span id="i13_mc_msg" style="margin-left:10px;font-size:13px;"></span>
            </p>
        </div>

        <!-- PRO upsell -->
        <div style="background:#fff8e5;border:1px solid #f0a500;border-radius:6px;padding:16px 20px;margin-top:16px;max-width:600px;">
            <strong><?php _e('Want more ESPs?','email-subscribe'); ?></strong>
            <?php _e('PRO version includes Brevo, Kit (ConvertKit) and Klaviyo integrations with unlimited syncs, double opt-in and tag support.','email-subscribe'); ?>
            <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" style="display:inline-block;margin-top:8px;background:#f0a500;color:#fff;padding:6px 14px;border-radius:4px;font-weight:600;text-decoration:none;"><?php _e('Upgrade to PRO →','email-subscribe'); ?></a>
        </div>
    </div>
    <script>
    function i13McSave(){
        var msg = document.getElementById('i13_mc_msg');
        msg.textContent = '<?php _e('Saving...','email-subscribe'); ?>';
        var fd = new FormData();
        fd.append('action','i13_es_mailchimp_save');
        fd.append('nonce','<?php echo wp_create_nonce('i13_mc_nonce'); ?>');
        fd.append('api_key', document.getElementById('i13_mc_api_key').value);
        fd.append('list_id', document.getElementById('i13_mc_list_id').value);
        fd.append('enabled', document.getElementById('i13_mc_enabled').value);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST',body:fd})
            .then(r=>r.json()).then(r=>{
                msg.textContent = r.success ? '<?php _e('Saved!','email-subscribe'); ?>' : (r.data || '<?php _e('Error saving','email-subscribe'); ?>');
                msg.style.color = r.success ? '#00a32a' : '#d63638';
            });
    }
    function i13McTest(){
        var msg = document.getElementById('i13_mc_msg');
        msg.textContent = '<?php _e('Testing...','email-subscribe'); ?>';
        var fd = new FormData();
        fd.append('action','i13_es_mailchimp_test');
        fd.append('nonce','<?php echo wp_create_nonce('i13_mc_nonce'); ?>');
        fd.append('api_key', document.getElementById('i13_mc_api_key').value);
        fd.append('list_id', document.getElementById('i13_mc_list_id').value);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST',body:fd})
            .then(r=>r.json()).then(r=>{
                msg.textContent = r.success ? '<?php _e('Connection successful!','email-subscribe'); ?>' : (r.data || '<?php _e('Connection failed','email-subscribe'); ?>');
                msg.style.color = r.success ? '#00a32a' : '#d63638';
            });
    }
    </script>
    <?php
}

function i13_es_mailchimp_save() {
    check_ajax_referer('i13_mc_nonce','nonce');
    if(!current_user_can('es_email_subscribe_settings')) wp_send_json_error('Access denied');
    update_option('i13_es_mc_api_key', sanitize_text_field($_POST['api_key']));
    update_option('i13_es_mc_list_id', sanitize_text_field($_POST['list_id']));
    update_option('i13_es_mc_enabled',  sanitize_text_field($_POST['enabled']));
    wp_send_json_success();
}

function i13_es_mailchimp_test() {
    check_ajax_referer('i13_mc_nonce','nonce');
    $api_key = sanitize_text_field($_POST['api_key']);
    $list_id = sanitize_text_field($_POST['list_id']);
    if(empty($api_key)) { wp_send_json_error(__('Please enter an API key','email-subscribe')); }
    $dc = substr($api_key, strpos($api_key,'-')+1);
    $response = wp_remote_get("https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}", array(
        'headers' => array('Authorization' => 'Basic '.base64_encode('user:'.$api_key)),
        'timeout' => 10,
    ));
    if(is_wp_error($response)) { wp_send_json_error($response->get_error_message()); }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if(isset($body['id'])) {
        wp_send_json_success(sprintf(__('Connected to audience: %s (%d subscribers)','email-subscribe'), $body['name'], $body['stats']['member_count']));
    } else {
        wp_send_json_error($body['detail'] ?? __('Invalid API key or List ID','email-subscribe'));
    }
}

// Hook new subscriber into Mailchimp after successful subscription
add_action('i13_es_subscriber_added', 'i13_es_sync_to_mailchimp', 10, 2);
function i13_es_sync_to_mailchimp($email, $name) {
    if(get_option('i13_es_mc_enabled') !== '1') return;
    $api_key = get_option('i13_es_mc_api_key','');
    $list_id = get_option('i13_es_mc_list_id','');
    if(empty($api_key) || empty($list_id)) return;

    // Check monthly limit
    $month_key = 'i13_es_mc_synced_'.date('Y_m');
    $synced = (int) get_option($month_key, 0);
    if($synced >= 100) return; // Free limit

    $dc = substr($api_key, strpos($api_key,'-')+1);
    $names = explode(' ', $name, 2);
    wp_remote_post("https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members", array(
        'headers' => array(
            'Authorization' => 'Basic '.base64_encode('user:'.$api_key),
            'Content-Type'  => 'application/json',
        ),
        'body'    => json_encode(array(
            'email_address' => $email,
            'status'        => 'subscribed',
            'merge_fields'  => array(
                'FNAME' => isset($names[0]) ? $names[0] : $name,
                'LNAME' => isset($names[1]) ? $names[1] : '',
            ),
        )),
        'timeout' => 10,
    ));
    update_option($month_key, $synced + 1);
    // Keep backward compat key
    update_option('i13_es_mc_synced_this_month', $synced + 1);
}

// ═══════════════════════════════════════════════════════════════════════════
// FEATURE 3: ONBOARDING WIZARD
// ═══════════════════════════════════════════════════════════════════════════

function i13_es_maybe_redirect_onboarding() {
    if ( get_option('i13_es_show_onboarding') && ! get_option('i13_es_onboarding_done') ) {
        delete_option('i13_es_show_onboarding');
        wp_safe_redirect( admin_url('admin.php?page=i13_es_onboarding') );
        exit;
    }
}

function i13_es_dismiss_onboarding() {
    check_ajax_referer('i13_es_dismiss','nonce');
    update_option('i13_es_onboarding_done', 1);
    wp_send_json_success();
}

// Register onboarding page (hidden from menu)
add_action('admin_menu', 'i13_es_register_onboarding_page');
function i13_es_register_onboarding_page() {
    add_submenu_page(null, __('Setup Email Subscribe','email-subscribe'), '', 'es_email_subscribe_settings', 'i13_es_onboarding', 'i13_es_onboarding_page');
}

function i13_es_onboarding_page() {
    if(!current_user_can('es_email_subscribe_settings')) wp_die('Access Denied');
    // Only mark done if not a preview
    if( empty($_GET['preview']) ) {
        update_option('i13_es_onboarding_done', 1);
    }
    $settings_url  = admin_url('admin.php?page=email_subscription_popup');
    $mailchimp_url = admin_url('admin.php?page=i13_es_mailchimp');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?php _e('Welcome to Email Subscribe!','email-subscribe'); ?></title>
        <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f0f1; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .i13-ob-wrap { max-width: 680px; margin: 40px auto; }
        .i13-ob-header { background: linear-gradient(135deg, #2271b1, #135e96); color: #fff; border-radius: 8px 8px 0 0; padding: 32px 40px; text-align: center; }
        .i13-ob-header h1 { font-size: 26px; margin-bottom: 8px; }
        .i13-ob-header p { opacity: 0.85; font-size: 15px; }
        .i13-ob-body { background: #fff; border-radius: 0 0 8px 8px; padding: 32px 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .i13-ob-step { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px solid #f0f0f1; align-items: flex-start; }
        .i13-ob-step:last-of-type { border-bottom: none; }
        .i13-ob-icon { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .i13-ob-icon.blue { background: #e8f0fb; }
        .i13-ob-icon.green { background: #e8f8ef; }
        .i13-ob-icon.orange { background: #fff8e5; }
        .i13-ob-step h3 { font-size: 15px; margin-bottom: 4px; }
        .i13-ob-step p { font-size: 13px; color: #666; line-height: 1.5; margin-bottom: 8px; }
        .i13-ob-btn { display: inline-block; padding: 7px 16px; border-radius: 4px; font-size: 13px; font-weight: 500; text-decoration: none; cursor: pointer; border: 1px solid #2271b1; color: #2271b1; background: #fff; }
        .i13-ob-btn:hover { background: #f0f6ff; }
        .i13-ob-btn.primary { background: #2271b1; color: #fff; }
        .i13-ob-btn.primary:hover { background: #135e96; }
        .i13-ob-footer { text-align: center; margin-top: 24px; }
        .i13-ob-pro { background: #fff8e5; border: 1px solid #f0a500; border-radius: 6px; padding: 16px 20px; margin-top: 20px; }
        .i13-ob-pro h3 { color: #f0a500; margin-bottom: 8px; }
        .i13-ob-features { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 12px; font-size: 13px; }
        </style>
    </head>
    <body>
    <div class="i13-ob-wrap">
        <div class="i13-ob-header">
            <div style="font-size:48px;margin-bottom:12px;">📧</div>
            <h1><?php _e('Welcome to Email Subscribe!','email-subscribe'); ?></h1>
            <p><?php _e('You are 3 steps away from growing your email list. Let us get you set up quickly.','email-subscribe'); ?></p>
        </div>
        <div class="i13-ob-body">

            <!-- Step 1 -->
            <div class="i13-ob-step">
                <div class="i13-ob-icon blue">⚙️</div>
                <div style="flex:1;">
                    <h3><?php _e('Step 1 — Configure your popup','email-subscribe'); ?></h3>
                    <p><?php _e('Set your heading, subheading, button text, and choose when the popup appears. Takes less than 2 minutes.','email-subscribe'); ?></p>
                    <a href="<?php echo $settings_url; ?>" class="i13-ob-btn primary"><?php _e('Go to Settings →','email-subscribe'); ?></a>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="i13-ob-step">
                <div class="i13-ob-icon green">📧</div>
                <div style="flex:1;">
                    <h3><?php _e('Step 2 — Connect Mailchimp (optional)','email-subscribe'); ?></h3>
                    <p><?php _e('Automatically sync new subscribers to your Mailchimp audience. Free up to 100 syncs per month.','email-subscribe'); ?></p>
                    <a href="<?php echo $mailchimp_url; ?>" class="i13-ob-btn"><?php _e('Setup Mailchimp →','email-subscribe'); ?></a>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="i13-ob-step">
                <div class="i13-ob-icon orange">🔒</div>
                <div style="flex:1;">
                    <h3><?php _e('Step 3 — Enable GDPR checkbox (recommended)','email-subscribe'); ?></h3>
                    <p><?php _e('Add a consent checkbox to your popup for GDPR compliance. Go to Settings and enable "Show Checkbox For Terms and Conditions Agreement".','email-subscribe'); ?></p>
                    <a href="<?php echo $settings_url; ?>#gdpr" class="i13-ob-btn"><?php _e('Enable GDPR →','email-subscribe'); ?></a>
                </div>
            </div>

            <!-- PRO upsell -->
            <div class="i13-ob-pro">
                <h3>⭐ <?php _e('Get even more with PRO','email-subscribe'); ?></h3>
                <div class="i13-ob-features">
                    <span>🎨 <?php _e('6 beautiful popup styles','email-subscribe'); ?></span>
                    <span>📊 <?php _e('Full analytics dashboard','email-subscribe'); ?></span>
                    <span>📧 <?php _e('Brevo, Kit & Klaviyo sync','email-subscribe'); ?></span>
                    <span>🚀 <?php _e('Exit-intent trigger','email-subscribe'); ?></span>
                    <span>♾️ <?php _e('Unlimited subscribers','email-subscribe'); ?></span>
                    <span>📥 <?php _e('Unlimited CSV import','email-subscribe'); ?></span>
                </div>
                <a href="<?php echo I13_ES_PRO_URL; ?>" target="_blank" style="display:inline-block;background:#f0a500;color:#fff;padding:9px 20px;border-radius:4px;font-weight:700;text-decoration:none;">
                    <?php _e('Get PRO Version →','email-subscribe'); ?>
                </a>
            </div>

            <div class="i13-ob-footer">
                <a href="<?php echo $settings_url; ?>" style="color:#888;font-size:13px;"><?php _e('Skip and go to settings','email-subscribe'); ?></a>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
}

// ═══════════════════════════════════════════════════════════════════════════
// GUTENBERG BLOCK — Email Subscribe Form
// ═══════════════════════════════════════════════════════════════════════════

add_action('init', 'i13_es_register_block');
function i13_es_register_block() {
    if ( ! function_exists('register_block_type') ) return; // Gutenberg not available

    // Register block script
    wp_register_script(
        'i13-es-block',
        plugins_url('blocks/block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
        '1.0',
        true
    );

    // Register the block with server-side rendering — all params
    register_block_type('email-subscribe/form', array(
        'editor_script'   => 'i13-es-block',
        'render_callback' => 'i13_es_block_render',
        'attributes'      => array(
            'heading'              => array('type' => 'string',  'default' => ''),
            'subheading'           => array('type' => 'string',  'default' => ''),
            'emailLabel'           => array('type' => 'string',  'default' => ''),
            'nameLabel'            => array('type' => 'string',  'default' => ''),
            'submitButtonLabel'    => array('type' => 'string',  'default' => ''),
            'requiredFieldMessage' => array('type' => 'string',  'default' => ''),
            'invalidEmailMessage'  => array('type' => 'string',  'default' => ''),
            'invalidRequestMessage'=> array('type' => 'string',  'default' => ''),
            'emailExistMessage'    => array('type' => 'string',  'default' => ''),
            'successMessage'       => array('type' => 'string',  'default' => ''),
            'waitMessage'          => array('type' => 'string',  'default' => ''),
            'showName'             => array('type' => 'boolean', 'default' => true),
            'showAgreement'        => array('type' => 'boolean', 'default' => false),
            'agreementText'        => array('type' => 'string',  'default' => ''),
            'agreementError'       => array('type' => 'string',  'default' => ''),
        ),
    ));
}

function i13_es_block_render( $attributes ) {
    // Map block attributes to shortcode instance params
    $instance = array(
        'heading'              => ! empty($attributes['heading'])              ? $attributes['heading']              : '',
        'subheading'           => ! empty($attributes['subheading'])           ? $attributes['subheading']           : '',
        'emaillabel'           => ! empty($attributes['emailLabel'])           ? $attributes['emailLabel']           : '',
        'namelabel'            => ! empty($attributes['nameLabel'])            ? $attributes['nameLabel']            : '',
        'submitbuttonlabel'    => ! empty($attributes['submitButtonLabel'])    ? $attributes['submitButtonLabel']    : '',
        'requiredfieldmessage' => ! empty($attributes['requiredFieldMessage']) ? $attributes['requiredFieldMessage'] : '',
        'invalidemailmessage'  => ! empty($attributes['invalidEmailMessage'])  ? $attributes['invalidEmailMessage']  : '',
        'invalidrequestmessage'=> ! empty($attributes['invalidRequestMessage'])? $attributes['invalidRequestMessage']: '',
        'emailexistmessage'    => ! empty($attributes['emailExistMessage'])    ? $attributes['emailExistMessage']    : '',
        'successmessage'       => ! empty($attributes['successMessage'])       ? $attributes['successMessage']       : '',
        'waitmessage'          => ! empty($attributes['waitMessage'])          ? $attributes['waitMessage']          : '',
        'shownamefield'        => ! empty($attributes['showName'])             ? 1 : 0,
        'show_agreement'       => ! empty($attributes['showAgreement'])        ? 1 : 0,
        'agreement_text'       => ! empty($attributes['agreementText'])        ? $attributes['agreementText']        : '',
        'agreement_error'      => ! empty($attributes['agreementError'])       ? $attributes['agreementError']       : '',
    );

    // Reuse existing shortcode function
    return print_email_subscribe_form_func($instance);
}
