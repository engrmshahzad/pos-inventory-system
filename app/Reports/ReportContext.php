<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Carbon;

class ReportContext
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public ?int $branchId,
        public User $user,
        public array $params,
        public bool $canProfit,
        public string $preset,
    ) {
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function start(): Carbon
    {
        return $this->from->copy()->startOfDay();
    }

    public function end(): Carbon
    {
        return $this->to->copy()->endOfDay();
    }
}
