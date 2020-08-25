<?php


namespace Validation;


use Helpers\MessageHelper;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class MessageForwardValidation implements BaseValidationInterface
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

        $v->rule('required','forward_messages_id');
        $v->rule("regex",'forward_messages_id','#message:\d{1,}:\d{1,}\,{0,1}#')->message("Поле forward_messages_id не в формате message:{user_id}:{int}");

        $v->rule("required",'userToken');

        $v->validate();

    }
}