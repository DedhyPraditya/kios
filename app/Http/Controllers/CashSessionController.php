<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CashSessionController extends Controller
{
    /** Halaman tutup kasir: shift berjalan + riwayat shift. */
    public function index(Request $request)
    {
        $user = $request->user();
        $open = CashSession::openFor($user);

        $current = null;

        if ($open) {
            $open->load(['movements.user:id,name']);

            $current = [
                'id' => $open->id,
                'opened_at' => $open->opened_at->isoFormat('dddd, D MMM YYYY, HH:mm'),
                'kasir' => $user->name,
                ...$open->summary(),
                'movements' => $open->movements
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (CashMovement $m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'amount' => $m->amount,
                        'note' => $m->note,
                        'user' => $m->user?->name,
                        'created_at' => $m->created_at->isoFormat('D MMM, HH:mm'),
                    ]),
            ];
        }

        // Admin melihat semua shift; kasir hanya miliknya sendiri.
        $history = CashSession::query()
            ->with(['user:id,name', 'closer:id,name'])
            ->whereNotNull('closed_at')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('closed_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CashSession $s) => [
                'id' => $s->id,
                'kasir' => $s->user?->name,
                'opened_at' => $s->opened_at->isoFormat('D MMM YYYY, HH:mm'),
                'closed_at' => $s->closed_at?->isoFormat('D MMM YYYY, HH:mm'),
                'opening_cash' => $s->opening_cash,
                'expected_cash' => $s->expected_cash,
                'counted_cash' => $s->counted_cash,
                'difference' => $s->difference,
                'deposit' => $s->deposit,
                'auto_closed' => $s->auto_closed,
                'note' => $s->note,
            ]);

        // Kalau shift terakhir ditutup sistem, kasir perlu diberi tahu bahwa
        // uang laci hari itu tak pernah dihitung.
        $terakhir = CashSession::whereNotNull('closed_at')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('closed_at')
            ->first();

        return Inertia::render('Shift/Index', [
            'current' => $current,
            'history' => $history,
            'autoClosed' => $terakhir?->auto_closed
                ? [
                    'kasir' => $terakhir->user?->name,
                    'opened_at' => $terakhir->opened_at->isoFormat('dddd, D MMM YYYY'),
                    'expected_cash' => $terakhir->expected_cash,
                ]
                : null,
        ]);
    }

    /** Buka shift: catat modal awal laci. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (CashSession::openFor($request->user())) {
            throw ValidationException::withMessages([
                'opening_cash' => 'Masih ada shift yang terbuka. Tutup dulu shift itu.',
            ]);
        }

        CashSession::create([
            'user_id' => $request->user()->id,
            'opening_cash' => $data['opening_cash'],
            'opened_at' => now(),
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Shift dibuka.');
    }

    /** Kas masuk / keluar di luar penjualan. */
    public function movement(Request $request)
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['masuk', 'keluar'])],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $session = CashSession::openFor($request->user());

        if (! $session) {
            throw ValidationException::withMessages([
                'amount' => 'Belum ada shift terbuka.',
            ]);
        }

        CashMovement::create([
            'cash_session_id' => $session->id,
            'user_id' => $request->user()->id,
            ...$data,
        ]);

        return back()->with('success', 'Kas dicatat.');
    }

    /** Tutup shift: bandingkan uang fisik dengan hitungan sistem. */
    public function close(Request $request, CashSession $session)
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'integer', 'min:0'],
            'deposit' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $session->isOpen()) {
            throw ValidationException::withMessages(['counted_cash' => 'Shift ini sudah ditutup.']);
        }

        if ($session->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }

        DB::transaction(function () use ($data, $request, $session) {
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $expected = $session->summary()['expected_cash'];

            $session->update([
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
                'counted_cash' => $data['counted_cash'],
                'expected_cash' => $expected,
                'difference' => $data['counted_cash'] - $expected,
                'deposit' => $data['deposit'] ?? null,
                'note' => $data['note'] ?? $session->note,
            ]);
        });

        return back()->with('success', 'Shift ditutup.');
    }
}
