<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom uang & jumlah barang dijadikan bilangan bertanda.
     *
     * Di MySQL, pengurangan dua kolom UNSIGNED yang hasilnya negatif (mis.
     * harga jual di bawah modal) memunculkan galat "BIGINT UNSIGNED value is
     * out of range", sehingga setiap pengurangan di query harus dicor ke
     * SIGNED. Dengan kolom bertanda, pengurangan aman apa adanya. Nilai yang
     * tidak boleh negatif tetap dijaga validasi.
     *
     * Format: tabel => [kolom => [tipe, default, nullable]].
     */
    private const KOLOM = [
        'products' => [
            'price' => ['bigInteger', 0, false],
            'cost' => ['bigInteger', 0, false],
            'low_stock' => ['integer', 5, false],
        ],
        'sales' => [
            'subtotal' => ['bigInteger', 0, false],
            'discount' => ['bigInteger', 0, false],
            'total' => ['bigInteger', 0, false],
            'paid' => ['bigInteger', 0, false],
            'change' => ['bigInteger', 0, false],
            'refunded' => ['bigInteger', 0, false],
        ],
        'sale_items' => [
            'price' => ['bigInteger', null, false],
            'cost' => ['bigInteger', 0, false],
            'qty' => ['integer', null, false],
            'returned_qty' => ['integer', 0, false],
            'subtotal' => ['bigInteger', null, false],
        ],
        'customers' => [
            'credit_limit' => ['bigInteger', null, true],
        ],
        'credit_payments' => [
            'amount' => ['bigInteger', null, false],
        ],
        'stock_movements' => [
            'cost' => ['bigInteger', null, true],
        ],
        'cash_sessions' => [
            'opening_cash' => ['bigInteger', 0, false],
            'counted_cash' => ['bigInteger', null, true],
            'expected_cash' => ['bigInteger', null, true],
            'deposit' => ['bigInteger', null, true],
        ],
        'cash_movements' => [
            'amount' => ['bigInteger', null, false],
        ],
        'arang_jenis' => [
            'harga_beli_default' => ['bigInteger', 0, false],
            'harga_jual_default' => ['bigInteger', 0, false],
        ],
        'arang_pembelian' => [
            'harga_beli_per_kg' => ['bigInteger', null, false],
            'total_harga' => ['bigInteger', null, false],
        ],
        'arang_penjualan' => [
            'harga_jual_per_kg' => ['bigInteger', null, false],
            'total_harga' => ['bigInteger', null, false],
            'diskon' => ['bigInteger', 0, false],
            'grand_total' => ['bigInteger', null, false],
            'paid' => ['bigInteger', 0, false],
            'change' => ['bigInteger', 0, false],
        ],
    ];

    public function up(): void
    {
        $this->ubah(bertanda: true);
    }

    public function down(): void
    {
        $this->ubah(bertanda: false);
    }

    private function ubah(bool $bertanda): void
    {
        foreach (self::KOLOM as $tabel => $kolom) {
            Schema::table($tabel, function (Blueprint $table) use ($kolom, $bertanda) {
                foreach ($kolom as $nama => [$tipe, $default, $nullable]) {
                    $col = $table->{$tipe}($nama, false, ! $bertanda);
                    $nullable ? $col->nullable() : $col->nullable(false);
                    if ($default !== null) {
                        $col->default($default);
                    }
                    $col->change();
                }
            });
        }
    }
};
