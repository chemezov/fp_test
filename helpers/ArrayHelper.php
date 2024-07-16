<?php

namespace FpDbTest\helpers;

class ArrayHelper
{
    public static function isAssociative(array $array, bool $allStrings = true): bool
    {
        if (empty($array)) {
            return false;
        }

        if ($allStrings) {
            foreach ($array as $key => $value) {
                if (!is_string($key)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($array as $key => $value) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }
}
