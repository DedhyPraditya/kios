<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arang_pembelian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('arang_jenis_id')->constrained('arang_jenis');
            $table->string('nama_pemasok');
            $table->decimal('berat_kg', 10, 2); // contoh: 12.50 kg
            $table->unsignedBigInteger('harga_beli_per_kg');
            $table->unsignedBigInteger('total_harga'); // berat_kg * harga_beli_per_kg
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arang_pembelian');
    }
};

