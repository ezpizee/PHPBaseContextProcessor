<?php

namespace ContextProcessor;

use JsonSerializable;
use mysqli;
use mysqli_result;
use RuntimeException;

class DBO implements JsonSerializable
{
    /**
     * @var mysqli
     */
    private $conn;

    /**
     * @var DBCredentials
     */
    private $config;

    private $stm;
    private $stopWhenError = false;
    private $keepResults = false;
    private $errors = [];
    private $results = [];
    private $queries = [];

    public function __construct(DBCredentials $config, bool $stopWhenError = false, bool $keepResults = false)
    {
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

    private function connect()
    : void
    {
        $this->conn = new mysqli($this->config->host, $this->config->username, $this->config->password, $this->config->dbName, $this->config->port);
        if ($this->conn->error) {
            throw new RuntimeException("Failed to connect to MySQL: " . $this->conn->error . ' (' . $this->config . ')');
        }
        else if ($this->conn->connect_error) {
            throw new RuntimeException("Failed to connect to MySQL: " . $this->conn->connect_error . ' (' . $this->config . ')');
        }
    }

    public function getErrors()
    : array
    {
        return $this->errors;
    }

    public function getDebugQueries()
    : array
    {
        return $this->queries;
    }

    public function closeConnection()
    : void
    {
        if ($this->isConnected()) {
            $this->conn->close();
        }
    }

    public function isConnected()
    : bool
    {
        return $this->conn instanceof mysqli && !$this->conn->connect_errno;
    }

    public function lastInsertId() { return $this->isConnected() ? $this->conn->insert_id : 0; }

    public function exec(string $query = '')
    : bool
    {
        return $this->execute($query);
    }

    public function execute(string $query = '')
    : bool
    {
        if ($query) {
            $this->setQuery($query);
        }
        if ($this->stm) {
            $this->reset();
            $arr = explode("\n", $this->stm);
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
                        $this->query(substr($query, 0, strlen($query) - 1), false, false);
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
        $this->queries[] = $query;

        if ($fetchResult) {
            if ($isAssoc) {
                $result = $this->conn->query($query);
                if ($result) {
                    $row = $result->fetch_assoc();
                    if (!empty($row)) {
                        $this->results[] = $row;
                    }
                }
                else if ($this->conn->error) {
                    $this->errors[] = $this->conn->error;
                }
            }
            else {
                $result = $this->conn->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        if (!empty($row)) {
                            $this->results[] = $row;
                        }
                    }
                }
                else if ($this->conn->error) {
                    $this->errors[] = $this->conn->error;
                }
            }
        }
        else {
            $result = $this->conn->query($query);
            if (is_bool($result) && !$result && $this->conn->error) {
                if ($this->stopWhenError || $stopWhenError) {
                    throw new RuntimeException(DBO::class . ".query: " . $this->conn->error . "\n");
                }
                else {
                    $this->errors[] = $this->conn->error;
                }
            }
            else if ($result instanceof mysqli_result && $this->keepResults) {
                $this->results[] = $result->fetch_all();
            }
        }
    }

    public final function getTableColumns(string $tableName)
    : TableColumns
    {
        $query = 'DESCRIBE ' . $this->quoteName($tableName);
        return (new TableColumns($this->loadAssocList($query)));
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
            $arr = explode("\n", $this->stm);
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
        $row = null;
        $row = $this->loadAssoc($dbExistStm);
        return !empty($row) && is_array($row) && isset($row['SCHEMA_NAME']);
    }

    public function quote(string $str)
    : string
    {
        return $this->isConnected() ? "'" . $this->conn->real_escape_string($str) . "'" : $str;
    }

    public function loadAssoc(string $query = '')
    : array
    {
        if ($query) {
            $this->setQuery($query);
        }
        if ($this->stm) {
            $this->reset();
            $arr = explode("\n", $this->stm);
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