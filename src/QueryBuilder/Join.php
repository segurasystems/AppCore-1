<?php

namespace Gone\AppCore\QueryBuilder;

use JsonSerializable;

class Join implements JsonSerializable
{
    const INNER = 'INNER';
    const OUTER = 'OUTER';
    const LEFT = 'LEFT';
    const RIGHT = 'RIGHT';
    const RIGHT_OUTER = 'RIGHT OUTER';
    const LEFT_OUTER = 'LEFT OUTER';

    const JOINS
        = [
            self::INNER,
            self::OUTER,
            self::LEFT,
            self::RIGHT,
            self::RIGHT_OUTER,
            self::LEFT_OUTER,
        ];

    private $to;
    private $alias;
    private $join;
    private $type;

    public function __construct(array $join, array $to, string $type = null, $alias = null)
    {
        $this->setJoin($join[0], $join[1]);
        $this->setTo($to[0], $to[1]);
        $this->setType($type);
        $this->setAlias($alias);
    }

    public static function CreateFromArray(array $array): ?Join
    {
        return new self($array["join"], $array["to"], $array["type"] ?? null, $array["alias"] ?? null);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $array = [
            "join" => $this->getJoin(),
            "to"   => $this->getTo()
        ];
        if (!empty($this->getType())) {
            $array["type"] = $this->getType();
        }
        if (!empty($this->getAlias())) {
            $array["alias"] = $this->getAlias();
        }
        return $array;
    }

    /**
     * @return array
     */
    public function getJoin(): array
    {
        return $this->join;
    }

    /**
     * @param string $table
     * @param string $column
     *
     * @return $this
     */
    public function setJoin(string $table, string $column)
    {
        $this->join = [$table, $column];
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * @param string $table
     * @param string $column
     *
     * @return $this
     */
    public function setTo(string $table, string $column)
    {
        $this->to = [$table, $column];
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @param mixed $alias
     *
     * @return Join
     */
    public function setAlias(string $alias = null)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     *
     * @return Join
     */
    public function setType(string $type = null)
    {
        if (!in_array($type, self::JOINS) && $type !== null) {
            $type = self::INNER;
        }
        $this->type = $type;
        return $this;
    }
}