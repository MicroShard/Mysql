<?php

namespace Microshard\Mysql\Model;


abstract class FieldDescription
{
    const TYPE = 'undefined';

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $isRequired = true;

    /**
     * @var mixed
     */
    private $defaultValue;

    /**
     * @var bool
     */
    private $isPrimaryKey = false;

    /**
     * @var bool
     */
    private $isAutoIncrement = false;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var bool
     */
    private $readOnly = false;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param mixed|null $defaultValue
     * @return $this
     */
    public function setOptional($defaultValue = null): self
    {
        $this->isRequired = false;
        $this->defaultValue = $defaultValue;
        return $this;
    }

    /**
     * @return $this
     */
    public function setPrimaryKey(): self
    {
        $this->isPrimaryKey = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function setAutoIncrement(): self
    {
        $this->isAutoIncrement = true;
        return $this;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function validateValue(&$value): bool
    {
        if (count($this->options)) {
            return in_array($value, $this->options);
        }
        return true;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return boolean
     */
    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @return boolean
     */
    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    /**
     * @return boolean
     */
    public function isAutoIncrement(): bool
    {
        return $this->isAutoIncrement;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return bool
     */
    public function hasOptions(): bool
    {
        return count($this->options) > 0;
    }

    /**
     * @return boolean
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * @return FieldDescription
     */
    public function setReadOnly(): FieldDescription
    {
        $this->readOnly = true;
        return $this;
    }
}
