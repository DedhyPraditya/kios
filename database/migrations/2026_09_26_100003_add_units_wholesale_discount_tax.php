<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - Satuan ganda: produk bisa dijual per satuan lain (mis. dus isi 40)
     *   dengan harga & barcode sendiri. Stok tetap dihitung dalam satuan dasar.
     * - Harga grosir: harga per satuan dasar yang turun bila beli >= jumlah
     *   tertentu (boleh beberapa tingkat).
     * - Diskon per baris nota (rupiah) & diskon nota dalam persen.
     * - PPN opsional: tarif & nilai pajak disimpan per nota.
     */
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);              // mis. "dus", "pak", "renteng"
            $table->integer('isi');                  // jumlah satuan dasar per satuan ini
            $table->bigInteger('price');             // harga jual per satuan ini
            $table->string('barcode', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('product_wholesale_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('min_qty');              // minimal beli (satuan dasar)
            $table->bigInteger('price');             // harga per satuan dasar
            $table->timestamps();
            $table->unique(['product_id', 'min_qty']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('unit_name', 20)->nullable()->after('name'); // null = satuan dasar
            $table->integer('unit_isi')->default(1)->after('unit_name');  // stok berkurang qty x isi
            $table->bigInteger('discount')->default(0)->after('qty');     // diskon baris (Rp); subtotal sudah dikurangi
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_percent');
            $table->bigInteger('tax')->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'tax_rate', 'tax']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn(['unit_name', 'unit_isi', 'discount']);
        });

        Schema::dropIfExists('product_wholesale_prices');
        Schema::dropIfExists('product_units');
    }
};
