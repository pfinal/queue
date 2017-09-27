<?php

namespace PFinal\Queue\Driver;

use Exception;
use Pheanstalk\Pheanstalk;
use PFinal\Queue\Job\BeanstalkJob;

/**
 * Beanstalk队列驱动
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class Beanstalk extends QueueDriver
{
    //服务器
    public $host = '127.0.0.1';

    public $timeToRun = 60;

    protected $ph;

    public function push($class, $data = null, $queue = null)
    {
        $queue = is_null($queue) ? $this->defaultTube : $queue;

        $payload = parent::serialize($class, $data);

        if ($this->ph == null) {
            $this->ph = new Pheanstalk($this->host);
        }

        return $this->ph->useTube($queue)->put(
            $payload,
            Pheanstalk::DEFAULT_PRIORITY,
            Pheanstalk::DEFAULT_DELAY,
            $this->timeToRun
        );
    }

    public function pop($queue = null)
    {
        $queue = is_null($queue) ? $this->defaultTube : $queue;

        if ($this->ph == null) {
            $this->ph = new Pheanstalk($this->host);
        }

        //$job = $ph->watch($queue)->ignore('default')->reserve(); //一直等待,直到一个 job 可用
        $job = $this->ph->watch($queue)->reserve(3); // 3秒超时

        if ($job === false) {
            return null;
        }

        return new BeanstalkJob($this->ph, $job, $queue);
    }
}


