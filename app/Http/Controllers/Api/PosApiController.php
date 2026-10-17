<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosApiController extends Controller
{
    /**
     * Mengambil data inisialisasi Kasir POS (Produk, Kategori, Pelanggan, Profil Toko).
     */
    public function index(): JsonResponse
    {
        $products = Product::active()
            ->with([
                'category:id,name',
                'units:id,product_id,name,isi,price,barcode',
                'wholesalePrices:id,product_id,min_qty,price',
            ])
            ->orderBy('name')
            ->get(['id', 'category_id', 'barcode', 'name', 'price', 'cost', 'stock', 'low_stock']);

        $categories = Category::orderBy('name')->get(['id', 'name']);

        $customers = Customer::where('is_blocked', false)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'credit_limit'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'credit_limit' => $c->credit_limit,
                'outstanding' => $c->outstanding(),
            ]);

        $store = Setting::values();

        return response()->json([
            'success' => true,
            'products' => $products,
            'categories' => $categories,
            'customers' => $customers,
            'store' => $store,
        ]);
    }

    /**
     * Memproses transaksi penjualan langsung dari aplikasi Flutter.
     */
    public function store(Request $request): JsonResponse
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
            'customer_id' => ['nullable', 'exists:customers,id'],
            'due_date' => ['nullable', 'date'],
            'cashier_id' => ['nullable', 'integer'],
        ]);

        $cashierId = $data['cashier_id'] ?? User::first()->id ?? 1;

        $sale = DB::transaction(function () use ($data, $cashierId) {
            $productIds = collect($data['items'])->pluck('id');
            $products = Product::with(['units', 'wholesalePrices'])->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            // Cek ketersediaan stok
            foreach ($data['items'] as $item) {
                $product = $products->get($item['id']);
                $factor = 1;
                if (!empty($item['unit_id'])) {
                    $unit = $product->units->firstWhere('id', $item['unit_id']);
                    $factor = $unit ? $unit->isi : 1;
                }
                $requiredStock = $item['qty'] * $factor;
                if ($product->stock < $requiredStock) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok '{$product->name}' tidak cukup. Tersisa: {$product->stock}"],
                    ]);
                }
            }

            // Hitung subtotal
            $subtotal = 0;
            $itemsToInsert = [];
            foreach ($data['items'] as $item) {
                $product = $products->get($item['id']);
                $unitPrice = $product->price;
                $factor = 1;
                $unitName = null;

                if (!empty($item['unit_id'])) {
                    $unit = $product->units->firstWhere('id', $item['unit_id']);
                    if ($unit) {
                        $unitPrice = $unit->price;
                        $factor = $unit->isi;
                        $unitName = $unit->name;
                    }
                } else {
                    $unitPrice = $product->priceFor($item['qty']);
                }

                $itemSubtotal = ($unitPrice * $item['qty']) - ($item['discount'] ?? 0);
                $subtotal += $itemSubtotal;

                $itemsToInsert[] = [
                    'product_id' => $product->id,
                    'product_unit_id' => $item['unit_id'] ?? null,
                    'name' => $product->name,
                    'unit_name' => $unitName,
                    'unit_isi' => $factor,
                    'price' => $unitPrice,
                    'cost' => $product->cost * $factor,
                    'qty' => $item['qty'],
                    'discount' => $item['discount'] ?? 0,
                    'subtotal' => $itemSubtotal,
                    'factor' => $factor,
                ];
            }

            $discount = $data['discount'] ?? 0;
            $total = max(0, $subtotal - $discount);
            $paid = (int) $data['paid'];
            $isKasbon = ($data['payment_type'] ?? 'tunai') === 'kasbon';
            $isQris = ($data['payment_type'] ?? 'tunai') === 'qris';

            if ($isQris) {
                $paid = $total;
                $change = 0;
            } elseif ($isKasbon) {
                $paid = min($paid, $total); // DP tidak melebihi total
                $change = 0;

                if (!empty($data['customer_id'])) {
                    $customer = Customer::find($data['customer_id']);
                    if ($customer && $customer->credit_limit !== null) {
                        $sisaHutangBaru = $customer->outstanding() + ($total - $paid);
                        if ($sisaHutangBaru > $customer->credit_limit) {
                            throw ValidationException::withMessages([
                                'customer_id' => ['Melebihi batas kredit pelanggan (sisa hutang jadi Rp' . number_format($sisaHutangBaru, 0, ',', '.') . ').'],
                            ]);
                        }
                    }
                }
            } else {
                $change = max(0, $paid - $total);
            }

            $isPaid = !$isKasbon || ($total - $paid) <= 0;

            $sale = Sale::create([
                'user_id' => $cashierId,
                'customer_id' => $isKasbon ? ($data['customer_id'] ?? null) : null,
                'invoice_no' => Sale::makeInvoiceNo(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discount_percent' => $data['discount_percent'] ?? null,
                'total' => $total,
                'paid' => $paid,
                'change' => $change,
                'payment_type' => $data['payment_type'] ?? 'tunai',
                'status' => $isPaid ? 'lunas' : 'belum_lunas',
                'due_date' => $isKasbon ? ($data['due_date'] ?? null) : null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($itemsToInsert as $ins) {
                $factor = $ins['factor'];
                unset($ins['factor']);
                $ins['sale_id'] = $sale->id;
                SaleItem::create($ins);

                // Potong stok produk & catat riwayat mutasi
                $product = $products->get($ins['product_id']);
                StockMovement::apply($product, -($ins['qty'] * $factor), 'penjualan', [
                    'user_id' => $cashierId,
                    'sale_id' => $sale->id,
                    'note' => 'Nota ' . $sale->invoice_no,
                ]);
            }

            \App\Models\ActivityLog::record(
                'sale.create',
                "Penjualan mobile nota {$sale->invoice_no} " . strtoupper($sale->payment_type) . ' Rp' . number_format($sale->total, 0, ',', '.'),
                $sale,
                ['invoice_no' => $sale->invoice_no, 'total' => $sale->total, 'payment_type' => $sale->payment_type, 'items' => count($data['items'])]
            );

            return $sale;
        });

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil disimpan!',
            'sale' => $sale->load(['items', 'customer']),
        ]);
    }

    /**
     * Riwayat Transaksi Penjualan.
     */
    public function sales(): JsonResponse
    {
        $sales = Sale::with(['items', 'customer', 'user:id,name'])
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'sales' => $sales,
        ]);
    }

    /**
     * Autentikasi Pengguna Kasir / Admin Mobile.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau kata sandi tidak cocok.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            // Token string
            'token' => 'mobile_session_' . md5($user->id . time() . 'kios_berkah'),
        ]);
    }

    /**
     * Ringkasan Dashboard untuk Beranda Mobile.
     */
    public function dashboard(): JsonResponse
    {
        $today = \Carbon\Carbon::today();

        $tokoToday = Sale::valid()->whereDate('created_at', $today);
        $tokoTodayOmzet = (int) (clone $tokoToday)->sum(DB::raw('total - refunded'));
        $tokoTodayTrx = (clone $tokoToday)->count();

        $lowProducts = Product::active()
            ->whereColumn('stock', '<=', 'low_stock')
            ->orderBy('stock')
            ->limit(10)
            ->get(['id', 'name', 'stock', 'low_stock', 'price']);

        $totalProducts = Product::active()->count();
        $totalLowStock = Product::active()->whereColumn('stock', '<=', 'low_stock')->count();

        $recentSales = Sale::with(['user:id,name', 'customer:id,name'])
            ->valid()
            ->latest()
            ->limit(5)
            ->get();

        $store = Setting::values();

        return response()->json([
            'success' => true,
            'stats' => [
                'today_omzet' => $tokoTodayOmzet,
                'today_trx' => $tokoTodayTrx,
                'total_products' => $totalProducts,
                'low_stock_count' => $totalLowStock,
            ],
            'low_stock_items' => $lowProducts,
            'recent_sales' => $recentSales,
            'store_name' => $store['store_name'] ?? 'Kios BERKAH',
        ]);
    }
}
