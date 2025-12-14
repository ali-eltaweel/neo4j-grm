<?php

namespace Neo4jGRM\Models;

use Generator;
use Neo4jGRM\Builder\RelationshipQueryBuilder;

class Relationship extends GraphEntity {
    
    public static final function query(): RelationshipQueryBuilder {

        return (new RelationshipQueryBuilder())->entity(static::class);
    }

    public static final function create(callable $left, callable $right, array $properties = [], bool $leftToRight = true): static {

        $query = static::query()->addProperties($properties)->leftToRight($leftToRight);

        $left($query->leftNode());
        $right($query->rightNode());

        return $query->create();
    }

    public static final function get(int|string|null $id = null, ?callable $left = null, ?callable $right = null, array $properties = [], bool $leftToRight = true, ?array $fields = null, ?int $skip = null, ?int $limit = null): Generator {

        $query = static::query()->leftToRight($leftToRight);

        if (!is_null($id)) $query->whereId($id);

        if (!is_null($left))  $left($query->leftNode());
        if (!is_null($right)) $right($query->rightNode());
        
        foreach ($properties as $field => $value) {

            $query->where($field, $value);
        }

        yield from $query->get($fields, $skip, $limit);
    }

    public static final function count(int|string|null $id = null, ?callable $left = null, ?callable $right = null, array $properties = [], bool $leftToRight = true): int {

        $query = static::query()->leftToRight($leftToRight);

        if (!is_null($id)) $query->whereId($id);

        if (!is_null($left))  $left($query->leftNode());
        if (!is_null($right)) $right($query->rightNode());
        
        foreach ($properties as $field => $value) {

            $query->where($field, $value);
        }

        return $query->count();
    }

    public static final function first(?int $id = null, ?callable $left = null, ?callable $right = null, array $properties = [], bool $leftToRight = true, ?array $fields = null, ?int $skip = null): ?static {

        $query = static::query()->leftToRight($leftToRight);

        if (!is_null($id)) $query->whereId($id);

        if (!is_null($left))  $left($query->leftNode());
        if (!is_null($right)) $right($query->rightNode());
        
        foreach ($properties as $field => $value) {

            $query->where($field, $value);
        }

        return $query->first($fields, $skip);
    }

    public static final function firstOrCreate(callable $left, callable $right, ?int $id = null, array $properties = [], bool $leftToRight = true, ?int $skip = null): static {

        return static::first($id, $left, $right, $properties, $leftToRight, null, $skip)
            ?? static::create($left, $right, $properties, $leftToRight);
    }
}
