<?php

namespace FpDbTest\helpers;

/**
 * Класс и метод взят из yii2 фреймворка as-is.
 */
class ArrayHelper
{
    public static function isAssociative($array, $allStrings = true)
    {
        if (empty($array) || !is_array($array)) {
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
