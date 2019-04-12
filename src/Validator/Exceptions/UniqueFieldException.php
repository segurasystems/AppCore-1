<?php

namespace Gone\AppCore\Validator\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

class UniqueFieldException extends ValidationException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be unique',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be unique',
        ],
    ];
}