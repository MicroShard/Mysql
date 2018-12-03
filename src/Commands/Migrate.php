<?php

namespace Microshard\Mysql\Commands;

use Microshard\Console\Command;
use Microshard\Mysql\Migration\Manager;


class Migrate extends Command
{
    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Migrate Database.';
    }

    protected function execute()
    {
        $manager = new Manager($this->getContainer());
        $that = $this;
        $manager->setLogCallback(function(string $message) use ($that) {
            $that->echoLine($message);
        });
        $manager->migrate();

        $this->echoLine('Migration complete.');
    }
}
