<?php

namespace PFinal\Queue\Job;

use PFinal\Queue\Driver\Redis;
use PFinal\Queue\Job;

class RedisJob extends Job
{
    protected $queue;

    protected $job;

    /** @var  Redis */
    protected $driver;

    protected $reserved;

    public function __construct($driver, $job, $queue, $reserved)
    {
        $this->queue = $queue;
        $this->driver = $driver;
        $this->job = $job;
        $this->reserved = $reserved;
    }

    /**
     * 服务端完全删除一个 job
     */
    public function delete()
    {
        parent::delete();
        $this->driver->delete($this->queue, $this);
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return serialize(array($this->job['job'], $this->job['data']));
    }

    /**
     * 将一个已经被获取的 job 重新放回 ready 队列
     */
    public function release($delay = 10)
    {
        $this->driver->release($this->queue, $this, $delay);
    }

    public function attempts()
    {
        return $this->job['attempts'];
    }

    public function getJobId()
    {
        return $this->job['id'];
    }

    public function failed()
    {
        parent::failed();
        $this->driver->fail($this->queue, $this->getRawBody());
    }

    /**
     * Get the underlying reserved Redis job.
     *
     * @return string
     */
    public function getReservedJob()
    {
        return $this->reserved;
    }
}