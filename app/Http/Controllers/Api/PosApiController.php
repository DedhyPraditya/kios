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
            ->get(['id', 'name', 'phone', 'credit_limit']);

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
                    'product_name' => $product->name,
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
            $paid = $data['paid'];
            $change = max(0, $paid - $total);
            $isPaid = $data['payment_type'] !== 'kasbon' || $paid >= $total;

            $sale = Sale::create([
                'user_id' => $cashierId,
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_number' => 'INV' . date('YmdHis') . '-' . rand(100, 999),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discount_percent' => $data['discount_percent'] ?? null,
                'total' => $total,
                'paid' => $paid,
                'change' => $change,
                'payment_method' => $data['payment_type'] ?? 'tunai',
                'is_paid' => $isPaid,
                'due_date' => $data['due_date'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($itemsToInsert as $ins) {
                $factor = $ins['factor'];
                unset($ins['factor']);
                $ins['sale_id'] = $sale->id;
                SaleItem::create($ins);

                // Potong stok produk
                $product = $products->get($ins['product_id']);
                $product->decrement('stock', $ins['qty'] * $factor);

                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $cashierId,
                    'type' => 'sale',
                    'qty' => -($ins['qty'] * $factor),
                    'stock_before' => $product->stock + ($ins['qty'] * $factor),
                    'stock_after' => $product->stock,
                    'description' => "Penjualan Nota #{$sale->invoice_number}",
                ]);
            }

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
}
