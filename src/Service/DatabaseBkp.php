<?php

namespace App\Service;

use Doctrine\DBAL\DriverManager;

class DatabaseBkp
{
    private $conn;
    private $dbName;
    private $dbHost;
    private $dbUser;
    private $dbPass;


    public function __construct($dbName)
    {
        $hosts = explode(':', explode('mysql://', $_SERVER['DATABASE_URL'])[1]);
        $base = explode('@', $hosts[1]);
        $this->dbHost = end($base);
        $this->dbUser = $hosts[0];
        $this->dbPass = $base[0];
        $this->dbName = $dbName;
    }

    public function conectBase(): DatabaseBkp
    {
        $connectionParams = [
            'dbname' => 'mysql', // Precisa estar conectado a um banco já existente
            'user' => $this->dbUser,
            'password' => $this->dbPass,
            'host' => $this->dbHost,
            'driver' => 'pdo_mysql',
        ];

        $this->conn = DriverManager::getConnection($connectionParams);
        return $this;
    }

    public function createDatabase(): DatabaseBkp
    {
        $this->conn->executeStatement("CREATE DATABASE {$this->dbName}");
        return $this;
    }

    public function importDatabase($backupFile)
    {
        try {
            $this->conn->executeStatement("USE $this->dbName");
            $sql = file_get_contents($backupFile);
            $this->conn->executeStatement($sql);
        } catch (\Exception $e) {
            dd($e);
        }
    }
}