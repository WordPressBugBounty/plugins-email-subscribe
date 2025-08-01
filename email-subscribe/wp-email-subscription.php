<?php
/*
  Plugin Name: Email Subscription Popup
  Plugin URI:https://www.i13websolution.com/product/wordpress-newsletter-subscription-pro-plugin/
  Author URI:https://www.i13websolution.com/
  Description: This is beautiful email subscription modal popup plugin for wordpress.Each time new user visit your site user will see modal popup for email subscription.Even you can setup email subscription form by widget.
  Author:I Thirteen Web Solution
  Version:1.2.26
  Text Domain:email-subscribe
  Domain Path: /languages
 */

add_action('admin_menu', 'email_subscription_popup_admin_menu');
//add_action( 'admin_init', 'email_subscription_popup_admin_admin_init' );
register_activation_hook(__FILE__, 'install_email_subscription_popup_admin');
register_deactivation_hook(__FILE__, 'es_email_subscribe_remove_access_capabilities');
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


    $hook_suffix = add_menu_page(__('Email Subscription', 'email-subscribe'), __('Email Subscription', 'email-subscribe'), 'es_email_subscribe_settings', 'email_subscription_popup', 'email_subscription_popup_admin_options');
    $hook_suffix = add_submenu_page('email_subscription_popup', __('Email Subscription Form Setting', 'email-subscribe'), __('Email Subscription Form Setting', 'email-subscribe'), 'es_email_subscribe_settings', 'email_subscription_popup', 'email_subscription_popup_admin_options');
    $hook_suffix_subscriber = add_submenu_page('email_subscription_popup', __('Manage Subscribers', 'email-subscribe'), __('Manage Subscribers', 'email-subscribe'), 'es_email_subscribe_view_subscribers', 'email_subscription_popup_subscribers_management', 'massEmailToEmail_Subscriber_Func');
    $hook_suffix_unsubscriber = add_submenu_page('email_subscription_popup', __('Unsubscribers List', 'email-subscribe'), __('Unsubscribers List', 'email-subscribe'), 'es_email_subscribe_view_unsubscribers', 'Newssletter-Email-Unsubscriber', 'email_subscription_unsubscribers_func');
    $hook_suffix_shortcode= add_submenu_page('email_subscription_popup', __('Email Subscribe Form Shortcode', 'email-subscribe'), __('Email Subscribe Form Shortcode', 'email-subscribe'), 'es_email_subscribe_view_shortcode', 'Newssletter-Email-Shortcode', 'email_subscription_shortcode_func');

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

                    <div class="postbox"> 
                        <center><h3 class="hndle"><span></span><?php echo __('New AI based DIVI Theme', 'email-subscribe'); ?></h3> </center>
                        <div class="inside">
                            <center><a href="https://www.elegantthemes.com/affiliates/idevaffiliate.php?id=11715&url=80806" target="_blank"><img border="0" src="<?php echo plugins_url('images/divi_300x250.jpg', __FILE__); ?>" width="250" height="250"></a></center>

                            <div style="margin:10px 5px">

                            </div>
                        </div></div>
                    <div class="postbox"> 
                        <center><h3 class="hndle"><span></span><?php echo __('Google For Business', 'email-subscribe'); ?></h3> </center>
                        <div class="inside">
                            <center><a target="_blank" href="https://goo.gl/OJBuHT"><img style="max-width:350px" src="<?php echo plugins_url('images/g-suite-promo-code-4.png', __FILE__); ?>" width="" height="250" border="0"></a></center>
                            <div style="margin:10px 5px">
                            </div>
                        </div></div>

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
        $options['agreement_text'] = trim(htmlentities(strip_tags(stripslashes($_POST['agreement_text']), '<a><b><p><strong><em><i>'), ENT_QUOTES));
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
                            <td>
                                <a target="_blank" title="Donate" href="http://www.i13websolution.com/donate-wordpress_image_thumbnail.php">
                                    <img id="help us for free plugin" height="30" width="90" src="<?php echo plugins_url('images/paypaldonate.jpg', __FILE__); ?>" border="0" alt="help us for free plugin" title="help us for free plugin">
                                </a>
                            </td>
                        </tr>
                    </table> 
                    <span><h3 style="color: blue;"><a target="_blank" href="https://www.i13websolution.com/product/wordpress-newsletter-subscription-pro-plugin/">UPGRADE TO PRO VERSION</a></h3></span>

                    <h2><?php echo __('Settings', 'email-subscribe'); ?></h2>
                    <br>
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
                                                                        <h3 style="font-size: 13px"><label for="show_agreement"><?php echo __('Show Checkbox For Terms and Conditions Agreement', 'email-subscribe'); ?> <span class="required">*</span></label></h3>
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

                <div class="postbox"> 
                    <center><h3 class="hndle"><span></span><?php echo __('New AI based DIVI Theme', 'email-subscribe'); ?></h3> </center>
                    <div class="inside">
                        <center><a href="https://www.elegantthemes.com/affiliates/idevaffiliate.php?id=11715&url=80806" target="_blank"><img border="0" src="<?php echo plugins_url('images/divi_300x250.jpg', __FILE__); ?>" width="250" height="250"></a></center>

                        <div style="margin:10px 5px">

                        </div>
                    </div></div>
                <div class="postbox"> 
                    <center><h3 class="hndle"><span></span><?php echo __('Google For Business', 'email-subscribe'); ?></h3> </center>
                    <div class="inside">
                        <center><a target="_blank" href="https://goo.gl/OJBuHT"><img style="max-width:350px" src="<?php echo plugins_url('images/g-suite-promo-code-4.png', __FILE__); ?>" width="" height="250" border="0"></a></center>
                        <div style="margin:10px 5px">
                        </div>
                    </div></div>

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
                        <td>
                            <a target="_blank" title="Donate" href="http://www.i13websolution.com/donate-wordpress_image_thumbnail.php">
                                <img id="help us for free plugin" height="30" width="90" src="<?php echo plugins_url('images/paypaldonate.jpg', __FILE__); ?>" border="0" alt="help us for free plugin" title="help us for free plugin">
                            </a>
                        </td>
                    </tr>
                </table> 
                <span><h3 style="color: blue;"><a target="_blank" href="https://www.i13websolution.com/product/wordpress-newsletter-subscription-pro-plugin/">UPGRADE TO PRO VERSION</a></h3></span>

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
                                    <td>
                                        <a target="_blank" title="Donate" href="http://www.i13websolution.com/donate-wordpress_image_thumbnail.php">
                                            <img id="help us for free plugin" height="30" width="90" src="<?php echo plugins_url('images/paypaldonate.jpg', __FILE__); ?>" border="0" alt="help us for free plugin" title="help us for free plugin">
                                        </a>
                                    </td>
                                </tr>
                            </table> 
                            <span><h3 style="color: blue;"><a target="_blank" href="https://www.i13websolution.com/product/wordpress-newsletter-subscription-pro-plugin/">UPGRADE TO PRO VERSION</a></h3></span>

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

                        <div class="postbox"> 
                            <center><h3 class="hndle"><span></span><?php echo __('Access All Themes In One Price', 'email-subscribe'); ?></h3> </center>
                            <div class="inside">
                                <center><a href="https://www.elegantthemes.com/affiliates/idevaffiliate.php?id=11715&url=80806" target="_blank"><img border="0" src="<?php echo plugins_url('images/divi_300x250.jpg', __FILE__); ?>" width="250" height="250"></a></center>

                                <div style="margin:10px 5px">

                                </div>
                            </div></div>
                        <div class="postbox"> 
                            <center><h3 class="hndle"><span></span><?php echo __('Google For Business', 'email-subscribe'); ?></h3> </center>
                            <div class="inside">
                                <center><a target="_blank" href="https://goo.gl/OJBuHT"><img style="max-width:350px" src="<?php echo plugins_url('images/g-suite-promo-code-4.png', __FILE__); ?>" width="" height="250" border="0"></a></center>
                                <div style="margin:10px 5px">
                                </div>
                            </div></div>

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