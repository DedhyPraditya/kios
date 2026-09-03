<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    /** Halaman "Barang masuk": form penerimaan + histori pergerakan stok. */
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'type' => $request->input('type', 'semua'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        $movements = StockMovement::query()
            ->with(['product:id,name,barcode', 'user:id,name'])
            ->when($filters['q'], fn ($q, $term) => $q->whereHas(
                'product',
                fn ($p) => $p->where('name', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%")
            ))
            ->when($filters['type'] !== 'semua', fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['from'], fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['to'], fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (StockMovement $m) => [
                'id' => $m->id,
                'created_at' => $m->created_at->isoFormat('D MMM YYYY, HH:mm'),
                'product' => $m->product?->name ?? '—',
                'type' => $m->type,
                'type_label' => StockMovement::LABELS[$m->type] ?? $m->type,
                'qty' => $m->qty,
                'stock_after' => $m->stock_after,
                'cost' => $m->cost,
                'supplier' => $m->supplier,
                'user' => $m->user?->name,
                'note' => $m->note,
                'sale_id' => $m->sale_id,
            ]);

        // Nilai barang yang masuk hari ini — untuk kartu ringkasan.
        $todayIn = StockMovement::where('type', 'masuk')
            ->whereDate('created_at', today())
            ->get();

        return Inertia::render('Stock/Index', [
            'movements' => $movements,
            'filters' => $filters,
            'types' => StockMovement::LABELS,
            'products' => Product::orderBy('name')->get(['id', 'name', 'barcode', 'stock', 'cost']),
            'summary' => [
                'today_qty' => (int) $todayIn->sum('qty'),
                'today_value' => (int) $todayIn->sum(fn ($m) => (int) $m->cost * $m->qty),
                'low_stock' => Product::whereColumn('stock', '<=', 'low_stock')->count(),
            ],
        ]);
    }

    /** Catat barang masuk (pembelian) atau penyesuaian stok manual. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['masuk', 'penyesuaian'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer'],
            'items.*.cost' => ['nullable', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['items'] as $row) {
                $qty = (int) $row['qty'];

                if ($qty === 0) {
                    continue;
                }

                if ($data['type'] === 'masuk' && $qty < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Jumlah barang masuk tidak boleh minus. Pakai jenis "Penyesuaian" untuk mengurangi.',
                    ]);
                }

                $product = Product::whereKey($row['product_id'])->lockForUpdate()->firstOrFail();

                if ($product->stock + $qty < 0) {
                    throw ValidationException::withMessages([
                        'items' => "Penyesuaian membuat stok {$product->name} jadi minus (stok sekarang {$product->stock}).",
                    ]);
                }

                // Harga modal ikut diperbarui saat barang masuk dengan harga baru.
                $cost = isset($row['cost']) ? (int) $row['cost'] : null;

                if ($data['type'] === 'masuk' && $cost !== null && $cost > 0 && $cost !== $product->cost) {
                    $product->cost = $cost;
                }

                StockMovement::apply($product, $qty, $data['type'], [
                    'user_id' => $request->user()->id,
                    'cost' => $cost,
                    'supplier' => $data['supplier'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Pergerakan stok dicatat.');
    }
}
