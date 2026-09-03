<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_no', 'user_id', 'cash_session_id', 'customer_id', 'payment_type', 'status',
        'subtotal', 'discount', 'total', 'paid', 'change', 'due_date', 'note',
        'voided_at', 'voided_by', 'void_reason', 'refunded',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'change' => 'integer',
            'refunded' => 'integer',
            'due_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Nota batal tak boleh ikut dihitung di laporan / omzet / piutang. */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Nilai bersih nota setelah dikurangi barang yang diretur. */
    public function netTotal(): int
    {
        return max($this->total - $this->refunded, 0);
    }

    public function scopeKasbon(Builder $query): Builder
    {
        return $query->where('payment_type', 'kasbon');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('payment_type', 'kasbon')
            ->where('status', 'belum_lunas')
            ->whereNull('voided_at');
    }

    /** Sisa yang harus dibayar untuk nota kasbon ini. */
    public function outstanding(): int
    {
        if ($this->payment_type !== 'kasbon' || $this->isVoided()) {
            return 0;
        }

        $paidBack = (int) $this->creditPayments()->sum('amount');

        return max($this->netTotal() - $this->paid - $paidBack, 0);
    }

    /** Setel ulang status lunas/belum berdasarkan pembayaran. */
    public function syncStatus(): void
    {
        if ($this->payment_type !== 'kasbon') {
            return;
        }

        $status = $this->outstanding() <= 0 ? 'lunas' : 'belum_lunas';

        if ($status !== $this->status) {
            $this->forceFill(['status' => $status])->save();
        }
    }

    public static function makeInvoiceNo(): string
    {
        $prefix = 'INV'.now()->format('Ymd');
        $seq = static::whereDate('created_at', today())->count() + 1;

        return $prefix.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
