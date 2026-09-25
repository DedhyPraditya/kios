<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();
        $categoryId = $request->integer('category') ?: null;
        $status = $request->string('status')->toString() ?: 'semua'; // semua | menipis | habis | terhapus
        $sort = $request->string('sort')->toString() ?: 'nama';       // nama | harga_asc | harga_desc | stok

        $products = Product::query()
            ->when($status === 'terhapus', fn ($q) => $q->onlyTrashed())
            ->with([
                'category:id,name',
                'units:id,product_id,name,isi,price,barcode',
                'wholesalePrices:id,product_id,min_qty,price',
            ])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($status === 'menipis', fn ($q) => $q->whereColumn('stock', '<=', 'low_stock'))
            ->when($status === 'habis', fn ($q) => $q->where('stock', '<=', 0))
            ->tap(fn ($q) => match ($sort) {
                'harga_asc' => $q->orderBy('price'),
                'harga_desc' => $q->orderByDesc('price'),
                'stok' => $q->orderBy('stock'),
                default => $q->orderBy('name'),
            })
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'category' => $categoryId,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->lepasBarcodeArsip($data['barcode'] ?? null);
        [$units, $tiers] = [$data['units'], $data['wholesale_prices']];
        unset($data['units'], $data['wholesale_prices']);

        $product = DB::transaction(function () use ($data, $units, $tiers) {
            $product = Product::create($data);
            $this->simpanSatuanDanGrosir($product, $units, $tiers);

            return $product;
        });

        \App\Models\ActivityLog::record('product.create', "Menambahkan produk '{$product->name}'", $product, $data);

        return back()->with('success', 'Produk ditambahkan.');
    }

    public function update(Request $request, Product $product)
    {
        $oldData = $product->only(['name', 'price', 'cost', 'stock', 'low_stock', 'is_active']);
        $data = $this->validated($request, $product);
        $this->lepasBarcodeArsip($data['barcode'] ?? null, $product->id);
        [$units, $tiers] = [$data['units'], $data['wholesale_prices']];
        unset($data['units'], $data['wholesale_prices']);

        DB::transaction(function () use ($product, $data, $units, $tiers) {
            $product->update($data);
            $this->simpanSatuanDanGrosir($product, $units, $tiers);
        });

        $changes = [];
        foreach (['name', 'price', 'cost', 'stock', 'low_stock', 'is_active'] as $k) {
            if (($oldData[$k] ?? null) != ($data[$k] ?? null)) {
                $changes[$k] = ['before' => $oldData[$k] ?? null, 'after' => $data[$k] ?? null];
            }
        }

        $desc = "Memperbarui produk '{$product->name}'";
        if (isset($changes['price'])) {
            $desc .= " (Harga: Rp" . number_format($oldData['price'], 0, ',', '.') . " → Rp" . number_format($data['price'], 0, ',', '.') . ")";
        }

        \App\Models\ActivityLog::record('product.update', $desc, $product, $changes);

        return back()->with('success', 'Produk diperbarui.');
    }

    /**
     * Produk tidak dihapus permanen, hanya diarsipkan: nota dan riwayat stok
     * yang memakainya tetap utuh, dan produk bisa dipulihkan.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        \App\Models\ActivityLog::record('product.delete', "Mengarsipkan produk '{$product->name}'", $product, [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
        ]);

        return back()->with('success', "Produk '{$product->name}' diarsipkan. Bisa dipulihkan lewat filter \"Produk terhapus\".");
    }

    public function restore(int $id)
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        // Barcode sudah dipakai produk lain selama diarsipkan → pulihkan tanpa barcode.
        $barcodeDipakai = $product->barcode
            && Product::where('barcode', $product->barcode)->exists();
        if ($barcodeDipakai) {
            $product->barcode = null;
        }

        $product->restore();

        \App\Models\ActivityLog::record('product.restore', "Memulihkan produk '{$product->name}'", $product);

        return back()->with('success', $barcodeDipakai
            ? "Produk '{$product->name}' dipulihkan tanpa barcode, karena barcodenya sudah dipakai produk lain."
            : "Produk '{$product->name}' dipulihkan.");
    }

    /**
     * Samakan satuan tambahan & harga grosir dengan isian form. Satuan yang
     * masih ada diperbarui (id tetap, supaya nota lama tetap menunjuk ke
     * sana); yang dihapus dari form ikut dihapus.
     */
    private function simpanSatuanDanGrosir(Product $product, array $units, array $tiers): void
    {
        $dipertahankan = [];
        foreach ($units as $u) {
            $unit = ! empty($u['id']) ? $product->units()->find($u['id']) : null;
            $isi = [
                'name' => trim($u['name']),
                'isi' => (int) $u['isi'],
                'price' => (int) $u['price'],
                'barcode' => ($u['barcode'] ?? '') !== '' ? $u['barcode'] : null,
            ];
            $unit ? $unit->update($isi) : ($unit = $product->units()->create($isi));
            $dipertahankan[] = $unit->id;
        }
        $product->units()->whereNotIn('id', $dipertahankan)->delete();

        $product->wholesalePrices()->delete();
        foreach (collect($tiers)->unique('min_qty') as $t) {
            $product->wholesalePrices()->create([
                'min_qty' => (int) $t['min_qty'],
                'price' => (int) $t['price'],
            ]);
        }
    }

    /** Barcode milik produk arsip dilepas bila dipakai produk aktif. */
    private function lepasBarcodeArsip(?string $barcode, ?int $kecualiId = null): void
    {
        if (! $barcode) {
            return;
        }

        Product::onlyTrashed()
            ->where('barcode', $barcode)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->update(['barcode' => null]);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $unitIds = $product ? $product->units()->pluck('id')->all() : [];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode')->ignore($product?->id)->withoutTrashed(),
                Rule::unique('product_units', 'barcode'),
            ],
            'units' => ['nullable', 'array', 'max:5'],
            'units.*.id' => ['nullable', 'integer', Rule::in($unitIds)],
            'units.*.name' => ['required', 'string', 'max:20'],
            'units.*.isi' => ['required', 'integer', 'min:2'],
            'units.*.price' => ['required', 'integer', 'min:0'],
            'units.*.barcode' => ['nullable', 'string', 'max:64', 'distinct'],
            'wholesale_prices' => ['nullable', 'array', 'max:5'],
            'wholesale_prices.*.min_qty' => ['required', 'integer', 'min:2', 'distinct'],
            'wholesale_prices.*.price' => ['required', 'integer', 'min:0', 'lt:price'],
            'price' => ['required', 'integer', 'min:0'],
            'cost' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer'],
            'low_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'wholesale_prices.*.price.lt' => 'Harga grosir harus lebih murah dari harga jual biasa.',
            'wholesale_prices.*.min_qty.distinct' => 'Minimal beli harga grosir tidak boleh kembar.',
            'units.*.isi.min' => 'Isi satuan minimal 2 (satuan dasar sudah pcs).',
            'units.*.barcode.distinct' => 'Barcode satuan tidak boleh kembar.',
        ]);

        // Barcode satuan tidak boleh bentrok dengan barcode produk / satuan lain.
        foreach ($data['units'] ?? [] as $i => $u) {
            $kode = $u['barcode'] ?? null;
            if (! $kode) {
                continue;
            }
            $bentrok = $kode === ($data['barcode'] ?? null)
                || Product::where('barcode', $kode)->exists()
                || ProductUnit::where('barcode', $kode)->when($u['id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))->exists();
            if ($bentrok) {
                throw ValidationException::withMessages([
                    "units.{$i}.barcode" => "Barcode {$kode} sudah dipakai produk atau satuan lain.",
                ]);
            }
        }

        $data['units'] ??= [];
        $data['wholesale_prices'] ??= [];

        return $data;
    }
}
