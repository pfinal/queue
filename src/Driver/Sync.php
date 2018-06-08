<?php

namespace PFinal\Queue\Driver;

use PFinal\Queue\Job\SyncJob;

/**
 * 同步驱动(本地开发时使用)
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class Sync extends QueueDriver
{
    private $payload;

    public function push($class, $data = null, $queue = null, $delay = 0)
    {
        $this->payload = parent::serialize($class, $data);

        $job = $this->pop($queue);
        $job->resolveAndFire(unserialize($job->getRawBody()));

        return 1;
    }

    public function pop($queue = null)
    {
        return new SyncJob($this, $this->payload, $queue);
    }
}


