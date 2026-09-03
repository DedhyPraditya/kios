<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                 // salinan nama saat transaksi
            $table->unsignedBigInteger('price');    // harga jual satuan saat transaksi
            $table->unsignedBigInteger('cost')->default(0); // modal satuan saat transaksi
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('subtotal'); // price * qty
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
