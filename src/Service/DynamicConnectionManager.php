<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

class DynamicConnectionManager
{
    private Connection $connection;
    private array $originalParams;

    public function __construct(ManagerRegistry $registry)
    {
        $this->connection = $registry->getConnection();
        $this->originalParams = $this->connection->getParams();
    }

    public function switchDatabase(string $newDbName, string $newUsername): void
    {
        if ($this->connection->isConnected()) {
            $this->connection->close();
        }

        $params = $this->originalParams;
        // dd($params, $newUsername,get_current_user());

        $params['dbname'] = $newDbName ?? $_ENV['DBNAMETENANT'];//'u199209817_26';
        if(get_current_user() == 'u199209817'){
            $params['user'] = $newUsername;
        }
        //if($params['host'] !== '127.0.0.1'){
        //}
        // senha, host e driver permanecem os mesmos

        $reflection = new \ReflectionClass($this->connection);
        $property = $reflection->getProperty('params');
        $property->setAccessible(true);
        $property->setValue($this->connection, $params);

        $this->connection->connect();
    }

    public function restoreOriginal(): void
    {
        // dd($this->originalParams);
        $databaseVariables = explode('/', $_ENV['DATABASE_URL']);
        $databaseUser = explode(':',$databaseVariables[2]);
        // dd(explode('/', $_ENV['DATABASE_URL']),$databaseUser[0]);
        $this->switchDatabase(explode('?', $databaseVariables[3])[0], $databaseUser[0]);
        // dump($this->connection);
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}