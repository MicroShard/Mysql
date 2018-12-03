<?php

namespace MicroShard\Mysql\Adapter;

use Microshard\Mysql\Model\FieldDescription;

class QueryValidator
{
    const LIST_AND = 'and';
    const LIST_OR = 'or';

    const ELEMENT_FIELD = 'field';
    const ELEMENT_OPERATOR = 'operator';
    const ELEMENT_VALUE = 'value';
    const ELEMENT_REFERENCE = 'reference';

    const OPERATOR_EQUALS = 'eq';
    const OPERATOR_GREATER = 'gt';
    const OPERATOR_GREATER_EQUALS = 'gte';
    const OPERATOR_LESSER = 'lt';
    const OPERATOR_LESSER_EQUALS = 'lte';
    const OPERATOR_NOT_EQUAL = 'neq';
    const OPERATOR_CONTAINS = 'cv';
    const OPERATOR_NOT_CONTAINS = 'ncv';
    const OPERATOR_EMPTY = 'ey';
    const OPERATOR_NOT_EMPTY = 'ney';
    const OPERATOR_IN = 'in';
    const OPERATOR_NOT_IN = 'nin';

    /**
     * @var array
     */
    protected $operators = [
        self::OPERATOR_CONTAINS,
        self::OPERATOR_EMPTY,
        self::OPERATOR_EQUALS,
        self::OPERATOR_GREATER,
        self::OPERATOR_GREATER_EQUALS,
        self::OPERATOR_LESSER,
        self::OPERATOR_LESSER_EQUALS,
        self::OPERATOR_NOT_CONTAINS,
        self::OPERATOR_NOT_EMPTY,
        self::OPERATOR_NOT_EQUAL,
        self::OPERATOR_IN,
        self::OPERATOR_NOT_IN
    ];

    /**
     * @var array
     */
    protected $unaryOperators = [
        self::OPERATOR_EMPTY,
        self::OPERATOR_NOT_EMPTY
    ];

    /**
     * @var FieldDescription[]
     */
    protected $description;

    /**
     * QueryResolver constructor.
     * @param FieldDescription[] $description
     */
    public function __construct(array $description)
    {
        $this->description = $description;
    }

    /**
     * @param array $query
     * @return $this
     * @throws QueryValidatorException
     */
    public function validate(array $query)
    {
        if (!$this->isList($query)) {
            throw new QueryValidatorException('invalid query');
        }
        $this->validateList($query);
        return $this;
    }

    /**
     * @param array $list
     * @return $this
     * @throws QueryValidatorException
     */
    protected function validateList(array $list)
    {
        $type = (isset($list[self::LIST_AND])) ? self::LIST_AND : self::LIST_OR;
        if (!is_array($list[$type])) {
            throw new QueryValidatorException('invalid query');
        }

        foreach ($list[$type] as $item) {
            if (!is_array($item)) {
                throw new QueryValidatorException('invalid query');
            }

            if ($this->isList($item)) {
                $this->validateList($item);
            } else if($this->isElement($item)) {
                $this->validateElement($item);
            } else {
                throw new QueryValidatorException('invalid query');
            }
        }
        return $this;
    }

    /**
     * @param array $data
     * @return $this
     * @throws QueryValidatorException
     */
    protected function validateElement(array $data)
    {
        if (!isset($data[self::ELEMENT_FIELD]) || !isset($data[self::ELEMENT_OPERATOR])){
            throw new QueryValidatorException('invalid query - missing field or operator');
        }
        $field = $data[self::ELEMENT_FIELD];
        $operator = $data[self::ELEMENT_OPERATOR];

        if (!isset($this->description[$field])) {
            throw new QueryValidatorException('invalid query - field $field does not exists');
        }

        if (!in_array($operator, $this->operators)) {
            throw new QueryValidatorException("invalid query - unknown operator $operator");
        }

        if (!in_array($operator, $this->unaryOperators)) {

            if (!isset($data[self::ELEMENT_VALUE]) && !isset($data[self::ELEMENT_REFERENCE])){
                throw new QueryValidatorException("invalid query - missing value or reference");
            }
            if (isset($data[self::ELEMENT_VALUE])) {
                $value = $data[self::ELEMENT_VALUE];
                $description = $this->description[$field];
                if (in_array($operator,[self::OPERATOR_IN, self::OPERATOR_NOT_IN]) && is_array($value)) {
                    foreach ($value as $val) {
                        if (!$description->validateValue($val)){
                            throw new QueryValidatorException("invalid query - list contains invalid value $val");
                        }
                    }
                } else {
                    if (!$description->validateValue($value)){
                        throw new QueryValidatorException("invalid query - invalid value $value");
                    }
                }
            } else {
                $reference = $data[self::ELEMENT_REFERENCE];
                if (!$this->description[$reference]) {
                    throw new QueryValidatorException("invalid query - reference $reference does not exists");
                }
            }
        }

        return $this;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function isList(array $data)
    {
        return isset($data[self::LIST_AND]) || isset($data[self::LIST_OR]);
    }

    /**
     * @param array $data
     * @return bool
     */
    public function isElement(array $data)
    {
        return isset($data[self::ELEMENT_FIELD]) && isset($data[self::ELEMENT_OPERATOR]);
    }
}
