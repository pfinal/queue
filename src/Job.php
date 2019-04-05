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

    /**
     * 标记删除
     */
    public function delete()
    {
        $this->deleted = true;
    }

    /**
     * 是否被删除
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * job的类名
     *
     * @return string
     */
    public function getName()
    {
        return current(unserialize($this->getRawBody()));
    }

    /**
     * 队列
     *
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    public function fail()
    {
        if ($this->isDeleted()) {
            return;
        }
        $this->delete();
        $this->failed();
    }

    /**
     * 失败后处理
     */
    protected function failed()
    {

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
     *
     * @return int
     */
    public function attempts()
    {

    }

    /**
     * Job ID
     *
     * @return mixed
     */
    public function getJobId()
    {

    }

    /**
     * Raw Body
     *
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