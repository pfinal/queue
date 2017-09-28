<?php

namespace PFinal\Queue\Driver;

use PFinal\Database\Builder;
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

    /** @var Builder */
    protected $db;

    /**
     * 超时被终止的job，再次被拉起的间隔(秒)
     *
     * 参数项 --timeout 的值应该是中小于配置项 retryAfter 的值,这是为了确保队列进程总在任务重试以前关闭
     * 如果 --timeout 比 retryAfter 大，则你的任务可能被执行两次
     *
     * @var int|null
     */
    protected $retryAfter = 60;


    public function __construct(array $config = array())
    {
        foreach ($config as $k => $v) {
            $this->$k = $v;
        }

        $this->db = new Builder($this->dbConfig);
    }

    public function push($class, $data = null, $queue = null, $delay = 0)
    {
        return $this->pushToDatabase(parent::serialize($class, $data), $delay, 0, $queue);
    }

    public function pop($queue = null)
    {
        $queue = is_null($queue) ? $this->defaultTube : $queue;

        if (!is_null($this->retryAfter)) {
            $this->releaseJobsThatHaveBeenReservedTooLong($queue);
        }

        return $this->getNextAvailableJob($queue);
    }

    public function delete($job)
    {
        $this->db->table($this->table)->where('id=?', [$job['id']])->delete();
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string $queue
     * @param  string $payload
     * @return void
     */
    public function log($queue, $payload)
    {
        $failed_at = date('Y-m-d H:i:s');
        $this->db->table($this->tableFailed)->insert(compact('queue', 'payload', 'failed_at'));
    }

    /**
     * 保存到数据库
     * @param int $delay 延时 以秒为单位的整数（从当前算起的时间差）
     * @param string $payload
     * @param int $attempts
     * @return int
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

    public function release($job, $delay)
    {
        $expired = date('Y-m-d H:i:s', time() + $delay);

        $sql = "UPDATE {$this->table} SET reserved=0, reserved_at=?,available_at=?, attempts=attempts+1 WHERE id=?";
        $this->db->getConnection()->execute($sql, [date('Y-m-d H:i:s'), $expired, $job['id']]);
    }

    /**
     * 获取下一个有效job
     * @return array|null
     */
    protected function getNextAvailableJob($queue)
    {
        $this->db->getConnection()->beginTransaction();

        $job = $this->db->table($this->table)->lockForUpdate()
            ->where('queue=?', [$queue])
            ->where('reserved=0')
            ->where('available_at<=?', [date('Y-m-d H:i:s')])
            ->orderBy('id asc')
            ->findOne();

        if ($job !== null) {
            if ($this->db->table($this->table)->update([
                'reserved' => 1,
                'reserved_at' => date('Y-m-d H:i:s'),
            ], 'id=?', [$job['id']])
            ) {
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
     */
    protected function releaseJobsThatHaveBeenReservedTooLong($queue)
    {
        $expired = date('Y-m-d H:i:s', time() - $this->retryAfter);

        $sql = "UPDATE {$this->table} SET reserved=0, reserved_at=?, attempts=attempts+1 "
            . "WHERE queue=? AND reserved=1 AND reserved_at<=?";
        $this->db->getConnection()->execute($sql, [date('Y-m-d H:i:s'), $queue, $expired]);
    }
}


