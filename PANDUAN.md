# Panduan Pakai — Kios BERKAH

Panduan untuk yang menjaga toko. Ditulis urut sesuai kejadian sehari-hari, dari
menyiapkan aplikasi pertama kali sampai tutup toko.

Nama tombol di panduan ini **persis** seperti yang tampil di layar.

---

## Daftar isi

- [A. Sekali di awal — menyiapkan aplikasi](#a-sekali-di-awal--menyiapkan-aplikasi)
- [B. Rutinitas harian](#b-rutinitas-harian)
- [C. Melayani pembeli](#c-melayani-pembeli)
- [D. Kasbon & piutang](#d-kasbon--piutang)
- [E. Barang masuk & stok](#e-barang-masuk--stok)
- [F. Kalau ada yang salah](#f-kalau-ada-yang-salah)
- [G. Rekap laci (opsional)](#g-rekap-laci-opsional)
- [H. Melihat hasil jualan](#h-melihat-hasil-jualan)
- [I. Kalau aplikasi bermasalah](#i-kalau-aplikasi-bermasalah)

---

## A. Sekali di awal — menyiapkan aplikasi

Enam langkah ini cukup dikerjakan satu kali, sebelum mulai jualan.

### 1. Masuk & ganti kata sandi

1. Buka alamat aplikasi di browser (mis. <http://kios-nizam.test>).
2. Isi **Email** `admin@kios.test` dan **Password** `password`, tekan **Masuk**.
3. Klik kartu nama Anda di **pojok kiri bawah** → **Profil**.
4. Di kartu **Ganti kata sandi**: isi kata sandi sekarang (`password`), lalu kata
   sandi baru dua kali → **Simpan kata sandi**.

> Kata sandi bawaan diketahui siapa saja yang pernah melihat berkas proyek ini.
> Jangan mulai jualan sebelum menggantinya.

### 2. Isi identitas toko

1. Menu **Pengaturan** (sidebar, kelompok *Kelola*).
2. Isi **Nama toko**, **Alamat**, **Telepon**, **Kaki struk**
   (mis. "Terima kasih telah berbelanja" atau "Barang tidak dapat ditukar").
3. Lihat kotak **Pratinjau struk** di sebelah kanan — begitulah nanti bentuk
   struknya. → **Simpan pengaturan**.

Nama toko ini otomatis dipakai di struk, di sudut kiri atas aplikasi, dan di
judul tab browser.

### 3. Buat kategori

1. Menu **Kategori** (kelompok *Barang*).
2. Ketik nama kategori di kotak isian, tekan **Tambah**. Ulangi.
3. Contoh yang masuk akal untuk kios: *Minuman, Makanan, Rokok, Kebutuhan Rumah*.

Kategori dipakai sebagai tombol saring di layar Kasir, jadi jangan terlalu
banyak — cukup 4–8 kelompok besar.

### 4. Masukkan produk

1. Menu **Produk** → tombol **+ Tambah Produk**.
2. Isi:
   - **Nama** — tulis lengkap dengan ukuran, mis. "Aqua Botol 600ml".
   - **Barcode** — boleh dikosongkan. Kalau diisi, scanner bisa langsung
     menemukan barang ini.
   - **Kategori**.
   - **Harga jual** — yang dibayar pembeli.
   - **Harga modal** — yang Anda bayar ke grosir. Dipakai menghitung laba.
   - **Stok** — jumlah barang sekarang.
   - **Ambang menipis** — di angka berapa Anda ingin diingatkan untuk kulakan.
   - **Aktif — tampil di layar kasir** — biarkan tercentang. Hilangkan
     centangnya untuk barang yang sedang tidak dijual (barangnya tetap ada di
     daftar dan riwayat, hanya hilang dari layar Kasir).
3. **Simpan**. Ulangi untuk semua barang.

> Kalau barangnya banyak, isi dulu yang paling laku (20–30 barang). Sisanya bisa
> ditambah sambil jalan.

### 5. Daftarkan pelanggan kasbon

Hanya perlu kalau Anda melayani hutang.

1. Menu **Pelanggan** → **+ Tambah Pelanggan**.
2. Isi **Nama**, **Telepon**, **Alamat**, dan **Batas kredit**.
   - Batas kredit dikosongkan = tanpa batas.
   - Kalau diisi mis. `200000`, aplikasi akan menolak kasbon baru begitu total
     hutangnya melewati Rp200.000.
3. Centang **Blokir dari kasbon** untuk pelanggan yang tidak boleh berhutang
   lagi. Pelanggannya tetap tersimpan beserta riwayat hutangnya.

### 6. Buat akun pegawai (kalau ada)

1. Menu **Pengguna** → **+ Tambah Pengguna**.
2. Isi **Nama**, **Email**, **Password**, **Ulangi password**, lalu pilih
   **Peran**:
   - **admin** — bisa semua menu.
   - **kasir** — hanya Dashboard, Kasir, dan Tutup kasir. Boleh menjual
     (termasuk kasbon), tapi tidak bisa mengubah produk, harga, membatalkan
     nota, atau melihat laporan.

---

## B. Rutinitas harian

### Pagi — sebelum buka

1. Buka aplikasi, masuk dengan akun Anda.
2. Lihat **Dashboard**:
   - **Stok menipis** — kartu merah. Kalau angkanya lebih dari 0, klik
     **Perlu restok → Lihat semua** untuk daftar barang yang harus dikulak.
   - **Omzet hari ini** dan **Transaksi hari ini** mulai dari nol setiap
     pergantian hari.
3. Kalau ingin merekap uang laci hari itu, buka shift dulu — lihat
   [bagian G](#g-rekap-laci-opsional). **Ini opsional**; tanpa membuka shift pun
   kasir sudah bisa dipakai.

### Malam — sebelum tutup

1. Menu **Laporan** → tekan chip **Hari ini** untuk melihat omzet, laba kotor,
   dan jumlah transaksi hari itu.
2. Menu **Piutang** → cek siapa yang hutangnya sudah lewat jatuh tempo.
3. Kalau tadi pagi membuka shift, tutup sekarang — [bagian G](#g-rekap-laci-opsional).

---

## C. Melayani pembeli

### Jual tunai — alur paling sering

1. Menu **Kasir** (atau tombol **Buka kasir** di Dashboard).
2. **Masukkan barang** ke struk, tiga cara:
   - **Scan barcode** — kursor sudah otomatis di kotak scan. Barang langsung
     masuk.
   - **Ketik nama** lalu tekan **Enter** — kalau hasilnya tinggal satu, barang
     langsung masuk.
   - **Ketuk kartu produk**. Saring dulu dengan tombol kategori kalau perlu.
3. Barang yang sama diketuk dua kali = jumlahnya jadi 2. Untuk mengubah:
   - tombol **−** dan **+** di baris struk;
   - tombol **HAPUS** di kanan nama barang untuk membuang satu baris.
4. **Diskon** (kalau ada): isi kotak **Diskon** dengan nominal potongan, mis.
   `2000`. Total langsung menyesuaikan.
5. **Terima uang.** Isi kotak **Bayar** dengan uang yang diserahkan pembeli.
   Ada tombol cepat: **Pas** (uang pas) dan pecahan Rp5.000–Rp100.000.
6. Baris **Kembali** menunjukkan kembalian yang harus Anda berikan.
7. Tekan **Bayar**.
8. Layar berpindah ke **struk**. Tekan **Cetak** kalau pembeli mau strukanya,
   atau langsung **Transaksi baru** untuk melayani pembeli berikutnya.

**Yang otomatis terjadi:** stok berkurang, nomor nota dibuat
(`INV20260903-0001`), dan omzet hari ini bertambah.

### Membaca warna stok di kartu produk

Angka **STOK** di kartu produk berubah warna mengikuti kondisi barang:

| Warna | Artinya |
| --- | --- |
| 🟢 Hijau | Aman |
| 🟡 Kuning | Mulai menipis (sisa ≤ 2× ambang) |
| 🔴 Merah | Sudah di ambang menipis — waktunya kulakan |

Angkanya adalah **sisa setelah dikurangi isi struk**. Jadi kalau Anda memasukkan
banyak barang sekaligus dan warnanya berubah merah, itu peringatan bahwa stok
akan habis setelah transaksi ini.

Barang yang stoknya nol tidak bisa diketuk sama sekali.

---

## D. Kasbon & piutang

### Menjual dengan kasbon

1. Masukkan barang seperti biasa (langkah C1–C4).
2. Di panel struk, tekan tombol **Kasbon**.
3. Pilih **Pelanggan** dari daftar. Di bawahnya muncul hutang pelanggan itu
   sekarang dan batas kreditnya.
4. **Jatuh tempo** (opsional) — tanggal janji bayar. Dipakai halaman Piutang
   untuk menandai nota yang sudah lewat tempo.
5. **DP (opsional)** — kalau pembeli membayar sebagian di muka, isi di sini.
   Baris **Sisa hutang** menunjukkan yang belum dibayar.
6. Tekan **Simpan kasbon**.

Aplikasi menolak kalau pelanggan **diblokir** atau kalau hutangnya akan
**melewati batas kredit**.

### Menerima cicilan / pelunasan

1. Menu **Pelanggan** → cari namanya → **klik nama pelanggannya** (nama itu
   sendiri tautannya, bukan tombol terpisah).
2. Lihat kartu merah **Sisa hutang** dan daftar nota yang belum lunas.
3. Di kartu **Terima pembayaran**: isi jumlah uang yang dibayarkan, tambahkan
   catatan bila perlu → **Catat pembayaran**.

> Kartu **Terima pembayaran** hanya muncul kalau pelanggan itu memang punya
> hutang. Kalau sisa hutangnya sudah Rp0, kartunya sengaja disembunyikan.

Uangnya otomatis dibagi ke **nota paling lama dulu**. Nota yang sisanya habis
otomatis bertanda **LUNAS**.

### Melihat semua hutang toko

Menu **Piutang**:

- **Total piutang** — semua uang yang masih di tangan pembeli.
- **Umur hutang** — dikelompokkan **0–7 hari** (hijau), **8–30 hari** (kuning),
  **> 30 hari** (merah). Yang merah perlu ditagih.
- Tabel per pelanggan dan daftar nota belum lunas beserta penanda jatuh tempo.

---

## E. Barang masuk & stok

### Barang datang dari grosir

1. Menu **Barang masuk** → tombol **+ Catat barang masuk**.
2. **Jenis**: pilih **Barang masuk**.
3. **Pemasok** — nama grosir/toko tempat kulakan (opsional).
4. **Catatan** — mis. nomor nota belanja (opsional).
5. Untuk tiap barang: pilih **produk**, isi **Qty**, dan **Modal** (harga beli
   satuan). Tekan **+ Tambah baris** untuk barang berikutnya.
6. Lihat **Perkiraan nilai belanja** untuk mencocokkan dengan nota grosir.
7. **Simpan**.

Stok bertambah, dan **kalau harga modal berubah**, harga modal produk ikut
diperbarui — supaya perhitungan laba tetap benar.

### Barang rusak, hilang, atau salah hitung

Pakai jenis **Penyesuaian**, dan isi **Qty dengan angka minus**, mis. `-3` untuk
tiga barang yang rusak. Tulis alasannya di **Catatan**.

Aplikasi menolak penyesuaian yang membuat stok jadi minus.

### Melacak riwayat stok

Di halaman **Barang masuk**, tabel bawah mencatat **setiap** perubahan stok:

| Jenis | Muncul saat |
| --- | --- |
| **Barang masuk** | Anda mencatat kulakan |
| **Penjualan** | Ada transaksi di kasir |
| **Retur** | Pembeli mengembalikan barang |
| **Nota dibatalkan** | Sebuah nota dibatalkan |
| **Penyesuaian** | Anda mengoreksi stok manual |

Kolom **Sisa stok** menunjukkan stok setelah gerakan itu — berguna untuk melacak
di titik mana selisih stok mulai terjadi.

---

## F. Kalau ada yang salah

Nota yang sudah tersimpan **tidak bisa disunting isinya**. Ini disengaja: kalau
angka nota lama bisa diubah diam-diam, catatan toko tidak bisa dipercaya lagi.
Tersedia tiga jalan resmi:

### 1. Pembeli mengembalikan sebagian barang → **Retur**

1. Menu **Riwayat** → cari notanya → **Detail**.
2. Tombol **Retur barang**.
3. Isi jumlah yang dikembalikan per baris (tombol **Isi semua** untuk semuanya).
4. Tulis **Alasan**, mis. "barang penyok" → **Simpan retur**.

Stok kembali bertambah, nilai nota berkurang, dan kalau itu nota kasbon,
hutang pelanggan ikut menyusut.

### 2. Notanya salah total → **Batalkan**

1. Menu **Riwayat** → **Detail** → tombol **Batalkan nota**.
2. Tulis **Alasan** → **Ya, batalkan nota**.

Semua stok kembali, hutang dihapus, nota tetap tersimpan dengan cap
**Dibatalkan** supaya jejaknya jelas.

> Nota yang sudah pernah menerima cicilan **tidak bisa dibatalkan** — pakai
> retur, supaya uang yang sudah diterima tidak hilang dari catatan.

### 3. Hanya keterangannya yang salah → **Ubah keterangan**

Di halaman **Detail** nota, kartu **Ubah keterangan** bisa memperbaiki catatan,
pelanggan, dan tanggal jatuh tempo. Jumlah barang dan uang tidak bisa diubah
dari sini.

### Mencari nota lama

Menu **Riwayat** menyediakan: kotak cari (nomor nota / nama pelanggan /
catatan), rentang **Dari–Sampai**, pilihan **kasir**, dan **status**
(Tunai / Kasbon / Lunas / Belum lunas / Batal).

Nota batal ditampilkan dengan angka **dicoret**, dan nota yang pernah diretur
menampilkan nilai returnya di bawah total.

---

## G. Rekap laci (opsional)

Fitur ini untuk mencocokkan uang fisik di laci dengan catatan aplikasi. **Tidak
wajib** — kalau toko dijaga sendiri, boleh diabaikan sepenuhnya dan kasir tetap
jalan normal.

### Buka shift (pagi)

1. Menu **Tutup kasir** (di ponsel: tombol **Lainnya** → **Tutup kasir**).
2. Hitung uang yang ada di laci sekarang, isikan sebagai **Modal awal laci**.
3. **Buka shift**.

### Selama jualan

- Semua penjualan otomatis masuk rekap shift itu.
- Uang keluar-masuk di luar penjualan dicatat lewat tombol
  **Kas masuk / keluar** — mis. beli kantong plastik, ambil uang untuk belanja.

### Tutup shift (malam)

1. Tombol **Tutup shift**.
2. Aplikasi menampilkan **Seharusnya di laci** — hasil hitungannya:

   > modal awal **+** penjualan tunai **+** DP kasbon **+** pelunasan hutang
   > **+** kas masuk **−** kas keluar

3. Hitung uang fisik di laci, isikan di **Uang fisik dihitung**. **Selisih**
   langsung terlihat: hijau bila lebih, merah bila kurang.
4. Isi **Disetor ke pemilik** dan **Catatan** bila perlu → **Tutup shift**.

> Penjualan kasbon **tidak** dihitung sebagai uang laci — hanya DP-nya, karena
> sisanya memang belum diterima.

### Kalau lupa menutup

Shift yang dibiarkan terbuka sampai ganti hari akan **dikunci otomatis** pada
pukul 23:59:59 hari itu, dan diberi lencana **OTOMATIS** di riwayat. Kolom
"Dihitung" dan "Selisih" ditandai **tak dihitung** — karena memang tidak ada
yang menghitung uangnya. Hitungan hari berikutnya mulai dari nol.

---

## H. Melihat hasil jualan

Menu **Laporan** (khusus admin):

1. Pilih rentang tanggal lewat **Dari** dan **Sampai**, lalu **Terapkan** — atau
   pakai tombol cepat **Hari ini** / **7 hari** / **30 hari**.
2. Empat kartu ringkasan:
   - **Omzet** — total uang penjualan (nota batal & barang retur tidak dihitung).
   - **Laba kotor** — omzet dikurangi harga modal barang yang terjual. Angka ini
     hanya benar kalau **harga modal produk diisi rajin**.
   - **Transaksi** — jumlah nota.
   - **Total diskon** — potongan yang Anda berikan.
3. Di bawahnya: grafik **Omzet harian**, **Produk terlaris**, dan **Transaksi
   terakhir** (tautan **Riwayat lengkap** untuk daftar penuh).

---

## I. Kalau aplikasi bermasalah

| Gejala | Yang terjadi & apa yang harus dilakukan |
| --- | --- |
| Halaman **403 — "Halaman ini bukan untuk akun kamu"** | Anda masuk sebagai kasir dan membuka menu khusus admin. Masuk dengan akun admin. |
| Halaman **404 — "Halamannya tidak ada"** | Alamat salah atau datanya sudah dihapus. Tekan **Kembali ke beranda**. |
| Halaman **419 — "Sesi kamu sudah kedaluwarsa"** | Halaman dibiarkan terbuka terlalu lama. Muat ulang lalu ulangi. Isian yang belum tersimpan perlu diisi ulang. |
| **500 — "Ada yang salah di sistem"** | Catat apa yang sedang dikerjakan saat itu, lalu sampaikan ke yang mengurus aplikasi. |
| Lupa kata sandi | Minta admin lain menggantikannya lewat menu **Pengguna** → **Ubah** → isi **Password** baru. Tautan "Lupa password?" di halaman masuk belum berfungsi karena butuh pengaturan email. |
| Barang tidak muncul di kasir | Cek menu **Produk** — kemungkinan statusnya tidak aktif, atau stoknya nol. |
| Stok di aplikasi beda dengan barang asli | Buka **Barang masuk**, telusuri tabel pergerakan stok untuk menemukan titik selisihnya, lalu perbaiki dengan **Penyesuaian**. |
| Aplikasi tidak bisa dibuka sama sekali | Pastikan **Laragon menyala** (Apache + MySQL). |

### Kebiasaan yang menjaga catatan tetap benar

1. **Catat barang masuk setiap kali kulakan** — bukan sebulan sekali. Stok dan
   laba ikut salah kalau ini telat.
2. **Isi harga modal** setiap produk. Tanpa itu, angka laba di Laporan tidak
   ada artinya.
3. **Jangan hapus produk** yang pernah terjual — buka **Ubah**, lalu hilangkan
   centang **Aktif — tampil di layar kasir**. Menghapusnya membuat riwayat
   penjualan kehilangan kaitan ke produk.
4. **Perbaiki kesalahan lewat Retur atau Batal**, jangan dengan mengubah stok
   diam-diam lewat Penyesuaian — supaya alasannya tercatat.
