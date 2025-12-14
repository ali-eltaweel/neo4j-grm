<?php

namespace Neo4jGRM\Models;

use Laudis\Neo4j\Client;

use Closure, RuntimeException;
use Neo4jGRM\Builder\QueryBuilder;

abstract class GraphEntity {

    private static ?Closure $clientResolver = null;

    protected static ?string $label = null;

    protected static string $connection = 'default';

    protected array $hiddenProperties = [];

    public function __construct(private ?int $id = null, private array $properties = [], private array $labels = []) {}

    public final function getId(): int {

        return $this->id;
    }

    public final function getLabels(): array {

        return $this->labels;
    }

    public final function hideProperties(string ...$properties): void {

        $this->hiddenProperties = array_unique(array_merge($this->hiddenProperties, $properties));
    }

    public final function showProperties(string ...$properties): void {

        $this->hiddenProperties = array_filter(
            $this->hiddenProperties,
            fn($prop) => !in_array($prop, $properties)
        );
    }

    public function __get(string $property): mixed {
        
        return $this->properties[$property] ?? null;
    }

    public function __set(string $property, mixed $value): void {

        $this->properties[$property] = $value;
    }

    public function toArray() {

        return array_filter(
            $this->properties,
            fn($key) => !in_array($key, $this->hiddenProperties),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected final function setId(int $id): void {

        $this->id = $id;
    }

    public static final function setClientResolver(Closure $resolver): void {
        
        self::$clientResolver = $resolver;
    }

    public static final function getClient(): Client {

        if (is_null($clientResolver = self::$clientResolver)) {
            
            throw new RuntimeException('Client resolver is not set.');
        }

        return $clientResolver(static::$connection);
    }

    public static final function getLabel(): string {

        if (!is_null(static::$label)) {

            return static::$label;
        }

        return array_reverse(explode('\\', static::class))[0];
    }

    public static abstract function query(): QueryBuilder;
}
