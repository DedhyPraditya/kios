# Kios BERKAH — Aplikasi Kasir (POS)

Aplikasi kasir untuk toko kelontong: penjualan + struk, kasbon/piutang, stok
masuk-keluar, laporan, dan multi-pengguna.

Dibangun dengan **Laravel 13 + Inertia.js + Vue 3 + Tailwind CSS**, antarmuka
berbahasa Indonesia, uang dalam bilangan bulat Rupiah.

## Dokumentasi

Tiap berkas punya satu tugas — tidak ada isi yang diulang di dua tempat.

| Berkas | Untuk siapa | Isinya |
| --- | --- | --- |
| **[`PANDUAN.md`](PANDUAN.md)** | Penjaga toko | Cara memakai aplikasi sehari-hari, langkah demi langkah |
| **[`PROGRESS.md`](PROGRESS.md)** | Yang mengembangkan | Keputusan teknis, struktur data, fitur, pengujian, daftar pekerjaan |
| **[`design.md`](design.md)** | Yang mengembangkan | Acuan desain asli: warna, font, bentuk, tata letak |
| `README.md` (berkas ini) | Yang memasang | Cara memasang & menjalankan |

## Menjalankan (development)

```bash
composer install
npm install

cp .env.example .env          # kalau .env belum ada
php artisan key:generate

php artisan migrate --seed    # buat tabel + data contoh
npm run build                 # atau: npm run dev (mode pantau)
php artisan serve
```

Buka <http://127.0.0.1:8000> — otomatis diarahkan ke halaman Kasir (login dulu).
Lewat Laragon, situs juga tersedia di <http://kios-nizam.test>.

### Database

Aplikasi memakai **MySQL** (database `kios_nizam` lewat Laragon):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kios_nizam
DB_USERNAME=root
DB_PASSWORD=
```

Nyalakan MySQL dari panel Laragon, buat database `kios_nizam`, lalu
`php artisan migrate --seed`. Pengujian memakai SQLite di memori, jadi
`php artisan test` tak pernah menyentuh data asli.

### Akun contoh (dari seeder)

| Email | Password | Peran |
| --- | --- | --- |
| admin@kios.test | `password` | admin |
| kasir@kios.test | `password` | kasir |

> **Ganti kedua password ini sebelum dipakai jualan sungguhan** — lewat menu
> Profil atau Pengguna. Lihat [`PANDUAN.md`](PANDUAN.md) langkah 1.

## Uji

```bash
php artisan test
```

Rinciannya di [`PROGRESS.md`](PROGRESS.md) bagian 8.

## Cetak struk

Halaman struk punya tombol **Cetak** yang memanggil dialog cetak browser. Untuk
printer thermal 58 mm, atur ukuran kertas di dialog cetak itu. Cetak langsung
ke printer thermal (ESC/POS) belum dibuat.
