<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'name', 'price', 'cost', 'qty', 'returned_qty', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'qty' => 'integer',
            'returned_qty' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Sisa yang masih boleh diretur dari baris ini. */
    public function returnableQty(): int
    {
        return max($this->qty - $this->returned_qty, 0);
    }
}
