<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CreditPayment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditPaymentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'sale_id' => ['nullable', 'exists:sales,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $customer = Customer::whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();

            $targets = $customer->sales()
                ->unpaid()
                ->when($data['sale_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $totalOutstanding = $targets->sum(fn (Sale $s) => $s->outstanding());

            if ($totalOutstanding <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Tidak ada hutang yang perlu dibayar.',
                ]);
            }

            if ($data['amount'] > $totalOutstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah melebihi sisa hutang (Rp'
                        .number_format($totalOutstanding, 0, ',', '.').').',
                ]);
            }

            $left = $data['amount'];

            foreach ($targets as $sale) {
                if ($left <= 0) {
                    break;
                }

                $due = $sale->outstanding();
                if ($due <= 0) {
                    continue;
                }

                $pay = min($left, $due);

                CreditPayment::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'user_id' => $request->user()->id,
                    'cash_session_id' => CashSession::openFor($request->user())?->id,
                    'amount' => $pay,
                    'note' => $data['note'] ?? null,
                ]);

                $sale->syncStatus();
                $left -= $pay;
            }
        });

        return back()->with('success', 'Pembayaran hutang dicatat.');
    }
}
