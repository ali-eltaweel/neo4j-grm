<?php

namespace Neo4jGRM\Relations;

class Relation {

    public function __construct(
        public readonly string    $name,
        public readonly string    $relatedLabel,
        public readonly Direction $direction = Direction::OUTGOING,
        public readonly bool      $multiple  = true
    ) {}
}
