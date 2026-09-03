<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SaleController extends Controller
{
    /** Riwayat transaksi lengkap: cari no nota / pelanggan, saring tanggal, kasir, status. */
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status', 'semua'),
        ];

        // Saringan dipakai dua kali: sekali untuk daftar, sekali untuk ringkasan.
        // Query ringkasan sengaja dibangun bersih tanpa `with`/`withCount` — MySQL
        // (`only_full_group_by`) menolak kolom biasa yang dicampur agregat.
        $applyFilters = function ($query) use ($filters) {
            $query
                ->when($filters['q'], fn ($q, $term) => $q->where(function ($w) use ($term) {
                    $w->where('invoice_no', 'like', "%{$term}%")
                        ->orWhere('note', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
                }))
                ->when($filters['from'], fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
                ->when($filters['to'], fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
                ->when($filters['user_id'], fn ($q, $id) => $q->where('user_id', $id));

            return match ($filters['status']) {
                'batal' => $query->whereNotNull('voided_at'),
                'lunas' => $query->valid()->where('status', 'lunas'),
                'belum_lunas' => $query->valid()->where('status', 'belum_lunas'),
                'kasbon' => $query->valid()->where('payment_type', 'kasbon'),
                'tunai' => $query->valid()->where('payment_type', 'tunai'),
                default => $query,
            };
        };

        // Ringkasan dihitung dari hasil saring, tanpa nota batal.
        $totals = $applyFilters(Sale::query())->valid()
            ->selectRaw('COUNT(*) as trx, COALESCE(SUM(total - refunded), 0) as omzet, COALESCE(SUM(refunded), 0) as refunded')
            ->first();

        $voidedCount = $applyFilters(Sale::query())->whereNotNull('voided_at')->count();

        $query = $applyFilters(
            Sale::query()->with(['user:id,name', 'customer:id,name'])->withCount('items')
        );

        $sales = $query->latest()->paginate(20)->withQueryString()
            ->through(fn (Sale $s) => [
                'id' => $s->id,
                'invoice_no' => $s->invoice_no,
                'created_at' => $s->created_at->isoFormat('D MMM YYYY, HH:mm'),
                'user' => $s->user?->name,
                'customer' => $s->customer?->name,
                'payment_type' => $s->payment_type,
                'status' => $s->isVoided() ? 'batal' : $s->status,
                'items_count' => $s->items_count,
                'total' => $s->total,
                'refunded' => $s->refunded,
                'net_total' => $s->netTotal(),
                'outstanding' => $s->outstanding(),
            ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $filters,
            'cashiers' => User::orderBy('name')->get(['id', 'name']),
            'totals' => [
                'trx' => (int) $totals->trx,
                'omzet' => (int) $totals->omzet,
                'refunded' => (int) $totals->refunded,
                'voided' => $voidedCount,
            ],
        ]);
    }

    public function show(Sale $sale)
    {
        $sale->load([
            'items',
            'user:id,name',
            'customer:id,name,phone',
            'voidedBy:id,name',
            'creditPayments.user:id,name',
        ]);

        return Inertia::render('Sales/Show', [
            'sale' => [
                ...$sale->toArray(),
                'created_at_label' => $sale->created_at->isoFormat('dddd, D MMMM YYYY, HH:mm'),
                'due_date' => $sale->due_date?->toDateString(),
                'voided_at_label' => $sale->voided_at?->isoFormat('D MMM YYYY, HH:mm'),
                'voided_by_name' => $sale->voidedBy?->name,
                'outstanding' => $sale->outstanding(),
                'net_total' => $sale->netTotal(),
                'voided' => $sale->isVoided(),
                'items' => $sale->items->map(fn ($i) => [
                    ...$i->toArray(),
                    'returnable' => $i->returnableQty(),
                ]),
                'credit_payments' => $sale->creditPayments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'user' => $p->user?->name,
                    'created_at' => $p->created_at->isoFormat('D MMM YYYY, HH:mm'),
                ]),
            ],
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Ubah keterangan nota. Nilai uang & barang tidak boleh diubah — pakai retur / batal. */
    public function update(Request $request, Sale $sale)
    {
        if ($sale->isVoided()) {
            throw ValidationException::withMessages(['note' => 'Nota sudah dibatalkan.']);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($sale->payment_type !== 'kasbon') {
            unset($data['customer_id'], $data['due_date']);
        } elseif (($data['customer_id'] ?? null) === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Nota kasbon harus punya pelanggan.',
            ]);
        }

        $sale->update($data);

        return back()->with('success', 'Nota diperbarui.');
    }

    /** Batalkan seluruh nota: stok kembali, hutang hapus, uang tunai keluar laci. */
    public function void(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request, $sale) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->isVoided()) {
                throw ValidationException::withMessages(['reason' => 'Nota ini sudah dibatalkan.']);
            }

            if ($sale->creditPayments()->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'Nota sudah menerima pembayaran hutang. Retur barangnya, jangan dibatalkan.',
                ]);
            }

            // Kembalikan stok untuk barang yang belum diretur.
            foreach ($sale->items()->get() as $item) {
                $qty = $item->returnableQty();

                if ($qty < 1 || ! $item->product_id) {
                    continue;
                }

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

                if ($product) {
                    StockMovement::apply($product, $qty, 'batal', [
                        'user_id' => $request->user()->id,
                        'sale_id' => $sale->id,
                        'note' => 'Batal nota '.$sale->invoice_no,
                    ]);
                }
            }

            // Uang yang sudah diterima dikembalikan ke pembeli.
            $cashBack = $sale->payment_type === 'tunai' ? $sale->total : $sale->paid;

            $sale->forceFill([
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
                'void_reason' => $data['reason'],
            ])->save();

            $this->recordCashOut($request->user(), $cashBack, 'Batal nota '.$sale->invoice_no, $sale);
        });

        return back()->with('success', 'Nota dibatalkan dan stok dikembalikan.');
    }

    /** Retur sebagian barang dari nota yang sudah tersimpan. */
    public function refund(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', Rule::exists('sale_items', 'id')->where('sale_id', $sale->id)],
            'items.*.qty' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request, $sale) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->isVoided()) {
                throw ValidationException::withMessages(['reason' => 'Nota ini sudah dibatalkan.']);
            }

            $items = $sale->items()->lockForUpdate()->get()->keyBy('id');
            $refundValue = 0;
            $anything = false;

            foreach ($data['items'] as $row) {
                $qty = (int) $row['qty'];

                if ($qty < 1) {
                    continue;
                }

                $item = $items[$row['sale_item_id']];

                if ($qty > $item->returnableQty()) {
                    throw ValidationException::withMessages([
                        'items' => "Retur {$item->name} melebihi jumlah yang dibeli (sisa {$item->returnableQty()}).",
                    ]);
                }

                $anything = true;
                $item->increment('returned_qty', $qty);
                $refundValue += $item->price * $qty;

                if ($item->product_id) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

                    if ($product) {
                        StockMovement::apply($product, $qty, 'retur', [
                            'user_id' => $request->user()->id,
                            'sale_id' => $sale->id,
                            'note' => 'Retur nota '.$sale->invoice_no,
                        ]);
                    }
                }
            }

            if (! $anything) {
                throw ValidationException::withMessages(['items' => 'Tidak ada barang yang diretur.']);
            }

            // Diskon nota ikut dipotong proporsional supaya nilai retur adil.
            if ($sale->subtotal > 0 && $sale->discount > 0) {
                $refundValue = (int) round($refundValue * ($sale->total / $sale->subtotal));
            }

            $refundValue = min($refundValue, $sale->total - $sale->refunded);
            $sale->increment('refunded', $refundValue);
            $sale->refresh();

            if ($sale->payment_type === 'kasbon') {
                // Hutang berkurang lebih dulu; hanya kelebihan bayar yang dikembalikan tunai.
                $alreadyPaid = $sale->paid + (int) $sale->creditPayments()->sum('amount');
                $cashBack = max($alreadyPaid - $sale->netTotal(), 0);
                $sale->syncStatus();
            } else {
                $cashBack = $refundValue;
            }

            $sale->forceFill([
                'note' => trim(($sale->note ? $sale->note.' | ' : '').'Retur: '.$data['reason']),
            ])->save();

            $this->recordCashOut($request->user(), $cashBack, 'Retur nota '.$sale->invoice_no, $sale);
        });

        return back()->with('success', 'Retur dicatat, stok dan nilai nota disesuaikan.');
    }

    /**
     * Uang keluar laci dicatat di shift kasir yang sedang terbuka, bila ada.
     *
     * Nota yang dibatalkan/diretur di shift yang sama tidak dicatat: nilainya
     * sudah hilang sendiri dari rekap penjualan shift itu, jadi mencatat kas
     * keluar akan memotong laci dua kali.
     */
    private function recordCashOut(User $user, int $amount, string $note, Sale $sale): void
    {
        if ($amount < 1) {
            return;
        }

        $session = CashSession::openFor($user);

        if (! $session || $sale->cash_session_id === $session->id) {
            return;
        }

        CashMovement::create([
            'cash_session_id' => $session->id,
            'user_id' => $user->id,
            'direction' => 'keluar',
            'amount' => $amount,
            'note' => $note,
        ]);
    }
}
