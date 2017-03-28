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