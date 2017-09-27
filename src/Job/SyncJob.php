<?php

namespace PFinal\Queue\Job;

use PFinal\Queue\Job;

/**
 * 同步驱动job
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class SyncJob extends Job
{
    protected $queue;

    protected $job;

    protected $driver;

    public function __construct($driver, $job, $queue)
    {
        $this->queue = $queue;
        $this->driver = $driver;
        $this->job = $job;
    }

    /**
     * 工作执行次数
     * @return int
     */
    public function attempts()
    {
        return 1;
    }

    /**
     * @return string
     */
    public function getRawBody()
    {
        return $this->job;
    }

}