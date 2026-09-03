<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();

        $customers = Customer::query()
            ->search($search)
            ->withCount('sales')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'address' => $c->address,
                'note' => $c->note,
                'credit_limit' => $c->credit_limit,
                'is_blocked' => $c->is_blocked,
                'sales_count' => $c->sales_count,
                'outstanding' => $c->outstanding(),
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Customer $customer)
    {
        $unpaid = $customer->sales()
            ->unpaid()
            ->with('items:id,sale_id,name,qty')
            ->latest('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'invoice_no' => $s->invoice_no,
                'created_at' => $s->created_at,
                'due_date' => $s->due_date,
                'overdue' => $s->due_date && $s->due_date->isPast(),
                'total' => $s->total,
                'paid' => $s->paid,
                'outstanding' => $s->outstanding(),
                'items' => $s->items->map(fn ($i) => $i->qty.'× '.$i->name),
            ]);

        $payments = $customer->creditPayments()
            ->with(['user:id,name', 'sale:id,invoice_no'])
            ->latest('created_at')
            ->limit(50)
            ->get(['id', 'sale_id', 'user_id', 'amount', 'note', 'created_at']);

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'credit_limit' => $customer->credit_limit,
                'is_blocked' => $customer->is_blocked,
                'note' => $customer->note,
                'outstanding' => $customer->outstanding(),
            ],
            'unpaidSales' => $unpaid,
            'payments' => $payments,
        ]);
    }

    public function store(Request $request)
    {
        Customer::create($this->validated($request));

        return back()->with('success', 'Pelanggan ditambahkan.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return back()->with('success', 'Pelanggan diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->outstanding() > 0) {
            return back()->withErrors(['customer' => 'Tidak bisa menghapus: masih ada sisa hutang.']);
        }

        if ($customer->sales()->exists()) {
            return back()->withErrors(['customer' => 'Tidak bisa menghapus: pelanggan punya riwayat transaksi.']);
        }

        $customer->delete();

        return back()->with('success', 'Pelanggan dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'integer', 'min:0'],
            'is_blocked' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
