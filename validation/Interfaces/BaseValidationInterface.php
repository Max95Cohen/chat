<?php

namespace Validation\Interfaces;

interface BaseValidationInterface
{
    /**
     * @param array $params
     * @return mixed
     */
    public function validate(array $params);
}