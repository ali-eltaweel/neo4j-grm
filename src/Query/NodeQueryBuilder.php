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

    private string $labelClass;
    
    private array $labels;

    private array $properties;

    private ?int $id;
    
    protected QueryBuilder $query;

    public final function __construct() {
        
        $this->query = new QueryBuilder();
        
        $this->reset();
    }

    public final function forLabelClass(string $labelClass): self {

        if (!is_subclass_of($labelClass, Label::class)) {

            throw new \InvalidArgumentException(sprintf(
                'The class "%s" is not a subclass of "%s".',
                $labelClass,
                Label::class
            ));
        }
        $this->labelClass = $labelClass;

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

                $return->elements(array_map(fn (string $field) => "node.".$field, $fields));
                $return->element('id(node) AS id');
                $return->element('labels(node) AS labels');
            }
        });

        if (!is_null($skip)) {

            $this->query->skip($skip);
        }

        if (!is_null($limit)) {

            $this->query->limit($limit);
        }

        $result = $this->execute();

        /** @var CypherMap $record */
        foreach ($result as $record) {

            $nodeLabels = [];
            $nodeProperties = [];

            if (is_null($fields)) {

                /** @var Node $node */
                $node = $record->get('node');

                $nodeLabels     = $node->getLabels()->toArray();
                $nodeProperties = [ 'id' => $node->getId(), ...$node->getProperties()->toArray() ];

            } else {

                $nodeLabels = $record->get('labels')->toArray();

                foreach ($fields as $field) {

                    $nodeProperties[$field] = $record->get('node.'.$field);
                }

                $nodeProperties['id'] = $record->get('id');
            }

            $labelClass = $this->labelClass;

            $instance = new $labelClass($nodeProperties, $nodeLabels);

            yield $instance;
        }
    }

    public final function count(): int {

        $this->query->match()->node($this->setupNodeBuilder(...));

        if (!is_null($this->id)) {

            $this->query->where()->condition()->name('id(node)')->operator('=')->value($this->id);
        }

        $this->query->return()->element('count(node) AS count');

        return $this->execute()->first()->get('count');
    }

    public final function exist(): bool {

        $this->query->match()->node($this->setupNodeBuilder(...));

        if (!is_null($this->id)) {

            $this->query->where()->condition()->name('id(node)')->operator('=')->value($this->id);
        }

        $this->query->return()->element('count(node) AS count');

        return 0 < $this->execute()->first()->get('count');
    }

    public final function first(?array $fields = null): ?Label {

        foreach ($this->get($fields, 0, 1) as $node) {

            return $node;
        }

        return null;
    }

    public final function create(): Label {

        if (!is_null($this->id)) {

            throw new \LogicException('Cannot create a node with a predefined ID.');
        }

        $this->query->create()->node($this->setupNodeBuilder(...));

        $this->query->return()->element('node');

        /** @var Node $node */
        $node = $this->execute()->first()->get('node');

        $nodeLabels     = $node->getLabels()->toArray();
        $nodeProperties = [ 'id' => $node->getId(), ...$node->getProperties()->toArray() ];

        $labelClass = $this->labelClass;

        return new $labelClass($nodeProperties, $nodeLabels);
    }

    public function reset(): self {

        $this->labels     = [];
        $this->labelClass = Label::class;
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

        foreach ($this->getLabels() as $label) {

            $node->label($label);
        }

        $node->properties($this->properties);
    }

    private function getLabels(): array {

        $labels = $this->labels;

        if ($this->labelClass !== Label::class) {

            array_unshift($labels, $this->labelClass::getLabel());
        }

        return $labels;
    }
}
