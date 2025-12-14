<?php

namespace Neo4jGRM\Builder;

use Generator;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\CypherSequence;
use Laudis\Neo4j\Databags\SummarizedResult;
use Neo4jGRM\Models\GraphEntity;
use Neo4jQueryBuilder\Cypher\CypherQuery;
use Neo4jQueryBuilder\Cypher\Predicates\Boolean\AndPredicate;
use Neo4jQueryBuilder\Cypher\Predicates\Boolean\NotPredicate;
use Neo4jQueryBuilder\Cypher\Predicates\Boolean\OrPredicate;
use Neo4jQueryBuilder\Cypher\Predicates\Comparison as ComparisonPredicates;
use Neo4jQueryBuilder\Cypher\Predicates\Predicate;

/**
 * @template T of GraphEntity
 */
abstract class QueryBuilder {

    protected ?string $alias;
    
    protected ?string $entityClass;

    protected array $labels;

    protected ?int $id;

    protected array $properties;

    protected ?array $wherePredicate;

    public function __construct() {
        
        $this->reset();
    }

    public function reset(): void {
        
        $this->alias        = null;
        $this->entityClass  = null;
        $this->labels       = [];
        $this->id           = null;
        $this->properties   = [];
        $this->wherePredicate    = null;
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

        $this->properties[$key] = $value;

        return $this;
    }

    public final function addProperties(array $properties): static {

        foreach ($properties as $key => $value) {

            $this->addProperty($key, $value);
        }

        return $this;
    }

    public final function where(string|callable $field, mixed $operator = null, mixed $value = null): static {

        if (func_num_args() === 2) {

            $value    = $operator;
            $operator = '=';
        }

        if (func_num_args() === 1) {

            $predicate = [ 'type' => 'nested', 'closure' => $field ];
        } else {
            
            $predicate = [
                'type'     => 'comparison',
                'field'    => $field,
                'operator' => $operator,
                'value'    => $value
            ];
        }

        if (is_null($this->wherePredicate)) {

            $this->wherePredicate = $predicate;
        } else if ($this->wherePredicate['type'] === 'and') {

            $this->wherePredicate = [
                'type' => 'and', 'predicates' => [ ...$this->wherePredicate['predicates'], $predicate ]
            ];
        } else {

            $this->wherePredicate = [
                'type' => 'and', 'predicates' => [ $this->wherePredicate, $predicate ]
            ];
        }

        return $this;
    }

    public final function orWhere(string|callable $field, mixed $operator = null, mixed $value = null): static {

        if (func_num_args() === 2) {

            $value    = $operator;
            $operator = '=';
        }

        if (func_num_args() === 1) {

            $predicate = [ 'type' => 'nested', 'closure' => $field ];
        } else {
            
            $predicate = [
                'type'     => 'comparison',
                'field'    => $field,
                'operator' => $operator,
                'value'    => $value
            ];
        }

        if (is_null($this->wherePredicate)) {

            $this->wherePredicate = $predicate;
        } else if ($this->wherePredicate['type'] === 'or') {

            $this->wherePredicate = [
                'type' => 'or', 'predicates' => [ ...$this->wherePredicate['predicates'], $predicate ]
            ];
        } else {

            $this->wherePredicate = [
                'type' => 'or', 'predicates' => [ $this->wherePredicate, $predicate ]
            ];
        }

        return $this;
    }

    public final function whereNot(string|callable $field, mixed $operator = null, mixed $value = null): static {

        if (func_num_args() === 2) {

            $value    = $operator;
            $operator = '=';
        }

        if (func_num_args() === 1) {

            $predicate = [
                'type'      => 'not',
                'predicate' => [ 'type' => 'nested', 'closure' => $field ]
            ];
        } else {
            
            $predicate = [
                'type'      => 'not',
                'predicate' => [
                    'type'     => 'comparison',
                    'field'    => $field,
                    'operator' => $operator,
                    'value'    => $value
                ]
            ];
        }

        if (is_null($this->wherePredicate)) {

            $this->wherePredicate = $predicate;
        } else if ($this->wherePredicate['type'] === 'and') {

            $this->wherePredicate = [
                'type' => 'and', 'predicates' => [ ...$this->wherePredicate['predicates'], $predicate ]
            ];
        } else {

            $this->wherePredicate = [
                'type' => 'and', 'predicates' => [ $this->wherePredicate, $predicate ]
            ];
        }

        return $this;
    }

    public final function orWhereNot(string|callable $field, mixed $operator = null, mixed $value = null): static {

        if (func_num_args() === 2) {

            $value    = $operator;
            $operator = '=';
        }

        if (func_num_args() === 1) {

            $predicate = [
                'type'      => 'not',
                'predicate' => [ 'type' => 'nested', 'closure' => $field ]
            ];
        } else {
            
            $predicate = [
                'type'      => 'not',
                'predicate' => [
                    'type'     => 'comparison',
                    'field'    => $field,
                    'operator' => $operator,
                    'value'    => $value
                ]
            ];
        }

        if (is_null($this->wherePredicate)) {

            $this->wherePredicate = $predicate;
        } else if ($this->wherePredicate['type'] === 'or') {

            $this->wherePredicate = [
                'type' => 'or', 'predicates' => [ ...$this->wherePredicate['predicates'], $predicate ]
            ];
        } else {

            $this->wherePredicate = [
                'type' => 'or', 'predicates' => [ $this->wherePredicate, $predicate ]
            ];
        }

        return $this;
    }

    public final function whereId(int $value, string $operator = '='): static {

        return $this->where('id(%s)', $operator, $value);
    }

    /** @return ?T */
    public final function first(?array $fields = null, ?int $skip = null): ?GraphEntity {

        foreach ($this->get($fields, $skip, 1) as $node) {

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

    protected final function createPredicate(array $config): Predicate {

        switch ($config['type']) {

            case 'comparison':
                
                $predicateClass = match ($config['operator']) {
                    '='           => ComparisonPredicates\Equals::class,
                    '>'           => ComparisonPredicates\GreaterThan::class,
                    '>='          => ComparisonPredicates\GreaterThanOrEquals::class,
                    'IS NOT NULL' => ComparisonPredicates\IsNotNull::class,
                    'IS NULL'     => ComparisonPredicates\IsNull::class,
                    '<'           => ComparisonPredicates\LessThan::class,
                    '<='          => ComparisonPredicates\LessThanOrEquals::class,
                    '<>'          => ComparisonPredicates\NotEquals::class,
                };

                $field = $config['field'];
                if (strpos($field, '(') !== false) {

                    $lhs = sprintf($config['field'], $this->alias);
                } else {
                    $lhs = sprintf('%s.%s', $this->alias, $config['field']);
                }

                return new $predicateClass($lhs, $config['value']);

            case 'nested':

                $builder = clone $this;
                $builder->reset();

                $config['closure']($builder);

                if (is_null($builder->wherePredicate)) {

                    return new AndPredicate();
                }

                return $this->createPredicate($builder->wherePredicate);

            case 'and':

                return new AndPredicate(
                    ...array_map($this->createPredicate(...), $config['predicates'])
                );

            case 'or':

                return new OrPredicate(
                    ...array_map($this->createPredicate(...), $config['predicates'])
                );

            case 'not':

                return new NotPredicate(
                    $this->createPredicate($config['predicate'])
                );

            default:
                dd(
                    __METHOD__,
                    $config['type']
                );
        }
    }

    protected static final function mapToArray(array|CypherSequence $seq): array {

        return array_map(
            fn (mixed $value) => $value instanceof CypherSequence ? self::mapToArray($value) :$value,
            is_array($seq) ? $seq : $seq->toArray()
        );
    }
}
