<?php

namespace Microshard\Mysql\Commands;

use Microshard\Console\Command;
use Microshard\Mysql\Adapter;
use Microshard\Mysql\Migration\Manager;

class Setup extends Command
{
    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Setup Database.';
    }

    protected function execute()
    {
        $dbUser = $this->getContainer()->getConfiguration()->get('db_user');
        $dbHost = $this->getContainer()->getConfiguration()->get('db_host');
        $dbName = $this->getContainer()->getConfiguration()->get('db_name');

        //create temporary adapter without defined database
        $adapter = new Adapter(
            $dbHost,
            $dbUser,
            $this->getContainer()->getConfiguration()->get('db_pass')
        );

        $exists = $adapter->exec("SHOW DATABASES LIKE '$dbName'")->count();
        if ($exists == 0) {
            $adapter->exec("
                CREATE DATABASE $dbName CHARACTER SET utf8 COLLATE utf8_general_ci;
                GRANT ALL ON $dbName.* TO '$dbUser'@'%';
                FLUSH PRIVILEGES;    
            ");
            $this->echoLine("Database $dbName created.");
        } else {
            $this->echoLine("Database $dbName already exists.");
        }

        // switch to default adapter after Database exists
        $manager = new Manager($this->getContainer());
        if ($manager->tableExists() == 0) {
            $manager->createTable();
            $this->echoLine("Migrations Table created.");
        } else {
            $this->echoLine("Migrations Table already exists.");
        }
    }
}