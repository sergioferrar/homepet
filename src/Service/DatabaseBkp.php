<?php

namespace App\Service;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;

class DatabaseBkp
{
    private $conn;
    private $dbName;
    private $dbHost;
    private $dbUser;
    private $dbPass;


    public function __construct(Connection $connection)
    {
        $this->conn = $connection;
    }

    public function setDbName($dbName){
        $this->dbName = $dbName;
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