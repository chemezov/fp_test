<?php

namespace FpDbTest\parsers;

interface QueryParserInterface
{
    public function __construct(string $query, array $args = []);

    public function render(): string;
}
