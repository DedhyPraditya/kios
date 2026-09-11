<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\ArangPenjualan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArangController extends Controller
{
    public function index()
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $jenisList = ArangJenis::orderBy('nama')->get();

        $beliBulanIniKg = (float) ArangPembelian::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('berat_kg');
        $beliBulanIniRp = (int) ArangPembelian::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('total_harga');

        $jualBulanIniKg = (float) ArangPenjualan::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('berat_kg');
        $jualBulanIniRp = (int) ArangPenjualan::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->sum('grand_total');

        $latestBeli = ArangPembelian::with(['arangJenis', 'user:id,name'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id' => 'beli-' . $p->id,
                'raw_id' => $p->id,
                'type' => 'beli',
                'tanggal' => $p->tanggal->format('Y-m-d'),
                'jenis' => $p->arangJenis?->nama ?? '-',
                'pihak' => $p->nama_pemasok,
                'berat_kg' => (float) $p->berat_kg,
                'harga_per_kg' => $p->harga_beli_per_kg,
                'total' => $p->total_harga,
                'payment_type' => 'tunai',
                'status' => 'lunas',
                'user' => $p->user?->name ?? '-',
                'created_at' => $p->created_at,
            ]);

        $latestJual = ArangPenjualan::with(['arangJenis', 'user:id,name', 'customer:id,name'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($j) => [
                'id' => 'jual-' . $j->id,
                'raw_id' => $j->id,
                'type' => 'jual',
                'no_nota' => $j->no_nota,
                'tanggal' => $j->tanggal->format('Y-m-d'),
                'jenis' => $j->arangJenis?->nama ?? '-',
                'pihak' => $j->customer?->name ?? ($j->nama_pembeli ?: 'Umum'),
                'berat_kg' => (float) $j->berat_kg,
                'harga_per_kg' => $j->harga_jual_per_kg,
                'total' => $j->grand_total,
                'payment_type' => $j->payment_type,
                'status' => $j->status,
                'user' => $j->user?->name ?? '-',
                'created_at' => $j->created_at,
            ]);

        $recentTransactions = $latestBeli->concat($latestJual)
            ->sortByDesc('created_at')
            ->values()
            ->take(10);

        return Inertia::render('Arang/Index', [
            'jenisList' => $jenisList,
            'summary' => [
                'beli_kg' => $beliBulanIniKg,
                'beli_rp' => $beliBulanIniRp,
                'jual_kg' => $jualBulanIniKg,
                'jual_rp' => $jualBulanIniRp,
                'margin_rp' => $jualBulanIniRp - $beliBulanIniRp,
                'total_stok_kg' => round($jenisList->sum('stok_kg'), 2),
            ],
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function riwayat(Request $request)
    {
        $tab = $request->query('tab', 'semua');
        $jenisId = $request->query('jenis_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $jenisList = ArangJenis::orderBy('nama')->get();

        $beliQuery = ArangPembelian::with(['arangJenis', 'user:id,name']);
        $jualQuery = ArangPenjualan::with(['arangJenis', 'user:id,name', 'customer:id,name']);

        if ($jenisId) {
            $beliQuery->where('arang_jenis_id', $jenisId);
            $jualQuery->where('arang_jenis_id', $jenisId);
        }

        if ($startDate) {
            $beliQuery->whereDate('tanggal', '>=', $startDate);
            $jualQuery->whereDate('tanggal', '>=', $startDate);
        }

        if ($endDate) {
            $beliQuery->whereDate('tanggal', '<=', $endDate);
            $jualQuery->whereDate('tanggal', '<=', $endDate);
        }

        $items = collect();

        if ($tab === 'semua' || $tab === 'beli') {
            $beliItems = $beliQuery->latest('tanggal')->latest('id')->take(100)->get()->map(fn ($p) => [
                'id' => 'beli-' . $p->id,
                'raw_id' => $p->id,
                'type' => 'beli',
                'no_nota' => '-',
                'tanggal' => $p->tanggal->format('Y-m-d'),
                'jenis' => $p->arangJenis?->nama ?? '-',
                'pihak' => $p->nama_pemasok,
                'berat_kg' => (float) $p->berat_kg,
                'harga_per_kg' => $p->harga_beli_per_kg,
                'total' => $p->total_harga,
                'payment_type' => 'tunai',
                'status' => 'lunas',
                'user' => $p->user?->name ?? '-',
                'catatan' => $p->catatan,
                'created_at' => $p->created_at,
            ]);
            $items = $items->concat($beliItems);
        }

        if ($tab === 'semua' || $tab === 'jual') {
            $jualItems = $jualQuery->latest('tanggal')->latest('id')->take(100)->get()->map(fn ($j) => [
                'id' => 'jual-' . $j->id,
                'raw_id' => $j->id,
                'type' => 'jual',
                'no_nota' => $j->no_nota,
                'tanggal' => $j->tanggal->format('Y-m-d'),
                'jenis' => $j->arangJenis?->nama ?? '-',
                'pihak' => $j->customer?->name ?? ($j->nama_pembeli ?: 'Umum'),
                'berat_kg' => (float) $j->berat_kg,
                'harga_per_kg' => $j->harga_jual_per_kg,
                'total' => $j->grand_total,
                'payment_type' => $j->payment_type,
                'status' => $j->status,
                'user' => $j->user?->name ?? '-',
                'catatan' => $j->catatan,
                'created_at' => $j->created_at,
            ]);
            $items = $items->concat($jualItems);
        }

        $sortedItems = $items->sortByDesc('created_at')->values();

        return Inertia::render('Arang/Riwayat', [
            'transactions' => $sortedItems,
            'jenisList' => $jenisList,
            'filters' => [
                'tab' => $tab,
                'jenis_id' => $jenisId ? (int) $jenisId : null,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'totals' => [
                'total_kg' => round($sortedItems->sum('berat_kg'), 2),
                'total_rp' => $sortedItems->sum('total'),
            ],
        ]);
    }
}
