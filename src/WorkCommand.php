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
                new InputOption('tries', null, InputOption::VALUE_OPTIONAL, 'tries', 3),

                //工作执行时发生错误, 自动release, 设置工作再次能够执行的秒数。 Amount of time to delay failed jobs
                new InputOption('delay', null, InputOption::VALUE_OPTIONAL, 'delay', 10),

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

            $this->process($job, $maxTries, $delay);

        } else {
            //没有任务时，让监听器在拉取新工作时要等待几秒
            sleep($input->getOption('sleep'));
        }
    }

    public function process(Job $job, $maxTries = 0, $delay = 0)
    {
        $ex = null;

        try {

            $job->resolveAndFire(unserialize($job->getRawBody()));

        } catch (\Exception $ex) {
            $this->output->writeln($ex->getMessage() . ' ' . $ex->getFile() . '#' . $ex->getLine());
        } catch (\Throwable $ex) {
            $this->output->writeln($ex->getMessage() . ' ' . $ex->getFile() . '#' . $ex->getLine());
        }

        if ($job->isDeleted()) {
            $this->output->writeln(sprintf('%s Processed: #%s %s', date('Y-m-d H:i:s'), $job->getJobId(), $job->getName()));
            return;
        }

        if (!$job->isRelease()) {

            if ($maxTries > 0 && $job->attempts() >= $maxTries) {
                $this->logFailedJob($job, $ex ? $ex : new \Exception('attempted too many times: ' . $job->attempts()));
                return;
            }

            $job->release($delay);
        }

        $this->output->writeln(sprintf('%s Released: #%s %s', date('Y-m-d H:i:s'), $job->getJobId(), $job->getName()));
    }

    protected function logFailedJob(Job $job, $e = null)
    {
        $this->output->writeln(sprintf('%s Failed: #%s %s', date('Y-m-d H:i:s'), $job->getJobId(), $job->getName()));

        $job->fail($e);
    }
}
