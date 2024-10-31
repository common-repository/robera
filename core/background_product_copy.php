<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
defined('ABSPATH') or die('No script kiddies please!');

require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-async-request.php';
require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-background-process.php';

class RecommenderBackgroundProductCopy extends RecommenderBackgroundProcess
{

    /**
     * @var string
     */
    protected $action = 'product_copy';
    protected $parent_tag = 'products';

    public function addCandidateProduct($product_id)
    {
        $key = $this->identifier . '_candidates';
        $arr = get_site_option($key) ? get_site_option($key) : array();
        $arr[] = $product_id;
        update_site_option($key, $arr);
    }

    public function checkProductIsCandidate($product_id)
    {
        $key = $this->identifier . '_candidates';
        return in_array($product_id, get_site_option($key));
    }

    public function removeCandidateProduct($product_id)
    {
        $key = $this->identifier . '_candidates';
        $arr = get_site_option($key) ? get_site_option($key) : array();
        $index = array_search($product_id, $arr);
        unset($arr[$index]);
        update_site_option($key, $arr);
    }

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
        // Actions to perform
        $product = wc_get_product($item);

        $gallery_image_ids = $product->get_gallery_image_ids();
        $gallery_images = array();
        foreach ($gallery_image_ids as $gallery_image_id) {
            $gallery_images[] = wp_get_attachment_url($gallery_image_id);
        }

        $properties = array(
            'title' => $product->get_title(),
            'status' => $product->get_status(),
            'date_created' => $product->get_date_created(),
            'date_modified' => $product->get_date_modified(),
            'featured' => $product->get_featured(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku' => $product->get_sku(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'price' => $product->get_price(),
            'date_on_sale_from' => $product->get_date_on_sale_from(),
            'date_on_sale_to' => $product->get_date_on_sale_to(),
            'total_sales' => $product->get_total_sales(),
            'tax_status' => $product->get_tax_status(),
            'stock_status' => $product->get_stock_status(),
            'weigth' => $product->get_weight(),
            'category_ids' => $product->get_category_ids(),
            'tag_ids' => $product->get_tag_ids(),
            'permalink' => $product->get_permalink(),
            'rating_count' => $product->get_rating_count(),
            'availability' => $product->get_availability()['availability'],
            'image' => wp_get_attachment_url($product->get_image_id()),
            'gallery_images' => $gallery_images,
        );

        $response = $this->client->sendItem(
            $item,
            $properties
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
