<?php

namespace PFinal\Queue;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class ListenCommand extends Command
{
    protected $name = 'queue:listen';
    protected $description = 'Listen to a given queue';

    protected function configure()
    {
        $this->setName($this->name)
            ->setDescription($this->description)
            ->setDefinition(array(

                //The queue to listen on
                new InputOption('queue', null, InputOption::VALUE_OPTIONAL, 'queue', 'default'),

                //The memory limit in megabytes
                new InputOption('memory', null, InputOption::VALUE_OPTIONAL, 'memory', 128),

                //指定尝试上限 判断attempts 。 在工作被执行到一定的次数时，他将会添加至job_failed 数据表里。
                new InputOption('tries', null, InputOption::VALUE_OPTIONAL, 'tries', 3),

                //工作执行时发生错误，自动release, 设置工作再次能够执行的秒数。 Amount of time to delay failed jobs
                new InputOption('delay', null, InputOption::VALUE_OPTIONAL, 'delay', 10),

                //设置给每个工作允许执行的秒数
                new InputOption('timeout', null, InputOption::VALUE_OPTIONAL, 'timeout', 60),

                //指定队列休息时间,没有任务时，让监听器在拉取新工作时要等待几秒
                new InputOption('sleep', null, InputOption::VALUE_OPTIONAL, 'sleep', 1),
            ));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $listener = new Listener(COMMAND_PATH);

        $queue = $input->getOption('queue');
        $delay = $input->getOption('delay');
        $memory = $input->getOption('memory');
        $timeout = $input->getOption('timeout');

        $listener->setSleep($input->getOption('sleep'));
        $listener->setMaxTries($input->getOption('tries'));

        $listener->setOutputHandler(function ($type, $line) use ($output) {
            $output->writeln($line);
        });

        $listener->listen($queue, $delay, $memory, $timeout);
    }
}
