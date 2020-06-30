<?php
require __DIR__ . '/../vendor/autoload.php';

$capsule = new Illuminate\Database\Capsule\Manager();

use Helpers\ConfigHelper;
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
while (true){
    $allUsers = DB::table('customers')->get(['id','name','avatar','phone']);
    $redis = new Redis();

    $redis->pconnect('127.0.0.1',6379);

    foreach ($allUsers as $user) {
        $redis->set("user:avatar:{$user->id}",$user->avatar);
        $redis->set("user:name:{$user->id}",$user->name);
        $redis->set("user:phone:{$user->phone}",$user->phone);

    }

    $redis->close();
    sleep(1000);

}
