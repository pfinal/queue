<?php

namespace PFinal\Queue\Test {

    use PFinal\Queue\Job;

    class Send
    {
        public function demo(Job $job, $data)
        {
            echo $data['email'];
            echo $data['content'];

            $job->delete();

            //$job->release(5);

        }
    }
}