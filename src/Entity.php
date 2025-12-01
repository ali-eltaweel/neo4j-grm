<?php

namespace Neo4jGRM;

use Generator;
use InvalidArgumentException;

abstract class Entity {

    use Traits\HasClient;

    protected static ?string $label = null;
    
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

    public static final function getLabel(): string {

        return static::$label ?? class_basename(static::class);
    }

    public static abstract function get(int|string|null $id = null, array $properties = [], ?int $skip = null, ?int $limit = null): Generator;
}
