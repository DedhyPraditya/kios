<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArangPembelian extends Model
{
    protected $table = 'arang_pembelian';

    protected $fillable = [
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
