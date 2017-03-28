<?php

namespace PFinal\Queue;

/**
 * 队列任务对象
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
abstract class Job
{
    protected $deleted = false;

    protected $queue;

    protected $job;

    protected $driver;

    public function __construct($driver, $job, $queue)
    {
        $this->queue = $queue;
        $this->driver = $driver;
        $this->job = $job;
    }

    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Get the name of the queued job class.
     *
     * @return string
     */
    public function getName()
    {
        return current(unserialize($this->getRawBody()));
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    public function failed()
    {
        //Log::error(sprintf('Job #%s %s failed. RawBody:%s', $this->getJobId(), $this->getName(), $this->getRawBody()));
    }

    /**
     * 服务端完全删除一个 job
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * 将一个已经被获取的 job 重新放回 ready 队列
     *
     * @param int $delay 想要等待工作再次能够执行的秒数
     * @return mixed
     */
    public function release($delay = 0)
    {
    }

    /**
     * 当前job是第几次尝试执行
     * @return int
     */
    public function attempts()
    {
    }

    /**
     * 工作ID
     * @return mixed
     */
    public function getJobId()
    {
    }

    /**
     * @return string
     */
    public function getRawBody()
    {
    }

    /**
     * Resolve and fire the job handler method.
     *
     * @param  array $payload
     * @return void
     */
    public function resolveAndFire(array $payload)
    {

        list($callback, $data) = $payload;

        if (($index = strpos($callback, '@')) !== false) {
            $class = substr($callback, 0, $index);
            $method = substr($callback, $index + 1);
        } else {
            $class = $callback;
            $method = 'fire';
        }

        $obj = new $class;
        $obj->{$method}($this, $data);
    }
}