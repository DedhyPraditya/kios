<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use App\Models\ArangPenjualan;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArangPenjualanController extends Controller
{
    public function create()
    {
        $jenisList = ArangJenis::aktif()->orderBy('nama')->get();
        $customers = Customer::where('is_blocked', false)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return Inertia::render('Arang/Jual', [
            'jenisList' => $jenisList,
            'customers' => $customers,
            'store' => Setting::values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'arang_jenis_id' => ['required', 'exists:arang_jenis,id'],
            'nama_pembeli' => ['nullable', 'string', 'max:150'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'berat_kg' => ['required', 'numeric', 'min:0.01'],
            'harga_jual_per_kg' => ['required', 'integer', 'min:0'],
            'diskon' => ['nullable', 'integer', 'min:0'],
            'payment_type' => ['required', 'in:tunai,qris,kasbon'],
            'paid' => ['nullable', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['payment_type'] === 'kasbon' && empty($validated['customer_id'])) {
            return back()->withErrors(['customer_id' => 'Pelanggan harus dipilih untuk transaksi kasbon.']);
        }

        $jenis = ArangJenis::findOrFail($validated['arang_jenis_id']);
        $berat = (float) $validated['berat_kg'];

        if ($jenis->stok_kg < $berat) {
            return back()->withErrors([
                'berat_kg' => "Stok arang {$jenis->nama} tidak mencukupi. Sisa stok tersedia: {$jenis->stok_kg} kg.",
            ]);
        }

        $hargaPerKg = (int) $validated['harga_jual_per_kg'];
        $totalHarga = (int) round($berat * $hargaPerKg);
        $diskon = (int) ($validated['diskon'] ?? 0);
        $grandTotal = max(0, $totalHarga - $diskon);

        $paymentType = $validated['payment_type'];
        $paid = (int) ($validated['paid'] ?? 0);
        $change = 0;
        $status = 'lunas';

        if ($paymentType === 'tunai') {
            if ($paid < $grandTotal) {
                return back()->withErrors(['paid' => 'Uang yang dibayarkan kurang dari total belanja.']);
            }
            $change = $paid - $grandTotal;
            $status = 'lunas';
        } elseif ($paymentType === 'qris') {
            $paid = $grandTotal;
            $change = 0;
            $status = 'lunas';
        } elseif ($paymentType === 'kasbon') {
            $change = 0;
            $status = ($paid >= $grandTotal) ? 'lunas' : 'belum_lunas';
        }

        $activeSession = CashSession::openFor($request->user());

        $penjualan = ArangPenjualan::create([
            'tanggal' => $validated['tanggal'],
            'arang_jenis_id' => $validated['arang_jenis_id'],
            'nama_pembeli' => $validated['nama_pembeli'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'berat_kg' => $berat,
            'harga_jual_per_kg' => $hargaPerKg,
            'total_harga' => $totalHarga,
            'diskon' => $diskon,
            'grand_total' => $grandTotal,
            'payment_type' => $paymentType,
            'paid' => $paid,
            'change' => $change,
            'status' => $status,
            'cash_session_id' => $activeSession?->id,
            'user_id' => $request->user()->id,
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return redirect()->route('arang.penjualan.receipt', $penjualan->id)
            ->with('success', "Penjualan arang ({$penjualan->no_nota}) sebanyak {$berat} kg berhasil dicatat.");
    }

    public function receipt(ArangPenjualan $penjualan)
    {
        $penjualan->load(['arangJenis', 'customer:id,name,phone', 'user:id,name']);

        return Inertia::render('Arang/ReceiptPenjualan', [
            'store' => Setting::values(),
            'penjualan' => $penjualan,
        ]);
    }
}
