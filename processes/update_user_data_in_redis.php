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




$messagessIds = [13974,13975];


DB::table('messages')->whereIn('id',$messagessIds)->update([
    'status' => 1,
]);


dd(1);








$allUsers = DB::table('customers')->get(['id','name','avatar','phone']);
$redis = new Redis();
$redis->connect('127.0.0.1',6379);

$redis->hSet("chat:message:create",'user_id',13);
$redis->hSet("chat:message:create",'text','чат создан');
$redis->hSet("chat:message:create",'chat_id',null);
$redis->hSet("chat:message:create",'status',1);
$redis->hSet("chat:message:create",'time',null);
$redis->hSet("chat:message:create",'type',7);

$redis->hSet("group:message:create",'user_id',13);
$redis->hSet("group:message:create",'text','групповой чат создан');
$redis->hSet("group:message:create",'chat_id',null);
$redis->hSet("group:message:create",'status',1);
$redis->hSet("group:message:create",'time',null);
$redis->hSet("group:message:create",'type',7);




foreach ($allUsers as $user) {
    $redis->set("user:avatar:{$user->id}",$user->avatar);
    $redis->set("user:name:{$user->id}",$user->name);
    $redis->set("user:phone:{$user->phone}",$user->phone);


}

$redis->close();