<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Mengubah satu baris Log Aktivitas menjadi pesan yang enak dibaca di lonceng:
 * judul singkat, rincian penting, nominal (bila ada), dan tautan ke datanya.
 *
 * Hasil: ['title', 'detail', 'amount' => ?int, 'flow' => 'in'|'out'|null,
 *         'icon', 'url' => ?string]
 */
class RingkasAktivitas
{
    /** Nama halaman untuk alamat yang dicoba dibuka tanpa izin. */
    private const HALAMAN = [
        'reports' => 'Laporan',
        'products' => 'Produk',
        'categories' => 'Kategori',
        'users' => 'Pengguna',
        'customers' => 'Pelanggan',
        'credit-payments' => 'Pembayaran piutang',
        'sales' => 'Riwayat transaksi',
        'stok' => 'Barang masuk',
        'pengaturan' => 'Pengaturan',
        'piutang' => 'Piutang',
        'audit-logs' => 'Log aktivitas',
        'app-logs' => 'Pembaruan aplikasi',
        'backups' => 'Cadangan data',
        'arang/jenis' => 'Jenis arang',
        'alerts' => 'Pemberitahuan',
    ];

    private const BAYAR = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'kasbon' => 'Kasbon'];

    public static function dari(ActivityLog $log): array
    {
        $p = $log->properties ?? [];
        $nama = $log->user?->name;

        $hasil = match ($log->action) {
            'sale.create' => [
                'title' => 'Penjualan',
                'detail' => self::gabung(
                    $p['invoice_no'] ?? self::cari('/INV\S+/', $log->description),
                    self::BAYAR[$p['payment_type'] ?? ''] ?? null,
                    isset($p['items']) ? "{$p['items']} jenis barang" : null,
                ),
                'amount' => $p['total'] ?? null,
                'flow' => 'in',
                'icon' => 'kasir',
                'url' => $log->subject_id ? route('sales.show', $log->subject_id) : null,
            ],
            'sale.void' => [
                'title' => 'Nota dibatalkan',
                'detail' => self::gabung($p['invoice_no'] ?? null, isset($p['reason']) ? "alasan: {$p['reason']}" : null),
                'amount' => ($p['cash_back'] ?? 0) ?: null,
                'flow' => 'out',
                'icon' => 'riwayat',
                'url' => $log->subject_id ? route('sales.show', $log->subject_id) : null,
            ],
            'sale.refund' => [
                'title' => 'Retur barang',
                'detail' => self::gabung($p['invoice_no'] ?? null, isset($p['reason']) ? "alasan: {$p['reason']}" : null),
                'amount' => $p['refund_value'] ?? null,
                'flow' => 'out',
                'icon' => 'riwayat',
                'url' => $log->subject_id ? route('sales.show', $log->subject_id) : null,
            ],
            'sale.update' => [
                'title' => 'Keterangan nota diubah',
                'detail' => self::cari('/INV\S+/', $log->description),
                'icon' => 'riwayat',
                'url' => $log->subject_id ? route('sales.show', $log->subject_id) : null,
            ],
            'arang.jual' => [
                'title' => 'Jual arang',
                'detail' => self::gabung(
                    isset($p['berat_kg']) ? self::kg($p['berat_kg']).(isset($p['jenis']) ? " {$p['jenis']}" : '') : null,
                    self::BAYAR[$p['payment_type'] ?? ''] ?? null,
                    $p['no_nota'] ?? null,
                ),
                'amount' => $p['total'] ?? null,
                'flow' => 'in',
                'icon' => 'fire',
                'url' => $log->subject_id ? route('arang.penjualan.receipt', $log->subject_id) : null,
            ],
            'arang.beli' => [
                'title' => 'Beli arang',
                'detail' => self::gabung(
                    isset($p['berat_kg']) ? self::kg($p['berat_kg']).(isset($p['jenis']) ? " {$p['jenis']}" : '') : null,
                    isset($p['pemasok']) ? "dari {$p['pemasok']}" : null,
                    $p['no_nota'] ?? null,
                ),
                'amount' => $p['total'] ?? null,
                'flow' => 'out',
                'icon' => 'fire',
                'url' => $log->subject_id ? route('arang.pembelian.receipt', $log->subject_id) : null,
            ],
            'credit.payment' => [
                'title' => 'Pelunasan kasbon',
                'detail' => $p['customer_name'] ?? null,
                'amount' => $p['amount'] ?? null,
                'flow' => 'in',
                'icon' => 'wallet',
                'url' => isset($p['customer_id']) ? route('customers.show', $p['customer_id']) : null,
            ],
            'shift.open' => [
                'title' => 'Shift dibuka',
                'detail' => 'modal awal laci',
                'amount' => $p['opening_cash'] ?? null,
                'icon' => 'shift',
                'url' => route('shift.index'),
            ],
            'shift.close' => [
                'title' => 'Shift ditutup',
                'detail' => isset($p['difference'])
                    ? ($p['difference'] == 0 ? 'uang laci pas' : ($p['difference'] > 0 ? 'uang laci lebih ' : 'uang laci kurang ').self::rp(abs($p['difference'])))
                    : null,
                'amount' => $p['counted_cash'] ?? null,
                'icon' => 'shift',
                'url' => route('shift.index'),
            ],
            'shift.cash' => [
                'title' => ($p['direction'] ?? '') === 'masuk' ? 'Kas masuk laci' : 'Kas keluar laci',
                'detail' => $p['note'] ?? null,
                'amount' => $p['amount'] ?? null,
                'flow' => ($p['direction'] ?? '') === 'masuk' ? 'in' : 'out',
                'icon' => 'wallet',
                'url' => route('shift.index'),
            ],
            'product.create' => [
                'title' => 'Produk baru',
                'detail' => self::gabung(
                    self::kutip($log->description),
                    isset($p['price']) ? 'harga '.self::rp($p['price']) : null,
                    isset($p['stock']) ? "stok {$p['stock']}" : null,
                ),
                'icon' => 'produk',
                'url' => route('products.index', ['search' => self::kutip($log->description)]),
            ],
            'product.update' => [
                'title' => 'Produk diubah',
                'detail' => self::gabung(self::kutip($log->description), self::perubahanProduk($p)),
                'icon' => 'produk',
                'url' => route('products.index', ['search' => self::kutip($log->description)]),
            ],
            'product.delete' => [
                'title' => 'Produk diarsipkan',
                'detail' => $p['name'] ?? self::kutip($log->description),
                'icon' => 'produk',
                'url' => route('products.index', ['status' => 'terhapus']),
            ],
            'product.restore' => [
                'title' => 'Produk dipulihkan',
                'detail' => self::kutip($log->description),
                'icon' => 'produk',
                'url' => route('products.index', ['search' => self::kutip($log->description)]),
            ],
            'stock.in', 'stock.adjustment' => [
                'title' => $log->action === 'stock.in' ? 'Barang masuk' : 'Penyesuaian stok',
                'detail' => self::gabung(
                    isset($p['items_count']) ? "{$p['items_count']} jenis barang" : null,
                    isset($p['supplier']) && $p['supplier'] ? "dari {$p['supplier']}" : null,
                    $p['note'] ?? null,
                ),
                'icon' => 'stok',
                'url' => route('stock.index'),
            ],
            'auth.login' => ['title' => 'Masuk aplikasi', 'detail' => null, 'icon' => 'profil'],
            'auth.logout' => ['title' => 'Keluar aplikasi', 'detail' => null, 'icon' => 'keluar'],
            'auth.password' => ['title' => 'Kata sandi diganti', 'detail' => 'akun sendiri', 'icon' => 'shield'],
            'auth.failed' => self::loginGagal($log, $p),
            'auth.lockout' => [
                'title' => 'Login dikunci sementara',
                'detail' => 'terlalu sering salah untuk '.($p['email'] ?? '-'),
                'icon' => 'shield',
            ],
            'auth.forbidden' => [
                'title' => 'Akses ditolak',
                'detail' => 'mencoba membuka halaman '.self::halaman($p['path'] ?? self::cari('#/(\S+)$#', $log->description, 1)),
                'icon' => 'shield',
            ],
            'report.export' => [
                'title' => 'Laporan diunduh',
                'detail' => preg_replace('/^Mengunduh laporan /', '', $log->description),
                'icon' => 'download',
                'url' => route('reports.index'),
            ],
            default => [
                'title' => self::judulUmum($log->action),
                'detail' => $log->description,
                'icon' => 'history',
            ],
        };

        return [
            'title' => $hasil['title'],
            'detail' => $hasil['detail'] ?? null,
            'amount' => isset($hasil['amount']) ? (int) $hasil['amount'] : null,
            'flow' => $hasil['flow'] ?? null,
            'icon' => $hasil['icon'],
            'url' => $hasil['url'] ?? null,
            'who' => $nama,
        ];
    }

    private static function loginGagal(ActivityLog $log, array $p): array
    {
        $email = $p['email'] ?? null;
        $akun = $email ? User::where('email', $email)->value('name') : null;

        return $akun
            ? ['title' => 'Salah kata sandi', 'detail' => "akun {$akun} ({$email})", 'icon' => 'shield']
            : ['title' => 'Email tak terdaftar', 'detail' => $email ? "mencoba masuk sebagai {$email}" : null, 'icon' => 'shield'];
    }

    private static function perubahanProduk(array $p): ?string
    {
        $label = ['price' => 'harga', 'cost' => 'modal', 'stock' => 'stok', 'name' => 'nama', 'low_stock' => 'ambang', 'is_active' => 'status'];
        $bagian = [];
        foreach ($label as $k => $nama) {
            if (! is_array($p[$k] ?? null)) {
                continue;
            }
            [$dari, $ke] = [$p[$k]['before'] ?? null, $p[$k]['after'] ?? null];
            $bagian[] = match ($k) {
                'price', 'cost' => "{$nama} ".self::rp((int) $dari).' → '.self::rp((int) $ke),
                'is_active' => $ke ? 'diaktifkan' : 'dinonaktifkan',
                default => "{$nama} {$dari} → {$ke}",
            };
        }

        return $bagian ? implode(', ', $bagian) : null;
    }

    private static function halaman(?string $path): string
    {
        $path = trim((string) $path, '/');
        foreach (self::HALAMAN as $awalan => $nama) {
            if ($path === $awalan || str_starts_with($path, $awalan.'/')) {
                return $nama;
            }
        }

        return $path !== '' ? "/{$path}" : 'khusus admin';
    }

    private static function judulUmum(string $action): string
    {
        return [
            'user.create' => 'Pengguna baru',
            'user.update' => 'Akun pengguna diubah',
            'user.delete' => 'Pengguna dihapus',
            'setting.update' => 'Pengaturan toko diubah',
            'backup.create' => 'Cadangan data dibuat',
            'backup.download' => 'Cadangan data diunduh',
            'backup.delete' => 'Cadangan data dihapus',
            'backup.restore' => 'Data dipulihkan dari cadangan',
            'category.create' => 'Kategori baru',
            'category.update' => 'Kategori diubah',
            'category.delete' => 'Kategori dihapus',
            'customer.create' => 'Pelanggan baru',
            'customer.update' => 'Pelanggan diubah',
            'customer.delete' => 'Pelanggan dihapus',
            'arang_jenis.create' => 'Jenis arang baru',
            'arang_jenis.update' => 'Jenis arang diubah',
            'arang_jenis.delete' => 'Jenis arang dihapus',
            'profile.update' => 'Profil diubah',
        ][$action] ?? 'Aktivitas';
    }

    /** Teks di dalam tanda kutip tunggal pertama, mis. nama produk. */
    private static function kutip(string $teks): ?string
    {
        return self::cari("/'([^']+)'/", $teks, 1);
    }

    private static function cari(string $pola, string $teks, int $grup = 0): ?string
    {
        return preg_match($pola, $teks, $m) ? $m[$grup] : null;
    }

    private static function gabung(?string ...$bagian): ?string
    {
        $isi = array_filter($bagian, fn ($b) => $b !== null && $b !== '');

        return $isi ? implode(' · ', $isi) : null;
    }

    private static function rp(int $n): string
    {
        return 'Rp'.number_format($n, 0, ',', '.');
    }

    private static function kg(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',').' kg';
    }
}
