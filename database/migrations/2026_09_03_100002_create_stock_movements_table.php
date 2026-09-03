<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buku besar pergerakan stok: setiap perubahan stok wajib punya barisnya.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            // masuk | penjualan | retur | batal | penyesuaian
            $table->string('type');
            $table->integer('qty');                             // + menambah stok, - mengurangi
            $table->integer('stock_after');                     // sisa stok setelah gerakan ini
            $table->unsignedBigInteger('cost')->nullable();     // harga modal saat barang masuk
            $table->string('supplier')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
