<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Support\Peringatan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    /** Tab Aktivitas dibuka: semua aktivitas sampai detik ini dianggap sudah dibaca. */
    public function seenActivity(Request $request)
    {
        $request->user()->forceFill(['activity_seen_at' => now()])->save();

        return back();
    }

    /**
     * Tandai notifikasi lonceng sudah dibaca. Tanpa pesan flash: lonceng
     * langsung memperbarui isinya, banner hijau di tiap centang hanya
     * mengganggu.
     */
    public function dismiss(Request $request)
    {
        $userId = $request->user()->id;

        if ($request->boolean('dismiss_all')) {
            Peringatan::stokMenipis($userId)->get()
                ->each(fn (Product $p) => Peringatan::tandaiProduk($userId, $p));
            Peringatan::kasbonJatuhTempo($userId)->get()
                ->each(fn (Sale $s) => Peringatan::tandaiKasbon($userId, $s));
            $request->user()->forceFill(['activity_seen_at' => now()])->save();

            return back();
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['product_stock', 'sale_due'])],
            'id' => ['required', 'integer'],
        ]);

        // Angka pembanding diambil dari data server, bukan dari browser.
        if ($data['type'] === 'product_stock') {
            Peringatan::tandaiProduk($userId, Product::findOrFail($data['id']));
        } else {
            Peringatan::tandaiKasbon($userId, Sale::findOrFail($data['id']));
        }

        return back();
    }
}
