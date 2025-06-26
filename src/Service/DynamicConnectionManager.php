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


        $params['dbname'] = 'u199209817_26';
        //if($params['host'] !== '127.0.0.1'){
            $params['user'] = 'u199209817_26';
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
        $this->switchDatabase('u199209817_login', 'u199209817_systemhomepet');
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}