<?php

namespace Microshard\Mysql\Adapter;

use Microshard\Mysql\Adapter;
use Microshard\Mysql\Model\FieldDescription;
use Zend\Db\Sql\Select;

class ListBuilder
{
    const DEFAULT_PAGE_SIZE = 100;
    const MAX_PAGE_SIZE = 10000;

    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var FieldDescription[]
     */
    protected $description = [];

    /**
     * @var string
     */
    protected $primaryKey;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $query;

    /**
     * @var int
     */
    protected $pageSize = self::DEFAULT_PAGE_SIZE;

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var array
     */
    protected $sortFields = [];

    /**
     * @var array
     */
    protected $resultFields = [];

    /**
     * @var bool
     */
    protected $lockForUpdate = false;

    /**
     * @param Adapter $adapter
     * @param string $table
     * @param FieldDescription[] $description
     * @param string $primaryKey
     */
    public function __construct(Adapter $adapter, string $table, array $description, string $primaryKey)
    {
        $this->adapter = $adapter;
        $this->description = $description;
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    /**
     * @return null|FieldDescription
     */
    protected function getPrimaryKey()
    {
        if ($this->primaryKey) {
            return $this->description[$this->primaryKey];
        }
        return null;
    }

    /**
     * @param array $list
     * @return array
     */
    protected function filterExistingFields(array $list)
    {
        $clean = [];
        foreach ($list as $field){
            if (isset($this->description[$field])){
                $clean[] = $field;
            }
        }
        return $clean;
    }

    /**
     * @param int $size
     * @return $this
     */
    public function setPageSize(int $size)
    {
        if ($size < 1) {
            $size = self::DEFAULT_PAGE_SIZE;
        }
        if ($size > self::MAX_PAGE_SIZE) {
            $size = self::MAX_PAGE_SIZE;
        }
        $this->pageSize = $size;
        return $this;
    }

    /**
     * @param int $page
     * @return $this
     */
    public function setPage(int $page)
    {
        if ($page < 1) {
            $page = 1;
        }
        $this->page = $page;
        return $this;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function setSortFields(array $fields)
    {
        $cleanFields = array_keys($fields);
        $cleanFields = $this->filterExistingFields($cleanFields);
        foreach ($cleanFields as $field){
            if (in_array(strtoupper($fields[$field]), [Select::ORDER_ASCENDING, Select::ORDER_DESCENDING])){
                $this->sortFields[$field] = strtoupper($fields[$field]);
            }
        }

        return $this;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function setResultFields(array $fields)
    {
        $this->resultFields = $this->filterExistingFields($fields);
        return $this;
    }

    /**
     * @param array $query
     * @return ListBuilder
     */
    public function setQuery(array $query): self
    {
        $this->getQueryValidator()->validate($query);
        $this->query = $query;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} ";
        if ($this->query) {
            if ($where = $this->getSelectWhere()) {
                $sql .= "WHERE $where ";
            }
        }

        $count = 0;
        $result = $this->adapter->exec($sql);
        if ($result) {
            $count = $result->current();
            if (is_array($count)){
                $count = array_values($count)[0];
            }
        }
        return $count;
    }

    /**
     * @param string $assocField
     * @return array
     */
    public function loadData(string $assocField = null)
    {
        $fields = $this->getSelectFields();
        $sql = "SELECT $fields FROM {$this->table} ";
        if ($this->query) {
            if ($where = $this->getSelectWhere()) {
                $sql .= "WHERE $where ";
            }
        }

        $sql .= $this->getSelectOrder();
        $sql .= $this->getSelectLimit();

        if ($this->lockForUpdate) {
            $sql .= " FOR UPDATE";
        }

        $data = [];
        $result = $this->adapter->exec($sql);
        if ($result) {
            foreach ($result as $row) {
                if ($assocField && isset($row[$assocField])) {
                    $data[$row[$assocField]] = $row;
                } else {
                    $data[] = $row;
                }
            }
        }

        return $data;
    }

    /**
     * @return string
     */
    protected function getSelectFields()
    {
        $fields = '*';
        if (count($this->resultFields) > 0) {
            $quoted = [];
            foreach ($this->resultFields as $field){
                $quoted[] = $this->adapter->getPlatform()->quoteIdentifier($field);
            }
            $fields = implode(',', $quoted);
        }
        return $fields;
    }

    /**
     * @return string
     */
    protected function getSelectWhere()
    {
        return $this->renderList($this->query);
    }

    /**
     * @param array $list
     * @return string
     */
    protected function renderList(array $list)
    {
        $op = array_keys($list)[0];
        $parts = [];
        foreach ($list[$op] as $part) {
            if ($this->getQueryValidator()->isList($part)){
                if ($list = $this->renderList($part)) {
                    $parts[] = $list;
                }
            } else if ($this->getQueryValidator()->isElement($part)) {
                $parts[] = $this->renderElement($part);
            }
        }
        return (count($parts) > 0)
            ? "(" . implode(" $op ", $parts) . ")"
            : '';
    }

    /**
     * @param array $element
     * @return string
     */
    protected function renderElement(array $element)
    {
        $sql = "";
        $platform = $this->adapter->getPlatform();
        $field = $platform->quoteIdentifier($element[QueryValidator::ELEMENT_FIELD]);
        $operator = $element[QueryValidator::ELEMENT_OPERATOR];
        $value = isset($element[QueryValidator::ELEMENT_VALUE]) ? $element[QueryValidator::ELEMENT_VALUE] : null;
        $reference = isset($element[QueryValidator::ELEMENT_REFERENCE]) ? $element[QueryValidator::ELEMENT_REFERENCE] : null;

        switch ($operator) {
            case QueryValidator::OPERATOR_NOT_CONTAINS:
            case QueryValidator::OPERATOR_CONTAINS:
                $like = ($operator == QueryValidator::OPERATOR_CONTAINS) ? 'LIKE' : 'NOT LIKE';
                if ($value) {
                    $value = "%$value%";
                    $sql = $field . " $like " . $platform->quoteValue($value);
                } else {
                    $reference = "CONCAT("
                        . $platform->quoteValue('%') . ','
                        . $platform->quoteIdentifier($reference) . ','
                        . $platform->quoteValue('%') . ')';
                    $sql = $field . " $like " . $reference;
                }
                break;
            case QueryValidator::OPERATOR_EMPTY:
                $sql = $field . " IS NULL OR " . $field . "=" . $platform->quoteValue('');
                break;
            case QueryValidator::OPERATOR_NOT_EMPTY:
                $sql = $field . " IS NOT NULL AND " . $field . "!=" . $platform->quoteValue('');
                break;
            case QueryValidator::OPERATOR_IN:
            case QueryValidator::OPERATOR_NOT_IN:
                if (is_array($value)) {
                    $quoted = [];
                    foreach ($value as $val) {
                        $quoted[] = $platform->quoteValue($val);
                    }
                    $value = implode(',', $quoted);
                }
                $op = ($operator === QueryValidator::OPERATOR_IN) ? 'IN' : 'NOT IN';
                $sql = $field . " $op (" . $value . ")";
                break;
            default:
                if (!is_null($value)) {
                    $sql = $field . $this->operators[$operator] . $platform->quoteValue($value);
                } else {
                    $sql = $field . $this->operators[$operator] . $platform->quoteIdentifier($reference);
                }
        }

        return "(" . $sql . ")";
    }

    /**
     * @return string
     */
    protected function getSelectOrder()
    {
        $list = [];

        if (count($this->sortFields) == 0 && ($primaryKey = $this->getPrimaryKey())) {
            $this->sortFields[$primaryKey->getName()] = Select::ORDER_ASCENDING;
        }

        foreach ($this->sortFields as $field => $direction) {
            $list[] = $this->adapter->getPlatform()->quoteIdentifier($field) . ' ' . $direction;
        }
        return count($list) > 0
            ? "ORDER BY " . implode(',', $list) . ' '
            : '';
    }

    /**
     * @return string
     */
    protected function getSelectLimit()
    {
        $sql = "LIMIT " . $this->pageSize;
        if ($this->page > 1) {
            $offset = $this->pageSize * ($this->page - 1);
            $sql .= ' OFFSET ' . $offset;
        }
        return $sql;
    }

    /**
     * @return $this
     */
    public function lockForUpdate()
    {
        $this->lockForUpdate = true;
        return $this;
    }
}
