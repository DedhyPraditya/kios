<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            // Ditutup sistem saat ganti hari, bukan oleh kasir yang menghitung uang.
            $table->boolean('auto_closed')->default(false)->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn('auto_closed');
        });
    }
};
