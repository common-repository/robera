<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
defined('ABSPATH') or die('No script kiddies please!');

require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-async-request.php';
require_once RECOMMENDER_PLUGIN_PATH . 'libraries/recommender-background-process.php';

class RecommenderBackgroundUserCopy extends RecommenderBackgroundProcess
{

    /**
     * @var string
     */
    protected $action = 'user_copy';
    protected $parent_tag = 'users';

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
        $user = get_userdata($item);

        $properties = array(
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
        );

        $response = $this->client->sendUser(
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
