<?php


namespace Controllers;


use Helpers\PhoneHelper;
use Helpers\ResponseFormatHelper;
use Traits\RedisTrait;

class ContactController
{
    use RedisTrait;


    /**
     * @param array $data
     * @return array[]
     */
    public function get(array $data)
    {
        $page = $data['page'] ?? 1;
        $count = $data['count'] ?? 20;

        $start = $count * $page - $count;
        $end = $start + $count;

        $contacts = $this->redis->zRevRangeByScore("usr:con:{$data['user_id']}", '+inf', '-inf', ['limit' => [$start, $end], 'withscores' => true]);
        $userContacts = [];

        $userContacts['pagination'] = [
            'page' => $page,
            'count' => $count
        ];

        foreach ($contacts as $phone => $contact) {
            $userId = intval($contact);

            $userContacts['contacts'][] = [
                'user_id' => $userId,
                'user_name' => $this->redis->get("user:name:{$userId}"),
                'chat_id' => $this->redis->get("private:{$data['user_id']}:{$contact}"),
                'phone' => $phone
            ];

        }


        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$userContacts);

    }


    /**
     * @param array $data
     * @return array[]
     */
    public function save(array $data)
    {
        $phone = $data['phone'];
        $userId = $data['user_id'];

        if ($phone) {
            $phone = PhoneHelper::replaceForSeven($data['phone']);

            $customerId = $this->redis->get("user:phone:{$phone}");

            if ($customerId) {
                $this->redis->zAdd("usr:con:{$userId}",['NX'],$customerId,$phone);

                return ResponseFormatHelper::successResponseInCorrectFormat([$userId],[
                    'user_id' => $customerId,
                    'user_name' => $this->redis->get("user:name:{$customerId}"),
                    'phone' => $phone,
                    'chat_id' =>$this->redis->get("private:{$data['user_id']}:{$customerId}"),
                    'status' => true,
                ]);

            }
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$userId],[
            'phone' => $phone,
            'status' => false,
        ]);


    }








}