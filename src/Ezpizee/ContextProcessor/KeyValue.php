<?php

namespace Ezpizee\ContextProcessor;

use JetBrains\PhpStorm\ArrayShape;
use JsonSerializable;

final class KeyValue implements JsonSerializable
{
    /** @var array $keys */
    public array $keys = array();
    /** @var array $values */
    public array $values = array();

    public function add(string $k, $v): void
    {
        $this->addKey($k);
        $this->addValue($v);
    }

    public function addKey(string $key): void {$this->keys[] = $key;}

    public function addValue($value): void {$this->values[] = $value;}

    public function isBalance(): bool {return sizeof($this->keys) === sizeof($this->values);}

    public function isEmpty(): bool {return sizeof($this->keys) === 0 || sizeof($this->values) === 0;}

    #[ArrayShape(['keys' => "array", 'values' => "array"])]
    public function jsonSerialize(): array {return ['keys' => $this->keys, 'values' => $this->values];}

    public function __toString(): string {return json_encode($this->jsonSerialize());}
}