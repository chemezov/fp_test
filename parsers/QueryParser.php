<?php

namespace FpDbTest\parsers;

use FpDbTest\helpers\ArrayHelper;
use InvalidArgumentException;
use mysqli;

class QueryParser implements QueryParserInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function skip()
    {
        return SpecialMarker::SKIP;
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
            // Если кол-во аргументов меньше, чем кол-во спецификаторов
            if (count($args) === 0) {
                throw new InvalidArgumentException('Not enough arguments provided.');
            }

            $source = $matches[0];
            $target = array_shift($args);

            return $this->parseArg($target, $source);
        }, $query);

        if (count($args) > 0) {
            throw new InvalidArgumentException('More than needed arguments provided.');
        }

        return $result;
    }

    function removeSkippedBlocks(string &$query, array &$args = []): void
    {
        $args = array_values($args);

        $positions = [];

        // Найдём все позиции спецификаторов
        if (preg_match_all('/\?/', $query, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $positions[] = $match[1];
            }
        }

        // Проходим строку с конца и пытаемся найти логические блоки
        $i = strlen($query) - 1;

        while ($i >= 0) {
            $char = $query[$i];

            // Если логический блок открывается раньше, чем была найдена закрывающаяся скобка.
            if ($char === '{') {
                throw new InvalidArgumentException('Invalid braces order.');
            }

            if ($char === '}') {
                $bracesEnd = $i;
                $bracesStart = strrpos($query, '{', $bracesEnd - strlen($query) - 1);

                // Проверим, что текущий блок закрылся
                if ($bracesStart === false) {
                    throw new InvalidArgumentException('Unclosed braces in query');
                }

                // Проверим, что следующий блок закрывается не раньше, чем открылся текущий
                $nextBracesEnd = strrpos($query, '}', $bracesEnd - strlen($query) - 1);

                if ($nextBracesEnd > $bracesStart) {
                    throw new InvalidArgumentException('Unclosed braces in query');
                }

                // Проверяем текущий блок на наличие спецификатора
                $specifiersPositionsInBlock = array_filter($positions, fn($pos) => $pos > $bracesStart && $pos < $bracesEnd);

                // Спецификатор должен быть ровно 1 в текущем блоке
                if (count($specifiersPositionsInBlock) !== 1) {
                    throw new InvalidArgumentException('Wrong specifiers count to logical braces');
                }

                // Получим порядковый номер нашего спецификатора внутри логического блока и его значение
                $specifierIndex = array_keys($specifiersPositionsInBlock)[0];
                $arg = $args[$specifierIndex];

                // Если текущий блок нужно удалить
                if ($arg === $this->skip()) {
                    $query = substr_replace($query, '', $bracesStart, $bracesEnd - $bracesStart + 1);
                    unset($args[$specifierIndex]);
                } else {
                    // Если же текущий блок удалять не надо, то просто удалим скобки и передвинем итератор
                    $query = substr_replace($query, '', $bracesEnd, 1);
                    $query = substr_replace($query, '', $bracesStart, 1);
                }

                // Следующую итерацию начинаем от текущей открывающейся скобки
                $i = $bracesStart - 1;
                continue;
            }

            $i--;
        }

        // В конце мы должны проверить не осталось ли SKIP аргументов вне логических скобок.
        if (in_array($this->skip(), $args)) {
            throw new InvalidArgumentException('Unprocessed skip argument found.');
        }

        // Сбросим ключи аргументов, т.к. мы удаляли их с помощью unset.
        $args = array_values($args);
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
