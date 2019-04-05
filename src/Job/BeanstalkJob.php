<?php

namespace PFinal\Queue\Job;

use Pheanstalk\Pheanstalk;
use PFinal\Queue\Job;

/**
 * beanstalk对列job对象
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class BeanstalkJob extends Job
{
    protected $job;

    protected $driver;

    /** @var  \Pheanstalk\Job */
    private $_job;

    /** @var  Pheanstalk */
    private $_ph;

    protected $queue;

    public function __construct($driver, $job, $queue)
    {
        $this->_ph = $driver;
        $this->_job = $job;
        $this->queue = $queue;
    }

    /**
     * 服务端完全删除一个 job
     */
    public function delete()
    {
        parent::delete();
        $this->_ph->useTube($this->queue)->delete($this->_job);
    }

    /**
     * @return string
     */
    public function getRawBody()
    {
        return $this->_job->getData();
    }

    /**
     * 将一个已经被获取的 job 重新放回 ready 队列
     */
    public function release($delay = 0)
    {
        $this->_ph->useTube($this->queue)->release($this->_job, Pheanstalk::DEFAULT_PRIORITY, $delay);
    }

    /**
     * 工作执行次数
     * @return int
     */
    public function attempts()
    {
        $stats = $this->_ph->statsJob($this->_job);

        return (int)$stats->reserves;
    }

    public function getJobId()
    {
        return $this->_job->getId();
    }

    protected function failed()
    {
        parent::failed();
    }
}