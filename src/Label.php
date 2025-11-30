<?php

namespace Neo4jGRM;

use Generator;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Neo4jQueryBuilder\Clauses\Create;
use Neo4jQueryBuilder\QueryBuilder;

class Label extends Entity {

    protected static ?string $label = null;

    public static final function getLabel(): string {

        return static::$label ?? class_basename(static::class);
    }

    public static final function create(array $properties): static {

        $query = new QueryBuilder();

        $query->create()->node()->alias('n')->label(static::getLabel())->properties($properties);
        $query->return()->element('n');

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query);

        /** @var CypherMap $cypherMap */
        $cypherMap = $result->first();

        /** @var Node $node */
        $node = $cypherMap->get($cypherMap->key());

        /** @var CypherMap $properties */
        $properties = $node->getProperties();

        return new static([
            'id' => $node->getId(),
            ...$properties->toArray()
        ]);
    }

    public static final function insert(array $records, bool $return = false): Generator {

        $query = new QueryBuilder();

        $aliases = [];

        $query->create(function(Create $create) use ($records, &$aliases) {

            foreach ($records as $index => $properties) {
    
                $create->node()->alias($aliases[] = 'n' . $index)->label(static::getLabel())->properties($properties);
            }
        });

        $query->return()->elements($aliases);

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query);

        /** @var CypherMap $cypherMap */
        $cypherMap = $result->first();

        foreach ($aliases as $alias) {

            /** @var Node $node */
            $node = $cypherMap->get($alias);

            /** @var CypherMap $properties */
            $properties = $node->getProperties();

            yield new static([
                'id' => $node->getId(),
                ...$properties->toArray()
            ]);
        }
    }

    public static final function count(int|string|null $id = null, array $properties = []): int {

        $query = new QueryBuilder();

        $query->match()->node()->alias('n')->label(static::getLabel())->properties($properties);

        if (!is_null($id)) {

            $query->where()->condition()->name('id(n)')->operator('=')->value($id);
        }

        $query->return()->element('count(n) AS count');

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query);

        /** @var CypherMap $cypherMap */
        $cypherMap = $result->first();
        
        return $cypherMap->get('count');
    }

    public static final function get(int|string|null $id = null, array $properties = [], ?int $skip = null, ?int $limit = null): Generator {

        $query = new QueryBuilder();

        $query->match()->node()->alias('n')->label(static::getLabel())->properties($properties);

        if (!is_null($id)) {

            $query->where()->condition()->name('id(n)')->operator('=')->value($id);
        }

        $query->return()->element('n');

        if (!is_null($skip))  $query->skip($skip);
        if (!is_null($limit)) $query->limit($limit);

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query);

        /** @var CypherMap $record */
        foreach ($result as $record) {

            // /** @var Node $node */
            $node = $record->get('n');

            yield new static([
                'id' => $node->getId(),
                ...$node->getProperties()->toArray()
            ]);
        }
    }

    public static final function delete(int|string|null $id = null, array $properties = []): void {

        $query = new QueryBuilder();

        $query->match()->node()->alias('n')->label(static::getLabel())->properties($properties);

        if (!is_null($id)) {

            $query->where()->condition()->name('id(n)')->operator('=')->value($id);
        }

        $query->delete()->element('n');

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query);
    }
}
