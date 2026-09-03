<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'sale_id', 'type', 'qty',
        'stock_after', 'cost', 'supplier', 'note',
    ];

    /** Label Indonesia untuk tiap jenis gerakan. */
    public const LABELS = [
        'masuk' => 'Barang masuk',
        'penjualan' => 'Penjualan',
        'retur' => 'Retur',
        'batal' => 'Nota dibatalkan',
        'penyesuaian' => 'Penyesuaian',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'stock_after' => 'integer',
            'cost' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Ubah stok produk sekaligus mencatat jejaknya.
     * Selalu dipanggil di dalam transaksi DB dengan produk terkunci.
     */
    public static function apply(Product $product, int $qty, string $type, array $extra = []): self
    {
        $product->stock += $qty;
        $product->save();

        return static::create([
            'product_id' => $product->id,
            'user_id' => $extra['user_id'] ?? auth()->id(),
            'sale_id' => $extra['sale_id'] ?? null,
            'type' => $type,
            'qty' => $qty,
            'stock_after' => $product->stock,
            'cost' => $extra['cost'] ?? null,
            'supplier' => $extra['supplier'] ?? null,
            'note' => $extra['note'] ?? null,
        ]);
    }
}
