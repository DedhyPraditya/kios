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
            'alerts' => fn () => $request->user()?->isAdmin() ? [
                'lowStockCount' => Product::whereColumn('stock', '<=', 'low_stock')->count(),
                'lowStockItems' => Product::whereColumn('stock', '<=', 'low_stock')
                    ->select(['id', 'name', 'stock', 'low_stock'])
                    ->orderBy('stock', 'asc')
                    ->limit(5)
                    ->get(),
                'dueDebtsCount' => \App\Models\Sale::unpaid()
                    ->whereNotNull('due_date')
                    ->where('due_date', '<=', now()->addDays(3)->toDateString())
                    ->count(),
                'dueDebtsItems' => \App\Models\Sale::unpaid()
                    ->with('customer:id,name')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<=', now()->addDays(3)->toDateString())
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
                            'is_overdue' => $sale->due_date?->isPast() && !$sale->due_date?->isToday(),
                        ];
                    }),
                'total' => Product::whereColumn('stock', '<=', 'low_stock')->count()
                    + \App\Models\Sale::unpaid()->whereNotNull('due_date')->where('due_date', '<=', now()->addDays(3)->toDateString())->count(),
                'lowStock' => Product::whereColumn('stock', '<=', 'low_stock')->count(),
            ] : null,
        ];
    }
}
