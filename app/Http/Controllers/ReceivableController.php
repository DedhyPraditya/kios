<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ReceivableController extends Controller
{
    public function index()
    {
        $unpaid = Sale::query()
            ->unpaid()
            ->with('customer:id,name,phone')
            ->orderBy('created_at')
            ->get();

        $buckets = ['0-7' => 0, '8-30' => 0, '31+' => 0];
        $rows = [];
        $byCustomer = [];
        $total = 0;

        foreach ($unpaid as $sale) {
            $outstanding = $sale->outstanding();
            if ($outstanding <= 0) {
                continue;
            }

            $ageDays = (int) $sale->created_at->startOfDay()->diffInDays(Carbon::today());
            $bucket = $ageDays <= 7 ? '0-7' : ($ageDays <= 30 ? '8-30' : '31+');
            $buckets[$bucket] += $outstanding;
            $total += $outstanding;

            $cid = $sale->customer_id;
            $byCustomer[$cid]['name'] ??= $sale->customer?->name ?? '—';
            $byCustomer[$cid]['phone'] ??= $sale->customer?->phone;
            $byCustomer[$cid]['outstanding'] = ($byCustomer[$cid]['outstanding'] ?? 0) + $outstanding;
            $byCustomer[$cid]['oldest'] = min(
                $byCustomer[$cid]['oldest'] ?? $sale->created_at->toDateString(),
                $sale->created_at->toDateString(),
            );

            $rows[] = [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'customer' => $sale->customer?->name ?? '—',
                'customer_id' => $sale->customer_id,
                'created_at' => $sale->created_at->toDateString(),
                'due_date' => $sale->due_date?->toDateString(),
                'overdue' => $sale->due_date && $sale->due_date->isPast(),
                'age_days' => $ageDays,
                'outstanding' => $outstanding,
            ];
        }

        return Inertia::render('Piutang/Index', [
            'total' => $total,
            'buckets' => $buckets,
            'customers' => collect($byCustomer)
                ->map(fn ($v, $k) => [...$v, 'id' => $k])
                ->sortByDesc('outstanding')
                ->values(),
            'sales' => $rows,
        ]);
    }
}
