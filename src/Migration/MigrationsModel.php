<?php

namespace Microshard\Mysql\Migration;


use Microshard\Mysql\Model;
use Microshard\Mysql\Model\DateTimeDescription;
use Microshard\Mysql\Model\IntDescription;
use Microshard\Mysql\Model\StringDescription;
use Microshard\Application\Data\AbstractEntity;

class MigrationsModel extends Model
{
    public function init(): Model
    {
        $this->setTable('migrations');
        $this->addDescription((new IntDescription('id'))->setUnsigned()->setPrimaryKey()->setAutoIncrement());
        $this->addDescription((new StringDescription('file_name'))->setMaxLength(255));
        $this->addDescription(new DateTimeDescription('executed_at'));
        return $this;
    }

    /**
     * @return Entity
     */
    public function getNewEntity(): AbstractEntity
    {
        return new Entity();
    }
}
