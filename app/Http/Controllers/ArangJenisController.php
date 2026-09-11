<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArangJenisController extends Controller
{
    public function index()
    {
        return Inertia::render('Arang/Jenis', [
            'jenisList' => ArangJenis::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'harga_beli_default' => ['required', 'integer', 'min:0'],
            'harga_jual_default' => ['required', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['aktif'] = true;

        ArangJenis::create($validated);

        return back()->with('success', 'Jenis arang berhasil ditambahkan.');
    }

    public function update(Request $request, ArangJenis $jeni)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'harga_beli_default' => ['required', 'integer', 'min:0'],
            'harga_jual_default' => ['required', 'integer', 'min:0'],
            'aktif' => ['required', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $jeni->update($validated);

        return back()->with('success', 'Data jenis arang berhasil diperbarui.');
    }

    public function destroy(ArangJenis $jeni)
    {
        if ($jeni->pembelian()->exists() || $jeni->penjualan()->exists()) {
            $jeni->update(['aktif' => false]);
            return back()->with('success', 'Jenis arang sudah memiliki transaksi, status diubah menjadi nonaktif.');
        }

        $jeni->delete();

        return back()->with('success', 'Jenis arang berhasil dihapus.');
    }
}
