<?php

require __DIR__ . '/../vendor/autoload.php';

$capsule = new Illuminate\Database\Capsule\Manager();

use Illuminate\Database\Capsule\Manager as DB;

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'chat',
    'username' => 'admin',
    'password' => '123',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();

$allUsers = DB::table('users')->get(['id','name','avatar','phone']);
$redis = new Redis();
$redis->connect('127.0.0.1',6379);

foreach ($allUsers as $user) {
    $redis->set("user:avatar:{$user->id}",$user->avatar);
    $redis->set("user:name:{$user->id}",$user->name);
    $redis->set("user:phone:{$user->phone}",$user->hpone);

}

$redis->close();