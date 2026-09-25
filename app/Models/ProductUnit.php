<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satuan jual tambahan sebuah produk, mis. "dus" isi 40 pcs. */
class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'name', 'isi', 'price', 'barcode'];

    protected function casts(): array
    {
        return [
            'isi' => 'integer',
            'price' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
