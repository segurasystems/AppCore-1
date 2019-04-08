<?php

namespace Gone\AppCore\Interfaces;

use Gone\SDK\Common\Abstracts\AbstractModel;

interface CleanerInterface
{
    public function clean(AbstractModel $model) : void;
    public function getRules() : array;
}