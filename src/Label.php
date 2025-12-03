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

    private array $relationships = [];

    public function __get(string $name): mixed {

        if ($this->relationLoaded($name)) {

            return $this->relationships[$name];
        }

        if (method_exists($this, $name) && $this->$name() instanceof Relations\Relation) {

            $this->loadRelations([$name]);
            
            return $this->relationships[$name] ?? null;
        }

        return parent::__get($name);
    }

    public function toArray() {

        return array_merge(
            parent::toArray(),
            array_map(
                fn (Label|array $relation) => is_array($relation)
                    ? array_map(fn(Label $label) => $label->toArray(), $relation)
                    : $relation->toArray(),
                $this->relationships
            )
        );
    }

    public function all() {

        return array_merge(parent::all(), $this->relationships);
    }

    public final function relationLoaded(string $relationship): bool {

        return array_key_exists($relationship, $this->relationships);
    }

    public final function loadRelations(array $relationships, bool $forceReload = false): void {

        $relations = [];

        foreach ($relationships as $relationship) {

            if (array_key_exists($relationship, $this->relationships) && !$forceReload) {

                continue;
            }

            if (!method_exists($this, $relationship)) {

                throw new \RuntimeException("No relationship method '{$relationship}' defined in label '" . static::getLabel() . "'.");
            }

            if (!($relation = $this->$relationship()) instanceof Relations\Relation) {

                throw new \RuntimeException("No relationship method '{$relationship}' defined in label '" . static::getLabel() . "'.");
            }

            $relations[$relationship] = $relation;
        }
        
        $query = new QueryBuilder();

        foreach ($relations as $relationshipName => $relation) {

            $query->match()->relationship(function(RelationshipBuilder $rel) use ($relationshipName, $relation) {

                $rel->label($relation->name);
                $rel->from()->alias('this')->label(static::getLabel());
                $rel->to()->alias($relationshipName)->label($relation->relatedLabel::getLabel());
            });
        }

        $query->where()->condition()->name('id(this)')->operator('=')->value($this->id);

        $query->return()->elements(array_keys($relations));

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());

        /** @var CypherMap $record */
        $record = $result->first();

        foreach ($relations as $relationshipName => $relation) {

            /** @var Node $node */
            $node = $record->get($relationshipName);

            $relatedLabelClass = $relation->relatedLabel;

            $instance = new $relatedLabelClass([ 'id' => $node->getId(), ...$node->getProperties()->toArray() ]);

            if ($relation->multiple) {
            
                if (!array_key_exists($relationshipName, $this->relationships)) {

                    $this->relationships[$relationshipName] = [];
                }

                $this->relationships[$relationshipName][] = $instance;
            } else {

                $this->relationships[$relationshipName] = $instance;
            }
        }
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

    public static final function exist(int|string|null $id = null, array $properties = []): bool {

        return static::count($id, $properties) > 0;
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
