<?php

namespace FpDbTest\parsers;

use mysqli;

interface QueryParserInterface
{
    public function __construct(mysqli $mysqli);

    public function render(string $query, array $args = []): string;
}
