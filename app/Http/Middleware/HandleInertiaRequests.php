<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => (bool) $request->user()?->isAdmin(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'store' => fn () => Setting::values(),
            'alerts' => function () use ($request) {
                $user = $request->user();
                if (! $user?->isAdmin()) {
                    return null;
                }

                $userId = $user->id;

                // Query produk stok menipis (abaikan yang sudah ditandai dibaca kecuali stok turun lebih parah)
                $lowStockQuery = Product::whereColumn('stock', '<=', 'low_stock')
                    ->whereNotExists(function ($query) use ($userId) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('dismissed_alerts')
                            ->where('dismissed_alerts.user_id', $userId)
                            ->where('dismissed_alerts.type', 'product_stock')
                            ->whereColumn('dismissed_alerts.alertable_id', 'products.id')
                            ->whereColumn('dismissed_alerts.last_value', '<=', 'products.stock');
                    });

                // Query kasbon jatuh tempo (abaikan yang sudah ditandai dibaca)
                $dueDebtsQuery = \App\Models\Sale::unpaid()
                    ->whereNotNull('due_date')
                    ->where('due_date', '<=', now()->addDays(3)->toDateString())
                    ->whereNotExists(function ($query) use ($userId) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('dismissed_alerts')
                            ->where('dismissed_alerts.user_id', $userId)
                            ->where('dismissed_alerts.type', 'sale_due')
                            ->whereColumn('dismissed_alerts.alertable_id', 'sales.id');
                    });

                $lowStockCount = (clone $lowStockQuery)->count();
                $dueDebtsCount = (clone $dueDebtsQuery)->count();

                return [
                    'lowStockCount' => $lowStockCount,
                    'lowStockItems' => $lowStockQuery
                        ->select(['id', 'name', 'stock', 'low_stock'])
                        ->orderBy('stock', 'asc')
                        ->limit(5)
                        ->get(),
                    'dueDebtsCount' => $dueDebtsCount,
                    'dueDebtsItems' => $dueDebtsQuery
                        ->with('customer:id,name')
                        ->orderBy('due_date', 'asc')
                        ->limit(5)
                        ->get()
                        ->map(function ($sale) {
                            return [
                                'id' => $sale->id,
                                'invoice_no' => $sale->invoice_no,
                                'customer_name' => $sale->customer?->name ?? 'Tanpa nama',
                                'customer_id' => $sale->customer_id,
                                'due_date' => $sale->due_date?->format('d/m/Y'),
                                'outstanding' => $sale->outstanding(),
                                'is_overdue' => $sale->due_date?->isPast() && ! $sale->due_date?->isToday(),
                            ];
                        }),
                    'total' => $lowStockCount + $dueDebtsCount,
                    'lowStock' => $lowStockCount,
                ];
            },
        ];
    }
}
