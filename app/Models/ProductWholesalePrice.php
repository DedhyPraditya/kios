<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Harga grosir: harga per satuan dasar bila beli minimal `min_qty`. */
class ProductWholesalePrice extends Model
{
    protected $fillable = ['product_id', 'min_qty', 'price'];

    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'price' => 'integer',
        ];
    }
}
