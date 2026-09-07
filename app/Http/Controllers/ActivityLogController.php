<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();
        $category = $request->string('category')->toString() ?: 'semua';
        $userId = $request->integer('user_id') ?: null;
        $startDate = $request->string('start_date')->toString();
        $endDate = $request->string('end_date')->toString();

        $logs = ActivityLog::query()
            ->with('user:id,name,role')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($category !== 'semua', function ($q) use ($category) {
                $q->where('action', 'like', "{$category}.%");
            })
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('d M Y, H:i:s'),
                'created_at_human' => $log->created_at?->diffForHumans(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'role' => $log->user->role,
                ] : [
                    'id' => null,
                    'name' => 'Sistem / Terhapus',
                    'role' => '—',
                ],
            ]);

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'category' => $category,
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'categories' => [
                'semua' => 'Semua Kategori',
                'product' => 'Produk',
                'sale' => 'Penjualan / Kasir',
                'stock' => 'Stok & Barang Masuk',
                'credit' => 'Piutang / Kasbon',
                'user' => 'Pengguna',
                'setting' => 'Pengaturan Toko',
                'backup' => 'Cadangan Database',
            ],
        ]);
    }
}
