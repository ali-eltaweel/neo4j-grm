<?php

namespace Neo4jGRM;

use Generator;
use InvalidArgumentException;

abstract class Entity {

    use Traits\HasClient;

    protected static ?string $label = null;

    protected array $hidden = [];
    
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

    public function toArray() {

        return array_filter(
            $this->properties,
            fn($key) => !in_array($key, $this->hidden),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function all() {

        return $this->properties;
    }

    public final function hideProperties(string ...$properties): void {

        $this->hidden = array_unique(array_merge($this->hidden, $properties));
    }

    public final function showProperties(string ...$properties): void {

        $this->hidden = array_filter(
            $this->hidden,
            fn($prop) => !in_array($prop, $properties)
        );
    }

    public static final function getLabel(): string {

        if (!is_null(static::$label)) {

            return static::$label;
        }

        return array_reverse(explode('\\', static::class))[0];
    }

    public static abstract function get(int|string|null $id = null, array $properties = [], ?int $skip = null, ?int $limit = null): Generator;
}
