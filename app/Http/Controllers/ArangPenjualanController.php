<?php

namespace App\Http\Controllers;

use App\Models\ArangJenis;
use App\Models\ArangPenjualan;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            // Arang tidak dijual kasbon — hanya tunai atau QRIS.
            'payment_type' => ['required', 'in:tunai,qris'],
            'paid' => ['nullable', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'payment_type.in' => 'Penjualan arang hanya bisa tunai atau QRIS.',
        ]);

        if (! empty($validated['customer_id'])
            && Customer::whereKey($validated['customer_id'])->value('is_blocked')) {
            return back()->withErrors(['customer_id' => 'Pelanggan ini diblokir dari transaksi.']);
        }

        $berat = (float) $validated['berat_kg'];

        // Kunci baris jenis arang agar dua penjualan bersamaan tidak sama-sama
        // lolos cek stok (stok dihitung dari total beli − total jual).
        return DB::transaction(function () use ($validated, $berat, $request) {
            $jenis = ArangJenis::whereKey($validated['arang_jenis_id'])->lockForUpdate()->firstOrFail();

            if (! $jenis->aktif) {
                return back()->withErrors(['arang_jenis_id' => "Arang {$jenis->nama} sedang tidak dijual."]);
            }

            // Harga & diskon ditentukan admin. Kasir memakai harga jual yang
            // diatur admin di Jenis Arang, tanpa diskon — isian dari form diabaikan.
            if (! $request->user()->isAdmin()) {
                $validated['harga_jual_per_kg'] = $jenis->harga_jual_default;
                $validated['diskon'] = 0;
            }

            if ($jenis->stok_kg < $berat) {
                return back()->withErrors([
                    'berat_kg' => "Stok arang {$jenis->nama} tidak mencukupi. Sisa stok tersedia: {$jenis->stok_kg} kg.",
                ]);
            }

            return $this->simpan($validated, $berat, $request);
        });
    }

    private function simpan(array $validated, float $berat, Request $request)
    {
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

        \App\Models\ActivityLog::record(
            'arang.jual',
            "Jual arang {$penjualan->no_nota} {$berat} kg ".strtoupper($paymentType).' Rp'.number_format($grandTotal, 0, ',', '.'),
            $penjualan,
            ['berat_kg' => $berat, 'harga_per_kg' => $hargaPerKg, 'diskon' => $diskon, 'total' => $grandTotal]
        );

        // Tanpa banner: halaman struk sudah punya baris status, sama seperti struk kasir.
        return redirect()->route('arang.penjualan.receipt', $penjualan->id);
    }

    public function receipt(Request $request, ArangPenjualan $penjualan)
    {
        abort_unless($request->user()->canViewReceiptOf($penjualan->user_id), 403);

        $penjualan->load(['arangJenis', 'customer:id,name,phone', 'user:id,name']);

        return Inertia::render('Arang/ReceiptPenjualan', [
            'store' => Setting::values(),
            'penjualan' => $penjualan,
        ]);
    }
}
