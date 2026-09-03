<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'settings.all';

    /** Nilai bawaan bila baris pengaturan belum pernah disimpan. */
    public const DEFAULTS = [
        'store_name' => 'Kios BERKAH',
        'store_address' => '',
        'store_phone' => '',
        'receipt_footer' => 'Terima kasih telah berbelanja.',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Seluruh pengaturan toko (bawaan + yang tersimpan), di-cache. */
    public static function values(): array
    {
        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all(),
        );

        return [...self::DEFAULTS, ...array_filter($stored, fn ($v) => $v !== null)];
    }

    public static function get(string $key, ?string $fallback = null): ?string
    {
        return static::values()[$key] ?? $fallback;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
