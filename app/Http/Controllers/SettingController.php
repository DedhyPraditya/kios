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

    public function update(Request $request)
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:60'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
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
        Setting::put($data);

        \App\Models\ActivityLog::record('setting.update', "Memperbarui pengaturan dan identitas toko", null, $data);

        return back()->with('success', 'Pengaturan toko disimpan.');
    }
}
