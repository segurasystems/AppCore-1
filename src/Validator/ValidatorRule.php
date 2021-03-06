<?php

namespace Gone\AppCore\Validator;

use Respect\Validation\Validatable;

final class ValidatorRule
{
    protected $fields = [];
    /** @var Validatable */
    protected $rule;
    protected $scenario = AbstractValidator::SCENARIO_ALL;

    private function __construct()
    {
    }

    /**
     * @return ValidatorRule
     */
    public static function Factory()
    {
        $class = get_called_class();
        return new $class();
    }

    public function setFields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setRule(Validatable $rule)
    {
        $this->rule = $rule;
        return $this;
    }

    public function getScenario(): string
    {
        return $this->scenario;
    }

    public function setScenario(string $scenario)
    {
        $this->scenario = $scenario;
        return $this;
    }

    public function hasRule(): bool
    {
        return ($this->rule instanceof Validatable);
    }

    public function validate($value): bool
    {
        return $this->rule->check($value);
    }

}