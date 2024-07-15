<?php

namespace FpDbTest;

use FpDbTest\parsers\QueryParser;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        return (new QueryParser($query, $args))->render();
    }

    public function skip()
    {
        return QueryParser::SKIP;
    }
}
