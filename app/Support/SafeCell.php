<?php

namespace App\Support;

/**
 * Spreadsheet formula-injection guard: text starting with = + - @ (or tab/CR) is prefixed with an apostrophe
 * so Excel shows it as text instead of running it.
 */
class SafeCell
{
    public static function text(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
