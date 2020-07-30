<?php


namespace Helpers;


use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Str;
use Redis;

class MediaHelper
{

    /**
     * @return string[]
     */
    private static function getAllowedMimeTypes()
    {
        return [
            'images/gif',
            'image/gif',
            'images/jpeg',
            'image/jpeg',
            'images/png',
            'image/png',
            'application/x-shockwave-flash',
            'images/psd',
            'image/psd',
            'images/tiff',
            'image/tiff',
            'application/octet-stream',
            'images/jp2',
            'image/jp2',
            'images/iff',
            'images/vnd.wap.wbmp',
            'application/pdf',
            'images/xbm',
            'images/vnd.microsoft.icon',
            'images/webp',
            'audio/aac',
            'application/x-abiword',
            'application/x-freearc',
            'video/x-msvideo',
            'application/vnd.amazon.ebook',
            'images/bmp',
            'image/bmp',
            'application/x-bzip',
            'application/x-bzip2',
            'text/css',
            'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-fontobject',
            'application/epub+zip',
            'application/gzip',
            'text/calendar',
            'text/javascript',
            'application/json',
            'application/ld+json',
            'audio/mpeg',
            'video/mpeg',
            'application/vnd.apple.installer+xml',
            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.text',
            'audio/ogg',
            'video/ogg',
            'application/ogg',
            'audio/opus',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.rar',
            'application/rtf',
            'images/svg+xml',
            'application/x-shockwave-flash',
            'application/x-tar',
            'images/tiff',
            'video/mp2t',
            'font/ttf',
            'text/plain',
            'application/vnd.visio',
            'audio/wav',
            'audio/webm',
            'video/webm',
            'images/webp',
            'font/woff',
            'font/woff2',
            'application/xhtml+xml',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/xml',
            'text/xml',
            'application/zip',
            'video/3gpp',
            'audio/3gpp',
            'video/3gpp2',
            'audio/3gpp2',
            'application/x-7z-compressed',
            'video/x-flv',
            'video/mp4',
            'application/x-mpegURL',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'image/png',
        ];
    }


    /**
     * @param $type
     * @return bool
     */
    public static function checkAllowedMimeType($type) :bool
    {
        return in_array($type,self::getAllowedMimeTypes());
    }

    /**
     * @param $type
     * @return mixed|string
     */
    public static function getExtensionByMimeType($type) :string
    {
        return '.'.explode('/',$type)[1];
    }


    /**
     * @param array $data
     * @return int
     */
    public static function saveUploadHistory(array $data)
    {
        return Manager::table('user_uploads')->insertGetId($data);
    }

    public static function getMediaExtension()
    {

    }

    /**
     * @param string $extension
     * @return string
     */
    public static function generateFileName(string $extension)
    {
        return Str::random(rand(30, 35)) . $extension;
    }

    /**
     * @param string $extension
     * @param int $size
     * @return string
     */
    public static function generateFileNameForSmallImage(string $extension, int $size = 200)
    {
        return Str::random(rand(30,35)) ."_{$size}x{$size}". $extension;
    }

    /**
     * @param array $data
     * @param Redis $redis
     */
    public static function messageEditInRedis(array $data, Redis $redis) :void
    {
        $checkMessageInRedis = $redis->hGetAll($data['message_id']);
        if ($checkMessageInRedis) {
            $redis->hMSet($data['message_id'],'attachments',$data['attachments']);
            $redis->hMSet($data['message_id'],'status',MessageHelper::MESSAGE_EDITED_STATUS);
        }

    }

    /**
     * @param array $data
     * @param $sql
     */
    public static function messageEditInMysql(array $data, $sql) :void
    {
        $sql->update([
            'attachments' => $data['attachments'],
            'edited_time' => time(),
            'status' => MessageHelper::MESSAGE_EDITED_STATUS,
        ]);
    }

}