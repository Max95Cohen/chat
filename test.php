<?php
require __DIR__ . '/vendor/autoload.php';


$c = new \Controllers\UserController();

$data = [
    'phone' => '*113*8#',
    'user_id' => 1,
];
dd($c->checkExist($data));