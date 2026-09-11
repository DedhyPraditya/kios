<?php

namespace App\Support;

class Changelog
{
    /**
     * Dapatkan daftar seluruh riwayat pembaruan aplikasi Kios BERKAH.
     * Ditulis dalam bahasa yang mudah dipahami oleh pemilik dan pengelola toko.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'version' => '1.5.0',
                'date' => '2026-09-11',
                'date_human' => '11 September 2026',
                'title' => 'Kelola Bisnis Arang Kiloan Jadi Lebih Mudah — Lengkap dengan Cetak Struk Bluetooth',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Kabar gembira untuk usaha arang Anda! Kios BERKAH kini dilengkapi menu khusus "Arang" untuk mencatat pembelian arang kiloan dari pengrajin, penjualan kiloan ke pembeli, hitungan stok otomatis, hingga cetak struk langsung ke printer Bluetooth tanpa ribet.',
                'changes' => [
                    [
                        'category' => '✨ Fitur Baru',
                        'type' => 'feat',
                        'title' => 'Pencatatan Khusus Arang Berbasis Kilogram (kg)',
                        'description' => 'Kini Anda tidak perlu bingung mencampur arang dengan barang eceran toko. Sistem arang dibuat khusus dengan hitungan kilogram (kg), baik saat membeli dari pembuat arang maupun saat menjual ke pelanggan.',
                    ],
                    [
                        'category' => '🔥 Stok & Varian Otomatis',
                        'type' => 'feat',
                        'title' => 'Pantau Stok Tiap Varian Arang Secara Real-Time',
                        'description' => 'Kelola berbagai varian arang seperti arang batok kelapa atau arang kayu. Stok otomatis bertambah saat beli stok baru dan berkurang saat terjual. Lengkap dengan peringatan otomatis jika stok mulai menipis.',
                    ],
                    [
                        'category' => '🖨️ Cetak Struk & Bluetooth',
                        'type' => 'ui',
                        'title' => 'Cetak Struk Penjualan & Nota Pembelian ke Printer Bluetooth',
                        'description' => 'Berikan struk rapi 58mm untuk pembeli atau nota timbangan untuk pengrajin arang. Cukup satu sentuhan, struk langsung tercetak lewat printer Bluetooth di ponsel atau komputer kasir Anda.',
                    ],
                    [
                        'category' => '💰 Keuangan Terpadu',
                        'type' => 'system',
                        'title' => 'Uang Laci Shift Kasir Tetap Rapi & Akurat',
                        'description' => 'Uang tunai hasil penjualan arang otomatis masuk ke rekap kasir, dan pengeluaran beli arang secara tunai otomatis memotong kas laci — jadi hitungan fisik laci Anda di akhir shift selalu pas.',
                    ],
                ],
            ],
            [
                'version' => '1.4.0',
                'date' => '2026-09-11',
                'date_human' => '11 September 2026',
                'title' => 'Terima Pembayaran QRIS — Lebih Praktis, Tanpa Uang Kembalian',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Kini Anda bisa menerima pembayaran lewat QRIS langsung di kasir. Cukup unggah kode QRIS toko satu kali di Pengaturan, lalu tampilkan ke pembeli kapan saja. Transaksi tercatat otomatis — cepat, rapi, dan tanpa repot menghitung kembalian.',
                'changes' => [
                    [
                        'category' => '✨ Fitur Baru',
                        'type' => 'feat',
                        'title' => 'Kasir kini bisa menerima QRIS',
                        'description' => 'Pilih metode "QRIS" saat transaksi — kode QR toko langsung muncul di layar lengkap dengan total yang harus dibayar. Ada tombol "Perbesar" agar pembeli bisa scan dengan mudah dari jarak jauh. Transaksi langsung lunas begitu dikonfirmasi.',
                    ],
                    [
                        'category' => '⚙️ Pengaturan Toko',
                        'type' => 'feat',
                        'title' => 'Unggah kode QRIS toko Anda sendiri',
                        'description' => 'Buka menu Pengaturan, unggah foto kode QRIS toko (JPG, PNG, atau WEBP), dan simpan. QRIS langsung siap dipakai di kasir. Bisa diganti atau dihapus kapan saja.',
                    ],
                    [
                        'category' => '🧾 Struk & Laporan',
                        'type' => 'feat',
                        'title' => 'Struk QRIS & laporan keuangan yang tetap akurat',
                        'description' => 'Metode "QRIS" otomatis tercetak di struk. Di rekap shift, uang QRIS dicatat terpisah dari uang tunai — jadi laci kasir tidak tercampur dan laporan Anda tetap beres.',
                    ],
                ],
            ],
            [
                'version' => '1.3.0',
                'date' => '2026-09-07',
                'date_human' => '07 September 2026',
                'title' => 'Riwayat Pembaruan Aplikasi Kini Bisa Dilihat Langsung',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Anda tidak perlu menunggu kabar dari kami untuk tahu apa yang baru. Semua pembaruan, perbaikan, dan peningkatan aplikasi kini bisa Anda baca langsung di dalam aplikasi — kapan saja, tanpa perlu menghubungi siapa pun.',
                'changes' => [
                    [
                        'category' => '✨ Fitur Baru',
                        'type' => 'feat',
                        'title' => 'Halaman "Pembaruan Aplikasi" di menu sidebar',
                        'description' => 'Kini ada menu baru bernama "Pembaruan Aplikasi" di sidebar. Di sana Anda bisa melihat semua catatan pembaruan, fitur terbaru, dan perbaikan yang sudah kami lakukan — disusun rapi dari yang terbaru.',
                    ],
                    [
                        'category' => '🎨 Tampilan Lebih Nyaman',
                        'type' => 'ui',
                        'title' => 'Tampilan filter riwayat aktivitas diperbaiki',
                        'description' => 'Filter di halaman Riwayat Aktivitas kini tidak lagi terpotong atau muncul scrollbar yang mengganggu, terutama di layar laptop. Semua filter tersusun rapi dan mudah diakses.',
                    ],
                    [
                        'category' => '📋 Transparansi',
                        'type' => 'docs',
                        'title' => 'Setiap pembaruan akan selalu tercatat',
                        'description' => 'Mulai hari ini, setiap perubahan — sekecil apa pun — akan kami catat dan tampilkan di halaman ini. Anda selalu bisa melihat apa yang berubah dan kapan.',
                    ],
                ],
            ],
            [
                'version' => '1.2.0',
                'date' => '2026-09-07',
                'date_human' => '07 September 2026',
                'title' => 'Notifikasi Lebih Cerdas — Tidak Mengganggu, Tapi Tetap Waspada',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Peringatan stok menipis dan kasbon jatuh tempo kini lebih pintar. Anda bisa menutup notifikasi yang sudah dibaca, dan sistem akan otomatis mengingatkan kembali jika situasinya semakin kritis.',
                'changes' => [
                    [
                        'category' => '✨ Fitur Baru',
                        'type' => 'feat',
                        'title' => 'Tandai peringatan sebagai "sudah dibaca"',
                        'description' => 'Setiap peringatan di lonceng notifikasi kini bisa Anda tutup satu per satu, atau sekaligus dengan tombol "Tandai Semua Dibaca". Layar notifikasi jadi bersih dan tidak penuh peringatan yang sudah Anda ketahui.',
                    ],
                    [
                        'category' => '🔔 Peringatan Otomatis',
                        'type' => 'system',
                        'title' => 'Peringatan muncul kembali jika stok makin kritis',
                        'description' => 'Jika Anda sudah menutup notifikasi stok menipis, tapi kemudian terjadi penjualan lagi sehingga stok berkurang lebih banyak — notifikasi akan muncul kembali otomatis. Anda tidak akan kecolongan.',
                    ],
                ],
            ],
            [
                'version' => '1.1.0',
                'date' => '2026-09-07',
                'date_human' => '07 September 2026',
                'title' => 'Keamanan Data, Lonceng Notifikasi & Riwayat Aktivitas',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Paket pembaruan besar untuk ketenangan pikiran Anda: data toko bisa dicadangkan sendiri, semua aktivitas penting tercatat otomatis, dan ada lonceng notifikasi yang memberitahu Anda saat ada yang perlu diperhatikan.',
                'changes' => [
                    [
                        'category' => '🔒 Keamanan Data',
                        'type' => 'feat',
                        'title' => 'Cadangkan data toko dengan satu klik',
                        'description' => 'Di menu Cadangan Data, Anda bisa menyimpan seluruh data toko ke file yang bisa diunduh kapan saja. Jika terjadi sesuatu, data bisa dipulihkan — dilindungi kata sandi untuk keamanan ekstra.',
                    ],
                    [
                        'category' => '📖 Riwayat Aktivitas',
                        'type' => 'feat',
                        'title' => 'Lihat siapa mengubah apa dan kapan',
                        'description' => 'Setiap perubahan penting di toko — harga produk diubah, nota dibatalkan, barang masuk dicatat, kasbon dilunasi — tercatat otomatis beserta nama penggunanya. Cocok untuk memantau aktivitas kasir.',
                    ],
                    [
                        'category' => '🔔 Notifikasi Lonceng',
                        'type' => 'ui',
                        'title' => 'Lonceng notifikasi di sudut aplikasi',
                        'description' => 'Ada ikon lonceng di bagian atas aplikasi. Klik untuk melihat produk yang stoknya mulai menipis dan kasbon yang sudah mendekati jatuh tempo — agar Anda bisa segera bertindak sebelum terlambat.',
                    ],
                ],
            ],
            [
                'version' => '1.0.0',
                'date' => '2026-09-01',
                'date_human' => '01 September 2026',
                'title' => 'Peluncuran Kios BERKAH — Aplikasi Kasir Siap Pakai',
                'type' => 'launch',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Selamat datang di Kios BERKAH! Aplikasi kasir yang dirancang khusus untuk kios dan toko kecil — mudah dipakai sehari-hari, mulai dari mencatat penjualan, mengelola stok, sampai menerima kasbon dari pelanggan.',
                'changes' => [
                    [
                        'category' => '🛒 Kasir',
                        'type' => 'feat',
                        'title' => 'Proses transaksi cepat dengan scan barcode',
                        'description' => 'Cari barang lewat nama atau scan barcode, tambahkan ke keranjang, pilih metode bayar (tunai atau kasbon), dan selesai. Struk langsung bisa dicetak ke printer thermal atau disimpan.',
                    ],
                    [
                        'category' => '📦 Stok Barang',
                        'type' => 'feat',
                        'title' => 'Kelola stok dan harga dengan mudah',
                        'description' => 'Tambah, ubah, atau hapus produk kapan saja. Stok berkurang otomatis setiap ada penjualan. Ada peringatan otomatis saat stok mulai menipis.',
                    ],
                    [
                        'category' => '💳 Kasbon & Piutang',
                        'type' => 'feat',
                        'title' => 'Catat kasbon dan pantau hutang pelanggan',
                        'description' => 'Pelanggan bisa bayar nanti dengan sistem kasbon. Semua hutang tercatat otomatis dan bisa dilunasi kapan saja. Anda bisa mengatur batas hutang per pelanggan.',
                    ],
                    [
                        'category' => '📊 Laporan',
                        'type' => 'feat',
                        'title' => 'Laporan omzet dan penjualan harian',
                        'description' => 'Lihat omzet hari ini, grafik penjualan mingguan, dan produk terlaris — semua tersaji rapi di halaman Laporan dan Dashboard.',
                    ],
                ],
            ],
        ];
    }
}
