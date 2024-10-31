<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
defined('ABSPATH') or die('No script kiddies please!');

require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-async-request.php';
require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-background-process.php';

class RecommenderBackgroundOrderItemCopy extends RecommenderBackgroundProcess
{

    /**
     * @var string
     */
    protected $action = 'order_item_copy';
    protected $parent_tag = 'orders';

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over
     *
     * @return mixed
     */
    protected function task($item)
    {
        $order_id = $item;

        $order_item = new \WC_Order_Item_Product($order_id);
        $order = $order_item->get_order();
        $anonymous_id = $order_item->get_meta('_anonymous_id');
        if ($anonymous_id == '')
            $anonymous_id = null;
        if ($anonymous_id)
            wc_delete_order_item_meta($order_id, '_anonymous_id');
//        if ($order->get_status() != "completed") {
//            return false;
//        }

        // Send the order
        $user_id = $order->get_user_id();
        $properties = array(
            'billing_email'       => $order->get_billing_email(),
            'billing_phone'       => $order->get_billing_phone(),
            'billing_first_name'  => $order->get_billing_first_name(),
            'billing_last_name'   => $order->get_billing_last_name(),
            'billing_address'     => $order->get_billing_address_1(),
            'billing_city'        => $order->get_billing_city(),
            'billing_state'       => $order->get_billing_state(),
            'postcode'            => $order->get_billing_postcode(),
            'customer_ip_address' => $order->get_customer_ip_address(),
            'customer_user_agent' => $order->get_customer_user_agent(),
            'date_completed'      => $order->get_date_completed(),
            'date_paid'           => $order->get_date_paid(),
            'item_total_price'    => $order_item->get_total(),
            'status'              => $order->get_status(),
            'is_paid'             => in_array($order->get_status(), wc_get_is_paid_statuses()),
        );

        $response = $this->client->sendInteraction(
            $user_id, $order_item->get_product_id(), "purchase", $order_item->get_quantity(),
            $order->get_date_modified(), $anonymous_id, "order_item_id_".$order_id, $properties
        );

        return $this->checkResponse($item, $response);
    }

    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete()
    {
        error_log($this->identifier . " complete");
        parent::complete();
        // Show notice to user or perform some other arbitrary task...
    }
}
