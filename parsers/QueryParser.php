<?php

namespace FpDbTest\parsers;

use FpDbTest\helpers\ArrayHelper;
use InvalidArgumentException;
use mysqli;

class QueryParser implements QueryParserInterface
{
    private mysqli $mysqli;
    public readonly QueryParserSkipBlock $queryParserSkipBlock;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;

        $this->queryParserSkipBlock = new QueryParserSkipBlock();
    }

    public function skip()
    {
        return $this->queryParserSkipBlock->skip();
    }

    /**
     * Returns rendered query string.
     *
     * @param string $query
     * @param array $args
     * @return string
     */
    public function render(string $query, array $args = []): string
    {
        $regex = '#\?(d|f|a|\#)?#';

        $this->removeSkippedBlocks($query, $args);

        $result = preg_replace_callback($regex, function ($matches) use (&$args) {
            $source = $matches[0];
            $target = array_shift($args);

            return $this->parseArg($target, $source);
        }, $query);

        return $result;
    }

    protected function removeSkippedBlocks(&$query, &$args): void
    {
        if (substr_count($query, '{') !== substr_count($query, '}')) {
            throw new InvalidArgumentException('The number of opening and closing brackets does not match');
        }

        $this->queryParserSkipBlock->removeSkippedBlocks($query, $args);
    }

    protected function parseArg($arg, $type): string
    {
        return match ($type) {
            '?d' => $this->parseIntegerType($arg),
            '?f' => $this->parseFloatType($arg),
            '?#' => $this->parseIdentifierType($arg),
            '?a' => $this->parseArrayType($arg),
            default => $this->parseCommonType($arg),
        };
    }

    protected function parseIdentifierType($arg): string
    {
        return implode(', ', array_map(fn($value) => '`' . $value . '`', (array)$arg));
    }

    protected function parseArrayType($arg): string
    {
        if (!is_array($arg)) {
            throw new InvalidArgumentException('Argument must be an array');
        }

        $result = [];
        $isAssociative = ArrayHelper::isAssociative($arg);

        foreach ($arg as $key => $value) {
            $result[] = ($isAssociative ? "`$key` = " : '') . $this->parseScalarValue($value);
        }

        return implode(', ', $result);
    }

    protected function parseCommonType($arg): string
    {
        if (!is_array($arg)) {
            return $this->parseScalarValue($arg);
        }

        $result = [];

        foreach ($arg as $value) {
            $result[] = $this->parseScalarValue($value);
        }

        return implode(', ', $result);
    }

    protected function parseScalarValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_integer($value)) {
            return $this->parseIntegerType($value);
        }

        if (is_float($value)) {
            return $this->parseFloatType($value);
        }

        if (is_bool($value)) {
            return $this->parseBooleanType($value);
        }

        if (is_string($value)) {
            return $this->parseStringType($value);
        }

        throw new InvalidArgumentException('Invalid scalar value.');
    }

    protected function parseIntegerType($arg): string
    {
        return (string)(int)$arg;
    }

    protected function parseFloatType($arg): string
    {
        return str_replace(',', '.', (float)$arg);
    }

    protected function parseBooleanType($arg): string
    {
        if (!is_bool($arg)) {
            throw new InvalidArgumentException('Invalid boolean value.');
        }

        return $arg ? '1' : '0';
    }

    protected function parseStringType($arg): string
    {
        return "'" . $this->mysqli->real_escape_string($arg) . "'";
    }
}
