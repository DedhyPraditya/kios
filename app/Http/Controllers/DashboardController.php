<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = Sale::valid()->whereDate('created_at', Carbon::today());

        return Inertia::render('Dashboard', [
            'stats' => [
                'today_omzet' => (int) (clone $today)->sum(DB::raw('total - refunded')),
                'today_trx' => (clone $today)->count(),
                'products' => Product::count(),
                'low_stock' => Product::whereColumn('stock', '<=', 'low_stock')->count(),
            ],
            'lowStockList' => Product::whereColumn('stock', '<=', 'low_stock')
                ->orderBy('stock')
                ->limit(10)
                ->get(['id', 'name', 'stock', 'low_stock']),
            'salesTrend' => $this->salesTrend(),
            'system' => [
                'serverTime' => Carbon::now()->isoFormat('HH:mm'),
                'lastSaleAt' => optional(Sale::valid()->latest('id')->first())->created_at?->isoFormat('D MMM, HH:mm'),
            ],
        ]);
    }

    /** Omzet & jumlah transaksi 7 hari terakhir, hari kosong tetap diisi 0. */
    private function salesTrend(): array
    {
        $rows = Sale::valid()
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->selectRaw('DATE(created_at) as d, SUM(total - refunded) as omzet, COUNT(*) as trx')
            ->groupBy('d')
            ->pluck('omzet', 'd');

        $trx = Sale::valid()
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as trx')
            ->groupBy('d')
            ->pluck('trx', 'd');

        return collect(range(6, 0))
            ->map(function (int $back) use ($rows, $trx) {
                $date = Carbon::today()->subDays($back);
                $key = $date->toDateString();

                return [
                    'date' => $key,
                    'label' => $date->isoFormat('dd'),
                    'omzet' => (int) ($rows[$key] ?? 0),
                    'trx' => (int) ($trx[$key] ?? 0),
                ];
            })
            ->all();
    }
}
