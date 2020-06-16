<?php


namespace Middlewars\Interfaces;


interface BaseMiddlewareInterface
{

    public function handle(array $data);

    public function isNext() :bool;

    public function setNext(bool $next): void;

}