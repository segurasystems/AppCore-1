<?php

namespace Gone\AppCore\Validator\Rules;

use Respect\Validation\Rules\AbstractRule;

abstract class BaseRule extends AbstractRule
{
    protected function createException()
    {
        $currentFqn = get_called_class();
        $exceptionFqn = str_replace('\\Rules\\', '\\Exceptions\\', $currentFqn);
        $exceptionFqn .= 'Exception';

        return new $exceptionFqn();
    }
}