<?php

namespace App\Console\Commands;

use App\Models\CashSession;
use Illuminate\Console\Command;

class SealStaleCashSessions extends Command
{
    protected $signature = 'shift:tutup-otomatis';

    protected $description = 'Tutup shift kasir yang masih terbuka dari hari sebelumnya';

    public function handle(): int
    {
        $jumlah = CashSession::sealStale();

        $this->info($jumlah === 0
            ? 'Tidak ada shift yang menginap.'
            : "{$jumlah} shift ditutup otomatis karena ganti hari.");

        return self::SUCCESS;
    }
}
