<?php

namespace Neo4jGRM;

use Generator;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Neo4jQueryBuilder\Clauses\Create;
use Neo4jQueryBuilder\QueryBuilder;
use Neo4jQueryBuilder\RelationshipBuilder;

class Label extends Entity {

    public function __get(string $name): mixed {

        if (method_exists($this, $name)) {

            if (($relation = $this->$name()) instanceof Relations\Relation) {

                $query = new QueryBuilder();

                $query->match()->relationship(function(RelationshipBuilder $rel) use ($relation) {

                    if ($relation->direction === Relations\Direction::OUTGOING) {

                        $rel->from()->alias('a')->label(static::getLabel());
                    } else {
                        $rel->to()->alias('a')->label(static::getLabel());
                    }

                    $rel->label($relation->name);

                    if ($relation->direction === Relations\Direction::OUTGOING) {
                        
                        $rel->to()->alias('b')->label($relation->relatedLabel::getLabel());
                    } else {

                        $rel->from()->alias('b')->label($relation->relatedLabel::getLabel());
                    }
                });
                
                $query->where()->condition()->name('id(a)')->operator('=')->value($this->id);

                $query->return()->element('b');

                if (!$relation->multiple) {

                    $query->limit(1);
                }

                /** @var SummarizedResult $result */
                $result = static::getClient()->run($query);

                $records = [];

                /** @var CypherMap $record */
                foreach ($result as $record) {

                    /** @var Node $node */
                    $node = $record->get('b');

                    $relatedLabel = $relation->relatedLabel;

                    $relatedNode = new $relatedLabel([
                        'id' => $node->getId(),
                        ...$node->getProperties()->toArray()
                    ]);

                    if (!$relation->multiple) {

                        return $relatedNode;
                    }

                    $records[] = $relatedNode;
                }
                
                return $records;
            }
        }

        return parent::__get($name);
    }

    public static final function create(array $properties): static {

        $query = new QueryBuilder();

        $query->create()->node()->alias('n')->label(static::getLabel())->properties($properties);
        $query->return()->element('n');

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());

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

    public static final function insert(array $records): Generator {

        $query = new QueryBuilder();

        $aliases = [];

        $query->create(function(Create $create) use ($records, &$aliases) {

            foreach ($records as $index => $properties) {
    
                $create->node()->alias($aliases[] = 'n' . $index)->label(static::getLabel())->properties($properties);
            }
        });

        $query->return()->elements($aliases);

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());

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

        $node = $query->match()->node()->alias('n')->label(static::getLabel());

        foreach ($properties as $key => $value) {

            if (!is_null($value)) {

                $node->property($key, $value);
            }
        }

        if (!is_null($id)) {

            $query->where()->condition()->name('id(n)')->operator('=')->value($id);
        }

        foreach ($properties as $key => $value) {

            if (is_null($value)) {

                $query->where()->condition()->name("n.{$key}")->operator('IS NULL');
            }
        }

        $query->return()->element('count(n) AS count');

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());

        /** @var CypherMap $cypherMap */
        $cypherMap = $result->first();
        
        return $cypherMap->get('count');
    }

    public static final function get(int|string|null $id = null, array $properties = [], ?int $skip = null, ?int $limit = null): Generator {

        $query = new QueryBuilder();

        $node = $query->match()->node()->alias('n')->label(static::getLabel());

        foreach ($properties as $key => $value) {

            if (!is_null($value)) {

                $node->property($key, $value);
            }
        }

        if (!is_null($id)) {

            $query->where()->condition()->name('id(n)')->operator('=')->value($id);
        }

        foreach ($properties as $key => $value) {

            if (is_null($value)) {

                $query->where()->condition()->name("n.{$key}")->operator('IS NULL');
            }
        }

        $query->return()->element('n');

        if (!is_null($skip))  $query->skip($skip);
        if (!is_null($limit)) $query->limit($limit);

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());

        /** @var CypherMap $record */
        foreach ($result as $record) {

            /** @var Node $node */
            $node = $record->get('n');

            yield new static([
                'id' => $node->getId(),
                ...$node->getProperties()->toArray()
            ]);
        }
    }

    public static final function first(int|string|null $id = null, array $properties = []): ?static {

        foreach (static::get($id, $properties, 0, 1) as $record) {

            return $record;
        }

        return null;
    }

    public static final function firstOrCreate(array $properties = []): ?static {

        return static::first(null, $properties) ?? static::create($properties);
    }

    public static final function delete(int|string|null $id = null, array $properties = []): void {

        $query = new QueryBuilder();

        $node = $query->match()->node()->alias('n')->label(static::getLabel());

        foreach ($properties as $key => $value) {

            if (!is_null($value)) {

                $node->property($key, $value);
            }
        }

        if (!is_null($id)) {

            $query->where()->condition()->name('id(n)')->operator('=')->value($id);
        }

        foreach ($properties as $key => $value) {

            if (is_null($value)) {

                $query->where()->condition()->name("n.{$key}")->operator('IS NULL');
            }
        }

        $query->delete()->element('n');

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());
    }
}
