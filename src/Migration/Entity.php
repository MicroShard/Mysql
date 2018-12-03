<?php

namespace Microshard\Mysql\Migration;

use Microshard\Application\Data\AbstractEntity;

class Entity extends AbstractEntity
{
    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->getValue('id');
    }

    /**
     * @param string $value
     * @return Entity
     */
    public function setFileName(string $value): self
    {
        $this->setValue('file_name', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName(): ?string
    {
        return $this->getValue('file_name');
    }

    /**
     * @param string $value
     * @return Entity
     */
    public function setCreatedAt(string $value): self
    {
        $this->setValue('created_at', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): ?string
    {
        return $this->getValue('created_at');
    }
}