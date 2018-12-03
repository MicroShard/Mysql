<?php

namespace Microshard\Mysql;

use Microshard\Mysql\Adapter\ListBuilder;
use Microshard\Mysql\Model\FieldDescription;
use PDO;
use Zend\Db\Adapter\Driver\Pdo\Pdo AS ZendPDO;
use Zend\Db\Adapter\Platform\Mysql;

class Adapter
{
    /**
     * @var ZendPDO
     */
    protected $pdo;

    /**
     * @var Mysql
     */
    protected $platform;

    /**
     * @var \Closure
     */
    protected $lazyConstructor;

    /**
     * DatabaseAdapter constructor.
     * @param string $dbHost
     * @param string $user
     * @param string $password
     * @param string|null $dbName
     */
    public function __construct(string $dbHost, string $user, string $password, string $dbName = null)
    {
        $this->lazyConstructor = function() use ($dbHost, $user, $password, $dbName) {
            $dsn = "mysql:dbname={$dbName}";
            if ($dbHost) {
                $dsn .= ";host={$dbHost}";
            }

            $settings = array(
                'driver' => 'Pdo',
                'dsn' => $dsn,
                'username' => $user,
                'password' => $password,
                'driver_options' => array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
                )
            );

            return new ZendPDO($settings);
        };
    }

    /**
     * @return ZendPDO
     */
    public function getPdo(): ZendPDO
    {
        if (!$this->pdo) {
            $this->pdo = ($this->lazyConstructor)();
        }
        return $this->pdo;
    }

    /**
     * @return Mysql
     */
    public function getPlatform(): Mysql
    {
        if (!$this->platform) {
            $this->platform = new Mysql($this->getPdo());
        }
        return $this->platform;
    }

    /**
     * @param string $table
     * @param array $description
     * @param string $primaryKey
     * @return ListBuilder
     */
    public function newListBuilder(string $table, array $description, string $primaryKey)
    {
        return new ListBuilder($this, $table, $description, $primaryKey);
    }

    /**
     * @param string $table
     * @param string $autoIncrementField
     * @param FieldDescription[] $description
     * @param array $data
     * @return array
     */
    public function insert(string $table, $autoIncrementField, array $description , array $data)
    {
        $columns = [];
        $values = [];

        foreach ($data as $field => $value) {
            $columns[] = $this->getPlatform()->quoteIdentifier($field);
            $values[] = $this->getPlatform()->quoteValue($value);
        }

        $columns = "(" . implode(",", $columns) . ")";
        $values = "(" . implode(",", $values) . ")";

        $sql = "INSERT INTO $table $columns VALUES $values";
        $result = $this->getPdo()->getConnection()->execute($sql);
        if ($autoIncrementField) {
            $data[$autoIncrementField] = $result->getGeneratedValue();
        }

        return $data;
    }

    /**
     * @param string $table
     * @param string $primaryKey
     * @param FieldDescription[] $description
     * @param array $data
     * @return array
     */
    public function update(string $table, string $primaryKey, array $description, array $data)
    {
        $set = [];
        $primaryKeyValue = null;
        $primaryKeyField = null;
        foreach ($data as $field => $value) {
            $quoteField = $this->getPlatform()->quoteIdentifier($field);
            $quoteValue = $this->getPlatform()->quoteValue($value);
            if ($field == $primaryKey) {
                $primaryKeyValue = $quoteValue;
                $primaryKeyField = $quoteField;
            } else {
                $set[] = "$quoteField=$quoteValue";
            }
        }
        $set = implode(',', $set);

        $sql = "UPDATE $table SET $set WHERE $primaryKeyField=$primaryKeyValue";
        $result = $this->getPdo()->getConnection()->execute($sql);
        return $data;
    }

    /**
     * @param string $table
     * @param string $field
     * @param string $value
     * @param FieldDescription $description
     * @return array|bool
     */
    public function read(string $table, string $field, string $value, FieldDescription $description)
    {
        $field = $this->getPlatform()->quoteIdentifier($field);
        $value = $this->getPlatform()->quoteValue($value);

        $sql = "SELECT * FROM $table WHERE $field=$value LIMIT 1";
        $result = $this->getPdo()
            ->getConnection()
            ->execute($sql);

        return $result->current();
    }

    /**
     * @param string $table
     * @param string $field
     * @param string $value
     * @param FieldDescription[] $description
     * @return $this
     */
    public function delete(string $table, string $field, string $value, array $description)
    {
        $field = $this->getPlatform()->quoteIdentifier($field);
        $value = $this->getPlatform()->quoteValue($value);

        $sql = "DELETE FROM $table WHERE $field=$value";
        $this->getPdo()
            ->getConnection()
            ->execute($sql);
        return $this;
    }

    /**
     * @param string $sql
     * @return \Zend\Db\Adapter\Driver\Pdo\Result|\Zend\Db\Adapter\Driver\ResultInterface
     */
    public function exec(string $sql)
    {
        return $this->getPdo()->getConnection()->execute($sql);
    }

    /**
     * @return $this
     */
    public function beginTransaction()
    {
        $this->getPdo()->getConnection()->beginTransaction();
        return $this;
    }

    /**
     * @return $this
     */
    public function rollback()
    {
        $this->getPdo()->getConnection()->rollback();
        return $this;
    }

    /**
     * @return $this
     */
    public function commit()
    {
        $this->getPdo()->getConnection()->commit();
        return $this;
    }
}