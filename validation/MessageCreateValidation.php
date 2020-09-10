<?php


namespace Validation;


use Controllers\ChatController;
use Helpers\ChatHelper;
use Helpers\MessageHelper;
use Traits\RedisTrait;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class MessageCreateValidation implements BaseValidationInterface
{

    use RedisTrait;

    /**
     * @param array $params
     * @return array|bool|mixed
     */
    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule("required",'userToken');

        $v->rule("required",'chat_id');
        $v->rule("integer",'chat_id');

        $v->rule("required",'user_ids');

        $v->rule('in','message_type',MessageHelper::getMessageTypes());


        $v->rule("required",'phone');
        $v->rule("regex",'phone','#^\+\d{5,30}$|^\d{5,30}$#');

        $v->rule("type",'in',[
            ChatController::PRIVATE,
            ChatController::GROUP
        ]);

        $checkIsBot = ChatHelper::checkIsChatBot($params['chat_id'],$this->redis);

        $params['check_is_bot'] = $checkIsBot;

        $v->rule('regex','check_is_bot',"#1#")->message("нельзя писать боту");


        $this->redis->close();
        $v->validate();

        return $v->errors();
    }
}