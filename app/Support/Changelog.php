<?php

namespace App\Support;

class Changelog
{
    /**
     * Dapatkan daftar seluruh riwayat rilis dan pembaruan aplikasi.
     * Dimulai sejak 07 September 2026 dan diperbarui setiap kali ada perubahan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'version' => '1.4.0',
                'date' => '2026-09-11',
                'date_human' => '11 September 2026',
                'title' => 'Metode Pembayaran QRIS & Pengaturan Unggah QRIS',
                'type' => 'feature',
                'author' => 'Tim Pengembang',
                'description' => 'Penambahan metode pembayaran QRIS di kasir (POS), fitur upload dan pratinjau QRIS di Pengaturan Toko, modal perbesar QRIS untuk pelanggan, penyesuaian struk, dan rekap shift non-tunai.',
                'changes' => [
                    [
                        'category' => 'Fitur Baru',
                        'type' => 'feat',
                        'title' => 'Pembayaran QRIS di Kasir & Modal Scan Pelanggan',
                        'description' => 'Kasir dapat memilih metode QRIS dengan nominal pas dan status lunas langsung. Dilengkapi modal perbesar QRIS di layar agar mudah discan pembeli.',
                    ],
                    [
                        'category' => 'Pengaturan Toko',
                        'type' => 'feat',
                        'title' => 'Unggah & Pratinjau QRIS Statis Toko (/pengaturan)',
                        'description' => 'Panel khusus untuk mengunggah, melihat pratinjau, mengganti, dan menghapus gambar QRIS toko dengan validasi berkas.',
                    ],
                    [
                        'category' => 'Struk & Pembukuan',
                        'type' => 'feat',
                        'title' => 'Struk QRIS & Rekap Shift Terpisah',
                        'description' => 'Metode QRIS tercetak di struk thermal/web. Omzet QRIS dicatat sebagai non-tunai pada shift agar uang laci fisik tetap akurat.',
                    ],
                ],
            ],
            [
                'version' => '1.3.0',
                'date' => '2026-09-07',
                'date_human' => '07 September 2026',
                'title' => 'Menu Log Aplikasi & Penyempurnaan Tampilan Filter',
                'type' => 'feature',
                'author' => 'Tim Pengembang',
                'description' => 'Penambahan menu Log Aplikasi untuk transparansi pembaruan sistem secara langsung, perbaikan tata letak filter jejak audit, dan integrasi kebijakan pencatatan berkala.',
                'changes' => [
                    [
                        'category' => 'Fitur Baru',
                        'type' => 'feat',
                        'title' => 'Menu Log Aplikasi (/app-logs)',
                        'description' => 'Menu baru di sidebar untuk melihat riwayat pembaruan, rilis versi, catatan perbaikan bug, dan perkembangan sistem yang selalu diperbarui.',
                    ],
                    [
                        'category' => 'Penyempurnaan UI/UX',
                        'type' => 'ui',
                        'title' => 'Tata Letak Filter Log Aktivitas Responsif',
                        'description' => 'Mengubah grid filter yang kaku menjadi tata letak fleksibel dengan kapsul rentang tanggal terpadu, menghilangkan masalah scrollbar horizontal yang sempat muncul di layar laptop.',
                    ],
                    [
                        'category' => 'Dokumentasi & Kebijakan',
                        'type' => 'docs',
                        'title' => 'Sinkronisasi PROGRESS.md & Log Aplikasi',
                        'description' => 'Pencatatan setiap perubahan sistem mulai hari ini dan seterusnya secara serempak di PROGRESS.md dan modul Log Aplikasi.',
                    ],
                ],
            ],
            [
                'version' => '1.2.0',
                'date' => '2026-09-07',
                'date_human' => '07 September 2026',
                'title' => 'Fitur Baca / Sembunyikan Notifikasi & Smart Re-Alerting',
                'type' => 'feature',
                'author' => 'Tim Pengembang',
                'description' => 'Penambahan fitur dismissal pada panel notifikasi lonceng dengan penyimpanan database dan mekanisme pintar untuk memunculkan kembali peringatan jika stok kian kritis.',
                'changes' => [
                    [
                        'category' => 'Fitur Baru',
                        'type' => 'feat',
                        'title' => 'Tandai Notifikasi Dibaca (Per Item & Semua)',
                        'description' => 'Tombol centang pada setiap baris peringatan stok menipis maupun kasbon jatuh tempo, serta tombol "Tandai Semua Dibaca" di header dropdown.',
                    ],
                    [
                        'category' => 'Logika Pintar',
                        'type' => 'system',
                        'title' => 'Smart Re-Alerting Stok Menipis',
                        'description' => 'Jika peringatan stok ditandai dibaca saat sisa 3, notifikasi tidak muncul lagi. Namun bila terjadi penjualan yang membuat stok turun ke 2 atau 1, notifikasi otomatis muncul kembali.',
                    ],
                    [
                        'category' => 'Pengujian Otomatis',
                        'type' => 'test',
                        'title' => 'Suite Pengujian Notifikasi',
                        'description' => 'Penambahan pengujian otomatis NotificationAlertsTest hingga 95 pengujian lulus 100% tanpa error.',
                    ],
                ],
            ],
            [
                'version' => '1.1.0',
                'date' => '2026-09-07',
                'date_human' => '07 September 2026',
                'title' => 'Paket Keamanan & Pemeliharaan (Backup, Audit Log, Notifikasi Lonceng)',
                'type' => 'feature',
                'author' => 'Tim Pengembang',
                'description' => 'Implementasi lengkap paket pemeliharaan sistem: cadangan basis data mandiri, pencatatan jejak audit, dan pusat notifikasi lonceng interaktif.',
                'changes' => [
                    [
                        'category' => 'Keamanan & Cadangan',
                        'type' => 'feat',
                        'title' => 'Cadangan Basis Data Mandiri (/backups)',
                        'description' => 'Pembuatan arsip database terkompresi .sql.gz berbasis PHP murni (PDO), unduh arsip, dan pemulihan (restore) database terproteksi kata sandi admin.',
                    ],
                    [
                        'category' => 'Jejak Audit',
                        'type' => 'feat',
                        'title' => 'Log Aktivitas Sistem (/audit-logs)',
                        'description' => 'Perekaman otomatis perubahan produk, modal/harga, penyesuaian stok manual, pembatalan nota kasir, retur barang, pelunasan kasbon, dan perubahan pengguna.',
                    ],
                    [
                        'category' => 'Antarmuka Pengguna',
                        'type' => 'ui',
                        'title' => 'Dropdown Notifikasi Lonceng Interaktif',
                        'description' => 'Menu popover pada lonceng navigasi desktop dan mobile yang merangkum stok kritis dan kasbon yang mendekati/melewati batas jatuh tempo.',
                    ],
                ],
            ],
        ];
    }
}
