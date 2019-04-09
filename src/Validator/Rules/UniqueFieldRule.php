<?php


namespace Gone\AppCore\Validator\Rules;


use Gone\AppCore\Abstracts\TableAccessLayer;
use Gone\AppCore\QueryBuilder\Condition;
use Gone\AppCore\QueryBuilder\Query;

class UniqueFieldRule extends BaseRule
{
    /** @var string */
    private $field;
    /** @var array */
    private $pks = [];
    /** @var TableAccessLayer */
    private $accessLayer;

    private $data;

    public function __construct(TableAccessLayer $accessLayer, $pks = null)
    {
        $this->accessLayer = $accessLayer;
        if (empty($pks)) {
            $pks = [];
        }
        if (!is_array($pks)) {
            $pks = [];
        }
        $this->pks = $pks;
    }

    public function beforeCheck(string $field, array $data)
    {
        $this->field = $field;
        $this->data = $data;
    }

    public function validate($input)
    {
        $where = [
            "AND",
            [Condition::EQUAL, $this->field, $input],
        ];

        if (!empty($this->pks)) {
            foreach ($this->pks as $pk) {
                if (!empty($this->data[$pk])) {
                    $where[] = [
                        Condition::NOT_EQUAL,
                        $pk,
                        $this->data[$pk],
                    ];
                }
            }
        }

        $count = $this->accessLayer->count(
            Query::Factory()
                ->where($where)
        );
        return $count === 0;
    }

}