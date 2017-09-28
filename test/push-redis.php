<?php

namespace PFinal\Queue\Test {

    use PFinal\Queue\Job;

    require_once __DIR__ . '/../vendor/autoload.php';

    date_default_timezone_set('PRC');

    $queue = new \PFinal\Queue\Driver\Redis();

    //$id = $queue->push('Foo@bar', ['id' => 1], 'default', 10);
    //var_dump($id);

    $job = $queue->pop();
    if ($job) {
        var_dump($job->getJobId());
        $job->delete();
    } else {
        var_dump($job);
    }

}