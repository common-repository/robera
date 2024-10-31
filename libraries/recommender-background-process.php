<?php

namespace Recommender;

//Security to limit direcct access to the plugin file
defined('ABSPATH') or die('No script kiddies please!');

if (! class_exists('RecommenderBackgroundProcess')) {
    abstract class RecommenderBackgroundProcess extends RecommenderAsyncRequest
    {
        /**
         * Action
         *
         * (default value: 'background_process')
         *
         * @var    string
         * @access protected
         */
        protected $action = 'background_process';
        /**
         * Start time of current process.
         *
         * (default value: 0)
         *
         * @var    int
         * @access protected
         */
        protected $start_time = 0;
        /**
         * Cron_hook_identifier
         *
         * @var    mixed
         * @access protected
         */
        protected $cron_hook_identifier;
        /**
         * Cron_interval_identifier
         *
         * @var    mixed
         * @access protected
         */
        protected $cron_interval_identifier;
        /**
         * first_data_transfer_option
         *
         * @var    mixed
         * @access protected
         */
        public $first_data_transfer_option;
        protected $parent_tag;
        /**
         * Initiate new background process
         */
        public function __construct()
        {
            parent::__construct();
            $this->client = new RecommenderClient();
            $this->first_data_transfer_option = $this->identifier.'_first_data_transfer_option';
            $this->cron_hook_identifier     = $this->identifier . '_cron';
            $this->cron_interval_identifier = $this->identifier . '_cron_interval';
            $this->data = array();
            add_action($this->cron_hook_identifier, array( $this, 'handleCronHealthcheck' ));
            add_filter('cron_schedules', array( $this, 'scheduleCronHealthcheck' ));
        }
        /**
         * Dispatch
         *
         * @access public
         * @return void
         */
        public function dispatch()
        {
            // Schedule the cron healthcheck.
            $this->scheduleEvent();
            // Perform remote post.
            return;
            # return parent::dispatch();
        }
        /**
         * Push to queue
         *
         * @param mixed $data Data.
         *
         * @return $this
         */
        public function pushToQueue($data)
        {
            $this->data[] = $data;
            return $this;
        }
        /**
         * Save queue
         *
         * @return $this
         */
        public function save()
        {
            $key = $this->generateKey();
            if (! empty($this->data)) {
                update_site_option($key, $this->data);
            }
            $this->data = array();
            return $this;
        }
        /**
         * Update queue
         *
         * @param string $key  Key.
         * @param array  $data Data.
         *
         * @return $this
         */
        public function update($key, $data)
        {
            if (! empty($data)) {
                update_site_option($key, $data);
            }
            return $this;
        }
        /**
         * Delete queue
         *
         * @param string $key Key.
         *
         * @return $this
         */
        public function delete($key)
        {
            delete_site_option($key);
            return $this;
        }
        /**
         * Generate key
         *
         * Generates a unique key based on microtime. Queue items are
         * given a unique key so that they can be merged upon save.
         *
         * @param int $length Length.
         *
         * @return string
         */
        protected function generateKey($length = 64)
        {
            $unique  = md5(microtime() . rand());
            $prepend = $this->identifier . '_batch_';
            return substr($prepend . $unique, 0, $length);
        }
        /**
         * Maybe process queue
         *
         * Checks whether data exists within the queue and that
         * the process is not already running.
         */
        public function maybeHandle()
        {
            // Don't lock up other requests while processing
            session_write_close();
            if ($this->isProcessRunning()) {
                // Background process already running.
                wp_die();
            }
            if ($this->isQueueEmpty()) {
                // No data to process.
                wp_die();
            }
            check_ajax_referer($this->identifier, 'nonce');
            $this->handle();
            wp_die();
        }
        /**
         * Is queue empty
         *
         * @return bool
         */
        protected function isQueueEmpty()
        {
            global $wpdb;
            $table  = $wpdb->options;
            $column = 'option_name';
            if (is_multisite()) {
                $table  = $wpdb->sitemeta;
                $column = 'meta_key';
            }
            $key = $wpdb->esc_like($this->identifier . '_batch_') . '%';
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "
            SELECT COUNT(*)
            FROM {$table}
            WHERE {$column} LIKE %s
        ",
                    $key
                )
            );
            return ($count > 0) ? false : true;
        }
        /**
         * Is process running
         *
         * Check whether the current process is already running
         * in a background process.
         */
        protected function isProcessRunning()
        {
            if (get_site_transient($this->identifier . '_process_lock')) {
                // Process already running.
                return true;
            }
            return false;
        }
        /**
         * Lock process
         *
         * Lock the process so that multiple instances can't run simultaneously.
         * Override if applicable, but the duration should be greater than that
         * defined in the time_exceeded() method.
         */
        protected function lockProcess()
        {
            $this->start_time = time(); // Set start time of current process.
            $lock_duration = (property_exists($this, 'queue_lock_time')) ? $this->queue_lock_time : 60; // 1 minute
            $lock_duration = apply_filters($this->identifier . '_queue_lock_time', $lock_duration);
            set_site_transient($this->identifier . '_process_lock', microtime(), $lock_duration);
        }
        /**
         * Unlock process
         *
         * Unlock the process so that other instances can spawn.
         *
         * @return $this
         */
        protected function unlockProcess()
        {
            delete_site_transient($this->identifier . '_process_lock');
            return $this;
        }
        /**
         * Get batch
         *
         * @return stdClass Return the first batch from the queue
         */
        protected function getBatch()
        {
            global $wpdb;
            $table        = $wpdb->options;
            $column       = 'option_name';
            $key_column   = 'option_id';
            $value_column = 'option_value';
            if (is_multisite()) {
                $table        = $wpdb->sitemeta;
                $column       = 'meta_key';
                $key_column   = 'meta_id';
                $value_column = 'meta_value';
            }
            $key = $wpdb->esc_like($this->identifier . '_batch_') . '%';
            $query = $wpdb->get_row(
                $wpdb->prepare(
                    "
            SELECT *
            FROM {$table}
            WHERE {$column} LIKE %s
            ORDER BY {$key_column} ASC
            LIMIT 1
        ",
                    $key
                )
            );
            $batch       = new \stdClass();
            $batch->key  = $query->$column;
            $batch->data = maybe_unserialize($query->$value_column);
            return $batch;
        }
        /**
         * Handle
         *
         * Pass each queue item to the task handler, while remaining
         * within server memory and time limit constraints.
         */
        protected function handle()
        {
            $this->lockProcess();
            do {
                $batch = $this->getBatch();
                foreach ($batch->data as $key => $value) {
                    $task = $this->task($value);
                    if (false !== $task) {
                        $batch->data[$key] = $task;
                    } else {
                        unset($batch->data[$key]);
                        if (!get_option($this->first_data_transfer_option)) {
                            $val = get_option('recommender_api_progress_' . $this->action, 0)+1;
                            update_option('recommender_api_progress_' . $this->action, $val);
                        }
                    }
                    if ($this->timeExceeded() || $this->memoryExceeded()) {
                        // Batch limits reached.
                        break;
                    }
                }
                // Update or delete current batch.
                if (!empty($batch->data)) {
                    $this->update($batch->key, $batch->data);
                } else {
                    $this->delete($batch->key);
                }
            } while (! $this->timeExceeded() && ! $this->memoryExceeded() && ! $this->isQueueEmpty());

            $this->unlockProcess();
            // Start next batch or complete process.
            if (! $this->isQueueEmpty()) {
                # $this->dispatch();
            } else {
                $this->complete();
            }
            wp_die();
        }
        /**
         * Memory exceeded
         *
         * Ensures the batch process never exceeds 90%
         * of the maximum WordPress memory.
         *
         * @return bool
         */
        protected function memoryExceeded()
        {
            $memory_limit   = $this->getMemoryLimit() * 0.9; // 90% of max memory
            $current_memory = memory_get_usage(true);
            $return         = false;
            if ($current_memory >= $memory_limit) {
                $return = true;
            }
            return apply_filters($this->identifier . '_memory_exceeded', $return);
        }
        /**
         * Get memory limit
         *
         * @return int
         */
        protected function getMemoryLimit()
        {
            if (function_exists('ini_get')) {
                $memory_limit = ini_get('memory_limit');
            } else {
                // Sensible default.
                $memory_limit = '128M';
            }
            if (! $memory_limit || -1 === intval($memory_limit)) {
                // Unlimited, set to 32GB.
                $memory_limit = '32000M';
            }
            return intval($memory_limit) * 1024 * 1024;
        }
        /**
         * Time exceeded.
         *
         * Ensures the batch never exceeds a sensible time limit.
         * A timeout limit of 30s is common on shared hosting.
         *
         * @return bool
         */
        protected function timeExceeded()
        {
            $finish = $this->start_time + apply_filters($this->identifier . '_default_time_limit', 30); // 30 seconds
            $return = false;
            if (time() >= $finish) {
                $return = true;
            }
            return apply_filters($this->identifier . '_time_exceeded', $return);
        }
        /**
         * Complete.
         *
         * Override if applicable, but ensure that the below actions are
         * performed, or, call parent::complete().
         */
        protected function complete()
        {
            if (!get_option($this->first_data_transfer_option)){
                $this->client->changeState($this->action.'_done');
                update_option($this->first_data_transfer_option, true);
            }
            // clear schedule of the cron healthCheck.
            $this->clearScheduledEvent();
        }
        /**
         * Schedule cron healthcheck
         *
         * @access public
         * @param  mixed $schedules Schedules.
         * @return mixed
         */
        public function scheduleCronHealthcheck($schedules)
        {
            $interval = apply_filters($this->identifier . '_cron_interval', 3);
            if (property_exists($this, 'cron_interval')) {
                $interval = apply_filters($this->identifier . '_cron_interval', $this->cron_interval);
            }
            // Adds every 5 minutes to the existing schedules.
            $schedules[ $this->cron_interval_identifier ] = array(
                'interval' => MINUTE_IN_SECONDS * $interval,
                'display'  => sprintf(__('Every %d Minutes'), $interval),
            );
            return $schedules;
        }
        /**
         * Handle cron healthcheck
         *
         * Restart the background process if not already running
         * and data exists in the queue.
         */
        public function handleCronHealthcheck()
        {
            if (!get_option("recommender_api_client_secret_sent")) {
                // Client Secret is not sent to recommender server.
                exit;
            }
            if ($this->isProcessRunning()) {
                // Background process already running.
                exit;
            }
            if ($this->isQueueEmpty()) {
                // No data to process.
                $this->clearScheduledEvent();
                exit;
            }
            $this->handle();
            exit;
        }
        /**
         * Schedule event
         */
        protected function scheduleEvent()
        {
            if (! wp_next_scheduled($this->cron_hook_identifier)) {
                wp_schedule_event(time(), $this->cron_interval_identifier, $this->cron_hook_identifier);
            }
        }
        /**
         * Clear scheduled event
         */
        protected function clearScheduledEvent()
        {
            $timestamp = wp_next_scheduled($this->cron_hook_identifier);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $this->cron_hook_identifier);
            }
        }
        /**
         * Cancel Process
         *
         * Stop processing queue items, clear cronjob and delete batch.
         */
        public function cancelProcess($full = false)
        {
            if ($full) {
                $this->clearScheduledEvent();
                $this->unlockProcess();
            }
            while (! $this->isQueueEmpty()) {
                $batch = $this->getBatch();
                $this->delete($batch->key);
            }
            wp_clear_scheduled_hook($this->cron_hook_identifier);
        }
        /**
         * Task
         *
         * Override this method to perform any actions required on each
         * queue item. Return the modified item for further processing
         * in the next pass through. Or, return false to remove the
         * item from the queue.
         *
         * @param mixed $item Queue item to iterate over.
         *
         * @return mixed
         */
        abstract protected function task($item);

        protected function checkResponse($item, $response){
            // check the response
            if (is_wp_error($response)) {
                error_log(sprintf("[RECOMMENDER] --- Error adding %s %s.",$this->action, $item));
                error_log("[RECOMMENDER] --- " . $response->get_error_message());
                return $item;
            }
            $status_code = wp_remote_retrieve_response_code($response);
            $answer = $item;
            if (floor($status_code / 100 ) != 2) {
                $error_body = wp_remote_retrieve_body($response);
                error_log("[RECOMMENDER] --- Error adding a ".$this->action);
                error_log("[RECOMMENDER] --- ".$error_body);
                if ($status_code == 400 && strpos($error_body, 'duplicated') !== false ) {
                    $answer = false;
                }
            }else{
                $answer = false;
            }
            return $answer;
        }
    }
}
