<?php

namespace Gone\AppCore\Interfaces;

use Gone\SDK\Common\Abstracts\AbstractModel;

interface ValidatorInterface
{
    public function validate(AbstractModel $model, string $scenario = "default"): bool;

    public function getErrors(): array;

    public function stopOnFirstError();
}