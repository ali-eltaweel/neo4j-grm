<?php

namespace Neo4jGRM;

use InvalidArgumentException;

abstract class Entity {

    use Traits\HasClient;

    public function __construct(private array $properties) {}

    public function __get(string $property): mixed {
        
        return $this->properties[$property] ?? null;
    }

    public function __set(string $property, mixed $value): void {

        if ($property === 'id') {
            
            throw new InvalidArgumentException('The "id" property is read-only.');
        }

        $this->properties[$property] = $value;
    }
}
