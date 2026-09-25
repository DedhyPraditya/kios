<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\DismissedAlert;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Isi lonceng notifikasi admin: stok menipis, kasbon mendekati jatuh tempo,
 * dan aktivitas terbaru dari Log Aktivitas (termasuk percobaan masuk gagal).
 *
 * Notifikasi yang sudah ditandai dibaca disembunyikan, tapi muncul lagi bila
 * keadaannya memburuk:
 *  - stok turun di bawah angka saat ditandai (`last_value` = stok saat itu);
 *    bila stok sudah diisi ulang di atas ambang, tanda dibaca dihapus
 *    (lihat Product::booted) sehingga penurunan berikutnya diingatkan lagi;
 *  - kasbon yang ditandai sebelum tempo muncul lagi setelah lewat tempo
 *    (`last_value` = 1 bila saat ditandai sudah lewat tempo, selain itu 0).
 */
class Peringatan
{
    /** Kasbon yang jatuh tempo dalam sekian hari ke depan ikut diingatkan. */
    public const HARI_SEBELUM_TEMPO = 3;

    /** Kejadian keamanan: selalu ditonjolkan dan dihitung, siapa pun pelakunya. */
    public const AKSI_KEAMANAN = ['auth.failed', 'auth.lockout', 'auth.forbidden'];

    /**
     * Aktivitas yang belum dilihat admin: semua kegiatan pengguna lain plus
     * kejadian keamanan, sejak tab Aktivitas terakhir dibuka (bila belum
     * pernah, 24 jam terakhir). Kegiatan admin itu sendiri tidak dihitung.
     */
    public static function aktivitasBaru(User $admin): Builder
    {
        return ActivityLog::query()
            ->where('created_at', '>', $admin->activity_seen_at ?? now()->subDay())
            ->where(fn (Builder $q) => $q
                ->whereNull('user_id')
                ->orWhere('user_id', '!=', $admin->id)
                ->orWhereIn('action', self::AKSI_KEAMANAN));
    }

    /** Daftar aktivitas terbaru untuk lonceng. */
    public static function aktivitasTerbaru(User $admin, int $jumlah = 15): array
    {
        $batasBaru = $admin->activity_seen_at ?? now()->subDay();

        return ActivityLog::with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit($jumlah)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->name,
                'ip' => $log->ip_address,
                'time' => $log->created_at?->locale('id')->diffForHumans(),
                'security' => in_array($log->action, self::AKSI_KEAMANAN, true),
                'new' => $log->created_at > $batasBaru && ($log->user_id !== $admin->id
                    || in_array($log->action, self::AKSI_KEAMANAN, true)),
            ])
            ->all();
    }

    public static function stokMenipis(?int $userId = null): Builder
    {
        return Product::active()
            ->whereColumn('stock', '<=', 'low_stock')
            ->when($userId, fn (Builder $q) => $q->whereNotExists(fn (QueryBuilder $d) => $d
                ->selectRaw('1')
                ->from('dismissed_alerts')
                ->where('dismissed_alerts.user_id', $userId)
                ->where('dismissed_alerts.type', 'product_stock')
                ->whereColumn('dismissed_alerts.alertable_id', 'products.id')
                ->whereColumn('dismissed_alerts.last_value', '<=', 'products.stock')));
    }

    public static function kasbonJatuhTempo(?int $userId = null): Builder
    {
        $hariIni = today()->toDateString();

        return Sale::unpaid()
            ->whereNotNull('due_date')
            ->where('due_date', '<=', today()->addDays(self::HARI_SEBELUM_TEMPO)->toDateString())
            ->when($userId, fn (Builder $q) => $q->whereNotExists(fn (QueryBuilder $d) => $d
                ->selectRaw('1')
                ->from('dismissed_alerts')
                ->where('dismissed_alerts.user_id', $userId)
                ->where('dismissed_alerts.type', 'sale_due')
                ->whereColumn('dismissed_alerts.alertable_id', 'sales.id')
                // Ditandai sebelum tempo → muncul lagi begitu lewat tempo.
                ->where(fn (QueryBuilder $w) => $w
                    ->where('dismissed_alerts.last_value', 1)
                    ->orWhere('sales.due_date', '>=', $hariIni))));
    }

    public static function tandaiProduk(int $userId, Product $product): void
    {
        DismissedAlert::updateOrCreate(
            ['user_id' => $userId, 'type' => 'product_stock', 'alertable_id' => $product->id],
            ['last_value' => $product->stock]
        );
    }

    public static function tandaiKasbon(int $userId, Sale $sale): void
    {
        DismissedAlert::updateOrCreate(
            ['user_id' => $userId, 'type' => 'sale_due', 'alertable_id' => $sale->id],
            ['last_value' => $sale->due_date?->lt(today()) ? 1 : 0]
        );
    }
}
