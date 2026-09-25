<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArangPembelian extends Model
{
    protected $table = 'arang_pembelian';

    protected $fillable = [
        'no_nota',
        'tanggal',
        'arang_jenis_id',
        'nama_pemasok',
        'berat_kg',
        'harga_beli_per_kg',
        'total_harga',
        'cash_session_id',
        'user_id',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'berat_kg' => 'float',
            'harga_beli_per_kg' => 'integer',
            'total_harga' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ArangPembelian $pembelian) {
            if (empty($pembelian->no_nota)) {
                $prefix = 'BELI-ARNG-' . now()->format('Ymd') . '-';
                $last = static::where('no_nota', 'like', $prefix . '%')->latest('id')->first();
                $seq = 1;
                if ($last) {
                    $parts = explode('-', $last->no_nota);
                    $seq = ((int) end($parts)) + 1;
                }
                $pembelian->no_nota = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function arangJenis(): BelongsTo
    {
        return $this->belongsTo(ArangJenis::class, 'arang_jenis_id');
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
