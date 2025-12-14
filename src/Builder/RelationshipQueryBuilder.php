<?php

namespace Neo4jGRM\Builder;

use Generator;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Relationship as TypesRelationship;
use Neo4jGRM\Models\Relationship as RelationshipModel;
use Neo4jQueryBuilder\Cypher\Clauses\Create;
use Neo4jQueryBuilder\Cypher\Clauses\Delete;
use Neo4jQueryBuilder\Cypher\Clauses\Limit;
use Neo4jQueryBuilder\Cypher\Clauses\Match_;
use Neo4jQueryBuilder\Cypher\Clauses\Return_;
use Neo4jQueryBuilder\Cypher\Clauses\Set;
use Neo4jQueryBuilder\Cypher\Clauses\Skip;
use Neo4jQueryBuilder\Cypher\Clauses\Where;
use Neo4jQueryBuilder\Cypher\CypherQuery;
use Neo4jQueryBuilder\Cypher\Predicates\Boolean\AndPredicate;
use Neo4jQueryBuilder\Cypher\Relationship;

/**
 * @template T of RelationshipModel
 * 
 * @extends QueryBuilder<T>
 */
class RelationshipQueryBuilder extends QueryBuilder {

    private NodeQueryBuilder $leftNode, $rightNode;

    private bool $leftToRight;

    public function reset(): void {

        parent::reset();

        $this->alias       = 'rel';
        $this->leftNode    = (new NodeQueryBuilder())->alias('left');
        $this->rightNode   = (new NodeQueryBuilder())->alias('right');
        $this->leftToRight = true;
    }

    public final function leftNode(): NodeQueryBuilder {

        return $this->leftNode;
    }

    public final function rightNode(): NodeQueryBuilder {

        return $this->rightNode;
    }

    public final function leftToRight(bool $value = true): self {

        $this->leftToRight = $value;

        return $this;
    }

    public final function get(?array $fields = null, ?int $skip = null, ?int $limit = null): Generator {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($relationship = $this->createRelationshipCypher());
        $this->leftNode->createNodeCypher($relationship->left);
        $this->rightNode->createNodeCypher($relationship->right);
        $relationship->leftToRight($this->leftToRight);

        $predicates = [];

        if (!is_null($this->wherePredicate)) {

            $predicates[] = $this->createPredicate($this->wherePredicate);
        }

        if (!is_null($this->leftNode->wherePredicate)) {

            $predicates[] = $this->leftNode->createPredicate($this->leftNode->wherePredicate);
        }

        if (!is_null($this->rightNode->wherePredicate)) {

            $predicates[] = $this->rightNode->createPredicate($this->rightNode->wherePredicate);
        }
        
        if (!empty($predicates)) {

            if (count($predicates) === 1) {

                $query->addClause(new Where($predicates[0]));
            } else {
                
                $query->addClause(new Where(new AndPredicate(...$predicates)));
            }
        }

        $query->addClause($return = new Return_());
        if (is_null($fields)) {
            $return->addItem($this->alias);
        } else {
            foreach ($fields as $field) {

                $return->addItem(sprintf('%s.%s', $this->alias, $field));
            }

            $return->addItem(sprintf('id(%s) AS __id', $this->alias));
            $return->addItem(sprintf('type(%s) AS __label', $this->alias));
        }

        if (!is_null($skip))  $query->addClause(new Skip($skip));
        if (!is_null($limit)) $query->addClause(new Limit($limit));

        $result = $this->execute($query);

        /** @var CypherMap $record */
        foreach ($result as $record) {

            $relationshipId         = null;
            $relationshipLabels     = [];
            $relationshipProperties = [];

            if (is_null($fields)) {

                /** @var TypesRelationship $relationship */
                $relationship = $record->get($this->alias);

                $relationshipId         = $relationship->getId();
                $relationshipLabels     = [ $relationship->getType() ];
                $relationshipProperties = static::mapToArray($relationship->getProperties());
            } else {

                $relationshipId     = $record->get('__id');
                $relationshipLabels = [ $record->get('__label') ];

                foreach ($fields as $field) {

                    $relationshipProperties[$field] = $record->get(sprintf('%s.%s', $this->alias, $field));
                }
            }

            $relationshipClass = $this->entityClass ?? RelationshipModel::class;

            $instance = new $relationshipClass($relationshipId, static::mapToArray($relationshipProperties), $relationshipLabels);

            yield $instance;
        }
    }

    public final function count(): int {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($relationship = $this->createRelationshipCypher());
        $this->leftNode->createNodeCypher($relationship->left);
        $this->rightNode->createNodeCypher($relationship->right);
        $relationship->leftToRight($this->leftToRight);

        if (!is_null($this->wherePredicate)) {

            $query->addClause(new Where($this->createPredicate($this->wherePredicate)));
        }
    
        $query->addClause(new Return_(
            sprintf('count(%s) as count', $this->alias)
        ));

        return $this->execute($query)->first()->get('count');
    }

    public final function create(): RelationshipModel {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($this->leftNode->createNodeCypher());
        $match->addItem($this->rightNode->createNodeCypher());

        $predicates = [];

        if (!is_null($this->wherePredicate)) {

            $predicates[] = $this->createPredicate($this->wherePredicate);
        }

        if (!is_null($this->leftNode->wherePredicate)) {

            $predicates[] = $this->leftNode->createPredicate($this->leftNode->wherePredicate);
        }

        if (!is_null($this->rightNode->wherePredicate)) {

            $predicates[] = $this->rightNode->createPredicate($this->rightNode->wherePredicate);
        }
        
        if (!empty($predicates)) {

            if (count($predicates) === 1) {

                $query->addClause(new Where($predicates[0]));
            } else {
                
                $query->addClause(new Where(new AndPredicate(...$predicates)));
            }
        }

        $query->addClause($create = new Create());
        $create->addItem($relationship = $this->createRelationshipCypher());
        $relationship->left->alias($this->leftNode->alias);
        $relationship->right->alias($this->rightNode->alias);
        $relationship->leftToRight($this->leftToRight);

        $query->addClause(new Return_($this->alias));

        $result = $this->execute($query);

        /** @var TypesRelationship $relationship */
        $relationship = $result->first()->get($this->alias);

        $relationshipId         = $relationship->getId();
        $relationshipLabels     = [ $relationship->getType() ];
        $relationshipProperties = static::mapToArray($relationship->getProperties());

        $relationshipClass = $this->entityClass ?? RelationshipModel::class;

        return new $relationshipClass($relationshipId, static::mapToArray($relationshipProperties), $relationshipLabels);
    }

    public final function delete(): int {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($relationship = $this->createRelationshipCypher());
        $this->leftNode->createNodeCypher($relationship->left);
        $this->rightNode->createNodeCypher($relationship->right);
        $relationship->leftToRight($this->leftToRight);

        $predicates = [];

        if (!is_null($this->wherePredicate)) {

            $predicates[] = $this->createPredicate($this->wherePredicate);
        }

        if (!is_null($this->leftNode->wherePredicate)) {

            $predicates[] = $this->leftNode->createPredicate($this->leftNode->wherePredicate);
        }

        if (!is_null($this->rightNode->wherePredicate)) {

            $predicates[] = $this->rightNode->createPredicate($this->rightNode->wherePredicate);
        }
        
        if (!empty($predicates)) {

            if (count($predicates) === 1) {

                $query->addClause(new Where($predicates[0]));
            } else {
                
                $query->addClause(new Where(new AndPredicate(...$predicates)));
            }
        }

        $query->addClause(new Delete($this->alias));
        
        $query->addClause(new Return_(
            sprintf('count(%s) as count', $this->alias)
        ));

        return $this->execute($query)->first()->get('count');
    }

    public final function update(array $properties): int {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($relationship = $this->createRelationshipCypher());
        $this->leftNode->createNodeCypher($relationship->left);
        $this->rightNode->createNodeCypher($relationship->right);
        $relationship->leftToRight($this->leftToRight);

        $predicates = [];

        if (!is_null($this->wherePredicate)) {

            $predicates[] = $this->createPredicate($this->wherePredicate);
        }

        if (!is_null($this->leftNode->wherePredicate)) {

            $predicates[] = $this->leftNode->createPredicate($this->leftNode->wherePredicate);
        }

        if (!is_null($this->rightNode->wherePredicate)) {

            $predicates[] = $this->rightNode->createPredicate($this->rightNode->wherePredicate);
        }
        
        if (!empty($predicates)) {

            if (count($predicates) === 1) {

                $query->addClause(new Where($predicates[0]));
            } else {
                
                $query->addClause(new Where(new AndPredicate(...$predicates)));
            }
        }

        $query->addClause($set = new Set());
        
        foreach ($properties as $field => $value) {

            $set->property($this->alias, $field, $value);
        }
        
        $query->addClause(new Return_(
            sprintf('count(%s) as count', $this->alias)
        ));

        return $this->execute($query)->first()->get('count');
    }

    private function createRelationshipCypher(): Relationship {

        $relationship = new Relationship($this->alias);

        $labels = $this->labels;

        if (!is_null($class = $this->entityClass) && !in_array($label = $class::getLabel(), $labels)) {

            array_unshift($labels, $label);
        }

        foreach ($labels as $label) {

            $relationship->addLabel($class::getLabel());
        }

        $relationship->properties->addAll($this->properties);

        return $relationship;
    }
}
