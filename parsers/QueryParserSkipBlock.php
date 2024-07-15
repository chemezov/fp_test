<?php

namespace FpDbTest\parsers;

use InvalidArgumentException;

class QueryParserSkipBlock
{
    public function skip()
    {
        return SkipEnum::SKIP;
    }

    public function removeSkippedBlocks(&$string, &$args, int $skipSpecifierCount = 0)
    {
        $len = strlen($string);
        $args = array_values($args);

        $specifierIndex = -1;

        for ($i = 0; $i < $len; $i++) {
            $char = $string[$i];

            // Find specifier
            if ($char === '?') {
                $specifierIndex++;

                if ($specifierIndex <= $skipSpecifierCount - 1) {
                    continue;
                }

                $isArgSkipped = $args[$specifierIndex] === $this->skip();

                $prevString = substr($string, 0, $i);
                $nextString = substr($string, $i + 1);

                /**
                 * Если аргумент пропускается, то мы принудительно ищем фигурные скобки.
                 * Если они не будут найдены или будут в неправильном порядке - выбросится исключение.
                 */
                $bracesStart = $this->findBracesStart($prevString, $isArgSkipped);
                $bracesEnd = $this->findBracesEnd($nextString, $isArgSkipped);

                if ($bracesEnd !== null) {
                    $bracesEnd += strlen($prevString) + 1; // 1 - сам символ ?
                }

                if ($isArgSkipped) {
                    $string = substr_replace($string, '', $bracesStart, $bracesEnd - $bracesStart + 1); // 1 - плюс закрывающая фигурная скобка }
                    unset($args[$specifierIndex]);

                    return $this->removeSkippedBlocks($string, $args, $skipSpecifierCount);
                } else {
                    // Safe find braces start and end
                    if ($bracesStart === null && $bracesEnd === null) {
                        return $this->removeSkippedBlocks($string, $args, ++$skipSpecifierCount);
                    }

                    if ($bracesStart !== null && $bracesEnd !== null) {
                        $string = substr_replace($string, '', $bracesEnd, 1);
                        $string = substr_replace($string, '', $bracesStart, 1);

                        return $this->removeSkippedBlocks($string, $args, ++$skipSpecifierCount);
                    }

                    throw new InvalidArgumentException('Invalid braces order.');
                }
            }
        }

        return false;
    }

    protected function findBracesStart(string $prevString, bool $throwException = true): ?int
    {
        $bracesStart = null;

        for ($j = strlen($prevString) - 1; $j >= 0; $j--) {
            if ($prevString[$j] === '}') {
                if ($throwException) {
                    throw new InvalidArgumentException('Invalid braces order.');
                }

                return null;
            }

            if ($prevString[$j] === '{') {
                $bracesStart = $j;
                break;
            }
        }

        if ($bracesStart === null) {
            if ($throwException) {
                throw new InvalidArgumentException('Unable to find braces start');
            }

            return null;
        }

        return $bracesStart;
    }

    protected function findBracesEnd(string $nextString, bool $throwException = true): ?int
    {
        $bracesEnd = null;

        for ($j = 0; $j < strlen($nextString); $j++) {
            if ($nextString[$j] === '{') {
                if ($throwException) {
                    throw new InvalidArgumentException('Invalid braces order.');
                }

                return null;
            }

            if ($nextString[$j] === '}') {
                $bracesEnd = $j;
                break;
            }
        }

        if ($bracesEnd === null) {
            if ($throwException) {
                throw new InvalidArgumentException('Unable to find braces end');
            }

            return null;
        }

        return $bracesEnd;
    }
}
