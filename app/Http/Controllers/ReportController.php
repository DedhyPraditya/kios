<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?: Carbon::today();
        $to = $request->date('to') ?: Carbon::today();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $salesInRange = Sale::valid()->whereBetween('created_at', [$from, $to]);

        $summary = [
            'count' => (clone $salesInRange)->count(),
            'omzet' => (int) (clone $salesInRange)->sum(DB::raw('total - refunded')),
            'discount' => (int) (clone $salesInRange)->sum('discount'),
            'refunded' => (int) (clone $salesInRange)->sum('refunded'),
        ];

        // Barang yang sudah diretur tidak ikut dihitung sebagai penjualan.
        $itemsInRange = SaleItem::whereHas(
            'sale',
            fn ($q) => $q->valid()->whereBetween('created_at', [$from, $to]),
        );

        $summary['profit'] = (int) (clone $itemsInRange)
            ->selectRaw('COALESCE(SUM((price - cost) * (qty - returned_qty)), 0) as p')
            ->value('p');

        $daily = (clone $salesInRange)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as trx, SUM(total - refunded) as omzet')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $topProducts = (clone $itemsInRange)
            ->selectRaw('name, SUM(qty - returned_qty) as qty, SUM(price * (qty - returned_qty)) as omzet')
            ->groupBy('name')
            ->havingRaw('SUM(qty - returned_qty) > 0')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $recent = (clone $salesInRange)
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get(['id', 'invoice_no', 'user_id', 'total', 'created_at']);

        // Total piutang saat ini — snapshot terkini, tak terikat rentang tanggal laporan.
        $piutangTotal = Sale::unpaid()->get()->sum(fn (Sale $s) => $s->outstanding());

        return Inertia::render('Reports/Index', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $summary,
            'daily' => $daily,
            'topProducts' => $topProducts,
            'recent' => $recent,
            'piutangTotal' => $piutangTotal,
        ]);
    }
}
