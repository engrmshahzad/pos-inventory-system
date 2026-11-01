<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly string $branchName,
        public readonly float $available,
        public readonly float $requested,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for "%s" at %s: available %s, requested %s.',
            $productName,
            $branchName,
            rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($requested, 3, '.', ''), '0'), '.'),
        ));
    }
}
