<?php

namespace Recommender;

defined('ABSPATH') or die('No script kiddies please!');

class RecommenderClient
{
    const DATE_TIME_FORMAT = \DateTime::ISO8601;
    const TIMEOUT = 10;
    const HTTPVERSION = '1.1';
    const CLIENT_DUPLICATE_STATUS = "CLIENT_DUPLICATE";
    const EVENTS_URL = 'https://api.rooberah.co/recommender/api/v1/';


    /**
     * Sets client basic information
     */
    public function __construct($options = null)
    {
        if (!$options) {
            $options = get_option('recommender_options');
        }

        $this->site_name = wp_parse_url(get_bloginfo('url'))['host'];
        $this->client_secret = $options && array_key_exists('recommender_api_client_secret', $options) ? $options['recommender_api_client_secret'] : '';
        $this->client_id = $options && array_key_exists('client_id', $options) ? $options['client_id'] : '';
        $this->client_secret = get_option(RecommenderPlugin::CLIENT_SECRET_OPTION);
        global $wp_version;
        $this->user_agent = $this->site_name . ' WordPress/' . $wp_version . ' - ' .
            'Recommender/' . RECOMMENDER_PLUGIN_VERSION;
    }

    /**
     * Get header
     *
     * @return array request header
     */
    public function getHeader($use_authorization = true)
    {
        $header = array(
            'Content-Type' => 'application/json',
            'User-Agent' => $this->user_agent,
            'Accept-Encoding' => 'gzip',
        );
        if ($use_authorization)
            $header['Authorization'] = "SECRET " . $this->site_name . "#" . $this->client_secret;

        return $header;
    }

    /**
     * Get client token empty for now
     *
     * @param boolean $client_id client id
     * @param boolean $client_secret client secret
     *
     * @return string                 result token
     */
    public function getToken($client_id, $client_secret)
    {
        return '';
    }

    /**
     * Gets event time in the proper format
     *
     * @param object $event_time the default event time
     *
     * @return object             result event time
     */
    public function getEventTime($event_time = null)
    {
        $result = $event_time;
        if (!isset($event_time)) {
            $result = current_time('mysql');
        }
        return $result;
    }

    public function getRecommendationsForUserProduct($uid, $pid, $aid, $num_products = 4)
    {
        $url = self::EVENTS_URL . 'recommend/recommend_to_user_on_item/';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(),
                'body' => json_encode(
                    [
                        'site_name' => $this->site_name,
                        'user_id' => $uid,
                        'item_id' => $pid,
                        'num_products' => $num_products,
                        'anonymous_id' => $aid
                    ]
                )
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting recommendations for user on product page.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[RECOMMENDER] --- Error getting recommendations for user on product page.");
            error_log("[RECOMMENDER] --- " . $error_body);
            return array();
        }
        return json_decode($response["body"], true)["recommendations"];
    }

    public function getRecommendationsForUser($uid, $aid, $num_products = 4)
    {
        $url = self::EVENTS_URL . 'recommend/recommend_to_user/';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(),
                'body' => json_encode(
                    [
                        'site_name' => $this->site_name,
                        'user_id' => $uid,
                        'num_products' => $num_products,
                        'anonymous_id' => $aid
                    ]
                )
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting recommendations for user.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[RECOMMENDER] --- Error getting recommendations for user.");
            error_log("[RECOMMENDER] --- " . $error_body);
            return array();
        }
        return json_decode($response["body"], true)["recommendations"];
    }

    public function getOverviewStatistics($from, $to)
    {
        $queries = array("site_name" => $this->site_name);
        if ($from && $to) {
            $queries["date[from]"] = $from;
            $queries["date[to]"] = $to;
        }
        $url = self::EVENTS_URL . 'statistic/overview/?' . http_build_query($queries);
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader()
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting overview statistics.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[RECOMMENDER] --- Error getting overview statistics.");
            error_log("[RECOMMENDER] --- " . $error_body);
            return array();
        }
        return json_decode($response["body"], true);
    }

    public function getUserProfile($page, $user_id)
    {
        $queries = array("site_name" => $this->site_name, "page" => $page, "id" => $user_id);
        $url = self::EVENTS_URL . 'user/user_profile/?' . http_build_query($queries);
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader()
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting users list.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }
        return json_decode($response["body"], true);
    }

    public function getUsers($query)
    {
        $query['site_name'] = $this->site_name;
        $query['page'] = $query['user_page'];
        $url = self::EVENTS_URL . 'user/get_users/?' . http_build_query($query);
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader()
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting users list.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }
        return json_decode($response["body"], true);
    }

    public function getAnonymousUsers($query)
    {
        $query['site_name'] = $this->site_name;
        $query['page'] = $query['user_page'];
        $url = self::EVENTS_URL . 'user/get_anon_users/?' . http_build_query($query);
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader()
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting anonymous users list.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }
        return json_decode($response["body"], true);
    }

    public function getUsersStatistics()
    {
        $queries = array("site_name" => $this->site_name);
        $url = self::EVENTS_URL . 'statistic/users_overview/?' . http_build_query($queries);
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader()
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting users overview statistics.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }
        return json_decode($response["body"], true);
    }

    public function getAvailability()
    {
        $queries = array("site_name" => $this->site_name);
        $url = self::EVENTS_URL . 'statistic/availability/?' . http_build_query($queries);
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader()
            )
        );
        if (is_wp_error($response)) {
            error_log("[RECOMMENDER] --- Error getting users overview statistics.");
            error_log("[RECOMMENDER] --- " . $response->get_error_message());
            return array();
        }
        return json_decode($response["body"], true);
    }

    /**
     * Set a user entity
     *
     * @param int|string $uid User Id
     * @param array $properties Properties of the user entity to set
     * @param string $event_time Time of the event in ISO 8601 format
     *                               (e.g. 2014-09-09T16:17:42.937-08:00).
     *                               Default is the current time.
     *
     * @return string JSON response
     */
    public function sendUser($uid, array $properties = array(), $event_time = null)
    {
        $event_time = $this->getEventTime($event_time);
        // casting to object so that an empty array would be represented as {}
        if (empty($properties)) {
            $properties = (object)$properties;
        }

        $url = self::EVENTS_URL . 'user/';
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(),
                'body' => json_encode(
                    [
                        'site_name' => $this->site_name,
                        'user_id' => $uid,
                        'properties' => $properties,
                        'created_at' => $event_time,
                    ]
                )
            )
        );


        return $response;
    }

    public function sendItem($iid, array $properties = array(), $event_time = null)
    {
        $event_time = $this->getEventTime($event_time);
        // casting to object so that an empty array would be represented as {}
        if (empty($properties)) {
            $properties = (object)$properties;
        }

        $url = self::EVENTS_URL . 'item/';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(),
                'body' => json_encode(
                    [
                        'site_name' => $this->site_name,
                        'item_id' => $iid,
                        'properties' => $properties,
                        'created_at' => $event_time,
                    ]
                )
            )
        );

        return $response;
    }

    public function sendClientSecret($secret)
    {
        $url = self::EVENTS_URL . 'client/';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(false),
                'body' => json_encode(
                    [
                        'site_name' => $this->site_name,
                        'client_secret' => $secret,
                        'email' => get_option('admin_email', '')
                    ]
                )
            )
        );
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code != 201 && $body['status'] != self::CLIENT_DUPLICATE_STATUS) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[RECOMMENDER] --- Sending client secret.");
            error_log("[RECOMMENDER] --- " . $error_body);
            return false;
        }
        update_option(RECOMMENDER_PLUGIN_PREFIX.'_email_admin_sent', true);
        return true;
    }

    public function sendClientEmail()
    {
        $url = self::EVENTS_URL . 'client/set_email/';

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(),
                'body' => json_encode(
                    [
                        'email' => get_option('admin_email'),
                    ]
                )
            )
        );
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code != 200 && $body['status'] != self::CLIENT_DUPLICATE_STATUS) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[RECOMMENDER] --- Sending client secret.");
            error_log("[RECOMMENDER] --- " . $error_body);
            return false;
        }
        update_option(RECOMMENDER_PLUGIN_PREFIX.'_email_admin_sent', true);
        return true;
    }

    public function sendInteraction($user_id, $item_id, $interaction_type, $interaction_value, $interaction_time, $anonymous_id, $interaction_id = null, array $properties = array(), array $user_features = array(), array $item_features = array(), $event_time = null)
    {
        $event_time = $this->getEventTime($event_time);
        // casting to object so that an empty array would be represented as {}
        if (empty($properties)) {
            $properties = (object)$properties;
        }
        $interactionId = $interaction_id;
        if (!$interactionId) {
            $character_pool = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@$%^&*()_=~`:;./\\'\"[]{}|";
            $interactionId = "";
            for ($j = 0; $j < 32; $j++) {
                $interactionId .= $character_pool[rand(0, strlen($character_pool) - 1)];
            }
        }

        $url = self::EVENTS_URL . 'interaction/';
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(),
                'body' => json_encode(
                    [
                        'site_name' => $this->site_name,
                        'item_id' => $item_id,
                        'user_id' => $user_id,
                        'interaction_type' => $interaction_type,
                        'interaction_value' => $interaction_value,
                        'interaction_time' => (string)$interaction_time,
                        'user_features' => $user_features,
                        'item_features' => $item_features,
                        'properties' => $properties,
                        'created_at' => $event_time,
                        'interaction_id' => $interactionId,
                        'anonymous_id' => $anonymous_id
                    ]
                )
            )
        );

        return $response;
    }

    public function changeState($new_state)
    {
        $url = self::EVENTS_URL . 'client/change_state/';
        return wp_remote_request(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'httpversion' => self::HTTPVERSION,
                'headers' => $this->getHeader(),
                'body' => json_encode(
                    [
                        'state' => $new_state,
                        'site_name' => $this->site_name
                    ]
                ),
                'method' => "PUT"
            )
        );
    }
} // end of class Recomendo_Client
