<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dismissed_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30)->index(); // 'product_stock' | 'sale_due'
            $table->unsignedBigInteger('alertable_id');
            $table->integer('last_value')->nullable(); // nilai saat di-dismiss (misal stock = 2)
            $table->timestamps();

            $table->unique(['user_id', 'type', 'alertable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dismissed_alerts');
    }
};
