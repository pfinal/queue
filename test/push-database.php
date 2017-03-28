<?php

namespace PFinal\Queue\Test {

    use PFinal\Queue\Job;

    require_once __DIR__ . '/../vendor/autoload.php';

    date_default_timezone_set('PRC');

    /** @var $queue \PFinal\Queue\Driver\Database */
    $queue = require '_db_queue.php';

    $mail = 'a@b.c';
    $content = 'test';

    $queue->push(function (\PFinal\Queue\Job $job) use ($mail, $content) {
        $job->delete();
    });


    $queue->push('PFinal\Queue\Test\Send@demo', ['email' => $mail, 'content' => $content]);



}