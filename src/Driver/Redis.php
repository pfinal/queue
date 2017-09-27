<?php

namespace PFinal\Queue\Driver;

use PFinal\Database\Builder;
use PFinal\Queue\Job\RedisJob;

/**
 * @author  Zou Yiliang
 * @since   1.0
 */
class Redis extends QueueDriver
{
    protected $redis;

    //存放失败的job
    protected $tableFailed = '{{%job_failed}}';

    /** @var Builder Database Builder */
    protected $db;

    /**
     * Redis服务器配置信息
     * [
     *      'scheme' => 'tcp',
     *      'host' => '127.0.0.1',
     *      'port' => 6379,
     * ]
     */
    protected $server;

    /**
     * 超时被终止的job，再次被拉起的间隔(秒)
     *
     * @var int|null
     */
    protected $retryAfter = 60;

    public function __construct(array $config = array())
    {
        foreach ($config as $k => $v) {
            $this->$k = $v;
        }

        if (!empty($this->dbConfig)) {
            $this->db = new Builder($this->dbConfig);
        }
    }

    /**
     * @return \Predis\Client
     */
    protected function getConnection()
    {
        if ($this->redis instanceof \Predis\Client) {
            return $this->redis;
        }

        if (empty($this->server)) {
            $params = array(
                'scheme' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 6379,
            );
        } else {
            $params = $this->server;
        }

        $this->redis = new \Predis\Client($params);
        return $this->redis;
    }

    /**
     * Get the queue or return the default.
     *
     * @param  string|null $queue
     * @return string
     */
    protected function getQueue($queue)
    {
        return 'queues:' . (empty($queue) ? $this->defaultTube : $queue);
    }

    public function push($class, $data = null, $queue = null)
    {
        if (!is_string($class)) {
            throw new \Exception('目前只支持字符串');
        }

        $payload = static::createPayload($class, $data);

        $this->getConnection()->rpush($this->getQueue($queue), json_encode($payload));

        return $payload['id'];
    }

    protected function createPayload($class, $data)
    {
        return array(
            'job' => $class,
            'data' => $data,
            'attempts' => 0,
            'id' => static::random(32),
        );
    }

    public function pop($queue = null)
    {
        $this->migrate($prefixed = $this->getQueue($queue));

        list($job, $reserved) = $this->retrieveNextJob($prefixed);

        if ($reserved) {
            return new RedisJob($this, json_decode($job, true), $queue, $reserved);
        }
        return null;
    }

    /**
     * Migrate any delayed or expired jobs onto the primary queue.
     *
     * @param  string $queue
     * @return void
     */
    protected function migrate($queue)
    {
        $this->migrateExpiredJobs($queue . ':delayed', $queue);

        if (!is_null($this->retryAfter)) {
            $this->migrateExpiredJobs($queue . ':reserved', $queue);
        }
    }

    /**
     * Migrate the delayed jobs that are ready to the regular queue.
     *
     * @param  string $from
     * @param  string $to
     * @return array
     */
    public function migrateExpiredJobs($from, $to)
    {
        return $this->getConnection()->eval(
            LuaScripts::migrateExpiredJobs(), 2, $from, $to, time()
        );
    }

    protected function retrieveNextJob($queue)
    {
        return $this->getConnection()->eval(
            LuaScripts::pop(), 2, $queue, $queue . ':reserved',
            $this->availableAt($this->retryAfter)
        );
    }

    protected function availableAt($delay = 0)
    {
        return time() + $delay;
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param  string $queue
     * @param  RedisJob $job
     * @return void
     */
    public function delete($queue, $job) //deleteReserved
    {
        $this->getConnection()->zrem($this->getQueue($queue) . ':reserved', $job->getReservedJob());
    }

    /**
     * Delete a reserved job from the reserved queue and release it.
     *
     * @param  string $queue
     * @param  RedisJob $job
     * @param  int $delay
     * @return void
     */
    public function release($queue, $job, $delay)
    {
        $queue = $this->getQueue($queue);

        $this->getConnection()->eval(
            LuaScripts::release(), 2, $queue . ':delayed', $queue . ':reserved',
            $job->getReservedJob(), $this->availableAt($delay)
        );
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string $queue
     * @param  string $payload
     * @return void
     */
    public function fail($queue, $payload)
    {
        $failed_at = date('Y-m-d H:i:s');

        if ($this->db && $this->tableFailed) {
            $this->db->table($this->tableFailed)->insert(compact('queue', 'payload', 'failed_at'));
        }
    }

    protected static function random($length = 16)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length * 2);

            if ($bytes === false) {
                throw new \RuntimeException('Unable to generate random string.');
            }

            return substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $length);
        }

        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($pool, $length)), 0, $length);
    }
}


