<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\ArangPenjualan;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Filter laporan dari query string: rentang tanggal, kasir, kategori.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?int, 3: ?int}
     */
    private function filters(Request $request): array
    {
        $from = ($request->date('from') ?: Carbon::today())->copy()->startOfDay();
        $to = ($request->date('to') ?: Carbon::today())->copy()->endOfDay();

        return [$from, $to, $request->integer('kasir') ?: null, $request->integer('kategori') ?: null];
    }

    /**
     * Semua angka laporan. Dipakai halaman Laporan, ekspor Excel, dan CSV
     * supaya ketiganya selalu sama.
     *
     * Filter kategori hanya berlaku untuk barang toko: arang tidak punya
     * kategori sehingga tidak ikut dihitung. Omzet per kategori dihitung dari
     * harga barang setelah diskon per barang (sebelum diskon nota), karena diskon nota tidak bisa
     * dibagi ke tiap kategori.
     */
    private function gatherReportData(Carbon $from, Carbon $to, ?int $kasirId = null, ?int $categoryId = null): array
    {
        $store = Setting::values();
        $storeName = $store['store_name'] ?? 'Kios BERKAH';

        $productIdsInCategory = $categoryId
            ? Product::withTrashed()->where('category_id', $categoryId)->select('id')
            : null;

        // 1. Toko Eceran
        $salesInRange = Sale::valid()
            ->whereBetween('created_at', [$from, $to])
            ->when($kasirId, fn ($q) => $q->where('user_id', $kasirId))
            ->when($categoryId, fn ($q) => $q->whereHas('items', fn ($i) => $i->whereIn('product_id', $productIdsInCategory)));

        $itemsInRange = SaleItem::whereHas(
            'sale',
            fn ($q) => $q->valid()
                ->whereBetween('created_at', [$from, $to])
                ->when($kasirId, fn ($q) => $q->where('user_id', $kasirId)),
        )->when($categoryId, fn ($q) => $q->whereIn('product_id', $productIdsInCategory));

        // Nilai bersih baris (sudah dikurangi diskon baris) untuk barang yang tidak diretur.
        $netLine = 'subtotal * (qty - returned_qty) / qty';

        $tokoCount = (clone $salesInRange)->count();
        if ($categoryId) {
            $tokoOmzet = (int) round((clone $itemsInRange)->sum(DB::raw($netLine)));
            $tokoDiscount = 0;
            $tokoRefunded = (int) round((clone $itemsInRange)->sum(DB::raw('subtotal * returned_qty / qty')));
        } else {
            $tokoOmzet = (int) (clone $salesInRange)->sum(DB::raw('total - refunded'));
            // Diskon nota + diskon per baris barang.
            $tokoDiscount = (int) (clone $salesInRange)->sum('discount')
                + (int) (clone $itemsInRange)->sum('discount');
            $tokoRefunded = (int) (clone $salesInRange)->sum('refunded');
        }

        // PPN yang dipungut; bagian barang yang diretur ikut dikurangi.
        $tokoTax = $categoryId ? 0 : (int) round((clone $salesInRange)
            ->where('tax', '>', 0)
            ->sum(DB::raw('tax * (total - refunded) / total')));

        $tokoProfit = (int) round((float) (clone $itemsInRange)
            ->selectRaw("COALESCE(SUM({$netLine} - cost * (qty - returned_qty)), 0) as p")
            ->value('p'));

        // 2. Modul Arang (tidak punya kategori, jadi kosong saat filter kategori dipakai)
        $arangFilter = fn ($q) => $q
            ->when($kasirId, fn ($q) => $q->where('user_id', $kasirId))
            ->when($categoryId, fn ($q) => $q->whereRaw('1 = 0'));
        $arangJualInRange = ArangPenjualan::whereBetween('arang_penjualan.created_at', [$from, $to])->tap($arangFilter);
        $arangBeliInRange = ArangPembelian::whereBetween('created_at', [$from, $to])->tap($arangFilter);

        $arangCount = (clone $arangJualInRange)->count();
        $arangOmzet = (int) (clone $arangJualInRange)->sum('grand_total');
        $arangDiscount = (int) (clone $arangJualInRange)->sum('diskon');
        $arangKg = (float) (clone $arangJualInRange)->sum('berat_kg');
        $arangBeliStok = (int) (clone $arangBeliInRange)->sum('total_harga');

        $arangProfit = (int) (clone $arangJualInRange)
            ->join('arang_jenis', 'arang_penjualan.arang_jenis_id', '=', 'arang_jenis.id')
            ->selectRaw('COALESCE(SUM(arang_penjualan.grand_total - (arang_penjualan.berat_kg * arang_jenis.harga_beli_default)), 0) as p')
            ->value('p');

        // 3. Ringkasan Terpadu
        $summary = [
            'count' => $tokoCount + $arangCount,
            'omzet' => $tokoOmzet + $arangOmzet,
            'profit' => $tokoProfit + $arangProfit,
            'discount' => $tokoDiscount + $arangDiscount,
            'refunded' => $tokoRefunded,
            'tax' => $tokoTax,
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
        $tokoDaily = $categoryId
            ? (clone $itemsInRange)
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->selectRaw('DATE(sales.created_at) as d, COUNT(DISTINCT sales.id) as trx, SUM(sale_items.subtotal * (sale_items.qty - sale_items.returned_qty) / sale_items.qty) as omzet')
                ->groupBy('d')
                ->toBase()
                ->get()
                ->keyBy('d')
            : (clone $salesInRange)
                ->selectRaw('DATE(created_at) as d, COUNT(*) as trx, SUM(total - refunded) as omzet')
                ->groupBy('d')
                ->get()
                ->keyBy('d');

        $arangDaily = (clone $arangJualInRange)
            ->selectRaw('DATE(arang_penjualan.created_at) as d, COUNT(*) as trx, SUM(grand_total) as omzet')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $allDates = $tokoDaily->keys()->merge($arangDaily->keys())->unique()->sort()->values();
        $daily = $allDates->map(function ($date) use ($tokoDaily, $arangDaily) {
            $tTrx = $tokoDaily[$date]->trx ?? 0;
            $tOmzet = (int) round((float) ($tokoDaily[$date]->omzet ?? 0));
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
            ->selectRaw("name, unit_name, SUM(qty - returned_qty) as qty, SUM({$netLine}) as omzet")
            ->groupBy('name', 'unit_name')
            ->havingRaw('SUM(qty - returned_qty) > 0')
            ->toBase()
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'qty' => (int) $r->qty,
                'unit' => $r->unit_name ?? 'pcs',
                'omzet' => (int) round($r->omzet),
            ]);

        $topArang = (clone $arangJualInRange)
            ->join('arang_jenis', 'arang_penjualan.arang_jenis_id', '=', 'arang_jenis.id')
            ->selectRaw("arang_jenis.nama as raw_name, ROUND(SUM(arang_penjualan.berat_kg), 1) as qty, 'kg' as unit, SUM(arang_penjualan.grand_total) as omzet")
            ->groupBy('arang_jenis.nama')
            ->havingRaw('SUM(arang_penjualan.berat_kg) > 0')
            ->toBase()
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
            ->toBase()
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
            ->toBase()
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

        $recent = $recentToko->concat($recentArang)->sortByDesc('created_at')->take(20)->values();

        // 7. Piutang Total (Toko + Arang)
        $piutangToko = Sale::unpaid()->get()->sum(fn (Sale $s) => $s->outstanding());
        $piutangArang = (int) ArangPenjualan::where('status', 'belum_lunas')->sum(DB::raw('grand_total - paid'));
        $piutang = [
            'total' => $piutangToko + $piutangArang,
            'toko' => $piutangToko,
            'arang' => $piutangArang,
        ];

        return [
            'storeName' => $storeName,
            'summary' => $summary,
            'breakdown' => $breakdown,
            'daily' => $daily,
            'topProducts' => $topProducts,
            'recent' => $recent,
            'piutang' => $piutang,
            'salesInRange' => $salesInRange,
            'arangJualInRange' => $arangJualInRange,
            'arangBeliInRange' => $arangBeliInRange,
        ];
    }

    public function index(Request $request)
    {
        [$from, $to, $kasirId, $categoryId] = $this->filters($request);

        $data = $this->gatherReportData($from, $to, $kasirId, $categoryId);

        return Inertia::render('Reports/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => ['kasir' => $kasirId, 'kategori' => $categoryId],
            'kasirList' => User::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'summary' => $data['summary'],
            'breakdown' => $data['breakdown'],
            'daily' => $data['daily'],
            'topProducts' => $data['topProducts'],
            'recent' => $data['recent'],
            'piutangTotal' => $data['piutang']['total'],
        ]);
    }

    public function export(Request $request, ReportExcelExportService $excelService)
    {
        if ($request->query('format') === 'csv') {
            return $this->exportCsv($request);
        }

        return $this->exportExcel($request, $excelService);
    }

    public function exportExcel(Request $request, ReportExcelExportService $excelService): StreamedResponse
    {
        [$from, $to, $kasirId, $categoryId] = $this->filters($request);

        $data = $this->gatherReportData($from, $to, $kasirId, $categoryId);

        $sales = (clone $data['salesInRange'])
            ->with(['user:id,name', 'customer:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        $arangJuals = (clone $data['arangJualInRange'])
            ->with(['user:id,name', 'customer:id,name', 'arangJenis:id,nama'])
            ->orderBy('created_at', 'desc')
            ->get();

        $arangBelis = (clone $data['arangBeliInRange'])
            ->with(['user:id,name', 'arangJenis:id,nama'])
            ->orderBy('created_at', 'desc')
            ->get();

        $spreadsheet = $excelService->generate(
            $data['storeName'],
            $from,
            $to,
            $data['summary'],
            $data['breakdown'],
            $data['daily'],
            $data['topProducts'],
            $sales,
            $arangJuals,
            $arangBelis,
            $data['piutang'],
            $this->keteranganFilter($kasirId, $categoryId)
        );

        $this->catatEkspor('Excel', $from, $to, $kasirId, $categoryId);
        $filename = 'Laporan-'.Str::slug($data['storeName']).'-'.$from->format('Ymd').'-sd-'.$to->format('Ymd').'.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /** Laporan siap cetak / arsip dalam PDF A4, isinya sama dengan halaman Laporan. */
    public function exportPdf(Request $request)
    {
        [$from, $to, $kasirId, $categoryId] = $this->filters($request);

        $data = $this->gatherReportData($from, $to, $kasirId, $categoryId);

        $pdf = Pdf::loadView('laporan.pdf', [
            'store' => Setting::values(),
            'storeName' => $data['storeName'],
            'from' => $from,
            'to' => $to,
            'filterNote' => $this->keteranganFilter($kasirId, $categoryId),
            'kategoriAktif' => (bool) $categoryId,
            'summary' => $data['summary'],
            'breakdown' => $data['breakdown'],
            'daily' => $data['daily'],
            'topProducts' => $data['topProducts'],
            'piutang' => $data['piutang'],
            'sales' => (clone $data['salesInRange'])->with(['user:id,name', 'customer:id,name'])->oldest()->get(),
            'arangJuals' => (clone $data['arangJualInRange'])->with(['user:id,name', 'customer:id,name', 'arangJenis:id,nama'])->oldest()->get(),
        ])->setPaper('a4');

        $this->catatEkspor('PDF', $from, $to, $kasirId, $categoryId);
        $filename = 'Laporan-'.Str::slug($data['storeName']).'-'.$from->format('Ymd').'-sd-'.$to->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$from, $to, $kasirId, $categoryId] = $this->filters($request);
        $data = $this->gatherReportData($from, $to, $kasirId, $categoryId);
        $storeName = $data['storeName'];
        $keteranganFilter = $this->keteranganFilter($kasirId, $categoryId);
        $this->catatEkspor('CSV', $from, $to, $kasirId, $categoryId);

        $filename = 'Laporan-'.Str::slug($storeName).'-'.$from->format('Ymd').'-sd-'.$to->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($from, $to, $storeName, $data, $keteranganFilter) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM untuk Microsoft Excel compatibility
            fwrite($out, "\xEF\xBB\xBF");

            // --- HEADER LAPORAN ---
            $this->csvRow($out, ['LAPORAN PENJUALAN & KEUANGAN TERPADU']);
            $this->csvRow($out, ['Nama Toko', $storeName]);
            $this->csvRow($out, ['Periode', $from->format('d/m/Y') . ' s/d ' . $to->format('d/m/Y')]);
            $this->csvRow($out, ['Waktu Unduh', now()->format('d/m/Y H:i:s')]);
            if ($keteranganFilter) {
                $this->csvRow($out, ['Filter', $keteranganFilter]);
            }
            $this->csvRow($out, []);

            $salesInRange = $data['salesInRange'];
            $arangJualInRange = $data['arangJualInRange'];
            $arangBeliInRange = $data['arangBeliInRange'];
            ['toko' => $toko, 'arang' => $arang] = $data['breakdown'];
            [$tokoOmzet, $tokoProfit, $tokoCount, $tokoDiscount] = [$toko['omzet'], $toko['profit'], $toko['count'], $toko['discount']];
            [$arangOmzet, $arangProfit, $arangCount, $arangDiscount] = [$arang['omzet'], $arang['profit'], $arang['count'], $arang['discount']];
            [$arangKg, $arangBeliStok] = [$arang['berat_kg'], $arang['beli_stok']];

            // --- RINGKASAN EKSEKUTIF ---
            $this->csvRow($out, ['[ RINGKASAN EKSEKUTIF KEUANGAN ]']);
            $this->csvRow($out, ['Indikator', 'Nilai Gabungan', 'Toko Eceran', 'Modul Arang']);
            $this->csvRow($out, [
                'Total Omzet Penjualan',
                $tokoOmzet + $arangOmzet,
                $tokoOmzet,
                $arangOmzet,
            ]);
            $this->csvRow($out, [
                'Estimasi Laba Kotor',
                $tokoProfit + $arangProfit,
                $tokoProfit,
                $arangProfit,
            ]);
            $this->csvRow($out, [
                'Jumlah Transaksi',
                $tokoCount + $arangCount,
                $tokoCount,
                $arangCount,
            ]);
            $this->csvRow($out, [
                'Total Diskon Diberikan',
                $tokoDiscount + $arangDiscount,
                $tokoDiscount,
                $arangDiscount,
            ]);
            $this->csvRow($out, [
                'PPN Dipungut (termasuk di omzet)',
                $data['summary']['tax'],
                $data['summary']['tax'],
                '-',
            ]);
            $this->csvRow($out, [
                'Volume Arang Terjual (kg)',
                $arangKg . ' kg',
                '-',
                $arangKg . ' kg',
            ]);
            $this->csvRow($out, [
                'Pembelian Stok Arang dari Pembuat',
                $arangBeliStok,
                '-',
                $arangBeliStok,
            ]);
            $this->csvRow($out, []);

            // --- RINCIAN PENJUALAN BARANG TOKO ECERAN ---
            $this->csvRow($out, ['[ RINCIAN PENJUALAN TOKO ECERAN ]']);
            $this->csvRow($out, [
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
                $this->csvRow($out, [
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
            $this->csvRow($out, []);

            // --- RINCIAN PENJUALAN ARANG KILOAN ---
            $this->csvRow($out, ['[ RINCIAN PENJUALAN ARANG KILOAN ]']);
            $this->csvRow($out, [
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
                $this->csvRow($out, [
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
            $this->csvRow($out, []);

            // --- RINCIAN PEMBELIAN STOK ARANG ---
            $this->csvRow($out, ['[ RINCIAN PEMBELIAN STOK ARANG DARI PEMBUAT ]']);
            $this->csvRow($out, [
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
                $this->csvRow($out, [
                    $ab->no_nota ?? 'BELI-ARNG-' . str_pad((string) $ab->id, 4, '0', STR_PAD_LEFT),
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

    private function catatEkspor(string $format, Carbon $from, Carbon $to, ?int $kasirId, ?int $categoryId): void
    {
        $filter = $this->keteranganFilter($kasirId, $categoryId);
        \App\Models\ActivityLog::record(
            'report.export',
            "Mengunduh laporan {$format} periode {$from->format('d/m/Y')} s/d {$to->format('d/m/Y')}".($filter ? " ({$filter})" : '')
        );
    }

    /** Keterangan filter untuk kop ekspor, mis. "Kasir: Budi, Kategori: Minuman". */
    private function keteranganFilter(?int $kasirId, ?int $categoryId): ?string
    {
        $bagian = array_filter([
            $kasirId ? 'Kasir: '.(User::whereKey($kasirId)->value('name') ?? '-') : null,
            $categoryId ? 'Kategori: '.(Category::whereKey($categoryId)->value('name') ?? '-').' (tanpa arang, sebelum diskon nota)' : null,
        ]);

        return $bagian ? implode(', ', $bagian) : null;
    }

    /**
     * Tulis satu baris CSV. Teks berawalan = + - @ diberi tanda kutip tunggal
     * agar tidak dijalankan sebagai rumus saat dibuka di Excel (formula
     * injection lewat nama pelanggan, pemasok, atau catatan).
     */
    private function csvRow($out, array $fields): void
    {
        $safe = array_map(function ($v) {
            if (! is_string($v) || $v === '' || is_numeric($v)) {
                return $v;
            }
            if (preg_match('/^[=+@\t\r]/', $v) || ($v[0] === '-' && strlen($v) > 1)) {
                return "'".$v;
            }

            return $v;
        }, $fields);

        fputcsv($out, $safe);
    }
}
