<?php


namespace Controllers;


use Helpers\ResponseFormatHelper;
use Traits\RedisTrait;

class StickerController
{
    use RedisTrait;

    const STICKER_URL = 'https://media.chat.indigo24.xyz/media/stickers/';


    public function getAll(array $data)
    {
        $allPacks = $this->redis->zRange("all:st:pack", 0, -1,true);


        $response = [];
        $response['media_url'] = self::STICKER_URL;
        foreach ($allPacks as $pack) {

            $stickerId = intval($pack);

            $packStickers = $this->redis->zRange("st:pack:{$stickerId}", 0, -1);
            foreach ($packStickers as $sticker) {
                $stickerData = $this->redis->hGetAll("sticker:{$sticker}");

                $response['packs'][$pack]['stickers'][] = [
                    'id' => $stickerData['id'],
                    'path' => $stickerData['path'],
                ];

                if ($stickerData['preview']) {
                    $response[$pack]['preview'] = $stickerData['path'];
                }

            }

        }
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$response);

    }


}