<?php

namespace Neo4jGRM\Builder;

use Generator;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node as TypesNode;
use Neo4jGRM\Models\Node as NodeModel;
use Neo4jQueryBuilder\Cypher\Clauses\Limit;
use Neo4jQueryBuilder\Cypher\Clauses\Match_;
use Neo4jQueryBuilder\Cypher\Clauses\Return_;
use Neo4jQueryBuilder\Cypher\Clauses\Skip;
use Neo4jQueryBuilder\Cypher\Clauses\Where;
use Neo4jQueryBuilder\Cypher\CypherQuery;
use Neo4jQueryBuilder\Cypher\Node;

/**
 * @template T of NodeModel
 * 
 * @extends QueryBuilder<T>
 */
class NodeQueryBuilder extends QueryBuilder {

    public function reset(): void {

        parent::reset();

        $this->alias = 'node';
    }

    public final function get(?array $fields = null, ?int $skip = null, ?int $limit = null): Generator {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($this->createNodeCypher());

        if (!is_null($this->wherePredicate)) {

            $query->addClause(new Where($this->createPredicate($this->wherePredicate)));
        }
    
        $query->addClause($return = new Return_());
        if (is_null($fields)) {
            $return->addItem($this->alias);
        } else {
            foreach ($fields as $field) {

                $return->addItem(sprintf('%s.%s', $this->alias, $field));
            }

            $return->addItem(sprintf('id(%s) AS __id', $this->alias));
            $return->addItem(sprintf('labels(%s) AS __labels', $this->alias));
        }

        if (!is_null($skip))  $query->addClause(new Skip($skip));
        if (!is_null($limit)) $query->addClause(new Limit($limit));

        $result = $this->execute($query);

        /** @var CypherMap $record */
        foreach ($result as $record) {

            $nodeId         = null;
            $nodeLabels     = [];
            $nodeProperties = [];

            if (is_null($fields)) {

                /** @var TypesNode $node */
                $node = $record->get($this->alias);

                $nodeId         = $node->getId();
                $nodeLabels     = $node->getLabels()->toArray();
                $nodeProperties = static::mapToArray($node->getProperties());

            } else {

                $nodeId     = $record->get('__id');
                $nodeLabels = $record->get('__labels')->toArray();

                foreach ($fields as $field) {

                    $nodeProperties[$field] = $record->get(sprintf('%s.%s', $this->alias, $field));
                }
            }

            $nodeClass = $this->entityClass ?? NodeModel::class;

            $instance = new $nodeClass($nodeId, static::mapToArray($nodeProperties), $nodeLabels);

            yield $instance;
        }
    }

    public final function count(): int {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($this->createNodeCypher());

        if (!is_null($this->wherePredicate)) {

            $query->addClause(new Where($this->createPredicate($this->wherePredicate)));
        }

        $query->addClause(new Return_(
            sprintf('count(%s) as count', $this->alias)
        ));

        return $this->execute($query)->first()->get('count');
    }

    private function createNodeCypher(): Node {

        $node = new Node($this->alias);

        $labels = $this->labels;

        if (!is_null($class = $this->entityClass) && !in_array($label = $class::getLabel(), $labels)) {

            array_unshift($labels, $label);
        }

        foreach ($labels as $label) {

            $node->addLabel($class::getLabel());
        }

        $node->properties->addAll($this->properties);

        return $node;
    }
}
