<?php

namespace Gone\AppCore\Validator\Rules;

use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Rules\AbstractRule;

abstract class BaseRule extends AbstractRule
{
    protected function createException()
    {
        return new ValidationException();
    }
}