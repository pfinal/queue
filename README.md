# Queue

一个简单易用的PHP队列组件

## 使用composer 安装

```
composer require pfinal/queue
```

使用示例

```
<?php

$queue->push(function (Job $job) use ($email, $text) {
    if( Mail::send($email, $text) ){
    
        //任务执行成功后删除job
        $job->delete();
        
    }else{
        
        if ($this->attempts() > 10) {
        
            //todo 处理任务失败业务逻辑
            //...
             
            $job->fail();
            
        }else{
        
            //延时重试
            $delay = $this->attempts() * 5;
            $job->release($delay);
            
        }
    }
});

```

命令行监听

```
php console queue:listen
php console queue:listen --queue=default
console queue:listen --memory=1024 --timeout=3600
```

database驱动需要的表:

```sql
CREATE TABLE `pre_job` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` text  NOT NULL,
  `attempts` tinyint unsigned NOT NULL,      -- 第一次执行失败后，记录重试次数(加1等于总执行次数)
  `reserved` tinyint unsigned NOT NULL,
  `reserved_at` DATETIME NOT NULL,
  `available_at` DATETIME NOT NULL,      -- 设置job生效时间，用于创建延时任务(delay)
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS pre_job_failed;
CREATE TABLE `pre_job_failed` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` text,
  `payload` text,
  `failed_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```