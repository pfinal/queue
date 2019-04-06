<?php

namespace PFinal\Queue\Driver;

use PFinal\Database\Builder;
use PFinal\Queue\Job;
use PFinal\Queue\Job\DatabaseJob;

/**
 * 数据库队列驱动
 *
 * $app->register(new QueueProvider(), ['queue.config' => ['class'=>'PFinal\Queue\Driver\Database', 'table' => '{{pre_job}}', 'tableFailed' => '{{pre_job_failed}}']]);
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class Database extends QueueDriver
{
    //数据库表名
    protected $table = '{{%job}}';

    //存放失败的job
    protected $tableFailed = '{{%job_failed}}';

    protected $dbConfig;

    /** @var \PFinal\Database\Builder */
    protected $db;

    /**
     * 超时被终止的job，再次被拉起的间隔(秒)
     *
     * 参数项 --timeout 的值应该是中小于配置项 retryAfter 的值,这是为了确保队列进程总在任务重试以前关闭
     * 如果 --timeout 比 retryAfter 大，则你的任务可能被执行两次
     *
     * @var int|null
     */
    protected $retryAfter = 90;

    /**
     * Database constructor.
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        foreach ($config as $k => $v) {
            $this->$k = $v;
        }

        $this->db = new Builder($this->dbConfig);
    }

    /**
     * @param string $class
     * @param null $data
     * @param null $queue
     * @param int $delay
     * @return int|mixed
     * @throws \PFinal\Database\Exception
     */
    public function push($class, $data = null, $queue = null, $delay = 0)
    {
        return $this->pushToDatabase(parent::serialize($class, $data), $delay, 0, $queue);
    }

    /**
     * @param null $queue
     * @return null|Job
     * @throws \PFinal\Database\Exception
     */
    public function pop($queue = null)
    {
        $queue = is_null($queue) ? $this->defaultTube : $queue;

        if (!is_null($this->retryAfter)) {
            $this->releaseJobsThatHaveBeenReservedTooLong($queue);
        }

        return $this->getNextAvailableJob($queue);
    }

    /**
     * @param $job
     * @throws \PFinal\Database\Exception
     */
    public function delete($job)
    {
        $this->db->table($this->table)
            ->where('id = ?', array($job['id']))
            ->delete();
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string $queue
     * @param  string $payload
     * @param  \Throwable|null $e
     * @throws \PFinal\Database\Exception
     */
    public function log($queue, $payload, $e = null)
    {
        $failed_at = date('Y-m-d H:i:s');

        if ($e instanceof \Throwable) {
            $exception = $e->getMessage() . "\n" . $e->getTraceAsString();
        } else {
            $exception = (string)$e;
        }

        $this->db->table($this->tableFailed)
            ->insert(compact('queue', 'payload', 'failed_at', 'exception'));
    }

    /**
     * 保存到数据库
     * @param int $delay 延时 以秒为单位的整数（从当前算起的时间差）
     * @param string $payload
     * @param int $attempts
     * @return int
     * @throws \PFinal\Database\Exception
     */
    public function pushToDatabase($payload, $delay = 0, $attempts = 0, $queue = null)
    {
        $queue = is_null($queue) ? $this->defaultTube : $queue;

        $availableAt = time() + $delay;

        return $this->db->table($this->table)->insertGetId([
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => $attempts,
            'reserved' => 0,
            'reserved_at' => '1970-01-01 00:00:00',
            'created_at' => date('Y-m-d H:i:s'),
            'available_at' => date('Y-m-d H:i:s', $availableAt),
        ]);
    }

    /**
     * @param Job $job
     * @param $delay
     * @return int|void
     * @throws \PFinal\Database\Exception
     */
    public function release($job, $delay)
    {
        //删除重建，相当于移到最后

        $this->db->table($this->table)
            ->where('id = ?', $job['id'])
            ->delete();

        $expired = date('Y-m-d H:i:s', time() + $delay);

        $job['reserved'] = 0;
        $job['reserved_at'] = date('Y-m-d H:i:s');
        $job['available_at'] = $expired;

        unset($job['id']);

        return $this->db->table($this->table)->insertGetId($job);
    }

    /**
     * 获取下一个有效job
     *
     * @return Job|null
     * @throws \PFinal\Database\Exception
     */
    protected function getNextAvailableJob($queue)
    {
        $this->db->getConnection()->beginTransaction();

        $job = $this->db->table($this->table)->lockForUpdate()
            ->where('queue = ?', [$queue])
            ->where('reserved = 0')
            ->where('available_at <= ?', [date('Y-m-d H:i:s')])
            ->orderBy('id asc')
            ->lockForUpdate()
            ->findOne();

        if ($job !== null) {

            $res = $this->db->table($this->table)
                ->where('id = ?', array($job['id']))
                ->update(array(
                    'reserved' => 1,
                    'reserved_at' => date('Y-m-d H:i:s'),
                ));

            if ($res) {
                $this->db->getConnection()->commit();
                return new DatabaseJob($this, $job, $queue);
            }
        }

        $this->db->getConnection()->rollBack();

        return null;
    }

    /**
     * Release the jobs that have been reserved for too long.
     *
     * @param  string $queue
     * @return void
     * @throws \PFinal\Database\Exception
     */
    protected function releaseJobsThatHaveBeenReservedTooLong($queue)
    {
        $expired = date('Y-m-d H:i:s', time() - $this->retryAfter);

        $this->db->table($this->table)
            ->where('queue = ? AND reserved = 1 AND reserved_at <= ?', array($queue, $expired))
            ->increment('attempts', 1, array(
                'reserved' => 0,
                'reserved_at' => date('Y-m-d H:i:s'),
            ));
    }
}
