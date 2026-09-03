<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->unsignedBigInteger('price')->default(0);   // harga jual (Rupiah)
            $table->unsignedBigInteger('cost')->default(0);    // harga modal (Rupiah)
            $table->integer('stock')->default(0);
            $table->unsignedInteger('low_stock')->default(5);  // ambang stok menipis
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
