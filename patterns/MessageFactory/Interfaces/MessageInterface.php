<?php

namespace Patterns\MessageFactory\Interfaces;

use Redis;

interface MessageInterface
{

    public function addExtraFields(Redis $redis,string $redisKey, array $data) :void;

}