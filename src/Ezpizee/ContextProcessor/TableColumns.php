<?php

namespace ContextProcessor;

use JsonSerializable;

class TableColumns implements JsonSerializable
{
    private $fieldList = [];
    private $fieldObject = [];

    public function __construct(array $rows)
    {
        if (!empty($rows)) {
            foreach ($rows as $i => $row) {
                $this->fieldList[] = $row['Field'];
            }
            $this->fieldObject = $rows;
            foreach ($this->fieldObject as $i => $row) {
                $arr = explode('(', $row['Type']);
                if (sizeof($arr) > 1) {
                    $this->fieldObject[$i]['Size'] = str_replace(')', '', $arr[1]);
                    $this->fieldObject[$i]['Type'] = $arr[0];
                }
            }
        }
    }

    public function getFieldList(bool $excludePK = false)
    : array
    {
        if ($excludePK) {
            $fields = [];
            foreach ($this->fieldObject as $i => $field) {
                if ($this->getFieldObjectElement($i, 'Key') !== 'PRI') {
                    $fields[] = $this->getFieldName($i);
                }
            }
            return $fields;
        }
        return $this->fieldList;
    }

    public function getFieldObjectElement(int $index, string $field)
    : string
    {
        if (isset($this->fieldObject[$index])) {
            return isset($this->fieldObject[$index][$field]) ? $this->fieldObject[$index][$field] : "";
        }
        return "";
    }

    public function getFieldName(int $index)
    : string
    {
        return $this->getFieldObjectElement($index, 'Field');
    }

    public function getFieldObject()
    : array
    {
        return $this->fieldObject;
    }

    public function hasField(int $index, string $name)
    : bool
    {
        return $this->getFieldObjectElement($index, 'Field') === $name;
    }

    public function isType(int $index, string $type)
    : bool
    {
        return $this->getFieldType($index) === $type;
    }

    public function getFieldType(int $index)
    : string
    {
        return $this->getFieldObjectElement($index, 'Type');
    }

    public function getDefault(int $index)
    : string
    {
        $default = $this->getFieldObjectElement($index, 'Default');
        return empty($default) ? "" : $default;
    }

    public function getSize(int $index)
    : int
    {
        $size = $this->getFieldObjectElement($index, 'Size');
        return $size ? (int)$size : 0;
    }

    public function __toString() { return json_encode($this->jsonSerialize()); }

    public function jsonSerialize()
    {
        return ['fieldList' => $this->fieldList, 'fieldObject' => $this->fieldObject];
    }
}