<?php

use Helpers\MediaHelper;
use Illuminate\Support\Str;
use Patterns\MessageFactory\Factory as MessageFactory;

require __DIR__ . '/vendor/autoload.php';

const MEDIA_DIR = __DIR__;
const MEDIA_URL = 'http://media.loc/media/';


$http = new Swoole\Http\Server("127.0.0.1", 9517);

$capsule = new Illuminate\Database\Capsule\Manager();

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


$mediaDir = __DIR__ . '/media/files';
$mediaUrl = 'http://media.loc/files';

$http->on('request', function ($request, $response) use ($mediaDir, $mediaUrl) {

    $mimeType = $request->files['file']['type'];

    if (MediaHelper::checkAllowedMimeType($mimeType)) {

        $responseData = MessageFactory::getItem($request->post['type'])->upload($request);

        // сохраняю в истории загрузок пользователя
        MediaHelper::saveUploadHistory([
            'user_id' => $request->post['user_id'],
            'mime_type' => $request->files['file']['type'],
            'time' => time(),
            'file_name' => $responseData['file_name'],
        ]);

        $response->header("Content-Type", "application/json");

        $response->end(json_encode($responseData['data']));


    }
});
$http->start();