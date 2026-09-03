# Kios BERKAH — Rangkuman Proyek

Aplikasi kasir (POS) untuk toko kelontong: penjualan dan struk, kasbon/piutang,
stok masuk-keluar, laporan, dan multi-pengguna. Antarmuka berbahasa Indonesia,
uang dalam bilangan bulat Rupiah, zona waktu `Asia/Jakarta`.

Berkas ini ringkasan satu halaman. Rinciannya ada di dokumen lain — lihat
[Peta dokumen](#peta-dokumen) di bawah.

Terakhir diperbarui: 3 September 2026.

---

## Sekilas

| Hal | Isi |
| --- | --- |
| Nama tampilan | Kios BERKAH (folder/repo: `kios-nizam`) |
| Framework | Laravel 13, PHP 8.3 |
| Frontend | Inertia.js + Vue 3 + Tailwind CSS (scaffold Breeze) |
| Database | MySQL 8.0 (`kios_nizam`); pengujian pakai SQLite di memori |
| Autentikasi | Login bawaan Breeze; pendaftaran mandiri dimatikan |
| Peran | `admin` dan `kasir` |
| Ukuran | 14 controller aplikasi (+ 9 bawaan Breeze), 11 model, 17 migrasi, 22 komponen halaman Vue |
| Pengujian | `php artisan test` — 81 lulus, 488 assertion |

## Modul

| Modul | Halaman | Akses | Inti |
| --- | --- | --- | --- |
| Kasir | `/pos` | admin + kasir | Scan barcode / cari nama, keranjang, diskon nominal, bayar tunai atau kasbon, stok berkurang dalam satu transaksi basis data |
| Struk | `/pos/struk/{sale}` | admin + kasir | Tampilan pita kertas thermal, cetak lewat dialog browser, blok kasbon dan cap lunas |
| Riwayat | `/sales` | admin | Daftar dan detail nota; ubah keterangan, batalkan nota, retur sebagian |
| Produk | `/products` | admin | CRUD, cari, saring kategori dan status stok, lencana stok |
| Kategori | `/categories` | admin | Tambah, ubah inline, hapus |
| Pelanggan | `/customers` | admin | CRUD, batas kredit, blokir, detail hutang, terima pembayaran (FIFO ke nota terlama) |
| Piutang | `/piutang` | admin | Total piutang, umur hutang 0–7 / 8–30 / >30 hari, rekap per pelanggan |
| Barang masuk | `/stok` | admin | Penerimaan barang dan penyesuaian, buku besar seluruh pergerakan stok |
| Tutup kasir | `/shift` | admin + kasir | Shift laci opsional, kas masuk/keluar, hitungan dan selisih laci |
| Laporan | `/reports` | admin | Omzet, laba kotor, jumlah transaksi, grafik harian, produk terlaris |
| Pengguna | `/users` | admin | CRUD akun dan peran |
| Pengaturan | `/pengaturan` | admin | Identitas toko yang dipakai struk dan sidebar |
| Dashboard | `/dashboard` | admin + kasir | Metrik hari ini, daftar perlu restok, grafik 7 hari |

## Keputusan yang membentuk aplikasi

- **Uang disimpan sebagai bilangan bulat Rupiah.** Tanpa desimal, tanpa tipe
  pecahan — pembulatan hanya muncul saat diskon nota diprorata ke retur.
- **Penjualan ditulis dalam satu transaksi basis data** dengan `lockForUpdate`,
  dan harga diambil ulang dari basis data, bukan dari yang dikirim peramban.
- **Nota tidak pernah dihapus.** Koreksi lewat pembatalan (stok kembali, nota
  bercap "Dibatalkan") atau retur sebagian (`returned_qty` naik, nilai nota dan
  hutang menyusut). Nota yang sudah dicicil tak boleh dibatalkan, hanya diretur.
- **Shift kasir bersifat opsional.** Kios dijaga pemiliknya sendiri, jadi
  mewajibkan buka-tutup laci hanya menambah langkah. Kalau kebetulan ada shift
  terbuka, nota menempel ke sana; kalau tidak, penjualan tetap jalan.
- **Shift yang lupa ditutup dikunci pada `23:59:59`** hari itu lewat dua lapis
  pengaman: perintah terjadwal dan penguncian susulan saat aplikasi dibuka lagi.
  Uang yang tak pernah dihitung disimpan `null`, bukan nol.
- **Kas keluar hanya dicatat kalau shift asal nota sudah ditutup.** Selama shift
  asal masih terbuka, rekapnya mengoreksi diri sendiri; mencatatnya lagi akan
  memotong laci dua kali.
- **Pengujian memakai SQLite di memori** supaya cepat dan tak menyentuh data
  asli. Konsekuensinya ada kelas galat yang hanya muncul di MySQL — lihat
  bagian 7 [`PROGRESS.md`](PROGRESS.md) sebelum percaya pada test yang hijau.

## Struktur data

Sebelas tabel inti: `users`, `categories`, `products`, `sales`, `sale_items`,
`customers`, `credit_payments`, `stock_movements`, `cash_sessions`,
`cash_movements`, `settings`.

Dua di antaranya berperan sebagai buku besar yang tak diubah setelah ditulis:
`stock_movements` (setiap perubahan stok beserta sisa stoknya) dan
`credit_payments` (setiap pelunasan hutang). Nilai barang di `sale_items`
adalah salinan saat transaksi, jadi perubahan harga produk tidak mengubah
nota lama.

Rincian kolom ada di bagian 2 [`PROGRESS.md`](PROGRESS.md).

## Desain antarmuka

Font Hanken Grotesk untuk teks dan JetBrains Mono untuk semua angka. Warna utama
hijau `#1E6B4F` di atas latar `#F4F6F5`. Tata letak menyesuaikan lebar layar:
sidebar penuh di desktop, rail ikon di tablet, tab bar bawah di ponsel.
Keranjang kasir dan struk memakai gaya "pita kertas" yang sama.

Acuan aslinya di [`design.md`](design.md); penyimpangan yang disengaja dicatat
di bagian 5 [`PROGRESS.md`](PROGRESS.md).

## Status

Seluruh alur harian sudah jalan: menjual, mencetak struk, mencatat kasbon,
menerima pelunasan, menerima barang, menutup laci, dan melihat laporan.
Aplikasi sudah dipakai di atas MySQL dan sudah diperiksa tampilannya di lebar
1440 / 820 / 390 piksel lewat Microsoft Edge.

Yang masih terbuka, ringkasnya:

- **Perlu diputuskan pemilik** — nasib fitur shift, model laci bila nanti ada
  pegawai.
- **Sebelum dipakai sungguhan** — ganti password akun contoh, bersihkan data
  seeder, setel `.env` produksi (`APP_DEBUG=false`).
- **Fitur** — diskon per item dan persen, QRIS/transfer, ekspor laporan,
  cetak langsung printer thermal, log aktivitas, backup dan restore.
- **Tampilan** — unggah logo sendiri, grafik interaktif, foto produk, impor
  produk massal, dialog konfirmasi hapus yang rapi, aksesibilitas.
- **Uji di perangkat asli** — tablet atau ponsel fisik, belum pernah disentuh.

Daftar lengkap dan terkini ada di bagian 9 [`PROGRESS.md`](PROGRESS.md) — daftar
itu satu-satunya sumber; jangan menyalinnya ke berkas lain.

## Peta dokumen

| Berkas | Untuk siapa | Isinya |
| --- | --- | --- |
| [`README.md`](README.md) | Yang memasang | Cara memasang dan menjalankan |
| [`PANDUAN.md`](PANDUAN.md) | Penjaga toko | Cara memakai sehari-hari |
| [`PROGRESS.md`](PROGRESS.md) | Yang mengembangkan | Keputusan teknis, struktur data, pengujian, daftar pekerjaan |
| [`design.md`](design.md) | Yang mengembangkan | Acuan desain: warna, font, bentuk |
| `RANGKUMAN.md` (berkas ini) | Pembaca baru | Gambaran umum satu halaman |
