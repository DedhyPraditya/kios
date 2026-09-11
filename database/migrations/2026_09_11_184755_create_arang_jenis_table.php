<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arang_jenis', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->unsignedBigInteger('harga_beli_default')->default(0); // Rp per kg
            $table->unsignedBigInteger('harga_jual_default')->default(0); // Rp per kg
            $table->boolean('aktif')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arang_jenis');
    }
};

