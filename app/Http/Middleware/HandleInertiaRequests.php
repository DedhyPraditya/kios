<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\Setting;
use App\Support\Peringatan;
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

                $lowStockQuery = Peringatan::stokMenipis($user->id);
                $dueDebtsQuery = Peringatan::kasbonJatuhTempo($user->id);

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
                                'is_overdue' => (bool) $sale->due_date?->lt(today()),
                            ];
                        }),
                    'total' => $lowStockCount + $dueDebtsCount,
                    'lowStock' => $lowStockCount,
                ];
            },
        ];
    }
}
