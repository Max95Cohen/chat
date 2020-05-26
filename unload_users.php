<?php

use Helpers\PhoneHelper;

require __DIR__ . '/vendor/autoload.php';



//$testController = new \Controllers\UserController();
//
//$testController->checkExist('77077111154');

$capsule = new \Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'indigo24Mobile',
    'username'  => 'admin',
    'password'  => '123',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();



$capsule = new \Illuminate\Database\Capsule\Manager();

$users = \Illuminate\Database\Capsule\Manager::table('customers')->limit(10)->get();
$redis = new \Redis();
$redis->connect('127.0.0.1',6379);


foreach ($users as $user) {
//    $phoneInCorrectFormat = PhoneHelper::replaceForSeven($user->phone);
//    $redis->zAdd("users:phones", ['NX'],$phoneInCorrectFormat,$user->id);
    $redis->hSet($user->id,'token',$user->unic);


    dump($user->id);
}


