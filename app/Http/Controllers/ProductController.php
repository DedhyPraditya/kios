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
        Product::create($data);

        return back()->with('success', 'Produk ditambahkan.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $product->update($data);

        return back()->with('success', 'Produk diperbarui.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

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
