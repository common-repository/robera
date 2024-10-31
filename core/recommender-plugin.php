<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
use Firebase\JWT\JWT;

defined('ABSPATH') or die('No script kiddies please!');

require_once RECOMMENDER_PLUGIN_PATH.'core/recommender-client.php';
require_once RECOMMENDER_PLUGIN_PATH.'core/background_user_copy.php';
require_once RECOMMENDER_PLUGIN_PATH.'core/background_general_interaction_copy.php';
require_once RECOMMENDER_PLUGIN_PATH.'core/background_order_item_copy.php';
require_once RECOMMENDER_PLUGIN_PATH.'core/background_product_copy.php';
require_once RECOMMENDER_PLUGIN_PATH.'core/utils.php';

class RecommenderPlugin
{
    const CLIENT_SECRET_OPTION = "recommender_api_client_secret";
    const CLIENT_SECRET_SENT_OPTION = "recommender_api_client_secret_sent";
    const REDIRECT_ON_ACTIVATION_OPTION_NAME = RECOMMENDER_PLUGIN_PREFIX."do_activation_redirect";
    const PRODUCT_UPDATE_OPTION = RECOMMENDER_PLUGIN_PREFIX.'_product_update_done';

    public static $RECOMMEND_ON_RELATED_PRODUCTS_OPTION_NAME = RECOMMENDER_PLUGIN_PREFIX . "recommend_on_related";
    public static $RECOMMEND_ON_RELATED_PRODUCTS_SECTION_CLASS_OPTION_NAME = RECOMMENDER_PLUGIN_PREFIX . "related_products_section_class_name";
    public static $STATIC_FILES_URL = "https://api.rooberah.co/plugin-statics/";
    public static $COOKIE_KEY = RECOMMENDER_PLUGIN_PREFIX.'anonymous_id';

    public function __construct($options)
    {
        $this->has_woocommerce = in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins'))
        );
        $this->is_cookie_set=false;
        $this->cookie_key = '';

        $this->bucket_size = 100;
        $this->client = new RecommenderClient($options);

        add_action('plugins_loaded', array(&$this, 'loadTranslationFiles'));

        $this->options = $options;
        $this->options['post_type'] = 'product';

        $this->bg_user_copy = new RecommenderBackgroundUserCopy();
        $this->bg_order_item_copy = new RecommenderBackgroundOrderItemCopy();
        $this->bg_product_copy = new RecommenderBackgroundProductCopy();
        $this->bg_interaction_copy = new RecommenderBackgroundGeneralInteractionCopy();

        add_action('admin_init', array(&$this, 'sendData'));
        add_action('admin_init', array($this, 'my_plugin_redirect'));
        add_action('plugins_loaded', array(&$this, 'checkSendSecret'));
//        add_action('wp', array(&$this, 'sendProductView'));
        add_action('wp_enqueue_scripts', array(&$this, 'enqueueScripts'));
        add_action('rest_api_init', array(&$this, 'restApis'));
        add_action('save_post_product', array(&$this, 'sendNewProduct'), 10, 3);
        add_action('user_register', array(&$this, 'sendNewUser'), 10, 1);
        add_action('wp', array(&$this, 'getAnonymousID'));
        if (!get_option(RECOMMENDER_PLUGIN_PREFIX.'_email_admin_sent', false))
            add_action('wp', array(&$this, 'sendAdminEmail'));
        if (!get_option(self::PRODUCT_UPDATE_OPTION, false)){
            add_action('wp', array(&$this, 'onceUpdateProducts'));
        }
        add_action('wp_login', array(&$this, 'loginFunction'));

        register_activation_hook(RECOMMENDER_PLUGIN_FILE_PATH, array($this, 'recommenderActivate'));
        register_deactivation_hook(RECOMMENDER_PLUGIN_FILE_PATH, array($this, 'recommenderDeactivate'));
        register_uninstall_hook(RECOMMENDER_PLUGIN_FILE_PATH, array('\Recommender\RecommenderPlugin', 'uninstall'));

        if ($this->has_woocommerce) {
            add_action( 'woocommerce_order_status_changed', array(&$this, 'order_status_changed'), 10, 4 );
//            add_action('woocommerce_order_status_completed', array(&$this, 'sendBuyData'), 10, 1);
            add_action('woocommerce_add_to_cart', array(&$this, 'sendCartProduct'), 10, 6);
            add_action('woocommerce_remove_cart_item', array(&$this, 'sendRemoveFromCartInteraction'), 10, 2);
//            add_action('woocommerce_cart_item_removed', array(&$this, 'sendRemoveFromCartInteraction'), 10, 2);
            add_action('woocommerce_after_cart_item_quantity_update', array(&$this, 'changeCartItemQuantity'), 10, 4 );
            add_filter('woocommerce_hidden_order_itemmeta', array(&$this,'custom_woocommerce_hidden_order_itemmeta'), 10, 1);
            add_action( 'woocommerce_thankyou', array(&$this, 'change_product_status'), 10, 1 );
        }
    } // end of method -> __construct


    public function restApis()
    {
        $allowAll = function () { return true; };

        register_rest_route(RECOMMENDER_PLUGIN_PREFIX . '/v1', '/sync', array(
            'methods' => 'GET',
            'callback' => array($this, 'syncData'),
            'permission_callback' => $allowAll
        ));
    }

    public function syncData($request)
    {
        $this->sendData();
        $data = [
            'data'=> 'success'
        ];
        return rest_ensure_response($data);
    }


    function change_product_status($order_id){
        if(!$order_id)
            return;
        $items = wc_get_order($order_id)->get_items();
        foreach ($items as $item) {
            $item_id = $item->get_product_id();
            $this->bg_product_copy->pushToQueue($item_id);
            $this->bg_product_copy->save()->dispatch();
        }
    }

    function sendAdminEmail(){
        if (get_option(self::CLIENT_SECRET_SENT_OPTION, false) && !get_option(RECOMMENDER_PLUGIN_PREFIX.'_email_admin_sent', false))
            $this->client->sendClientEmail();
    }

    function onceUpdateProducts(){
        if (get_option(self::PRODUCT_UPDATE_OPTION, false)) {
            return;
        } elseif (!get_option($this->bg_product_copy->first_data_transfer_option, false)) {
            $args = array(
                'limit' => '-1',
                'return' => 'ids',
            );
            $product_ids = wc_get_products($args);

            foreach (array_values($product_ids) as $i => $id) {
                $this->bg_product_copy->pushToQueue($id);
                if (($i + 1) % $this->bucket_size == 0) {
                    $this->bg_product_copy->save();
                }
            }
            $this->bg_product_copy->save()->dispatch();
        }
        update_option(self::PRODUCT_UPDATE_OPTION, true);
    }

    function custom_woocommerce_hidden_order_itemmeta($arr) {
        $arr[] = 'anonymous_id';
        $arr[] = '_anonymous_id';
        return $arr;
    }


    function my_plugin_redirect() {
        if (get_option(self::REDIRECT_ON_ACTIVATION_OPTION_NAME, false)) {
            delete_option(self::REDIRECT_ON_ACTIVATION_OPTION_NAME);
            wp_redirect(get_admin_url()."?page=".RecommenderAdmin::$SETTINGS_PAGE_NAME);
        }
    }


    public function order_status_changed( $order_id, $transition_from, $transition_to, $instance ) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        foreach ($items as $item) {
            if (strlen($item->get_meta('_anonymous_id')) == 0) {
                $item->add_meta_data('_anonymous_id', $this->getAnonymousID());
                $item->save_meta_data();
            }
            $this->bg_order_item_copy->pushToQueue($item->get_id());
        }
        $this->bg_order_item_copy->save()->dispatch();
    }

    public function getAnonymousID(){
        $domain = COOKIE_DOMAIN ? COOKIE_DOMAIN : $_SERVER['HTTP_HOST'];
        $key = self::$COOKIE_KEY;
        if (!isset($_COOKIE[$key]) && !$this->is_cookie_set)
        {
            global $wp;
            $current_url = home_url( add_query_arg( array(), $wp->request ) );
            $cookie_key = utils::randomString(128);
            $this->is_cookie_set = true;
            $this->cookie_key=$cookie_key;
            setcookie(
                $key,
                $cookie_key,
                time() + YEAR_IN_SECONDS,
                '/',
                $domain,
                strpos($current_url, 'https') !== false
            );
        }
        elseif (isset($_COOKIE[$key]))
            $cookie_key = $_COOKIE[$key];
        elseif ($this->is_cookie_set)
            $cookie_key = $this->cookie_key;
        return $cookie_key;
    }

    public function loginFunction($login){
        $user_id =get_user_by('login', $login)->ID;
        $anonymous_id = $this->getAnonymousID();
        $this->bg_interaction_copy->pushToQueue(array(
            $user_id,
            null,
            'login',
            $this->client->getEventTime(null),
            $anonymous_id
        ));
        $this->bg_interaction_copy->save()->dispatch();
    }

    public function enqueueScripts()
    {
        if (!is_admin()) {
            wp_register_script('jquery3.1.1', RecommenderPlugin::$STATIC_FILES_URL."jquery.min.js", array(), null, false);
            wp_add_inline_script('jquery3.1.1', 'try {var jQuery3_1_1 = $.noConflict(true);}catch (e) {try{var jQuery3_1_1 = jQuery.noConflict(true);}catch(e){}}');
            wp_register_script('timeme', RecommenderPlugin::$STATIC_FILES_URL.'timeme.min.js');
            wp_enqueue_script(RECOMMENDER_PLUGIN_PREFIX.'recommender-js', RecommenderPlugin::$STATIC_FILES_URL."recommender.js", array('jquery3.1.1', 'timeme'), null, false);
            $translations = [
                'P'=>esc_html__('Powered by', 'robera-recommender'),
                'R'=>esc_html__('rooberah', 'robera-recommender'),
                'permission'=> $this->client->getAvailability()['p_permission']
            ];
            wp_localize_script(
                RECOMMENDER_PLUGIN_PREFIX.'recommender-js', 'translations', $translations
            );
            $product_id = null;
            if (is_product()) {
                global $product;
                if (!is_object($product)) {
                    $product = wc_get_product(get_the_ID());
                }
                $product_id = $product->get_id();
            }
            $site_name = wp_parse_url(get_bloginfo('url'))['host'];
            $secret = get_option(self::CLIENT_SECRET_OPTION);
            $interactionIds = utils::generateInteractionIds(5, $site_name, $secret, 32);
            $view_interaction = utils::generateInteractionIds(1, $site_name, $secret, 32)[0];
            wp_localize_script(
                RECOMMENDER_PLUGIN_PREFIX.'recommender-js',
                'recommender_info',
                array(
                    'interaction_url' => RecommenderClient::EVENTS_URL."interaction/",
                    'site_name' => wp_parse_url(get_bloginfo('url'))['host'],
                    'user_id' => get_current_user_id(),
                    'is_product' => is_product(),
                    'product_id' => $product_id,
                    'jwt_pool' => $interactionIds,
                    'time' => $this->client->getEventTime(),
                    'anonymous_id' => $this->getAnonymousID(),
                    'view_interaction'=> $view_interaction,
                    'related_products_section_class' => get_option(RecommenderPlugin::$RECOMMEND_ON_RELATED_PRODUCTS_SECTION_CLASS_OPTION_NAME) != ""?get_option(RecommenderPlugin::$RECOMMEND_ON_RELATED_PRODUCTS_SECTION_CLASS_OPTION_NAME):"related products"
                )
            );
        }
    }

    public function loadTranslationFiles()
    {
        load_plugin_textdomain('robera-recommender', false, basename(RECOMMENDER_PLUGIN_PATH) . '/languages/');
    }

    public function registerUserRecommendationsBlock()
    {
        // automatically load dependencies and version
        // $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');

        wp_register_script(
            'gutenberg-user-recommendation-block',
            RecommenderPlugin::$STATIC_FILES_URL."block.js",
            array(
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-components',
                'wp-editor',
            )
        );

        wp_set_script_translations('gutenberg-user-recommendation-block', 'robera-recommender', plugin_dir_path(RECOMMENDER_PLUGIN_FILE_PATH) . 'languages');

        register_block_type('recommender/user-recommendation', array(
            'editor_script' => 'gutenberg-user-recommendation-block',
        ));
    }

    public function register()
    {
    }

    public function trySendingOnce($action, $func, $target)
    {
        $sent_option_key = 'recommender_api_sent_' . $action;
        $target_key = 'recommender_api_target_' . $action;
        add_option($target_key, $target);

        if (get_option($sent_option_key) < get_option($target_key) && !get_site_transient('recommender_api_lock_' . $action)) {
            try {
                set_site_transient('recommender_api_lock_' . $action, microtime(), 120);
                $func($sent_option_key);
                delete_site_transient('recommender_api_lock_' . $action);
            } catch (\Exception $e) {
                error_log($e);
                delete_option($sent_option_key);
            }
        }
    }

    public function sendData()
    {
        $this->trySendingOnce("users", [&$this, "addAllUsersBackground"], count_users()['total_users']);

        $args = array(
            'limit' => '-1',
            'return' => 'ids'
        );
        $product_ids = wc_get_products($args);
        $this->trySendingOnce("products", [&$this, "addAllProductsBackground"], count($product_ids));

        $query = new \WC_Order_Query(array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids'
        ));
        $orders = $query->get_orders();
        $this->trySendingOnce("orders", [&$this, "addAllOrderItemsBackground"], count($orders));
    }

    private function checkProductInCart($pid){
        if ( ! WC()->cart->is_empty() ) {
            foreach(WC()->cart->get_cart() as $cart_item ) {
                if ($cart_item['product_id'] == $pid)
                    if ($cart_item['quantity'] > 1)
                        return true;
                    else
                        return false;
            }
        }
        return false;
    }

    public function sendCartProduct( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ){
        $user_id = get_current_user_id();
        if (!$this->checkProductInCart($product_id)) {
            $this->bg_interaction_copy->pushToQueue(array(
                $user_id,
                $product_id,
                'add_to_cart',
                $this->client->getEventTime(null),
                $this->getAnonymousID(),
                $quantity
            ));
            $this->bg_interaction_copy->save()->dispatch();
        }
    }

    public function sendRemoveFromCartInteraction( $cart_item_key,  $instance ){
        $product = $instance->removed_cart_contents[ $cart_item_key ];
        $product_id = $product[ 'product_id' ];
        $quantity = $product['quantity'];
        $user_id = get_current_user_id();
        $this->bg_interaction_copy->pushToQueue(array(
            $user_id,
            $product_id,
            'remove_from_cart',
            $this->client->getEventTime(null),
            $this->getAnonymousID(),
            $quantity
        ));
        $this->bg_interaction_copy->save()->dispatch();
    }

    function changeCartItemQuantity( $cart_item_key, $quantity, $old_quantity, $cart ){
        $product = $cart->cart_contents[ $cart_item_key ];
        $product_id = $product[ 'product_id' ];
        if($product_id) {
            $quantity = $quantity - $old_quantity;
            $user_id = get_current_user_id();
            if ($quantity < 0)
                $type = 'remove_from_cart';
            else
                $type = 'add_to_cart';
            $quantity = abs($quantity);
            $this->bg_interaction_copy->pushToQueue(array(
                $user_id,
                $product_id,
                $type,
                $this->client->getEventTime(null),
                $this->getAnonymousID(),
                $quantity
            ));
            $this->bg_interaction_copy->save()->dispatch();
        }
    }

    public function sendProductView()
    {
        $user_id =get_current_user_id();
        $product_id = null;
        if (is_product()) {
            global $product;
            if (!is_object($product)) {
                $product = wc_get_product(get_the_ID());
            }
            $product_id = $product->get_id();
        }
        if (!$product_id) {
            return;
        }
        $this->bg_interaction_copy->pushToQueue(array(
            $user_id,
            $product_id,
            'view',
            $this->client->getEventTime(null),
            $this->getAnonymousID()
        ));
        $this->bg_interaction_copy->save()->dispatch();
    }

    public function sendNewProduct($product_id, $product_post, $update)
    {
        if (!$update) {
            $this->bg_product_copy->addCandidateProduct($product_id);
        }
        try{
            $condition = $this->bg_product_copy->checkProductIsCandidate($product_id);
        }catch (\Exception $e){
            $condition = false;
        }
        if (($product_post->post_status == 'publish' && $condition) || ($update && !$condition)) {
            $this->bg_product_copy->pushToQueue($product_id);
            $this->bg_product_copy->save()->dispatch();
            $this->bg_product_copy->removeCandidateProduct($product_id);
        }
    }

    public function checkSendSecret() {
        $secret_sent = get_option(self::CLIENT_SECRET_SENT_OPTION);
        if ($secret_sent)
            return;
        error_log("[RECOMMENDER] --- Trying to send client secret.");
        $secret = get_option(self::CLIENT_SECRET_OPTION);
        update_option(self::CLIENT_SECRET_SENT_OPTION, $this->client->sendClientSecret($secret));
    }

    public function sendNewUser($user_id)
    {
        $a_id = $this->getAnonymousID();
        $this->bg_user_copy->pushToQueue($user_id);
        $this->bg_user_copy->save()->dispatch();
        $this->bg_interaction_copy->pushToQueue(array(
            $user_id,
            null,
            'register',
            $this->client->getEventTime(null),
            $a_id
        ));
        $this->bg_interaction_copy->save()->dispatch();
    }

    public function sendBuyData($order_id)
    {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        foreach ($items as $item) {
            $this->bg_order_item_copy->pushToQueue($item->get_id());
        }
        $this->bg_order_item_copy->save()->dispatch();
    }

    public function recommenderActivate()
    {
        $secret = get_option(self::CLIENT_SECRET_OPTION);
        if (!$secret) {
            $secret = utils::randomString(256);
            add_option(self::CLIENT_SECRET_OPTION, $secret);
        }

        add_option(self::CLIENT_SECRET_SENT_OPTION, $this->client->sendClientSecret($secret));
        add_option(self::REDIRECT_ON_ACTIVATION_OPTION_NAME, true);

        error_log("client_secret_sent after activation: " . get_option('self::CLIENT_SECRET_SENT_OPTION') ? "true" : "false");

        error_log("activate with secret token: $secret");
    }

    public function recommenderDeactivate()
    {

        error_log("deactivate");

        $this->bg_user_copy->cancelProcess(true);
        $this->bg_order_item_copy->cancelProcess(true);
        $this->bg_product_copy->cancelProcess(true);
    }

    public static function uninstall()
    {
        global $wpdb;

        $plugin_options = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'recommender%'");

        foreach ($plugin_options as $option) {
            delete_option($option->option_name);
        }
    }

    // Add all users using background processing
    public function addAllUsersBackground($sent_option_key)
    {
        $sent = get_option($sent_option_key) ? get_option($sent_option_key) : 0;

        $args = array(
            'fields'      => 'ids',
            'offset'      => $sent
        );

        $user_ids = get_users($args);

        // Array of WP_User objects.

        foreach (array_values($user_ids) as $i => $id) {
            $this->bg_user_copy->pushToQueue($id);
            if (($i + 1) % $this->bucket_size == 0) {
                $this->bg_user_copy->save();
                update_option($sent_option_key, $i + 1);
            }
        }

        update_option($sent_option_key, count($user_ids));

        $this->bg_user_copy->save()->dispatch();
    } //end-of-method add_all_users_background()

    public function addAllProductsBackground($sent_option_key)
    {
        if (!$this->has_woocommerce) {
            error_log("Woocommerce is not installed");
            return;
        }
        $sent = get_option($sent_option_key) ? get_option($sent_option_key) : 0;
        $args = array(
            'limit' => '-1',
            'return' => 'ids',
            'offset' => $sent
        );
        $product_ids = wc_get_products($args);

        foreach (array_values($product_ids) as $i => $id) {
            $this->bg_product_copy->pushToQueue($id);
            if (($i + 1) % $this->bucket_size == 0) {
                $this->bg_product_copy->save();
                update_option($sent_option_key, $i + 1);
            }
        }
        update_option($sent_option_key, count($product_ids));

        $this->bg_product_copy->save()->dispatch();
    }

    public function addAllOrderItemsBackground($sent_option_key)
    {
        if (!$this->has_woocommerce) {
            error_log("Woocommerce is not installed");
            return;
        }

        $sent = get_option($sent_option_key) ? get_option($sent_option_key) : 0;
        do {
            $query = new \WC_Order_Query(array(
                'limit' => $this->bucket_size,
                'orderby' => 'date',
                'order' => 'DESC',
                'offset' => $sent
            ));
            $orders = $query->get_orders();
            foreach (array_values($orders) as $i => $order) {
                $items = $order->get_items();
                update_option(
                    'recommender_api_target_order_items',
                    count($items)+get_option('recommender_api_target_order_items', 0)
                );
                foreach ($items as $item) {
                    $this->bg_order_item_copy->pushToQueue($item->get_id());
                }
            }
            $this->bg_order_item_copy->save();
            $sent += count($orders);
            update_option($sent_option_key, $sent);
        } while (count($orders) != 0);
        $this->bg_order_item_copy->save()->dispatch();
    }
} // end of class --> Recomendo_Plugin
