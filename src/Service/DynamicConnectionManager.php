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


        $params['dbname'] = $newDbName;
        //if($params['host'] !== '127.0.0.1'){
            $params['user'] = $newUsername;
        //}
        // senha, host e driver permanecem os mesmos
dd($params);
        $reflection = new \ReflectionClass($this->connection);
        $property = $reflection->getProperty('params');
        $property->setAccessible(true);
        $property->setValue($this->connection, $params);

        $this->connection->connect();
    }

    public function restoreOriginal(): void
    {
        $this->switchDatabase($this->originalParams['dbname'], $this->originalParams['user']);
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}