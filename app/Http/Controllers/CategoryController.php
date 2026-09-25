<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Categories/Index', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);
        $category = Category::create($request->only('name'));
        \App\Models\ActivityLog::record('category.create', "Menambahkan kategori '{$category->name}'", $category);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function update(Request $request, Category $category)
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);
        $lama = $category->name;
        $category->update($request->only('name'));
        \App\Models\ActivityLog::record('category.update', "Mengganti nama kategori '{$lama}' menjadi '{$category->name}'", $category);

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        \App\Models\ActivityLog::record('category.delete', "Menghapus kategori '{$category->name}'", null, ['id' => $category->id]);

        return back()->with('success', 'Kategori dihapus.');
    }
}
