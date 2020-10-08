<?php

use Helpers\MediaHelper;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Patterns\MessageFactory\Factory as MessageFactory;
use Helpers\ConfigHelper;
use Gumlet\ImageResize;

require __DIR__ . '/vendor/autoload.php';

const MEDIA_DIR = '/var/www/media.chat.indigo24.com/';
const MEDIA_URL = 'https://media.chat.indigo24.com/media/';

$capsule = new Illuminate\Database\Capsule\Manager();

$config = ConfigHelper::getDbConfig('chat_db');
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


$mediaUrl = 'http://media.loc/files';

$http = new Swoole\Http\Server("0.0.0.0", 9517);
$http->set([
    'package_max_length' => 100 * 1024 * 1024,
]);


$http->on('request', function ($request, $response) {
    $mimeType = mime_content_type($request->files['file']['tmp_name']);
    $group = $request->post['group'] ?? null;

    if ($group && MediaHelper::checkAllowedMimeType($mimeType)) {
        $extension = '.jpg';
        $strFileName = Str::random(rand(30, 35));

        $fileName = $strFileName . '_AxB';

        $fileName .= $extension;

        $resizeFileName = $strFileName . "_200x200" . $extension;

        $filePath = "/var/www/media.chat.indigo24.com/media/images/{$fileName}";

        move_uploaded_file($request->files['file']['tmp_name'], $filePath);

        $image = new ImageResize($filePath);
        $image->crop(200, 200, true, ImageResize::CROPCENTER);
        $image->save("/var/www/media.chat.indigo24.com/media/images/{$resizeFileName}");


        $response->header("Content-Type", "application/json");
        $response->end(json_encode([
            'file_name' => $fileName,
            'success' => true,
        ]));
    } elseif (!$group && MediaHelper::checkAllowedMimeType($mimeType)) {
        $responseData = MessageFactory::getItem($request->post['type'])->upload($request);

        // сохраняю в истории загрузок пользователя

        $fileId = MediaHelper::saveUploadHistory([
            'user_id' => $request->post['user_id'],
            'mime_type' => $responseData['data']['extension'] ?? $request->files['file']['type'],
            'time' => time(),
            'file_name' => $responseData['file_name'],
        ]);

        $response->header("Content-Type", "application/json");
        $responseData['data']['file_id'] = $fileId;
        $response->end(json_encode($responseData['data']));
    } else {
        $response->end(json_encode([
            'status' => false,
            'message' => 'не удалось загрузить файл неподходящее расширение'
        ]));
    }
});

$http->start();
