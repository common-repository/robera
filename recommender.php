<?php
/**
 * Plugin Name
 *
 * @package   Robera Recommender
 * @author    Robera Team
 * @copyright 2020 Robera
 * @license   GPLv2 or later
 *
 * @wordpress-plugin
 * Plugin Name:       Robera Recommender
 * Description:       This Plugins help you interact with your WooCommerce users smarter by recommend products and related-products to them according to their preferences.
 * Text Domain:       robera-recommender
 * Version:           1.0.17
 */

namespace Recommender;


if (!defined('RECOMMENDER_PLUGIN_PATH')) {
    define('RECOMMENDER_PLUGIN_PATH', dirname(__FILE__).'/');
    define('RECOMMENDER_PLUGIN_FILE_PATH', __FILE__);
    define('RECOMMENDER_PLUGIN_VERSION', '0.2.0');
    define('RECOMMENDER_PLUGIN_PREFIX', 'recommender');
}


require_once __DIR__ . '/vendor/autoload.php';

require_once RECOMMENDER_PLUGIN_PATH.'core/recommender-plugin.php';
require_once RECOMMENDER_PLUGIN_PATH.'core/recommender-core.php';
require_once RECOMMENDER_PLUGIN_PATH.'core/recommender-admin.php';

const SENTRY_URL = 'http://7b81acae221e4fe08d8b5d6c3871281b@sentry.rooberah.co/3';
const SENTRY_ACTIVITY_PERMISSION = true;

if (class_exists('\Recommender\RecommenderPlugin') && !isset($TESTING)) {
    if (SENTRY_ACTIVITY_PERMISSION) {
	\Sentry\init(['dsn' => SENTRY_URL, 'send_attempts' => 1]);
        \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
            $scope->setTag('site_name', wp_parse_url(get_bloginfo('url'))['host']);
        });
    }

    $options = get_option('recommender_options');
    $recommender = new RecommenderPlugin($options);
    $core = new RecommenderCore();
    $admin = new RecommenderAdmin();
}
