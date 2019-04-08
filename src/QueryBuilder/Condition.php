<?php

namespace Gone\AppCore\QueryBuilder;

use JsonSerializable;

class Condition implements JsonSerializable
{
    const EQUAL = '=';
    const NOT_EQUAL = '!=';
    const GREATER_THAN = '>';
    const LESS_THAN = '<';
    const GREATER_THAN_OR_EQUAL = '>=';
    const LESS_THAN_OR_EQUAL = '<=';
    const LIKE = 'LIKE';
    const NOT_LIKE = 'NOT LIKE';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';
    const BETWEEN = 'BETWEEN';
    const NOT_BETWEEN = 'NOT BETWEEN';

    const COMPARATORS
        = [
            self::EQUAL,
            self::NOT_EQUAL,
            self::GREATER_THAN,
            self::LESS_THAN,
            self::GREATER_THAN_OR_EQUAL,
            self::LESS_THAN_OR_EQUAL,
            self::LIKE,
            self::NOT_LIKE,
            self::IN,
            self::NOT_IN,
            self::BETWEEN,
            self::NOT_BETWEEN,
        ];
    const SINGULAR_COMPARATORS
        = [
            self::EQUAL,
            self::NOT_EQUAL,
            self::GREATER_THAN,
            self::LESS_THAN,
            self::GREATER_THAN_OR_EQUAL,
            self::LESS_THAN_OR_EQUAL,
            self::LIKE,
            self::NOT_LIKE,
        ];

    private $column;
    private $value;
    private $comparator;
    private $table;

    public function __construct(string $column, $value, string $comparator = self::EQUAL, string $table = null)
    {
        $this->setColumn($column);
        $this->setValue($value);
        $this->setComparator($comparator);
        $this->setTable($table);
    }

    /**
     * @param array $array
     *
     * @return Condition|null
     */
    public static function CreateFromArray(array $array): ?Condition
    {
        $split = explode(".", $array[1]);
        if (count($split) === 2) {
            $array[1] = $split[1];
            $array[3] = $split[0];
        }
        return new self($array[1], $array[2], $array[0], $array[3] ?? null);
    }

    public static function IsComparator($comparator)
    {
        if (is_string($comparator)) {
            return in_array(strtoupper($comparator), self::COMPARATORS);
        }
        return false;
    }

    public static function IsSingularComparator($comparator)
    {
        if (is_string($comparator)) {
            return in_array(strtoupper($comparator), self::SINGULAR_COMPARATORS);
        }
        return false;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $array = [$this->getComparator(), $this->getColumn(), $this->getValue()];
        if (!empty($this->getTable())) {
            $array[] = $this->getTable();
        }
        return $array;
    }

    /**
     * @return string
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * @param string $column
     *
     * @return $this
     */
    public function setColumn(string $column)
    {
        $this->column = $column;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        if (is_array($value)) {
            $query = Query::CreateFromArray($value);
            if (!$query->isEmpty()) {
                $this->value = $query;
                return $this;
            }
        }
        $this->value = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getComparator(): string
    {
        return $this->comparator;
    }

    /**
     * @param string $comparator
     *
     * @return $this
     */
    public function setComparator(string $comparator)
    {
        $comparator = strtoupper($comparator);
        if (!in_array($comparator, self::COMPARATORS)) {
            $comparator = self::EQUAL;
        }
        $this->comparator = $comparator;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function setTable(string $table = null)
    {
        $this->table = $table;
        return $this;
    }
}