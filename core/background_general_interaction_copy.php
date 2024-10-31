<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
defined('ABSPATH') or die('No script kiddies please!');

require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-async-request.php';
require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-background-process.php';

class RecommenderBackgroundGeneralInteractionCopy extends RecommenderBackgroundProcess
{

    /**
     * @var string
     */
    protected $action = 'general_interaction_copy';

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
        $user_id = $item[0];
        $item_id = $item[1];
        $interaction_type = $item[2];
        $interaction_time = $item[3];
        $anonymous_id = $item[4];
        if (sizeof($item) == 5) {
            $interaction_value = 1;
        }else{
            $interaction_value = $item[5];
        }

        $response = $this->client->sendInteraction(
            $user_id, $item_id, $interaction_type, $interaction_value, $interaction_time, $anonymous_id
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
