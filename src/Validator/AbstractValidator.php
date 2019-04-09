<?php

namespace Gone\AppCore\Validator;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\AppCore\Interfaces\ValidatorInterface;
use Respect\Validation\Exceptions\ValidationException;

abstract class AbstractValidator implements ValidatorInterface
{
    const SCENARIO_ALL = "all";
    const SCENARIO_DEFAULT = "default";

    protected $errors = [];
    private $stopOnFirstError = false;

    abstract public function getRules(): ValidatorRuleCollection;

    final public function validate(AbstractModel $model, string $scenario = self::SCENARIO_DEFAULT): bool
    {
        $data = array_change_key_case($model->__toArray(), CASE_LOWER);

        $rules = $this->getScenarioRules($scenario);

        $this->clearErrors();

        foreach ($rules as $rule) {
            if (!$this->validateRule($rule, $data) && $this->stopOnFirstError) {
                break;
            }
        }

        $this->stopOnFirstError = false;

        return empty($this->getErrors());

    }

    private function validateRule(ValidatorRule $rule, array $data)
    {
        $keys = $rule->getFields();
        $pass = true;
        foreach ($keys as $key) {
            $lckey = strtolower($key);
            $value = $data[$lckey] ?? null;
            try {
                $rule->validate($value,$key,$data);
            } catch (ValidationException $e) {
                $this->errors[$key][] = $e->getMainMessage();
                $pass = false;
            }
        }
        return $pass;
    }

    /**
     * @param string|null $scenario
     *
     * @return ValidatorRule[]
     */
    private function getScenarioRules(string $scenario): array
    {
        $rules = [];
        foreach ($this->getRules() as $rule) {
            if ($rule->hasScenario($scenario) || $scenario === self::SCENARIO_ALL) {
                $rules[] = $rule;
            }
        }
        return $rules;
    }

    private function clearErrors()
    {
        $this->errors = [];
    }

    final public function getErrors(): array
    {
        return $this->errors;
    }

    final public function stopOnFirstError()
    {
        $this->stopOnFirstError = true;
        return $this;
    }
}