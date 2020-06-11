<?php


namespace Patterns\MessageFactory\Interfaces;


use Swoole\Http\Request;

interface MediaMessageInterface
{
    public function upload(Request $request) :array;

}