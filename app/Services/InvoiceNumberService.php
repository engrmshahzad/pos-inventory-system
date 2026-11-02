<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InvoiceSequence;
use App\Support\SettingsDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free document numbers. The counter row is locked, so two tills can never get the same number.
 * Branch documents: HYD-S-000001 (sale), HYD-P-000001 (purchase). Global documents: TR-000001.
 */
class InvoiceNumberService
{
    public function next(string $type, ?Branch $branch = null, int $pad = 6): string
    {
        return DB::transaction(function () use ($type, $branch, $pad) {
            $query = fn () => InvoiceSequence::where('type', $type)
                ->when($branch, fn ($q) => $q->where('branch_id', $branch->id), fn ($q) => $q->whereNull('branch_id'))
                ->lockForUpdate()
                ->first();

            $sequence = $query();
            if (! $sequence) {
                InvoiceSequence::insertOrIgnore([[
                    'branch_id' => $branch?->id, 'type' => $type, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]]);
                $sequence = $query();
            }

            $sequence->increment('last_number');
            $number = str_pad((string) $sequence->last_number, $pad, '0', STR_PAD_LEFT);

            // The counter is keyed by the internal type; the printed prefix comes from Settings.
            $key = SettingsDefinition::PREFIX_KEYS[$type] ?? null;
            $prefix = $key ? strtoupper((string) settings($key, $type)) : $type;

            return $branch ? "{$branch->code}-{$prefix}-{$number}" : "{$prefix}-{$number}";
        });
    }
}
