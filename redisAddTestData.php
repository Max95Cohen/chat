<?php

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$redis->hSet("Customer:113631", 'unique', '5e2a0557ef07f2ef0c4521ed3541b6512055fa52');
