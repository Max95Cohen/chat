<?php

namespace Middlewars\Validation;

use Helpers\ResponseFormatHelper;
use Helpers\ValidationHelper;
use Middlewars\Interfaces\BaseMiddlewareInterface;

class ValidationMiddleware implements BaseMiddlewareInterface
{
    private bool $next = false;

    public function handle(array $data)
    {
        $validation = $data['validation'] ?? null;

        if ($validation) {
            $validationErrors = ValidationHelper::getValidationClass($data['cmd_name'])->validate($data);

            if ($validationErrors) {
                return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $validationErrors);
            }
        }

        $this->setNext(true);
    }

    /**
     * @return bool
     */
    public function isNext(): bool
    {
        return $this->next;
    }

    /**
     * @param bool $next
     */
    public function setNext(bool $next): void
    {
        $this->next = $next;
    }
}
