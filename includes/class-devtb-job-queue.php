<?php
/**
 * DEVTB Job Queue
 *
 * Handles async batch translation processing
 * Uses WordPress transients for job storage and WP Cron for scheduling
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage API
 * @version    4.5.0
 */

class DEVTB_Job_Queue {
    /**
     * Logger instance
     *
     * @var DEVTB_Logger
     */
    private $logger;

    /**
     * Job TTL (24 hours)
     *
     * @var int
     */
    private $job_ttl = DAY_IN_SECONDS;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new DEVTB_Logger();

        // Register cron hook for processing jobs
        add_action('devtb_process_batch_job', [$this, 'process_batch_job']);

        // Register cleanup hook
        add_action('devtb_cleanup_old_jobs', [$this, 'cleanup_old_jobs']);

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('devtb_cleanup_old_jobs')) {
            wp_schedule_event(time(), 'daily', 'devtb_cleanup_old_jobs');
        }
    }

    /**
     * Create a new batch translation job
     *
     * @param string $source Source framework
     * @param array  $targets Target frameworks
     * @param string $content Content to translate
     * @param array  $options Additional options
     * @return string Job ID
     */
    public function create_job($source, $targets, $content, $options = []) {
        $job_id = 'devtb_' . uniqid() . '_' . time();

        $job_data = [
            'job_id'     => $job_id,
            'status'     => 'queued',
            'source'     => $source,
            'targets'    => $targets,
            'content'    => $content,
            'options'    => $options,
            'progress'   => 0,
            'total'      => count($targets),
            'successful' => 0,
            'failed'     => 0,
            'results'    => [],
            'errors'     => [],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'started_at' => null,
            'completed_at' => null,
        ];

        // Store job data
        set_transient('devtb_job_' . $job_id, $job_data, $this->job_ttl);

        // Schedule immediate processing
        wp_schedule_single_event(time(), 'devtb_process_batch_job', [$job_id]);

        $this->logger->info('Batch job created', [
            'job_id'  => $job_id,
            'source'  => $source,
            'targets' => count($targets),
        ]);

        return $job_id;
    }

    /**
     * Process a batch translation job
     *
     * @param string $job_id Job ID
     */
    public function process_batch_job($job_id) {
        $job = get_transient('devtb_job_' . $job_id);

        if (!$job) {
            $this->logger->error('Job not found', ['job_id' => $job_id]);
            return;
        }

        // Update status to processing
        $job['status'] = 'processing';
        $job['started_at'] = current_time('mysql');
        $this->update_job($job_id, $job);

        $this->logger->info('Processing batch job', [
            'job_id' => $job_id,
            'targets' => count($job['targets']),
        ]);

        try {
            // Load translator
            require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-translator.php';
            $translator = new Translator();

            $start_time = microtime(true);

            // Process each target framework
            foreach ($job['targets'] as $index => $target) {
                try {
                    // Translate
                    $result = $translator->translate($job['content'], $job['source'], $target);

                    if ($result) {
                        // Success
                        $job['results'][$target] = [
                            'success' => true,
                            'result'  => $result,
                            'stats'   => $translator->get_stats(),
                        ];
                        $job['successful']++;
                    } else {
                        // Failed
                        $job['results'][$target] = [
                            'success' => false,
                            'error'   => 'Translation failed',
                        ];
                        $job['errors'][] = "Failed to translate to {$target}";
                        $job['failed']++;
                    }
                } catch (Exception $e) {
                    // Exception during translation
                    $job['results'][$target] = [
                        'success' => false,
                        'error'   => $e->getMessage(),
                    ];
                    $job['errors'][] = "{$target}: {$e->getMessage()}";
                    $job['failed']++;

                    $this->logger->error('Translation error in batch job', [
                        'job_id' => $job_id,
                        'target' => $target,
                        'error'  => $e->getMessage(),
                    ]);
                }

                // Update progress
                $job['progress'] = round((($index + 1) / $job['total']) * 100);
                $job['updated_at'] = current_time('mysql');
                $this->update_job($job_id, $job);
            }

            $elapsed_time = microtime(true) - $start_time;

            // Mark as complete
            $job['status'] = 'completed';
            $job['completed_at'] = current_time('mysql');
            $job['elapsed_time'] = round($elapsed_time, 3);
            $this->update_job($job_id, $job);

            $this->logger->info('Batch job completed', [
                'job_id'     => $job_id,
                'successful' => $job['successful'],
                'failed'     => $job['failed'],
                'time'       => round($elapsed_time, 2),
            ]);

            // Trigger webhook if configured
            $this->trigger_webhook($job);

        } catch (Exception $e) {
            // Fatal error during processing
            $job['status'] = 'failed';
            $job['errors'][] = 'Fatal error: ' . $e->getMessage();
            $job['completed_at'] = current_time('mysql');
            $this->update_job($job_id, $job);

            $this->logger->error('Batch job failed', [
                'job_id' => $job_id,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get job status
     *
     * @param string $job_id Job ID
     * @return array|false Job data or false if not found
     */
    public function get_job($job_id) {
        return get_transient('devtb_job_' . $job_id);
    }

    /**
     * Update job data
     *
     * @param string $job_id Job ID
     * @param array  $job_data Job data
     */
    private function update_job($job_id, $job_data) {
        set_transient('devtb_job_' . $job_id, $job_data, $this->job_ttl);
    }

    /**
     * Cancel a job
     *
     * @param string $job_id Job ID
     * @return bool Success
     */
    public function cancel_job($job_id) {
        $job = $this->get_job($job_id);

        if (!$job) {
            return false;
        }

        // Can only cancel queued or processing jobs
        if (!in_array($job['status'], ['queued', 'processing'])) {
            return false;
        }

        $job['status'] = 'cancelled';
        $job['completed_at'] = current_time('mysql');
        $this->update_job($job_id, $job);

        $this->logger->info('Job cancelled', ['job_id' => $job_id]);

        return true;
    }

    /**
     * Retry a failed job
     *
     * @param string $job_id Job ID
     * @return string|false New job ID or false on failure
     */
    public function retry_job($job_id) {
        $job = $this->get_job($job_id);

        if (!$job) {
            return false;
        }

        // Can only retry failed jobs
        if ($job['status'] !== 'failed') {
            return false;
        }

        // Get failed targets
        $failed_targets = [];
        foreach ($job['results'] as $target => $result) {
            if (!$result['success']) {
                $failed_targets[] = $target;
            }
        }

        if (empty($failed_targets)) {
            return false;
        }

        // Create new job for failed targets
        $new_job_id = $this->create_job(
            $job['source'],
            $failed_targets,
            $job['content'],
            $job['options']
        );

        $this->logger->info('Job retried', [
            'original_job_id' => $job_id,
            'new_job_id'      => $new_job_id,
            'retry_count'     => count($failed_targets),
        ]);

        return $new_job_id;
    }

    /**
     * Get all jobs (for admin dashboard)
     *
     * @param int $limit Limit number of jobs
     * @return array Jobs
     */
    public function get_all_jobs($limit = 50) {
        global $wpdb;

        // Get all DEVTB job transients
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_devtb_job_%'
             ORDER BY option_id DESC
             LIMIT {$limit}"
        );

        $jobs = [];
        foreach ($transients as $transient) {
            $job_data = maybe_unserialize($transient->option_value);
            if ($job_data && is_array($job_data)) {
                $jobs[] = $job_data;
            }
        }

        return $jobs;
    }

    /**
     * Get job statistics
     *
     * @return array Stats
     */
    public function get_stats() {
        $jobs = $this->get_all_jobs(1000);

        $stats = [
            'total'      => count($jobs),
            'queued'     => 0,
            'processing' => 0,
            'completed'  => 0,
            'failed'     => 0,
            'cancelled'  => 0,
        ];

        foreach ($jobs as $job) {
            $status = $job['status'] ?? 'unknown';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * Cleanup old jobs (runs daily)
     */
    public function cleanup_old_jobs() {
        global $wpdb;

        // Delete transients older than 7 days
        $cutoff = time() - (7 * DAY_IN_SECONDS);

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_name NOT LIKE %s
                 AND option_value < %d",
                '_transient_timeout_devtb_job_%',
                '_transient_devtb_job_%',
                $cutoff
            )
        );

        if ($deleted) {
            $this->logger->info('Old jobs cleaned up', ['deleted' => $deleted]);
        }
    }

    /**
     * Trigger webhook notification
     *
     * @param array $job Job data
     */
    private function trigger_webhook($job) {
        // Use DEVTB_Webhook class for secure webhook delivery
        if (class_exists('DEVTB_Webhook')) {
            $webhook = new DEVTB_Webhook();

            // Prepare webhook payload
            $payload = [
                'job_id'       => $job['job_id'],
                'status'       => $job['status'],
                'source'       => $job['source'],
                'total'        => $job['total'],
                'successful'   => $job['successful'],
                'failed'       => $job['failed'],
                'elapsed_time' => $job['elapsed_time'] ?? null,
                'completed_at' => $job['completed_at'],
            ];

            // Send webhook with retry support
            $webhook->send('job.completed', $payload);
        }
    }

    /**
     * Get job progress (for real-time updates)
     *
     * @param string $job_id Job ID
     * @return array Progress data
     */
    public function get_progress($job_id) {
        $job = $this->get_job($job_id);

        if (!$job) {
            return [
                'found'    => false,
                'job_id'   => $job_id,
            ];
        }

        return [
            'found'       => true,
            'job_id'      => $job['job_id'],
            'status'      => $job['status'],
            'progress'    => $job['progress'],
            'total'       => $job['total'],
            'successful'  => $job['successful'],
            'failed'      => $job['failed'],
            'updated_at'  => $job['updated_at'],
        ];
    }
}

// Initialize job queue
if (class_exists('DEVTB_Logger')) {
    new DEVTB_Job_Queue();
}
