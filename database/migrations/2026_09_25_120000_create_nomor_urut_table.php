<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penghitung nomor nota per awalan (mis. INV20260925). Baris dikunci saat
     * mengambil nomor berikutnya supaya dua transaksi bersamaan tidak
     * mendapat nomor yang sama.
     */
    public function up(): void
    {
        Schema::create('nomor_urut', function (Blueprint $table) {
            $table->string('awalan', 50)->primary();
            $table->unsignedInteger('terakhir')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_urut');
    }
};
