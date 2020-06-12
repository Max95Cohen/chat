<?php

namespace Patterns\MessageStrategy;

use BaseStrategyInterface;

class Strategy implements BaseStrategyInterface
{
    protected $count = 20;
    protected $page = 1;

}