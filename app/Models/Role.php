<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie role + `base_type` (owner / manager / cashier), which decides what data the role sees.
 * The three built-in roles cannot be renamed or deleted; owner cannot be edited at all.
 */
class Role extends SpatieRole
{
    public const BUILT_IN = ['owner', 'manager', 'cashier'];

    /** Types a custom role can be based on. */
    public const BASE_TYPES = [
        'manager' => ['Manager-like', 'Sees all data of their branch'],
        'cashier' => ['Cashier-like', 'Sees only their own sales and returns'],
    ];

    public function isBuiltIn(): bool
    {
        return in_array($this->name, self::BUILT_IN, true);
    }

    public static function typeOf(?string $name): ?string
    {
        return $name ? static::where('name', $name)->value('base_type') : null;
    }
}
