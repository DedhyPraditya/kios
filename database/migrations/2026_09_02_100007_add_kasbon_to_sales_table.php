<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->string('payment_type')->default('tunai')->after('customer_id'); // tunai | kasbon
            $table->string('status')->default('lunas')->after('payment_type');       // lunas | belum_lunas
            $table->date('due_date')->nullable()->after('change');

            $table->index(['payment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['payment_type', 'status', 'due_date']);
        });
    }
};
