<?php

namespace PFinal\Queue;

use PFinal\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class WorkCommand extends Command
{
    protected $name = 'queue:work';
    protected $description = 'Process the next job on a queue';

    /** @var Container */
    protected $app;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * WorkCommand constructor.
     * @param Container $app 必须传入此参数
     * @param null $name
     */
    public function __construct($app = null, $name = null)
    {
        $this->app = $app;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName($this->name)
            ->setDescription($this->description)
            ->setDefinition(array(

                //The queue to listen on
                new InputOption('queue', null, InputOption::VALUE_OPTIONAL, 'queue', 'default'),

                //The memory limit in megabytes
                new InputOption('memory', null, InputOption::VALUE_OPTIONAL, 'memory', 128),

                //指定尝试上限 判断attempts, 在工作被执行到一定的次数时，他将会添加至job_failed 数据表里
                new InputOption('tries', null, InputOption::VALUE_OPTIONAL, 'tries', 0),

                //工作执行时发生错误, 自动release, 设置工作再次能够执行的秒数。 Amount of time to delay failed jobs
                new InputOption('delay', null, InputOption::VALUE_OPTIONAL, 'delay', 3),

                //设置给每个工作允许执行的秒数
                new InputOption('timeout', null, InputOption::VALUE_OPTIONAL, 'timeout', 60),

                //指定队列休息时间,没有任务时,让监听器在拉取新工作时要等待几秒
                new InputOption('sleep', null, InputOption::VALUE_OPTIONAL, 'sleep', 1),
            ));
    }

    /**
     * Execute the console command.
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $queue = $input->getOption('queue');
        $job = $this->app['queue']->pop($queue);
        if (!is_null($job)) {
            $maxTries = $input->getOption('tries');
            $delay = $input->getOption('delay');

            return $this->process($job, $maxTries, $delay);
        }

        //没有任务时，让监听器在拉取新工作时要等待几秒
        sleep($input->getOption('sleep'));
    }

    public function process(Job $job, $maxTries = 0, $delay = 0)
    {
        if ($maxTries > 0 && $job->attempts() > $maxTries) {
            return $this->logFailedJob($job);
        }

        try {
            // First we will fire off the job. Once it is done we will see if it will
            // be auto-deleted after processing and if so we will go ahead and run
            // the delete method on the job. Otherwise we will just keep moving.

            $job->resolveAndFire(unserialize($job->getRawBody()));

            if ($job->isDeleted()) {
                $this->output->writeln(sprintf('%s Processed: #%s %s', date('Y-m-d H:i:s'), $job->getJobId(), $job->getName()));
            } else {
                $this->output->writeln(sprintf('%s Released: #%s %s', date('Y-m-d H:i:s'), $job->getJobId(), $job->getName()));
            }

            return ['job' => $job, 'failed' => false];
        } catch (\Exception $ex) {
            // If we catch an exception, we will attempt to release the job back onto
            // the queue so it is not lost. This will let is be retried at a later
            // time by another listener (or the same one). We will do that here.
            if (!$job->isDeleted()) $job->release($delay);

            $this->output->writeln(sprintf('%s Released: #%s %s', date('Y-m-d H:i:s'), $job->getJobId(), $job->getName()));

            throw $ex;
        }
    }

    protected function logFailedJob(Job $job)
    {
        $this->output->writeln(sprintf('%s Failed: #%s %s', date('Y-m-d H:i:s'), $job->getJobId(), $job->getName()));

        $job->fail();

        return ['job' => $job, 'failed' => true];
    }

}
