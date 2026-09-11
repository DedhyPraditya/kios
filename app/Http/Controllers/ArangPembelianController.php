<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use App\Models\ArangPembelian;
use App\Models\CashSession;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArangPembelianController extends Controller
{
    public function create()
    {
        $jenisList = ArangJenis::aktif()->orderBy('nama')->get();

        return Inertia::render('Arang/Beli', [
            'jenisList' => $jenisList,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'arang_jenis_id' => ['required', 'exists:arang_jenis,id'],
            'nama_pemasok' => ['required', 'string', 'max:150'],
            'berat_kg' => ['required', 'numeric', 'min:0.01'],
            'harga_beli_per_kg' => ['required', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $berat = (float) $validated['berat_kg'];
        $hargaPerKg = (int) $validated['harga_beli_per_kg'];
        $totalHarga = (int) round($berat * $hargaPerKg);

        $activeSession = CashSession::openFor($request->user());

        $pembelian = ArangPembelian::create([
            'tanggal' => $validated['tanggal'],
            'arang_jenis_id' => $validated['arang_jenis_id'],
            'nama_pemasok' => $validated['nama_pemasok'],
            'berat_kg' => $berat,
            'harga_beli_per_kg' => $hargaPerKg,
            'total_harga' => $totalHarga,
            'cash_session_id' => $activeSession?->id,
            'user_id' => $request->user()->id,
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return redirect()->route('arang.pembelian.receipt', $pembelian->id)
            ->with('success', 'Pembelian arang sebanyak ' . $berat . ' kg berhasil dicatat.');
    }

    public function receipt(ArangPembelian $pembelian)
    {
        $pembelian->load(['arangJenis', 'user:id,name']);

        return Inertia::render('Arang/ReceiptPembelian', [
            'store' => Setting::values(),
            'pembelian' => $pembelian,
        ]);
    }
}
