<?php

namespace PFinal\Queue\Driver;

/**
 * 对列驱动
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class QueueDriver
{
    //队列管道名称，默认为default
    public $defaultTube = 'default';

    /**
     * 推送一个新任务到队列中
     * @param string $class 处理任务的类和方法，例如'Jobs\Email@send'，如果不指定方法，默认调用fire方法。也可以是一个匿名函数。
     *
     * fire方法接受一个 Job 实例对像 和一个$data。$data是调用Queue::push方法传递的第二个参数值
     *      pulic function fire($job, $data){
     *          //处理这个job ...
     *          //当处理完成，从队列中将它删除
     *          $job->delete();
     *          //或处理失败时，将一个任务放回队列
     *          $job->release(5);
     *      }
     *
     * 直接使用匿名函数示例
     * Queue::push(function (Job $job) use ($email, $text) {
     *      if( Mail::send($email, $text) ){
     *          $job->delete();
     *      }else{
     *          $job->release(5);
     *      }
     *
     * });
     *
     *
     * @param mixed $data 需要传递给处理器的数据 如果第一个参数为匿名函数，此参数无效
     * @return mixed 返回jobId
     */
    public function push($class, $data = null, $queue = null)
    {
    }

    /**
     * 返回一个工作任务
     *
     * database驱动，无可用job时返回false
     * pheanstalk驱动，无可用job时, 将一直等待, 直到一个 job 可用
     *
     * @return \PFinal\Queue\Job | null
     */
    public function pop($queue = null)
    {
    }

    /**
     * 获取队列大小
     *
     * @param  string $queue
     * @return int
     */
    public function size($queue = null)
    {
        return 0;
    }

    /**
     * 将Queue::push的内容，序列化为字符串，如果是匿名函数，则包装为PFinal\Queue\QueueClosure对象
     * @param $class
     * @param null $data
     * @return string
     */
    public static function serialize($class, $data = null)
    {
        if ($class instanceof \Closure) {
            $data = ['closure' => (new \SuperClosure\Serializer())->serialize($class)];
            $class = 'PFinal\Queue\QueueClosure';
        }

        return serialize(array($class, $data));
    }

}
