<?php


interface BaseStrategyInterface
{

    public function setStrategy(string $strategy) :void;


    public function executeStrategy(string $function);

}