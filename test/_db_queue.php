<?php

$dbConfig = array(
    'dsn' => 'mysql:host=127.0.0.1;dbname=test',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => 'pre_',
);

$queue = new \PFinal\Queue\Driver\Database(['dbConfig' => $dbConfig]);

return $queue;