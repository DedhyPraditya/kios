<?php

namespace App\Http\Controllers;

use App\Models\DismissedAlert;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    public function dismiss(Request $request)
    {
        $user = $request->user();

        if ($request->boolean('dismiss_all')) {
            // Tandai semua produk stok menipis saat ini
            $lowProducts = Product::whereColumn('stock', '<=', 'low_stock')->get(['id', 'stock']);
            foreach ($lowProducts as $p) {
                DismissedAlert::updateOrCreate(
                    ['user_id' => $user->id, 'type' => 'product_stock', 'alertable_id' => $p->id],
                    ['last_value' => $p->stock]
                );
            }

            // Tandai semua kasbon yang mendekati/lewat jatuh tempo saat ini
            $dueSales = Sale::unpaid()
                ->whereNotNull('due_date')
                ->where('due_date', '<=', now()->addDays(3)->toDateString())
                ->get(['id']);

            foreach ($dueSales as $s) {
                DismissedAlert::updateOrCreate(
                    ['user_id' => $user->id, 'type' => 'sale_due', 'alertable_id' => $s->id],
                    ['last_value' => 0]
                );
            }

            return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['product_stock', 'sale_due'])],
            'id' => ['required', 'integer'],
            'value' => ['nullable', 'integer'],
        ]);

        DismissedAlert::updateOrCreate(
            ['user_id' => $user->id, 'type' => $data['type'], 'alertable_id' => $data['id']],
            ['last_value' => $data['value'] ?? 0]
        );

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }
}
