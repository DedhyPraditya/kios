<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arang_penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('no_nota')->unique();
            $table->date('tanggal');
            $table->foreignId('arang_jenis_id')->constrained('arang_jenis');
            $table->string('nama_pembeli')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->decimal('berat_kg', 10, 2);
            $table->unsignedBigInteger('harga_jual_per_kg');
            $table->unsignedBigInteger('total_harga');
            $table->unsignedBigInteger('diskon')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->enum('payment_type', ['tunai', 'qris', 'kasbon'])->default('tunai');
            $table->unsignedBigInteger('paid')->default(0);
            $table->unsignedBigInteger('change')->default(0);
            $table->enum('status', ['lunas', 'belum_lunas'])->default('lunas');
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arang_penjualan');
    }
};

