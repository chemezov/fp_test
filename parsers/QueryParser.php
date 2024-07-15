<?php

namespace FpDbTest\parsers;

use FpDbTest\helpers\ArrayHelper;
use InvalidArgumentException;

class QueryParser implements QueryParserInterface
{
    public const string SKIP = '__SKIP__';

    private string $query;
    private array $args;

    public function __construct(string $query, array $args = [])
    {
        $this->query = $query;
        $this->args = $args;
    }

    /**
     * Returns rendered query string.
     *
     * @return string
     */
    public function render(): string
    {
        $regex = '#\?(d|f|a|\#)?#';

        $query = $this->query;
        $args = $this->args;

        $result = preg_replace_callback($regex, function ($matches) use (&$args) {
            $source = $matches[0];
            $target = array_shift($args);

            return $this->parseArg($target, $source);
        }, $query);

        $result = $this->removeSkippedBlocks($result);
        $result = $this->removeNonSkippedBlocks($result);

        return $result;
    }

    protected function removeSkippedBlocks($input): string
    {
        $pattern = '/\{[^{]*' . self::SKIP . '[^}]*\}/';

        return preg_replace($pattern, '', $input);
    }

    protected function removeNonSkippedBlocks($input): string
    {
        $pattern = '/\{([^{}]*)\}/';

        return preg_replace($pattern, '$1', $input);
    }

    protected function parseArg($arg, $type): string
    {
        if ($arg === self::SKIP) {
            return $arg;
        }

        return match ($type) {
            '?d' => $this->parseIntegerType($arg),
            '?f' => $this->parseFloatType($arg),
            '?#' => $this->parseIdentifierType($arg),
            default => $this->parseCommonType($arg),
        };
    }

    protected function parseIdentifierType($arg): string
    {
        return implode(', ', array_map(fn($value) => '`' . $value . '`', (array)$arg));
    }

    protected function parseCommonType($arg): string
    {
        if (!is_array($arg)) {
            return $this->parseScalarValue($arg);
        }

        $result = [];
        $isAssociative = ArrayHelper::isAssociative($arg);

        foreach ($arg as $key => $value) {
            $result[] = ($isAssociative ? "`$key` = " : '') . $this->parseScalarValue($value);
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
        return $arg ? '1' : '0';
    }

    protected function parseStringType($arg): string
    {
        return "'" . addslashes($arg) . "'";
    }
}
