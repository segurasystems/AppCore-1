<?php

namespace Gone\AppCore\Validator\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

class ForeignKeyException extends ValidationException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{ name }} must have a valid reference',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{ name }} must have no valid reference',
        ],
    ];
}