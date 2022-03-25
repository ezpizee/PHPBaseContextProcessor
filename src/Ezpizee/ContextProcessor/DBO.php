<?php

namespace Ezpizee\ContextProcessor;

use Ezpizee\Utils\StringUtil;
use JsonSerializable;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class DBO implements JsonSerializable
{
    private static $connections = [];
    /**
     * @var PDO
     */
    private $conn = null;
    /**
     * @var DBCredentials
     */
    private $config = null;
    private $stm = '';
    private $stopWhenError = false;
    private $keepResults = false;
    private $errors = [];
    private $results = [];
    private $queries = [];
    private $isDebug = false;
    private $cachedResults = [];

    public function __construct(DBCredentials $config, bool $stopWhenError = false, bool $keepResults = false)
    {
        if ($config->isValid()) {
            $this->stopWhenError = $stopWhenError;
            $this->keepResults = $keepResults;
            $this->config = $config;
            if ($this->config->isValid()) {
                $this->connect();
            }
            else {
                throw new RuntimeException('Invalid sql credentials (' . DBO::class . ')');
            }
        }
    }

    private function connect()
    : void
    {
        if (defined('DEBUG') && DEBUG &&
            defined('EZPIZEE_STACK_SQL_STM') && EZPIZEE_STACK_SQL_STM) {
            $this->setIsDebug(true);
        }
        if (isset(self::$connections[$this->config->dsn])) {
            $this->conn = self::$connections[$this->config->dsn];
        }
        else if ($this->config->driver === 'oracle_oci') {
            $this->conn = oci_connect($this->config->username, $this->config->password, $this->config->dsn, $this->config->charset);
            if (!$this->conn) {
                $m = oci_error();
                throw new RuntimeException(
                    "Failed to connect to db server (".$this->config->driver."): " . $m['message'] . ' (' . $this->config->dsn . ')',
                    500
                );
            }
            else {
                self::$connections[$this->config->dsn] = $this->conn;
            }
        }
        else {
            try {
                $this->conn = new PDO($this->config->dsn, $this->config->username, $this->config->password, $this->config->options);
                self::$connections[$this->config->dsn] = $this->conn;
            }
            catch (PDOException $e) {
                throw new RuntimeException(
                    "Failed to connect to db server (".$this->config->driver."): " . $e->getMessage() . ' (' . $this->config->dsn . ')',
                    500
                );
            }
        }
    }

    public function isConnected(): bool {return $this->conn instanceof PDO;}

    public function setIsDebug(bool $b): void {$this->isDebug = $b;}

    public function getErrors(): array {return $this->errors;}

    public function getDebugQueries(): array {return $this->queries;}

    public function closeConnection()
    : void
    {
        if ($this->isConnected()) {
            $this->conn = null;
            if (isset(self::$connections[$this->config->dsn])) {
                unset(self::$connections[$this->config->dsn]);
            }
        }
    }

    public function lastInsertId() { return $this->isConnected() ? $this->conn->lastInsertId() : 0; }

    public function exec(string $query = ''): bool {return $this->execute($query);}

    public function executeQuery(string $query): bool {return $this->execute($query);}

    public function execute(string $query = '')
    : bool
    {
        if ($query) {
            $this->setQuery($query);
        }
        if ($this->stm) {
            $this->reset();
            $arr = explode(";\n", $this->stm);
            if (sizeof($arr) > 1) {
                $query = '';
                foreach ($arr as $line) {
                    // Skip it if it's a comment
                    if (
                        (substr($line, 0, 2) === '--' || trim($line) === '') ||
                        (
                            strlen(trim($line)) > 3 && substr(trim($line), 0, 3) === '/*!' &&
                            substr(trim($line), -3, 3) === '*/;'
                        )
                    ) {
                        continue;
                    }

                    // Add this line to the current segment
                    $query .= $line;

                    // If it has a semicolon at the end, it's the end of the query
                    if (substr(trim($line), -1, 1) === ';') {
                        $this->query(substr($query, 0, strlen($query) - 1), false, false);
                        // Reset temp variable to empty
                        $query = '';
                    }
                    else if (trim($query)) {
                        $this->query($query, false, false);
                        // Reset temp variable to empty
                        $query = '';
                    }
                }
            }
            else {
                $this->query($this->stm, false, false);
            }
            return sizeof($this->errors) < 1;
        }
        else {
            throw new RuntimeException(DBO::class . '.execute: query statement is empty', 500);
        }
    }

    public function setQuery(string $stm)
    : void
    {
        $this->stm = str_replace('#__', $this->getPrefix(), $stm);
    }

    public function getPrefix()
    : string
    {
        return $this->isConnected() ? $this->config->prefix : '';
    }

    private function reset()
    : void
    {
        $this->errors = [];
        $this->results = [];
    }

    private function query(string $query, bool $fetchResult = false, bool $isAssoc = false, bool $stopWhenError = false)
    {
        $query = StringUtil::removeWhitespace($query);
        $md5Query = md5($query);

        if (isset($this->cachedResults[$md5Query])) {
            $this->results = $this->cachedResults[$md5Query];
        }
        else {
            if ($this->isDebug) {
                $this->queries[] = $query;
            }

            if ($fetchResult) {
                if ($isAssoc) {
                    $result = $this->conn->query($query);
                    if ($result) {
                        $row = $result->fetch(PDO::FETCH_ASSOC);
                        if (!empty($row)) {
                            $this->results[] = $row;
                        }
                    } else if (!empty($this->conn->errorInfo())) {
                        $this->errors[] = $this->conn->errorInfo();
                    }
                } else {
                    $result = $this->conn->query($query);
                    if ($result) {
                        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $row) {
                            if (!empty($row)) {
                                $this->results[] = $row;
                            }
                        }
                    } else if (!empty($this->conn->errorInfo())) {
                        $this->errors[] = $this->conn->errorInfo();
                    }
                }
            } else {
                $result = $this->conn->query($query);
                if (is_bool($result) && !$result && !empty($this->conn->errorInfo())) {
                    if ($this->stopWhenError || $stopWhenError) {
                        throw new RuntimeException(DBO::class . ".query: " . json_encode($this->conn->errorInfo()) . "\n");
                    } else {
                        $this->errors[] = $this->conn->errorInfo();
                    }
                } else if ($result instanceof PDOStatement && $this->keepResults) {
                    $this->results[] = $result->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            $this->cachedResults[$md5Query] = $this->results;
        }
    }

    public final function getTableColumns(string $tableName)
    : TableColumns
    {
        $query = 'DESCRIBE ' . $this->quoteName($tableName);
        return (new TableColumns($this->loadAssocList($query)));
    }

    public final function alterStorageEngine(string $tb, string $engine)
    : void
    {
        $query = 'ALTER'.' TABLE '.$tb.' ENGINE = '.$engine;
        $this->exec($query);
    }

    public function quoteName(string $str)
    : string
    {
        return '`' . $str . '`';
    }

    public function loadAssocList(string $query = '')
    : array
    {
        if ($query) {
            $this->setQuery($query);
        }
        if ($this->stm) {
            $this->reset();
            $arr = explode(";\n", $this->stm);
            if (sizeof($arr) > 1) {
                $query = '';
                foreach ($arr as $line) {
                    // Skip it if it's a comment
                    if (substr($line, 0, 2) === '--' || trim($line) === '') {
                        continue;
                    }
                    if (strlen(trim($line)) > 3 && substr(trim($line), 0, 3) === '/*!' && substr(trim($line), -3, 3) === '*/;') {
                        continue;
                    }

                    // Add this line to the current segment
                    $query .= $line;

                    // If it has a semicolon at the end, it's the end of the query
                    if (substr(trim($line), -1, 1) == ';') {
                        $this->query(substr($query, 0, strlen($query) - 1), true, false);
                        // Reset temp variable to empty
                        $query = '';
                    }
                }
                return $this->results;
            }
            else {
                $this->query($this->stm, true, false);
                return $this->results;
            }
        }
        else {
            throw new RuntimeException(DBO::class . '.loadAssocList: query statement is empty', 500);
        }
    }

    public final function dbExists(string $dbName)
    : bool
    {
        $dbExistStm = 'SELECT ' . 'SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=' . $this->quote($dbName);
        $row = $this->loadAssoc($dbExistStm);
        return !empty($row) && is_array($row) && isset($row['SCHEMA_NAME']);
    }

    public function quote(string $str)
    : string
    {
        return $this->isConnected() ? $this->conn->quote($str) : $str;
    }

    public function loadAssoc(string $query = '')
    : array
    {
        if ($query) {
            $this->setQuery($query);
        }
        if ($this->stm) {
            $this->reset();
            $arr = explode(";\n", $this->stm);
            if (sizeof($arr) > 1) {
                $query = '';
                foreach ($arr as $line) {
                    // Skip it if it's a comment
                    if (substr($line, 0, 2) === '--' || trim($line) === '') {
                        continue;
                    }
                    if (strlen(trim($line)) > 3 && substr(trim($line), 0, 3) === '/*!' && substr(trim($line), -3, 3) === '*/;') {
                        continue;
                    }

                    // Add this line to the current segment
                    $query .= $line;

                    // If it has a semicolon at the end, it's the end of the query
                    if (substr(trim($line), -1, 1) == ';') {
                        $this->query(substr($query, 0, strlen($query) - 1), true, true);
                        // Reset temp variable to empty
                        $query = '';
                    }
                }
                return $this->results;
            }
            else {
                $this->query($this->stm, true, true);
                return isset($this->results[0]) ? $this->results[0] : [];
            }
        }
        else {
            throw new RuntimeException(DBO::class . '.loadAssoc: query statement is empty', 500);
        }
    }

    public function fetchAssoc(string $query): array {return $this->loadAssoc($query);}

    public function fetchRow(string $query): array {return $this->loadAssoc($query);}

    public function fetchAssocList(string $query): array {return $this->loadAssocList($query);}

    public function fetchRows(string $query): array {return $this->loadAssocList($query);}

    public function fetchAssociative(string $query): array {return $this->loadAssoc($query);}

    public function fetchAllAssociative(string $query): array {return $this->loadAssocList($query);}

    public function getConnections(): array {return array_keys(self::$connections);}

    /**
     * @return DBCredentials
     */
    public function getConfig() { return $this->config; }

    public function __toString() { return json_encode($this->jsonSerialize()); }

    public function jsonSerialize()
    : array
    {
        return $this->config->jsonSerialize();
    }
}