<?php

namespace Gone\AppCore\Validator;

use Respect\Validation\Validatable;

final class ValidatorRule
{
    protected $fields = [];
    /** @var Validatable */
    protected $rule;
    protected $scenarios = [AbstractValidator::SCENARIO_DEFAULT];

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

    public function hasScenario($scenario): bool
    {
        return in_array($scenario,$this->getScenarios());
    }

    public function getScenarios(): array
    {
        return $this->scenarios;
    }

    public function setScenario($scenario)
    {
        $this->setScenarios($scenario);
        return $this;
    }

    public function setScenarios($scenarios)
    {
        if (empty($scenarios)) {
            $scenarios = [AbstractValidator::SCENARIO_DEFAULT];
        }
        if (!is_array($scenarios)) {
            $scenarios = [$scenarios];
        }
        $this->scenarios = $scenarios;
        return $this;
    }

    public function hasRule(): bool
    {
        return ($this->rule instanceof Validatable);
    }

    public function validate($value, string $field, array $data = []): bool
    {
        if (method_exists($this->rule, "beforeCheck")) {
            $this->rule->beforeCheck($field, $data);
        }
        return $this->rule->check($value);
    }

}