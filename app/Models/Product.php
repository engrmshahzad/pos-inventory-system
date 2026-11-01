<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_IN = 'in';
    public const STATUS_LOW = 'low';
    public const STATUS_OUT = 'out';

    protected $fillable = [
        'name', 'sku', 'barcode', 'category_id', 'brand_id', 'unit_id',
        'purchase_price', 'sale_price', 'wholesale_price', 'tax_percent', 'min_stock',
        'track_expiry', 'description', 'image', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Adds a `stock_qty` column: one branch's quantity, or the total of all branches when $branchId is null.
     */
    public function scopeWithStock(Builder $query, ?int $branchId): Builder
    {
        $sub = DB::table('branch_stock')
            ->selectRaw('COALESCE(SUM(quantity), 0)')
            ->whereColumn('branch_stock.product_id', 'products.id')
            ->when($branchId, fn ($q) => $q->where('branch_stock.branch_id', $branchId));

        if (is_null($query->getQuery()->columns)) {
            $query->select('products.*');
        }

        return $query->selectSub($sub, 'stock_qty');
    }

    /** Filter by in / low / out against the given branch (or all branches). Needs withStock(). */
    public function scopeStockStatus(Builder $query, string $status, ?int $branchId): Builder
    {
        $qty = '(SELECT COALESCE(SUM(bs.quantity), 0) FROM branch_stock bs WHERE bs.product_id = products.id'
            .($branchId ? ' AND bs.branch_id = '.(int) $branchId : '').')';
        $minStock = $branchId ? 'products.min_stock' : 'products.min_stock * (SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL)';

        return match ($status) {
            self::STATUS_OUT => $query->whereRaw("{$qty} <= 0"),
            self::STATUS_LOW => $query->whereRaw("{$qty} > 0 AND {$qty} <= {$minStock}"),
            self::STATUS_IN => $query->whereRaw("{$qty} > {$minStock}"),
            default => $query,
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(fn ($q) => $q->where('products.name', 'like', $like)
            ->orWhere('products.sku', 'like', $like)
            ->orWhere('products.barcode', 'like', $like));
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /** Profit margin on sale price, in percent. */
    public function getMarginAttribute(): float
    {
        $sale = (float) $this->sale_price;

        return $sale > 0 ? round((($sale - (float) $this->purchase_price) / $sale) * 100, 1) : 0.0;
    }

    /** in / low / out for a quantity; min_stock is per branch, so multiply by branch count for totals. */
    public function stockStatusFor(float $quantity, int $branches = 1): string
    {
        if ($quantity <= 0) {
            return self::STATUS_OUT;
        }

        return $quantity <= (float) $this->min_stock * max($branches, 1) ? self::STATUS_LOW : self::STATUS_IN;
    }

    public static function statusBadge(string $status): array
    {
        return match ($status) {
            self::STATUS_OUT => ['danger', 'Out of stock'],
            self::STATUS_LOW => ['warning', 'Low stock'],
            default => ['success', 'In stock'],
        };
    }

    public function formatQty(float|string|null $quantity): string
    {
        $quantity = (float) $quantity;

        if (floor($quantity) == $quantity) {
            return number_format($quantity);
        }

        // Only trim zeros after the decimal point: 1.500 -> 1.5, never 120 -> 12.
        return rtrim(rtrim(number_format($quantity, 3), '0'), '.');
    }
}
