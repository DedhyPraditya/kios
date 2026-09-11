<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArangJenis extends Model
{
    protected $table = 'arang_jenis';

    protected $fillable = [
        'nama',
        'harga_beli_default',
        'harga_jual_default',
        'aktif',
        'catatan',
    ];

    protected $appends = ['stok_kg'];

    protected function casts(): array
    {
        return [
            'harga_beli_default' => 'integer',
            'harga_jual_default' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function pembelian(): HasMany
    {
        return $this->hasMany(ArangPembelian::class, 'arang_jenis_id');
    }

    public function penjualan(): HasMany
    {
        return $this->hasMany(ArangPenjualan::class, 'arang_jenis_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function getStokKgAttribute(): float
    {
        $totalBeli = (float) $this->pembelian()->sum('berat_kg');
        $totalJual = (float) $this->penjualan()->sum('berat_kg');

        return round($totalBeli - $totalJual, 2);
    }
}
