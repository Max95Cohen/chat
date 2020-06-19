<?php

namespace Patterns\MessageStrategy\Interfaces;

interface BaseStrategyInterface
{

    public function setStrategy(string $strategy) :void;


    public function executeStrategy(string $function);

}