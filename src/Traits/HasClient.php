<?php

namespace Neo4jGRM\Traits;

use Closure;
use Laudis\Neo4j\Client;
use RuntimeException;

trait HasClient {

    private static ?Closure $clientResolver = null;

    protected static string $connection = 'default';

    public static final function setClientResolver(Closure $resolver): void {
        
        self::$clientResolver = $resolver;
    }

    public static final function getClient(): Client {

        if (is_null($clientResolver = self::$clientResolver)) {
            
            throw new RuntimeException('Client resolver is not set.');
        }

        return $clientResolver(static::$connection);
    }
}
