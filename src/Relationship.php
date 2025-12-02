<?php

namespace Neo4jGRM;

use Closure;
use Generator;
use InvalidArgumentException;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship as TypesRelationship;
use Neo4jQueryBuilder\Clauses\Match_;
use Neo4jQueryBuilder\Clauses\Where;
use Neo4jQueryBuilder\QueryBuilder;
use Neo4jQueryBuilder\RelationshipBuilder;

/**
 * @property-read ?Label $startingNode
 * @property-read ?Label $endingNode
 */
class Relationship extends Entity {

    private ?Label $startingNode;
    
    private ?Label $endingNode;

    public function __construct(private array $properties) {

        parent::__construct($properties);

        $this->startingNode = null;
        $this->endingNode   = null;
    }

    public function startingLabel(): ?string {

        return null;
    }

    public function endingLabel(): ?string {

        return null;
    }

    public function __get(string $property): mixed {

        if ($property === 'startingNode') {
    
            return $this->startingNode;
        }

        if ($property === 'endingNode') {
            
            return $this->endingNode;
        }

        return parent::__get($property);
    }

    public function __set(string $property, mixed $value): void {

        if (in_array($property, [ 'startingNode', 'endingNode' ])) {
            
            throw new InvalidArgumentException(sprintf('The "%s" property is read-only.', $property));
        }

        parent::__set($property, $value);
    }

    public static final function get(
        
        int|string|null $id = null,
        array $properties = [],
        ?int $skip = null,
        ?int $limit = null,
        null|Label|Closure $start = null,
        null|Label|Closure $end = null,
    ): Generator {

        $query = new QueryBuilder();

        $query->match()->relationship(function(RelationshipBuilder $relationship) use ($properties) {

            $relationship->alias('rel')->label(static::getLabel())->properties($properties);
            $relationship->from()->alias('start')->label((new static([]))->startingLabel()::getLabel());
            $relationship->to()->alias('end')->label((new static([]))->endingLabel()::getLabel());
        });

        if (!is_null($id)) {

            $query->where()->condition()->name('id(rel)')->operator('=')->value($id);
        }

        if (!is_null($start ?? $end)) {

            $query->where(function(Where $where) use ($start, $end) {

                if ($start instanceof Label) {

                    $where->condition()->name('id(start)')->operator('=')->value($start->id);
                }

                if (is_callable($start)) {

                    $start($where);
                }

                if ($end instanceof Label) {

                    $where->condition()->name('id(end)')->operator('=')->value($end->id);
                }
                
                if (is_callable($end)) {

                    $end($where);
                }
            });
        }

        $query->return()->elements(['start', 'rel', 'end']);

        if (!is_null($skip))  $query->skip($skip);
        if (!is_null($limit)) $query->limit($limit);

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());

        /** @var CypherMap $record */
        foreach ($result as $record) {

            /** @var Node $relationship */
            $relationship = $record->get('rel');
            
            /** @var Node $start */
            $start = $record->get('start');

            /** @var Node $end */
            $end = $record->get('end');

            $instance = new static([
                'id' => $relationship->getId(),
                ...$relationship->getProperties()->toArray()
            ]);

            if (!is_null($labelClass = $instance->startingLabel())) {

                $instance->startingNode = new $labelClass([
                    'id' => $start->getId(),
                    ...$start->getProperties()->toArray()
                ]);
            }

            if (!is_null($labelClass = $instance->endingLabel())) {

                $instance->endingNode = new $labelClass([
                    'id' => $end->getId(),
                    ...$end->getProperties()->toArray()
                ]);
            }

            yield $instance;
        }
    }

    public static final function first(int|string|null $id = null, array $properties = [], null|Label|Closure $start = null, null|Label|Closure $end = null): ?static {

        foreach (static::get(id: $id, properties: $properties, limit: 1, start: $start, end: $end) as $record) {

            return $record;
        }

        return null;
    }

    public static final function create(Label|Closure $start, Label|Closure $end): static {

        $query = new QueryBuilder();

        $query->match(function(Match_ $match) {
            $match->node()->alias('start');
            $match->node()->alias('end');
        });

        $query->where(function(Where $where) use ($start, $end) {

            if ($start instanceof Label) {

                $where->condition()->name('id(start)')->operator('=')->value($start->id);
            } else {

                $start($where);
            }

            if ($end instanceof Label) {

                $where->condition()->name('id(end)')->operator('=')->value($end->id);
            } else {

                $end($where);
            }
        });

        $query->create()->relationship(function(RelationshipBuilder $rel) {

            $rel->alias('rel');
            $rel->from()->alias('start');
            $rel->label(static::getLabel());
            $rel->to()->alias('end');
        });

        $query->return()->element('rel');

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query, $query->getParameters());

        /** @var CypherMap $cypherMap */
        $cypherMap = $result->first();

        /** @var TypesRelationship $relationship */
        $relationship = $cypherMap->get($cypherMap->key());

        /** @var CypherMap $properties */
        $properties = $relationship->getProperties();

        return new static([
            'id' => $relationship->getId(),
            ...$properties->toArray()
        ]);
    }

    public static final function firstOrCreate(Label|Closure $start, Label|Closure $end): ?static {

        return static::first(start: $start, end: $end) ?? static::create(start: $start, end: $end);
    }
}