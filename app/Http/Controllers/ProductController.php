<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();
        $categoryId = $request->integer('category') ?: null;
        $status = $request->string('status')->toString() ?: 'semua'; // semua | menipis | habis
        $sort = $request->string('sort')->toString() ?: 'nama';       // nama | harga_asc | harga_desc | stok

        $products = Product::query()
            ->with('category:id,name')
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
        $product = Product::create($data);

        \App\Models\ActivityLog::record('product.create', "Menambahkan produk '{$product->name}'", $product, $data);

        return back()->with('success', 'Produk ditambahkan.');
    }

    public function update(Request $request, Product $product)
    {
        $oldData = $product->only(['name', 'price', 'cost', 'stock', 'low_stock', 'is_active']);
        $data = $this->validated($request, $product);
        $product->update($data);

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

    public function destroy(Product $product)
    {
        $name = $product->name;
        $id = $product->id;
        $product->delete();

        \App\Models\ActivityLog::record('product.delete', "Menghapus produk '{$name}'", null, ['id' => $id, 'name' => $name]);

        return back()->with('success', 'Produk dihapus.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode')->ignore($product?->id),
            ],
            'price' => ['required', 'integer', 'min:0'],
            'cost' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer'],
            'low_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
