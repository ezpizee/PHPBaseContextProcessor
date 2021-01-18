<?php

namespace Ezpizee\ContextProcessor;

use JsonSerializable;

final class KeyValue implements JsonSerializable
{
    /**
     * @var array
     */
    public $keys = array();
    /**
     * @var array
     */
    public $values = array();

    public function isBalance(): bool{return sizeof($this->keys) === sizeof($this->values);}

    public function isEmpty(): bool {return sizeof($this->keys) === 0 || sizeof($this->values) === 0;}

    public function jsonSerialize(): array
    {
        return ['keys'=>$this->keys,'values'=>$this->values];
    }

    public function __toString(): string
    {
        return json_encode($this->jsonSerialize());
    }
}