<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'product_unit_id', 'name', 'unit_name', 'unit_isi',
        'price', 'cost', 'qty', 'discount', 'returned_qty', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'qty' => 'integer',
            'returned_qty' => 'integer',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'unit_isi' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Nilai bersih satu satuan setelah diskon baris (dipakai retur & laporan). */
    public function netUnitPrice(): float
    {
        return $this->qty > 0 ? $this->subtotal / $this->qty : 0;
    }

    /** Jumlah satuan dasar untuk $qty satuan di baris ini (mis. 2 dus x 40). */
    public function baseQty(int $qty): int
    {
        return $qty * max($this->unit_isi, 1);
    }

    /** Sisa yang masih boleh diretur dari baris ini. */
    public function returnableQty(): int
    {
        return max($this->qty - $this->returned_qty, 0);
    }
}
