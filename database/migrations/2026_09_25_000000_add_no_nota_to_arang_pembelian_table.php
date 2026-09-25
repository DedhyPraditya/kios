<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arang_pembelian', function (Blueprint $table) {
            $table->string('no_nota')->nullable()->unique()->after('id');
        });

        // Nota lama sudah tercetak sebagai "BELI-ARNG-" + id, jadi nomor itu
        // yang disimpan agar kertas yang dipegang pemasok tetap bisa dicari.
        DB::table('arang_pembelian')->whereNull('no_nota')->orderBy('id')->each(function ($row) {
            DB::table('arang_pembelian')
                ->where('id', $row->id)
                ->update(['no_nota' => 'BELI-ARNG-' . str_pad((string) $row->id, 4, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        Schema::table('arang_pembelian', function (Blueprint $table) {
            $table->dropUnique(['no_nota']);
            $table->dropColumn('no_nota');
        });
    }
};
