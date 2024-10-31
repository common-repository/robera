<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
defined('ABSPATH') or die('No script kiddies please!');

require_once ABSPATH . 'wp-includes/pluggable.php';


if (!class_exists('RecommenderAsyncRequest')) {
    abstract class RecommenderAsyncRequest
    {
        /**
         * Prefix
         *
         * (default value: 'wp')
         *
         * @var    string
         * @access protected
         */
        protected $prefix = 'recommender';
        /**
         * Action
         *
         * (default value: 'async_request')
         *
         * @var    string
         * @access protected
         */
        protected $action = 'async_request';
        /**
         * Identifier
         *
         * @var    mixed
         * @access protected
         */
        protected $identifier;
        /**
         * Data
         *
         * (default value: array())
         *
         * @var    array
         * @access protected
         */
        protected $data = array();
        /**
         * Initiate new async request
         */
        public function __construct()
        {
            $this->identifier = $this->prefix . '_' . $this->action;
            add_action('wp_ajax_' . $this->identifier, array( $this, 'maybeHandle' ));
            add_action('wp_ajax_nopriv_' . $this->identifier, array( $this, 'maybeHandle' ));
        }
        /**
         * Set data used during the request
         *
         * @param array $data Data.
         *
         * @return $this
         */
        public function data($data)
        {
            $this->data = $data;
            return $this;
        }
        /**
         * Dispatch the async request
         *
         * @return array|WP_Error
         */
        public function dispatch()
        {
            $url  = add_query_arg($this->getQueryArgs(), $this->getQueryUrl());
            $args = $this->getPostArgs();
            return wp_remote_post(esc_url_raw($url), $args);
        }
        /**
         * Get query args
         *
         * @return array
         */
        protected function getQueryArgs()
        {
            if (property_exists($this, 'query_args')) {
                return $this->query_args;
            }
            return array(
                'action' => $this->identifier,
                'nonce'  => wp_create_nonce($this->identifier),
            );
        }
        /**
         * Get query URL
         *
         * @return string
         */
        protected function getQueryUrl()
        {
            if (property_exists($this, 'query_url')) {
                return $this->query_url;
            }
            return admin_url('admin-ajax.php');
        }
        /**
         * Get post args
         *
         * @return array
         */
        protected function getPostArgs()
        {
            if (property_exists($this, 'post_args')) {
                return $this->post_args;
            }
            return array(
                'timeout'   => 0.01,
                'blocking'  => false,
                'body'      => $this->data,
                'cookies'   => $_COOKIE,
                'sslverify' => apply_filters('https_local_ssl_verify', false),
            );
        }
        /**
         * Maybe handle
         *
         * Check for correct nonce and pass to handler.
         */
        public function maybeHandle()
        {
            // Don't lock up other requests while processing
            session_write_close();
            check_ajax_referer($this->identifier, 'nonce');
            $this->handle();
            wp_die();
        }
        /**
         * Handle
         *
         * Override this method to perform any actions required
         * during the async request.
         */
        abstract protected function handle();
    }
}
