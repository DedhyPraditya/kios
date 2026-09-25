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

        $jenis = ArangJenis::create($validated);
        \App\Models\ActivityLog::record('arang_jenis.create', "Menambahkan jenis arang '{$jenis->nama}'", $jenis, $validated);

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
        \App\Models\ActivityLog::record('arang_jenis.update', "Memperbarui jenis arang '{$jeni->nama}'", $jeni, $jeni->getChanges());

        return back()->with('success', 'Data jenis arang berhasil diperbarui.');
    }

    public function destroy(ArangJenis $jeni)
    {
        if ($jeni->pembelian()->exists() || $jeni->penjualan()->exists()) {
            $jeni->update(['aktif' => false]);
            \App\Models\ActivityLog::record('arang_jenis.update', "Menonaktifkan jenis arang '{$jeni->nama}'", $jeni);

            return back()->with('success', 'Jenis arang sudah memiliki transaksi, status diubah menjadi nonaktif.');
        }

        $jeni->delete();
        \App\Models\ActivityLog::record('arang_jenis.delete', "Menghapus jenis arang '{$jeni->nama}'", null, ['id' => $jeni->id]);

        return back()->with('success', 'Jenis arang berhasil dihapus.');
    }
}
