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
                'version' => '1.7.1',
                'date' => '2026-09-25',
                'date_human' => '25 September 2026',
                'title' => 'Semua Aktivitas Toko Terpantau dari Lonceng',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Lonceng pemberitahuan kini punya tab Aktivitas: setiap penjualan, pembelian arang, perubahan barang, pegawai yang masuk atau keluar, hingga percobaan masuk yang gagal langsung terlihat — tanpa harus membuka menu lain.',
                'changes' => [
                    [
                        'category' => '🔔 Tab Aktivitas',
                        'type' => 'feat',
                        'title' => 'Pantau Kegiatan Pegawai dari Lonceng',
                        'description' => 'Lihat siapa menjual apa, siapa mengubah harga atau menambah barang, dan kapan shift dibuka atau ditutup. Angka merah di lonceng menunjukkan kegiatan pegawai yang belum Anda lihat, dan isinya diperbarui sendiri setiap menit.',
                    ],
                    [
                        'category' => '🛡️ Keamanan',
                        'type' => 'sec',
                        'title' => 'Percobaan Masuk Tanpa Izin Langsung Ketahuan',
                        'description' => 'Kata sandi salah, email tak terdaftar, login yang dikunci karena terlalu sering salah, dan pegawai yang mencoba membuka halaman khusus admin ditandai merah di lonceng lengkap dengan alamat IP-nya.',
                    ],
                    [
                        'category' => '📝 Log Aktivitas',
                        'type' => 'feat',
                        'title' => 'Catatan Lebih Lengkap',
                        'description' => 'Log Aktivitas kini juga mencatat penjualan kasir, jual dan beli arang, jenis arang, kategori, pelanggan, shift dan kas laci, perubahan profil dan kata sandi, unduhan laporan, serta setiap masuk dan keluar aplikasi.',
                    ],
                ],
            ],
            [
                'version' => '1.7.0',
                'date' => '2026-09-25',
                'date_human' => '25 September 2026',
                'title' => 'Jual per Dus, Harga Grosir, Diskon Fleksibel & PPN',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Pembaruan besar untuk kasir dan laporan. Barang kini bisa dijual per pcs maupun per dus dengan harga grosir otomatis, diskon bisa diberikan per barang atau dalam persen, PPN bisa diaktifkan bila toko Anda PKP, dan laporan bisa disaring per kasir atau kategori lalu diunduh sebagai PDF.',
                'changes' => [
                    [
                        'category' => '📦 Satuan Ganda',
                        'type' => 'feat',
                        'title' => 'Jual per Pcs atau per Dus',
                        'description' => 'Di menu Produk, tambahkan satuan lain seperti dus, pak, atau renteng lengkap dengan isi, harga, dan barcode sendiri. Di kasir tinggal pilih satuannya atau scan barcode dus — stok tetap dihitung per pcs secara otomatis.',
                    ],
                    [
                        'category' => '🏷️ Harga Grosir',
                        'type' => 'feat',
                        'title' => 'Harga Turun Otomatis untuk Pembelian Banyak',
                        'description' => 'Atur harga grosir bertingkat, misalnya beli 10 pcs ke atas jadi Rp3.300. Begitu jumlahnya tercapai di kasir, harga langsung menyesuaikan dan diberi tanda "Harga grosir".',
                    ],
                    [
                        'category' => '✂️ Diskon Fleksibel',
                        'type' => 'feat',
                        'title' => 'Diskon per Barang dan Diskon Persen',
                        'description' => 'Beri potongan untuk satu barang saja lewat "+ Diskon barang", atau untuk seluruh nota. Keduanya bisa dalam rupiah atau persen — cukup tekan tombol Rp / %.',
                    ],
                    [
                        'category' => '🧾 PPN Opsional',
                        'type' => 'feat',
                        'title' => 'Tarik PPN di Kasir',
                        'description' => 'Untuk toko PKP: aktifkan PPN di Pengaturan dan tentukan tarifnya. PPN otomatis dihitung, tercetak di struk, dan dilaporkan terpisah di halaman Laporan.',
                    ],
                    [
                        'category' => '📊 Laporan',
                        'type' => 'feat',
                        'title' => 'Saring per Kasir & Kategori, Unduh PDF',
                        'description' => 'Lihat hasil jualan satu kasir atau satu kategori barang saja. Laporan juga bisa diunduh sebagai PDF A4 yang rapi, siap dicetak atau dikirim — mengikuti saringan yang sedang dipakai.',
                    ],
                    [
                        'category' => '🔎 Kode di Struk',
                        'type' => 'feat',
                        'title' => 'QR / Barcode Nomor Nota di Struk',
                        'description' => 'Pilih di Pengaturan agar nomor nota tercetak sebagai QR code atau barcode. Saat pembeli datang untuk retur, scan struknya di menu Riwayat dan notanya langsung terbuka.',
                    ],
                    [
                        'category' => '🖨️ Cetak dari PC',
                        'type' => 'feat',
                        'title' => 'Cetak Langsung ke Printer USB / COM',
                        'description' => 'Selain Bluetooth, halaman struk di Chrome/Edge PC kini punya tombol "Printer USB / COM" untuk mencetak langsung tanpa kotak dialog cetak.',
                    ],
                    [
                        'category' => '🗃️ Arsip Produk',
                        'type' => 'fix',
                        'title' => 'Produk Dihapus Bisa Dipulihkan',
                        'description' => 'Menghapus produk kini hanya mengarsipkannya — riwayat nota dan stoknya tetap utuh. Buka saringan "Produk terhapus" untuk memulihkannya kapan saja.',
                    ],
                    [
                        'category' => '🛠️ Lebih Andal',
                        'type' => 'fix',
                        'title' => 'Perhitungan Uang Lebih Kokoh',
                        'description' => 'Penyimpanan angka uang di database diperkuat sehingga perhitungan laba dan hutang tidak lagi bisa gagal saat hasilnya minus. Tombol-tombol di halaman arang juga kembali tampil rapi.',
                    ],
                ],
            ],
            [
                'version' => '1.6.2',
                'date' => '2026-09-25',
                'date_human' => '25 September 2026',
                'title' => 'Toko Lebih Aman & Lonceng Notifikasi Lebih Pintar',
                'type' => 'fix',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Pembaruan keamanan dan kerapian menyeluruh. Nomor nota tidak lagi bisa kembar walau dua kasir menyimpan bersamaan, harga arang kini sepenuhnya di tangan admin, struk hanya bisa dibuka oleh yang berhak, dan lonceng notifikasi lebih cermat mengingatkan stok menipis serta kasbon jatuh tempo.',
                'changes' => [
                    [
                        'category' => '🔐 Keamanan',
                        'type' => 'sec',
                        'title' => 'Login dan Ekspor Laporan Lebih Aman',
                        'description' => 'Batas percobaan login kini tidak bisa diakali, dan akun otomatis dikunci sementara bila kata sandi salah berkali-kali. File Excel/CSV hasil ekspor juga aman dibuka: tulisan dari pengguna tidak akan pernah dijalankan sebagai rumus.',
                    ],
                    [
                        'category' => '🧾 Nomor Nota',
                        'type' => 'fix',
                        'title' => 'Nomor Nota Tidak Pernah Kembar',
                        'description' => 'Dua kasir yang menyimpan transaksi di detik yang sama kini tetap mendapat nomor nota berurutan — tidak ada lagi transaksi gagal tersimpan. Berlaku untuk nota kasir, jual arang, dan beli arang.',
                    ],
                    [
                        'category' => '🔥 Jual Arang',
                        'type' => 'feat',
                        'title' => 'Harga Arang Ditentukan Admin, Pembayaran Tunai atau QRIS',
                        'description' => 'Kasir otomatis memakai harga jual yang diatur admin di Jenis Arang, tanpa bisa mengubah harga atau memberi diskon. Admin tetap leluasa menyesuaikan harga dan diskon saat menjual. Penjualan arang kini hanya tunai atau QRIS, sesuai aturan toko.',
                    ],
                    [
                        'category' => '🛡️ Struk & Data',
                        'type' => 'sec',
                        'title' => 'Struk Hanya untuk Admin dan Pembuatnya',
                        'description' => 'Struk kasir dan nota arang kini hanya bisa dibuka admin atau pegawai yang membuat transaksinya. Produk nonaktif juga tidak bisa lagi terjual, dan admin terakhir tidak bisa terhapus secara tidak sengaja.',
                    ],
                    [
                        'category' => '🔔 Lonceng Notifikasi',
                        'type' => 'fix',
                        'title' => 'Notifikasi Lebih Cermat dan Nyaman Dipakai',
                        'description' => 'Stok yang sudah diisi ulang lalu menipis lagi kembali diingatkan, dan kasbon yang ditandai sebelum tempo muncul lagi saat lewat tempo. Panel tetap terbuka saat mencentang notifikasi, tampil penuh di layar HP, dan tombol "Lihat Semua Produk Menipis" langsung menampilkan produk yang menipis saja.',
                    ],
                    [
                        'category' => '💾 Cadangan Data',
                        'type' => 'fix',
                        'title' => 'Pesan Gagal Cadangan Lebih Jelas',
                        'description' => 'Bila pencadangan atau pemulihan gagal, pesan yang tampil kini singkat dan mudah dipahami. Rincian teknisnya tersimpan di catatan aplikasi untuk pemeriksaan.',
                    ],
                ],
            ],
            [
                'version' => '1.6.1',
                'date' => '2026-09-25',
                'date_human' => '25 September 2026',
                'title' => 'Scan Barcode Pakai Kamera HP',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Tidak punya alat scanner? Tidak masalah! Kamera HP kini bisa dipakai untuk scan barcode, baik di Kasir maupun saat mendaftarkan produk baru. Tampilan Dashboard di ponsel dan laptop juga dirapikan.',
                'changes' => [
                    [
                        'category' => '📷 Scan Kamera',
                        'type' => 'feat',
                        'title' => 'Tombol "Kamera" di Kasir',
                        'description' => 'Tekan tombol "Kamera" di kotak cari kasir, arahkan ke barcode, dan barang langsung masuk keranjang. Kamera tetap menyala untuk scan beruntun, lengkap dengan senter, getar, dan bunyi sebagai tanda berhasil.',
                    ],
                    [
                        'category' => '📦 Data Produk',
                        'type' => 'feat',
                        'title' => 'Isi Barcode Produk Lewat Kamera',
                        'description' => 'Saat menambah atau mengubah produk, barcode cukup di-scan dengan kamera — tidak perlu mengetik angka panjang satu per satu.',
                    ],
                    [
                        'category' => '📊 Dashboard',
                        'type' => 'ui',
                        'title' => 'Angka Omzet Selalu Rapi di Kartu',
                        'description' => 'Angka omzet yang besar tidak lagi keluar dari kartu di layar ponsel maupun laptop.',
                    ],
                ],
            ],
            [
                'version' => '1.6.0',
                'date' => '2026-09-25',
                'date_human' => '25 September 2026',
                'title' => 'QRIS Dinamis: Pembeli Cukup Scan, Nominal Langsung Terisi',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Bayar pakai QRIS kini secepat bayar tunai! Kode QR di layar kasir otomatis berisi total belanja, jadi pembeli tinggal scan dan konfirmasi di aplikasi e-wallet atau m-banking mereka — tidak perlu mengetik nominal, tidak ada lagi salah ketik angka. Uang tetap masuk ke rekening QRIS toko Anda seperti biasa.',
                'changes' => [
                    [
                        'category' => '⚡ QRIS Dinamis',
                        'type' => 'feat',
                        'title' => 'Nominal Belanja Otomatis Masuk ke Kode QR',
                        'description' => 'Setiap kali metode QRIS dipilih, aplikasi membuat kode QR baru yang sudah berisi total belanja. Pembeli scan, nominal langsung muncul, tinggal bayar. Berlaku di Kasir maupun Jual Arang, termasuk tampilan layar penuh lewat tombol "Perbesar".',
                    ],
                    [
                        'category' => '✅ Cek QRIS Instan',
                        'type' => 'feat',
                        'title' => 'Tahu Langsung Apakah QRIS Anda Siap Dipakai',
                        'description' => 'Saat mengunggah gambar QRIS di menu Pengaturan, aplikasi langsung memeriksa kodenya dan menampilkan nama merchant yang terbaca. Jika gambar kurang jelas, Anda langsung diberi saran agar QRIS dinamis bisa berjalan sempurna.',
                    ],
                    [
                        'category' => '🛠️ Lebih Andal',
                        'type' => 'fix',
                        'title' => 'Gambar QRIS Selalu Tampil di Kasir',
                        'description' => 'Gambar QRIS kini dijamin tampil di layar kasir dan halaman Jual Arang tanpa pengaturan server tambahan. Tidak ada lagi kotak QRIS kosong saat pembeli siap membayar.',
                    ],
                ],
            ],
            [
                'version' => '1.5.4',
                'date' => '2026-09-25',
                'date_human' => '25 September 2026',
                'title' => 'Arang Makin Rapi: Menu Khusus, Nomor Nota, dan Pencarian Kilat',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Usaha arang Anda kini punya "ruang kerja" sendiri di menu samping. Setiap pembelian arang dari pembuat juga mendapat nomor nota resmi, dan seluruh riwayat bisa dicari dalam sekejap — cukup ketik nomor nota atau nama pemasok / pembeli.',
                'changes' => [
                    [
                        'category' => '🗂️ Menu Arang Sendiri',
                        'type' => 'ui',
                        'title' => 'Semua Urusan Arang dalam Satu Kelompok Menu',
                        'description' => 'Ringkasan Arang, Jual Arang, Beli Arang, Riwayat Arang, dan Jenis Arang kini berkumpul dalam kelompok "Arang" di menu samping. Satu klik langsung ke tujuan, tanpa perlu mencari tombol di halaman.',
                    ],
                    [
                        'category' => '🧾 Nomor Nota Pembelian',
                        'type' => 'feat',
                        'title' => 'Setiap Pembelian Arang Punya Nomor Nota',
                        'description' => 'Pembelian arang dari pembuat atau pemasok kini otomatis mendapat nomor nota (contoh: BELI-ARNG-20260925-0001) yang tercetak di nota dan tersimpan rapi. Nota lama tetap memakai nomor yang sudah tercetak, jadi arsip Anda tidak berubah.',
                    ],
                    [
                        'category' => '🔎 Pencarian Kilat',
                        'type' => 'feat',
                        'title' => 'Cari Riwayat Arang dengan Nomor Nota atau Nama',
                        'description' => 'Halaman Riwayat Arang kini punya kotak pencarian. Ketik nomor nota, nama pemasok, atau nama pembeli — hasilnya muncul seketika dan bisa dipadukan dengan filter jenis arang serta tanggal.',
                    ],
                    [
                        'category' => '🛠️ Perbaikan',
                        'type' => 'fix',
                        'title' => 'Tombol Tambah Jenis Arang Kembali Muncul',
                        'description' => 'Tombol di bagian atas halaman-halaman arang, termasuk "Tambah Jenis Arang", kini tampil sebagaimana mestinya sehingga varian arang baru bisa langsung ditambahkan.',
                    ],
                ],
            ],
            [
                'version' => '1.5.3',
                'date' => '2026-09-25',
                'date_human' => '25 September 2026',
                'title' => 'Kasir Kilat: Cukup Scan, Barang Langsung Masuk Keranjang',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Antrean pelanggan di kasir kini makin cepat! Cukup arahkan scanner ke barcode produk dan barang langsung masuk ke keranjang belanja — tanpa perlu tekan Enter, tanpa klik apa pun. Belanjaan sebanyak apa pun bisa dilayani beruntun dalam hitungan detik. Jika ada barcode yang belum terdaftar, kasir langsung diberi tahu lewat bunyi dan pesan yang jelas.',
                'changes' => [
                    [
                        'category' => '⚡ Scan Kilat',
                        'type' => 'feat',
                        'title' => 'Scan Barcode Langsung Masuk Keranjang Tanpa Tekan Enter',
                        'description' => 'Begitu barcode di-scan, produk otomatis masuk ke keranjang dan kotak cari langsung siap untuk scan berikutnya. Scan barang yang sama berkali-kali? Jumlahnya otomatis bertambah. Tangan kasir bebas fokus ke barang, bukan ke keyboard.',
                    ],
                    [
                        'category' => '🔔 Peringatan Barcode',
                        'type' => 'feat',
                        'title' => 'Barcode Tak Dikenal? Langsung Ketahuan Penyebabnya',
                        'description' => 'Jika barcode yang di-scan belum terdaftar, terdengar bunyi peringatan dan muncul pesan berisi kode hasil scan beserta kemungkinan penyebabnya — produk belum didaftarkan, barcode di data produk berbeda dengan di kemasan, atau scan kurang sempurna. Tersedia tautan untuk membuka menu Produk di tab baru, jadi isi keranjang tetap aman.',
                    ],
                    [
                        'category' => '🛡️ Lebih Akurat',
                        'type' => 'fix',
                        'title' => 'Tidak Ada Lagi Barang Salah Masuk Keranjang',
                        'description' => 'Hasil scan kini hanya diterima jika barcode-nya cocok persis. Barcode yang mirip sebagian tidak akan lagi memasukkan produk lain ke keranjang, sehingga total belanja pelanggan selalu tepat.',
                    ],
                ],
            ],
            [
                'version' => '1.5.2',
                'date' => '2026-09-11',
                'date_human' => '11 September 2026',
                'title' => 'Ekspor Excel Resmi (.xlsx) Multi-Sheet Siap Saji Tanpa Olah Data',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Laporan penjualan kini dapat diunduh langsung sebagai file Microsoft Excel murni (.xlsx) dengan 4 lembar kerja (Ringkasan Eksekutif, Penjualan Toko, Penjualan Arang, dan Pembelian Stok Arang). Seluruh tabel sudah bergaris rapi, berwarna resmi, berformat angka Rupiah standar akuntansi, dan dilengkapi rumus jumlah otomatis (=SUM) sehingga siap dicetak atau diarsipkan tanpa perlu edit manual lagi.',
                'changes' => [
                    [
                        'category' => '📊 Ekspor Excel (.xlsx)',
                        'type' => 'feat',
                        'title' => 'Buku Kerja Multi-Sheet Rapi & Standar Akuntansi',
                        'description' => 'File Excel terbagi rapi menjadi 4 sheet: Ringkasan Eksekutif, Penjualan Toko Eceran, Penjualan Arang Kiloan, dan Pembelian Stok Arang dari Pembuat. Setiap sheet memiliki kop toko, tanggal cetak, dan judul resmi.',
                    ],
                    [
                        'category' => '💰 Format Angka & Rumus Otomatis',
                        'type' => 'ui',
                        'title' => 'Format Rupiah Asli & Rumus SUM Siap Pakai',
                        'description' => 'Seluruh angka omzet, modal, diskon, dan grand total otomatis berformat Rupiah ("Rp #,##0") yang tetap berupa angka asli (bukan teks) sehingga bisa dijumlahkan. Baris total di akhir tabel sudah otomatis menghitung dengan rumus SUM.',
                    ],
                    [
                        'category' => '🔒 Kerapian Tampilan',
                        'type' => 'ui',
                        'title' => 'Kolom Pas & Freeze Header Otomatis',
                        'description' => 'Lebar kolom otomatis disesuaikan dengan isi teks sehingga tidak ada tulisan terpotong atau tanda ###. Baris judul tabel dibekukan (freeze pane) agar tetap terlihat saat menggulir ribuan data transaksi.',
                    ],
                ],
            ],
            [
                'version' => '1.5.1',
                'date' => '2026-09-11',
                'date_human' => '11 September 2026',
                'title' => 'Laporan Keuangan Terpadu & Ekspor Excel Sekali Klik',
                'type' => 'feature',
                'author' => 'Tim Kios BERKAH',
                'description' => 'Halaman Laporan kini menggabungkan seluruh data toko — eceran dan arang kiloan — dalam satu tampilan. Lihat omzet gabungan, laba kotor terpadu, dan perincian Toko vs Arang secara berdampingan. Ditambah tombol Ekspor Excel/CSV untuk mengunduh pembukuan lengkap ke file yang langsung terbuka rapi di Microsoft Excel.',
                'changes' => [
                    [
                        'category' => '📊 Laporan Terpadu',
                        'type' => 'feat',
                        'title' => 'Omzet, Laba, dan Transaksi Gabungan (Toko Eceran + Arang Kiloan)',
                        'description' => 'Halaman Laporan kini menjumlahkan penjualan toko eceran dan arang kiloan menjadi satu angka. Tersedia juga panel perincian berdampingan agar Anda bisa membandingkan kontribusi masing-masing secara langsung.',
                    ],
                    [
                        'category' => '📥 Ekspor Excel',
                        'type' => 'feat',
                        'title' => 'Unduh Laporan CSV/Excel Lengkap Sekali Klik',
                        'description' => 'Tombol "Ekspor Excel / CSV" di halaman Laporan mengunduh file pembukuan lengkap: ringkasan eksekutif keuangan, daftar transaksi toko, daftar penjualan arang, dan daftar pembelian stok arang dari pembuat — semuanya dalam format yang langsung terbuka rapi di Excel atau Google Sheets.',
                    ],
                    [
                        'category' => '🏠 Dashboard Lebih Lengkap',
                        'type' => 'ui',
                        'title' => 'Omzet Hari Ini & Tren 7 Hari Mencakup Arang',
                        'description' => 'Kartu "Omzet hari ini" di Dashboard kini menjumlahkan penjualan eceran toko dan penjualan arang kiloan. Peringatan stok menipis juga kini mencakup varian arang yang stoknya di bawah 10 kg.',
                    ],
                ],
            ],
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
