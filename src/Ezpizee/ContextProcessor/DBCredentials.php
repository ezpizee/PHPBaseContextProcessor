<?php

namespace Ezpizee\ContextProcessor;

use JsonSerializable;
use PDO;

class DBCredentials implements JsonSerializable
{
    public $dsn;
    public $driver;
    public $host;
    public $port;
    public $charset = 'utf8';
    public $username;
    public $password;
    public $dbName;
    public $prefix;
    public $collate = 'utf8_unicode_ci';
    public $options = null;

    public function __construct(array $config)
    {
        if (isset($config['driver'])) {
            $this->driver = $config['driver'];
        }
        else if (isset($config['dbtype'])) {
            $this->driver = $config['dbtype'];
        }

        if (isset($config['host'])) {
            $this->host = $config['host'] === 'localhost' ? '127.0.0.1' : $config['host'];
        }

        if (isset($config['port'])) {
            $this->port = $config['port'];
        }

        if (isset($config['charset'])) {
            $this->charset = $config['charset'];
        }

        if (isset($config['collate'])) {
            $this->charset = $config['collate'];
        }

        if (isset($config['user'])) {
            $this->username = $config['user'];
        }
        else if (isset($config['username'])) {
            $this->username = $config['username'];
        }
        else if (isset($config['u'])) {
            $this->username = $config['u'];
        }

        if (isset($config['password'])) {
            $this->password = $config['password'];
        }
        else if (isset($config['pwd'])) {
            $this->password = $config['pwd'];
        }
        else if (isset($config['pw'])) {
            $this->password = $config['pw'];
        }
        else if (isset($config['p'])) {
            $this->password = $config['p'];
        }

        if (isset($config['dbname'])) {
            $this->dbName = $config['dbname'];
        }
        else if (isset($config['database'])) {
            $this->dbName = $config['database'];
        }
        else if (isset($config['db'])) {
            $this->dbName = $config['db'];
        }
        else {
            $this->dbName = '';
        }

        if (isset($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }
        else if (isset($config['dbprefix'])) {
            $this->prefix = $config['dbprefix'];
        }
        else {
            $this->prefix = '';
        }

        if (isset($config['options'])) {
            $this->options = $config['options'];
        }
        else {
            $this->setOptions();
        }

        $this->setDSN();
    }

    private function setDSN()
    : void
    {
        if ($this->driver && $this->host) {
            $this->fetchDriver();
            $this->dsn = $this->driver . ':host=' . $this->host .
                ($this->port ? ';port=' . $this->port : '') .
                ($this->dbName ? ';dbname=' . $this->dbName : '') .
                ($this->charset ? ';charset=' . $this->charset : '');
        }
    }

    private function fetchDriver()
    {
        if ($this->driver === 'pdomysql' || $this->driver === 'pdo_mysql' || $this->driver === 'mysqli') {
            $this->driver = 'mysql';
        }
    }

    private function setOptions()
    : void
    {
        if ($this->collate) {
            if ($this->driver === 'mysql') {
                $this->options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT         => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->charset . " COLLATE " . $this->collate
                ];
            }
        }
    }

    public function isValid()
    : bool
    {
        return $this->dsn && $this->username && $this->password;
    }

    public function __toString()
    : string
    {
        return json_encode($this->jsonSerialize());
    }

    public function jsonSerialize()
    : array
    {
        return [
            'dsn'      => $this->dsn,
            'driver'   => $this->driver,
            'host'     => $this->host,
            'port'     => $this->port,
            'charset'  => $this->charset,
            'username' => $this->username,
            'password' => $this->password,
            'dbname'   => $this->dbName,
            'prefix'   => $this->prefix,
            'collate'  => $this->collate,
            'options'  => $this->options
        ];
    }
}