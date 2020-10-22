<?php

namespace Controllers;

use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Traits\RedisTrait;

class SettingsController
{
    use RedisTrait;

    /**
     * @param array $data
     * @return array[]
     */
    public function setSettings(array $data): array
    {
        $settings = $data['settings'];
        $responseSettings = [];
        $settingsKeys = UserHelper::getAllSettingsKeys();

        foreach ($settings as $key => $setting) {

            if (in_array($key, $settingsKeys)) {
                $responseSettings['settings'][$key] = $setting;
                $this->redis->hSet("u:sets:{$data['user_id']}", $key, $setting);
            }
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseSettings);
    }

    /**
     * @param array $data
     * @return array[]
     */
    public function getSettings(array $data): array
    {
        $savedSettings = $this->redis->hGetAll("u:sets:{$data['user_id']}");
        $settingsKeys = UserHelper::getAllSettingsKeys();
        $responseSettings = [];

        foreach ($settingsKeys as $settingKey) {
            $responseSettings['settings'][$settingKey] = $savedSettings[$settingKey] ?? 0;
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseSettings);
    }
}
