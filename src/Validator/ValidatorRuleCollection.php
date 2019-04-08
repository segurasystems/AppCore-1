<?php

namespace Gone\AppCore\Validator;

use Iterator;
use Exception;

final class ValidatorRuleCollection implements Iterator
{
    /** @var ValidatorRule[] */
    private $rules = [];
    private $position = 0;

    private function __construct()
    {
    }

    public static function Factory()
    {
        return new self();
    }

    /**
     * @param ValidatorRule $rule
     * @param string        $key
     *
     * @return ValidatorRuleCollection
     * @throws Exception
     */
    public function addRule(ValidatorRule $rule, string $key): self
    {
        if (!$rule->hasRule()) {
            throw new Exception("Cannot add empty rule to collection [Rule]");
        }
        if (empty($rule->getFields())) {
            throw new Exception("Cannot add empty rule to collection [Fields]");
        }
        $this->rules[$key] = $rule;
        return $this;
    }

    /**
     * @return ValidatorRule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getRule($key): ?ValidatorRule
    {
        return $this->rules[$key] ?? null;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current(): ValidatorRule
    {
        return $this->getRules()[$this->key()];
    }

    public function key()
    {
        return array_keys($this->rules)[$this->position] ?? null;
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid(): bool
    {
        return isset($this->getRules()[$this->key()]);
    }
}