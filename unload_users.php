<?php

//use Helpers\ConfigHelper;
use Helpers\PhoneHelper;

require __DIR__ . '/vendor/autoload.php';

//$capsule = new \Illuminate\Database\Capsule\Manager();
//$config = ConfigHelper::getDbConfig('mobile_db');

/*$capsule->addConnection([
    'driver' => $config['driver'],
    'host' => $config['host'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => $config['password'],
    'charset' => $config['charset'],
    'collation' => $config['collation'],
    'prefix' => $config['prefix'],
]);*/

//$capsule->setAsGlobal();
//$capsule = new \Illuminate\Database\Capsule\Manager();

while (true) {
    $users = \Illuminate\Database\Capsule\Manager::table('customers')->get();
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);
    foreach ($users as $user) {
        $phoneInCorrectFormat = PhoneHelper::replaceForSeven($user->phone);
        $redis->zAdd("users:phones", ['NX'], $phoneInCorrectFormat, $user->id);

        $redis->set("user:avatar:{$user->id}", $user->avatar);
        $redis->set("user:name:{$user->id}", $user->name);
        $redis->set("user:email:{$user->id}", $user->email);
    }
    $redis->close();
}
