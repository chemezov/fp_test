<?php

namespace FpDbTest;

use FpDbTest\parsers\QueryParser;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private QueryParser $queryParser;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->queryParser = new QueryParser($this->mysqli);
    }

    public function buildQuery(string $query, array $args = []): string
    {
        return $this->queryParser->render($query, $args);
    }

    public function skip()
    {
        return $this->queryParser->skip();
    }
}
