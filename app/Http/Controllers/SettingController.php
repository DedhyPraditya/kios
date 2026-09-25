<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/Index', [
            'store' => Setting::values(),
        ]);
    }

    /**
     * Gambar QRIS toko. Disajikan lewat route, bukan /storage, agar tetap
     * tampil walau `php artisan storage:link` belum/tidak bisa dijalankan.
     */
    public function qris()
    {
        $path = Setting::get('qris_image');
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:60'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'receipt_code' => ['nullable', 'in:none,qr,barcode'],
            'tax_enabled' => ['nullable', 'boolean'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'qris_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_qris' => ['nullable', 'boolean'],
        ]);

        $currentQris = Setting::get('qris_image');

        if ($request->boolean('remove_qris')) {
            if ($currentQris && Storage::disk('public')->exists($currentQris)) {
                Storage::disk('public')->delete($currentQris);
            }
            Setting::put(['qris_image' => '']);
        } elseif ($request->hasFile('qris_image')) {
            if ($currentQris && Storage::disk('public')->exists($currentQris)) {
                Storage::disk('public')->delete($currentQris);
            }
            $path = $request->file('qris_image')->store('qris', 'public');
            Setting::put(['qris_image' => $path]);
        }

        unset($data['qris_image'], $data['remove_qris']);
        // Disimpan sebagai teks di tabel pengaturan.
        if (array_key_exists('tax_enabled', $data)) {
            $data['tax_enabled'] = $data['tax_enabled'] ? '1' : '0';
        }
        if (array_key_exists('tax_rate', $data) && $data['tax_rate'] !== null) {
            $data['tax_rate'] = (string) round((float) $data['tax_rate'], 2);
        }
        Setting::put($data);

        \App\Models\ActivityLog::record('setting.update', "Memperbarui pengaturan dan identitas toko", null, $data);

        return back()->with('success', 'Pengaturan toko disimpan.');
    }
}
