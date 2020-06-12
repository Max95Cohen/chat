<?php


interface MessageStrategyInterface
{
    public function getMessages(array $data) :array;

    public function setCount(?int $count) :int;

    public function setPage(?int $page) :int;


}