<?php

namespace Neo4jGRM\Models;

use Generator;
use Neo4jGRM\Builder\NodeQueryBuilder;

class Node extends GraphEntity {
    
    public static final function query(): NodeQueryBuilder {

        return (new NodeQueryBuilder())->entity(static::class);
    }

    public static final function create(array $properties): static {

        return static::query()->addProperties($properties)->create();
    }

    public static final function get(?int $id = null, array $properties = [], ?array $fields = null, ?int $skip = null, ?int $limit = null): Generator {

        $query = static::query();

        if (!is_null($id)) $query->whereId($id);
        
        foreach ($properties as $field => $value) {

            $query->where($field, $value);
        }

        yield from $query->get($fields, $skip, $limit);
    }

    public static final function count(?int $id = null, array $properties = []): int {

        $query = static::query();

        if (!is_null($id)) $query->whereId($id);
        
        foreach ($properties as $field => $value) {

            $query->where($field, $value);
        }

        return $query->count();
    }

    public static final function first(?int $id = null, array $properties = [], ?array $fields = null, ?int $skip = null): ?static {

        $query = static::query();

        if (!is_null($id)) $query->whereId($id);
        
        foreach ($properties as $field => $value) {

            $query->where($field, $value);
        }

        return $query->first($fields, $skip);
    }

    public static final function firstOrCreate(?int $id = null, array $properties = [], ?int $skip = null): static {

        return static::first($id, $properties, null, $skip) ?? static::create($properties);
    }
}
