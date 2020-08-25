<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class ChatMembersAddValidation implements BaseValidationInterface
{

    /**
     * @param array $params
     * @return mixed|void
     */
    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule("required",'chat_id');
        $v->rule("integer",'chat_id');

        $v->rule('required','userToken');

        $v->rule('required','members_id');
        $v->rule("regex",'members_id','#^message:\d{1,}:\d{1,}$#')->message("Поле message_id не в формате message:{user_id}:{int} я получил {$params['message_id']}");

        $v->validate();
    }
}