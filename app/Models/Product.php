<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'barcode', 'name', 'price', 'cost', 'stock', 'low_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'stock' => 'integer',
            'low_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Stok sudah diisi ulang di atas ambang: hapus tanda "sudah dibaca"
        // supaya bila nanti menipis lagi, lonceng mengingatkan kembali.
        static::updated(function (Product $product) {
            if ($product->wasChanged(['stock', 'low_stock']) && $product->stock > $product->low_stock) {
                DismissedAlert::where('type', 'product_stock')
                    ->where('alertable_id', $product->id)
                    ->delete();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getIsLowAttribute(): bool
    {
        return $this->stock <= $this->low_stock;
    }
}
