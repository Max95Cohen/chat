<?php

namespace Patterns\ChatFactory\Interfaces;


use Redis;

interface BaseChatCreateInterface
{
    public function create(array $data, Redis $redis);
}