<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use App\Models\ArangPenjualan;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = Carbon::today();

        // 1. Omzet & Transaksi Hari Ini (Toko + Arang)
        $tokoToday = Sale::valid()->whereDate('created_at', $today);
        $tokoTodayOmzet = (int) (clone $tokoToday)->sum(DB::raw('total - refunded'));
        $tokoTodayTrx = (clone $tokoToday)->count();

        $arangToday = ArangPenjualan::whereDate('created_at', $today);
        $arangTodayOmzet = (int) (clone $arangToday)->sum('grand_total');
        $arangTodayTrx = (clone $arangToday)->count();

        $todayOmzet = $tokoTodayOmzet + $arangTodayOmzet;
        $todayTrx = $tokoTodayTrx + $arangTodayTrx;

        // 2. Stok Menipis (Produk Toko + Varian Arang <= 10 kg)
        $lowProducts = Product::whereColumn('stock', '<=', 'low_stock')
            ->orderBy('stock')
            ->limit(10)
            ->get(['id', 'name', 'stock', 'low_stock'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'sku' => 'PRD-' . str_pad((string) $p->id, 3, '0', STR_PAD_LEFT),
                'name' => $p->name,
                'stock' => $p->stock,
                'low_stock' => $p->low_stock,
                'unit' => 'pcs',
                'type' => 'toko',
            ]);

        $lowArang = ArangJenis::aktif()
            ->get()
            ->filter(fn ($aj) => $aj->stok_kg <= 10)
            ->map(fn ($aj) => [
                'id' => 'arang-' . $aj->id,
                'sku' => 'ARNG-' . str_pad((string) $aj->id, 3, '0', STR_PAD_LEFT),
                'name' => 'Arang ' . $aj->nama,
                'stock' => $aj->stok_kg,
                'low_stock' => 10,
                'unit' => 'kg',
                'type' => 'arang',
            ]);

        $totalLowStock = Product::whereColumn('stock', '<=', 'low_stock')->count() + $lowArang->count();
        $lowStockList = $lowProducts->concat($lowArang)->sortBy('stock')->take(10)->values();

        // 3. Transaksi Terakhir (Toko / Arang)
        $lastSale = Sale::valid()->latest('created_at')->first();
        $lastArang = ArangPenjualan::latest('created_at')->first();
        $lastTime = null;

        if ($lastSale && $lastArang) {
            $lastTime = $lastSale->created_at->gt($lastArang->created_at) ? $lastSale->created_at : $lastArang->created_at;
        } elseif ($lastSale) {
            $lastTime = $lastSale->created_at;
        } elseif ($lastArang) {
            $lastTime = $lastArang->created_at;
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'today_omzet' => $todayOmzet,
                'today_trx' => $todayTrx,
                'products' => Product::count(),
                'low_stock' => $totalLowStock,
            ],
            'lowStockList' => $lowStockList,
            'salesTrend' => $this->salesTrend(),
            'system' => [
                'serverTime' => Carbon::now()->isoFormat('HH:mm'),
                'lastSaleAt' => $lastTime ? $lastTime->isoFormat('D MMM, HH:mm') : null,
            ],
        ]);
    }

    /** Omzet & jumlah transaksi 7 hari terakhir terpadu (Toko + Arang). */
    private function salesTrend(): array
    {
        $since = Carbon::today()->subDays(6);

        $tokoRows = Sale::valid()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, SUM(total - refunded) as omzet, COUNT(*) as trx')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $arangRows = ArangPenjualan::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, SUM(grand_total) as omzet, COUNT(*) as trx')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        return collect(range(6, 0))
            ->map(function (int $back) use ($tokoRows, $arangRows) {
                $date = Carbon::today()->subDays($back);
                $key = $date->toDateString();

                $tOmzet = (int) ($tokoRows[$key]->omzet ?? 0);
                $tTrx = (int) ($tokoRows[$key]->trx ?? 0);
                $aOmzet = (int) ($arangRows[$key]->omzet ?? 0);
                $aTrx = (int) ($arangRows[$key]->trx ?? 0);

                return [
                    'date' => $key,
                    'label' => $date->isoFormat('dd'),
                    'omzet' => $tOmzet + $aOmzet,
                    'trx' => $tTrx + $aTrx,
                ];
            })
            ->all();
    }
}
