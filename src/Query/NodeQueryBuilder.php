<?php

namespace Neo4jGRM\Query;

use Generator;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Neo4jGRM\Entity;
use Neo4jGRM\Label;
use Neo4jQueryBuilder\Clauses\Return_;
use Neo4jQueryBuilder\NodeBuilder;
use Neo4jQueryBuilder\QueryBuilder;

class NodeQueryBuilder {

    private array $labelsMap;

    private array $labels;

    private array $properties;

    private ?int $id;
    
    protected QueryBuilder $query;

    public final function __construct() {
        
        $this->query = new QueryBuilder();
        
        $this->reset();
    }

    public final function addLabelClass(string $labelClass): self {

        if (!is_subclass_of($labelClass, Label::class)) {

            throw new \InvalidArgumentException("Class {$labelClass} is not a subclass of " . Label::class);
        }

        $this->labelsMap[ $labelClass::getLabel() ] = $labelClass;

        return $this;
    }
    
    public final function labeled(string ...$labels): self {

        $this->labels = array_unique(array_merge($this->labels, $labels));

        return $this;
    }

    public final function withProperty(string $key, mixed $value): self {

        if ($key === 'id') {

            $this->id = (int)$value;
        } else {

            $this->properties[$key] = $value;
        }

        return $this;
    }

    public final function withProperties(array $properties): self {

        foreach ($properties as $key => $value) {

            $this->withProperty($key, $value);
        }

        return $this;
    }

    public final function get(?array $fields = null, ?int $skip = null, ?int $limit = null): Generator {

        $this->query->match()->node($this->setupNodeBuilder(...));

        if (!is_null($this->id)) {

            $this->query->where()->condition()->name('id(node)')->operator('=')->value($this->id);
        }

        $this->query->return(function(Return_ $return) use ($fields) {

            if (is_null($fields)) {

                $return->element('node');
            } else {

                $return->elements(array_map(fn (string $field) => 'node.'.$field, $fields));
                $return->element('id(node) AS id');
            }
        });

        if (!is_null($skip)) {

            $this->query->skip($skip);
        }

        if (!is_null($limit)) {

            $this->query->limit($limit);
        }
dd(
    $this->query.'',
    $this->query->getParameters()
);
        $result = $this->execute();

        /** @var CypherMap $record */
        foreach ($result as $record) {

            $nodeProperties = [];

            if (is_null($fields)) {

                /** @var Node $node */
                $node = $record->get('node');

                $nodeProperties = [ 'id' => $node->getId(), ...$node->getProperties()->toArray() ];
            } else {

                foreach ($fields as $field) {

                    $nodeProperties[$field] = $record->get('node.'.$field);
                }

                $nodeProperties['id'] = $record->get('id');
            }

            $labelClass = $this->labelsMap[$this->labels[0] ?? null] ?? Label::class;

            yield new $labelClass($nodeProperties);
        }
    }

    public final function first(?array $fields = null): ?Label {

        foreach ($this->get($fields, 0, 1) as $node) {

            return $node;
        }

        return null;
    }

    public function reset(): self {

        $this->labelsMap = [];

        $this->labels     = [];
        $this->properties = [];
        $this->id         = null;

        $this->query->reset();

        return $this;
    }

    protected final function execute(): SummarizedResult {

        return Entity::getClient()->run($this->query, $this->query->getParameters());
    }

    private function setupNodeBuilder(NodeBuilder $node): void {

        $node->alias('node');

        foreach ($this->labels as $label) {

            $node->label($label);
        }

        $node->properties($this->properties);
    }
}
