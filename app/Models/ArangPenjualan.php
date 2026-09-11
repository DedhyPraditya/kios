<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArangPenjualan extends Model
{
    protected $table = 'arang_penjualan';

    protected $fillable = [
        'no_nota',
        'tanggal',
        'arang_jenis_id',
        'nama_pembeli',
        'customer_id',
        'berat_kg',
        'harga_jual_per_kg',
        'total_harga',
        'diskon',
        'grand_total',
        'payment_type',
        'paid',
        'change',
        'status',
        'cash_session_id',
        'user_id',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'berat_kg' => 'float',
            'harga_jual_per_kg' => 'integer',
            'total_harga' => 'integer',
            'diskon' => 'integer',
            'grand_total' => 'integer',
            'paid' => 'integer',
            'change' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ArangPenjualan $penjualan) {
            if (empty($penjualan->no_nota)) {
                $prefix = 'ARNG-' . now()->format('Ymd') . '-';
                $last = static::where('no_nota', 'like', $prefix . '%')->latest('id')->first();
                $seq = 1;
                if ($last) {
                    $parts = explode('-', $last->no_nota);
                    $seq = ((int) end($parts)) + 1;
                }
                $penjualan->no_nota = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function arangJenis(): BelongsTo
    {
        return $this->belongsTo(ArangJenis::class, 'arang_jenis_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
