<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'kasir'])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $data['password'] = Hash::make($data['password']);
        $newUser = User::create($data);

        \App\Models\ActivityLog::record('user.create', "Menambahkan pengguna '{$newUser->name}' (Peran: {$newUser->role})", $newUser);

        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'kasir'])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        \App\Models\ActivityLog::record('user.update', "Memperbarui akun pengguna '{$user->name}'", $user);

        return back()->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }

        $userName = $user->name;
        $userId = $user->id;
        $user->delete();

        \App\Models\ActivityLog::record('user.delete', "Menghapus akun pengguna '{$userName}'", null, ['id' => $userId, 'name' => $userName]);

        return back()->with('success', 'Pengguna dihapus.');
    }
}
