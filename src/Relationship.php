<?php

namespace Neo4jGRM;

use Generator;
use InvalidArgumentException;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
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

    public static final function get(int|string|null $id = null, array $properties = [], ?int $skip = null, ?int $limit = null): Generator {

        $query = new QueryBuilder();

        $query->match()->relationship(function(RelationshipBuilder $relationship) use ($properties) {

            $relationship->alias('rel')->label(static::getLabel())->properties($properties);
            $relationship->from()->alias('from');
            $relationship->to()->alias('to');
        });

        if (!is_null($id)) {

            $query->where()->condition()->name('id(rel)')->operator('=')->value($id);
        }

        $query->return()->elements(['from', 'rel', 'to']);

        if (!is_null($skip))  $query->skip($skip);
        if (!is_null($limit)) $query->limit($limit);

        /** @var SummarizedResult $result */
        $result = static::getClient()->run($query);

        /** @var CypherMap $record */
        foreach ($result as $record) {

            /** @var Node $relationship */
            $relationship = $record->get('rel');
            
            /** @var Node $from */
            $from = $record->get('from');

            /** @var Node $to */
            $to = $record->get('to');

            $instance = new static([
                'id' => $relationship->getId(),
                ...$relationship->getProperties()->toArray()
            ]);

            if (!is_null($labelClass = $instance->startingLabel())) {

                $instance->startingNode = new $labelClass([
                    'id' => $from->getId(),
                    ...$from->getProperties()->toArray()
                ]);
            }

            if (!is_null($labelClass = $instance->endingLabel())) {

                $instance->endingNode = new $labelClass([
                    'id' => $to->getId(),
                    ...$to->getProperties()->toArray()
                ]);
            }

            yield $instance;
        }
    }
}