<?php

namespace Ezpizee\ContextProcessor;

use JsonSerializable;
use RuntimeException;

class QueryKeyValuePairs implements JsonSerializable
{
    private $tableFields = [];
    private $fields = [];
    private $values = [];
    private $keys = [];
    private $primaryKeysValues = [];
    private $condition = '';
    private $existentCondition = '';

    public function __construct(array $tableFields=array())
    {
        $this->tableFields = $tableFields;
    }

    public function addFieldValue($key, $value)
    : void
    {
        $key = str_replace("`", '', $key);
        $value = is_array($value) || is_object($value) ? json_encode($value) : (is_null($value) ? '' : $value);
        if (!empty($this->tableFields)) {
            if (in_array(strtolower($key), $this->tableFields)) {
                $key = strtolower($key);
            }
            else if (in_array(strtoupper($key), $this->tableFields)) {
                $key = strtoupper($key);
            }
            else if (!in_array($key, $this->tableFields)) {
                $key = null;
            }
        }
        if (!empty($key)) {
            if (!empty($value)) {
                if (substr($value, 0, 1) === "'") {
                    if ($value[strlen($value) - 1] === "'") {
                        $value = substr($value, 1, strlen($value) - 2);
                    }
                }
            }
            $this->fields[$key] = $key;
            $this->values[$key] = $value;
        }
    }
    public function setFieldValueIfNotExists($key, $val)
    : void
    {
        if (!isset($this->fields[$key])) {
            $this->fields[$key] = $key;
            $this->values[$key] = $val;
        }
    }

    public function hasCondition(): bool {return !empty($this->condition);}
    public function addCondition(string $key, string $val, string $operator='AND')
    : void
    {
        if (!in_array(strtoupper(trim($operator)), ['AND','OR'])) {
            throw new RuntimeException('Operator for condition has to be either AND or OR ('.self::class.'->addCondition)', 500);
        }
        $condition = '('.$key.'='.$val.')';
        if (strpos($this->condition, $condition) === false) {
            $this->condition = $this->condition.(!empty($this->condition) ? ' '.strtoupper(trim($operator)).' ' : '').$condition;
        }
    }
    public function setCondition(string $condition, string $operator='AND')
    : void
    {
        if (empty($condition)) {
            throw new RuntimeException('Condition cannot be empty ('.self::class.'->setCondition)', 500);
        }
        if (!in_array(strtoupper(trim($operator)), ['AND','OR'])) {
            throw new RuntimeException('Operator for condition has to be either AND or OR ('.self::class.'->setCondition)', 500);
        }
        if (strpos($this->condition, $condition) === false) {
            $this->condition = $this->condition.(!empty($this->condition) ? ' '.strtoupper(trim($operator)).' ' : '').$condition;
        }
    }
    public function getCondition(): string {return $this->condition;}

    public function hasExistentCondition(): bool {return !empty($this->existentCondition);}
    public function addExistentCondition(string $key, string $val, string $operator='AND')
    : void
    {
        if (!in_array(strtoupper(trim($operator)), ['AND','OR'])) {
            throw new RuntimeException('Operator for condition has to be either AND or OR ('.self::class.'->addExistentCondition)', 500);
        }
        $condition = '('.$key.'='.$val.')';
        if (strpos($this->existentCondition, $condition) === false) {
            $this->existentCondition = $this->existentCondition.
                (!empty($this->existentCondition) ? ' '.strtoupper(trim($operator)).' ' : '').$condition;
        }
    }
    public function setExistentCondition(string $condition, string $operator='AND')
    : void
    {
        if (empty($condition)) {
            throw new RuntimeException('Condition cannot be empty ('.self::class.'->setExistentCondition)', 500);
        }
        if (!in_array(strtoupper(trim($operator)), ['AND','OR'])) {
            throw new RuntimeException('Operator for condition has to be either AND or OR ('.self::class.'->setExistentCondition)', 500);
        }
        if (strpos($this->existentCondition, $condition) === false) {
            $this->existentCondition = $this->existentCondition.
                (!empty($this->existentCondition) ? ' '.strtoupper(trim($operator)).' ' : '').$condition;
        }
    }
    public function getExistentCondition(): string {return $this->existentCondition;}

    public function setTableFields(array $tableFields): void {
        if (empty($this->tableFields)){
            $this->tableFields = $tableFields;
        }
        else {
            throw new RuntimeException('tableFields is not empty ('.self::class.'->setTableFields)', 500);
        }
    }
    public function getTableFields(): array {return $this->tableFields;}
    public function hasTableFields(): bool {return !empty($this->tableFields);}

    public function isValidPrimaryKeys(): bool {
        if (!empty($this->tableFields)) {
            if (!empty($this->keys)) {
                foreach ($this->keys as $key) {
                    if (!(!empty($key) && (
                            in_array($key, $this->tableFields) ||
                            in_array(strtolower($key), $this->tableFields) ||
                            in_array(strtoupper($key), $this->tableFields))
                    )) {
                        throw new RuntimeException('key is either empty or not in the tableFields ('.self::class.'->isValidKeys)', 500);
                    }
                }
                return true;
            }
            else {
                throw new RuntimeException('keys is empty ('.self::class.'->isValidKeys)', 500);
            }
        }
        else {
            throw new RuntimeException('tableFields is empty ('.self::class.'->isValidKeys)', 500);
        }
    }
    public function isInPrimaryKeys(string $field)
    : bool
    {
        return in_array($field, $this->keys) || in_array(strtolower($field), $this->keys) || in_array(strtoupper($field), $this->keys);
    }
    public function setPrimaryKeys($keys): void {
        if (!empty($keys) && !is_numeric($keys) && !is_bool($keys)) {
            if (is_string($keys)) {
                $this->keys = explode(',', $keys);
            }
            else if (is_object($keys)) {
                $this->keys = json_decode(json_encode($keys), true);
            }
            else if (is_array($keys)) {
                $this->keys = $keys;
            }
            else {
                throw new RuntimeException('Invalid keys ('.self::class.'->setPrimaryKeys)', 500);
            }
        }
        else {
            throw new RuntimeException('keys is empty ('.self::class.'->setPrimaryKeys)', 500);
        }
    }
    public function getPrimaryKeys(): array {return $this->keys;}
    public function getNumPrimaryKeys(): int {return sizeof($this->keys);}
    public function getPrimaryKeysAsString(): string {return implode(',', $this->keys);}

    public function addPrimaryKeysValue(string $key, string $value): void {
        if (in_array($key, $this->keys)) {
            $this->primaryKeysValues[$key] = $value;
        }
        else {
            throw new RuntimeException('key does not exist ('.self::class.'->addPrimaryKeysValue)', 500);
        }
    }
    public function getPrimaryKeyValue(string $key): string {return isset($this->primaryKeysValues[$key]) ? $this->primaryKeysValues[$key] : "";}
    public function getPrimaryKeysValues(): array {return $this->primaryKeysValues;}
    public function hasPrimaryKeyValue(string $key): bool {return isset($this->primaryKeysValues[$key]);}

    public function getFields(): array {return $this->fields;}
    public function getValues(): array {return $this->values;}
    public function getValue(string $key): string {return isset($this->values[$key]) ? $this->values[$key] : '';}
    public function getFieldsAsString(): string {return implode(',', $this->fields);}
    public function getValuesAsString(): string {return implode(',', $this->values);}

    public function reset()
    : void
    {
        $this->tableFields = [];
        $this->fields = [];
        $this->values = [];
        $this->keys = [];
        $this->primaryKeysValues = [];
        $this->condition = '';
        $this->existentCondition = '';
    }

    public function jsonSerialize()
    {
        return ['fields'=>$this->fields, 'values'=>$this->values, 'tableFields'=>$this->tableFields];
    }

    public function __toString()
    {
        return json_encode($this->jsonSerialize());
    }
}