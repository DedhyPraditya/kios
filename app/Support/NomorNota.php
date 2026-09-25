<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Nomor urut nota yang aman dipakai beberapa kasir sekaligus.
 *
 * Tiap awalan punya satu baris di tabel `nomor_urut`. Baris itu dikunci
 * (`lockForUpdate`) sampai transaksi pemanggil selesai, jadi transaksi kedua
 * menunggu dan mendapat nomor sesudahnya — bukan nomor yang sama.
 */
class NomorNota
{
    /**
     * @param  string  $awalan  mis. "INV20260925"
     * @param  callable(): int  $nomorAwal  nomor terakhir yang sudah terpakai
     *                                       sebelum penghitung ada (data lama)
     */
    public static function berikutnya(string $awalan, callable $nomorAwal): int
    {
        return DB::transaction(function () use ($awalan, $nomorAwal) {
            $baris = DB::table('nomor_urut')->where('awalan', $awalan)->lockForUpdate()->first();

            if (! $baris) {
                DB::table('nomor_urut')->insertOrIgnore([
                    'awalan' => $awalan,
                    'terakhir' => $nomorAwal(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $baris = DB::table('nomor_urut')->where('awalan', $awalan)->lockForUpdate()->first();
            }

            $nomor = (int) $baris->terakhir + 1;

            DB::table('nomor_urut')->where('awalan', $awalan)->update([
                'terakhir' => $nomor,
                'updated_at' => now(),
            ]);

            return $nomor;
        });
    }

    /** Nomor urut terbesar pada kolom yang berakhiran "-0001" untuk awalan ini. */
    public static function terbesarDi(string $tabel, string $kolom, string $awalanDenganPemisah): int
    {
        return (int) DB::table($tabel)
            ->where($kolom, 'like', $awalanDenganPemisah.'%')
            ->pluck($kolom)
            ->map(fn ($no) => (int) substr($no, strlen($awalanDenganPemisah)))
            ->max();
    }
}
