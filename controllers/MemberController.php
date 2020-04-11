<?php


namespace Controllers;


use Redis;

class MemberController
{

    private $r;

    public function __construct()
    {
        $this->r = new Redis();
        $this->r->connect('127.0.0.1',6379);
    }

    public function store()
    {
        $data = [
            'chat_id' => 6,
            'unic' => 102,
        ];
        $chatId = $data['chat_id'];
        //@TODO get user id by unic

        $userId = 102;
        $chatMemberKey = "chat:member:count:{$chatId}";
        $chatMemberCount = $this->r->incrBy($chatMemberKey,1);

        $this->r->zAdd("chat:members:{$chatId}",['NX'],$chatMemberCount,$userId);

        // @TODO add json response for front end dev
    }

}