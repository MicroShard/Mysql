<?php

namespace Microshard\Mysql\Entity;

use Microshard\Application\Data\AbstractEntity;
use Microshard\Mysql\Model;

class Collection extends \Microshard\Application\Data\Collection
{

    /**
     * @var Model
     */
    private $model;

    /**
     * @var bool
     */
    private $isLoaded = false;

    /**
     * @var array
     */
    private $query = [];

    /**
     * @var \Closure
     */
    private $binding;

    /**
     * @param Model $model
     * @param \Closure|null $binding
     */
    public function __construct(Model $model, \Closure $binding = null)
    {
        $this->model = $model;
        $this->binding = $binding;
    }

    /**
     * @return Collection
     */
    public function load(): self
    {
        if (!$this->isLoaded){
            if ($this->binding) {
                ($this->binding)($this);
            }

            $items = $this->model->list([
                'query' => $this->query
            ]);
            $this->setItems($items);
            $this->isLoaded = true;
        }
        return $this;
    }

    /**
     * @return $this
     * @throws \Microshard\Application\Exception\SystemException
     */
    public function save()
    {
        if ($this->isLoaded()) {
            foreach ($this->getItems() as $item) {
                $this->model->save($item);
            }
        }
        return $this;
    }

    /**
     * @return Collection
     */
    public function setIsLoaded(): self
    {
        $this->isLoaded = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->isLoaded;
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param null|string $operator
     * @return $this
     */
    public function addFilter(string $field, $value, ?string $operator = '='): self
    {
        $this->query[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator
        ];
        return $this;
    }

    /**
     * @return AbstractEntity[]
     */
    public function getItems(): array
    {
        $this->load();
        return parent::getItems();
    }

    /**
     * @param AbstractEntity $entity
     * @return Collection
     */
    public function addItem(AbstractEntity $entity): \Microshard\Application\Data\Collection
    {
        $this->load();
        return parent::addItem($entity);
    }

    /**
     * @param AbstractEntity $item
     * @return Collection
     * @throws \Microshard\Application\Exception\SystemException
     */
    public function deleteItem(AbstractEntity $item): self
    {
        $index = array_search($item, $this->getItems());
        if ($index) {
            parent::removeItem($item);
            $this->model->delete($item);
        }
        return $this;
    }

    /**
     * @param int $index
     * @return AbstractEntity|null
     */
    public function getItemByIndex(int $index): ?AbstractEntity
    {
        $this->load();
        return parent::getItemByIndex($index);
    }

    /**
     * @return AbstractEntity|bool
     */
    public function current()
    {
        $this->load();
        return parent::current();
    }

    /**
     * @return AbstractEntity|bool
     */
    public function next()
    {
        $this->load();
        return parent::next();
    }

    /**
     * @return int|null|string
     */
    public function key()
    {
        $this->load();
        return parent::key();
    }

    public function rewind()
    {
        $this->load();
        parent::rewind();
    }
}