<?php

namespace Neo4jGRM\Builder;

use Generator;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Relationship as TypesRelationship;
use Neo4jGRM\Models\Relationship as RelationshipModel;
use Neo4jQueryBuilder\Cypher\Clauses\Limit;
use Neo4jQueryBuilder\Cypher\Clauses\Match_;
use Neo4jQueryBuilder\Cypher\Clauses\Return_;
use Neo4jQueryBuilder\Cypher\Clauses\Skip;
use Neo4jQueryBuilder\Cypher\CypherQuery;
use Neo4jQueryBuilder\Cypher\Relationship;

/**
 * @template T of RelationshipModel
 * 
 * @extends QueryBuilder<T>
 */
class RelationshipQueryBuilder extends QueryBuilder {

    public function reset(): void {

        parent::reset();

        $this->alias = 'rel';
    }

    public final function get(?array $fields = null, ?int $skip = null, ?int $limit = null): Generator {

        $query = new CypherQuery();

        $query->addClause($match = new Match_());
        $match->addItem($this->createRelationshipCypher());

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
        $match->addItem($this->createRelationshipCypher());
    
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
