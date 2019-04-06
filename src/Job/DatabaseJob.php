<?php

namespace PFinal\Queue\Job;

use PFinal\Queue\Driver\Database;
use PFinal\Queue\Job;

/**
 * 数据库队列job对象
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class DatabaseJob extends Job
{
    protected $queue;

    /** @var  array */
    protected $job;

    /** @var  Database */
    protected $driver;

    public function __construct($driver, $job, $queue)
    {
        $this->queue = $queue;
        $this->driver = $driver;
        $this->job = $job;
        $this->job['attempts'] = $this->job['attempts'] + 1;
    }

    /**
     * 服务端完全删除一个 job
     */
    public function delete()
    {
        parent::delete();
        $this->driver->delete($this->job);
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job['payload'];
    }

    /**
     * 将一个已经被获取的 job 重新放回 ready 队列
     */
    public function release($delay = 10)
    {
        parent::release($delay);
        $this->driver->release($this->job, $delay);
    }

    public function attempts()
    {
        return $this->job['attempts'];
    }

    public function getJobId()
    {
        return $this->job['id'];
    }

    protected function failed($e=null)
    {
        $this->driver->log($this->job['queue'], $this->job['payload'], $e);
    }
}
