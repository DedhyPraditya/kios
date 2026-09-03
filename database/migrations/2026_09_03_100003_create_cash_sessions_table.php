<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu shift kasir (buka laci sampai tutup laci).
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('opening_cash')->default(0);   // modal awal laci
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('counted_cash')->nullable();   // uang fisik saat tutup
            $table->unsignedBigInteger('expected_cash')->nullable();  // seharusnya menurut sistem
            $table->bigInteger('difference')->nullable();             // counted - expected (bisa minus)
            $table->unsignedBigInteger('deposit')->nullable();        // disetor ke pemilik
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
