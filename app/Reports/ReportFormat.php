<?php

namespace App\Reports;

class ReportFormat
{
    public static function cell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'money' => money((float) $value),
            'number' => number_format((float) $value),
            'qty' => rtrim(rtrim(number_format((float) $value, 3), '0'), '.') ?: '0',
            'percent' => number_format((float) $value, 1).'%',
            'date' => fmtDate($value),
            'datetime' => fmtDate($value, true),
            default => (string) $value,
        };
    }

    public static function isNumeric(string $type): bool
    {
        return in_array($type, ['money', 'number', 'qty', 'percent'], true);
    }

    public static function badgeColor(string $value): string
    {
        return match (true) {
            in_array($value, ['Out', 'Rejected', 'Cancelled'], true) => 'danger',
            in_array($value, ['Low', 'Pending approval', 'Partially received'], true) => 'warning',
            in_array($value, ['In stock', 'Approved', 'Received'], true) => 'success',
            in_array($value, ['In transit'], true) => 'info',
            default => 'slate',
        };
    }
}
