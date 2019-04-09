<?php


namespace Gone\AppCore\Validator\Rules;


use Gone\AppCore\Abstracts\TableAccessLayer;
use Gone\AppCore\QueryBuilder\Condition;
use Gone\AppCore\QueryBuilder\Query;

class ForeignKeyRule extends BaseRule
{
    /** @var string */
    private $field;
    /** @var TableAccessLayer */
    private $accessLayer;

    public function __construct(TableAccessLayer $accessLayer,string $foreignField)
    {
        $this->accessLayer = $accessLayer;
        $this->field = $foreignField;
    }

    public function validate($input)
    {
        $where = [
            "AND",
            [Condition::EQUAL, $this->field, $input],
        ];

        $count = $this->accessLayer->count(
            Query::Factory()
                ->where($where)
        );
        return $count === 1;
    }

}