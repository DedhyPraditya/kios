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
            'store' => Setting::values(),
            'products' => Product::active()
                ->with([
                    'category:id,name',
                    'units:id,product_id,name,isi,price,barcode',
                    'wholesalePrices:id,product_id,min_qty,price',
                ])
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
            'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', Rule::in(['tunai', 'kasbon', 'qris'])],
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
            $products = Product::whereIn('id', $ids)
                ->with(['units', 'wholesalePrices'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Satu baris per produk + satuan; baris kembar digabung.
            $rows = collect($data['items'])
                ->groupBy(fn ($r) => $r['id'].'-'.($r['unit_id'] ?? 0))
                ->map(fn ($rs) => [
                    'id' => $rs[0]['id'],
                    'unit_id' => $rs[0]['unit_id'] ?? null,
                    'qty' => $rs->sum('qty'),
                    'discount' => $rs->sum(fn ($r) => (int) ($r['discount'] ?? 0)),
                ])
                ->values();

            $subtotal = 0;
            $lines = [];
            $baseQtyById = [];

            foreach ($rows as $row) {
                $product = $products[$row['id']] ?? null;

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada produk di keranjang yang sudah dihapus. Muat ulang halaman kasir.',
                    ]);
                }

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => "{$product->name} sedang tidak dijual.",
                    ]);
                }

                $unit = null;
                if ($row['unit_id']) {
                    $unit = $product->units->firstWhere('id', $row['unit_id']);
                    if (! $unit) {
                        throw ValidationException::withMessages([
                            'items' => "Satuan untuk {$product->name} sudah berubah. Muat ulang halaman kasir.",
                        ]);
                    }
                }

                $qty = (int) $row['qty'];
                $isi = $unit?->isi ?? 1;
                // Harga dihitung ulang di server: harga satuan, atau harga grosir
                // bila jumlah satuan dasar mencapai minimal beli.
                $price = $unit ? $unit->price : $product->priceFor($qty);
                $gross = $price * $qty;
                $lineDiscount = min((int) $row['discount'], $gross);
                $lineSubtotal = $gross - $lineDiscount;
                $subtotal += $lineSubtotal;
                $baseQtyById[$product->id] = ($baseQtyById[$product->id] ?? 0) + $qty * $isi;

                $lines[] = [
                    'product_id' => $product->id,
                    'product_unit_id' => $unit?->id,
                    'name' => $product->name,
                    'unit_name' => $unit?->name,
                    'unit_isi' => $isi,
                    'price' => $price,
                    'cost' => $product->cost * $isi,
                    'qty' => $qty,
                    'discount' => $lineDiscount,
                    'subtotal' => $lineSubtotal,
                ];
            }

            foreach ($baseQtyById as $id => $baseQty) {
                if ($products[$id]->stock < $baseQty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$products[$id]->name} tidak cukup (sisa {$products[$id]->stock}).",
                    ]);
                }
            }

            // Diskon nota: persen (dihitung di server) atau nominal.
            $discountPercent = isset($data['discount_percent']) && $data['discount_percent'] > 0
                ? round((float) $data['discount_percent'], 2)
                : null;
            $discount = $discountPercent !== null
                ? (int) round($subtotal * $discountPercent / 100)
                : min((int) ($data['discount'] ?? 0), $subtotal);

            // PPN opsional, ditambahkan di atas total setelah diskon.
            $store = Setting::values();
            $taxRate = ! empty($store['tax_enabled']) ? max(0, min(100, (float) $store['tax_rate'])) : 0;
            $tax = (int) round(($subtotal - $discount) * $taxRate / 100);
            $total = $subtotal - $discount + $tax;
            $isKasbon = $data['payment_type'] === 'kasbon';
            $isQris = $data['payment_type'] === 'qris';
            $paid = (int) $data['paid'];

            if ($isQris) {
                $qrisImage = Setting::get('qris_image');
                if (empty($qrisImage)) {
                    throw ValidationException::withMessages([
                        'payment_type' => 'Gambar QRIS belum diunggah di Pengaturan toko. Silakan unggah QRIS terlebih dahulu.',
                    ]);
                }
                $paid = $total;
            }

            if (! $isKasbon && ! $isQris && $paid < $total) {
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
                'discount_percent' => $discountPercent,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
                'paid' => $paid,
                'change' => ($isKasbon || $isQris) ? 0 : $paid - $total,
                'due_date' => $isKasbon ? ($data['due_date'] ?? null) : null,
                'note' => $data['note'] ?? null,
            ]);

            $sale->items()->createMany($lines);

            foreach ($baseQtyById as $id => $qty) {
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

    public function receipt(Request $request, Sale $sale)
    {
        abort_unless($request->user()->canViewReceiptOf($sale->user_id), 403);

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
