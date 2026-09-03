<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index()
    {
        return Inertia::render('Pos/Index', [
            'products' => Product::active()
                ->with('category:id,name')
                ->orderBy('name')
                ->get(['id', 'category_id', 'barcode', 'name', 'price', 'stock', 'low_stock']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'customers' => Customer::where('is_blocked', false)
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'credit_limit'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'credit_limit' => $c->credit_limit,
                    'outstanding' => $c->outstanding(),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'paid' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', Rule::in(['tunai', 'kasbon'])],
            'customer_id' => [
                'nullable', 'exists:customers,id',
                Rule::requiredIf($request->input('payment_type') === 'kasbon'),
            ],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $data['payment_type'] ??= 'tunai';

        // Shift sifatnya opsional: kalau kebetulan ada yang terbuka, nota
        // menempel ke sana untuk rekap laci. Kalau tidak, penjualan tetap jalan.
        $shift = CashSession::openFor($request->user());

        $sale = DB::transaction(function () use ($data, $request, $shift) {
            $ids = collect($data['items'])->pluck('id');
            $products = Product::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $qtyById = collect($data['items'])
                ->groupBy('id')
                ->map(fn ($rows) => collect($rows)->sum('qty'));

            $subtotal = 0;
            $lines = [];

            foreach ($qtyById as $id => $qty) {
                $product = $products[$id];

                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$product->name} tidak cukup (sisa {$product->stock}).",
                    ]);
                }

                $lineSubtotal = $product->price * $qty;
                $subtotal += $lineSubtotal;

                $lines[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'cost' => $product->cost,
                    'qty' => $qty,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $discount = min((int) ($data['discount'] ?? 0), $subtotal);
            $total = $subtotal - $discount;
            $isKasbon = $data['payment_type'] === 'kasbon';
            $paid = (int) $data['paid'];

            if (! $isKasbon && $paid < $total) {
                throw ValidationException::withMessages([
                    'paid' => 'Uang bayar kurang dari total.',
                ]);
            }

            if ($isKasbon) {
                $paid = min($paid, $total); // DP tidak boleh melebihi total
                $customer = Customer::whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();

                if ($customer->is_blocked) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Pelanggan ini diblokir dari kasbon.',
                    ]);
                }

                if ($customer->credit_limit !== null) {
                    $sisaHutangBaru = $customer->outstanding() + ($total - $paid);
                    if ($sisaHutangBaru > $customer->credit_limit) {
                        throw ValidationException::withMessages([
                            'customer_id' => 'Melebihi batas kredit pelanggan (sisa hutang jadi Rp'
                                .number_format($sisaHutangBaru, 0, ',', '.').').',
                        ]);
                    }
                }
            }

            $sale = Sale::create([
                'invoice_no' => Sale::makeInvoiceNo(),
                'user_id' => $request->user()->id,
                'cash_session_id' => $shift?->id,
                'customer_id' => $isKasbon ? $data['customer_id'] : null,
                'payment_type' => $data['payment_type'],
                'status' => $isKasbon && ($total - $paid) > 0 ? 'belum_lunas' : 'lunas',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'change' => $isKasbon ? 0 : $paid - $total,
                'due_date' => $isKasbon ? ($data['due_date'] ?? null) : null,
                'note' => $data['note'] ?? null,
            ]);

            $sale->items()->createMany($lines);

            foreach ($qtyById as $id => $qty) {
                StockMovement::apply($products[$id], -$qty, 'penjualan', [
                    'user_id' => $request->user()->id,
                    'sale_id' => $sale->id,
                    'note' => 'Nota '.$sale->invoice_no,
                ]);
            }

            return $sale;
        });

        return redirect()->route('pos.receipt', $sale);
    }

    public function receipt(Sale $sale)
    {
        $sale->load(['items', 'user:id,name', 'customer:id,name,phone']);

        return Inertia::render('Pos/Receipt', [
            'store' => Setting::values(),
            'sale' => [
                ...$sale->toArray(),
                'due_date' => $sale->due_date?->isoFormat('D MMM YYYY'),
                'outstanding' => $sale->outstanding(),
                'voided' => $sale->isVoided(),
            ],
        ]);
    }
}
