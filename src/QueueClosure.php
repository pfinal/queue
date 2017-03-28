<?php

namespace PFinal\Queue;

/**
 * 队列闭包Job包装类
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class QueueClosure
{

    /**
     * Fire the Closure based queue job.
     *
     * @param $job
     * @param $data
     */
    public function fire($job, $data)
    {
        $closure = (new \SuperClosure\Serializer())->unserialize($data['closure']);

        $closure($job);
    }
}
