<?php

namespace Neo4jGRM\Builder;

use Generator;
use InvalidArgumentException;
use Laudis\Neo4j\Databags\SummarizedResult;
use Neo4jGRM\Models\GraphEntity;
use Neo4jQueryBuilder\Cypher\CypherQuery;

/**
 * @template T of GraphEntity
 */
abstract class QueryBuilder {

    protected ?string $alias;
    
    protected ?string $entityClass;

    protected array $labels;

    protected ?int $id;

    protected array $properties;

    public function __construct() {
        
        $this->reset();
    }

    public function reset(): void {
        
        $this->alias        = null;
        $this->entityClass  = null;
        $this->labels       = [];
        $this->id           = null;
        $this->properties   = [];
    }

    public final function alias(string $alias): static {

        $this->alias = $alias;

        return $this;
    }

    public final function entity(string $class): static {

        if (!is_subclass_of($class, GraphEntity::class)) {

            throw new InvalidArgumentException("{$class} is not a valid graph entity class");
        }

        $this->entityClass = $class;

        return $this;
    }

    public final function addLabel(string $label): static {

        if (!in_array($label, $this->labels)) {

            $this->labels[] = $label;
        }

        return $this;
    }

    public final function addProperty(string $key, mixed $value): static {

        if ($key === 'id') {

            $this->id = (int)$value;
        } else {

            $this->properties[$key] = $value;
        }

        return $this;
    }

    public final function addProperties(array $properties): self {

        foreach ($properties as $key => $value) {

            $this->addProperty($key, $value);
        }

        return $this;
    }

    /** @return ?T */
    public final function first(?array $fields = null, ?int $skip = null, ?int $limit = null): ?GraphEntity {

        foreach ($this->get($fields, $skip, $limit) as $node) {

            return $node;
        }

        return null;
    }
    
    public abstract function count(): int;

    /** @return Generator<T> */
    public abstract function get(?array $fields = null, ?int $skip = null, ?int $limit = null): Generator;

    protected final function execute(CypherQuery $query): SummarizedResult {

        return GraphEntity::getClient()->run(
            $query->getQueryString(),
            $query->getParameters()
        );
    }
}
