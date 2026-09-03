<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('due_date');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
            $table->unsignedBigInteger('refunded')->default(0)->after('void_reason'); // nilai barang diretur

            $table->index('voided_at');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('returned_qty')->default(0)->after('qty');
        });

        Schema::table('credit_payments', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('returned_qty');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason', 'refunded']);
        });
    }
};
