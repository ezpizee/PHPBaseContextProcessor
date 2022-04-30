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
    public $service_name;
    public $oracle_region;
    public $collate = 'utf8';
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

        if (isset($config['service_name'])) {
            $this->service_name = $config['service_name'];
        }

        if (isset($config['oracle_region'])) {
            $this->oracle_region = $config['oracle_region'];
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

        if (!empty($this->options) && defined('MYSQL_ATTR_SSL_CA')) {
            $this->options[PDO::MYSQL_ATTR_SSL_CA] = MYSQL_ATTR_SSL_CA;
            $this->options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $this->setDSN();
    }

    private function setDSN()
    : void
    {
        if ($this->driver && $this->host) {
            $this->fetchDriver();
            if ($this->driver === 'oracle_oci') {
                $dsn = '(description= (retry_count=2)(retry_delay=1)(address=(protocol=tcps)(port=${port})(host=${host}))'.
                    '(connect_data=(service_name=${service_name}))'.
                    '(security=(ssl_server_cert_dn="CN=${host}, OU=${oracle_region}, O=Oracle Corporation, L=Redwood City, ST=California, C=US")))';
                $this->dsn = str_replace(
                    ['${port}', '${host}', '${service_name}', '${oracle_region}'],
                    [$this->port, $this->host, $this->service_name, $this->oracle_region],
                    $dsn);
            }
            else {
                $this->dsn = $this->driver . ':host=' . $this->host .
                    ($this->port ? ';port=' . $this->port : '') .
                    ($this->dbName ? ';dbname=' . $this->dbName : '') .
                    ($this->charset ? ';charset=' . $this->charset : '');
            }
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
            'dsn'               => $this->dsn,
            'driver'            => $this->driver,
            'host'              => $this->host,
            'port'              => $this->port,
            'charset'           => $this->charset,
            'username'          => $this->username,
            'password'          => $this->password,
            'dbname'            => $this->dbName,
            'prefix'            => $this->prefix,
            'service_name'      => $this->service_name,
            'oracle_region'     => $this->oracle_region,
            'collate'           => $this->collate,
            'options'           => $this->options
        ];
    }
}