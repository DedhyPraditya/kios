<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\ArangPenjualan;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?: Carbon::today();
        $to = $request->date('to') ?: Carbon::today();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        // 1. Toko Eceran
        $salesInRange = Sale::valid()->whereBetween('created_at', [$from, $to]);
        $tokoCount = (clone $salesInRange)->count();
        $tokoOmzet = (int) (clone $salesInRange)->sum(DB::raw('total - refunded'));
        $tokoDiscount = (int) (clone $salesInRange)->sum('discount');
        $tokoRefunded = (int) (clone $salesInRange)->sum('refunded');

        $itemsInRange = SaleItem::whereHas(
            'sale',
            fn ($q) => $q->valid()->whereBetween('created_at', [$from, $to]),
        );

        $tokoProfit = (int) (clone $itemsInRange)
            ->selectRaw('COALESCE(SUM((CAST(price AS SIGNED) - CAST(cost AS SIGNED)) * CAST(qty - returned_qty AS SIGNED)), 0) as p')
            ->value('p');

        // 2. Modul Arang
        $arangJualInRange = ArangPenjualan::whereBetween('created_at', [$from, $to]);
        $arangBeliInRange = ArangPembelian::whereBetween('created_at', [$from, $to]);

        $arangCount = (clone $arangJualInRange)->count();
        $arangOmzet = (int) (clone $arangJualInRange)->sum('grand_total');
        $arangDiscount = (int) (clone $arangJualInRange)->sum('diskon');
        $arangKg = (float) (clone $arangJualInRange)->sum('berat_kg');
        $arangBeliStok = (int) (clone $arangBeliInRange)->sum('total_harga');

        $arangProfit = (int) DB::table('arang_penjualan')
            ->join('arang_jenis', 'arang_penjualan.arang_jenis_id', '=', 'arang_jenis.id')
            ->whereBetween('arang_penjualan.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(CAST(arang_penjualan.grand_total AS SIGNED) - (arang_penjualan.berat_kg * arang_jenis.harga_beli_default)), 0) as p')
            ->value('p');

        // 3. Ringkasan Terpadu
        $summary = [
            'count' => $tokoCount + $arangCount,
            'omzet' => $tokoOmzet + $arangOmzet,
            'profit' => $tokoProfit + $arangProfit,
            'discount' => $tokoDiscount + $arangDiscount,
            'refunded' => $tokoRefunded,
        ];

        $breakdown = [
            'toko' => [
                'count' => $tokoCount,
                'omzet' => $tokoOmzet,
                'profit' => $tokoProfit,
                'discount' => $tokoDiscount,
            ],
            'arang' => [
                'count' => $arangCount,
                'omzet' => $arangOmzet,
                'profit' => $arangProfit,
                'discount' => $arangDiscount,
                'berat_kg' => round($arangKg, 2),
                'beli_stok' => $arangBeliStok,
            ],
        ];

        // 4. Omzet Harian Terpadu
        $tokoDaily = (clone $salesInRange)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as trx, SUM(total - refunded) as omzet')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $arangDaily = (clone $arangJualInRange)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as trx, SUM(grand_total) as omzet')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $allDates = $tokoDaily->keys()->merge($arangDaily->keys())->unique()->sort()->values();
        $daily = $allDates->map(function ($date) use ($tokoDaily, $arangDaily) {
            $tTrx = $tokoDaily[$date]->trx ?? 0;
            $tOmzet = (int) ($tokoDaily[$date]->omzet ?? 0);
            $aTrx = $arangDaily[$date]->trx ?? 0;
            $aOmzet = (int) ($arangDaily[$date]->omzet ?? 0);

            return [
                'd' => $date,
                'trx' => $tTrx + $aTrx,
                'omzet' => $tOmzet + $aOmzet,
                'toko_omzet' => $tOmzet,
                'arang_omzet' => $aOmzet,
            ];
        });

        // 5. Produk Terlaris Terpadu
        $topItems = (clone $itemsInRange)
            ->selectRaw("name, SUM(qty - returned_qty) as qty, 'pcs' as unit, SUM(price * (qty - returned_qty)) as omzet")
            ->groupBy('name')
            ->havingRaw('SUM(qty - returned_qty) > 0')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'qty' => $r->qty,
                'unit' => 'pcs',
                'omzet' => (int) $r->omzet,
            ]);

        $topArang = DB::table('arang_penjualan')
            ->join('arang_jenis', 'arang_penjualan.arang_jenis_id', '=', 'arang_jenis.id')
            ->whereBetween('arang_penjualan.created_at', [$from, $to])
            ->selectRaw("arang_jenis.nama as raw_name, ROUND(SUM(arang_penjualan.berat_kg), 1) as qty, 'kg' as unit, SUM(arang_penjualan.grand_total) as omzet")
            ->groupBy('arang_jenis.nama')
            ->havingRaw('SUM(arang_penjualan.berat_kg) > 0')
            ->get()
            ->map(fn ($r) => [
                'name' => 'Arang ' . $r->raw_name,
                'qty' => $r->qty,
                'unit' => 'kg',
                'omzet' => (int) $r->omzet,
            ]);

        $topProducts = $topItems->concat($topArang)->sortByDesc('omzet')->take(10)->values()->all();

        // 6. Transaksi Terakhir Terpadu
        $recentToko = (clone $salesInRange)
            ->with('user:id,name')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'type' => 'toko',
                'type_label' => 'Toko',
                'invoice_no' => $s->invoice_no,
                'receipt_url' => route('pos.receipt', $s->id),
                'user_name' => $s->user?->name ?? 'Kasir',
                'total' => (int) $s->total,
                'created_at' => $s->created_at,
            ]);

        $recentArang = (clone $arangJualInRange)
            ->with('user:id,name')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (ArangPenjualan $a) => [
                'id' => $a->id,
                'type' => 'arang',
                'type_label' => 'Arang',
                'invoice_no' => $a->no_nota,
                'receipt_url' => route('arang.penjualan.receipt', $a->id),
                'user_name' => $a->user?->name ?? 'Kasir',
                'total' => (int) $a->grand_total,
                'created_at' => $a->created_at,
            ]);

        $recent = $recentToko->merge($recentArang)->sortByDesc('created_at')->take(20)->values();

        // 7. Piutang Total (Toko + Arang)
        $piutangToko = Sale::unpaid()->get()->sum(fn (Sale $s) => $s->outstanding());
        $piutangArang = (int) ArangPenjualan::where('status', 'belum_lunas')->sum(DB::raw('grand_total - paid'));
        $piutangTotal = $piutangToko + $piutangArang;

        return Inertia::render('Reports/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $summary,
            'breakdown' => $breakdown,
            'daily' => $daily,
            'topProducts' => $topProducts,
            'recent' => $recent,
            'piutangTotal' => $piutangTotal,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from') ?: Carbon::today();
        $to = $request->date('to') ?: Carbon::today();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $store = Setting::values();
        $storeName = $store['store_name'] ?? 'Kios BERKAH';

        $filename = 'Laporan-'.Str::slug($storeName).'-'.$from->format('Ymd').'-sd-'.$to->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($from, $to, $storeName) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM untuk Microsoft Excel compatibility
            fwrite($out, "\xEF\xBB\xBF");

            // --- HEADER LAPORAN ---
            fputcsv($out, ['LAPORAN PENJUALAN & KEUANGAN TERPADU']);
            fputcsv($out, ['Nama Toko', $storeName]);
            fputcsv($out, ['Periode', $from->format('d/m/Y') . ' s/d ' . $to->format('d/m/Y')]);
            fputcsv($out, ['Waktu Unduh', now()->format('d/m/Y H:i:s')]);
            fputcsv($out, []);

            // 1. Data Toko Eceran
            $salesInRange = Sale::valid()->whereBetween('created_at', [$from, $to]);
            $tokoCount = (clone $salesInRange)->count();
            $tokoOmzet = (int) (clone $salesInRange)->sum(DB::raw('total - refunded'));
            $tokoDiscount = (int) (clone $salesInRange)->sum('discount');

            $itemsInRange = SaleItem::whereHas(
                'sale',
                fn ($q) => $q->valid()->whereBetween('created_at', [$from, $to]),
            );
            $tokoProfit = (int) (clone $itemsInRange)
                ->selectRaw('COALESCE(SUM((CAST(price AS SIGNED) - CAST(cost AS SIGNED)) * CAST(qty - returned_qty AS SIGNED)), 0) as p')
                ->value('p');

            // 2. Data Modul Arang
            $arangJualInRange = ArangPenjualan::whereBetween('created_at', [$from, $to]);
            $arangBeliInRange = ArangPembelian::whereBetween('created_at', [$from, $to]);

            $arangCount = (clone $arangJualInRange)->count();
            $arangOmzet = (int) (clone $arangJualInRange)->sum('grand_total');
            $arangDiscount = (int) (clone $arangJualInRange)->sum('diskon');
            $arangKg = (float) (clone $arangJualInRange)->sum('berat_kg');
            $arangBeliStok = (int) (clone $arangBeliInRange)->sum('total_harga');

            $arangProfit = (int) DB::table('arang_penjualan')
                ->join('arang_jenis', 'arang_penjualan.arang_jenis_id', '=', 'arang_jenis.id')
                ->whereBetween('arang_penjualan.created_at', [$from, $to])
                ->selectRaw('COALESCE(SUM(CAST(arang_penjualan.grand_total AS SIGNED) - (arang_penjualan.berat_kg * arang_jenis.harga_beli_default)), 0) as p')
                ->value('p');

            // --- RINGKASAN EKSEKUTIF ---
            fputcsv($out, ['=== RINGKASAN EKSEKUTIF KEUANGAN ===']);
            fputcsv($out, ['Indikator', 'Nilai Gabungan', 'Toko Eceran', 'Modul Arang']);
            fputcsv($out, [
                'Total Omzet Penjualan',
                $tokoOmzet + $arangOmzet,
                $tokoOmzet,
                $arangOmzet,
            ]);
            fputcsv($out, [
                'Estimasi Laba Kotor',
                $tokoProfit + $arangProfit,
                $tokoProfit,
                $arangProfit,
            ]);
            fputcsv($out, [
                'Jumlah Transaksi',
                $tokoCount + $arangCount,
                $tokoCount,
                $arangCount,
            ]);
            fputcsv($out, [
                'Total Diskon Diberikan',
                $tokoDiscount + $arangDiscount,
                $tokoDiscount,
                $arangDiscount,
            ]);
            fputcsv($out, [
                'Volume Arang Terjual (kg)',
                $arangKg . ' kg',
                '-',
                $arangKg . ' kg',
            ]);
            fputcsv($out, [
                'Pembelian Stok Arang dari Pembuat',
                $arangBeliStok,
                '-',
                $arangBeliStok,
            ]);
            fputcsv($out, []);

            // --- RINCIAN PENJUALAN BARANG TOKO ECERAN ---
            fputcsv($out, ['=== RINCIAN PENJUALAN TOKO ECERAN ===']);
            fputcsv($out, [
                'No. Nota',
                'Waktu Transaksi',
                'Kasir',
                'Pelanggan',
                'Metode Pembayaran',
                'Status',
                'Subtotal (Rp)',
                'Diskon (Rp)',
                'Total (Rp)',
            ]);

            $sales = (clone $salesInRange)
                ->with(['user:id,name', 'customer:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($sales as $s) {
                fputcsv($out, [
                    $s->invoice_no,
                    $s->created_at->format('d/m/Y H:i'),
                    $s->user?->name ?? '-',
                    $s->customer?->name ?? 'Umum',
                    strtoupper($s->payment_type),
                    $s->status === 'lunas' ? 'Lunas' : 'Belum Lunas',
                    $s->subtotal,
                    $s->discount,
                    $s->total,
                ]);
            }
            fputcsv($out, []);

            // --- RINCIAN PENJUALAN ARANG KILOAN ---
            fputcsv($out, ['=== RINCIAN PENJUALAN ARANG KILOAN ===']);
            fputcsv($out, [
                'No. Nota',
                'Waktu Transaksi',
                'Kasir',
                'Pembeli / Pelanggan',
                'Varian Arang',
                'Berat (kg)',
                'Harga / kg (Rp)',
                'Diskon (Rp)',
                'Grand Total (Rp)',
                'Metode Pembayaran',
                'Status',
                'Catatan',
            ]);

            $arangJuals = (clone $arangJualInRange)
                ->with(['user:id,name', 'customer:id,name', 'arangJenis:id,nama'])
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($arangJuals as $aj) {
                fputcsv($out, [
                    $aj->no_nota,
                    $aj->created_at->format('d/m/Y H:i'),
                    $aj->user?->name ?? '-',
                    $aj->customer?->name ?? ($aj->nama_pembeli ?: 'Umum'),
                    $aj->arangJenis?->nama ?? 'Arang',
                    $aj->berat_kg,
                    $aj->harga_jual_per_kg,
                    $aj->diskon,
                    $aj->grand_total,
                    strtoupper($aj->payment_type),
                    $aj->status === 'lunas' ? 'Lunas' : 'Belum Lunas',
                    $aj->catatan ?? '-',
                ]);
            }
            fputcsv($out, []);

            // --- RINCIAN PEMBELIAN STOK ARANG ---
            fputcsv($out, ['=== RINCIAN PEMBELIAN STOK ARANG DARI PEMBUAT ===']);
            fputcsv($out, [
                'No. Bukti',
                'Waktu Pembelian',
                'Penerima / Kasir',
                'Nama Pembuat / Pemasok',
                'Varian Arang',
                'Berat (kg)',
                'Harga Beli / kg (Rp)',
                'Total Bayar (Rp)',
                'Catatan',
            ]);

            $arangBelis = (clone $arangBeliInRange)
                ->with(['user:id,name', 'arangJenis:id,nama'])
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($arangBelis as $ab) {
                fputcsv($out, [
                    'BELI-ARNG-' . str_pad((string) $ab->id, 4, '0', STR_PAD_LEFT),
                    $ab->created_at->format('d/m/Y H:i'),
                    $ab->user?->name ?? '-',
                    $ab->nama_pemasok,
                    $ab->arangJenis?->nama ?? 'Arang',
                    $ab->berat_kg,
                    $ab->harga_beli_per_kg,
                    $ab->total_harga,
                    $ab->catatan ?? '-',
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
