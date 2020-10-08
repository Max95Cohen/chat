<?php

require __DIR__ . '/../vendor/autoload.php';

$capsule = new Illuminate\Database\Capsule\Manager();

use Helpers\ConfigHelper;
use Helpers\PhoneHelper;
use Illuminate\Database\Capsule\Manager as DB;

$config = ConfigHelper::getDbConfig('mobile_db');

$capsule->addConnection([
    'driver' => $config['driver'],
    'host' => $config['host'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => $config['password'],
    'charset' => $config['charset'],
    'collation' => $config['collation'],
    'prefix' => $config['prefix'],
]);

$capsule->setAsGlobal();

$allUsers = DB::table('customers')->get(['id', 'name', 'avatar', 'phone']);

$redis = new Redis();

$redis->pconnect('127.0.0.1', 6379);

foreach ($allUsers as $user) {
    $phoneInCorrectFormat = PhoneHelper::replaceForSeven($user->phone);

    $redis->set("user:avatar:{$user->id}", $user->avatar);
    $redis->set("user:name:{$user->id}", $user->name);
    $redis->set("userId:phone:{$user->id}", $user->phone);
    $redis->set("user:phone:{$phoneInCorrectFormat}", $user->id);
}

$redis->close();

echo "Success\n";
