# Progres Pembangunan — Kios BERKAH POS

Catatan teknis aplikasi kasir untuk toko/kios milik tante: keputusan yang
diambil, apa yang sudah jalan, dan apa yang belum.
Terakhir diperbarui: 3 September 2026.

> Nama tampilan aplikasi: **Kios BERKAH**. Nama folder / repo tetap `kios-nizam`.

**Dokumen lain — masing-masing satu tugas, tanpa isi kembar:**

| Berkas | Isinya |
| --- | --- |
| [`README.md`](README.md) | Cara memasang & menjalankan aplikasi |
| [`PANDUAN.md`](PANDUAN.md) | Cara memakai aplikasi sehari-hari (untuk penjaga toko) |
| [`design.md`](design.md) | Acuan desain asli (warna, font, bentuk) |
| `PROGRESS.md` (berkas ini) | Keputusan teknis, fitur, pengujian, daftar pekerjaan |

---

## 1. Fondasi proyek

| Hal | Keputusan |
| --- | --- |
| Framework | Laravel 13 |
| Frontend | Inertia.js + Vue 3 + Tailwind CSS (scaffold Laravel Breeze, stack Vue) |
| Database | MySQL 8.0 — database `kios_nizam` lewat Laragon |
| Autentikasi | Bawaan Breeze (login saja); pendaftaran mandiri **dimatikan** |
| Bahasa antarmuka | Indonesia |
| Zona waktu | `Asia/Jakarta` |
| Uang | Bilangan bulat Rupiah (tanpa desimal) |

### Catatan database

Awalnya SQLite (MySQL Laragon belum menyala), lalu dipindah ke **MySQL**:
`.env` = `mysql` / `127.0.0.1:3306` / user `root` tanpa password, lalu
`php artisan migrate:fresh --seed`. `database/database.sqlite` lama tak dipakai.
Pengujian tetap SQLite `:memory:` (di `phpunit.xml`) — sengaja, biar cepat dan
tak menyentuh data asli.

### Penyesuaian toolchain

- **Vite dikunci v6** (`@vitejs/plugin-vue@5`, `laravel-vite-plugin@1`) karena
  Node terpasang v20.14 (Vite 8 butuh ≥ 20.19). Naikkan Node ke 22 LTS lalu boleh
  kembalikan ke versi terbaru.
- `resources/js/bootstrap.js` dibuat manual (axios).

---

## 2. Struktur data

| Tabel | Isi utama |
| --- | --- |
| `users` | + kolom `role` (`admin` / `kasir`) |
| `categories` | nama kategori |
| `products` | barcode (unik, opsional), nama, harga jual, harga modal, stok, ambang menipis, status aktif, kategori |
| `sales` | no nota, kasir, **pelanggan**, **metode bayar**, **status lunas**, subtotal, diskon, total, bayar/DP, kembali, **jatuh tempo**, catatan |
| `sale_items` | salinan nama/harga jual/modal saat transaksi, qty, subtotal |
| `customers` | nama, telepon, alamat, batas kredit (kosong = tanpa batas), diblokir, catatan |
| `credit_payments` | pembayaran hutang: pelanggan, nota, kasir, jumlah, catatan |
| `stock_movements` | buku besar stok: produk, jenis (masuk/penjualan/retur/batal/penyesuaian), qty ±, sisa stok, modal, pemasok |
| `cash_sessions` | shift kasir: modal awal, buka/tutup, uang dihitung, seharusnya, selisih, setoran |
| `cash_movements` | kas masuk/keluar di luar penjualan, per shift |
| `settings` | kunci/nilai identitas toko (nama, alamat, telepon, kaki struk) |

Kolom tambahan: `sales.cash_session_id / voided_at / voided_by / void_reason /
refunded`, `sale_items.returned_qty`, `credit_payments.cash_session_id`.

Model: `User`, `Category`, `Product`, `Sale`, `SaleItem`, `Customer`,
`CreditPayment`, `StockMovement`, `CashSession`, `CashMovement`, `Setting`
— lengkap relasi + cast.

Seeder: 2 akun contoh, 3 pelanggan contoh, 11 produk dalam 4 kategori.

| Email | Password | Peran |
| --- | --- | --- |
| admin@kios.test | `password` | admin |
| kasir@kios.test | `password` | kasir |

---

## 3. Peran & hak akses

- Middleware alias `admin` (di `bootstrap/app.php`).
- **admin**: Dashboard, Kasir, Tutup kasir, Riwayat, Produk, Barang masuk,
  Kategori, Pelanggan, Piutang, Laporan, Pengguna, Pengaturan.
- **kasir**: Dashboard, Kasir, Tutup kasir. (Bisa jual kasbon dan menutup laci
  sendiri, tapi tak bisa kelola pelanggan / terima pembayaran hutang / batalkan
  nota / catat barang masuk.)
- `HandleInertiaRequests` membagikan `auth.user`, `auth.isAdmin`, flash, dan
  `alerts.lowStock` (jumlah produk stok menipis, untuk titik merah lonceng).

---

## 4. Fitur yang SUDAH jalan

### Kasir (`/pos`)

- Scan barcode / ketik nama; Enter = tambah otomatis bila barcode cocok atau
  hasil tunggal.
- Filter kategori (chip), grid produk dengan sisa stok.
- Keranjang: ubah qty, hapus baris, subtotal per baris.
- Diskon (nominal total), tombol nominal cepat, hitung kembalian.
- **Metode bayar: Tunai / Kasbon.** Kasbon → pilih pelanggan, DP opsional, jatuh
  tempo; ditolak bila pelanggan diblokir atau melebihi batas kredit.
- Simpan transaksi dalam **DB transaction** + `lockForUpdate`; harga diambil ulang
  dari DB; tolak bila stok kurang / bayar kurang (tunai); no nota otomatis
  `INVYYYYMMDD-0001`; stok berkurang otomatis.

### Struk (`/pos/struk/{sale}`)

- Gaya pita kertas thermal; tombol Cetak (dialog browser) + Transaksi baru.
- Blok kasbon: pelanggan, DP, sisa hutang, jatuh tempo, cap **LUNAS / BELUM LUNAS**.
- **Cetak disetel untuk printer thermal RPP02N** (kertas 58 mm, kepala cetak
  48 mm, 203 dpi). Struk dibuat selebar kertas dengan bantalan 5 mm kiri-kanan
  supaya isinya jatuh di bidang yang benar-benar tercetak; warna dan gerigi pita
  dipaksa hitam-putih karena kepala thermal tak mengenal warna.
- **Tinggi halaman diukur, bukan ditebak.** `@page { size: 58mm auto }` bukan
  CSS yang sah — `size` hanya menerima panjang, jadi seluruh deklarasinya
  dibuang peramban dan kertas balik ke ukuran driver (sering panjang tetap,
  memuntahkan kertas kosong tiap struk). `Receipt.vue` mengukur struk lewat
  salinan tersembunyi berlebar cetak, lalu menyuntik `@page { size: 58mm
  <tinggi>mm }` sesaat sebelum `window.print()`. Diukur dari salinan karena
  tata letak layar lebih lebar dan berhuruf lebih besar, jadi tingginya beda.
  Ditambah 10 mm untuk ruang sobek.

### Produk (`/products`) — admin

- CRUD lewat modal.
- Toolbar: cari nama/barcode, filter **kategori**, filter **status stok**
  (semua / menipis / habis), **urutkan** (nama / harga / stok).
- Tabel + `StockBadge` (badge titik-warna) + paginasi ("Menampilkan x–y dari z").

### Kategori (`/categories`) — admin

- Tambah, ubah inline, hapus; menampilkan jumlah produk.

### Pelanggan (`/customers`) — admin

- CRUD (batas kredit, blokir, catatan), cari nama/telepon, paginasi, sisa hutang
  per pelanggan.
- **Detail** (`/customers/{id}`): ringkasan hutang, nota belum lunas + jatuh
  tempo, riwayat pembayaran, **form terima pembayaran** (dibagi FIFO ke nota
  terlama; nota jadi "lunas" saat sisa 0).

### Piutang (`/piutang`) — admin

- Total piutang, **umur hutang** (0–7 / 8–30 / >30 hari), rekap per pelanggan,
  daftar nota belum lunas + penanda jatuh tempo.

### Laporan (`/reports`) — admin

- Filter rentang tanggal + preset (hari ini / 7 / 30 hari).
- Ringkasan omzet, laba kotor, jumlah transaksi, total diskon.
- Grafik batang omzet harian, produk terlaris, transaksi terakhir.

### Riwayat transaksi (`/sales`) — admin

- Daftar semua nota + paginasi 20; cari no nota / pelanggan / catatan; saring
  tanggal, kasir, dan status (tunai / kasbon / lunas / belum lunas / batal).
- Kartu ringkasan hasil saring: jumlah nota + nilai bersih (setelah retur).
- **Detail nota** (`/sales/{id}`): rincian barang, ringkasan uang, pelunasan.
  - **Ubah keterangan** — catatan, pelanggan & jatuh tempo (khusus kasbon).
    Nilai uang/barang sengaja tak bisa disunting.
  - **Batalkan nota** — stok kembali, hutang hapus, uang tunai yang sudah
    diterima dicatat keluar laci, nota tetap tersimpan bercap "Dibatalkan".
    Ditolak bila nota sudah pernah menerima pelunasan hutang.
  - **Retur sebagian** — pilih qty per baris; stok balik, `returned_qty` naik,
    diskon nota diprorata, nilai nota & hutang kasbon menyusut. Bila pelanggan
    jadi kelebihan bayar, uangnya dikembalikan **dan** kolom `paid` nota ikut
    dikoreksi — kalau tidak, DP yang sudah keluar tetap terhitung sebagai uang
    masuk di rekap laci dan sisa hutang pelanggan. Kelebihan yang berasal dari
    pelunasan hutang tak bisa dikoreksi di barisnya (catatan riwayat), jadi
    dicatat sebagai kas keluar.

### Barang masuk (`/stok`) — admin

- Form multi-baris: pilih produk, qty, harga modal, pemasok, catatan. Jenis
  **Barang masuk** (hanya plus) atau **Penyesuaian** (boleh minus, ditolak bila
  membuat stok negatif). Harga modal produk ikut diperbarui saat barang masuk.
- Histori seluruh pergerakan stok (masuk / penjualan / retur / batal /
  penyesuaian) + sisa stok setiap gerakan; saring produk, jenis, tanggal.
- Kartu: qty masuk hari ini, nilai belanja hari ini, produk perlu restok.

### Tutup kasir (`/shift`) — kasir & admin

- Buka shift dengan modal awal laci; nota dan pelunasan hutang otomatis
  menempel ke shift yang sedang terbuka.
- Catat **kas masuk / keluar** di luar penjualan (beli plastik, ambil kembalian).
  Batal & retur mencatat kas keluar **hanya bila shift asal nota sudah
  ditutup**; selama shift asal masih terbuka rekapnya mengoreksi diri sendiri
  (nota batal keluar dari rekap, retur memotong nilai bersih & DP), jadi
  mencatatnya lagi akan memotong laci dua kali — termasuk saat yang membatalkan
  akun lain dengan shift sendiri.
- Hitungan laci: modal awal + tunai + DP kasbon + pelunasan + kas masuk − kas
  keluar. Nota kasbon hanya menyumbang DP-nya; nota batal tak dihitung.
- Tutup shift: isi uang fisik → **selisih** tersimpan, plus setoran & catatan.
  Riwayat shift (admin lihat semua, kasir lihat miliknya).
- **Ganti hari menutup shift sendiri.** Shift yang lupa ditutup dikunci pada
  `23:59:59` hari itu, ditandai `auto_closed`, dan `expected_cash` disimpan.
  `counted_cash` & `difference` sengaja **null** (bukan 0) karena uangnya tak
  pernah dihitung — di riwayat tampil "—" dan "tak dihitung" dengan lencana
  **OTOMATIS**. Halaman Tutup kasir memberi tahu lewat spanduk amber.
  Dua lapis pengaman: perintah terjadwal `shift:tutup-otomatis` (00:01) dan
  penguncian susulan di `CashSession::sealStale()` yang jalan saat aplikasi
  dibuka lagi — jadi tetap benar walau komputer mati tengah malam.
- **Shift sifatnya opsional — kasir tidak pernah terkunci.** Kios dijaga
  pemiliknya sendiri, jadi mewajibkan buka/tutup laci hanya menambah langkah
  tanpa manfaat pengawasan. Kalau kebetulan ada shift terbuka, nota menempel ke
  sana untuk rekap laci; kalau tidak, penjualan tetap jalan dan `cash_session_id`
  dibiarkan kosong.
- Shift terikat **per akun** (`user_id`), jadi shift yang dibuka akun kasir tidak
  terlihat oleh akun admin. Kalau nanti dipakai berdua dengan satu laci
  bersama, model ini yang perlu diubah lebih dulu.

### Pengaturan toko (`/pengaturan`) — admin

- Nama toko, alamat, telepon, kaki struk — tersimpan di tabel `settings`
  (di-cache) dan dibagikan ke semua halaman lewat `HandleInertiaRequests`.
- Dipakai di struk, wordmark sidebar, dan top bar ponsel. Ada pratinjau struk.

### Pengguna (`/users`) — admin

- CRUD akun, atur peran, ganti password opsional; tak bisa hapus akun sendiri.

### Dashboard (`/dashboard`)

- 4 kartu metrik: omzet hari ini, transaksi hari ini, jumlah produk, stok menipis.
- **Perlu restok**: tautan "Lihat semua", baris pakai thumbnail placeholder + ID.
- **Aktivitas Toko**: grafik batang omzet 7 hari (hari kosong terisi 0);
  empty-state + ornamen gelombang bila belum ada transaksi.
- **Informasi Sistem**: Basis data [Terhubung], Waktu server, Transaksi terakhir,
  tombol "Pengaturan akun".

### Profil (`/profile`)

- Kartu identitas (inisial, peran, tanggal dibuat), ubah nama/email, ganti kata
  sandi, hapus akun. Sudah berbahasa Indonesia & ikut `design.md`.
- **Admin terakhir tak bisa menghapus akunnya sendiri** — kalau tidak, toko tak
  ada yang bisa mengelola.

### Halaman error

- Satu komponen `Error.vue` untuk 403 / 404 / 419 / 429 / 500 / 503, pesan
  ditulis untuk penjaga toko (bukan istilah teknis), warna kartu ikut jenis
  galat. Dipasang di `bootstrap/app.php` lewat `withExceptions()->respond()`.
- Galat 500 tetap menampilkan jejak Laravel selama `APP_DEBUG=true`; permintaan
  JSON tetap dapat JSON.

### Lambang & favicon

- Lambang: pita struk putih di atas kotak hijau. Berkas: `public/favicon.svg`,
  `favicon.ico` (16/32/48), `apple-touch-icon.png`, `icon-192/512.png`,
  `site.webmanifest` (bisa dipasang ke layar utama tablet).
- Komponen `AppLogo.vue` (varian `solid` / `inverse`) dipakai di sidebar, top bar
  ponsel, halaman masuk, dan halaman error. Judul tab & wordmark ikut nama toko
  dari Pengaturan.

### Login

- Panel kiri = foto `public/images/bg-login.jpg` + overlay gradien hijau +
  lambang & nama toko. Panel kanan = form.

---

## 5. Desain antarmuka

Acuan lengkapnya ada di **[`design.md`](design.md)** (bagian prosa; front-matter
YAML diabaikan karena bentrok). Yang dicatat di sini hanya **hasil penerapannya**
di kode — nilai yang benar-benar dipakai dan tempatnya.

- **Font**: Hanken Grotesk (UI) + JetBrains Mono (kelas `.num`: semua angka uang /
  stok / nomor nota). Dimuat via bunny.net.
- **Warna** (`tailwind.config.js`, nama key lama dipertahankan): latar `#F4F6F5`,
  kartu `#FFFFFF`, tinta `#121212` (+ abu `#64748B` / `#94A3B8`), border
  `#E2E8F0`, primary `#1E6B4F` (hover `#1B6049`), amber `#F4A261` (teks gelap
  `#7A3D06`), teal `#2A9D8F`, error `#BA1A1A`, header tabel `#F1F5F9`, hover baris
  `#F0F7F4`.
- **Bentuk**: `rounded-card` 12px, `rounded-control` 6px, `rounded-xl` 12px (CTA).
- **Elevasi**: tonal + ghost border; bayangan kartu `0 4px 12px rgba(0,0,0,.03)`.
- **Tipografi**: utilitas `display-lg` / `headline-md/sm` / `body-lg/md` /
  `label-caps` / `data-mono`.
- **Kelas komponen** (`resources/css/app.css`): `.card .field .label
  .btn-primary/-outline/-alert/-ghost/-danger .link .chip .th .td .row-hover
  .num .tape`.
- **Komponen bersama**: `PageHeader` (judul + subjudul + slot aksi, di badan
  konten), `Pagination`, `StockBadge`, `AppLogo`, `Icon` (SVG garis 20px).
- **Aturan warna stok** ada di satu tempat: `resources/js/lib/stock.js`
  (`low` ≤ ambang → merah, `mid` ≤ 2× ambang → amber, sisanya hijau). Dipakai
  `StockBadge` di tabel Produk **dan** di kartu jualan halaman Kasir — di kasir
  angkanya sisa stok setelah dikurangi isi keranjang, jadi lencana berubah
  merah saat barang ditambahkan mendekati ambang.
- **Sidebar**: `lg` sidebar penuh 260px berlabel; `md` rail ikon 72px; `<md`
  **tab bar bawah**. Nav aktif = **blok hijau penuh**.
- **Top bar** (desktop, `AuthenticatedLayout`): kiri = **nama menu sidebar yang
  aktif**; kanan = **ikon lonceng** (titik merah bila ada stok menipis). Tanpa
  wordmark, tanpa ikon cari.
- **Menu akun**: Profil & Keluar di balik kartu nama pengguna (dropdown; di rail
  desktop membuka ke atas).
- **Ciri khas**: keranjang kasir & struk pakai gaya "pita kertas" yang sama.

### Penyimpangan sadar dari `design.md`

- `<768px` pakai **bottom tab bar**, bukan drawer.
- Nav aktif **blok hijau**, bukan pill 4px.
- Radius 12/6px, bukan 8/4px.
- Elemen di luar `design.md` yang dibuat: kartu Informasi Sistem, ornamen
  gelombang, top bar (label menu + lonceng).

---

## 6. Penjadwal (opsional, hanya kalau fitur shift dipakai)

Agar shift terkunci tepat tengah malam walau tak ada yang membuka aplikasi,
Task Scheduler Windows perlu menjalankan tiap menit:

```
D:\laragon\bin\php\php-8.x\php.exe D:\laragon\www\kios-BERKAH\artisan schedule:run
```

Tanpa itu aplikasi tetap benar — shift kemarin dikunci saat aplikasi pertama
kali dibuka keesokan harinya — hanya jam penutupannya yang tercatat mundur ke
`23:59:59` hari sebelumnya (memang begitu yang diinginkan).

## 7. Produksi

Aplikasi sudah jalan di server sendiri, bukan lagi hanya di Laragon.

| Hal | Isi |
| --- | --- |
| Alamat | `kios.madignet.site`, di belakang Cloudflare |
| Server | VPS aaPanel, aplikasi di `/www/wwwroot/kios` |
| PHP | 8.3, dijalankan sebagai user `www` |
| Lingkungan | `APP_ENV=production`, `APP_DEBUG=false` |
| Cara rilis | `bash deploy.sh` di folder aplikasi — pull, composer, build, migrasi, cache, izin akses |

Dua hal yang pernah menghentikan deploy dan sudah dipagari di `deploy.sh`:

1. **`chmod -R 755` seluruh folder** membuat 185 berkas tercatat "modified"
   (mode 644 jadi 755), lalu `git pull` menolak jalan. Sekarang skrip menyetel
   `core.fileMode false` dan menyetel izin per jenis: folder 755, berkas 644,
   775 hanya untuk `storage` dan `bootstrap/cache`.
2. **Menyeragamkan berkas ke 644 mencabut bit eksekusi `node_modules/.bin/vite`**
   sehingga `npm run build` mati. `.git`, `node_modules`, dan `vendor` kini
   dilewati — isinya urusan git, npm, dan composer.

## 8. Cara memeriksa tampilan di browser

Mesin ini **punya Microsoft Edge**, jadi tampilan bisa diperiksa sungguhan
(catatan lama yang bilang "tidak ada browser" sudah tidak berlaku):

```sh
php artisan serve --port=8123
npm install puppeteer-core          # di folder kerja sementara, bukan proyek
```

Jalankan skrip puppeteer-core dengan
`executablePath: "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe"`,
login sebagai `admin@kios.test`, lalu `page.screenshot()` tiap halaman. Ukuran
yang dipakai: 1440x900 (desktop), 820px (tablet), 390px (ponsel).

Cara ini menemukan tiga hal yang lolos dari `php artisan test`:

1. `/sales` **error 500 di MySQL** — query ringkasan mewarisi `sales.*` +
   `withCount` lalu dicampur agregat; ditolak `only_full_group_by`. SQLite (dipakai
   pengujian) membiarkannya.
2. **Laci terpotong dua kali** saat nota dibatalkan/diretur di shift yang sama:
   nilainya hilang dari rekap penjualan *dan* dicatat sebagai kas keluar.
3. Halaman error menawarkan "Ke halaman masuk" walau pengguna sudah login — pada
   404 halaman dirender di luar grup middleware `web`, jadi props `auth` kosong.
4. `/reports` **error 22003 di MySQL** begitu ada barang terjual rugi: kolom uang
   bertipe `unsigned`, jadi `price - cost` meluber alih-alih jadi minus (SQLite
   membiarkannya). Pengurangan yang boleh negatif kini dicor `CAST(... AS SIGNED)`
   — di `ReportController` (laba) dan `Customer::outstanding()` (nota lebih
   bayar). Perkaliannya ikut dicor, karena `SIGNED * UNSIGNED` balik jadi
   unsigned. Kalau nanti ada pengurangan kolom uang baru, ingat pola ini.

## 9. Pengujian

`php artisan test` — **81 lulus** (488 assertion).

- `PosTest`: pencatatan penjualan + pengurangan stok, tolak stok/bayar kurang,
  batas akses kasir.
- `KasbonTest`: kasbon buat hutang + kurangi stok, DP, wajib pelanggan, tolak
  lewat batas kredit / pelanggan diblokir, pelunasan FIFO + auto-lunas, tolak
  bayar melebihi sisa hutang.
- `PagesSmokeTest`: semua halaman utama (termasuk Riwayat, Barang masuk, Tutup
  kasir, Pengaturan) + detail pelanggan + detail nota + struk ter-render.
- `SaleCorrectionTest`: penjualan menulis jejak stok; batal mengembalikan stok
  & mengeluarkan nota dari laporan; tolak batal ganda; tolak batal nota yang
  sudah dicicil; retur sebagian & retur kasbon; tolak retur melebihi pembelian;
  ubah keterangan tak mengubah uang; kasir tak boleh membatalkan; pencarian
  riwayat per no nota; retur kasbon lebih bayar mengoreksi DP nota; kelebihan
  dari pelunasan dicatat kas keluar; batal nota shift lain yang masih terbuka
  tak memotong laci dua kali; batal nota dari shift yang sudah ditutup dicatat
  di shift berjalan; batal nota bekas retur hanya mengembalikan sisa uangnya.
- `ShiftGantiHariTest`: nota menempel ke shift yang kebetulan terbuka; nota
  setelah tengah malam tak masuk rekap kemarin; shift menginap terkunci di
  `23:59:59` dengan `counted_cash`/`difference` kosong; hari baru mulai dari nol
  & shift baru bisa dibuka; perintah terjadwal menutup shift semua kasir; shift
  hari ini tidak ikut ditutup; halaman Tutup kasir menampilkan pemberitahuan.
- `StockAndShiftTest`: barang masuk menambah stok & memperbarui modal; tolak qty
  minus; penyesuaian tak boleh bikin stok negatif; hitungan laci (tunai + DP +
  pelunasan − kas keluar); tutup shift menyimpan selisih; tolak dua shift
  sekaligus; kasir lain tak boleh menutup shift orang; pengaturan toko tersimpan
  & muncul di struk; kasir tak boleh mengubah pengaturan.

---

## 10. Belum dikerjakan

> Satu-satunya daftar pekerjaan proyek ini. `list.md` sudah dilebur ke sini
> supaya tak ada dua daftar yang saling menyalip.

### Perlu diputuskan pemilik

- [ ] **Nasib fitur shift / Tutup kasir.** Sekarang opsional dan tak mengunci
      apa pun. Kalau ternyata tak pernah dipakai, menu beserta tabel
      `cash_sessions` / `cash_movements` bisa dibuang agar aplikasi lebih ringkas.
- [ ] **Model laci bila nanti ada pegawai.** Shift kini terikat per akun; untuk
      satu laci yang dipakai bergantian, perlu diubah jadi satu shift per toko.
- [ ] **Pasang Task Scheduler** (`php artisan schedule:run` tiap menit) — hanya
      perlu kalau fitur shift dipakai. Perintahnya ada di bagian 6.

### Fitur toko

- [ ] Ubah kolom uang jadi bilangan bertanda (`bigInteger`, bukan
      `unsignedBigInteger`) supaya pengurangan tak perlu dicor satu per satu.
- [ ] Diskon per item + diskon persen (sekarang hanya nominal total).
- [ ] Multi-metode bayar (QRIS / transfer).
- [ ] Pajak / PPN opsional.
- [ ] Satuan ganda (pcs / dus) & harga grosir.
- [ ] Ekspor laporan Excel / PDF + tombolnya di halaman Laporan.
- [ ] Filter tambahan di Laporan: per kasir, per kategori.
- [ ] Cetak langsung ke printer thermal lewat ESC/POS (potong kertas otomatis,
      tanpa dialog cetak). Tata letak 58 mm sendiri sudah jadi — lihat bagian 4.
- [ ] Opsi QR / barcode nomor nota di struk.
- [ ] Log aktivitas / audit (siapa mengubah harga, stok, menghapus).
- [ ] Soft delete produk (sekarang hard delete).
- [ ] Backup & restore database.

### Tampilan

- [ ] Unggah logo sendiri lewat Pengaturan (lambang bawaan sudah terpasang).
- [ ] Grafik interaktif (Chart.js) menggantikan bar CSS.
- [ ] Upload foto produk; impor produk massal (CSV); cetak label barcode.
- [ ] Dialog konfirmasi hapus yang rapi (kini `confirm()` bawaan browser).
- [ ] Pencarian global (ikon cari sudah dihapus dari top bar).
- [ ] Panel notifikasi pada lonceng (kini hanya titik merah + tautan).
- [ ] Ringkas angka besar di kartu metrik (mis. `Rp1,2 jt`).
- [ ] Aksesibilitas: `aria-label` tombol ikon, perangkap fokus modal, urutan tab.
- [ ] Loading state / skeleton antar halaman.
- [ ] Kasir: parkir transaksi (hold bill), pintasan keyboard, mode offline.

### Rilis / operasional

- [ ] Ganti password akun contoh; bersihkan data seeder demo & nota uji coba.
- [ ] Feature test untuk `ProductController` / `CategoryController` /
      `UserController` / `ReportController`.
- [ ] Alur reset password (butuh SMTP) atau matikan; rapikan email verification.
- [ ] Rate limiting endpoint sensitif selain throttle login bawaan.
- [ ] **Uji di tablet / HP fisik** — tampilan sudah diperiksa lewat Edge pada
      lebar 1440 / 820 / 390 px, tapi belum disentuh di perangkat asli.
