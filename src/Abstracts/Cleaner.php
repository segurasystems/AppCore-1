<?php

namespace Gone\AppCore\Abstracts;

use Gone\SDK\Common\Abstracts\AbstractModel;
use Gone\AppCore\Interfaces\CleanerInterface;

abstract class Cleaner implements CleanerInterface
{
    const CLEANER_FIELDS = "fields";
    const CLEANER_RULES = "rules";

    const RULE_TRIML = "triml";
    const RULE_TRIMR = "trimr";
    const RULE_TRIM = "trim";
    const RULE_EMPTY2NULL = "empty2null";

    public function getRules(): array
    {
        return [];
    }

    final public function clean(AbstractModel $model): void
    {
        $properties = $model->__toArray();
        $properties = $this->cleanProperties($properties);
        $model->setProperties($properties);
    }

    final private function cleanProperties(array $properties): array
    {
        $rules = $this->getRules();
        foreach ($rules as $rule) {
            $properties = $this->executeRuleOnProperties($rule, $properties);
        }
        return $properties;
    }

    final private function executeRuleOnProperties(array $rule, array $properties)
    {
        $fields = $rule[self::CLEANER_FIELDS];
        $rules = $rule[self::CLEANER_RULES];
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        if (!is_array($rules)) {
            $rules = [$rules];
        }
        foreach ($fields as $field) {
            $properties = $this->cleanField($field, $properties, $rules);
        }
        return $properties;
    }

    final private function cleanField(string $field, array $properties, array $rules)
    {
        $value = $properties[$field] ?? null;
        if ($value !== null) {
            foreach ($rules as $rule) {
                $value = $this->cleanValueWithRule($value, $rule);
            }
            $properties[$field] = $value;
        }
        return $properties;
    }

    final private function cleanValueWithRule($value, string $rule)
    {
        switch ($rule) {
            case self::RULE_TRIM:
                $value = trim($value);
                break;
            case self::RULE_TRIML:
                $value = ltrim($value);
                break;
            case self::RULE_TRIMR:
                $value = rtrim($value);
                break;
            case self::RULE_EMPTY2NULL:
                $value = empty($value) ? null : $value;
                break;
        }
        return $value;
    }

}