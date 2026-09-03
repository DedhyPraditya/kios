<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'address', 'credit_limit', 'is_blocked', 'note',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'is_blocked' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    /** Total sisa hutang: nota kasbon belum lunas dikurangi pelunasan. */
    public function outstanding(): int
    {
        // Dicor ke SIGNED: nota yang lebih bayar (DP melebihi nilai bersih setelah
        // retur) bernilai minus, dan pengurangan kolom unsigned meluber di MySQL.
        $owed = (int) $this->sales()
            ->where('payment_type', 'kasbon')
            ->whereNull('voided_at')
            ->sum(DB::raw('CAST(total AS SIGNED) - CAST(refunded AS SIGNED) - CAST(paid AS SIGNED)'));

        $settled = (int) $this->creditPayments()->sum('amount');

        return max($owed - $settled, 0);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%"));
    }
}
