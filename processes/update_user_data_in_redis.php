<?php
require __DIR__ . '/../vendor/autoload.php';

$capsule = new Illuminate\Database\Capsule\Manager();

use Illuminate\Database\Capsule\Manager as DB;

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
//    'database' => 'indigo24Mobile',
    'database' => 'chat',
    'username' => 'admin',
    'password' => '123',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
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
