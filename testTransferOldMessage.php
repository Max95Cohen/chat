<?php

use Controllers\ChatController;
use Illuminate\Database\Capsule\Manager;

require __DIR__ . '/vendor/autoload.php';

$capsule = new \Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'indigo24Mobile',
    'username'  => 'admin',
    'password'  => '123',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_520_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();


$messages = Manager::table('messages_old')->where('message','LIKE','%[REPLY]%')->get();
$messageImageReply = Manager::table('messages_old')->where('id',284404)->first();
dd($messageImageReply);
foreach ($messages as $message) {
    dd($message);
}