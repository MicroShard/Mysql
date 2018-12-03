<?php

namespace Microshard\Mysql;


use Microshard\Application\Data\AbstractEntity;
use Microshard\Application\Exception\SystemException;
use Microshard\Mysql\Adapter\ListBuilder;
use Microshard\Mysql\Entity\Collection;
use Microshard\Mysql\Model\FieldDescription;
use Microshard\Mysql\Model\FloatDescription;
use Microshard\Mysql\Model\IntDescription;

abstract class Model
{
    /**
     * @var FieldDescription[]
     */
    protected $description = [];

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $autoIncrementField;

    /**
     * @var string
     */
    protected $primaryKey;

    /**
     * @var Adapter
     */
    protected $databaseAdapter;

    /**
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->databaseAdapter = $adapter;
        $this->init();
    }

    /**
     * @return FieldDescription[]
     */
    public function getDescriptions(): array
    {
        return $this->description;
    }

    /**
     * @param FieldDescription $description
     * @return $this
     */
    protected function addDescription(FieldDescription $description)
    {
        $this->description[$description->getName()] = $description;
        if ($description->isPrimaryKey()) {
            $this->primaryKey = $description->getName();
        }
        if ($description->isAutoIncrement()) {
            $this->autoIncrementField = $description->getName();
        }

        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasField(string $name)
    {
        return isset($this->description[$name]);
    }

    /**
     * @param string $name
     * @return FieldDescription
     */
    public function getFieldDescription(string $name): FieldDescription
    {
        return $this->description[$name];
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $table
     * @return Model
     */
    protected function setTable(string $table): Model
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return string
     */
    protected function getAutoIncrementField()
    {
        return $this->autoIncrementField;
    }

    /**
     * @return string
     */
    protected function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @return Adapter
     */
    protected function getDatabaseAdapter(): Adapter
    {
        return $this->databaseAdapter;
    }

    public abstract function init(): Model;

    public abstract function getNewEntity(): AbstractEntity;

    /**
     * @param \Closure|null $binding
     * @return Collection
     */
    public function getNewCollection(\Closure $binding = null)
    {
        return new Collection($this, $binding);
    }

    /**
     * @param AbstractEntity $entity
     * @return Model
     * @throws SystemException
     */
    public function save(AbstractEntity $entity): self
    {
        if ($field = $this->getPrimaryKey()) {
            $data = $entity->getData();
            if (isset($data[$field]) && !empty($data[$field])){
                $this->update($entity);
            } else {
                $this->create($entity);
            }
        } else {
            $this->create($entity);
        }
        return $this;
    }

    /**
     * @param AbstractEntity $entity
     * @return AbstractEntity
     * @throws SystemException
     */
    public function create(AbstractEntity $entity)
    {
        $this->beforeCreate($entity);

        $data = $entity->getData();
        $filtered = [];
        foreach ($this->getDescriptions() as $field => $description){
            if ($description->isReadOnly()) {
                continue;
            }
            $value = (isset($data[$field])) ? $data[$field] : null;
            if (is_null($value) && $this->autoIncrementField == $field) {
                continue;
            }

            if (is_null($value) && !$description->isRequired()) {
                $defaultValue = $description->getDefaultValue();
                if ($defaultValue !== null) {
                    $value = $defaultValue;
                } else {
                    //skip optional fields with no value
                    continue;
                }
            }

            if (!$description->validateValue($value)) {
                throw new SystemException("invalid value for $field");
            }
            $filtered[$field] = $value;
        }

        $filtered = $this->getDatabaseAdapter()->insert(
            $this->getTable(),
            $this->getAutoIncrementField(),
            $this->getDescriptions(),
            $filtered
        );
        $entity->addData($filtered);

        $this->afterCreate($entity);
        return $entity;
    }

    /**
     * @param AbstractEntity $entity
     * @return $this
     */
    protected function beforeCreate(AbstractEntity $entity)
    {
        return $this;
    }

    /**
     * @param AbstractEntity $entity
     * @return $this
     */
    protected function afterCreate(AbstractEntity $entity)
    {
        return $this;
    }

    /**
     * @param $value
     * @param null|string $field
     * @return AbstractEntity
     */
    public function load($value, ?string $field = null): AbstractEntity
    {
        if (!$field) {
            $field = $this->getPrimaryKey();
        }

        $result = $this->loadInternal($field, $value);

        if (!$result){
            $result = [];
        } else {
            foreach ($result as $field => $value) {
                $description = $this->getFieldDescription($field);
                if ($description::TYPE == IntDescription::TYPE) {
                    $result[$field] = intval($value);
                } else if ($description::TYPE == FloatDescription::TYPE) {
                    $result[$field] = floatval($value);
                }
            }
        }
        $entity = $this->getNewEntity();
        $entity->setData($result);
        return $entity;
    }

    /**
     * @param string $field
     * @param $value
     * @return array|bool
     */
    protected function loadInternal(string $field, $value)
    {
        return $this->getDatabaseAdapter()
            ->read(
                $this->getTable(),
                $field,
                $value,
                $this->getFieldDescription($field)
            );
    }

    /**
     * @param AbstractEntity $entity
     * @return AbstractEntity
     * @throws SystemException
     */
    public function update(AbstractEntity $entity)
    {
        $this->beforeUpdate($entity);

        $data = $entity->getData();
        $new = [];
        foreach ($this->getDescriptions() as $field => $description) {
            if ($description->isReadOnly()) {
                continue;
            }
            if (isset($data[$field])) {
                $value = $data[$field];
                if (!$description->validateValue($value)) {
                    throw new SystemException("invalid value for $field");
                }
                $new[$field] = $value;
            }
        }

        if (count($new)) {
            $new = $this->getDatabaseAdapter()->update(
                $this->getTable(),
                $this->getPrimaryKey(),
                $this->getDescriptions(),
                $new
            );
            $entity->addData($new);
        }

        $this->afterUpdate($entity);
        return $entity;
    }

    /**
     * @param AbstractEntity $entity
     * @return $this
     */
    protected function beforeUpdate(AbstractEntity $entity)
    {
        return $this;
    }

    /**
     * @param AbstractEntity $entity
     * @return $this
     */
    protected function afterUpdate(AbstractEntity $entity)
    {
        return $this;
    }

    /**
     * @param AbstractEntity $entity
     * @return AbstractEntity
     * @throws SystemException
     */
    public function delete(AbstractEntity $entity): AbstractEntity
    {
        $value = null;
        if (!$this->getPrimaryKey()) {
            throw new SystemException('unable to delete entry without primary key');
        } else {
            $data = $entity->getData();
            $value = (isset($data[$this->getPrimaryKey()])) ? $data[$this->getPrimaryKey()] : null;
        }


        if (!is_null($value)){
            $this->beforeDelete($entity);
            $this->getDatabaseAdapter()->delete(
                $this->getTable(),
                $this->getPrimaryKey(),
                $value,
                $this->getDescriptions()
            );

            $this->afterDelete($entity);
        }

        return $entity;
    }

    /**
     * @param AbstractEntity $entity
     * @return $this
     */
    protected function beforeDelete(AbstractEntity $entity)
    {
        return $this;
    }

    /**
     * @param AbstractEntity $entity
     * @return $this
     */
    protected function afterDelete(AbstractEntity $entity)
    {
        return $this;
    }

    /**
     * @param array $data
     * @param string $assocField
     * @return AbstractEntity[]
     */
    public function list(array $data, string $assocField = null): array
    {
        $builder = $this->getListBuilder();
        if (isset($data['query'])) {
            $builder->setQuery($data['query']);
        }
        if (isset($data['pageSize'])) {
            $builder->setPageSize($data['pageSize']);
        }
        if (isset($data['page'])) {
            $builder->setPage($data['page']);
        }
        if (isset($data['sortFields'])) {
            $builder->setSortFields($data['sortFields']);
        }
        if (isset($data['fields'])) {
            $builder->setResultFields($data['fields']);
        }

        $assocField = (is_null($assocField)) ? $this->getPrimaryKey() : $assocField;
        $items = $builder->loadData($assocField);
        $result = [];
        foreach ($items as $idx => $item) {
            $result[$idx] = $this->getNewEntity()->setData($item);
        }
        return $result;
    }

    /**
     * @return ListBuilder
     */
    public function getListBuilder()
    {
        return $this->getDatabaseAdapter()->newListBuilder($this->getTable(), $this->getDescriptions(), $this->primaryKey);
    }

    /**
     * @return string
     */
    public function getCurrentDateTime()
    {
        $date = new \DateTime('now', new \DateTimeZone('Europe/Berlin'));
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @return array
     */
    public function describe()
    {
        $result = [];
        foreach ($this->description as $description) {
            $desc = [
                'primary' => $description->isPrimaryKey(),
                'readonly' => $description->isReadOnly(),
                'type' => $description::TYPE,
                'options' => $description->getOptions(),
                'required' => $description->isRequired(),
                'default' => $description->getDefaultValue()
            ];
            $result[$description->getName()] = $desc;
        }
        return $result;
    }
}
