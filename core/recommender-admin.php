<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
defined('ABSPATH') or die('No script kiddies please!');

require_once RECOMMENDER_PLUGIN_PATH . 'core/recommender-client.php';

class RecommenderAdmin
{
    public static $SETTINGS_PAGE_NAME = RECOMMENDER_PLUGIN_PREFIX . "_settings";
    public static $USERS_PAGE = RECOMMENDER_PLUGIN_PREFIX . "_users";

    public function __construct()
    {
        add_action('init', array(&$this, 'registerUserRecommendationsBlock'));

        add_action( 'admin_notices', array(&$this, 'admin_notice') );
        add_action('admin_menu', array(&$this, 'createMenus'));
        add_action('admin_enqueue_scripts', array(&$this, 'enqueueScripts'));

        add_action('rest_api_init', array(&$this, 'restApisRegisteration'));

        $this->client = new RecommenderClient();
    }

     public function admin_notice() {
         $message = $this->client->getAvailability()['information_msg'];
//         $condition = !(isset($_GET['page']) && ($_GET['page']==self::$SETTINGS_PAGE_NAME || $_GET['page']==self::$USERS_PAGE));
         if ($message) {
             wp_enqueue_style('robera-style', plugins_url("static/robera-styles.css", __FILE__));
             require_once('templates/error.php');
         }
    }

    private function getRedirectToSettingsResponse()
    {
        $response = new \WP_REST_Response();
        $response->set_status(302);
        $response->header('Location', '/wp-admin/admin.php?page=' . RecommenderAdmin::$SETTINGS_PAGE_NAME);
        return $response;
    }

    public function restApisRegisteration()
    {
        $can_edit_others = function () {
            return current_user_can('edit_others_posts');
        };
        $allowAll = function () { return true; };

        register_rest_route(RECOMMENDER_PLUGIN_PREFIX . '/v1', '/settings', array(
            'methods' => 'POST',
            'permission_callback' => $can_edit_others,
            'callback' => array($this, 'changeSettings')
        ));

        register_rest_route(RECOMMENDER_PLUGIN_PREFIX . '/v1', '/progress', array(
            'methods' => 'GET',
            'callback' => array($this, 'progressPercent'),
            'permission_callback' => $allowAll
        ));
    }

    public function progressPercent($request)
    {
        $library_actions = ['order_item_copy', 'product_copy', 'user_copy'];
        $target_actions = ['order_items', 'products', 'users'];
        $result = 0;
        $total = 0;
        foreach ($library_actions as $i => $action) {
            $target = get_option('recommender_api_target_' . $target_actions[$i], 0);
            $sent = get_option('recommender_api_progress_' . $library_actions[$i], 0);
            $result += min($target, $sent);
            $total += $target;
        }
        $total = ($total == 0) ? 1 : $total;
        $data = ['result' => ceil($result / $total * 100) . '%'];
        if ($data['result'] == '100%')
            $data['state'] = 'training';
        return rest_ensure_response($data);
    }

    public function changeSettings($request)
    {
        $data = $request->get_body_params();
        if (is_array($data)) {
            $classNames = explode(" ", $data["rel_section_class"]);
            $arr = [];
            for ($i = 0; $i < sizeof($classNames); $i++) {
                $arr[$i] = sanitize_html_class($classNames[$i]);
            }
            $className = join(" ", $arr);
            $checked = isset($data["related"]);
            update_option(RecommenderPlugin::$RECOMMEND_ON_RELATED_PRODUCTS_OPTION_NAME, $checked);
            update_option(RecommenderPlugin::$RECOMMEND_ON_RELATED_PRODUCTS_SECTION_CLASS_OPTION_NAME, $className);
        }
        return $this->getRedirectToSettingsResponse();
    }

    public function registerUserRecommendationsBlock()
    {
        $args = array(
            'posts_per_page' => 4,
            'columns'        => 4,
            'orderby'        => 'rand'
        );
        $columns = max(apply_filters( 'woocommerce_output_related_products_args', $args)['columns'], 10);
        $args = array(
            'limit' => strval($columns),
            'return' => 'ids',
            'offset' => '0'
        );
        $product_ids = wc_get_products($args);
        $products = array();
        $i = 0;
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            $products[$i++] = array(
                "name" => $product->get_data()['name'],
                "id" => $product->get_data()['id'],
                "image_url" => wp_get_attachment_image_url($product->get_image_id(), 'full'),
                "sale_price" => $product->get_data()['sale_price'],
                "regular_price" => $product->get_data()['regular_price'],
                "price" => $product->get_data()['price'],
            );
        }
        wp_enqueue_style('block-style', RecommenderPlugin::$STATIC_FILES_URL . "blockStyle.css");
        wp_register_script(
            'gutenberg-user-recommendation-block',
            RecommenderPlugin::$STATIC_FILES_URL . "block.js",
            array(
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-components',
                'wp-editor',
            )
        );

        wp_localize_script(
            'gutenberg-user-recommendation-block',
            "data",
            array(
                "products" => $products,
                "msg" => esc_html__('Note: The items shown in this block are personalized. It means each person would see a unique content on this block.', 'robera-recommender'),
                "columns" => $columns,
                "default" => min(4, $columns)
            )
        );

        wp_set_script_translations('gutenberg-user-recommendation-block', 'robera-recommender', plugin_dir_path(RECOMMENDER_PLUGIN_FILE_PATH) . 'languages');

        register_block_type('recommender/user-recommendation', array(
            'editor_script' => 'gutenberg-user-recommendation-block',
        ));
    }

    public function createMenus()
    {
        add_menu_page(esc_html__('Robera', 'robera-recommender'), esc_html__('Robera', 'robera-recommender'), 'manage_options', RecommenderAdmin::$SETTINGS_PAGE_NAME, array($this, 'settingsPage'), plugins_url('static/RooBeRah_Logo_Gray_2.svg', __FILE__), 6);
        add_submenu_page(
            RecommenderAdmin::$SETTINGS_PAGE_NAME,
            esc_html__('Dashboard', 'robera-recommender'),
            esc_html__('Dashboard', 'robera-recommender'),
            'manage_options',
            RecommenderAdmin::$SETTINGS_PAGE_NAME,
            array($this, 'settingsPage'));

        // add_submenu_page(RecommenderAdmin::$SETTINGS_PAGE_NAME, esc_html__('Settings', 'robera-recommender'), esc_html__('Settings', "robera-recommender"), 'manage_options', RecommenderAdmin::$SETTINGS_PAGE_NAME, array($this, 'settingsPage'));
        add_submenu_page(
            RecommenderAdmin::$SETTINGS_PAGE_NAME,
            esc_html__('Users', 'robera-recommender'),
            esc_html__('Users', "robera-recommender"),
            'manage_options',
            RecommenderAdmin::$USERS_PAGE,
            array($this, 'usersPage')
        );

    }

    public function settingsPage()
    {
        $server_data = $this->client->getAvailability();
        $filter_type = isset($_GET["filter_type"]) ? $_GET["filter_type"] : "None";
        if (isset($_GET["from_date"])) {
            $from = $_GET["from_date"];
            $to = $_GET["to_date"];
        } else {
            $to = false;
            $from = false;
        }
        wp_enqueue_script(
            'settingsPage',
            RecommenderPlugin::$STATIC_FILES_URL . 'settings.js',
            array('jquery3.1.1')
        );
        $data = $this->client->getOverviewStatistics($from, $to);
        $isRtl = is_rtl();
        $translation = [
            "receiving data" => esc_html__("Receiving Data", "robera-recommender"),
            "training" => esc_html__("Training the Engine", "robera-recommender"),
            "trained" => esc_html__("Ready", "robera-recommender")
        ];
        $tooltip_translation = [
            "receiving data" => esc_html__("We are receiving your data and will work on them ASAP.", "robera-recommender"),
            "training" => esc_html__("Data received. We are studying customers’ behavior based on your data.", "robera-recommender"),
            "trained" => esc_html__("Robera is at your service. Enjoy our recommendation system.", "robera-recommender")
        ];
        global $wp;
        $script_data = [
            'is_rtl' => is_rtl(),
            'filter_type' => $filter_type,
            'from_date' => isset($_GET["from_date"]) ? $_GET["from_date"] : '',
            'to_date' => isset($_GET["to_date"]) ? $_GET["to_date"] : '',
            'home_url' => home_url(add_query_arg(array(), $wp->request)) . "?rest_route=/recommender/v1/progress/",
            'sync_url' => home_url(add_query_arg(array(), $wp->request)) . "?rest_route=/recommender/v1/sync/",
            'progress_url' => RecommenderClient::EVENTS_URL . 'client/get_progress/?site_name=' . $this->client->site_name,
            'state' => isset($data['state']) ? $data['state'] : 'training',
            'translation' => $translation,
            'tooltips' => $tooltip_translation
        ];
        wp_localize_script(
            'settingsPage',
            "data",
            $script_data
        );
        require_once('templates/settings-page.php');
    }

    public function usersPage()
    {
        $server_data = $this->client->getAvailability();
        global $wp;
        $parent_url = home_url(add_query_arg(array(), $wp->request)).'/wp-admin/admin.php?page='.self::$USERS_PAGE;
        $link = home_url(add_query_arg(array(), $wp->request)).'/wp-admin/admin.php';
        $interaction_translation = [
            "purchase" => esc_html__("purchase", "robera-recommender"),
            "login" => esc_html__("login", "robera-recommender"),
            "click_on_recommended" => esc_html__("click on recommended", "robera-recommender"),
            "add_to_cart" => esc_html__("add to cart", "robera-recommender"),
            "view" => esc_html__("view", "robera-recommender"),
            "register" => esc_html__("register", "robera-recommender"),
            "remove_from_cart" => esc_html__("remove from cart", "robera-recommender"),
            "no interaction" => esc_html__("no interaction", "robera-recommender")
        ];
        if (isset($_GET['user_id']) && $server_data['permission']) {
            $user_id = $_GET['user_id'];
            $current_user_page = isset($_GET['user_page']) ? $_GET['user_page'] : 1;
            $data = $this->client->getUserProfile($current_user_page, $user_id);
            $tabs = [
                'user_id'=>'registered',
                'anonymous_id'=>'anonymous'
            ];
            $tab = $tabs[$data['type']];
            $users_page_num = $data['page_nums'];
            require_once('templates/user-page.php');
        } else {
            if  ($server_data['permission']) {
                $overview = $this->client->getUsersStatistics();
                wp_enqueue_script(
                    'usersPage',
                    RecommenderPlugin::$STATIC_FILES_URL.'users.js',
                    array('jquery3.1.1')
                );
                wp_enqueue_script('pieChart', "https://canvasjs.com/assets/script/canvasjs.min.js");
                // overview
                $num_all_users = $overview['num_users'] + $overview['num_anonymous_users'];
                $anonymous_users_percentage = $overview['num_anonymous_users'] * 100 / $num_all_users;
                $registered_users_percentage = $overview['num_users'] * 100 / $num_all_users;
                // users
                $queries = $_GET;
                $queries['tab'] = $tab = isset($queries['tab']) ? $queries['tab'] : 'registered';
                $queries['user_page'] = $current_user_page = isset($queries['user_page']) ? $queries['user_page'] : 1;
                if ($tab == 'registered') {
                    $data = $this->client->getUsers($queries);
                    for ($i = 0; $i < sizeof($data['results']); $i++) {
                        $session_handler = new \WC_Session_Handler();
                        $session = $session_handler->get_session(intval($data['results'][$i]['identifier']));
                        $data['results'][$i]['cart_value'] = maybe_unserialize($session['cart_totals'])['total'];
                    }
                } else {
                    $data = $this->client->getAnonymousUsers($queries);
                }
                $users_page_num = $data['page_nums'];
                $users = $data['results'];
            }
            require_once('templates/users-page.php');
        }
    }

    public function enqueueScripts($hook_suffix)
    {
        if (strpos($hook_suffix, RECOMMENDER_PLUGIN_PREFIX) !== false) {
            wp_register_script('jquery3.1.1', RecommenderPlugin::$STATIC_FILES_URL . 'jquery.min.js', array(), null, false);
            wp_add_inline_script('jquery3.1.1', 'var jQuery3_1_1 = $.noConflict(true);');
            wp_enqueue_style('robera-style', plugins_url("static/robera-styles.css", __FILE__));
            wp_enqueue_style('jquery-theme', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            if (is_rtl()) {
                wp_enqueue_style('semantic-style-rtl', RecommenderPlugin::$STATIC_FILES_URL . "semantic.rtl.min.css");
            } else {
                wp_enqueue_style('semantic-style', RecommenderPlugin::$STATIC_FILES_URL . "semantic.min.css");
            }
            wp_enqueue_script('semantic-js', RecommenderPlugin::$STATIC_FILES_URL . "semantic.min.js", array('jquery3.1.1'));
//            wp_enqueue_style('new_semantic_version', "https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.4.1/semantic.min.css");
        }
    }
}
