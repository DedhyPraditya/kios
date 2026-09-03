<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = [
        'user_id', 'opening_cash', 'opened_at', 'closed_at', 'closed_by',
        'auto_closed', 'counted_cash', 'expected_cash', 'difference', 'deposit', 'note',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash' => 'integer',
            'counted_cash' => 'integer',
            'expected_cash' => 'integer',
            'difference' => 'integer',
            'deposit' => 'integer',
            'auto_closed' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Shift yang masih terbuka milik kasir ini, bila ada.
     *
     * Shift yang menginap ditutup lebih dulu: begitu ganti hari, rekap laci
     * kemarin dikunci supaya penjualan hari ini tidak ikut masuk ke sana.
     */
    public static function openFor(User $user): ?self
    {
        static::sealStale($user);

        return static::open()->where('user_id', $user->id)->latest('opened_at')->first();
    }

    /**
     * Kunci shift yang dibuka pada hari sebelumnya.
     *
     * Uang fisiknya tak pernah dihitung — jadi `counted_cash` dan `difference`
     * sengaja dibiarkan kosong, bukan diisi nol, supaya tidak terbaca sebagai
     * "laci kosong" atau "selisih pas".
     *
     * @param  User|null  $user  batasi ke satu kasir; kosong = semua kasir
     * @return int jumlah shift yang ditutup
     */
    public static function sealStale(?User $user = null): int
    {
        $stale = static::open()
            ->whereDate('opened_at', '<', today())
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->get();

        foreach ($stale as $session) {
            $session->update([
                'closed_at' => $session->opened_at->copy()->endOfDay(),
                'closed_by' => null,
                'auto_closed' => true,
                'counted_cash' => null,
                'expected_cash' => $session->summary()['expected_cash'],
                'difference' => null,
                'note' => trim(($session->note ? $session->note.' | ' : '')
                    .'Ditutup otomatis karena ganti hari; uang laci belum sempat dihitung.'),
            ]);
        }

        return $stale->count();
    }

    /**
     * Rekap laci: dari mana saja uang tunai masuk dan keluar selama shift.
     * Nota kasbon hanya menyumbang DP-nya; nota batal tidak dihitung.
     */
    public function summary(): array
    {
        $sales = $this->sales()->whereNull('voided_at')->get();

        // Nilai dihitung bersih: barang yang diretur di shift ini langsung
        // mengurangi uang laci, jadi tak perlu dicatat lagi sebagai kas keluar.
        $tunai = (int) $sales->where('payment_type', 'tunai')->sum(fn (Sale $s) => $s->netTotal());
        $dpKasbon = (int) $sales->where('payment_type', 'kasbon')->sum('paid');
        $omzetKasbon = (int) $sales->where('payment_type', 'kasbon')->sum(fn (Sale $s) => $s->netTotal());
        $pelunasan = (int) $this->creditPayments()->sum('amount');

        $movements = $this->movements()->get();
        $masuk = (int) $movements->where('direction', 'masuk')->sum('amount');
        $keluar = (int) $movements->where('direction', 'keluar')->sum('amount');

        $expected = $this->opening_cash + $tunai + $dpKasbon + $pelunasan + $masuk - $keluar;

        return [
            'opening_cash' => $this->opening_cash,
            'trx_count' => $sales->count(),
            'sales_tunai' => $tunai,
            'sales_kasbon' => $omzetKasbon,
            'dp_kasbon' => $dpKasbon,
            'credit_payments' => $pelunasan,
            'cash_in' => $masuk,
            'cash_out' => $keluar,
            'expected_cash' => $expected,
        ];
    }
}
