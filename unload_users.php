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

$users = \Illuminate\Database\Capsule\Manager::table('customers')->get();
$redis = new \Redis();
$redis->connect('127.0.0.1',6379);


//$test = $redis->zRevRangeByScore('user:chats:1','+inf','-inf',['limit'=>[20,40]]);
//
//dd($test);


foreach ($users as $user) {
    $phoneInCorrectFormat = PhoneHelper::replaceForSeven($user->phone);
//    $redis->zAdd("users:phones", ['NX'],$phoneInCorrectFormat,$user->id);
//    $redis->zAdd("users:phones", ['NX'],$phoneInCorrectFormat,$user->id);
//    $redis->hSet($user->id,'token',$user->unic);
//    $redis->zAdd("users:avatars", ['CX'],$user->id,$user->avatar);

//    $redis->set("user:avatar:{$user->id}",$user->avatar);
//    $redis->set("user:name:{$user->id}",$user->name);
    $redis->set("user:email:{$user->id}",$user->email);


//    $redis->zAdd("users:names",['CX'],$user->id,$user->name);
}


