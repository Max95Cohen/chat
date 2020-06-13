<?php


namespace Midlewars\Interfaces;


interface BaseMiddlewareInterface
{
    public function handle(array $data);
}