<?php


namespace Helpers;


use Illuminate\Support\Str;
use Validation\Interfaces\BaseValidationInterface;

class ValidationHelper
{

    const NAMESPACE = 'Validation\\';

    /*
     * This function convert route:cmd and get validation class for request.
     * Be sure to name the class by the rule.
     * Example: user:check (route cmd) UserCheckValidation (validation class name)
     */


    /**
     * @param string $cmd
     * @return BaseValidationInterface
     */
    public static function getValidationClass(string $cmd) :BaseValidationInterface
    {
        $classNameInSnakeKeys = str_replace(':','_',$cmd);

        $class = self::NAMESPACE.ucfirst(Str::camel($classNameInSnakeKeys)) .'Validation';

        return new $class;
    }




}